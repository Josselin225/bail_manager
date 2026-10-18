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
- **État des lieux d'entrée** :
  - saisie pièce par pièce, avec des pièces proposées selon le type de bien ;
  - état de chaque élément (neuf, bon, usagé, mauvais) et observations ;
  - photos par pièce ;
  - relevés des compteurs et nombre de clés ;
  - document annexé au bail, imprimable rempli ou en grille vierge à compléter sur place ;
  - proposé juste après l'impression du contrat ;
  - **sur tablette** : grandes cases tactiles, puis signature au doigt du locataire et de l'agent ; les signatures s'impriment sur le document et sont annulées si le contenu est modifié ensuite.
- **Sortie du locataire** :
  - préavis de départ (3 mois), donné par l'agence ou par le locataire depuis son espace ; date de sortie calculée et signalée au tableau de bord et au calendrier ;
  - état des lieux de sortie pré-rempli à partir de l'entrée, avec la colonne « Entrée » en regard ;
  - dégradations détectées automatiquement, avec proposition de retenues sur la caution à la résiliation.
- **Recouvrement des impayés** :
  - page dédiée : dette, ancienneté, dernière relance et plan en cours de chaque locataire en retard ;
  - relances graduées : rappel amiable (délai 8 jours), relance (8 jours), mise en demeure (15 jours) ; chaque niveau produit sa lettre imprimable ;
  - plan d'apurement : dette répartie en 2 à 24 mensualités, accord à signer par le locataire, suivi de chaque échéance (honorée, en retard, à venir).
- **Révisions de loyer** : à la demande du bailleur, révision triennale ou accord entre les parties. Les mois passés gardent l'ancien montant.
  - les baux dont le loyer n'a pas bougé depuis trois ans sont repérés (onglet « À réviser », alerte du tableau de bord, calendrier, synthèse quotidienne) ;
  - la révision ouvre une lettre au locataire prête à imprimer, avec contrôle du préavis de trois mois (art. 423-424).
- **Compteurs partagés** (cour commune, une facture SODECI ou CIE pour plusieurs logements) :
  - répartition au prorata des sous-compteurs (index repris de la facture précédente ou de l'état des lieux d'entrée) ou à parts égales entre logements occupés ;
  - la part de chaque logement occupé devient une charge sur son bail ; celle d'un logement vide est débitée du compte du propriétaire ;
  - historique des factures et de leur répartition.
- **Charges locatives**, **cautions** (retenues, restitution) et **résiliation** avec solde de tout compte.

- **Demandes d'intervention** (pannes, réparations) :
  - saisies par l'agence ou par le locataire depuis son espace, avec photos et indicateur d'urgence ;
  - suivi : nouvelle, en cours, terminée, avec l'artisan et le coût ;
  - à la clôture, le coût est imputé au bailleur (compte courant), au locataire (charge locative) ou à l'agence (caisse).

### 3.3 Connecteurs (SMS, WhatsApp, Mobile Money, signature)
Ces services sont **prêts à brancher** : l'agence choisit son fournisseur et saisit ses identifiants dans Paramètres > Connecteurs. En **mode test**, rien ne sort du logiciel : chaque message est consigné dans le journal des envois et chaque paiement se valide sur une page de simulation.
- **SMS** (Orange SMS API ou Twilio) et **WhatsApp** (API Cloud de Meta), avec un canal privilégié et un message d'essai.
- **Messages automatiques**, activables un par un :
  - relances d'impayés ;
  - confirmation de chaque paiement (montant, mois, numéro de quittance) ;
  - rappels d'échéance et de retard (au plus un par jour et par locataire).
- **Paiement Mobile Money** (Wave, Orange Money, MTN MoMo, Moov Money) via CinetPay ou PayDunya :
  - le locataire paie depuis son espace le plus ancien mois dû ;
  - le statut est toujours revérifié auprès de l'agrégateur, jamais sur la seule notification reçue ;
  - à la confirmation, le loyer est encaissé et la quittance émise automatiquement, sans doublon possible.
- **Signature électronique du bail** par code SMS à 6 chiffres :
  - valable 15 minutes, 5 essais au plus ;
  - saisi par le locataire dans son espace ou par l'agent ;
  - date, heure, numéro et adresse IP conservés ; mention imprimée sur le contrat.
- **Journaux** : journal des envois (simulés, envoyés, en échec avec la raison) et liste des paiements en ligne.

### 3.4 Finances
- **Comptes courants des bailleurs**, versements et relevés de compte imprimables.
- **Caisse de l'agence** : commissions, frais d'agence, entrées et sorties.
- **Rapprochement des relevés** Mobile Money (Wave, Orange Money, MTN, Moov) et bancaires :
  - import d'un fichier CSV ou Excel, colonnes reconnues automatiquement (modifiables) ;
  - chaque paiement reçu est rapproché d'un bail par le téléphone du locataire, le montant dû et son nom dans le libellé ;
  - encaissement de la sélection en un clic, réparti du plus ancien mois dû au plus récent ;
  - un paiement déjà encaissé est reconnu à chaque nouvel import : jamais de double encaissement.

### 3.5 Pilotage
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
- **Activité de l'équipe** : pour chaque membre de l'agence, sur la période choisie, montants encaissés, baux signés, relances, visites faites (et visiteurs intéressés), états des lieux. Un agent ne voit que sa propre activité.

### 3.6 Relations
- **Site vitrine public** :
  - accueil avec diaporama ;
  - catalogue avec filtres et étiquettes **Disponible / Occupée** ;
  - fiche de bien avec galerie plein écran ;
  - réservation de visite ;
  - formulaire de contact.
- **Agenda des visites** : semaine par semaine, visites du site et visites prises au téléphone ou à l'agence, avec heure, agent attribué (« Je m'en charge », « Mes visites ») et compte rendu (visiteur intéressé ou non).
- **Espace locataire** :
  - paiements et quittances téléchargeables ;
  - charges ;
  - messagerie avec l'agence ;
  - préavis de départ en ligne ;
  - paiement du loyer par Mobile Money et signature du bail par code SMS ;
  - demandes d'intervention avec photos ;
  - information sur le mandat de gestion du logement.
- **Espace bailleur** (connexion par téléphone et code d'accès remis par l'agence) :
  - solde à verser, dernier versement, taux d'occupation, loyers mensuels et impayés de ses locataires ;
  - graphique des loyers nets encaissés et des versements reçus sur 12 mois, bilan de l'année ;
  - fiche de chaque bien : photo, occupé ou disponible, locataire, situation des paiements, départ prévu ;
  - relevé de compte imprimable sur la période de son choix, versements, quittances et travaux sur ses biens ;
  - un bailleur ne voit jamais les biens ni les quittances d'un autre.
- **Partage des annonces** : boutons WhatsApp, Facebook, copie du lien et partage du téléphone, sur le site et sur la fiche d'un bien disponible. Le lien partagé affiche un aperçu riche (photo, titre, loyer).
- **Récapitulatif fiscal annuel** par bailleur (agence et espace bailleur) :
  - loyers encaissés, commissions et net, bien par bien, avec les mois loués ;
  - travaux et ajustements imputés, sommes versées ;
  - imprimable et exportable vers Excel, pour aider à déclarer les revenus fonciers.
- **Application installable (PWA)** : BailManager s'installe sur ordinateur et téléphone (« Installer l'application » dans le menu du profil ; bouton « Installer » dans les espaces locataire et propriétaire), avec raccourcis vers les espaces locataire et propriétaire. Les données personnelles ne sont jamais conservées dans le cache du téléphone.
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
| Préavis de départ | 3 mois ; annulable par l'administrateur seulement |
| Relances | Une seule progression : rappel, relance, puis mise en demeure ; impossible si le locataire est à jour |
| Plan d'apurement | Le total des mensualités égale la dette ; un nouveau plan remplace le précédent |
| Clauses d'un bail ou d'un mandat | Figées à la signature : modifier les clauses types ne change pas les documents déjà signés |
| Biens confiés | Chaque bien est rattaché au mandat sous lequel il a été confié, avec sa date ; un renouvellement reprend les biens, l'ancien mandat garde sa liste |
| Facture partagée | La somme des parts égale la facture ; un index en baisse est refusé |
| Rapprochement | Une ligne de relevé ne peut être encaissée qu'une fois |
| Signatures d'état des lieux | Toute modification après signature annule les signatures |
| Licence | Limites d'utilisateurs, de biens et de modules ; lecture seule 15 jours après l'échéance |
| Maison occupée | Toute maison ayant un contrat actif est « Occupée » sur le site ; aucune visite n'y est réservable |

Les contrats de bail mentionnent l'agence mandataire (avec son représentant), le propriétaire, la référence et la durée du mandat. Ils précisent que la fin du mandat n'affecte pas le bail.

## 5. Ergonomie et charte graphique

- **Charte unique** : bleu marine, rouge « signal » réservé à l'essentiel, police Public Sans, cartes arrondies.
- **Où elle s'applique** : site public, espaces de connexion, interface de gestion et documents PDF.
- **Mobile** : toutes les pages sont adaptées. Les tableaux deviennent des cartes, et les menus des tiroirs et un menu burger.
- **Mode sombre** complet.
- **Navigation** :
  - menu latéral en tiroirs, avec compteurs ;
  - page **Menu** (sous « Tableau de bord ») : toutes les fonctions avec leur description, en trois vues — tuiles, liste, icônes (une illustration en couleur par fonction, fond selon la rubrique) — combinables avec deux ordres — par rubrique ou de A à Z (regroupées par lettre, avec barre de lettres) —, tous mémorisés ; filtre, favoris épinglés et pages récemment ouvertes ;
  - recherche globale (Ctrl K) ;
  - en-têtes de page fixes au défilement ;
  - un même loader à l'image de la marque.
- **Formulaires harmonisés** :
  - mêmes libellés, mêmes aides et sections titrées ;
  - champs obligatoires contrôlés avant envoi ;
  - focus automatique sur le premier champ.
- **Documents PDF** : quittance, contrat de bail, états des lieux, reçus, mandat, lettres de relance, plan d'apurement, relevés et rapports. Ils ont tous :
  - un en-tête à la marque et une référence encadrée ;
  - des tableaux aérés et des chiffres clés en tuiles ;
  - des blocs de signature ;
  - pour la quittance, deux exemplaires sur une seule page A4.
- **Guide d'utilisation illustré** (menu Aide) : 8 parties et 41 fiches, chacune avec une capture réelle annotée de pastilles numérotées qui renvoient aux étapes du texte, des encadrés « Bon à savoir » et « Règles appliquées par le logiciel », une recherche, des questions fréquentes, l'agrandissement des captures et l'impression du guide complet.
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
- **Espaces en ligne** : locataires et bailleurs se connectent avec leur téléphone et un code à usage personnel, stocké chiffré, désactivable à tout moment ; session limitée à 30 minutes.
- **Identifiants des fournisseurs** (SMS, WhatsApp, paiement, sauvegarde) : réservés à l'administrateur, jamais renvoyés par le serveur, séparés de la fiche entreprise publique, et **chiffrés dans la base** (une copie volée de la base ne suffit pas à les lire).
- **Licence** : signée électroniquement (Ed25519) par l'éditeur ; le logiciel ne contient que la clé publique, une licence ne peut donc être ni fabriquée ni prolongée sans la clé privée de l'éditeur.
- **Droits** : l'administrateur seul peut supprimer, résilier un mandat, réviser un loyer, gérer les utilisateurs et les paramètres.
- **Fichiers téléversés** : le contenu fait foi, jamais l'extension (un fichier renommé en « .pdf » est refusé) ; toute image est en plus décodée pour vérification ; la taille est limitée à 8 Mo.
- **Configuration** : les éléments sensibles (clé secrète, base, fichiers clients) sont exclus du dépôt Git.

## 7. Qualité

- **35 tests automatiques** sur les règles de gestion. Ils couvrent notamment :
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
  - états des lieux d'entrée et de sortie, dégradations et préavis ;
  - demandes d'intervention et imputation des coûts ;
  - relances graduées et plan d'apurement ;
  - espace bailleur (connexion, chiffres, cloisonnement entre bailleurs) ;
  - connecteurs en mode test (secrets jamais renvoyés, SMS simulé, signature par code, paiement simulé et encaissé une seule fois) ;
  - récapitulatif fiscal, sauvegarde chiffrée (aller-retour, mauvaise phrase refusée, destination absente signalée) et aperçu des annonces partagées ;
  - clauses et biens figés à la signature, secrets chiffrés, contrôle du contenu des documents ;
  - rapprochement de relevé (reconnaissance, répartition sur les mois dus, aucun double encaissement) ;
  - compteurs partagés (prorata, parts égales, logement vide au propriétaire) ;
  - agenda des visites et signature d'état des lieux sur tablette ;
  - activité de l'équipe, révision triennale, licence (essai, modules, limites, falsification, grâce, lecture seule).
- **Contrôle visuel** des 38 pages principales, en bureau et sur mobile, après chaque évolution : aucun débordement, aucune erreur JavaScript.
- **Commande** : `python manage.py test apps`.

## 8. Installation et exploitation

- **Installation Windows** : `scripts-windows\1-installer.bat`, puis `2-demarrer.bat`. Le logiciel est alors accessible sur http://localhost:8000. Détails dans `docs/INSTALLATION.md`.
- **Serveur de l'agence** (installation locale, chaque agence garde sa base) : `3-configurer-serveur.bat`, lancé une fois en administrateur, fait démarrer BailManager avec Windows, ouvre le pare-feu au réseau de l'agence et planifie rappels et sauvegardes. Paramètres > Serveur affiche l'adresse à ouvrir sur les autres postes (avec QR code pour les téléphones), la base, l'espace disque et le dernier passage des tâches.
- **Licences** : chaque agence reçoit une licence (durée, nombre d'utilisateurs, nombre de biens, modules) générée par l'éditeur avec `python manage.py generer_licence` et la clé privée, et la colle dans Paramètres > Licence. Sans licence : essai de 30 jours, tous modules ouverts.
- **Reprise des données de la version 1** : `python manage.py importer_ancienne_base`, décrite dans `docs/MIGRATION.md`.
- **Démonstration** : `python manage.py donnees_demo` (données fictives et photos libres de droits).
- **Tâches planifiées** :
  - `envoyer_rappels`, chaque jour ;
  - `sauvegarder`, une archive ZIP des données et des fichiers, puis une copie chiffrée hors du serveur.
- **Sauvegarde hors du serveur** (Paramètres > Sauvegarde) :
  - destinations : dossier (disque externe, dossier Google Drive ou OneDrive synchronisé), WebDAV (Nextcloud, kDrive…) ou stockage S3 (AWS, Backblaze B2, Wasabi, Cloudflare R2) ;
  - chiffrement AES-256 avec une phrase secrète propre à l'agence ;
  - date et résultat de la dernière copie affichés ; restauration avec `dechiffrer_sauvegarde`.

## 9. Limites connues et perspectives

- **Connecteurs** : les appels aux vrais fournisseurs suivent leur documentation publique, mais ils restent à valider avec les identifiants de l'agence (comptes de test CinetPay / PayDunya, expéditeur SMS agréé). WhatsApp impose des modèles de messages pré-approuvés pour écrire à un client hors d'une conversation en cours.
- **Installation locale** : tant que le logiciel n'est pas en ligne, les espaces locataire et propriétaire ne sont joignables que depuis le réseau de l'agence, et la confirmation automatique des paiements Mobile Money (qui suppose que l'agrégateur joigne le serveur) n'est pas possible : le rapprochement des relevés prend le relais.
- **Clé de chiffrement des secrets** : elle dérive de `SECRET_KEY` (ou de `CLE_CHIFFREMENT`) dans le fichier `.env`. Si ce fichier est perdu ou modifié, les identifiants des fournisseurs sont à ressaisir.
- **Clé privée des licences** : conservée par l'éditeur hors du projet ; sa perte empêche d'émettre de nouvelles licences (il faudrait diffuser une nouvelle version du logiciel avec une nouvelle clé publique). À sauvegarder en lieu sûr.
- **Signature par code SMS** : c'est une signature électronique simple. Sa valeur probante est à confirmer avec un conseil juridique.
- **Biens d'un bailleur au mandat expiré** : décider s'ils doivent être retirés automatiquement du site vitrine.
- **Clauses des baux et mandats antérieurs à cette version** : faute d'historique, elles ont été figées sur les clauses en vigueur au moment de la mise à jour.
- **Sauvegarde en ligne** : l'archive est chiffrée en mémoire ; pour une agence avec plusieurs gigaoctets de photos, il faudra la traiter par morceaux. La conservation des anciennes copies en WebDAV ou S3 se règle chez l'hébergeur (règles de cycle de vie).
- **Photos de démonstration** : à remplacer par les vraies photos des biens avant toute présentation à un client.
- **Textes destinés aux locataires** (information sur le mandat, messages) : à faire valider par un conseil juridique.
