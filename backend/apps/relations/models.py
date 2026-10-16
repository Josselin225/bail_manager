"""Relations : réservations de visites, messages du site et messagerie locataires."""
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
    message = models.TextField(blank=True, default="")
    statut = models.CharField(max_length=12, choices=Statut.choices, default=Statut.EN_ATTENTE)
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
