"""Permet aux utilisateurs importés de la version PHP de garder leur mot de passe.

PHP password_hash() produit des empreintes bcrypt « $2y$... ». À l'import elles
sont stockées sous la forme « phpbcrypt$$2y$... ». Au premier login réussi,
Django ré-encode automatiquement le mot de passe avec l'algorithme par défaut.
"""
import bcrypt
from django.contrib.auth.hashers import BasePasswordHasher, mask_hash


class PhpBcryptHasher(BasePasswordHasher):
    algorithm = "phpbcrypt"

    def salt(self):
        return ""

    def encode(self, password, salt):
        h = bcrypt.hashpw(password.encode(), bcrypt.gensalt()).decode()
        return f"{self.algorithm}${h}"

    def verify(self, password, encoded):
        _, h = encoded.split("$", 1)
        h = h.replace("$2y$", "$2b$", 1)
        try:
            return bcrypt.checkpw(password.encode(), h.encode())
        except ValueError:
            return False

    def decode(self, encoded):
        _, h = encoded.split("$", 1)
        return {"algorithm": self.algorithm, "hash": h, "salt": ""}

    def safe_summary(self, encoded):
        return {"algorithme": self.algorithm, "empreinte": mask_hash(encoded)}

    def must_update(self, encoded):
        return True

    def harden_runtime(self, password, encoded):
        pass
