"""Information des locataires sur le mandat de gestion qui lie l'agence au propriétaire de leur logement.

Le bail n'est pas affecté par la fin du mandat : seul l'interlocuteur à qui verser les loyers peut changer.
Chaque message est déposé dans la messagerie de l'espace locataire et, si possible, envoyé par e-mail.
"""
from django.utils.html import escape

from apps.core.models import Parametres
from apps.core.utils import aujourdhui
from apps.locatif.models import Contrat
from apps.locatif.notifications import envoyer
from apps.relations.models import MessageLocataire


def _locataires_concernes(bailleur):
    return Contrat.objects.filter(maison__bailleur=bailleur, statut_contrat="actif").select_related("locataire", "maison")


def _informer(mandat, sujet, texte_pour):
    n = 0
    for c in _locataires_concernes(mandat.bailleur):
        texte = texte_pour(c)
        MessageLocataire.objects.create(locataire=c.locataire, expediteur=MessageLocataire.Expediteur.AGENCE, contenu=texte)
        if c.locataire.email:
            envoyer([c.locataire.email], sujet, sujet, f"<p>Bonjour {escape(c.locataire.nom)},</p><p>{escape(texte)}</p>")
        n += 1
    return n


def informer_locataires_echeance(mandat):
    """Prévient les locataires que le mandat arrive à échéance ; ne s'exécute qu'une fois par mandat."""
    if mandat.locataires_informes_le:
        return 0
    agence = Parametres.charger().nom_entreprise
    fin = mandat.echeance()

    def texte(c):
        return (
            f"Information sur la gestion de votre logement « {c.maison.designation} » : {agence} le gère pour le compte "
            f"de son propriétaire, {mandat.bailleur.nom}, en vertu du mandat de gestion n° {mandat.reference}, "
            f"qui arrive à échéance le {fin:%d/%m/%Y}. Votre bail n'est pas modifié et reste en vigueur. "
            "Si ce mandat n'est pas renouvelé, nous vous indiquerons par écrit, avant cette date, à qui verser vos loyers."
        )

    n = _informer(mandat, "Information sur la gestion de votre logement", texte)
    mandat.locataires_informes_le = aujourdhui()
    mandat.save(update_fields=["locataires_informes_le"])
    return n


def informer_locataires_renouvellement(mandat):
    """Confirme aux locataires que l'agence continue de gérer leur logement."""
    agence = Parametres.charger().nom_entreprise
    fin = mandat.echeance()
    duree = f"jusqu'au {fin:%d/%m/%Y}" if fin else "sans limite de durée"
    if fin and mandat.tacite_reconduction:
        duree += ", renouvelable par tacite reconduction"

    def texte(c):
        return (
            f"Bonne nouvelle : le mandat de gestion de votre logement « {c.maison.designation} » a été renouvelé "
            f"par son propriétaire, {mandat.bailleur.nom} (mandat n° {mandat.reference}). {agence} reste votre "
            f"interlocuteur {duree}. Rien ne change pour vous : continuez à verser vos loyers à l'agence."
        )

    return _informer(mandat, "Renouvellement du mandat de gestion de votre logement", texte)


def figer_existant():
    """Pour des données reprises (import, démonstration) : clauses figées et biens datés sur chaque mandat et bail."""
    from apps.core.models import ClauseContrat, ClauseMandat, clauses_en_vigueur
    from apps.locatif.models import Contrat

    from .models import BienConfie, MandatGestion

    Contrat.objects.filter(clauses_signees__isnull=True).update(clauses_signees=clauses_en_vigueur(ClauseContrat))
    MandatGestion.objects.filter(clauses_signees__isnull=True).update(clauses_signees=clauses_en_vigueur(ClauseMandat))
    for m in MandatGestion.objects.select_related("bailleur"):
        suivant = MandatGestion.objects.filter(bailleur=m.bailleur, date_debut__gt=m.date_debut).order_by("date_debut").first()
        for maison in m.bailleur.maisons.all():
            cree = maison.created_at.date()
            if suivant is None or cree < suivant.date_debut:
                BienConfie.objects.get_or_create(mandat=m, maison=maison, defaults={"confie_le": max(m.date_debut, cree)})
