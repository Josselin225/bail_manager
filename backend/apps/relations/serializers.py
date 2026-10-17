from rest_framework import serializers

from .models import MessageLocataire, MessageVisiteur, Reservation


class ReservationSerializer(serializers.ModelSerializer):
    maison_nom = serializers.CharField(source="maison.designation", read_only=True)
    maison_statut = serializers.CharField(source="maison.statut", read_only=True)
    statut_libelle = serializers.CharField(source="get_statut_display", read_only=True)
    maison_adresse = serializers.CharField(source="maison.adresse", read_only=True)
    agent_nom = serializers.CharField(source="agent.nom_complet", read_only=True, default="")
    origine_libelle = serializers.CharField(source="get_origine_display", read_only=True)

    class Meta:
        model = Reservation
        fields = ["id", "maison", "maison_nom", "maison_statut", "maison_adresse", "nom_visiteur", "tel_visiteur", "date_visite", "heure_visite", "message",
                  "statut", "statut_libelle", "origine", "origine_libelle", "agent", "agent_nom", "compte_rendu", "interesse", "created_at"]


class MessageVisiteurSerializer(serializers.ModelSerializer):
    class Meta:
        model = MessageVisiteur
        fields = ["id", "nom_visiteur", "email_visiteur", "telephone", "sujet", "contenu", "lu", "date_envoi"]


class MessageLocataireSerializer(serializers.ModelSerializer):
    class Meta:
        model = MessageLocataire
        fields = ["id", "locataire", "expediteur", "contenu", "lu", "created_at"]
        read_only_fields = ["expediteur", "lu", "created_at"]
