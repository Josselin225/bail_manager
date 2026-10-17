"""
Règles de gestion de BailManager — reprises de la version PHP (php/add_contrat.php,
add_paiement.php, process_restitution_caution.php, resilier_contrat.php,
add_revision_loyer.php...) et centralisées ici pour être appliquées partout de la
même façon (back-office, imports, tâches automatiques).
"""
import secrets
from dataclasses import dataclass
from datetime import date
from decimal import Decimal

from django.db import transaction
from django.db.models import Sum
from django.utils import timezone

from apps.core.exceptions import RegleMetier
from apps.core.models import ClauseContrat, Parametres, clauses_en_vigueur
from apps.core.utils import (
    ajouter_mois, arrondir, auteur, aujourdhui, date_du_mois, fcfa, index_periode, journaliser,
    libelle_periode, periode_depuis_index,
)
from apps.finances.models import CompteCourantBailleur, MouvementCaisse
from apps.patrimoine.models import Bailleur, Maison

from .models import Contrat, Encaissement, MouvementCaution, RevisionLoyer

CAUTION_NB_MOIS = 2          # Art. 416 : dépôt de garantie = 2 mois de loyer
AVANCE_MAX_MOIS = 2          # Art. 415 : avance de loyer limitée à 2 mois


# ─── Mandat & commission ──────────────────────────────────────────────────────
def exiger_mandat_actif(bailleur: Bailleur):
    mandat = bailleur.mandat_actif()
    if mandat is None:
        raise RegleMetier(
            f"Le bailleur {bailleur.nom} n'a pas de mandat de gestion en vigueur. "
            "Enregistrez d'abord un mandat pour ce bailleur.",
            code="mandat_requis",
        )
    return mandat


def taux_commission_pour(bailleur: Bailleur) -> Decimal:
    """Taux du mandat en vigueur ; à défaut, taux par défaut des paramètres."""
    mandat = bailleur.mandat_actif()
    if mandat is not None:
        return Decimal(mandat.taux_commission)
    return Decimal(Parametres.charger().taux_commission)


def nouvelle_reference_recu() -> str:
    while True:
        ref = "REC-" + secrets.token_hex(3).upper()
        if not Encaissement.objects.filter(reference_recu=ref).exists():
            return ref


# ─── Échéancier ───────────────────────────────────────────────────────────────
def _index_debut(contrat: Contrat) -> int:
    d = contrat.debut_facturation or contrat.date_debut
    return index_periode(d.month, d.year)


def loyer_pour_periode(contrat: Contrat, mois: int, annee: int, revisions=None) -> Decimal:
    """Loyer applicable à une période, en tenant compte des révisions passées."""
    revisions = revisions if revisions is not None else list(contrat.revisions.order_by("date_effet", "id"))
    if not revisions:
        return Decimal(contrat.loyer_mensuel)
    cible = index_periode(mois, annee)
    loyer = Decimal(revisions[0].ancien_loyer)
    for r in revisions:
        if index_periode(r.date_effet.month, r.date_effet.year) <= cible:
            loyer = Decimal(r.nouveau_loyer)
    return loyer


def paiements_par_periode(contrat: Contrat) -> dict:
    lignes = (
        contrat.encaissements.values("periode_annee", "periode_mois")
        .annotate(total=Sum("montant_recu"))
    )
    return {index_periode(l["periode_mois"], l["periode_annee"]): Decimal(l["total"] or 0) for l in lignes}


def total_paye(contrat: Contrat, mois: int, annee: int) -> Decimal:
    t = contrat.encaissements.filter(periode_mois=mois, periode_annee=annee).aggregate(t=Sum("montant_recu"))["t"]
    return Decimal(t or 0)


@dataclass
class Periode:
    mois: int
    annee: int
    loyer: Decimal
    paye: Decimal

    @property
    def reste(self):
        return max(Decimal(0), self.loyer - self.paye)

    @property
    def libelle(self):
        return libelle_periode(self.mois, self.annee)

    def as_dict(self):
        return {
            "mois": self.mois, "annee": self.annee, "libelle": self.libelle,
            "loyer": self.loyer, "paye": self.paye, "reste": self.reste,
            "statut": "solde" if self.reste == 0 else ("partiel" if self.paye > 0 else "impaye"),
        }


def echeancier(contrat: Contrat, jusqu_a: date | None = None, apres: int = 0) -> list[Periode]:
    """Toutes les périodes du bail, du début jusqu'au mois de `jusqu_a` (+ `apres` mois)."""
    jusqu_a = jusqu_a or aujourdhui()
    if contrat.statut_contrat == Contrat.Statut.TERMINE and contrat.date_resiliation:
        jusqu_a = min(jusqu_a, contrat.date_resiliation)
    paiements = paiements_par_periode(contrat)
    revisions = list(contrat.revisions.order_by("date_effet", "id"))
    debut = _index_debut(contrat)
    fin = max(index_periode(jusqu_a.month, jusqu_a.year) + apres, debut)
    resultat = []
    for i in range(debut, fin + 1):
        m, a = periode_depuis_index(i)
        resultat.append(Periode(m, a, loyer_pour_periode(contrat, m, a, revisions), paiements.get(i, Decimal(0))))
    return resultat


def periodes_impayees(contrat: Contrat, jusqu_a: date | None = None) -> list[Periode]:
    """Mois échus non soldés (le mois en cours n'est compté que si son échéance est passée)."""
    jusqu_a = jusqu_a or aujourdhui()
    jour = contrat.date_debut.day
    res = []
    for p in echeancier(contrat, jusqu_a):
        if p.reste > 0 and date_du_mois(p.annee, p.mois, jour) <= jusqu_a:
            res.append(p)
    return res


def recalculer_echeance(contrat: Contrat, sauvegarder=True) -> date | None:
    """date_prochain_loyer = échéance du premier mois non soldé (jour = jour de début du bail)."""
    paiements = paiements_par_periode(contrat)
    revisions = list(contrat.revisions.order_by("date_effet", "id"))
    i = _index_debut(contrat)
    for _ in range(1200):
        m, a = periode_depuis_index(i)
        if paiements.get(i, Decimal(0)) < loyer_pour_periode(contrat, m, a, revisions):
            break
        i += 1
    m, a = periode_depuis_index(i)
    d = date_du_mois(a, m, contrat.date_debut.day)
    contrat.date_prochain_loyer = d
    if sauvegarder:
        contrat.save(update_fields=["date_prochain_loyer"])
    return d


def statut_paiement(contrat: Contrat) -> str:
    if contrat.statut_contrat != Contrat.Statut.ACTIF:
        return "termine"
    d = contrat.date_prochain_loyer
    if d is None:
        return "a_jour"
    j = aujourdhui()
    if d < j:
        return "en_retard"
    if (d - j).days <= 5:
        return "echeance_proche"
    return "a_jour"


# ─── Encaissement d'un loyer ──────────────────────────────────────────────────
def verifier_dette_anterieure(contrat: Contrat, mois: int, annee: int):
    """Interdit de payer un mois tant qu'un mois précédent n'est pas soldé (règle PHP)."""
    cible = index_periode(mois, annee)
    for p in echeancier(contrat, date(annee, mois, 1)):
        if index_periode(p.mois, p.annee) >= cible:
            break
        if p.reste > 0:
            return p
    return None


@transaction.atomic
def enregistrer_encaissement(
    contrat: Contrat, montant, mois: int, annee: int, mode: str, request=None,
    origine=Encaissement.Origine.LOYER, date_operation=None, observation="", controler_dette=True,
) -> Encaissement:
    contrat = Contrat.objects.select_for_update().select_related("maison__bailleur", "locataire").get(pk=contrat.pk)
    montant = arrondir(montant or 0)
    if montant <= 0:
        raise RegleMetier("Le montant doit être supérieur à zéro.")
    if contrat.statut_contrat != Contrat.Statut.ACTIF and origine != Encaissement.Origine.CAUTION:
        raise RegleMetier("Ce contrat est terminé : aucun encaissement n'est possible.")
    if not 1 <= int(mois) <= 12:
        raise RegleMetier("Mois invalide.")
    if index_periode(mois, annee) < _index_debut(contrat):
        raise RegleMetier(f"{libelle_periode(mois, annee)} est antérieur au début du bail.")

    if controler_dette:
        dette = verifier_dette_anterieure(contrat, mois, annee)
        if dette is not None:
            raise RegleMetier(
                f"Dette détectée sur {dette.libelle} : il reste {fcfa(dette.reste)} à régler. "
                "Soldez d'abord ce mois.",
                code="dette_anterieure",
            )

    loyer = loyer_pour_periode(contrat, mois, annee)
    deja = total_paye(contrat, mois, annee)
    reste = loyer - deja
    if reste <= 0:
        raise RegleMetier(f"{libelle_periode(mois, annee)} est déjà soldé.")
    if montant > reste:
        raise RegleMetier(
            f"Le montant dépasse le reste à payer pour {libelle_periode(mois, annee)} ({fcfa(reste)}). "
            "Enregistrez le surplus sur le mois suivant."
        )

    bailleur = contrat.maison.bailleur
    taux = taux_commission_pour(bailleur)
    commission = arrondir(montant * taux / 100)
    net = montant - commission
    quand = date_operation or timezone.now()
    nom_auteur = auteur(request)

    enc = Encaissement.objects.create(
        contrat=contrat, montant_recu=montant, date_encaissement=quand, mode_paiement=mode,
        reference_recu=nouvelle_reference_recu(), periode_mois=mois, periode_annee=annee,
        reliquat_apres_paiement=reste - montant, taux_commission=taux, commission=commission,
        net_bailleur=net, origine=origine, observation=observation,
        enregistre_par=request.user if request and request.user.is_authenticated else None,
    )
    periode = enc.periode_concernee
    CompteCourantBailleur.objects.create(
        bailleur=bailleur, date_operation=quand, type_operation=CompteCourantBailleur.Type.LOYER,
        montant=net, encaissement=enc, effectue_par=nom_auteur,
        commentaire=f"Loyer {periode} — {contrat.locataire.nom} (net commission {taux:g}%) — Réf. {enc.reference_recu}",
    )
    if commission > 0:
        MouvementCaisse.objects.create(
            bailleur=bailleur, type_mouvement="Commission Gestion", montant=commission, date_operation=quand,
            reference_paiement=enc.reference_recu, encaissement=enc, effectue_par=nom_auteur,
            commentaire=f"Commission {taux:g}% sur {periode} — {contrat.maison.designation}",
        )
    recalculer_echeance(contrat)
    if origine == Encaissement.Origine.LOYER:
        from apps.connecteurs.services import confirmer_encaissement

        transaction.on_commit(lambda: confirmer_encaissement(enc.pk))
    journaliser(
        request, "Encaissement loyer",
        f"Contrat {contrat.reference} — {periode} — {fcfa(montant)} ({enc.get_mode_paiement_display()}) — Réf. {enc.reference_recu}",
    )
    return enc


# ─── Contrat de bail ──────────────────────────────────────────────────────────
@transaction.atomic
def creer_contrat(
    *, maison: Maison, locataire, date_debut: date, loyer_mensuel=None, mois_avance=2, droit_agence=0,
    date_fin=None, date_contrat=None, mode_paiement="especes", request=None,
) -> Contrat:
    maison = Maison.objects.select_for_update().select_related("bailleur").get(pk=maison.pk)
    # Le loyer est fixé à l'enregistrement du bien ; il ne se négocie pas à la signature du bail.
    loyer = arrondir(maison.loyer or 0)
    if loyer <= 0:
        raise RegleMetier(f"Aucun loyer n'est fixé pour « {maison.designation} » : renseignez-le sur la fiche du bien.")
    if loyer_mensuel not in (None, "") and arrondir(loyer_mensuel) != loyer:
        raise RegleMetier(
            f"Le loyer de « {maison.designation} » est fixé à {fcfa(loyer)} sur la fiche du bien. "
            "Il ne peut être modifié qu'ensuite, par une révision du loyer à la demande du bailleur."
        )
    if maison.statut != Maison.Statut.DISPONIBLE or maison.contrats.filter(statut_contrat="actif").exists():
        raise RegleMetier(f"« {maison.designation} » est déjà occupée. Résiliez d'abord le contrat en cours.")
    mois_avance = int(mois_avance or 0)
    if not 0 <= mois_avance <= AVANCE_MAX_MOIS:
        raise RegleMetier("L'avance de loyer ne peut dépasser deux (02) mois (Art. 415 du Code de la Construction).")
    if date_fin and date_fin <= date_debut:
        raise RegleMetier("L'échéance du bail doit être postérieure à la date de début.")
    mandat = exiger_mandat_actif(maison.bailleur)

    contrat = Contrat.objects.create(
        maison=maison, mandat=mandat, locataire=locataire, loyer_mensuel=loyer,
        date_contrat=date_contrat or aujourdhui(), date_debut=date_debut, date_fin=date_fin,
        debut_facturation=date_debut.replace(day=1),
        depot_garantie=loyer * CAUTION_NB_MOIS, mois_avance=mois_avance, avance_loyer=loyer * mois_avance,
        droit_agence=arrondir(droit_agence or 0),
        cree_par=request.user if request and request.user.is_authenticated else None,
        clauses_signees=clauses_en_vigueur(ClauseContrat),
    )
    maison.statut = Maison.Statut.OCCUPE
    maison.save(update_fields=["statut"])

    if contrat.droit_agence > 0:
        MouvementCaisse.objects.create(
            bailleur=maison.bailleur, type_mouvement="Droit d'agence", montant=contrat.droit_agence,
            date_operation=timezone.now(), reference_paiement=contrat.reference, effectue_par=auteur(request),
            commentaire=f"Frais d'agence — {contrat.reference} — {locataire.nom}",
        )
    # L'avance couvre les premiers mois du bail : chaque mois reçoit sa quittance et
    # la répartition bailleur / commission est faite comme pour un loyer ordinaire.
    for k in range(mois_avance):
        d = ajouter_mois(date_debut, k)
        enregistrer_encaissement(
            contrat, loyer, d.month, d.year, mode_paiement, request,
            origine=Encaissement.Origine.AVANCE, observation="Avance versée à la signature",
        )
    recalculer_echeance(contrat)
    journaliser(
        request, "Création contrat",
        f"{contrat.reference} — {maison.designation} / {locataire.nom} — loyer {fcfa(loyer)}",
    )
    return contrat


PREAVIS_MOIS = 3


@transaction.atomic
def enregistrer_preavis(contrat: Contrat, par: str, date_notification=None, motif="", request=None) -> Contrat:
    """Préavis de départ : la sortie est prévue trois mois après la notification (clause du bail)."""
    contrat = Contrat.objects.select_for_update().get(pk=contrat.pk)
    if contrat.statut_contrat != Contrat.Statut.ACTIF:
        raise RegleMetier("Ce bail est déjà terminé.")
    if par not in ("locataire", "bailleur"):
        raise RegleMetier("Indiquez qui donne le préavis : le locataire ou le bailleur.")
    if contrat.preavis_le:
        raise RegleMetier(f"Un préavis a déjà été notifié le {contrat.preavis_le:%d/%m/%Y} (sortie prévue le {contrat.date_sortie_prevue:%d/%m/%Y}).")
    date_notification = date_notification or aujourdhui()
    contrat.preavis_le, contrat.preavis_par, contrat.preavis_motif = date_notification, par, (motif or "")[:255]
    contrat.date_sortie_prevue = ajouter_mois(date_notification, PREAVIS_MOIS)
    contrat.save(update_fields=["preavis_le", "preavis_par", "preavis_motif", "date_sortie_prevue"])
    journaliser(
        request, "Préavis de départ",
        f"{contrat.reference} — préavis du {'locataire' if par == 'locataire' else 'bailleur'} notifié le {date_notification:%d/%m/%Y}, "
        f"sortie prévue le {contrat.date_sortie_prevue:%d/%m/%Y}. {motif}".strip(),
    )
    return contrat


@transaction.atomic
def annuler_preavis(contrat: Contrat, request=None) -> Contrat:
    contrat = Contrat.objects.select_for_update().get(pk=contrat.pk)
    if not contrat.preavis_le:
        raise RegleMetier("Aucun préavis en cours sur ce bail.")
    contrat.preavis_le, contrat.preavis_par, contrat.preavis_motif, contrat.date_sortie_prevue = None, "", "", None
    contrat.save(update_fields=["preavis_le", "preavis_par", "preavis_motif", "date_sortie_prevue"])
    journaliser(request, "Préavis de départ", f"{contrat.reference} — préavis annulé")
    return contrat


@transaction.atomic
def definir_echeance(contrat: Contrat, date_fin, request=None):
    if contrat.statut_contrat != Contrat.Statut.ACTIF:
        raise RegleMetier("Contrat introuvable ou déjà terminé.")
    if date_fin and date_fin <= contrat.date_debut:
        raise RegleMetier("L'échéance doit être postérieure au début du bail.")
    contrat.date_fin = date_fin
    contrat.save(update_fields=["date_fin"])
    journaliser(
        request, "Échéance contrat",
        f"{contrat.reference} : échéance {'fixée au ' + date_fin.strftime('%d/%m/%Y') if date_fin else 'retirée'}",
    )
    return contrat


@transaction.atomic
def reviser_loyer(contrat: Contrat, nouveau_loyer, motif="", date_effet=None, request=None, origine=RevisionLoyer.Origine.BAILLEUR) -> RevisionLoyer:
    contrat = Contrat.objects.select_for_update().get(pk=contrat.pk)
    nouveau = arrondir(nouveau_loyer or 0)
    if nouveau <= 0:
        raise RegleMetier("Montant invalide.")
    if contrat.statut_contrat != Contrat.Statut.ACTIF:
        raise RegleMetier("Impossible de réviser le loyer d'un contrat terminé.")
    if nouveau == contrat.loyer_mensuel:
        raise RegleMetier("Le nouveau loyer est identique au loyer actuel.")
    if origine not in RevisionLoyer.Origine.values:
        raise RegleMetier("Origine de la révision inconnue.")
    date_effet = (date_effet or aujourdhui()).replace(day=1)
    rev = RevisionLoyer.objects.create(
        contrat=contrat, ancien_loyer=contrat.loyer_mensuel, nouveau_loyer=nouveau, motif=motif, origine=origine,
        date_effet=date_effet, effectue_par=request.user if request else None,
    )
    contrat.loyer_mensuel = nouveau
    contrat.save(update_fields=["loyer_mensuel"])
    # Le bien garde le loyer en vigueur : le prochain bail repartira de ce montant
    Maison.objects.filter(pk=contrat.maison_id).update(loyer=nouveau)
    recalculer_echeance(contrat)
    journaliser(
        request, "Révision loyer",
        f"{contrat.reference} : {fcfa(rev.ancien_loyer)} → {fcfa(nouveau)} à partir de {libelle_periode(date_effet.month, date_effet.year)} "
        f"({rev.get_origine_display().lower()}). Motif : {motif}",
    )
    return rev


PERIODE_REVISION_MOIS = 36


def revisions_a_echeance(horizon_jours=90, jour=None):
    """Baux dont le loyer n'a pas été révisé depuis trois ans (depuis le début du bail ou la dernière révision),
    ou qui y arrivent dans `horizon_jours` : la révision triennale peut être proposée au bailleur."""
    from datetime import timedelta

    jour = jour or aujourdhui()
    res = []
    for c in Contrat.objects.filter(statut_contrat=Contrat.Statut.ACTIF).select_related("locataire", "maison__bailleur").prefetch_related("revisions"):
        derniere = max((r.date_effet for r in c.revisions.all() if r.date_effet), default=None)
        base = derniere or c.date_debut
        echeance = ajouter_mois(base, PERIODE_REVISION_MOIS)
        if echeance <= jour + timedelta(days=horizon_jours):
            res.append({
                "contrat": c.pk, "reference": c.reference, "locataire": c.locataire.nom, "maison": c.maison.designation,
                "bailleur": c.maison.bailleur.nom, "loyer": c.loyer_mensuel, "depuis": base, "derniere_revision": derniere,
                "echeance": echeance, "jours": (echeance - jour).days,
            })
    return sorted(res, key=lambda x: x["echeance"])


# ─── Cautions & résiliation ───────────────────────────────────────────────────
def caution_disponible(contrat: Contrat) -> Decimal:
    mvts = contrat.mouvements_caution.aggregate(t=Sum("montant"))["t"] or 0
    return Decimal(contrat.depot_garantie) + Decimal(mvts)


@transaction.atomic
def traiter_caution(
    contrat: Contrat, *, retenue_reparation=0, retenue_loyer=0, cloturer=False, motif="",
    date_effet=None, request=None,
) -> dict:
    """
    Retenues sur caution (contrat actif) ou solde de tout compte (résiliation).
      - retenue « réparations » : versée en caisse agence (Entrée (Réparation)) ;
      - retenue « loyers impayés » : imputée sur les mois impayés les plus anciens,
        comme un encaissement normal (quittance + part bailleur + commission) ;
      - à la clôture, le reste est restitué au locataire et le bien redevient disponible.
    """
    contrat = Contrat.objects.select_for_update().select_related("maison__bailleur", "locataire").get(pk=contrat.pk)
    if contrat.statut_contrat != Contrat.Statut.ACTIF:
        raise RegleMetier("Ce contrat est déjà terminé.")
    rep = arrondir(retenue_reparation or 0)
    loy = arrondir(retenue_loyer or 0)
    if rep < 0 or loy < 0:
        raise RegleMetier("Les retenues ne peuvent pas être négatives.")
    if not cloturer and rep + loy == 0:
        raise RegleMetier("Indiquez au moins un montant de retenue.")
    disponible = caution_disponible(contrat)
    if rep + loy > disponible:
        raise RegleMetier(f"Le total des retenues dépasse la caution disponible ({fcfa(disponible)}).")

    date_effet = date_effet or aujourdhui()
    nom = auteur(request)
    locataire = contrat.locataire
    quittances = []

    if loy > 0:
        impayes = periodes_impayees(contrat, date_effet)
        du = sum((p.reste for p in impayes), Decimal(0))
        if loy > du:
            raise RegleMetier(
                f"La retenue pour loyers ({fcfa(loy)}) dépasse le total des loyers impayés ({fcfa(du)})."
            )
        a_imputer = loy
        for p in impayes:
            if a_imputer <= 0:
                break
            part = min(p.reste, a_imputer)
            enc = enregistrer_encaissement(
                contrat, part, p.mois, p.annee, "especes", request, origine=Encaissement.Origine.CAUTION,
                observation="Imputé sur la caution", controler_dette=False,
            )
            quittances.append(enc.reference_recu)
            a_imputer -= part
        MouvementCaution.objects.create(
            locataire=locataire, contrat=contrat, type_mouvement="retenue", montant=-loy, motif="loyer",
            commentaire=f"Loyers impayés imputés sur caution ({', '.join(quittances)})", effectue_par=nom,
        )
    if rep > 0:
        MouvementCaution.objects.create(
            locataire=locataire, contrat=contrat, type_mouvement="retenue", montant=-rep, motif="reparation",
            commentaire=motif or "Retenue pour réparations", effectue_par=nom,
        )
        MouvementCaisse.objects.create(
            bailleur=contrat.maison.bailleur, type_mouvement="Entrée (Réparation)", montant=rep,
            date_operation=timezone.now(), reference_paiement=contrat.reference, effectue_par=nom,
            commentaire=f"Réparations retenues sur caution — {locataire.nom} — {contrat.maison.designation}",
        )

    restitue = Decimal(0)
    if cloturer:
        restitue = disponible - rep - loy
        if restitue > 0:
            MouvementCaution.objects.create(
                locataire=locataire, contrat=contrat, type_mouvement="restitution", montant=-restitue,
                motif="solde", commentaire="Solde de tout compte (restitution finale)", effectue_par=nom,
            )
        contrat.statut_contrat = Contrat.Statut.TERMINE
        contrat.date_resiliation = date_effet
        contrat.date_fin = date_effet
        contrat.motif_resiliation = motif or ""
        contrat.save(update_fields=["statut_contrat", "date_resiliation", "date_fin", "motif_resiliation"])
        Maison.objects.filter(pk=contrat.maison_id).update(statut=Maison.Statut.DISPONIBLE)

    journaliser(
        request, "Résiliation contrat" if cloturer else "Caution",
        f"{contrat.reference} — {locataire.nom} — réparations {fcfa(rep)}, loyers {fcfa(loy)}"
        + (f", restitué {fcfa(restitue)}" if cloturer else ""),
    )
    return {
        "contrat": contrat.pk, "retenue_reparation": rep, "retenue_loyer": loy,
        "restitue": restitue, "quittances": quittances, "caution_restante": caution_disponible(contrat),
    }
