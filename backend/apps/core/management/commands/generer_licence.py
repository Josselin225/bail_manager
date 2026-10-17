"""Outil de l'éditeur : fabrique la licence d'une agence (nécessite la clé privée, qui ne quitte jamais votre poste).

Exemple :
    python manage.py generer_licence --agence "Agence Le Berger" --jusquau 2027-12-31 --utilisateurs 3 --biens 80 \\
        --modules connecteurs,espace_bailleur --cle C:\\Users\\kkjos\\BailManager-licences\\cle-privee-licences.pem
"""
import os
import secrets
from datetime import date
from pathlib import Path

from django.core.management.base import BaseCommand, CommandError

from apps.core.licence import MODULES, lire, signer


class Command(BaseCommand):
    help = "Génère le texte de licence d'une agence (à coller dans Paramètres > Licence)."

    def add_arguments(self, parser):
        parser.add_argument("--agence", required=True)
        parser.add_argument("--jusquau", required=True, help="Date de fin, AAAA-MM-JJ")
        parser.add_argument("--utilisateurs", type=int, help="Nombre maximal d'utilisateurs actifs (vide = illimité)")
        parser.add_argument("--biens", type=int, help="Nombre maximal de biens (vide = illimité)")
        parser.add_argument("--modules", default="", help=f"Modules séparés par des virgules, ou « tous » : {', '.join(MODULES)}")
        parser.add_argument("--cle", default=os.environ.get("BAILMANAGER_CLE_LICENCE"), help="Fichier de la clé privée (.pem)")

    def handle(self, *args, **o):
        if not o["cle"] or not Path(o["cle"]).exists():
            raise CommandError("Clé privée introuvable : indiquez --cle <fichier .pem> (ou la variable BAILMANAGER_CLE_LICENCE).")
        try:
            fin = date.fromisoformat(o["jusquau"])
        except ValueError:
            raise CommandError("Date de fin invalide (format AAAA-MM-JJ).")
        modules = list(MODULES) if o["modules"].strip() == "tous" else [m.strip() for m in o["modules"].split(",") if m.strip()]
        inconnus = [m for m in modules if m not in MODULES]
        if inconnus:
            raise CommandError(f"Module(s) inconnu(s) : {', '.join(inconnus)}. Disponibles : {', '.join(MODULES)}.")
        donnees = {"numero": f"LIC-{date.today():%Y%m}-{secrets.token_hex(3).upper()}", "agence": o["agence"], "emise_le": date.today().isoformat(),
                   "expire_le": fin.isoformat(), "utilisateurs": o["utilisateurs"], "biens": o["biens"], "modules": modules}
        texte = signer(donnees, Path(o["cle"]).read_bytes())
        lire(texte)  # contrôle : la licence produite est bien acceptée par le logiciel
        self.stdout.write(self.style.SUCCESS(f"Licence {donnees['numero']} — {o['agence']} — jusqu'au {fin:%d/%m/%Y}"))
        self.stdout.write(f"Utilisateurs : {o['utilisateurs'] or 'illimité'} — Biens : {o['biens'] or 'illimité'} — Modules : {', '.join(modules) or 'aucun'}")
        self.stdout.write("\nTexte à coller dans Paramètres > Licence :\n")
        self.stdout.write(texte)
