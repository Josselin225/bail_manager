"""Sauvegarde automatique (à planifier : Planificateur de tâches Windows ou cron)."""
import io
import zipfile
from pathlib import Path

from django.conf import settings
from django.core import management
from django.core.management.base import BaseCommand
from django.utils import timezone

from apps.core.utils import journaliser


class Command(BaseCommand):
    help = "Crée une archive ZIP (données + fichiers) dans le dossier de sauvegardes et garde les N plus récentes."

    def add_arguments(self, parser):
        parser.add_argument("--garder", type=int, default=30)

    def handle(self, *args, **o):
        dossier = Path(settings.SAUVEGARDES_DIR)
        dossier.mkdir(parents=True, exist_ok=True)
        cible = dossier / f"bailmanager_{timezone.localtime():%Y%m%d_%H%M%S}.zip"
        donnees = io.StringIO()
        management.call_command(
            "dumpdata", "--natural-foreign", "--exclude=contenttypes", "--exclude=auth.permission",
            "--exclude=sessions", "--indent=1", stdout=donnees,
        )
        with zipfile.ZipFile(cible, "w", zipfile.ZIP_DEFLATED) as z:
            z.writestr("donnees.json", donnees.getvalue())
            racine = Path(settings.MEDIA_ROOT)
            if racine.exists():
                for f in racine.rglob("*"):
                    if f.is_file():
                        z.write(f, f"uploads/{f.relative_to(racine)}")
        anciennes = sorted(dossier.glob("bailmanager_*.zip"), reverse=True)[o["garder"]:]
        for f in anciennes:
            f.unlink()
        journaliser(None, "Sauvegarde automatique", cible.name)
        self.stdout.write(self.style.SUCCESS(f"Sauvegarde créée : {cible}"))
