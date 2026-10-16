from rest_framework import serializers

from .models import CompteCourantBailleur, MouvementCaisse


class OperationBailleurSerializer(serializers.ModelSerializer):
    bailleur_nom = serializers.CharField(source="bailleur.nom", read_only=True)
    type_libelle = serializers.CharField(source="get_type_operation_display", read_only=True)
    reference_recu = serializers.CharField(source="encaissement.reference_recu", read_only=True, default="")

    class Meta:
        model = CompteCourantBailleur
        fields = ["id", "bailleur", "bailleur_nom", "date_operation", "type_operation", "type_libelle", "montant", "commentaire", "mode", "reference_recu", "effectue_par"]


class MouvementCaisseSerializer(serializers.ModelSerializer):
    bailleur_nom = serializers.CharField(source="bailleur.nom", read_only=True, default="")

    class Meta:
        model = MouvementCaisse
        fields = ["id", "bailleur", "bailleur_nom", "type_mouvement", "montant", "date_operation", "reference_paiement", "commentaire", "effectue_par"]
