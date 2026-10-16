"""Espace bailleur : le propriétaire suit ses biens, ses loyers, son compte et les versements de l'agence."""
from datetime import date
from decimal import Decimal

from django.contrib.auth.hashers import check_password
from django.db.models import Q, Sum
from django.shortcuts import get_object_or_404
from rest_framework.decorators import api_view, authentication_classes, permission_classes
from rest_framework.response import Response

from apps.core.auth import verifier_csrf
from apps.core.exceptions import RegleMetier
from apps.core.models import Parametres
from apps.core.utils import aujourdhui, enregistrer_echec, ip_client, limite_atteinte, reinitialiser_limite
from apps.finances.models import CompteCourantBailleur
from apps.locatif import services
from apps.locatif.models import Contrat, Encaissement
from apps.patrimoine.models import Bailleur
from apps.patrimoine.serializers import resume_mandat
from apps.rapports.views import donnees_recap_fiscal, donnees_releve
from apps.relations.models import DemandeIntervention

from .permissions import EstBailleur
from .views import _image, _public, donnees_quittance

MOIS = ["Janv.", "Févr.", "Mars", "Avr.", "Mai", "Juin", "Juil.", "Août", "Sept.", "Oct.", "Nov.", "Déc."]


def _espace(view):
    view = permission_classes([EstBailleur])(view)
    return authentication_classes([])(view)


def _bailleur(request):
    return get_object_or_404(Bailleur, pk=request.session["bailleur_id"])


@api_view(["POST"])
@_public
def connexion_bailleur(request):
    verifier_csrf(request)
    ip = ip_client(request)
    if limite_atteinte("bailleur", ip):
        raise RegleMetier("Trop de tentatives. Réessayez dans quelques minutes.")
    tel = "".join(c for c in (request.data.get("telephone") or "") if c.isdigit() or c == "+")
    code = (request.data.get("code") or "").strip().upper()
    if not tel or not code:
        raise RegleMetier("Saisissez votre numéro de téléphone et votre code d'accès.")
    candidats = Bailleur.objects.filter(Q(telephone1=tel) | Q(telephone2=tel), acces__actif=True).select_related("acces")
    b = next((x for x in candidats if check_password(code, x.acces.code_acces)), None)
    if b is None:
        enregistrer_echec("bailleur", ip)
        raise RegleMetier("Téléphone ou code d'accès incorrect.")
    reinitialiser_limite("bailleur", ip)
    request.session.cycle_key()
    request.session["bailleur_id"] = b.pk
    request.session.set_expiry(30 * 60)
    return Response({"nom": b.nom})


@api_view(["POST"])
@_public
def deconnexion_bailleur(request):
    request.session.pop("bailleur_id", None)
    return Response({"ok": True})


def _mois_glissants(n=12):
    j = aujourdhui()
    i = j.year * 12 + j.month - 1
    return [((k % 12) + 1, k // 12) for k in range(i - n + 1, i + 1)]


@api_view(["GET"])
@_espace
def espace_bailleur(request):
    b = _bailleur(request)
    p = Parametres.charger()
    j = aujourdhui()
    ops = CompteCourantBailleur.objects.filter(bailleur=b)
    actifs = {c.maison_id: c for c in Contrat.objects.filter(statut_contrat="actif", maison__bailleur=b).select_related("locataire")}

    biens, attendu, impaye = [], Decimal(0), Decimal(0)
    for m in b.maisons.prefetch_related("photos").order_by("designation"):
        c = actifs.get(m.pk)
        dette = sum((x.reste for x in services.periodes_impayees(c)), Decimal(0)) if c else Decimal(0)
        if c:
            attendu += c.loyer_mensuel
            impaye += dette
        photo = m.photos.first()
        biens.append({
            "id": m.pk, "designation": m.designation, "type": m.type_maison, "adresse": m.adresse, "loyer": m.loyer,
            "photo": _image(request, photo.image) if photo else None, "occupe": bool(c),
            "locataire": c.locataire.nom if c else None, "contrat": c.reference if c else None,
            "date_debut": c.date_debut if c else None, "statut_paiement": services.statut_paiement(c) if c else None,
            "impaye": dette, "date_sortie_prevue": c.date_sortie_prevue if c else None,
        })

    # Douze derniers mois : net encaissé pour le bailleur et versements reçus
    mois = []
    for mo, an in _mois_glissants():
        du_mois = ops.filter(date_operation__year=an, date_operation__month=mo)
        mois.append({
            "libelle": f"{MOIS[mo - 1]} {str(an)[2:]}",
            "net": du_mois.filter(type_operation="loyer_encaisse").aggregate(t=Sum("montant"))["t"] or 0,
            "verse": -(du_mois.filter(type_operation="versement_bailleur").aggregate(t=Sum("montant"))["t"] or 0),
        })

    versements = ops.filter(type_operation="versement_bailleur")
    dernier = versements.first()
    quittances = Encaissement.objects.filter(contrat__maison__bailleur=b).select_related("contrat__maison", "contrat__locataire")[:80]
    interventions = DemandeIntervention.objects.filter(maison__bailleur=b).select_related("maison").order_by("-created_at")[:40]
    debut_annee = date(j.year, 1, 1)
    return Response({
        "bailleur": {"nom": b.nom, "code": b.code_bailleur, "telephone": b.telephone1, "email": b.email},
        "entreprise": {"nom": p.nom_entreprise, "telephone": p.contact_telephone, "email": p.contact_email},
        "mandat": {**resume_mandat(m), "taux_commission": m.taux_commission} if (m := b.mandat_actif()) else None,
        "chiffres": {
            "solde": ops.aggregate(t=Sum("montant"))["t"] or 0,
            "nb_biens": len(biens), "nb_occupes": len(actifs),
            "loyers_mensuels": attendu, "impaye": impaye,
            "net_annee": ops.filter(type_operation="loyer_encaisse", date_operation__date__gte=debut_annee).aggregate(t=Sum("montant"))["t"] or 0,
            "verse_annee": -(versements.filter(date_operation__date__gte=debut_annee).aggregate(t=Sum("montant"))["t"] or 0),
            "dernier_versement": {"date": dernier.date_operation, "montant": -dernier.montant, "mode": dernier.mode} if dernier else None,
        },
        "mois": mois,
        "biens": biens,
        "versements": [
            {"id": o.pk, "date": o.date_operation, "montant": -o.montant, "mode": o.mode, "commentaire": o.commentaire}
            for o in versements[:60]
        ],
        "quittances": [
            {"id": e.pk, "reference": e.reference_recu, "periode": e.periode_concernee, "montant": e.montant_recu,
             "date": e.date_encaissement, "maison": e.contrat.maison.designation, "locataire": e.contrat.locataire.nom}
            for e in quittances
        ],
        "interventions": [
            {"id": d.pk, "reference": d.reference, "titre": d.titre, "maison": d.maison.designation, "statut": d.statut,
             "statut_libelle": d.get_statut_display(), "date": d.created_at, "cout": d.cout,
             "imputation": d.imputation, "imputation_libelle": d.get_imputation_display()}
            for d in interventions
        ],
    })


@api_view(["GET"])
@_espace
def releve_bailleur(request):
    from apps.core.serializers import ParametresSerializer

    data = donnees_releve(_bailleur(request), request)
    data["entreprise"] = ParametresSerializer(Parametres.charger(), context={"request": request}).data
    return Response(data)


@api_view(["GET"])
@_espace
def recap_fiscal(request):
    from apps.core.serializers import ParametresSerializer

    data = donnees_recap_fiscal(_bailleur(request), request)
    data["entreprise"] = ParametresSerializer(Parametres.charger(), context={"request": request}).data
    return Response(data)


@api_view(["GET"])
@_espace
def quittance_bailleur(request, pk):
    b = _bailleur(request)
    enc = get_object_or_404(Encaissement.objects.select_related("contrat__maison__bailleur", "contrat__locataire"), pk=pk, contrat__maison__bailleur=b)
    return Response(donnees_quittance(request, enc))
