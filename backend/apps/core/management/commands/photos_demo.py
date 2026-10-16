"""Illustre les maisons sans photo avec les photos de démonstration (apps/core/demo_photos).

Chaque maison reçoit une façade puis des pièces adaptées à son type (studio : salon + cuisine,
villa : salon, chambres, cuisine…). Les maisons qui ont déjà des photos ne sont pas modifiées.
"""
from pathlib import Path

from django.core.files import File
from django.core.management.base import BaseCommand

from apps.patrimoine.models import Maison, PhotoMaison

DOSSIER = Path(__file__).resolve().parents[2] / "demo_photos"


def _serie(prefixe):
    return sorted(DOSSIER.glob(f"{prefixe}-*.jpg"))


class Command(BaseCommand):
    help = "Ajoute des photos de démonstration aux maisons qui n'en ont pas."

    def handle(self, *args, **o):
        series = {p: _serie(p) for p in ("exterieur", "salon", "chambre", "cuisine")}
        if not all(series.values()):
            self.stderr.write(f"Photos de démonstration introuvables dans {DOSSIER}.")
            return
        maisons = list(Maison.objects.filter(photos__isnull=True).order_by("pk"))
        for i, m in enumerate(maisons):
            t = (m.type_maison or "").lower()
            pieces = ["salon", "cuisine"]
            if "studio" not in t:
                pieces.insert(1, "chambre")
            if t.startswith(("villa", "4", "3")):
                pieces.insert(2, "chambre")
            choix = [series["exterieur"][i % len(series["exterieur"])]]
            for k, piece in enumerate(pieces):
                s = series[piece]
                choix.append(s[(i + k) % len(s)])
            for ordre, chemin in enumerate(choix):
                with chemin.open("rb") as f:
                    PhotoMaison.objects.create(maison=m, ordre=ordre, image=File(f, name=f"maison_{m.pk}_{ordre}_{chemin.name}"))
        self.stdout.write(self.style.SUCCESS(f"Photos ajoutées à {len(maisons)} maison(s)."))
