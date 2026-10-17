"""Fonctions utilitaires partagées (équivalent de config/db.php de la version PHP)."""
import calendar
import uuid
from datetime import date, timedelta
from decimal import ROUND_HALF_UP, Decimal

from django.conf import settings
from django.utils import timezone

from .exceptions import RegleMetier

MOIS = [
    "Janvier", "Février", "Mars", "Avril", "Mai", "Juin",
    "Juillet", "Août", "Septembre", "Octobre", "Novembre", "Décembre",
]


# ─── Dates & périodes ─────────────────────────────────────────────────────────
def ajouter_mois(d: date, n: int) -> date:
    """Ajoute n mois à une date en conservant le jour (borné à la fin du mois)."""
    mois_total = d.month - 1 + n
    annee = d.year + mois_total // 12
    mois = mois_total % 12 + 1
    jour = min(d.day, calendar.monthrange(annee, mois)[1])
    return date(annee, mois, jour)


def duree_entre(debut: date, fin: date) -> tuple[int, int, int]:
    """Durée exacte (années, mois, jours) de « debut » à « fin » ; (0, 0, 0) si fin est antérieure."""
    if fin <= debut:
        return 0, 0, 0
    mois = (fin.year - debut.year) * 12 + fin.month - debut.month
    if ajouter_mois(debut, mois) > fin:
        mois -= 1
    jours = (fin - ajouter_mois(debut, mois)).days
    return mois // 12, mois % 12, jours


def libelle_duree(annees: int, mois: int, jours: int) -> str:
    """« 2 ans 3 mois », « 5 mois 12 jours », « 18 jours » — les jours ne sont cités qu'en deçà d'un an."""
    parties = []
    if annees:
        parties.append(f"{annees} an{'s' if annees > 1 else ''}")
    if mois:
        parties.append(f"{mois} mois")
    if jours and not annees:
        parties.append(f"{jours} jour{'s' if jours > 1 else ''}")
    return " ".join(parties) or "Moins d'un jour"


def libelle_periode(mois: int, annee: int) -> str:
    return f"{MOIS[mois - 1]} {annee}"


def index_periode(mois: int, annee: int) -> int:
    """Rang absolu d'un mois — permet de comparer/itérer des périodes facilement."""
    return annee * 12 + (mois - 1)


def periode_depuis_index(i: int):
    return i % 12 + 1, i // 12


def parser_periode(texte: str):
    """'Février 2026' -> (2, 2026). Tolère accents/casse (données de la version PHP)."""
    import unicodedata

    def norm(s):
        return "".join(c for c in unicodedata.normalize("NFD", s.lower()) if unicodedata.category(c) != "Mn")

    parts = (texte or "").strip().split()
    if len(parts) != 2 or not parts[1].isdigit():
        return None
    noms = [norm(m) for m in MOIS]
    try:
        return noms.index(norm(parts[0])) + 1, int(parts[1])
    except ValueError:
        return None


def aujourdhui() -> date:
    return timezone.localdate()


# ─── Montants ─────────────────────────────────────────────────────────────────
def arrondir(montant) -> Decimal:
    """Le FCFA n'a pas de subdivision : on arrondit à l'unité."""
    return Decimal(montant).quantize(Decimal("1"), rounding=ROUND_HALF_UP)


def fcfa(montant) -> str:
    return f"{int(arrondir(montant or 0)):,}".replace(",", " ") + " FCFA"


# ─── Journal d'audit ──────────────────────────────────────────────────────────
def ip_client(request) -> str:
    if request is None:
        return "CLI"
    fwd = request.META.get("HTTP_X_FORWARDED_FOR")
    return (fwd.split(",")[0].strip() if fwd else request.META.get("REMOTE_ADDR", "")) or "0.0.0.0"


def journaliser(request, action: str, details: str = "") -> None:
    from .models import Journal

    user = getattr(request, "user", None) if request is not None else None
    Journal.objects.create(
        utilisateur=user if (user is not None and user.is_authenticated) else None,
        action=action,
        details=details,
        ip_adresse=ip_client(request),
    )


def auteur(request) -> str:
    user = getattr(request, "user", None)
    return getattr(user, "nom_complet", None) or "Administrateur"


# ─── Anti brute-force persistant ──────────────────────────────────────────────
def limite_atteinte(contexte: str, ip: str, maximum=5, fenetre=600) -> int:
    """Retourne le nombre de secondes d'attente restantes (0 si non bloqué)."""
    from .models import RateLimit

    rl = RateLimit.objects.filter(cle=f"{contexte}:{ip}").first()
    if not rl:
        return 0
    ecoule = (timezone.now() - rl.derniere_tentative).total_seconds()
    if ecoule > fenetre or rl.tentatives < maximum:
        return 0
    return int(fenetre - ecoule)


def enregistrer_echec(contexte: str, ip: str, fenetre=600) -> None:
    from .models import RateLimit

    rl, cree = RateLimit.objects.get_or_create(
        cle=f"{contexte}:{ip}", defaults={"derniere_tentative": timezone.now()}
    )
    if not cree:
        expire = (timezone.now() - rl.derniere_tentative).total_seconds() > fenetre
        rl.tentatives = 1 if expire else rl.tentatives + 1
        rl.derniere_tentative = timezone.now()
        rl.save()


def reinitialiser_limite(contexte: str, ip: str) -> None:
    from .models import RateLimit

    RateLimit.objects.filter(cle=f"{contexte}:{ip}").delete()


# ─── Fichiers envoyés ─────────────────────────────────────────────────────────
TYPES_IMAGES = {"image/jpeg": "jpg", "image/png": "png", "image/gif": "gif", "image/webp": "webp"}
TYPES_DOCUMENTS = {**TYPES_IMAGES, "application/pdf": "pdf"}


def _mime_reel(fichier) -> str:
    """Détecte le type à partir des premiers octets (signature), pas du nom envoyé."""
    debut = fichier.read(16)
    fichier.seek(0)
    if debut.startswith(b"\xff\xd8\xff"):
        return "image/jpeg"
    if debut.startswith(b"\x89PNG"):
        return "image/png"
    if debut[:6] in (b"GIF87a", b"GIF89a"):
        return "image/gif"
    if debut[:4] == b"RIFF" and debut[8:12] == b"WEBP":
        return "image/webp"
    if debut.startswith(b"%PDF"):
        return "application/pdf"
    # Contenu non reconnu : refusé, quel que soit le nom (un « .pdf » renommé ne passe pas).
    return "application/octet-stream"


def valider_fichier(fichier, types=TYPES_IMAGES, prefixe="fichier"):
    """Vérifie taille + type réel et renomme le fichier de façon sûre."""
    if fichier is None:
        return None
    if fichier.size > settings.TAILLE_MAX_FICHIER:
        raise RegleMetier("Le fichier dépasse la taille maximale autorisée (8 Mo).")
    mime = _mime_reel(fichier)
    ext = types.get(mime)
    if ext is None:
        formats = ", ".join(sorted({v.upper() for v in types.values()}))
        raise RegleMetier(f"Format de fichier non autorisé. Formats acceptés : {formats}.")
    if mime.startswith("image/"):
        from PIL import Image, UnidentifiedImageError

        try:
            with Image.open(fichier) as img:
                img.verify()  # image réellement décodable, pas seulement une signature recopiée
        except (UnidentifiedImageError, OSError, SyntaxError, ValueError):
            raise RegleMetier("Ce fichier n'est pas une image valide.")
        finally:
            fichier.seek(0)
    fichier.name = f"{prefixe}_{timezone.now():%Y%m%d%H%M%S}_{uuid.uuid4().hex[:8]}.{ext}"
    fichier.content_type = mime
    return fichier


def debut_fin_mois(annee: int, mois: int):
    debut = date(annee, mois, 1)
    fin = date(annee, mois, calendar.monthrange(annee, mois)[1])
    return debut, fin


def plage_jours(debut: date, fin: date):
    d = debut
    while d <= fin:
        yield d
        d += timedelta(days=1)


def date_du_mois(annee: int, mois: int, jour: int) -> date:
    """Date du `jour` dans le mois donné, bornée au dernier jour du mois."""
    return date(annee, mois, min(jour, calendar.monthrange(annee, mois)[1]))
