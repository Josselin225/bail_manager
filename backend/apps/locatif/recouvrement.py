"""Recouvrement des impayés : relances graduées et plans d'apurement."""
from datetime import date
from decimal import Decimal

from django.db import transaction
from django.db.models import Sum

from apps.core.exceptions import RegleMetier
from apps.core.utils import ajouter_mois, arrondir, aujourdhui, fcfa, journaliser

from . import services
from .models import Contrat, Encaissement, PlanApurement, Relance

DELAIS = {1: 8, 2: 8, 3: 15}  # jours laissés au locataire pour régler, selon le niveau


def situation_impayes(contrat: Contrat) -> dict:
    imp = services.periodes_impayees(contrat)
    return {
        "montant": sum((p.reste for p in imp), Decimal(0)),
        "periodes": [p.libelle for p in imp],
        "plus_ancien": imp[0].libelle if imp else "",
        "nb_mois": len(imp),
    }


@transaction.atomic
def relancer(contrat: Contrat, request=None) -> Relance:
    """Crée la relance du niveau suivant (rappel → relance → mise en demeure) pour la dette actuelle."""
    if contrat.statut_contrat != Contrat.Statut.ACTIF:
        raise RegleMetier("Ce bail est terminé.")
    s = situation_impayes(contrat)
    if s["montant"] <= 0:
        raise RegleMetier("Ce locataire est à jour : aucune relance à envoyer.")
    derniere = contrat.relances.order_by("-niveau", "-id").first()
    niveau = min((derniere.niveau + 1) if derniere else 1, Relance.Niveau.MISE_EN_DEMEURE)
    r = Relance.objects.create(
        contrat=contrat, niveau=niveau, date_relance=aujourdhui(), montant_du=s["montant"],
        periodes=", ".join(s["periodes"]), delai_jours=DELAIS[niveau],
        effectuee_par=request.user if request and request.user.is_authenticated else None,
    )
    journaliser(request, "Relance impayé", f"{r.reference} — {r.get_niveau_display()} — {contrat.reference} — {fcfa(r.montant_du)}")
    transaction.on_commit(lambda: message_relance(r.pk, request))
    return r


MESSAGES_RELANCE = {
    1: "Bonjour {nom}, sauf erreur, votre loyer n'est pas encore réglé : {montant} ({periodes}). Merci de régulariser avant le {limite}. {agence}",
    2: "Bonjour {nom}, malgré notre rappel, {montant} restent impayés ({periodes}). Merci de régler avant le {limite}, faute de quoi une mise en demeure vous sera adressée. {agence}",
    3: "{nom}, MISE EN DEMEURE : vous devez {montant} ({periodes}). Réglez avant le {limite} pour éviter une procédure de recouvrement. Un courrier vous est remis. {agence}",
}


def message_relance(relance_id, request=None):
    """Envoie la relance par SMS / WhatsApp si l'agence l'a activé (Paramètres > Connecteurs)."""
    from apps.connecteurs.models import Connecteurs
    from apps.connecteurs.services import envoyer_message
    from apps.core.models import Parametres

    if not Connecteurs.charger().envoi_relances:
        return
    r = Relance.objects.select_related("contrat__locataire").get(pk=relance_id)
    loc = r.contrat.locataire
    limite = r.date_relance.toordinal() + r.delai_jours
    texte = MESSAGES_RELANCE[r.niveau].format(
        nom=loc.nom, montant=fcfa(r.montant_du), periodes=r.periodes, limite=date.fromordinal(limite).strftime("%d/%m/%Y"),
        agence=Parametres.charger().nom_entreprise,
    )
    envoyer_message(loc.telephone1, texte, nom=loc.nom, objet=r.get_niveau_display(), request=request)


def echeances_egales(montant, nb: int, premiere: date) -> list[dict]:
    """Répartit le montant en nb mensualités (arrondies ; le reste sur la dernière)."""
    montant = arrondir(montant)
    base = arrondir(montant / nb)
    res = []
    for i in range(nb):
        m = base if i < nb - 1 else montant - base * (nb - 1)
        res.append({"date": ajouter_mois(premiere, i).isoformat(), "montant": int(m)})
    return res


@transaction.atomic
def creer_plan(contrat: Contrat, nb_echeances, premiere_echeance, commentaire="", request=None) -> PlanApurement:
    s = situation_impayes(contrat)
    if s["montant"] <= 0:
        raise RegleMetier("Ce locataire est à jour : aucun plan d'apurement nécessaire.")
    try:
        nb = int(nb_echeances)
    except (TypeError, ValueError):
        raise RegleMetier("Indiquez le nombre d'échéances.")
    if not 2 <= nb <= 24:
        raise RegleMetier("Un plan d'apurement compte entre 2 et 24 échéances.")
    if not premiere_echeance:
        raise RegleMetier("Indiquez la date de la première échéance.")
    contrat.plans_apurement.filter(actif=True).update(actif=False)
    plan = PlanApurement.objects.create(
        contrat=contrat, montant_total=s["montant"], date_accord=aujourdhui(),
        echeances=echeances_egales(s["montant"], nb, premiere_echeance), commentaire=(commentaire or "")[:255],
        cree_par=request.user if request and request.user.is_authenticated else None,
    )
    journaliser(request, "Plan d'apurement", f"{plan.reference} — {contrat.reference} — {fcfa(plan.montant_total)} en {nb} échéances")
    return plan


def suivi_plan(plan: PlanApurement) -> dict:
    """Chaque échéance est honorée si les paiements reçus depuis l'accord couvrent le cumul dû à cette date."""
    verse = Encaissement.objects.filter(contrat=plan.contrat, date_encaissement__date__gte=plan.date_accord).aggregate(t=Sum("montant_recu"))["t"] or 0
    j = aujourdhui()
    cumul, lignes = Decimal(0), []
    for e in plan.echeances:
        cumul += Decimal(e["montant"])
        d = date.fromisoformat(e["date"])
        statut = "honoree" if verse >= cumul else ("en_retard" if d < j else "a_venir")
        lignes.append({**e, "cumul": cumul, "statut": statut})
    return {
        "id": plan.pk, "reference": plan.reference, "montant_total": plan.montant_total, "date_accord": plan.date_accord,
        "actif": plan.actif, "commentaire": plan.commentaire, "verse": verse,
        "reste": max(Decimal(0), plan.montant_total - verse), "echeances": lignes,
        "en_retard": any(l["statut"] == "en_retard" for l in lignes),
    }
