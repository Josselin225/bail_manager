"""
Lance BailManager en production (Windows ou Linux) avec Waitress.
    python serveur.py            -> http://<ip-du-poste>:8000
    python serveur.py --port 80
Les autres postes du réseau ouvrent http://<ip-du-serveur>:8000 dans leur navigateur.
"""
import argparse
import os
import socket

os.environ.setdefault("DJANGO_SETTINGS_MODULE", "config.settings")

from waitress import serve  # noqa: E402

from config.wsgi import application  # noqa: E402


def ip_locale():
    try:
        s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
        s.connect(("8.8.8.8", 80))
        ip = s.getsockname()[0]
        s.close()
        return ip
    except OSError:
        return "127.0.0.1"


if __name__ == "__main__":
    p = argparse.ArgumentParser()
    p.add_argument("--port", type=int, default=int(os.environ.get("PORT", 8000)))
    p.add_argument("--hote", default="0.0.0.0")
    a = p.parse_args()
    print(f"BailManager démarré.\n  Sur ce poste       : http://localhost:{a.port}\n  Sur le réseau local : http://{ip_locale()}:{a.port}\nFermez cette fenêtre pour arrêter le serveur.")
    serve(application, host=a.hote, port=a.port, threads=8)
