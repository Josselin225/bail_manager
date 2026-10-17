"""État de l'installation locale : adresses réseau, base, sauvegardes et tâches quotidiennes (Paramètres > Serveur)."""
import shutil
import socket
from pathlib import Path

from django.conf import settings
from django.db import connection
from rest_framework.decorators import api_view, permission_classes
from rest_framework.response import Response

from .models import Journal
from .permissions import EstAdmin


def adresses_reseau():
    """Adresses IPv4 du poste : d'abord celle de l'interface principale (celle qui mène au réseau de l'agence),
    puis les autres (cartes virtuelles, VPN…), rarement utiles."""
    principale = None
    try:
        s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
        s.connect(("8.8.8.8", 80))  # aucun paquet n'est envoyé : sert seulement à connaître l'interface utilisée
        principale = s.getsockname()[0]
        s.close()
    except OSError:
        pass
    try:
        autres = {i[4][0] for i in socket.getaddrinfo(socket.gethostname(), None, socket.AF_INET)}
    except OSError:
        autres = set()
    autres = sorted(ip for ip in autres - {principale} if not ip.startswith("127."))
    return ([principale] if principale and not principale.startswith("127.") else []) + autres


def _derniere(action):
    j = Journal.objects.filter(action=action).order_by("-date_action").first()
    return {"date": j.date_action, "details": j.details} if j else None


@api_view(["GET"])
@permission_classes([EstAdmin])
def systeme(request):
    from apps.connecteurs.models import Connecteurs

    port = request.get_port()
    base = settings.DATABASES["default"]
    sqlite = base["ENGINE"].endswith("sqlite3")
    taille_base = Path(base["NAME"]).stat().st_size if sqlite and Path(base["NAME"]).exists() else None
    dossier = Path(settings.SAUVEGARDES_DIR)
    copies = sorted(dossier.glob("bailmanager_*.zip"), reverse=True) if dossier.exists() else []
    disque = shutil.disk_usage(settings.BASE_DIR)
    cfg = Connecteurs.charger()
    return Response({
        "version": settings.APP_VERSION,
        "poste": socket.gethostname(),
        "adresses": [f"http://{ip}:{port}" for ip in adresses_reseau()],
        "base": {"moteur": "SQLite" if sqlite else "MySQL / MariaDB", "nom": Path(base["NAME"]).name if sqlite else base["NAME"],
                 "taille": taille_base, "vendor": connection.vendor},
        "sauvegardes_locales": {"dossier": str(dossier), "nombre": len(copies),
                                "derniere": {"nom": copies[0].name, "taille": copies[0].stat().st_size} if copies else None},
        "copie_hors_serveur": {"destination": cfg.get_sauvegarde_destination_display(), "date": cfg.derniere_sauvegarde_le, "statut": cfg.derniere_sauvegarde_statut},
        "taches": {"rappels": _derniere("Rappels automatiques"), "sauvegarde": _derniere("Sauvegarde automatique")},
        "disque": {"libre": disque.free, "total": disque.total},
    })
