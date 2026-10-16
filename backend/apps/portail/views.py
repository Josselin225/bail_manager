"""Site vitrine public et espace locataire (pages index, nos_maisons, contact, locataire_*.php)."""
from django.contrib.auth.hashers import check_password
from django.db.models import Exists, OuterRef, Q
from django.shortcuts import get_object_or_404
from rest_framework import serializers
from rest_framework.decorators import api_view, authentication_classes, permission_classes
from rest_framework.permissions import AllowAny
from rest_framework.response import Response

from apps.core.auth import verifier_csrf
from apps.core.exceptions import RegleMetier
from apps.core.models import Parametres
from apps.core.utils import aujourdhui, enregistrer_echec, ip_client, journaliser, limite_atteinte, reinitialiser_limite
from apps.locatif import services
from apps.locatif.models import ChargeLocative, Contrat, Encaissement
from apps.patrimoine.models import Locataire, Maison
from apps.patrimoine.serializers import resume_mandat
from apps.relations.models import MessageLocataire, MessageVisiteur, Reservation

from .permissions import EstLocataire

def _public(view):
    view = permission_classes([AllowAny])(view)
    return authentication_classes([])(view)


def _image(request, f):
    return request.build_absolute_uri(f.url) if f else None


def _maisons_site():
    """Maisons publiées sur le site, annotées « occupee » : vrai si un contrat actif la concerne."""
    actif = Contrat.objects.filter(maison=OuterRef("pk"), statut_contrat=Contrat.Statut.ACTIF)
    return Maison.objects.filter(visible_site=True).annotate(occupee=Exists(actif)).prefetch_related("photos")


def _maison_publique(request, m):
    return {
        "id": m.pk, "designation": m.designation, "type_maison": m.type_maison, "adresse": m.adresse,
        "description": m.description, "loyer": m.loyer, "condition": m.condition,
        "occupee": m.occupee, "disponible": not m.occupee,
        "images": [_image(request, p.image) for p in m.photos.all()],
    }


@api_view(["GET"])
@_public
def vitrine(request):
    p = Parametres.charger()
    site = _maisons_site()
    dispo = site.filter(occupee=False)
    return Response({
        "entreprise": {
            "nom": p.nom_entreprise, "telephone": p.contact_telephone, "email": p.contact_email,
            "adresse": p.adresse_siege, "activites": p.activites, "site_web": p.site_web,
            "logo": _image(request, p.logo),
        },
        "nb_disponibles": dispo.count(),
        "nb_occupees": site.filter(occupee=True).count(),
        "types": sorted(set(site.exclude(type_maison="").values_list("type_maison", flat=True))),
        "a_la_une": [_maison_publique(request, m) for m in dispo.order_by("-created_at")[:3]],
    })


@api_view(["GET"])
@_public
def maisons_publiques(request):
    # Disponibles d'abord, puis occupées ; ?etat=disponible|occupee pour filtrer
    qs = _maisons_site().order_by("occupee", "-created_at")
    etat = request.query_params.get("etat")
    if etat in ("disponible", "occupee"):
        qs = qs.filter(occupee=etat == "occupee")
    if t := request.query_params.get("type"):
        qs = qs.filter(type_maison=t)
    if q := request.query_params.get("q"):
        qs = qs.filter(Q(designation__icontains=q) | Q(adresse__icontains=q) | Q(description__icontains=q))
    if mx := request.query_params.get("loyer_max"):
        qs = qs.filter(loyer__lte=mx)
    return Response([_maison_publique(request, m) for m in qs[:200]])


@api_view(["GET"])
@_public
def maison_publique(request, pk):
    m = get_object_or_404(_maisons_site(), pk=pk)
    return Response(_maison_publique(request, m))


class ReservationPubliqueSerializer(serializers.Serializer):
    maison = serializers.IntegerField()
    nom_visiteur = serializers.CharField(max_length=255)
    tel_visiteur = serializers.CharField(max_length=50)
    date_visite = serializers.DateField()
    message = serializers.CharField(required=False, allow_blank=True, max_length=1000)

    def validate_date_visite(self, v):
        if v < aujourdhui():
            raise serializers.ValidationError("Choisissez une date à partir d'aujourd'hui.")
        return v


@api_view(["POST"])
@_public
def reserver(request):
    verifier_csrf(request)
    ip = ip_client(request)
    if limite_atteinte("reservation", ip, maximum=5, fenetre=3600):
        raise RegleMetier("Trop de demandes envoyées. Réessayez dans une heure ou appelez-nous.")
    ser = ReservationPubliqueSerializer(data=request.data)
    ser.is_valid(raise_exception=True)
    d = ser.validated_data
    m = get_object_or_404(_maisons_site(), pk=d.pop("maison"), occupee=False)
    r = Reservation.objects.create(maison=m, **d)
    enregistrer_echec("reservation", ip, fenetre=3600)
    journaliser(None, "Nouvelle réservation", f"{r.nom_visiteur} — {m.designation} — visite le {r.date_visite:%d/%m/%Y}")
    return Response({"detail": "Votre demande de visite est enregistrée. Nous vous appellerons pour confirmer."}, status=201)


class ContactSerializer(serializers.Serializer):
    nom = serializers.CharField(max_length=255)
    email = serializers.EmailField()
    telephone = serializers.CharField(max_length=50, required=False, allow_blank=True)
    sujet = serializers.CharField(max_length=255, required=False, allow_blank=True)
    message = serializers.CharField(max_length=5000)


@api_view(["POST"])
@_public
def contact(request):
    verifier_csrf(request)
    ip = ip_client(request)
    if limite_atteinte("contact", ip, maximum=5, fenetre=3600):
        raise RegleMetier("Trop de messages envoyés. Réessayez dans une heure.")
    ser = ContactSerializer(data=request.data)
    ser.is_valid(raise_exception=True)
    d = ser.validated_data
    MessageVisiteur.objects.create(
        nom_visiteur=d["nom"], email_visiteur=d["email"], telephone=d.get("telephone", ""),
        sujet=d.get("sujet", ""), contenu=d["message"],
    )
    enregistrer_echec("contact", ip, fenetre=3600)
    journaliser(None, "Nouveau message", f"De {d['nom']} ({d['email']})")
    return Response({"detail": "Message envoyé. Nous vous répondrons rapidement."}, status=201)


# ─── Espace locataire ─────────────────────────────────────────────────────────
@api_view(["POST"])
@_public
def connexion_locataire(request):
    verifier_csrf(request)
    ip = ip_client(request)
    if limite_atteinte("locataire", ip):
        raise RegleMetier("Trop de tentatives. Réessayez dans quelques minutes.")
    tel = "".join(c for c in (request.data.get("telephone") or "") if c.isdigit() or c == "+")
    code = (request.data.get("code") or "").strip().upper()
    if not tel or not code:
        raise RegleMetier("Saisissez votre numéro de téléphone et votre code d'accès.")
    candidats = Locataire.objects.filter(Q(telephone1=tel) | Q(telephone2=tel), acces__actif=True).select_related("acces")
    loc = next((l for l in candidats if check_password(code, l.acces.code_acces)), None)
    if loc is None:
        enregistrer_echec("locataire", ip)
        raise RegleMetier("Téléphone ou code d'accès incorrect.")
    reinitialiser_limite("locataire", ip)
    request.session.cycle_key()
    request.session["locataire_id"] = loc.pk
    request.session.set_expiry(30 * 60)
    return Response({"nom": loc.nom})


@api_view(["POST"])
@_public
def deconnexion_locataire(request):
    request.session.pop("locataire_id", None)
    return Response({"ok": True})


def _locataire(request):
    return get_object_or_404(Locataire, pk=request.session["locataire_id"])


@api_view(["GET"])
@authentication_classes([])
@permission_classes([EstLocataire])
def espace(request):
    loc = _locataire(request)
    p = Parametres.charger()
    contrats = []
    for c in loc.contrats.select_related("maison__bailleur").order_by("-statut_contrat", "-date_debut"):
        contrats.append({
            # Qui gère le logement, pour qui, et jusqu'à quand (mandat de gestion du propriétaire)
            "gestion": {"agence": p.nom_entreprise, "proprietaire": c.maison.bailleur.nom, "mandat": resume_mandat(c.maison.bailleur.mandat_actif())},
            "id": c.pk, "reference": c.reference, "maison": c.maison.designation, "adresse": c.maison.adresse,
            "type": c.maison.type_maison, "loyer": c.loyer_mensuel, "date_debut": c.date_debut, "date_fin": c.date_fin,
            "statut": c.statut_contrat, "date_prochain_loyer": c.date_prochain_loyer,
            "statut_paiement": services.statut_paiement(c), "caution": c.depot_garantie,
            "caution_disponible": services.caution_disponible(c),
            "impayes": [x.as_dict() for x in services.periodes_impayees(c)] if c.statut_contrat == "actif" else [],
            "echeancier": [x.as_dict() for x in services.echeancier(c)][-12:],
        })
    paiements = Encaissement.objects.filter(contrat__locataire=loc).select_related("contrat__maison")[:60]
    charges = [
        {"id": ch.pk, "type": ch.type_charge, "montant": ch.montant, "date": ch.date_charge, "description": ch.description, "maison": ch.contrat.maison.designation}
        for ch in ChargeLocative.objects.filter(contrat__locataire=loc).select_related("contrat__maison")[:60]
    ]
    loc.messages.filter(expediteur="agence", lu=False).update(lu=True)
    return Response({
        "locataire": {"nom": loc.nom, "telephone": loc.telephone1, "email": loc.email},
        "entreprise": {"nom": p.nom_entreprise, "telephone": p.contact_telephone, "email": p.contact_email},
        "contrats": contrats,
        "paiements": [
            {"id": e.pk, "reference": e.reference_recu, "periode": e.periode_concernee, "montant": e.montant_recu,
             "date": e.date_encaissement, "mode": e.get_mode_paiement_display(), "maison": e.contrat.maison.designation}
            for e in paiements
        ],
        "charges": charges,
        "messages": [
            {"id": m.pk, "expediteur": m.expediteur, "contenu": m.contenu, "date": m.created_at}
            for m in loc.messages.order_by("created_at")
        ],
    })


@api_view(["POST"])
@authentication_classes([])
@permission_classes([EstLocataire])
def envoyer_message(request):
    verifier_csrf(request)
    texte = (request.data.get("contenu") or "").strip()
    if not texte:
        raise RegleMetier("Le message est vide.")
    loc = _locataire(request)
    m = MessageLocataire.objects.create(locataire=loc, expediteur="locataire", contenu=texte[:4000])
    return Response({"id": m.pk, "expediteur": "locataire", "contenu": m.contenu, "date": m.created_at}, status=201)


@api_view(["GET"])
@authentication_classes([])
@permission_classes([EstLocataire])
def quittance_locataire(request, pk):
    loc = _locataire(request)
    enc = get_object_or_404(Encaissement.objects.select_related("contrat__maison__bailleur", "contrat__locataire"), pk=pk, contrat__locataire=loc)
    from apps.locatif.serializers import EncaissementSerializer

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
    from apps.core.serializers import ParametresSerializer

    data["entreprise"] = ParametresSerializer(Parametres.charger(), context={"request": request}).data
    return Response(data)
