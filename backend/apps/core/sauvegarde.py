"""Archive de sauvegarde (données + fichiers), chiffrement et copie hors du serveur.

Toute copie envoyée hors du serveur est chiffrée (AES-256-GCM, clé dérivée de la phrase secrète par scrypt) :
l'archive contient des données personnelles (pièces d'identité, contrats). Sans la phrase, elle est illisible ;
`python manage.py dechiffrer_sauvegarde <fichier>` la rend à nouveau exploitable.
"""
import hashlib
import hmac
import io
import os
import urllib.error
import urllib.parse
import urllib.request
import zipfile
from datetime import datetime, timezone as tz
from pathlib import Path

from django.conf import settings
from django.core import management
from django.utils import timezone

from .exceptions import RegleMetier

ENTETE = b"BMSAV1"
EXTENSION = ".zip.chiffre"


def creer_archive(destination) -> None:
    """Écrit l'archive ZIP (données JSON + dossier des fichiers envoyés) dans `destination` (chemin ou flux)."""
    donnees = io.StringIO()
    management.call_command(
        "dumpdata", "--natural-foreign", "--exclude=contenttypes", "--exclude=auth.permission",
        "--exclude=sessions", "--indent=1", stdout=donnees,
    )
    with zipfile.ZipFile(destination, "w", zipfile.ZIP_DEFLATED) as z:
        z.writestr("donnees.json", donnees.getvalue())
        racine = Path(settings.MEDIA_ROOT)
        if racine.exists():
            for f in racine.rglob("*"):
                if f.is_file():
                    z.write(f, f"uploads/{f.relative_to(racine)}")


def _cle(phrase: str, sel: bytes) -> bytes:
    return hashlib.scrypt(phrase.encode(), salt=sel, n=2**15, r=8, p=1, maxmem=128 * 1024 * 1024, dklen=32)


def chiffrer(contenu: bytes, phrase: str) -> bytes:
    from cryptography.hazmat.primitives.ciphers.aead import AESGCM

    if len(phrase or "") < 12:
        raise RegleMetier("La phrase de chiffrement doit compter au moins 12 caractères.")
    sel, nonce = os.urandom(16), os.urandom(12)
    return ENTETE + sel + nonce + AESGCM(_cle(phrase, sel)).encrypt(nonce, contenu, ENTETE)


def dechiffrer(contenu: bytes, phrase: str) -> bytes:
    from cryptography.exceptions import InvalidTag
    from cryptography.hazmat.primitives.ciphers.aead import AESGCM

    if not contenu.startswith(ENTETE):
        raise RegleMetier("Ce fichier n'est pas une sauvegarde chiffrée BailManager.")
    sel, nonce, corps = contenu[6:22], contenu[22:34], contenu[34:]
    try:
        return AESGCM(_cle(phrase, sel)).decrypt(nonce, corps, ENTETE)
    except InvalidTag:
        raise RegleMetier("Phrase de chiffrement incorrecte (ou fichier altéré).")


# ─── Destinations ─────────────────────────────────────────────────────────────
def _vers_dossier(cfg, nom, contenu):
    dossier = Path(cfg.sauvegarde_dossier)
    if not cfg.sauvegarde_dossier or not dossier.exists():
        raise RegleMetier(f"Dossier introuvable : « {cfg.sauvegarde_dossier} ». Branchez le disque ou corrigez le chemin.")
    (dossier / nom).write_bytes(contenu)
    for vieux in sorted(dossier.glob(f"bailmanager_*{EXTENSION}"), reverse=True)[max(1, cfg.sauvegarde_garder):]:
        vieux.unlink()
    return str(dossier / nom)


def _requete(req):
    try:
        with urllib.request.urlopen(req, timeout=300) as r:
            return r.status
    except urllib.error.HTTPError as e:
        raise RegleMetier(f"Le stockage en ligne a refusé l'envoi ({e.code}) : {e.read().decode(errors='replace')[:300]}")
    except urllib.error.URLError as e:
        raise RegleMetier(f"Stockage en ligne injoignable : {e.reason}")


def _vers_webdav(cfg, nom, contenu):
    import base64

    url = cfg.sauvegarde_url.rstrip("/") + "/" + urllib.parse.quote(nom)
    jeton = base64.b64encode(f"{cfg.sauvegarde_identifiant}:{cfg.sauvegarde_secret}".encode()).decode()
    _requete(urllib.request.Request(url, data=contenu, method="PUT", headers={"Authorization": f"Basic {jeton}", "Content-Type": "application/octet-stream"}))
    return url


def _signer(cle, texte):
    return hmac.new(cle, texte.encode(), hashlib.sha256).digest()


def _vers_s3(cfg, nom, contenu):
    """PUT signé AWS Signature V4 (AWS S3, Backblaze B2, Wasabi, Cloudflare R2, MinIO…), adressage par chemin."""
    point = urllib.parse.urlparse(cfg.sauvegarde_url if "://" in cfg.sauvegarde_url else f"https://{cfg.sauvegarde_url}")
    region = cfg.sauvegarde_region or "us-east-1"
    maintenant = datetime.now(tz.utc)
    horodatage, jour = maintenant.strftime("%Y%m%dT%H%M%SZ"), maintenant.strftime("%Y%m%d")
    empreinte = hashlib.sha256(contenu).hexdigest()
    chemin = "/" + "/".join(urllib.parse.quote(p, safe="-_.~") for p in [cfg.sauvegarde_bucket, *f"bailmanager/{nom}".split("/")])
    entetes = {"host": point.netloc, "x-amz-content-sha256": empreinte, "x-amz-date": horodatage}
    signes = ";".join(sorted(entetes))
    canonique = "\n".join(["PUT", chemin, "", "".join(f"{k}:{entetes[k]}\n" for k in sorted(entetes)), signes, empreinte])
    portee = f"{jour}/{region}/s3/aws4_request"
    a_signer = "\n".join(["AWS4-HMAC-SHA256", horodatage, portee, hashlib.sha256(canonique.encode()).hexdigest()])
    cle = _signer(_signer(_signer(_signer(("AWS4" + cfg.sauvegarde_secret).encode(), jour), region), "s3"), "aws4_request")
    signature = hmac.new(cle, a_signer.encode(), hashlib.sha256).hexdigest()
    entetes["Authorization"] = f"AWS4-HMAC-SHA256 Credential={cfg.sauvegarde_identifiant}/{portee}, SignedHeaders={signes}, Signature={signature}"
    url = f"{point.scheme}://{point.netloc}{chemin}"
    _requete(urllib.request.Request(url, data=contenu, method="PUT", headers={k: v for k, v in entetes.items() if k != "host"}))
    return url


DESTINATIONS = {"dossier": _vers_dossier, "webdav": _vers_webdav, "s3": _vers_s3}


def sauvegarder_en_ligne(archive: Path | None = None, request=None):
    """Chiffre l'archive (créée si besoin) et l'envoie vers la destination réglée ; consigne le résultat."""
    from apps.connecteurs.models import Connecteurs

    from .utils import journaliser

    cfg = Connecteurs.charger()
    if cfg.sauvegarde_destination == "desactive":
        return None
    try:
        if archive is None:
            tampon = io.BytesIO()
            creer_archive(tampon)
            contenu = tampon.getvalue()
        else:
            contenu = Path(archive).read_bytes()
        nom = f"bailmanager_{timezone.localtime():%Y%m%d_%H%M%S}{EXTENSION}"
        emplacement = DESTINATIONS[cfg.sauvegarde_destination](cfg, nom, chiffrer(contenu, cfg.sauvegarde_phrase))
        cfg.derniere_sauvegarde_le, cfg.derniere_sauvegarde_statut = timezone.now(), f"Réussie : {emplacement} ({len(contenu) // 1024} Ko)"
        cfg.save(update_fields=["derniere_sauvegarde_le", "derniere_sauvegarde_statut"])
        journaliser(request, "Sauvegarde en ligne", cfg.derniere_sauvegarde_statut)
        return cfg.derniere_sauvegarde_statut
    except Exception as e:
        cfg.derniere_sauvegarde_le, cfg.derniere_sauvegarde_statut = timezone.now(), f"Échec : {e}"
        cfg.save(update_fields=["derniere_sauvegarde_le", "derniere_sauvegarde_statut"])
        journaliser(request, "Sauvegarde en ligne", cfg.derniere_sauvegarde_statut)
        raise RegleMetier(str(e)) if not isinstance(e, RegleMetier) else e
