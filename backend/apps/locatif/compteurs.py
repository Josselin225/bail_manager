"""Factures d'eau et d'électricité d'un compteur partagé (cour commune) : répartition entre les logements.

- Par index : chaque logement a un sous-compteur ; la facture est répartie au prorata des consommations.
- À parts égales : entre les logements occupés à la date de la facture.
La part d'un logement occupé devient une charge locative sur son bail ; celle d'un logement vide est
imputée au propriétaire (débit de son compte courant).
"""
import re
from decimal import Decimal, ROUND_HALF_UP

from django.db import transaction
from django.utils import timezone

from apps.core.exceptions import RegleMetier
from apps.core.utils import fcfa, journaliser
from apps.finances.models import CompteCourantBailleur

from .models import ChargeLocative, CompteurPartage, Contrat, EtatDesLieux, FacturePartagee, LigneRepartition


def _nombre(v):
    t = re.sub(r"[^\d,.]", "", str(v or "")).replace(",", ".")
    try:
        return Decimal(t) if t else None
    except Exception:
        return None


def index_precedent(compteur, maison, contrat):
    """Dernier index connu : la facture précédente de ce compteur, sinon l'état des lieux d'entrée du bail."""
    l = LigneRepartition.objects.filter(facture__compteur=compteur, maison=maison, index_actuel__isnull=False).order_by("-facture__date_facture", "-id").first()
    if l:
        return l.index_actuel
    if contrat:
        edl = EtatDesLieux.objects.filter(contrat=contrat, type_etat="entree").first()
        if edl:
            return _nombre(edl.index_eau if compteur.type_charge == "Eau" else edl.index_electricite)
    return None


def preparation(compteur, date_facture):
    """Logements du compteur avec leur bail à la date de la facture et leur dernier index."""
    res = []
    for m in compteur.maisons.select_related("bailleur").order_by("designation"):
        c = Contrat.objects.filter(maison=m, date_debut__lte=date_facture).exclude(statut_contrat="termine", date_resiliation__lt=date_facture).select_related("locataire").order_by("-date_debut").first()
        res.append({"maison": m.pk, "designation": m.designation, "bailleur": m.bailleur.nom, "contrat": c.pk if c else None,
                    "locataire": c.locataire.nom if c else None, "index_precedent": index_precedent(compteur, m, c)})
    return res


def calculer(compteur, montant, lignes):
    """Parts de chaque logement (arrondies au franc, l'écart d'arrondi sur la plus grosse part)."""
    montant = Decimal(montant)
    if montant <= 0:
        raise RegleMetier("Le montant de la facture doit être positif.")
    if compteur.mode == CompteurPartage.Mode.INDEX:
        for l in lignes:
            if l["index_actuel"] is None or l["index_precedent"] is None:
                raise RegleMetier(f"{l['designation']} : saisissez l'index précédent et l'index actuel.")
            if l["index_actuel"] < l["index_precedent"]:
                raise RegleMetier(f"{l['designation']} : l'index actuel est inférieur à l'index précédent.")
            l["consommation"] = l["index_actuel"] - l["index_precedent"]
        total = sum(l["consommation"] for l in lignes)
        if total <= 0:
            raise RegleMetier("Aucune consommation relevée : vérifiez les index.")
        for l in lignes:
            l["part"] = (montant * l["consommation"] / total).quantize(Decimal(1), ROUND_HALF_UP)
    else:
        occupes = [l for l in lignes if l["contrat"]]
        if not occupes:
            raise RegleMetier("Aucun logement occupé à cette date : la facture revient entièrement au propriétaire.")
        for l in lignes:
            l["consommation"] = None
            l["part"] = (montant / len(occupes)).quantize(Decimal(1), ROUND_HALF_UP) if l["contrat"] else Decimal(0)
    ecart = montant - sum(l["part"] for l in lignes)
    if ecart:
        max(lignes, key=lambda l: l["part"])["part"] += ecart
    return lignes


@transaction.atomic
def repartir(compteur, d, request):
    """Enregistre la facture et ses parts : charges sur les baux, part des logements vides au propriétaire."""
    lignes = preparation(compteur, d["date_facture"])
    saisis = {int(x["maison"]): x for x in d.get("lignes", [])}
    for l in lignes:
        s = saisis.get(l["maison"], {})
        if s.get("index_precedent") not in (None, ""):
            l["index_precedent"] = _nombre(s["index_precedent"])
        l["index_actuel"] = _nombre(s.get("index_actuel"))
    calculer(compteur, d["montant"], lignes)
    f = FacturePartagee.objects.create(compteur=compteur, numero=d.get("numero", ""), date_facture=d["date_facture"],
                                       periode=d.get("periode", ""), montant=d["montant"], cree_par=request.user if request.user.is_authenticated else None)
    libelle = f"{compteur.type_charge} — {compteur.nom}" + (f", facture {f.numero}" if f.numero else "") + (f" ({f.periode})" if f.periode else "")
    for l in lignes:
        charge = None
        if l["part"] > 0 and l["contrat"]:
            detail = f" — consommation {l['consommation']:g} (index {l['index_precedent']:g} → {l['index_actuel']:g})" if l["consommation"] is not None else " — part égale"
            charge = ChargeLocative.objects.create(contrat_id=l["contrat"], type_charge=compteur.type_charge, montant=l["part"],
                                                   date_charge=f.date_facture, description=libelle + detail)
        elif l["part"] > 0:
            m = compteur.maisons.select_related("bailleur").get(pk=l["maison"])
            CompteCourantBailleur.objects.create(
                bailleur=m.bailleur, date_operation=timezone.now(), type_operation=CompteCourantBailleur.Type.AJUSTEMENT, montant=-l["part"],
                commentaire=f"{libelle} — part du logement vide {m.designation}", effectue_par=getattr(request.user, "nom_complet", ""),
            )
        LigneRepartition.objects.create(facture=f, maison_id=l["maison"], contrat_id=l["contrat"], index_precedent=l["index_precedent"],
                                        index_actuel=l["index_actuel"], consommation=l["consommation"], montant=l["part"], charge=charge)
    journaliser(request, "Facture partagée", f"{libelle} — {fcfa(f.montant)} répartis entre {len(lignes)} logement(s)")
    return f
