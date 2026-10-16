from django.contrib.auth.password_validation import validate_password
from rest_framework import serializers

from apps.core.utils import TYPES_IMAGES, valider_fichier

from .models import Utilisateur


class UtilisateurSerializer(serializers.ModelSerializer):
    password = serializers.CharField(write_only=True, required=False, allow_blank=True)
    role_libelle = serializers.CharField(source="get_role_display", read_only=True)

    class Meta:
        model = Utilisateur
        fields = [
            "id", "nom_complet", "email", "role", "role_libelle", "session_timeout_minutes",
            "photo_profil", "is_active", "dernier_acces", "created_at", "password",
        ]
        read_only_fields = ["dernier_acces", "created_at", "photo_profil"]

    def validate_email(self, v):
        return v.lower().strip()

    def validate_session_timeout_minutes(self, v):
        if v < 1 or v > 480:
            raise serializers.ValidationError("Entre 1 et 480 minutes.")
        return v

    def validate(self, attrs):
        pwd = attrs.get("password")
        if self.instance is None and not pwd:
            raise serializers.ValidationError({"password": "Mot de passe obligatoire."})
        if pwd:
            validate_password(pwd)
        return attrs

    def create(self, data):
        pwd = data.pop("password")
        return Utilisateur.objects.create_user(password=pwd, **data)

    def update(self, instance, data):
        pwd = data.pop("password", None)
        for k, v in data.items():
            setattr(instance, k, v)
        if pwd:
            instance.set_password(pwd)
        instance.save()
        return instance


class ProfilSerializer(serializers.ModelSerializer):
    photo = serializers.ImageField(write_only=True, required=False)

    class Meta:
        model = Utilisateur
        fields = ["id", "nom_complet", "email", "role", "session_timeout_minutes", "photo_profil", "photo", "dernier_acces"]
        read_only_fields = ["role", "photo_profil", "dernier_acces"]

    def validate_photo(self, f):
        return valider_fichier(f, TYPES_IMAGES, "user")

    def update(self, instance, data):
        photo = data.pop("photo", None)
        if photo:
            instance.photo_profil = photo
        return super().update(instance, data)


class ChangementMotDePasseSerializer(serializers.Serializer):
    ancien = serializers.CharField()
    nouveau = serializers.CharField()

    def validate_nouveau(self, v):
        validate_password(v)
        return v
