"""Chiffrement des secrets stockés en base (clés des fournisseurs SMS, paiement, sauvegarde).

La clé vient de CLE_CHIFFREMENT (fichier .env) ou, à défaut, de SECRET_KEY : une copie volée de la base
ne suffit donc pas à lire les secrets. Changer cette clé rend illisibles les secrets déjà enregistrés
(ils sont alors à ressaisir dans Paramètres > Connecteurs).
"""
import base64
import hashlib
import os

from django.conf import settings
from django.db import models

PREFIXE = "enc1:"


def _fernet():
    from cryptography.fernet import Fernet

    source = os.environ.get("CLE_CHIFFREMENT") or settings.SECRET_KEY
    cle = hashlib.sha256(("bailmanager-secrets:" + source).encode()).digest()
    return Fernet(base64.urlsafe_b64encode(cle))


def chiffrer_texte(texte: str) -> str:
    if not texte or texte.startswith(PREFIXE):
        return texte or ""
    return PREFIXE + _fernet().encrypt(texte.encode()).decode()


def dechiffrer_texte(valeur: str) -> str:
    from cryptography.fernet import InvalidToken

    if not valeur or not valeur.startswith(PREFIXE):
        return valeur or ""
    try:
        return _fernet().decrypt(valeur[len(PREFIXE):].encode()).decode()
    except InvalidToken:
        return ""  # clé changée : le secret doit être ressaisi


class ChampSecret(models.TextField):
    """Texte chiffré en base, en clair dans le code."""

    def from_db_value(self, valeur, expression, connection):
        return dechiffrer_texte(valeur)

    def to_python(self, valeur):
        return dechiffrer_texte(valeur) if isinstance(valeur, str) else valeur

    def get_prep_value(self, valeur):
        return chiffrer_texte(super().get_prep_value(valeur))
