# BailManager 2

Logiciel de gestion locative, financière et comptable immobilière — refonte de la version 1 (PHP) en **Django** (serveur, API) et **React** (interface).

La structure et la logique de la version 1 sont conservées : patrimoine sous mandat de gestion, baux, encaissements avec répartition bailleur / commission, comptes courants bailleurs, caisse agence, cautions, rapports, site vitrine, espace locataire et journal d'audit.

S'y ajoutent : états des lieux d'entrée et de sortie, préavis, recouvrement gradué et plans d'apurement, demandes d'intervention, espace propriétaire, connecteurs SMS / WhatsApp / Mobile Money / signature par code SMS (avec mode test), récapitulatif fiscal annuel, partage des annonces, application installable (PWA) et sauvegarde chiffrée hors du serveur.

## Démarrage rapide (Windows)

1. Installer **Python 3.11 ou plus** (python.org, cocher « Add python.exe to PATH »).
2. Double-cliquer `scripts-windows\1-installer.bat` puis créer le compte administrateur.
3. Double-cliquer `scripts-windows\2-demarrer.bat`, puis ouvrir **http://localhost:8000**.

L'interface est déjà compilée (`backend/frontend_build`) : Node.js n'est pas nécessaire chez le client. Les détails (MySQL/WAMP, réseau local, Cloud, tâches planifiées) sont dans [docs/INSTALLATION.md](docs/INSTALLATION.md).

## Organisation

```
backend/                 Django
  config/                réglages, routes
  apps/core              paramètres agence, clauses, journal, anti brute-force, sauvegardes
  apps/comptes           utilisateurs, connexion, profil
  apps/patrimoine        bailleurs, mandats, maisons, locataires, documents
  apps/locatif           contrats, encaissements, charges, révisions, cautions  ← services.py = règles de gestion
  apps/finances          compte courant bailleurs, caisse agence
  apps/relations         réservations, messages du site, messagerie locataires
  apps/rapports          tableau de bord, rapports, calendrier, recherche, export Excel
  apps/portail           site public, espace locataire et espace bailleur
  apps/connecteurs       SMS, WhatsApp, paiement Mobile Money, signature par code SMS (mode test inclus)
  frontend_build/        interface compilée (servie par Django)
frontend/                sources React (Vite)
scripts-windows/         installation, démarrage, tâches planifiées
docs/                    installation, migration depuis la v1, changements
```

## Développement

```bash
cd backend && python -m venv .venv && . .venv/bin/activate      # Windows : .venv\Scripts\activate
pip install -r requirements.txt && cp .env.example .env          # DEBUG=1 en développement
python manage.py migrate && python manage.py initialiser && python manage.py runserver
cd ../frontend && npm install && npm run dev                     # http://localhost:5173 (relaie /api vers Django)
npm run build                                                    # recompile l'interface dans backend/frontend_build
python manage.py test apps                                       # tests des règles de gestion
```

## Commandes utiles

| Commande | Rôle |
|---|---|
| `python manage.py initialiser` | Paramètres, clauses types et compte administrateur |
| `python manage.py donnees_demo` | Données fictives sur une base vide (démonstration) |
| `python manage.py photos_demo` | Photos de démonstration pour les maisons qui n'en ont pas (appelée par `donnees_demo`) |
| `python manage.py importer_ancienne_base --uploads <dossier>` | Import de la base MySQL de la version PHP |
| `python manage.py envoyer_rappels` | Rappels de loyer, information des locataires 30 j avant la fin d'un mandat non reconduit, synthèse quotidienne (ex-`cron_rappels.php`) |
| `python manage.py sauvegarder` | Archive ZIP données + fichiers dans `sauvegardes/`, puis copie chiffrée vers la destination réglée (Paramètres > Sauvegarde) |
| `python manage.py dechiffrer_sauvegarde <fichier.zip.chiffre>` | Rend lisible une copie chiffrée (la phrase de chiffrement est demandée) |
