from rest_framework import serializers

from .models import MessageLocataire, MessageVisiteur, Reservation


class ReservationSerializer(serializers.ModelSerializer):
    maison_nom = serializers.CharField(source="maison.designation", read_only=True)
    maison_statut = serializers.CharField(source="maison.statut", read_only=True)
    statut_libelle = serializers.CharField(source="get_statut_display", read_only=True)

    class Meta:
        model = Reservation
        fields = ["id", "maison", "maison_nom", "maison_statut", "nom_visiteur", "tel_visiteur", "date_visite", "message", "statut", "statut_libelle", "created_at"]


class MessageVisiteurSerializer(serializers.ModelSerializer):
    class Meta:
        model = MessageVisiteur
        fields = ["id", "nom_visiteur", "email_visiteur", "telephone", "sujet", "contenu", "lu", "date_envoi"]


class MessageLocataireSerializer(serializers.ModelSerializer):
    class Meta:
        model = MessageLocataire
        fields = ["id", "locataire", "expediteur", "contenu", "lu", "created_at"]
        read_only_fields = ["expediteur", "lu", "created_at"]
