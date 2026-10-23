"""Détection des doublons à la saisie d'un locataire ou d'un bailleur.

Indices forts (même personne presque à coup sûr) : même téléphone, même n° de pièce d'identité, même e-mail.
Indice faible : nom identique ou très proche (ordre des mots, accents, majuscules et petites fautes ignorés).
Un indice fort demande une confirmation avant d'enregistrer ; un nom proche est seulement signalé.
"""
import re
import unicodedata
from difflib import SequenceMatcher

from apps.core.exceptions import RegleMetier


def _sans_accents(t):
    return "".join(c for c in unicodedata.normalize("NFD", str(t or "")) if unicodedata.category(c) != "Mn").lower()


def tel(t):
    """Les 8 derniers chiffres : « +225 07 07 07 07 07 » et « 0707070707 » se comparent à l'identique."""
    d = re.sub(r"\D", "", t or "")
    return d[-8:] if len(d) >= 8 else ""


def piece(p):
    return re.sub(r"[^A-Z0-9]", "", _sans_accents(p).upper())


def mots(nom):
    return sorted(m for m in re.split(r"[^a-z0-9]+", _sans_accents(nom)) if len(m) >= 2)


def noms_proches(a, b):
    ma, mb = mots(a), mots(b)
    if not ma or not mb:
        return False
    if ma == mb:  # mêmes mots, dans n'importe quel ordre
        return True
    if len(ma) >= 2 and len(mb) >= 2 and (set(ma) <= set(mb) or set(mb) <= set(ma)):  # « Koné Ibrahim » / « Koné Ibrahim Junior »
        return True
    return SequenceMatcher(None, " ".join(ma), " ".join(mb)).ratio() >= 0.9  # petite faute de frappe


def chercher(modele, donnees, champ_piece, exclure=None, lien=""):
    """Fiches existantes qui ressemblent aux données saisies, les plus sûres en premier."""
    tels = {tel(donnees.get("telephone1")), tel(donnees.get("telephone2"))} - {""}
    p = piece(donnees.get(champ_piece))
    email = (donnees.get("email") or "").strip().lower()
    nom = donnees.get("nom") or ""
    if not (tels or len(p) >= 4 or email or len(mots(nom)) >= 1):
        return []
    res = []
    qs = modele.objects.all()
    if exclure:
        qs = qs.exclude(pk=exclure)
    for o in qs.only("id", "nom", "telephone1", "telephone2", "email", champ_piece):
        raisons, fort = [], False
        if tels & ({tel(o.telephone1), tel(o.telephone2)} - {""}):
            raisons.append("même téléphone")
            fort = True
        if len(p) >= 4 and piece(getattr(o, champ_piece)) == p:
            raisons.append("même pièce d'identité")
            fort = True
        if email and (o.email or "").strip().lower() == email:
            raisons.append("même e-mail")
            fort = True
        if nom and noms_proches(nom, o.nom):
            raisons.append("nom identique" if mots(nom) == mots(o.nom) else "nom proche")
        if raisons:
            res.append({"id": o.pk, "nom": o.nom, "telephone": o.telephone1, "raisons": raisons, "fort": fort, "lien": f"{lien}{o.pk}"})
    return sorted(res, key=lambda x: (not x["fort"], -len(x["raisons"])))[:6]


def exiger_confirmation(modele, donnees, champ_piece, exclure, confirme, libelle, lien):
    """À l'enregistrement : un doublon probable (indice fort) bloque tant que l'utilisateur n'a pas confirmé."""
    if confirme:
        return
    forts = [d for d in chercher(modele, donnees, champ_piece, exclure, lien) if d["fort"]]
    if forts:
        d = forts[0]
        raise RegleMetier(
            f"Doublon possible : {libelle} « {d['nom']} » existe déjà ({', '.join(d['raisons'])}). "
            "Vérifiez qu'il ne s'agit pas de la même personne avant de créer une nouvelle fiche.",
            code="doublon_possible",
        )
