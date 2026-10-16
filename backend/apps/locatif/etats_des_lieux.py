"""État des lieux d'entrée (et de sortie) : modèle de pièces selon le type de bien, contrôle de la saisie.

Structure enregistrée (champ « pieces ») :
    [{"nom": "Salon", "elements": [{"nom": "Murs", "etat": "bon", "observation": ""}, ...]}, ...]
"""
import re

from apps.core.exceptions import RegleMetier

ETATS = {"neuf": "Neuf", "bon": "Bon état", "usage": "Usagé", "mauvais": "Mauvais état"}

ELEMENTS_PIECE = ["Sol", "Murs", "Plafond", "Portes", "Fenêtres et volets", "Électricité (prises, interrupteurs, éclairage)"]
ELEMENTS_EAU = ELEMENTS_PIECE + ["Plomberie et robinetterie", "Évacuations"]
ELEMENTS_EXTERIEUR = ["Clôture et portail", "Cour", "Toiture apparente", "Éclairage extérieur"]


def _piece(nom, elements=None):
    return {"nom": nom, "elements": [{"nom": e, "etat": "", "observation": ""} for e in (elements or ELEMENTS_PIECE)]}


def pieces_par_defaut(type_maison: str) -> list[dict]:
    """Pièces proposées selon le type du bien ; l'agence peut ensuite en ajouter ou en retirer."""
    t = (type_maison or "").lower()
    chiffre = re.search(r"(\d+)", t)
    if "studio" in t:
        pieces = [_piece("Pièce principale"), _piece("Coin cuisine", ELEMENTS_EAU), _piece("Salle d'eau", ELEMENTS_EAU)]
    elif "chambre" in t and "salon" in t:
        pieces = [_piece("Salon"), _piece("Chambre"), _piece("Cuisine", ELEMENTS_EAU), _piece("Salle d'eau", ELEMENTS_EAU)]
    elif "villa" in t or "duplex" in t:
        nb = int(chiffre.group(1)) if chiffre else 4
        pieces = [_piece("Salon")] + [_piece(f"Chambre {i}") for i in range(1, max(nb - 1, 1) + 1)]
        pieces += [_piece("Cuisine", ELEMENTS_EAU), _piece("Salle d'eau 1", ELEMENTS_EAU), _piece("Salle d'eau 2", ELEMENTS_EAU)]
        pieces += [_piece("Extérieurs", ELEMENTS_EXTERIEUR)]
    elif chiffre:
        nb = int(chiffre.group(1))
        pieces = [_piece("Salon")] + [_piece(f"Chambre {i}" if nb > 2 else "Chambre") for i in range(1, max(nb - 1, 1) + 1)]
        pieces += [_piece("Cuisine", ELEMENTS_EAU), _piece("Salle d'eau", ELEMENTS_EAU)]
    else:
        pieces = [_piece("Pièce principale"), _piece("Cuisine", ELEMENTS_EAU), _piece("Salle d'eau", ELEMENTS_EAU)]
    return pieces


def nettoyer_pieces(pieces) -> list[dict]:
    """Valide et normalise la saisie envoyée par l'interface."""
    if not isinstance(pieces, list) or not pieces:
        raise RegleMetier("Indiquez au moins une pièce.")
    propres = []
    for p in pieces:
        nom = str((p or {}).get("nom", "")).strip()[:80]
        if not nom:
            raise RegleMetier("Chaque pièce doit avoir un nom.")
        elements = []
        for e in p.get("elements") or []:
            nom_e = str((e or {}).get("nom", "")).strip()[:120]
            if not nom_e:
                continue
            etat = str(e.get("etat") or "")
            if etat and etat not in ETATS:
                raise RegleMetier(f"État inconnu pour « {nom_e} » ({nom}).")
            elements.append({"nom": nom_e, "etat": etat, "observation": str(e.get("observation") or "").strip()[:500]})
        propres.append({"nom": nom, "elements": elements})
    return propres
