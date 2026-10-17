"""Licence d'utilisation, propre à chaque agence (installation locale).

Une licence est un texte « données.signature » : les données (agence, échéance, limites, modules) sont signées
avec la clé privée de l'éditeur (Ed25519). Le logiciel ne contient que la clé publique : il peut vérifier une
licence, jamais en fabriquer une. Sans licence, une période d'essai de 30 jours donne accès à tout.

À l'échéance : alerte 30 jours avant, puis 15 jours de grâce, puis **lecture seule** (tout reste consultable,
imprimable et sauvegardable, mais plus rien ne s'enregistre) jusqu'à l'installation d'une nouvelle licence.
"""
import base64
import json
import re
from datetime import date, timedelta

from django.http import JsonResponse

CLE_PUBLIQUE = "fXRE4L49DHBSMi9eTXQ4xnAiJ24D+18sedotiX5V02Y="
DUREE_ESSAI = 30
ALERTE_JOURS = 30
GRACE_JOURS = 15

MODULES = {
    "connecteurs": "SMS, WhatsApp, paiement Mobile Money et signature par code SMS",
    "espace_bailleur": "Espace propriétaire en ligne",
    "rapprochement": "Rapprochement des relevés Mobile Money et bancaires",
    "compteurs": "Compteurs partagés (eau, électricité)",
}

# Chemins de l'API réservés à un module (expression régulière → module)
CHEMINS_MODULES = [
    (r"^/api/connecteurs/(tester|envois|paiements)/", "connecteurs"),
    (r"^/api/portail/(paiements|signature)/", "connecteurs"),
    (r"^/api/contrats/\d+/signature/", "connecteurs"),
    (r"^/api/portail-bailleur/", "espace_bailleur"),
    (r"^/api/bailleurs/\d+/generer-code/", "espace_bailleur"),
    (r"^/api/rapprochement/", "rapprochement"),
    (r"^/api/compteurs/", "compteurs"),
]

# En lecture seule, ces écritures restent permises : connexion, saisie de licence, sauvegardes, exports,
# et ce que font les visiteurs, locataires et propriétaires sur le site public.
ECRITURES_PERMISES = [r"^/api/auth/", r"^/api/licence/", r"^/api/sauvegarde/", r"^/api/connecteurs/sauvegarde/",
                      r"^/api/export/", r"^/api/public/", r"^/api/portail/(connexion|deconnexion)/", r"^/api/portail-bailleur/(connexion|deconnexion)/"]


def _b64(texte):
    return base64.urlsafe_b64decode(texte + "=" * (-len(texte) % 4))


def lire(texte):
    """Données d'une licence dont la signature est valide ; lève ValueError sinon."""
    from cryptography.exceptions import InvalidSignature
    from cryptography.hazmat.primitives.asymmetric.ed25519 import Ed25519PublicKey

    texte = re.sub(r"\s", "", texte or "")
    if texte.count(".") != 1:
        raise ValueError("Ce texte n'est pas une licence BailManager.")
    donnees, signature = texte.split(".")
    try:
        Ed25519PublicKey.from_public_bytes(base64.b64decode(CLE_PUBLIQUE)).verify(_b64(signature), donnees.encode())
    except (InvalidSignature, ValueError):
        raise ValueError("Licence invalide : la signature ne correspond pas (licence modifiée ou recopiée incomplètement).")
    d = json.loads(_b64(donnees))
    d["expire_le"] = date.fromisoformat(d["expire_le"])
    return d


def signer(donnees: dict, cle_privee_pem: bytes) -> str:
    """Outil de l'éditeur : fabrique le texte de licence (nécessite la clé privée, absente des installations)."""
    from cryptography.hazmat.primitives import serialization

    cle = serialization.load_pem_private_key(cle_privee_pem, None)
    corps = base64.urlsafe_b64encode(json.dumps(donnees, ensure_ascii=False, separators=(",", ":")).encode()).decode().rstrip("=")
    sig = base64.urlsafe_b64encode(cle.sign(corps.encode())).decode().rstrip("=")
    return f"{corps}.{sig}"


def etat(jour=None):
    """État de la licence de cette installation (période d'essai, valide, alerte, grâce ou expirée)."""
    from .models import Licence
    from .utils import aujourdhui

    jour = jour or aujourdhui()
    lic = Licence.charger()
    donnees, erreur = None, ""
    if lic.texte:
        try:
            donnees = lire(lic.texte)
        except ValueError as e:
            erreur = str(e)
    if donnees:
        fin, essai = donnees["expire_le"], False
        res = {"agence": donnees.get("agence", ""), "numero": donnees.get("numero", ""), "utilisateurs": donnees.get("utilisateurs"),
               "biens": donnees.get("biens"), "modules": [m for m in donnees.get("modules", []) if m in MODULES]}
    else:
        fin, essai = lic.debut_essai + timedelta(days=DUREE_ESSAI), True
        res = {"agence": "", "numero": "", "utilisateurs": None, "biens": None, "modules": list(MODULES)}
    jours = (fin - jour).days
    if jours > (7 if essai else ALERTE_JOURS):
        statut = "essai" if essai else "valide"
    elif jours >= 0:
        statut = "alerte"
    elif jours >= -GRACE_JOURS:
        statut = "grace"
    else:
        statut = "expiree"
    res.update({
        "essai": essai, "statut": statut, "expire_le": fin, "jours": jours, "lecture_seule": statut == "expiree",
        "fin_grace": fin + timedelta(days=GRACE_JOURS), "erreur": erreur, "installee_le": lic.installee_le,
        "modules_libelles": {k: v for k, v in MODULES.items()},
    })
    return res


def module_actif(nom):
    return nom in etat()["modules"]


def verifier_limite(type_limite, actuel):
    """Refuse l'ajout d'un utilisateur ou d'un bien au-delà de la licence."""
    from .exceptions import RegleMetier

    e = etat()
    maxi = e.get(type_limite)
    if maxi is not None and actuel >= maxi:
        quoi = "utilisateurs actifs" if type_limite == "utilisateurs" else "biens"
        raise RegleMetier(f"Votre licence est limitée à {maxi} {quoi}. Contactez votre fournisseur pour l'étendre.", code="licence_limite")


class LicenceMiddleware:
    """Lecture seule après expiration (au-delà de la grâce) et modules non inclus dans la licence."""

    def __init__(self, get_response):
        self.get_response = get_response

    def __call__(self, request):
        chemin = request.path
        if chemin.startswith("/api/"):
            e = None
            for motif, module in CHEMINS_MODULES:
                if re.match(motif, chemin):
                    e = e or etat()
                    if module not in e["modules"]:
                        return JsonResponse({"detail": f"Module non inclus dans votre licence : {MODULES[module]}.", "code": "licence_module"}, status=403)
            if request.method not in ("GET", "HEAD", "OPTIONS") and not any(re.match(m, chemin) for m in ECRITURES_PERMISES):
                e = e or etat()
                if e["lecture_seule"]:
                    return JsonResponse({
                        "detail": f"Licence expirée depuis le {e['expire_le']:%d/%m/%Y} : BailManager est en lecture seule. "
                                  "Vous pouvez tout consulter, imprimer et sauvegarder ; installez une nouvelle licence (Paramètres > Licence) pour enregistrer.",
                        "code": "licence_expiree",
                    }, status=403)
        return self.get_response(request)
