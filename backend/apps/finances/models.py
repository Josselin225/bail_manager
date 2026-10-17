"""Comptabilité : compte courant de chaque bailleur et caisse interne de l'agence."""
from django.db import models

from apps.patrimoine.models import Bailleur


class CompteCourantBailleur(models.Model):
    """Historique = source de vérité : le solde d'un bailleur est toujours la somme des lignes."""

    class Type(models.TextChoices):
        LOYER = "loyer_encaisse", "Loyer encaissé (net)"
        VERSEMENT = "versement_bailleur", "Versement au bailleur"
        AJUSTEMENT = "ajustement", "Ajustement"

    bailleur = models.ForeignKey(Bailleur, on_delete=models.PROTECT, related_name="operations")
    date_operation = models.DateTimeField(db_index=True)
    type_operation = models.CharField(max_length=30, choices=Type.choices)
    montant = models.DecimalField(max_digits=12, decimal_places=0)
    commentaire = models.TextField(blank=True, default="")
    mode = models.CharField(max_length=30, blank=True, default="")
    encaissement = models.ForeignKey("locatif.Encaissement", null=True, blank=True, on_delete=models.PROTECT, related_name="+")
    effectue_par = models.CharField(max_length=100, blank=True, default="")

    class Meta:
        db_table = "compte_courant_bailleur"
        ordering = ["-date_operation", "-id"]


class MouvementCaisse(models.Model):
    TYPES_ENTREE = ["Commission Gestion", "Droit d'agence", "Entrée (Réparation)", "Autre entrée"]
    TYPES_SORTIE = ["Retrait/Dépense"]

    bailleur = models.ForeignKey(Bailleur, null=True, blank=True, on_delete=models.SET_NULL, related_name="+")
    type_mouvement = models.CharField(max_length=50)
    montant = models.DecimalField(max_digits=12, decimal_places=0)
    date_operation = models.DateTimeField(db_index=True)
    reference_paiement = models.CharField(max_length=100, blank=True, default="")
    commentaire = models.TextField(blank=True, default="")
    effectue_par = models.CharField(max_length=100, blank=True, default="")
    encaissement = models.ForeignKey("locatif.Encaissement", null=True, blank=True, on_delete=models.PROTECT, related_name="+")

    class Meta:
        db_table = "mouvements_caisse_entreprise"
        ordering = ["-date_operation", "-id"]


class LigneRapprochee(models.Model):
    """Ligne d'un relevé Mobile Money / bancaire déjà encaissée : son empreinte empêche tout double encaissement."""

    empreinte = models.CharField(max_length=40, unique=True)
    source = models.CharField(max_length=60, blank=True, default="")
    date_operation = models.DateField()
    montant = models.DecimalField(max_digits=12, decimal_places=0)
    telephone = models.CharField(max_length=30, blank=True, default="")
    libelle = models.CharField(max_length=255, blank=True, default="")
    reference = models.CharField(max_length=120, blank=True, default="")
    contrat = models.ForeignKey("locatif.Contrat", on_delete=models.PROTECT, related_name="lignes_rapprochees")
    encaissement = models.ForeignKey("locatif.Encaissement", null=True, blank=True, on_delete=models.SET_NULL, related_name="+")
    reste_non_affecte = models.DecimalField(max_digits=12, decimal_places=0, default=0)
    created_at = models.DateTimeField(auto_now_add=True)

    class Meta:
        db_table = "lignes_rapprochees"
        ordering = ["-created_at", "-id"]
