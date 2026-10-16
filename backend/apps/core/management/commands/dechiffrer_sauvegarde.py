"""Rend lisible une sauvegarde chiffrée envoyée hors du serveur (fichier .zip.chiffre)."""
import getpass
from pathlib import Path

from django.core.management.base import BaseCommand, CommandError

from apps.core.exceptions import RegleMetier
from apps.core.sauvegarde import dechiffrer


class Command(BaseCommand):
    help = "Déchiffre une sauvegarde .zip.chiffre en archive .zip (la phrase de chiffrement est demandée)."

    def add_arguments(self, parser):
        parser.add_argument("fichier")
        parser.add_argument("--phrase", help="Phrase de chiffrement (sinon demandée sans affichage).")

    def handle(self, *args, **o):
        source = Path(o["fichier"])
        if not source.exists():
            raise CommandError(f"Fichier introuvable : {source}")
        phrase = o["phrase"] or getpass.getpass("Phrase de chiffrement : ")
        try:
            contenu = dechiffrer(source.read_bytes(), phrase)
        except RegleMetier as e:
            raise CommandError(str(e))
        cible = source.with_name(source.name.removesuffix(".chiffre"))
        cible.write_bytes(contenu)
        self.stdout.write(self.style.SUCCESS(f"Archive déchiffrée : {cible}"))
