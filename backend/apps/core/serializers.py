from rest_framework import serializers

from .models import ClauseContrat, ClauseMandat, Journal, Parametres
from .utils import TYPES_IMAGES, valider_fichier


class FichierImageField(serializers.ImageField):
    """ImageField qui vérifie la signature réelle et renomme le fichier (8 Mo max)."""

    def __init__(self, *a, prefixe="img", **kw):
        self.prefixe = prefixe
        kw.setdefault("required", False)
        kw.setdefault("allow_null", True)
        super().__init__(*a, **kw)

    def to_internal_value(self, data):
        if data in ("", None, "null"):
            return None
        f = super().to_internal_value(data)
        return valider_fichier(f, TYPES_IMAGES, self.prefixe)


class ParametresSerializer(serializers.ModelSerializer):
    logo = FichierImageField(prefixe="logo")
    pied_de_page = serializers.SerializerMethodField()

    class Meta:
        model = Parametres
        exclude = ["id"]

    def get_pied_de_page(self, obj):
        return obj.lignes_pied_de_page()

    def validate_taux_commission(self, v):
        if v < 0 or v > 100:
            raise serializers.ValidationError("Le taux doit être compris entre 0 et 100.")
        return v


class ClauseContratSerializer(serializers.ModelSerializer):
    class Meta:
        model = ClauseContrat
        fields = ["id", "titre", "contenu", "ordre_affichage", "actif"]


class ClauseMandatSerializer(serializers.ModelSerializer):
    class Meta:
        model = ClauseMandat
        fields = ["id", "titre", "contenu", "ordre_affichage", "actif"]


class JournalSerializer(serializers.ModelSerializer):
    utilisateur_nom = serializers.CharField(source="utilisateur.nom_complet", default="Système", read_only=True)

    class Meta:
        model = Journal
        fields = ["id", "utilisateur", "utilisateur_nom", "action", "details", "date_action", "ip_adresse"]
