# Rapport de projet — BailManager 2

Logiciel de gestion locative, financière et comptable pour agence immobilière.
Refonte complète de la version 1 (PHP) en **Django** (serveur) et **React** (interface).

- Dépôt : https://github.com/Josselin225/bail_manager
- Date du rapport : 16 octobre 2026

---

## 1. Contexte et objectifs

L'agence gère des maisons pour le compte de propriétaires (les **bailleurs**). Un bailleur confie ses biens à l'agence par un **mandat de gestion**. L'agence trouve les locataires et signe avec eux un **contrat de bail** pour le compte du bailleur. Elle encaisse ensuite les loyers, prélève sa commission et reverse le net au bailleur.

La version 1, écrite en PHP, remplissait ces fonctions. La refonte avait quatre objectifs :

1. **Conserver la logique métier** de la version 1 : patrimoine sous mandat, baux, encaissements avec répartition bailleur / commission, comptes courants, caisse, cautions, rapports, site vitrine, espace locataire, journal d'audit.
2. **Moderniser l'interface** : plus claire, plus rapide, utilisable sur ordinateur comme sur téléphone.
3. **Fiabiliser les règles de gestion** : les placer côté serveur et les couvrir par des tests automatiques.
4. **Simplifier l'installation chez le client** : un seul serveur à lancer, sans Node.js ni Apache.

## 2. Architecture technique

| Couche | Technologies |
|---|---|
| Serveur / API | Python 3.11+, Django 5.1, Django REST Framework, django-filter |
| Base de données | SQLite (par défaut) ou MySQL / MariaDB (WAMP, XAMPP, Laragon, serveur cloud) |
| Interface | React 18, React Router, TanStack Query, Recharts (graphiques), Lucide (icônes) |
| Outillage interface | Vite 5 (développement et compilation) |
| Documents | Pages A4 générées dans le navigateur, enregistrables en PDF |
| Exports | Excel (openpyxl) |
| Mise en service | Waitress (serveur WSGI) + WhiteNoise (fichiers statiques) |

**Organisation du code**

```
backend/                 Django
  config/                réglages, routes de l'API
  apps/core              paramètres agence, clauses, journal, anti brute-force, sauvegardes
  apps/comptes           utilisateurs, connexion, profil
  apps/patrimoine        bailleurs, mandats, maisons et photos, locataires, documents
  apps/locatif           contrats, encaissements, charges, révisions, cautions (règles dans services.py)
  apps/finances          comptes courants bailleurs, caisse agence
  apps/relations         réservations de visite, messages du site, messagerie locataires
  apps/rapports          tableau de bord, rapports, calendrier, recherche, exports
  apps/portail           site public et espace locataire
  frontend_build/        interface compilée, servie par Django
frontend/                sources React (47 pages)
scripts-windows/         installation, démarrage, tâches planifiées
docs/                    installation, migration depuis la v1, changements
```

Le volume de code est d'environ 5 100 lignes Python côté serveur, hors migrations, et 7 600 lignes côté interface.

**Principe de fonctionnement.** L'interface React dialogue avec l'API Django. En production, `npm run build` compile l'interface dans `backend/frontend_build`, et Django la sert directement. Un seul serveur suffit alors (port 8000). En développement, Vite (port 5173) affiche immédiatement chaque modification du code.

## 3. Fonctionnalités

### 3.1 Patrimoine
- **Bailleurs** : fiche, code automatique, photo, compte courant, historique des mandats.
- **Mandat de gestion** :
  - durée **déterminée** ou **indéterminée** ;
  - tacite reconduction possible ;
  - taux de commission propre ;
  - scan du mandat signé ;
  - référence officielle (MG-0001…).
- **Maisons** :
  - désignation, type, adresse, description ;
  - loyer et condition d'entrée ;
  - **photos illimitées**, avec photo principale ;
  - publication sur le site vitrine.
- **Locataires** : identité, coordonnées, pièces d'identité, documents scannés, accès à l'espace locataire.

### 3.2 Gestion locative
- **Contrat de bail** :
  - création guidée ;
  - loyer repris du bien ;
  - calcul automatique de la caution (2 mois), de l'avance (2 mois maximum) et des frais d'agence ;
  - reçu de signature.
- **Rattachement au mandat** : chaque bail garde le mandat sous lequel l'agence l'a signé.
- **Ancienneté du bail** : durée depuis la signature, figée à la résiliation.
- **Encaissements** :
  - quittance numérotée, avec QR code de vérification ;
  - paiement partiel possible ;
  - contrôle des impayés antérieurs ;
  - répartition automatique entre commission (caisse agence) et net bailleur (compte courant).
- **État des lieux d'entrée** (et de sortie) :
  - saisie pièce par pièce, avec des pièces proposées selon le type de bien ;
  - état de chaque élément (neuf, bon, usagé, mauvais) et observations ;
  - relevés des compteurs et nombre de clés ;
  - document annexé au bail, imprimable rempli ou en grille vierge à compléter sur place ;
  - proposé juste après l'impression du contrat.
- **Révisions de loyer** : à la demande du bailleur, révision triennale ou accord entre les parties. Les mois passés gardent l'ancien montant.
- **Charges locatives**, **cautions** (retenues, restitution) et **résiliation** avec solde de tout compte.

### 3.3 Finances
- **Comptes courants des bailleurs**, versements et relevés de compte imprimables.
- **Caisse de l'agence** : commissions, frais d'agence, entrées et sorties.

### 3.4 Pilotage
- **Tableau de bord** en graphiques :
  - recouvrement sur 12 mois ;
  - impayés par ancienneté ;
  - encaissements comparés à l'année précédente ;
  - occupation par type de bien ;
  - commissions ;
  - modes de paiement ;
  - maisons les plus rentables.
- **Lecture des graphiques** : chacun propose une vue tableau et une infobulle détaillée.
- **Rapports imprimables** : journalier, mensuel, annuel, sur une période, état d'occupation, impayés. Tous exportables vers Excel.
- **Calendrier en trois vues** (jour, mois, année) : échéances de loyer, visites, fins de bail et de mandat.
- **Journal d'activité** : traçabilité de toutes les opérations.

### 3.5 Relations
- **Site vitrine public** :
  - accueil avec diaporama ;
  - catalogue avec filtres et étiquettes **Disponible / Occupée** ;
  - fiche de bien avec galerie plein écran ;
  - réservation de visite ;
  - formulaire de contact.
- **Espace locataire** :
  - paiements et quittances téléchargeables ;
  - charges ;
  - messagerie avec l'agence ;
  - information sur le mandat de gestion du logement.
- **Rappels automatiques** (tâche quotidienne) :
  - retards de paiement et échéances proches ;
  - synthèse envoyée à l'agence ;
  - information des locataires 30 jours avant la fin d'un mandat non reconduit, puis confirmation en cas de renouvellement.

## 4. Règles de gestion appliquées

Toutes ces règles sont vérifiées **par le serveur**. L'interface ne peut donc pas les contourner.

| Règle | Effet |
|---|---|
| Pas de bien sans mandat de gestion en vigueur | Création ou rattachement d'une maison refusés ; bailleur grisé dans les listes |
| Pas de bail sans mandat de gestion en vigueur | Création du contrat refusée |
| Le loyer est fixé sur le bien | Le contrat reprend ce loyer ; aucun autre montant n'est accepté |
| Loyer d'un bien loué | Modifiable uniquement par une révision sur le contrat en cours ; le bien est mis à jour en même temps |
| Avance de loyer limitée à 2 mois | Conforme à la loi n° 2019-576 |
| Mandat à durée déterminée | Date de fin obligatoire ; information du locataire dans le bail, dans son espace et par message |
| Mandat échu | Baux signalés « mandat échu » ; encaissement possible avec avertissement |
| Maison occupée | Toute maison ayant un contrat actif est « Occupée » sur le site ; aucune visite n'y est réservable |

Les contrats de bail mentionnent l'agence mandataire (avec son représentant), le propriétaire, la référence et la durée du mandat. Ils précisent que la fin du mandat n'affecte pas le bail.

## 5. Ergonomie et charte graphique

- **Charte unique** : bleu marine, rouge « signal » réservé à l'essentiel, police Public Sans, cartes arrondies.
- **Où elle s'applique** : site public, espaces de connexion, interface de gestion et documents PDF.
- **Mobile** : toutes les pages sont adaptées. Les tableaux deviennent des cartes, et les menus des tiroirs et un menu burger.
- **Mode sombre** complet.
- **Navigation** :
  - menu latéral en tiroirs, avec compteurs ;
  - recherche globale (Ctrl K) ;
  - en-têtes de page fixes au défilement ;
  - un même loader à l'image de la marque.
- **Formulaires harmonisés** :
  - mêmes libellés, mêmes aides et sections titrées ;
  - champs obligatoires contrôlés avant envoi ;
  - focus automatique sur le premier champ.
- **Documents PDF** : quittance, contrat de bail, état des lieux, reçus, mandat, relevés et rapports. Ils ont tous :
  - un en-tête à la marque et une référence encadrée ;
  - des tableaux aérés et des chiffres clés en tuiles ;
  - des blocs de signature ;
  - pour la quittance, deux exemplaires sur une seule page A4.
- **Accessibilité** :
  - navigation au clavier ;
  - contrastes vérifiés ;
  - couleurs des graphiques validées pour les personnes daltoniennes ;
  - respect de la préférence « réduire les animations ».

## 6. Sécurité

- **Mots de passe** : stockés chiffrés. Les anciens mots de passe de la version 1 restent compatibles grâce à un hachage bcrypt.
- **Connexion** :
  - protection contre les essais répétés (blocage temporaire après 5 échecs) ;
  - déconnexion après inactivité, réglable par utilisateur ;
  - protection CSRF.
- **Droits** : l'administrateur seul peut supprimer, résilier un mandat, réviser un loyer, gérer les utilisateurs et les paramètres.
- **Fichiers téléversés** : contrôle du type réel. Les photos de maisons passent en plus par une vérification d'image ; la taille est limitée à 8 Mo.
- **Configuration** : les éléments sensibles (clé secrète, base, fichiers clients) sont exclus du dépôt Git.

## 7. Qualité

- **22 tests automatiques** sur les règles de gestion. Ils couvrent notamment :
  - mandat obligatoire ;
  - calcul de l'avance et de la caution ;
  - impayés et paiements partiels ;
  - résiliation et retenues ;
  - droits des agents ;
  - photos ;
  - étiquettes Disponible / Occupée ;
  - durée du mandat et information des locataires ;
  - loyer fixé par le bien et révision ;
  - ancienneté des baux ;
  - état des lieux d'entrée.
- **Contrôle visuel** des 30 pages principales, en bureau et sur mobile, après chaque évolution : aucun débordement, aucune erreur JavaScript.
- **Commande** : `python manage.py test apps`.

## 8. Installation et exploitation

- **Installation Windows** : `scripts-windows\1-installer.bat`, puis `2-demarrer.bat`. Le logiciel est alors accessible sur http://localhost:8000. Détails dans `docs/INSTALLATION.md`.
- **Reprise des données de la version 1** : `python manage.py importer_ancienne_base`, décrite dans `docs/MIGRATION.md`.
- **Démonstration** : `python manage.py donnees_demo` (données fictives et photos libres de droits).
- **Tâches planifiées** :
  - `envoyer_rappels`, chaque jour ;
  - `sauvegarder`, une archive ZIP des données et des fichiers.

## 9. Limites connues et perspectives

- **État des lieux de sortie** : il se saisit comme celui d'entrée. Il reste à le comparer automatiquement à l'entrée pour proposer les retenues sur caution.
- **Documents joints** : le contrôle de type se fie encore à l'extension quand le contenu n'est pas reconnu. C'est une correction d'une ligne, à faire.
- **Biens d'un bailleur au mandat expiré** : décider s'ils doivent être retirés automatiquement du site vitrine.
- **Clauses** : elles s'impriment dans leur version actuelle. Il faudrait conserver la version en vigueur à la signature de chaque bail ou mandat.
- **Biens confiés** : la liste imprimée sur un mandat est celle d'aujourd'hui, et non celle de sa date de signature.
- **Photos de démonstration** : à remplacer par les vraies photos des biens avant toute présentation à un client.
- **Textes destinés aux locataires** (information sur le mandat, messages) : à faire valider par un conseil juridique.
