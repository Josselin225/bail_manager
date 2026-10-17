"""Relations : réservations de visites, messages du site et messagerie locataires."""
from django.conf import settings
from django.db import models

from apps.patrimoine.models import Locataire, Maison


class Reservation(models.Model):
    class Statut(models.TextChoices):
        EN_ATTENTE = "en_attente", "En attente"
        CONFIRME = "confirme", "Confirmée"
        TERMINE = "termine", "Visite effectuée"
        ANNULE = "annule", "Annulée"

    maison = models.ForeignKey(Maison, on_delete=models.CASCADE, related_name="reservations")
    nom_visiteur = models.CharField(max_length=255)
    tel_visiteur = models.CharField(max_length=50)
    date_visite = models.DateField()
    heure_visite = models.TimeField(null=True, blank=True)
    message = models.TextField(blank=True, default="")
    statut = models.CharField(max_length=12, choices=Statut.choices, default=Statut.EN_ATTENTE)
    origine = models.CharField(max_length=10, choices=[("site", "Site internet"), ("telephone", "Téléphone"), ("agence", "À l'agence")], default="site")
    agent = models.ForeignKey(settings.AUTH_USER_MODEL, null=True, blank=True, on_delete=models.SET_NULL, related_name="visites")
    compte_rendu = models.TextField(blank=True, default="")
    interesse = models.BooleanField(null=True, blank=True)
    created_at = models.DateTimeField(auto_now_add=True)

    class Meta:
        db_table = "reservations"
        ordering = ["-created_at"]


class MessageVisiteur(models.Model):
    nom_visiteur = models.CharField(max_length=255)
    email_visiteur = models.EmailField(max_length=255)
    telephone = models.CharField(max_length=50, blank=True, default="")
    sujet = models.CharField(max_length=255, blank=True, default="")
    contenu = models.TextField()
    lu = models.BooleanField(default=False)
    date_envoi = models.DateTimeField(auto_now_add=True)

    class Meta:
        db_table = "messages"
        ordering = ["-date_envoi"]


class MessageLocataire(models.Model):
    class Expediteur(models.TextChoices):
        LOCATAIRE = "locataire", "Locataire"
        AGENCE = "agence", "Agence"

    locataire = models.ForeignKey(Locataire, on_delete=models.CASCADE, related_name="messages")
    expediteur = models.CharField(max_length=10, choices=Expediteur.choices)
    contenu = models.TextField()
    lu = models.BooleanField(default=False)
    created_at = models.DateTimeField(auto_now_add=True)

    class Meta:
        db_table = "messages_locataires"
        ordering = ["created_at", "id"]


class DemandeIntervention(models.Model):
    """Panne ou travaux signalés par le locataire (ou saisis par l'agence) : suivi jusqu'à la clôture et l'imputation du coût."""

    class Categorie(models.TextChoices):
        PLOMBERIE = "plomberie", "Plomberie"
        ELECTRICITE = "electricite", "Électricité"
        MACONNERIE = "maconnerie", "Maçonnerie"
        MENUISERIE = "menuiserie", "Menuiserie, serrurerie"
        PEINTURE = "peinture", "Peinture"
        CLIMATISATION = "climatisation", "Climatisation"
        AUTRE = "autre", "Autre"

    class Statut(models.TextChoices):
        NOUVELLE = "nouvelle", "Nouvelle"
        EN_COURS = "en_cours", "En cours"
        TERMINEE = "terminee", "Terminée"
        ANNULEE = "annulee", "Annulée"

    class Imputation(models.TextChoices):
        BAILLEUR = "bailleur", "Au bailleur (compte courant)"
        LOCATAIRE = "locataire", "Au locataire (charge)"
        AGENCE = "agence", "À l'agence (caisse)"
        AUCUNE = "aucune", "Sans frais"

    maison = models.ForeignKey(Maison, on_delete=models.PROTECT, related_name="interventions")
    contrat = models.ForeignKey("locatif.Contrat", null=True, blank=True, on_delete=models.SET_NULL, related_name="interventions")
    locataire = models.ForeignKey(Locataire, null=True, blank=True, on_delete=models.SET_NULL, related_name="interventions")
    origine = models.CharField(max_length=10, default="agence", choices=[("locataire", "Locataire"), ("agence", "Agence")])
    categorie = models.CharField(max_length=15, choices=Categorie.choices, default=Categorie.AUTRE)
    urgente = models.BooleanField(default=False)
    titre = models.CharField(max_length=150)
    description = models.TextField(blank=True, default="")
    statut = models.CharField(max_length=10, choices=Statut.choices, default=Statut.NOUVELLE)
    artisan = models.CharField(max_length=150, blank=True, default="")
    artisan_telephone = models.CharField(max_length=30, blank=True, default="")
    date_intervention = models.DateField(null=True, blank=True)
    cout = models.DecimalField(max_digits=12, decimal_places=0, default=0)
    imputation = models.CharField(max_length=10, choices=Imputation.choices, blank=True, default="")
    commentaire_agence = models.TextField(blank=True, default="")
    cloturee_le = models.DateTimeField(null=True, blank=True)
    created_at = models.DateTimeField(auto_now_add=True)
    updated_at = models.DateTimeField(auto_now=True)

    class Meta:
        db_table = "demandes_intervention"
        ordering = ["-created_at", "-id"]

    @property
    def reference(self):
        return f"INT-{self.pk:04d}" if self.pk else ""


class PhotoIntervention(models.Model):
    demande = models.ForeignKey(DemandeIntervention, on_delete=models.CASCADE, related_name="photos")
    image = models.ImageField(upload_to="interventions/")
    created_at = models.DateTimeField(auto_now_add=True)

    class Meta:
        db_table = "photos_interventions"
        ordering = ["id"]
