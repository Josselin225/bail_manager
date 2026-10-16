"""Sauvegarde automatique (à planifier : Planificateur de tâches Windows ou cron)."""
from pathlib import Path

from django.conf import settings
from django.core.management.base import BaseCommand
from django.utils import timezone

from apps.core.exceptions import RegleMetier
from apps.core.sauvegarde import creer_archive, sauvegarder_en_ligne
from apps.core.utils import journaliser


class Command(BaseCommand):
    help = ("Crée une archive ZIP (données + fichiers) dans le dossier de sauvegardes, garde les N plus récentes, "
            "puis en envoie une copie chiffrée vers la destination réglée dans Paramètres > Connecteurs.")

    def add_arguments(self, parser):
        parser.add_argument("--garder", type=int, default=30)

    def handle(self, *args, **o):
        dossier = Path(settings.SAUVEGARDES_DIR)
        dossier.mkdir(parents=True, exist_ok=True)
        cible = dossier / f"bailmanager_{timezone.localtime():%Y%m%d_%H%M%S}.zip"
        creer_archive(cible)
        for f in sorted(dossier.glob("bailmanager_*.zip"), reverse=True)[o["garder"]:]:
            f.unlink()
        journaliser(None, "Sauvegarde automatique", cible.name)
        self.stdout.write(self.style.SUCCESS(f"Sauvegarde créée : {cible}"))
        try:
            statut = sauvegarder_en_ligne(cible)
            if statut:
                self.stdout.write(self.style.SUCCESS(f"Copie hors serveur : {statut}"))
        except RegleMetier as e:
            self.stderr.write(f"Copie hors serveur en échec : {e}")
