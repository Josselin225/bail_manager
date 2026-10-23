import secrets

from django.contrib.auth.hashers import make_password
from django.db import transaction
from django.db.models import Count, DecimalField, OuterRef, Prefetch, Q, Subquery, Sum, Value
from django.db.models.functions import Coalesce
from rest_framework import viewsets
from rest_framework.decorators import action
from rest_framework.exceptions import ValidationError
from rest_framework.response import Response

from apps.core.exceptions import RegleMetier
from apps.core.permissions import EstAdmin, SuppressionAdmin
from apps.core.serializers import FichierImageField
from apps.core.utils import journaliser
from apps.finances.models import CompteCourantBailleur, MouvementCaisse
from apps.locatif.models import Contrat
from apps.locatif.services import exiger_mandat_actif
from apps.patrimoine.mandats import informer_locataires_renouvellement

from apps.core.models import ClauseMandat, clauses_en_vigueur

from . import doublons
from .models import Bailleur, BailleurAcces, BienConfie, Document, Locataire, LocataireAcces, MandatGestion, Maison, PhotoMaison
from .serializers import (
    BailleurMiniSerializer, BailleurSerializer, DocumentSerializer, LocataireSerializer, MaisonSerializer,
    MandatSerializer,
)


def _somme(qs, champ="montant"):
    return Coalesce(
        Subquery(qs.values("bailleur").annotate(t=Sum(champ)).values("t")[:1]),
        Value(0), output_field=DecimalField(max_digits=14, decimal_places=0),
    )



def _controle_doublons(vue, ser, modele, champ_piece, libelle, lien):
    """Avant d'enregistrer : doublon probable = confirmation demandée (création, ou modification d'un identifiant)."""
    instance = ser.instance
    donnees = {k: ser.validated_data.get(k, getattr(instance, k, "") if instance else "") for k in ("nom", "telephone1", "telephone2", "email", champ_piece)}
    if instance and not any(k in ser.validated_data and ser.validated_data[k] != getattr(instance, k) for k in ("telephone1", "telephone2", "email", champ_piece)):
        return  # aucun identifiant modifié : pas de nouvelle vérification
    confirme = str(vue.request.data.get("confirmer_doublon", "")).lower() in ("1", "true", "oui")
    doublons.exiger_confirmation(modele, donnees, champ_piece, instance.pk if instance else None, confirme, libelle, lien)


def _recherche_doublons(request, modele, champ_piece, lien):
    d = {k: request.query_params.get(k, "") for k in ("nom", "telephone1", "telephone2", "email", champ_piece)}
    return Response(doublons.chercher(modele, d, champ_piece, request.query_params.get("exclure") or None, lien))


def confier(maison, mandat):
    """Rattache un bien au mandat en vigueur (liste « Biens confiés » du mandat, datée)."""
    if mandat is not None:
        from apps.core.utils import aujourdhui

        BienConfie.objects.get_or_create(mandat=mandat, maison=maison, defaults={"confie_le": aujourdhui()})

class BailleurViewSet(viewsets.ModelViewSet):
    serializer_class = BailleurSerializer
    permission_classes = [SuppressionAdmin]
    search_fields = ["nom", "code_bailleur", "telephone1", "telephone2", "email"]
    ordering_fields = ["nom", "created_at", "solde"]

    def get_queryset(self):
        return Bailleur.objects.annotate(
            solde=_somme(CompteCourantBailleur.objects.filter(bailleur=OuterRef("pk"))),
            commissions=_somme(MouvementCaisse.objects.filter(bailleur=OuterRef("pk"), type_mouvement="Commission Gestion")),
            nb_maisons=Count("maisons", distinct=True),
            nb_occupees=Count("maisons", filter=Q(maisons__statut="occupe"), distinct=True),
        ).prefetch_related("mandats").order_by("nom")

    @action(detail=False, pagination_class=None)
    def options(self, request):
        """Liste courte pour les menus déroulants."""
        return Response(BailleurMiniSerializer(Bailleur.objects.prefetch_related("mandats"), many=True).data)

    @action(detail=False, pagination_class=None)
    def doublons(self, request):
        """Bailleurs qui ressemblent à la saisie en cours (nom, téléphone, CNI, e-mail)."""
        return _recherche_doublons(request, Bailleur, "numero_cni", "/bailleurs/")

    @action(detail=True, methods=["post"], permission_classes=[EstAdmin], url_path="generer-code")
    def generer_code(self, request, pk=None):
        """Code d'accès à l'espace bailleur : affiché une seule fois, stocké haché."""
        b = self.get_object()
        if not b.telephone1:
            raise RegleMetier("Renseignez d'abord le téléphone du bailleur : il sert d'identifiant.")
        code = secrets.token_hex(4).upper()
        BailleurAcces.objects.update_or_create(bailleur=b, defaults={"code_acces": make_password(code), "actif": True})
        journaliser(request, "Code accès bailleur", f"Code généré pour {b.nom}")
        return Response({"code": code, "telephone": b.telephone1, "nom": b.nom})

    @action(detail=True, methods=["post"], permission_classes=[EstAdmin], url_path="desactiver-acces")
    def desactiver_acces(self, request, pk=None):
        b = self.get_object()
        BailleurAcces.objects.filter(bailleur=b).update(actif=False)
        journaliser(request, "Code accès bailleur", f"Accès désactivé pour {b.nom}")
        return Response({"ok": True})

    def perform_create(self, ser):
        _controle_doublons(self, ser, Bailleur, "numero_cni", "le bailleur", "/bailleurs/")
        b = ser.save()
        journaliser(self.request, "Création bailleur", f"{b.nom} ({b.code_bailleur})")

    def perform_update(self, ser):
        _controle_doublons(self, ser, Bailleur, "numero_cni", "le bailleur", "/bailleurs/")
        b = ser.save()
        journaliser(self.request, "Modification bailleur", f"{b.nom} ({b.code_bailleur})")

    def perform_destroy(self, b):
        if b.maisons.exists():
            raise RegleMetier("Suppression impossible : ce bailleur possède des biens enregistrés.")
        if b.operations.exists():
            raise RegleMetier("Suppression impossible : ce bailleur a un historique financier.")
        journaliser(self.request, "Suppression bailleur", f"{b.nom} ({b.code_bailleur})")
        b.delete()


class MandatViewSet(viewsets.ModelViewSet):
    serializer_class = MandatSerializer
    permission_classes = [SuppressionAdmin]
    filterset_fields = ["bailleur", "statut"]
    pagination_class = None

    def get_queryset(self):
        return MandatGestion.objects.select_related("bailleur")

    @transaction.atomic
    def perform_create(self, ser):
        # Un seul mandat actif par bailleur : l'enregistrement d'un nouveau mandat
        # (renouvellement) résilie le précédent, comme dans la version PHP.
        b = ser.validated_data["bailleur"]
        renouvellement = MandatGestion.objects.filter(bailleur=b, statut="actif").exists()
        MandatGestion.objects.filter(bailleur=b, statut="actif").update(statut="resilie")
        m = ser.save(clauses_signees=clauses_en_vigueur(ClauseMandat))
        for maison in b.maisons.all():
            BienConfie.objects.get_or_create(mandat=m, maison=maison, defaults={"confie_le": m.date_debut})
        journaliser(self.request, "Mandat de gestion", f"Mandat {m.reference} enregistré pour {b.nom} — {m.taux_commission}%")
        if renouvellement:
            n = informer_locataires_renouvellement(m)
            if n:
                journaliser(self.request, "Mandat de gestion", f"{n} locataire(s) informé(s) du renouvellement du mandat {m.reference}")

    def perform_update(self, ser):
        m = ser.save()
        journaliser(self.request, "Mandat de gestion", f"Mandat #{m.pk} modifié ({m.bailleur.nom})")

    @action(detail=True, methods=["post"], permission_classes=[EstAdmin])
    def resilier(self, request, pk=None):
        m = self.get_object()
        if m.statut != "actif":
            raise RegleMetier("Ce mandat n'est pas actif.")
        if Contrat.objects.filter(maison__bailleur=m.bailleur, statut_contrat="actif").exists() and not request.data.get("forcer"):
            raise RegleMetier(
                "Ce bailleur a encore des contrats de bail actifs. Confirmez pour résilier tout de même le mandat.",
                code="confirmation_requise",
            )
        m.statut = "resilie"
        m.save(update_fields=["statut"])
        journaliser(request, "Mandat de gestion", f"Mandat #{m.pk} résilié ({m.bailleur.nom})")
        return Response(MandatSerializer(m, context={"request": request}).data)


PHOTOS_MAX = 30


class MaisonViewSet(viewsets.ModelViewSet):
    serializer_class = MaisonSerializer
    permission_classes = [SuppressionAdmin]
    filterset_fields = ["statut", "bailleur", "type_maison"]
    search_fields = ["designation", "adresse", "type_maison", "bailleur__nom"]
    ordering_fields = ["designation", "loyer", "created_at"]

    def get_queryset(self):
        return Maison.objects.select_related("bailleur").prefetch_related(
            "photos", Prefetch("contrats", queryset=Contrat.objects.filter(statut_contrat="actif").select_related("locataire"))
        )

    def _fiche(self, m):
        return Response(MaisonSerializer(self.get_queryset().get(pk=m.pk), context={"request": self.request}).data)

    @action(detail=True, methods=["post"])
    def photos(self, request, pk=None):
        """Ajoute une ou plusieurs photos (champ multipart « photos »), à la suite des existantes."""
        m = self.get_object()
        fichiers = request.FILES.getlist("photos")
        if not fichiers:
            raise RegleMetier("Choisissez au moins une photo.")
        if m.photos.count() + len(fichiers) > PHOTOS_MAX:
            raise RegleMetier(f"{PHOTOS_MAX} photos au maximum par maison.")
        champ = FichierImageField(prefixe="maison")       # contrôle Pillow + signature, comme les autres images
        valides = []
        for f in fichiers:
            try:
                valides.append(champ.run_validation(f))
            except ValidationError:
                raise RegleMetier(f"« {f.name} » n'est pas une image valide (JPG, PNG, GIF ou WebP).")
        ordre = (m.photos.order_by("-ordre").values_list("ordre", flat=True).first() or 0) + 1
        for i, f in enumerate(valides):
            PhotoMaison.objects.create(maison=m, image=f, ordre=ordre + i)
        journaliser(request, "Photos maison", f"{m.designation} — {len(valides)} photo(s) ajoutée(s)")
        return self._fiche(m)

    @action(detail=True, methods=["post"], url_path=r"photos/(?P<photo_id>\d+)/supprimer")
    def supprimer_photo(self, request, pk=None, photo_id=None):
        m = self.get_object()
        p = m.photos.filter(pk=photo_id).first()
        if p is None:
            raise RegleMetier("Photo introuvable.")
        p.image.delete(save=False)
        p.delete()
        journaliser(request, "Photos maison", f"{m.designation} — photo supprimée")
        return self._fiche(m)

    @action(detail=True, methods=["post"], url_path=r"photos/(?P<photo_id>\d+)/principale")
    def photo_principale(self, request, pk=None, photo_id=None):
        """Place la photo en tête : c'est elle qui illustre la maison dans les listes et sur le site."""
        m = self.get_object()
        photos = list(m.photos.all())
        choisie = next((p for p in photos if p.pk == int(photo_id)), None)
        if choisie is None:
            raise RegleMetier("Photo introuvable.")
        for i, p in enumerate([choisie] + [p for p in photos if p is not choisie]):
            if p.ordre != i:
                p.ordre = i
                p.save(update_fields=["ordre"])
        return self._fiche(m)

    @action(detail=False)
    def types(self, request):
        types = Maison.objects.exclude(type_maison="").values_list("type_maison", flat=True).distinct()
        base = ["Studio", "Chambre salon", "2 pièces", "3 pièces", "4 pièces", "Villa", "Duplex", "Magasin", "Bureau"]
        return Response(sorted(set(base) | set(types)))

    def perform_create(self, ser):
        from apps.core.licence import verifier_limite

        verifier_limite("biens", Maison.objects.count())
        mandat = exiger_mandat_actif(ser.validated_data["bailleur"])
        m = ser.save()
        confier(m, mandat)
        journaliser(self.request, "Création maison", f"{m.designation} — bailleur {m.bailleur.nom}")

    def perform_update(self, ser):
        nouveau = ser.validated_data.get("bailleur")
        mandat = exiger_mandat_actif(nouveau) if nouveau and nouveau != ser.instance.bailleur else None
        loyer = ser.validated_data.get("loyer")
        if loyer is not None and loyer != ser.instance.loyer and ser.instance.contrats.filter(statut_contrat="actif").exists():
            raise RegleMetier(
                "Ce bien est loué : son loyer ne se modifie que par une révision du loyer sur le contrat en cours, "
                "à la demande du bailleur."
            )
        m = ser.save()
        if mandat:
            confier(m, mandat)
        journaliser(self.request, "Modification maison", m.designation)

    def perform_destroy(self, m):
        if m.statut == "occupe":
            raise RegleMetier("Maison occupée : résiliez d'abord le contrat en cours.")
        if m.contrats.exists():
            raise RegleMetier("Suppression impossible : cette maison a un historique de contrats.")
        journaliser(self.request, "Suppression maison", m.designation)
        for p in m.photos.all():
            p.image.delete(save=False)
        m.delete()


class LocataireViewSet(viewsets.ModelViewSet):
    serializer_class = LocataireSerializer
    permission_classes = [SuppressionAdmin]
    search_fields = ["nom", "telephone1", "telephone2", "email", "piece_identite"]
    ordering_fields = ["nom", "created_at"]

    def get_queryset(self):
        qs = Locataire.objects.select_related("acces").prefetch_related(
            Prefetch("contrats", queryset=Contrat.objects.select_related("maison"))
        )
        statut = self.request.query_params.get("statut")
        if statut == "actif":
            qs = qs.filter(contrats__statut_contrat="actif").distinct()
        elif statut == "sans_contrat":
            qs = qs.exclude(contrats__statut_contrat="actif")
        return qs

    @action(detail=False, pagination_class=None)
    def doublons(self, request):
        """Locataires qui ressemblent à la saisie en cours (nom, téléphone, pièce d'identité, e-mail)."""
        return _recherche_doublons(request, Locataire, "piece_identite", "/locataires/")

    @action(detail=False, pagination_class=None)
    def options(self, request):
        qs = Locataire.objects.order_by("nom").values("id", "nom", "telephone1")
        if request.query_params.get("sans_contrat"):
            qs = qs.exclude(contrats__statut_contrat="actif")
        return Response(list(qs))

    def perform_create(self, ser):
        _controle_doublons(self, ser, Locataire, "piece_identite", "le locataire", "/locataires/")
        l = ser.save()
        journaliser(self.request, "Création locataire", l.nom)

    def perform_update(self, ser):
        _controle_doublons(self, ser, Locataire, "piece_identite", "le locataire", "/locataires/")
        l = ser.save()
        journaliser(self.request, "Modification locataire", l.nom)

    def perform_destroy(self, l):
        if l.contrats.exists():
            raise RegleMetier("Suppression impossible : ce locataire a (ou a eu) un contrat de bail.")
        journaliser(self.request, "Suppression locataire", l.nom)
        for champ in ("photo_locataire", "photo_cni_recto", "photo_cni_verso"):
            f = getattr(l, champ)
            if f:
                f.delete(save=False)
        l.delete()

    @action(detail=True, methods=["post"], permission_classes=[EstAdmin], url_path="generer-code")
    def generer_code(self, request, pk=None):
        """Code d'accès à l'espace locataire : affiché une seule fois, stocké haché."""
        loc = self.get_object()
        if not loc.telephone1:
            raise RegleMetier("Renseignez d'abord le téléphone du locataire : il sert d'identifiant.")
        code = secrets.token_hex(4).upper()
        LocataireAcces.objects.update_or_create(locataire=loc, defaults={"code_acces": make_password(code), "actif": True})
        journaliser(request, "Code accès locataire", f"Code généré pour {loc.nom}")
        return Response({"code": code, "telephone": loc.telephone1, "nom": loc.nom})

    @action(detail=True, methods=["post"], permission_classes=[EstAdmin], url_path="desactiver-acces")
    def desactiver_acces(self, request, pk=None):
        loc = self.get_object()
        LocataireAcces.objects.filter(locataire=loc).update(actif=False)
        journaliser(request, "Code accès locataire", f"Accès désactivé pour {loc.nom}")
        return Response({"ok": True})


class DocumentViewSet(viewsets.ModelViewSet):
    serializer_class = DocumentSerializer
    permission_classes = [SuppressionAdmin]
    filterset_fields = ["entity_type", "entity_id"]
    search_fields = ["nom_original"]
    http_method_names = ["get", "post", "delete"]

    def get_queryset(self):
        return Document.objects.select_related("uploaded_by")

    def perform_create(self, ser):
        d = ser.save(uploaded_by=self.request.user)
        journaliser(self.request, "Document", f"Ajout de « {d.nom_original} » ({d.entity_type} #{d.entity_id})")

    def perform_destroy(self, d):
        journaliser(self.request, "Document", f"Suppression de « {d.nom_original} »")
        d.fichier.delete(save=False)
        d.delete()
