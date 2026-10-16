from decimal import Decimal

from django.db import transaction
from django.db.models import Sum
from django.utils import timezone

from apps.core.exceptions import RegleMetier
from apps.core.utils import arrondir, auteur, fcfa, journaliser

from .models import CompteCourantBailleur, MouvementCaisse


def solde_bailleur(bailleur) -> Decimal:
    return Decimal(CompteCourantBailleur.objects.filter(bailleur=bailleur).aggregate(t=Sum("montant"))["t"] or 0)


def solde_caisse() -> Decimal:
    return Decimal(MouvementCaisse.objects.aggregate(t=Sum("montant"))["t"] or 0)


@transaction.atomic
def verser_au_bailleur(bailleur, montant, mode="especes", commentaire="", request=None):
    montant = arrondir(montant or 0)
    if montant <= 0:
        raise RegleMetier("Montant invalide.")
    solde = solde_bailleur(bailleur)
    if montant > solde:
        raise RegleMetier(f"Le montant dépasse le solde dû au bailleur ({fcfa(solde)}).")
    op = CompteCourantBailleur.objects.create(
        bailleur=bailleur, date_operation=timezone.now(), type_operation=CompteCourantBailleur.Type.VERSEMENT,
        montant=-montant, mode=mode, commentaire=commentaire or "Versement au bailleur", effectue_par=auteur(request),
    )
    journaliser(request, "Versement bailleur", f"{bailleur.nom} — {fcfa(montant)}")
    return op


@transaction.atomic
def mouvement_caisse(type_mouvement, montant, commentaire="", request=None, sortie=False, reference=""):
    montant = arrondir(montant or 0)
    if montant <= 0:
        raise RegleMetier("Montant invalide.")
    if sortie:
        solde = solde_caisse()
        if montant > solde:
            raise RegleMetier(f"Solde de caisse insuffisant ({fcfa(solde)}).")
    mvt = MouvementCaisse.objects.create(
        type_mouvement=type_mouvement, montant=-montant if sortie else montant, commentaire=commentaire,
        date_operation=timezone.now(), effectue_par=auteur(request), reference_paiement=reference,
    )
    journaliser(request, "Retrait caisse" if sortie else "Mouvement caisse", f"{type_mouvement} : {fcfa(montant)} — {commentaire}")
    return mvt
