"""
BailManager — configuration Django.

Toutes les valeurs propres à une installation se règlent dans le fichier `.env`
(voir `.env.example`). Le même code sert pour une installation Cloud ou sur un
serveur local (réseau d'agence).
"""
import os
from pathlib import Path

from dotenv import load_dotenv

BASE_DIR = Path(__file__).resolve().parent.parent
load_dotenv(BASE_DIR / ".env")


def env(key, default=None):
    return os.environ.get(key, default)


def env_bool(key, default=False):
    return str(env(key, str(default))).lower() in ("1", "true", "yes", "oui", "on")


APP_VERSION = "2.0.0"

SECRET_KEY = env("SECRET_KEY", "dev-insecure-a-changer-dans-le-fichier-env")
DEBUG = env_bool("DEBUG", False)
if not DEBUG and (SECRET_KEY.startswith("dev-insecure") or SECRET_KEY == "remplacez-moi-par-une-longue-chaine-aleatoire" or len(SECRET_KEY) < 32):
    from django.core.exceptions import ImproperlyConfigured

    raise ImproperlyConfigured("Clé secrète absente ou trop faible : relancez scripts-windows\\1-installer.bat ou renseignez SECRET_KEY dans backend\\.env.")
ALLOWED_HOSTS = [h.strip() for h in env("ALLOWED_HOSTS", "*").split(",") if h.strip()]
CSRF_TRUSTED_ORIGINS = [
    o.strip() for o in env("CSRF_TRUSTED_ORIGINS", "http://localhost:5173,http://127.0.0.1:5173").split(",") if o.strip()
]

INSTALLED_APPS = [
    "django.contrib.contenttypes",
    "django.contrib.auth",
    "django.contrib.sessions",
    "django.contrib.staticfiles",
    "rest_framework",
    "django_filters",
    "apps.core",
    "apps.comptes",
    "apps.patrimoine",
    "apps.locatif",
    "apps.finances",
    "apps.relations",
    "apps.rapports",
    "apps.portail",
    "apps.connecteurs",
]

MIDDLEWARE = [
    "django.middleware.security.SecurityMiddleware",
    "whitenoise.middleware.WhiteNoiseMiddleware",
    "django.contrib.sessions.middleware.SessionMiddleware",
    "django.middleware.common.CommonMiddleware",
    "django.middleware.csrf.CsrfViewMiddleware",
    "django.contrib.auth.middleware.AuthenticationMiddleware",
    "apps.core.licence.LicenceMiddleware",
    "django.middleware.clickjacking.XFrameOptionsMiddleware",
]

ROOT_URLCONF = "config.urls"
WSGI_APPLICATION = "config.wsgi.application"

# Le build React (frontend/dist) est copié dans backend/frontend_build
FRONTEND_BUILD_DIR = BASE_DIR / "frontend_build"

TEMPLATES = [
    {
        "BACKEND": "django.template.backends.django.DjangoTemplates",
        "DIRS": [BASE_DIR / "templates"],
        "APP_DIRS": True,
        "OPTIONS": {"context_processors": ["django.template.context_processors.request"]},
    }
]

# ─── Base de données ──────────────────────────────────────────────────────────
# DB_ENGINE=mysql pour MySQL/MariaDB (WAMP, XAMPP, Laragon...),
# sinon SQLite : aucune installation requise, idéal pour un poste unique.
if env("DB_ENGINE", "sqlite") == "mysql":
    DATABASES = {
        "default": {
            "ENGINE": "django.db.backends.mysql",
            "NAME": env("DB_NAME", "bailmanager"),
            "USER": env("DB_USER", "root"),
            "PASSWORD": env("DB_PASS", ""),
            "HOST": env("DB_HOST", "localhost"),
            "PORT": env("DB_PORT", "3306"),
            "OPTIONS": {"charset": "utf8mb4"},
        }
    }
else:
    DATABASES = {
        "default": {
            "ENGINE": "django.db.backends.sqlite3",
            "NAME": BASE_DIR / env("SQLITE_NAME", "bailmanager.sqlite3"),
        }
    }

# Base de l'ancienne version PHP — lue uniquement par `manage.py importer_ancienne_base`
if env("LEGACY_DB_NAME"):
    DATABASES["legacy"] = {
        "ENGINE": "django.db.backends.mysql",
        "NAME": env("LEGACY_DB_NAME"),
        "USER": env("LEGACY_DB_USER", "root"),
        "PASSWORD": env("LEGACY_DB_PASS", ""),
        "HOST": env("LEGACY_DB_HOST", "localhost"),
        "PORT": env("LEGACY_DB_PORT", "3306"),
        "OPTIONS": {"charset": "utf8mb4"},
    }

AUTH_USER_MODEL = "comptes.Utilisateur"
PASSWORD_HASHERS = [
    "django.contrib.auth.hashers.PBKDF2PasswordHasher",
    "apps.comptes.hashers.PhpBcryptHasher",  # mots de passe importés de la version PHP
]
AUTH_PASSWORD_VALIDATORS = [
    {"NAME": "django.contrib.auth.password_validation.MinimumLengthValidator", "OPTIONS": {"min_length": 8}},
]

# ─── Sessions : expiration par inactivité, réglable par utilisateur ───────────
SESSION_COOKIE_NAME = "bm_session"
SESSION_SAVE_EVERY_REQUEST = True  # délai glissant : chaque requête repousse l'expiration
SESSION_COOKIE_HTTPONLY = True
SESSION_COOKIE_SAMESITE = "Lax"
SESSION_COOKIE_SECURE = env_bool("HTTPS", False)
CSRF_COOKIE_SECURE = env_bool("HTTPS", False)
CSRF_COOKIE_NAME = "bm_csrf"
if env_bool("HTTPS", False):
    SECURE_PROXY_SSL_HEADER = ("HTTP_X_FORWARDED_PROTO", "https")
# Derrière un proxy (Nginx, hébergeur) : son en-tête X-Forwarded-For donne l'adresse du client. Jamais en local.
PROXY_DE_CONFIANCE = env_bool("PROXY_DE_CONFIANCE", False)

LANGUAGE_CODE = "fr-fr"
TIME_ZONE = env("TIME_ZONE", "Africa/Abidjan")
USE_I18N = True
USE_TZ = True

STATIC_URL = "/static/"
STATIC_ROOT = BASE_DIR / "staticfiles"
STATIC_ROOT.mkdir(exist_ok=True)
WHITENOISE_ROOT = FRONTEND_BUILD_DIR if FRONTEND_BUILD_DIR.exists() else None
WHITENOISE_INDEX_FILE = False
WHITENOISE_MIMETYPES = {".webmanifest": "application/manifest+json"}

MEDIA_URL = "/media/"
MEDIA_ROOT = BASE_DIR / env("MEDIA_DIR", "uploads")
SAUVEGARDES_DIR = BASE_DIR / env("BACKUP_DIR", "sauvegardes")
FILE_UPLOAD_MAX_MEMORY_SIZE = 8 * 1024 * 1024
DATA_UPLOAD_MAX_MEMORY_SIZE = 12 * 1024 * 1024
TAILLE_MAX_FICHIER = 8 * 1024 * 1024  # 8 Mo, comme la version PHP

DEFAULT_AUTO_FIELD = "django.db.models.BigAutoField"

REST_FRAMEWORK = {
    "DEFAULT_AUTHENTICATION_CLASSES": ["apps.core.auth.SessionAuthentication"],
    "DEFAULT_PERMISSION_CLASSES": ["rest_framework.permissions.IsAuthenticated"],
    "DEFAULT_FILTER_BACKENDS": [
        "django_filters.rest_framework.DjangoFilterBackend",
        "rest_framework.filters.SearchFilter",
        "rest_framework.filters.OrderingFilter",
    ],
    "DEFAULT_PAGINATION_CLASS": "apps.core.pagination.Pagination",
    "PAGE_SIZE": 20,
    "EXCEPTION_HANDLER": "apps.core.exceptions.gestionnaire_exceptions",
    "COERCE_DECIMAL_TO_STRING": False,
}

# ─── E-mail (quittances, rappels, mot de passe oublié) ────────────────────────
if env("MAIL_HOST"):
    EMAIL_BACKEND = "django.core.mail.backends.smtp.EmailBackend"
    EMAIL_HOST = env("MAIL_HOST")
    EMAIL_PORT = int(env("MAIL_PORT", "587"))
    EMAIL_HOST_USER = env("MAIL_USER", "")
    EMAIL_HOST_PASSWORD = env("MAIL_PASS", "")
    EMAIL_USE_TLS = env_bool("MAIL_TLS", True)
else:
    EMAIL_BACKEND = "django.core.mail.backends.console.EmailBackend"
DEFAULT_FROM_EMAIL = env("MAIL_FROM", "BailManager <no-reply@bailmanager.local>")
URL_APPLICATION = env("APP_URL", "http://localhost:8000")

LOGGING = {
    "version": 1,
    "disable_existing_loggers": False,
    "handlers": {"console": {"class": "logging.StreamHandler"}},
    "root": {"handlers": ["console"], "level": "WARNING"},
}
