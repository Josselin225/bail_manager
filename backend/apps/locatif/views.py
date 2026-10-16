from datetime import date, datetime

from django.db.models import DecimalField, OuterRef, Q, Subquery, Sum, Value
from django.db.models.functions import Coalesce
from django.shortcuts import get_object_or_404
from django.utils import timezone
from django_filters import rest_framework as filters
from rest_framework import mixins, status, viewsets
from rest_framework.decorators import action
from rest_framework.response import Response

from apps.core.exceptions import RegleMetier
from apps.core.models import ClauseContrat
from apps.core.permissions import EstAdmin, SuppressionAdmin
from apps.core.utils import aujourdhui, journaliser, libelle_periode
from apps.patrimoine.models import Locataire, Maison

from . import services
from .models import ChargeLocative, Contrat, Encaissement, MouvementCaution, RevisionLoyer
from .serializers import (
    ChargeSerializer, ContratCreationSerializer, ContratSerializer, EncaissementCreationSerializer,
    EncaissementSerializer, MouvementCautionSerializer, RevisionSerializer,
)


def _annoter_caution(qs):
    return qs.annotate(
        total_mvts_caution=Coalesce(
            Subquery(
                MouvementCaution.objects.filter(contrat=OuterRef("pk")).values("contrat").annotate(t=Sum("montant")).values("t")[:1]
            ),
            Value(0), output_field=DecimalField(max_digits=14, decimal_places=0),
        )
    )


class ContratFiltre(filters.FilterSet):
    statut_paiement = filters.CharFilter(method="filtrer_paiement")
    bailleur = filters.NumberFilter(field_name="maison__bailleur")

    class Meta:
        model = Contrat
        fields = ["statut_contrat", "maison", "locataire", "bailleur"]

    def filtrer_paiement(self, qs, name, value):
        j = aujourdhui()
        if value == "en_retard":
            return qs.filter(statut_contrat="actif", date_prochain_loyer__lt=j)
        if value == "echeance_proche":
            return qs.filter(statut_contrat="actif", date_prochain_loyer__gte=j, date_prochain_loyer__lte=j + timezone.timedelta(days=5))
        if value == "a_jour":
            return qs.filter(statut_contrat="actif", date_prochain_loyer__gt=j + timezone.timedelta(days=5))
        return qs


class ContratViewSet(mixins.ListModelMixin, mixins.RetrieveModelMixin, mixins.CreateModelMixin, viewsets.GenericViewSet):
    serializer_class = ContratSerializer
    filterset_class = ContratFiltre
    search_fields = ["locataire__nom", "maison__designation", "maison__bailleur__nom", "locataire__telephone1"]
    ordering_fields = ["date_debut", "date_prochain_loyer", "loyer_mensuel", "created_at"]

    def get_queryset(self):
        qs = Contrat.objects.select_related("maison__bailleur", "locataire")
        return _annoter_caution(qs)

    def retrieve(self, request, *args, **kwargs):
        c = self.get_object()
        data = ContratSerializer(c, context={"request": request}).data
        data["echeancier"] = [p.as_dict() for p in services.echeancier(c, apres=2)]
        data["impayes"] = [p.as_dict() for p in services.periodes_impayees(c)]
        data["total_impaye"] = sum(p["reste"] for p in data["impayes"])
        data["encaissements"] = EncaissementSerializer(c.encaissements.select_related("enregistre_par", "contrat__locataire", "contrat__maison__bailleur"), many=True).data
        data["revisions"] = RevisionSerializer(c.revisions.all(), many=True).data
        data["charges"] = ChargeSerializer(c.charges.all(), many=True).data
        data["mouvements_caution"] = MouvementCautionSerializer(c.mouvements_caution.all(), many=True).data
        data["taux_commission"] = services.taux_commission_pour(c.maison.bailleur)
        return Response(data)

    def create(self, request, *args, **kwargs):
        ser = ContratCreationSerializer(data=request.data)
        ser.is_valid(raise_exception=True)
        d = ser.validated_data
        maison = get_object_or_404(Maison, pk=d.pop("maison"))
        locataire = get_object_or_404(Locataire, pk=d.pop("locataire"))
        contrat = services.creer_contrat(maison=maison, locataire=locataire, request=request, **d)
        return Response(ContratSerializer(_annoter_caution(Contrat.objects.filter(pk=contrat.pk)).get()).data, status=status.HTTP_201_CREATED)

    @action(detail=True, methods=["post"])
    def echeance(self, request, pk=None):
        valeur = request.data.get("date_fin") or None
        d = datetime.strptime(valeur, "%Y-%m-%d").date() if valeur else None
        c = services.definir_echeance(self.get_object(), d, request)
        return Response(ContratSerializer(c).data)

    @action(detail=True, methods=["post"], permission_classes=[EstAdmin])
    def reviser(self, request, pk=None):
        effet = request.data.get("date_effet")
        rev = services.reviser_loyer(
            self.get_object(), request.data.get("nouveau_loyer"), request.data.get("motif", ""),
            datetime.strptime(effet, "%Y-%m-%d").date() if effet else None, request,
            origine=request.data.get("origine") or RevisionLoyer.Origine.BAILLEUR,
        )
        return Response(RevisionSerializer(rev).data, status=201)

    @action(detail=True, methods=["post"])
    def caution(self, request, pk=None):
        """Retenue sur caution, le contrat restant actif."""
        res = services.traiter_caution(
            self.get_object(), retenue_reparation=request.data.get("retenue_reparation") or 0,
            retenue_loyer=request.data.get("retenue_loyer") or 0, motif=request.data.get("motif", ""), request=request,
        )
        return Response(res)

    @action(detail=True, methods=["post"], permission_classes=[EstAdmin])
    def resilier(self, request, pk=None):
        effet = request.data.get("date_effet")
        res = services.traiter_caution(
            self.get_object(), retenue_reparation=request.data.get("retenue_reparation") or 0,
            retenue_loyer=request.data.get("retenue_loyer") or 0, cloturer=True,
            motif=request.data.get("motif", ""),
            date_effet=datetime.strptime(effet, "%Y-%m-%d").date() if effet else None, request=request,
        )
        return Response(res)

    @action(detail=True)
    def situation(self, request, pk=None):
        """Remplace get_reliquat.php + check_dette_anterieure.php : état d'une période."""
        c = self.get_object()
        mois = int(request.query_params.get("mois", aujourdhui().month))
        annee = int(request.query_params.get("annee", aujourdhui().year))
        loyer = services.loyer_pour_periode(c, mois, annee)
        deja = services.total_paye(c, mois, annee)
        dette = services.verifier_dette_anterieure(c, mois, annee)
        prochaine = None
        for p in services.echeancier(c, apres=24):
            if p.reste > 0:
                prochaine = p.as_dict()
                break
        return Response({
            "periode": libelle_periode(mois, annee), "loyer": loyer, "deja_paye": deja,
            "reste": max(0, loyer - deja), "dette": dette.as_dict() if dette else None,
            "prochaine_periode_due": prochaine,
            "anterieure_au_bail": (annee * 12 + mois) < ((c.debut_facturation or c.date_debut).year * 12 + (c.debut_facturation or c.date_debut).month),
            "mandat_echu": c.maison.bailleur.mandat_actif() is None,
        })

    @action(detail=True)
    def impression(self, request, pk=None):
        """Données du contrat de bail imprimable (clauses comprises)."""
        c = self.get_object()
        data = ContratSerializer(c).data
        loc, b = c.locataire, c.maison.bailleur
        data["locataire_detail"] = {"nom": loc.nom, "telephone": loc.telephone1, "piece_identite": loc.piece_identite, "email": loc.email, "profession": loc.profession}
        data["bailleur_detail"] = {"nom": b.nom, "code": b.code_bailleur, "telephone": b.telephone1, "adresse": b.adresse, "cni": b.numero_cni}
        m = c.mandat
        data["mandat_detail"] = {
            "reference": m.reference, "date_signature": m.date_signature, "date_debut": m.date_debut,
            "duree": m.duree, "tacite_reconduction": m.tacite_reconduction, "date_fin": m.date_fin,
        } if m else None
        data["clauses"] = list(ClauseContrat.objects.filter(actif=True).values("titre", "contenu"))
        data["restitution"] = MouvementCautionSerializer(c.mouvements_caution.filter(type_mouvement="restitution"), many=True).data
        data["retenues"] = MouvementCautionSerializer(c.mouvements_caution.filter(type_mouvement="retenue"), many=True).data
        return Response(data)


class EncaissementFiltre(filters.FilterSet):
    du = filters.DateFilter(field_name="date_encaissement", lookup_expr="date__gte")
    au = filters.DateFilter(field_name="date_encaissement", lookup_expr="date__lte")
    bailleur = filters.NumberFilter(field_name="contrat__maison__bailleur")
    locataire = filters.NumberFilter(field_name="contrat__locataire")

    class Meta:
        model = Encaissement
        fields = ["contrat", "mode_paiement", "origine", "periode_mois", "periode_annee", "bailleur", "locataire"]


class EncaissementViewSet(mixins.ListModelMixin, mixins.RetrieveModelMixin, mixins.CreateModelMixin, viewsets.GenericViewSet):
    """Pas de modification ni suppression : un reçu émis est définitif (traçabilité)."""

    serializer_class = EncaissementSerializer
    filterset_class = EncaissementFiltre
    search_fields = ["reference_recu", "contrat__locataire__nom", "contrat__maison__designation"]
    ordering_fields = ["date_encaissement", "montant_recu"]

    def get_queryset(self):
        return Encaissement.objects.select_related("contrat__locataire", "contrat__maison__bailleur", "enregistre_par")

    def create(self, request, *args, **kwargs):
        ser = EncaissementCreationSerializer(data=request.data)
        ser.is_valid(raise_exception=True)
        d = ser.validated_data
        contrat = get_object_or_404(Contrat, pk=d["contrat"])
        enc = services.enregistrer_encaissement(
            contrat, d["montant"], d["periode_mois"], d["periode_annee"], d["mode_paiement"], request,
            observation=d.get("observation", ""),
        )
        from .notifications import envoyer_quittance

        envoyer_quittance(enc)
        return Response(EncaissementSerializer(enc).data, status=201)

    @action(detail=False)
    def totaux(self, request):
        qs = self.filter_queryset(self.get_queryset())
        agg = qs.aggregate(montant=Sum("montant_recu"), commission=Sum("commission"), net=Sum("net_bailleur"))
        return Response({k: v or 0 for k, v in agg.items()} | {"nombre": qs.count()})

    @action(detail=True)
    def quittance(self, request, pk=None):
        enc = self.get_object()
        c = enc.contrat
        data = EncaissementSerializer(enc).data
        total = services.total_paye(c, enc.periode_mois, enc.periode_annee)
        loyer = services.loyer_pour_periode(c, enc.periode_mois, enc.periode_annee)
        data.update({
            "loyer_periode": loyer, "total_paye_periode": total, "reste_periode": max(0, loyer - total),
            "locataire_telephone": c.locataire.telephone1, "maison_adresse": c.maison.adresse,
            "maison_type": c.maison.type_maison, "prochaine_echeance": c.date_prochain_loyer,
            "bailleur_nom": c.maison.bailleur.nom, "contrat_reference": c.reference,
            "impayes": [p.libelle for p in services.periodes_impayees(c)] if c.statut_contrat == "actif" else [],
        })
        return Response(data)


class ChargeViewSet(viewsets.ModelViewSet):
    serializer_class = ChargeSerializer
    permission_classes = [SuppressionAdmin]
    filterset_fields = ["contrat", "type_charge"]
    search_fields = ["contrat__locataire__nom", "contrat__maison__designation", "description"]

    def get_queryset(self):
        qs = ChargeLocative.objects.select_related("contrat__locataire", "contrat__maison")
        annee = self.request.query_params.get("annee")
        return qs.filter(date_charge__year=annee) if annee else qs

    @action(detail=False)
    def types(self, request):
        return Response(ChargeLocative.TYPES)

    def perform_create(self, ser):
        ch = ser.save()
        journaliser(self.request, "Charge locative", f"{ch.type_charge} {ch.montant} FCFA — {ch.contrat.reference}")

    def perform_update(self, ser):
        ch = ser.save()
        journaliser(self.request, "Charge locative", f"Modification #{ch.pk}")

    def perform_destroy(self, ch):
        journaliser(self.request, "Charge locative", f"Suppression #{ch.pk} ({ch.type_charge})")
        ch.delete()


class RevisionViewSet(mixins.ListModelMixin, viewsets.GenericViewSet):
    serializer_class = RevisionSerializer
    filterset_fields = ["contrat"]
    search_fields = ["contrat__locataire__nom", "contrat__maison__designation", "motif"]

    def get_queryset(self):
        return RevisionLoyer.objects.select_related("contrat__locataire", "contrat__maison", "effectue_par")


class CautionViewSet(mixins.ListModelMixin, viewsets.GenericViewSet):
    """Registre des cautions des contrats actifs (gestion_cautions.php)."""

    serializer_class = ContratSerializer
    search_fields = ["locataire__nom", "maison__designation"]

    def get_queryset(self):
        statut = self.request.query_params.get("statut", "actif")
        qs = Contrat.objects.select_related("maison__bailleur", "locataire")
        if statut in ("actif", "termine"):
            qs = qs.filter(statut_contrat=statut)
        return _annoter_caution(qs).order_by("locataire__nom")

    @action(detail=False)
    def synthese(self, request):
        actifs = Contrat.objects.filter(statut_contrat="actif")
        depot = actifs.aggregate(t=Sum("depot_garantie"))["t"] or 0
        mvts = MouvementCaution.objects.filter(contrat__statut_contrat="actif").aggregate(t=Sum("montant"))["t"] or 0
        retenues = MouvementCaution.objects.filter(type_mouvement="retenue").aggregate(t=Sum("montant"))["t"] or 0
        restitue = MouvementCaution.objects.filter(type_mouvement="restitution").aggregate(t=Sum("montant"))["t"] or 0
        return Response({
            "contrats_actifs": actifs.count(), "total_depose": depot, "total_disponible": depot + mvts,
            "total_retenues": -retenues, "total_restitue": -restitue,
            "sans_mouvement": actifs.exclude(mouvements_caution__isnull=False).count(),
        })

    @action(detail=False)
    def mouvements(self, request):
        qs = MouvementCaution.objects.select_related("locataire", "contrat")
        if request.query_params.get("contrat"):
            qs = qs.filter(contrat=request.query_params["contrat"])
        page = self.paginate_queryset(qs)
        return self.get_paginated_response(MouvementCautionSerializer(page, many=True).data)
