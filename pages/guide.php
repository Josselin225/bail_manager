<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once('../config/db.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$entreprise = $pdo->query("SELECT * FROM settings LIMIT 1")->fetch();
if (!$entreprise) {
    $entreprise = ['nom_entreprise' => 'BailManager Corporation', 'adresse_siege' => '', 'contact_telephone' => '', 'contact_email' => ''];
}

/* Chaque capture : [fichier image, titre court, explication détaillée] */
$sections = [
    ['id' => 'vitrine', 'icon' => 'fa-globe', 'title' => 'Site vitrine public', 'desc' => "La partie visible de tous, avant connexion : présentation de l'agence, biens à louer et prise de contact.", 'shots' => [
        ['guide/g02.png', "Page d'accueil",
            "C'est la première page que voit tout visiteur. Un carrousel met en avant l'agence et son slogan (« Gestion Transparente », etc.). Le menu du haut donne accès à Accueil, Nos Services, Nos Maisons et Contact, ainsi qu'au bouton rouge « Se connecter » qui mène à l'espace d'administration réservé au personnel. Le pied de page reprend le logo, une courte présentation et les coordonnées de l'agence."],
        ['guide/g03.png', "Nos Maisons",
            "Catalogue public des biens gérés par l'agence. Chaque carte affiche une photo, un badge « Disponible » ou « Occupé », la désignation du bien, sa localisation, le loyer mensuel et les conditions (nombre de mois de caution, type de logement). Sur un bien disponible, un visiteur intéressé clique sur « Réserver maintenant » pour envoyer une demande de visite — elle apparaîtra automatiquement dans le menu Réservations côté administration."],
        ['guide/g04.png', "Nos Services — Gestion Locative",
            "Détaille l'accompagnement proposé aux bailleurs pour la gestion de leur bien : recherche et sélection de locataires sérieux, rédaction et signature des contrats, état des lieux d'entrée/sortie, encaissement des loyers et suivi des paiements, relances automatiques en cas d'impayé, et coordination de l'entretien courant."],
        ['guide/g06.png', "Nos Services — Assistance Juridique",
            "Détaille l'accompagnement légal proposé : rédaction de baux conformes à la réglementation, accompagnement en cas de litige locataire/bailleur, procédures de recouvrement des loyers impayés, conseil sur les obligations légales du bailleur, et gestion des préavis/résiliations. Le bouton « Discuter de votre projet » renvoie vers la page Contact."],
        ['guide/g05.png', "Formulaire de contact",
            "Permet à un visiteur (locataire potentiel, bailleur, partenaire) d'écrire directement à l'agence : nom, email, sujet et message. Le formulaire est protégé par un jeton anti-CSRF invisible pour l'utilisateur. Dès l'envoi, le message arrive instantanément dans la messagerie interne (menu Messages) avec un badge de notification pour le personnel."],
    ]],
    ['id' => 'connexion', 'icon' => 'fa-right-to-bracket', 'title' => 'Connexion', 'desc' => "L'accès à l'espace d'administration se fait par email et mot de passe.", 'shots' => [
        ['guide/g07.png', "Connexion à l'espace d'administration",
            "Formulaire de connexion réservé au personnel (administrateur ou agent) : email et mot de passe. Le lien « Mot de passe oublié ? » envoie un email de réinitialisation à l'adresse enregistrée. Par sécurité, après plusieurs tentatives échouées en peu de temps, le système bloque temporairement les nouvelles tentatives."],
    ]],
    ['id' => 'dashboard', 'icon' => 'fa-chart-line', 'title' => 'Tableau de bord', 'desc' => "Vue d'ensemble en temps réel : chiffre d'affaires, occupation, retards de paiement, soldes et raccourcis vers les actions courantes.", 'shots' => [
        ['guide/g01.png', "Tableau de bord",
            "Page d'accueil de l'espace d'administration. La première rangée de cartes résume le chiffre d'affaires de l'année, les recettes du mois en cours, le taux d'occupation (maisons louées / total), le nombre de locataires actifs et le nombre de retards de paiement. La seconde rangée détaille le nombre de bailleurs, le solde total à leur reverser, les cautions déposées, le revenu potentiel des maisons encore libres, le solde de caisse disponible et les charges locatives de la période. Les boutons « Encaissement », « Nouveau contrat », « Ajouter locataire », « Maisons », « Réservations » et « Messages » sont des raccourcis vers les actions les plus fréquentes. Plus bas, un tableau liste nommément les locataires en retard de paiement avec le nombre de jours de retard, accompagné de deux graphiques (encaissements mensuels et taux d'occupation)."],
    ]],
    ['id' => 'recherche', 'icon' => 'fa-magnifying-glass', 'title' => 'Recherche globale', 'desc' => "Un champ de recherche unique, disponible en permanence en haut de l'écran, pour retrouver n'importe quelle donnée sans changer de page.", 'shots' => [
        [null, "Barre de recherche du bandeau supérieur",
            "Visible en haut de chaque page de l'espace d'administration, ce champ interroge en même temps les bailleurs, les locataires, les maisons et les contrats dès que quelques lettres sont saisies (par nom, code, téléphone ou désignation du bien). Les résultats s'affichent instantanément dans un menu déroulant, classés par type, et un clic ouvre directement la fiche correspondante."],
    ]],
    ['id' => 'patrimoine', 'icon' => 'fa-building', 'title' => 'Patrimoine', 'desc' => "Gestion des propriétaires (bailleurs) et des biens immobiliers (maisons).", 'shots' => [
        ['guide/g08.png', "Répertoire des Bailleurs",
            "Liste de tous les propriétaires enregistrés, avec leur code unique, leurs coordonnées, leur adresse et leur solde actuel (la somme que l'agence leur doit reverser). La barre de recherche filtre par nom/code/téléphone, et des filtres par date affinent par période d'enregistrement. Les boutons Excel et PDF exportent la liste affichée. Pour chaque bailleur, trois actions sont disponibles : voir le détail, modifier ses informations, ou ouvrir son compte courant. Une icône enveloppe permet aussi de lui envoyer directement un email (sujet et message libres) sans quitter la page, et une pastille verte ou rouge indique s'il a un mandat de gestion actif."],
        ['guide/g09.png', "Ajout d'un bailleur",
            "Formulaire d'enregistrement d'un nouveau propriétaire, organisé en deux blocs : « Identité » (photo, nom complet, sexe, code généré automatiquement, numéro de CNI/passeport, adresse) puis « Contacts » (téléphone principal obligatoire, téléphone secondaire optionnel, email). Une fois enregistré, le bailleur apparaît dans le répertoire et peut être associé à une ou plusieurs maisons."],
        ['guide/g10.png', "Parc immobilier (Maisons)",
            "Liste de tous les biens immobiliers avec leur photo, désignation, type (appartement, studio, duplex...), propriétaire, adresse, statut (Disponible/Occupé), locataire actuel le cas échéant et loyer mensuel. Les cartes du haut résument le nombre total de biens, ceux disponibles, ceux occupés, ainsi que le revenu mensuel généré par les biens occupés avec le pourcentage d'occupation global."],
        [null, "Répertoire des Locataires",
            "Liste de tous les locataires enregistrés : statut (actif ou libre, selon qu'un contrat en cours lui est rattaché), maison actuellement occupée le cas échéant, loyer mensuel, contact et date d'enregistrement. La recherche et les filtres par statut ou par date d'enregistrement affinent la liste, un bouton bascule entre vue liste et vue cartes, et Excel/PDF exportent la liste affichée. Pour chaque locataire : modifier ses informations, ouvrir une conversation WhatsApp, gérer ses documents joints, lui envoyer un email, ou le supprimer.", false],
        [null, "Mandat de gestion agence-bailleur",
            "Avant de pouvoir ajouter un bien ou un contrat pour un bailleur, l'agence doit enregistrer un mandat de gestion : un préalable qui matérialise l'accord entre l'agence et le propriétaire. Le bouton dédié sur la fiche du bailleur ouvre un formulaire (durée du mandat, scan du document signé) ; tant qu'aucun mandat actif n'existe, le bailleur reste grisé et non sélectionnable lors de la création d'un bien ou d'un contrat. Un document imprimable reprend les termes du mandat, et en enregistrer un nouveau avant l'échéance du précédent vaut renouvellement."],
    ]],
    ['id' => 'contrats', 'icon' => 'fa-file-contract', 'title' => 'Contrats & Loyers', 'desc' => "Établissement des baux, avec calcul automatique de la caution et impression du contrat.", 'shots' => [
        ['guide/g11.png', "Liste des Contrats",
            "Recense tous les baux établis : bien loué, locataire, loyer mensuel, date de signature/début, statut (Actif, Résilié ou Terminé). Le bouton imprimante ouvre l'aperçu du contrat, et « Résilier » met fin à un bail actif."],
        ['guide/g12.png', "Établir un Contrat de Bail",
            "Formulaire d'établissement d'un nouveau bail en deux étapes. « Parties » : choix du bien disponible et du locataire via une icône loupe qui ouvre une fenêtre de recherche dédiée (pratique dès que la liste s'allonge). « Conditions financières » : le loyer mensuel est saisi, le dépôt de garantie (caution) se calcule automatiquement à 2 mois de loyer et n'est pas modifiable — une règle de gestion imposée par l'agence — tandis que l'avance de loyer et le droit d'agence ont une valeur par défaut mais restent modifiables. Le total à payer à la signature (caution + avance + droit d'agence) se met à jour en direct."],
        ['guide/g13.png', "Aperçu du contrat",
            "Une fois le contrat enregistré, cet aperçu récapitule les parties (bailleur/preneur), la désignation du bien, un tableau des conditions financières (loyer, dépôt de garantie, avance de loyer, droit d'agence, date de prise d'effet, total payé à la signature), les principales clauses définies dans les Paramètres, et les zones de signature du preneur et de l'agence."],
        ['guide/g14.png', "Impression du contrat",
            "Le contrat est mis en forme pour tenir sur une seule page A4. Le bouton « Imprimer le contrat » ouvre la boîte de dialogue d'impression du navigateur ; en choisissant l'imprimante « Microsoft Print to PDF », on obtient directement un fichier PDF du contrat à remettre au locataire."],
        [null, "Échéance et renouvellement de contrat",
            "Un bouton dédié sur chaque ligne du tableau des contrats permet de définir (ou modifier) la date de fin d'un bail. Une fois une échéance fixée, elle apparaît dans le Calendrier et déclenche les rappels automatiques à l'approche de la fin du bail ; renseigner une nouvelle date de fin sur un contrat déjà échu vaut renouvellement, sans avoir à ressaisir tout le contrat."],
    ]],
    ['id' => 'encaissements', 'icon' => 'fa-hand-holding-dollar', 'title' => 'Encaissements', 'desc' => "Suivi des loyers dus, encaissement des paiements et génération des quittances.", 'shots' => [
        ['guide/g15.png', "Suivi des Encaissements",
            "Liste les contrats actifs avec leur prochaine échéance et leur statut (« À jour » ou « Impayé »). Une bannière d'alerte en haut récapitule les loyers en retard avec le nombre de jours écoulés (J+62, J+54...). Le bouton « Encaisser » ouvre le formulaire de paiement pour ce contrat, et l'icône ↺ affiche l'historique des versements déjà effectués."],
        ['guide/g16.png', "Encaisser un loyer",
            "Formulaire d'enregistrement d'un paiement : le total du loyer dû est pré-rempli, le montant réellement versé peut être inférieur (paiement partiel accepté), et le « reste à payer » se recalcule automatiquement. Il faut préciser le mois et l'année concernés ainsi que le mode de règlement (espèces, mobile money, chèque...). Une fois confirmé, une quittance est générée."],
        ['guide/g17.png', "Historique des paiements",
            "Pour un locataire et un contrat donnés, la liste complète des versements avec leur référence unique, la période couverte, la date et l'heure du paiement, le montant versé, le statut de la période (Soldé/Partiel), le mode de règlement, et un accès direct à la quittance imprimable de chaque versement."],
        ['guide/g18.png', "Quittance de loyer",
            "Document imprimable délivré au locataire pour chaque paiement soldant une période : montant en chiffres et en toutes lettres, objet (bien concerné, période), tableau récapitulatif (loyer mensuel / total versé cumulé / reste à payer), un rappel visible s'il reste des mois impayés sur le contrat, et un QR code permettant de vérifier l'authenticité du reçu."],
        ['guide/g19.png', "Tous les encaissements",
            "Vue consolidée de tous les paiements enregistrés, tous contrats et locataires confondus. Des filtres permettent de chercher par locataire, maison ou référence, de filtrer par mode de règlement et par date d'enregistrement. Les boutons Excel/PDF exportent la liste, et « Rapport détaillé » ouvre une synthèse chiffrée sur la période choisie."],
    ]],
    ['id' => 'calendrier', 'icon' => 'fa-calendar-alt', 'title' => 'Calendrier', 'desc' => "Vue calendaire des échéances de loyer, visites et fins de contrat.", 'shots' => [
        ['guide/g20.png', "Calendrier",
            "Vue mensuelle regroupant tous les événements liés aux contrats et aux réservations, avec un code couleur : rouge pour un loyer en retard, orange pour un loyer à échéance sous 7 jours, bleu pour une échéance normale, vert pour une visite confirmée, jaune pour une visite en attente et violet pour une fin de contrat. Les boutons « month / week / list » changent l'affichage, et les flèches naviguent d'un mois à l'autre."],
    ]],
    ['id' => 'gestion', 'icon' => 'fa-bolt', 'title' => 'Gestion locative', 'desc' => "Charges locatives (eau, électricité...) et révisions de loyer.", 'shots' => [
        ['guide/g21.png', "Charges locatives",
            "Permet d'enregistrer les dépenses liées à un logement occupé (facture d'eau, d'électricité...) en les rattachant à un locataire et un contrat précis, avec un type, un montant et une description libre. Les cartes du haut totalisent le montant global des charges et le nombre de contrats actifs concernés, avec un filtre par contrat et par type de charge."],
        ['guide/g22.png', "Révision de loyer",
            "Formulaire de mise à jour du loyer d'un contrat en cours : on sélectionne le contrat, le loyer actuel s'affiche pour référence, on saisit le nouveau loyer et un motif (ex. « indexation annuelle », « loyer loyale »). Le système calcule automatiquement la variation en pourcentage par rapport à l'ancien loyer et conserve un historique indiquant qui a effectué chaque révision et quand."],
    ]],
    ['id' => 'finances', 'icon' => 'fa-money-bill-transfer', 'title' => 'Finances', 'desc' => "Comptes courants des bailleurs, caisse de l'entreprise et gestion des cautions.", 'shots' => [
        ['guide/g23.png', "Compte Bailleur",
            "Vue globale (ou filtrée par bailleur) des sommes créditées — les loyers encaissés qui leur reviennent, déduction faite de la commission de l'agence — et des sommes déjà retirées, avec le solde restant à reverser. Un graphique compare les crédits et les retraits sur 3, 6 ou 12 mois, et un diagramme circulaire répartit les soldes entre les principaux bailleurs. Le bouton « Enregistrer un retrait » trace chaque somme effectivement remise à un bailleur."],
        ['guide/g24.png', "Caisse Entreprise",
            "Suivi des flux financiers propres à l'agence elle-même (commissions perçues sur les loyers, dépenses internes comme le carburant ou les fournitures) : total des entrées, total des sorties et solde disponible en caisse, avec un graphique des flux mensuels et un historique détaillé des opérations en bas de page."],
        ['guide/g25.png', "Rapport de Caisse Entreprise",
            "Rapport imprimable détaillant, opération par opération, chaque mouvement de caisse sur une période choisie (date, type d'opération, bailleur concerné le cas échéant, description, auteur, montant et solde cumulé), avec le solde d'ouverture et de clôture de la période. Peut être filtré par agent et exporté/imprimé."],
        ['guide/g26.png', "Gestion des Cautions",
            "Suivi des dépôts de garantie versés par chaque locataire : montant initial déposé, solde restant après d'éventuels mouvements (ex. retenue pour réparations lors d'un départ), pourcentage restant représenté par une barre de progression, statut (Intact/Partiel) et l'auteur de la dernière action. Le bouton « Restituer / Retenue » permet d'enregistrer une restitution totale ou partielle en fin de bail."],
    ]],
    ['id' => 'reservations', 'icon' => 'fa-calendar-check', 'title' => 'Réservations', 'desc' => "Suivi des demandes de visite envoyées depuis le site public.", 'shots' => [
        ['guide/g27.png', "Réservations",
            "Liste les demandes de visite envoyées par les visiteurs depuis la page « Nos Maisons » du site public : nom du visiteur, téléphone, bien concerné, date de visite souhaitée et statut (En attente/Effectuée). L'icône WhatsApp ouvre une conversation directe avec le visiteur, le crochet marque la visite comme effectuée, et la corbeille supprime la demande."],
    ]],
    ['id' => 'messages', 'icon' => 'fa-envelope', 'title' => 'Messages', 'desc' => "Messagerie centralisant les messages reçus via le formulaire de contact public.", 'shots' => [
        ['guide/g28.png', "Messagerie",
            "Boîte de réception centralisant tous les messages envoyés depuis le formulaire de contact du site public. La liste de gauche affiche l'expéditeur, le sujet et une pastille rouge pour les messages non lus ; un clic ouvre le message complet à droite, avec la possibilité de répondre ou de le supprimer."],
    ]],
    ['id' => 'messagerie-locataires', 'icon' => 'fa-comments', 'title' => 'Messagerie avec les locataires', 'desc' => "Un fil de discussion direct entre l'agence et chaque locataire, distinct des messages du site public.", 'shots' => [
        [null, "Conversation par locataire",
            "Menu réservé au personnel, listant chaque locataire avec un aperçu de son dernier message et une pastille rouge indiquant le nombre de messages non lus. En ouvrant une conversation, l'historique complet s'affiche sous forme de bulles (agence à droite, locataire à gauche) et un champ en bas permet de répondre instantanément. Le locataire, de son côté, retrouve et alimente la même conversation depuis son Espace Locataire — c'est un canal séparé de l'envoi d'email ponctuel disponible depuis le répertoire des locataires."],
    ]],
    ['id' => 'documents', 'icon' => 'fa-paperclip', 'title' => 'Documents', 'desc' => "Pièces jointes numérisées, rattachées à un bailleur, un locataire, un bien ou un contrat.", 'shots' => [
        [null, "Documents rattachés à une fiche",
            "Une icône trombone, présente sur les listes de bailleurs, locataires, maisons et contrats, ouvre l'espace documentaire propre à cette fiche : pièce d'identité, quittance externe, état des lieux scanné, etc. On y dépose un fichier (JPG, PNG, WEBP ou PDF, 8 Mo maximum), et chaque document déposé reste consultable ou supprimable à tout moment, avec la date d'ajout et son auteur."],
    ]],
    ['id' => 'rapports', 'icon' => 'fa-chart-bar', 'title' => 'Rapports', 'desc' => "Génération de documents imprimables : journalier, mensuel, annuel, occupation, impayés.", 'shots' => [
        ['guide/g29.png', "Centre de Rapports",
            "Point d'entrée unique pour générer cinq types de documents : le Rapport Journalier (activités et encaissements d'une date précise), le Rapport Mensuel (bilan complet d'un mois donné), le Rapport Annuel (statistiques de l'année entière), l'État d'Occupation (liste des biens loués et vacants) et la Liste des Impayés (suivi des retards de paiement)."],
    ]],
    ['id' => 'automatisations', 'icon' => 'fa-robot', 'title' => 'Automatisations', 'desc' => "Tâches planifiées qui s'exécutent en arrière-plan, sans intervention manuelle.", 'shots' => [
        [null, "Rappels de paiement automatiques",
            "Chaque jour, le système envoie automatiquement par email un rappel de paiement aux locataires dont le loyer est en retard ou dont l'échéance approche, ainsi qu'un récapitulatif des contrats arrivant bientôt à échéance à l'agence. Rien à déclencher manuellement : la tâche tourne seule en tâche de fond sur le serveur."],
        [null, "Sauvegardes planifiées",
            "En plus du bouton « Sauvegarde » disponible sur le tableau de bord (réservé à l'administrateur) pour une sauvegarde immédiate de la base de données, une sauvegarde automatique est planifiée à intervalle régulier, garantissant qu'une copie récente des données existe toujours même si personne n'y pense."],
    ]],
    ['id' => 'journal', 'icon' => 'fa-history', 'title' => 'Journal d\'activités', 'desc' => "Traçabilité complète de toutes les actions effectuées dans le logiciel (réservé à l'administrateur).", 'shots' => [
        ['guide/g30.png', "Journal d'activités",
            "Réservé à l'administrateur, ce journal (audit trail) enregistre chaque action effectuée dans le logiciel — connexions, sauvegardes de la base de données, créations, modifications, suppressions — avec la date et l'heure exactes, l'utilisateur concerné, le détail de l'action et l'adresse IP d'origine. Des filtres par nom, par date/année et par type d'action permettent de retrouver un événement précis."],
    ]],
    ['id' => 'profil', 'icon' => 'fa-user-circle', 'title' => 'Profil & Paramètres', 'desc' => "Gestion du compte personnel et configuration de l'agence.", 'shots' => [
        ['guide/g33.png', "Mon Profil",
            "Chaque utilisateur peut y changer sa photo de profil, modifier ses informations personnelles (nom, email) et changer son mot de passe. Le menu déroulant visible en haut à droite donne aussi accès, selon le rôle, au Support Tech, à la Gestion des utilisateurs, aux Paramètres de l'agence et à la Déconnexion."],
        ['guide/g34.png', "Paramètres",
            "Page réservée à l'administrateur : logo de l'agence, nom, taux de commission appliqué sur les loyers encaissés, email et téléphone de contact, adresse du siège. Plus bas (non visible ici), la gestion des clauses du contrat de bail permet d'ajouter, modifier ou désactiver les clauses qui apparaîtront automatiquement sur chaque contrat imprimé."],
    ]],
    ['id' => 'theme', 'icon' => 'fa-palette', 'title' => 'Personnalisation', 'desc' => "Mode sombre et couleur du menu personnalisable, au choix de chaque utilisateur.", 'shots' => [
        ['guide/g35.png', "Personnalisation du thème",
            "L'icône lune (en haut de la sidebar) bascule instantanément l'application en mode sombre. L'icône palette ouvre un sélecteur permettant de choisir la couleur du menu latéral parmi une palette prédéfinie ou une teinte précise via le curseur arc-en-ciel. Ce choix est propre à chaque utilisateur et reste mémorisé à ses prochaines connexions."],
    ]],
    ['id' => 'locataire', 'icon' => 'fa-key', 'title' => 'Espace Locataire', 'desc' => "Portail dédié permettant à chaque locataire de suivre son bail avec un code fourni par l'agence.", 'shots' => [
        ['guide/g32.png', "Espace Locataire",
            "Portail totalement séparé de l'espace d'administration : chaque locataire s'y connecte avec son numéro de téléphone et un code d'accès personnel fourni par l'agence, pour consulter son contrat et l'historique de ses paiements sans jamais avoir accès aux données des autres locataires ni aux fonctions d'administration."],
    ]],
    ['id' => 'apropos', 'icon' => 'fa-circle-info', 'title' => 'À propos', 'desc' => "Informations sur l'application, ses fonctionnalités et son support.", 'shots' => [
        ['guide/g31.png', "À propos",
            "Résume les fonctionnalités principales du logiciel, les technologies utilisées pour le développer, les coordonnées de l'agence qui l'utilise, ainsi que les coordonnées du développeur à contacter en cas de difficulté technique."],
    ]],
];

/* Aplatit les sections en pages de livre : garde, sommaire, une page "chapitre"
   par section puis une page par capture/nouveauté, et une couverture de fin. */
$pages = [];
$pages[] = ['type' => 'cover'];
$pages[] = ['type' => 'toc', 'entries' => []];
foreach ($sections as $s) {
    $chapterIdx = count($pages);
    $pages[1]['entries'][] = ['icon' => $s['icon'], 'title' => $s['title'], 'page' => $chapterIdx];
    $pages[] = ['type' => 'chapter', 'section' => $s, 'firstShotIndex' => $chapterIdx + 1];
    foreach ($s['shots'] as $shot) {
        $pages[] = ['type' => 'shot', 'section' => $s, 'img' => $shot[0], 'title' => $shot[1], 'text' => $shot[2], 'badge' => $shot[3] ?? 'Nouveauté'];
    }
}
$pages[] = ['type' => 'backcover'];
$totalPages = count($pages);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../favicon.svg">
    <title>Guide d'utilisation — BailManager</title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/fontawesome/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        :root { --marine:#002147; }

        /* Le viewer livre gère lui-même tout l'espace de .main-content, sans le padding standard */
        .main-content:has(> .book-app) { padding: 0 !important; overflow: hidden; }
        .book-app { height: calc(100vh - var(--tb-h, 60px)); display:flex; flex-direction:column; background:#e8ecf4; }

        /* ── Barre d'outils ─────────────────────────────────────────── */
        .book-toolbar { flex:none; display:flex; align-items:center; justify-content:space-between; gap:12px; padding:10px 20px; background:#fff; border-bottom:1px solid #e0e6f0; flex-wrap:wrap; }
        .book-toolbar-group { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
        .book-btn { display:inline-flex; align-items:center; gap:6px; height:34px; padding:0 13px; border-radius:8px; border:1.5px solid #e0e6f0; background:#fff; color:#2d3a55; font-size:12.5px; font-weight:600; cursor:pointer; transition:.15s; }
        .book-btn:hover:not(:disabled) { background:#eef2fb; border-color:#c9d4ea; color:var(--marine); }
        .book-btn:disabled { opacity:.4; cursor:not-allowed; }
        .book-btn.icon-only { width:34px; padding:0; justify-content:center; }
        .book-breadcrumb { font-size:12.5px; font-weight:700; color:#2d3a55; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:220px; }
        .book-breadcrumb .bb-sep { color:#aab4c8; font-weight:400; margin:0 4px; }
        .book-pagecount { font-size:12px; color:#7280a0; font-weight:600; min-width:64px; text-align:center; }
        .book-zoom-val { font-size:12px; color:#7280a0; font-weight:700; min-width:40px; text-align:center; }

        /* ── Zone de lecture ────────────────────────────────────────── */
        .book-viewport { position:relative; flex:1; overflow:auto; display:flex; align-items:center; justify-content:center; padding:24px; }
        .book-stage { position:relative; width:960px; height:720px; flex:none; transition:transform .12s ease; }
        .book-navbtn { position:absolute; top:50%; transform:translateY(-50%); width:44px; height:44px; border-radius:50%; border:none; background:rgba(255,255,255,.9); box-shadow:0 3px 12px rgba(0,0,0,.15); color:var(--marine); font-size:16px; cursor:pointer; z-index:20; transition:.15s; display:flex; align-items:center; justify-content:center; }
        .book-navbtn:hover:not(:disabled) { background:#fff; transform:translateY(-50%) scale(1.08); }
        .book-navbtn:disabled { opacity:0; pointer-events:none; }
        .book-navbtn.prev { left:8px; }
        .book-navbtn.next { right:8px; }

        /* ── La page elle-même ──────────────────────────────────────── */
        .book-page { position:absolute; inset:0; background:#fff; border-radius:10px; box-shadow:0 12px 40px rgba(20,30,60,.22); opacity:0; visibility:hidden; transform:scale(.97); transition:opacity .22s ease, transform .22s ease; overflow:hidden; display:flex; flex-direction:column; }
        .book-page.active { opacity:1; visibility:visible; transform:scale(1); z-index:10; }
        .book-page-num { position:absolute; bottom:10px; right:16px; font-size:10.5px; color:#b7bfd1; font-weight:600; }

        /* Garde */
        .bp-cover { justify-content:center; align-items:center; text-align:center; background:linear-gradient(135deg, #000080 0%, #002147 60%, #000033 100%); color:#fff; padding:40px; gap:6px; }
        .bp-cover .cover-logo { width:80px; height:80px; border-radius:18px; background:#fff; display:flex; align-items:center; justify-content:center; margin-bottom:18px; }
        .bp-cover .cover-logo img { max-width:64px; max-height:64px; object-fit:contain; }
        .bp-cover h1 { font-weight:800; font-size:2.5rem; margin-bottom:8px; }
        .bp-cover h1 span { color:#ff4d4d; }
        .bp-cover .cover-sub { font-size:1.05rem; opacity:.9; margin-bottom:20px; max-width:70%; }
        .bp-cover .cover-meta { font-size:.85rem; opacity:.75; }
        .bp-cover .cover-badge { display:inline-block; background:rgba(255,255,255,.15); border:1px solid rgba(255,255,255,.3); padding:6px 16px; border-radius:20px; font-size:.8rem; margin-top:16px; }
        .bp-cover .cover-start { margin-top:28px; display:inline-flex; align-items:center; gap:8px; background:#fff; color:var(--marine); border:none; padding:11px 24px; border-radius:24px; font-weight:700; font-size:13.5px; cursor:pointer; transition:.15s; }
        .bp-cover .cover-start:hover { transform:translateY(-2px); box-shadow:0 6px 18px rgba(0,0,0,.25); }
        .bp-cover .cover-hint { margin-top:14px; font-size:11.5px; opacity:.6; }

        /* Sommaire */
        .bp-toc { padding:36px 44px; overflow-y:auto; }
        .bp-toc h2 { font-size:19px; font-weight:800; color:#2d3a55; margin-bottom:20px; display:flex; align-items:center; gap:10px; }
        .bp-toc-grid { display:grid; grid-template-columns:1fr 1fr; gap:7px; }
        .bp-toc-link { display:flex; align-items:center; gap:10px; padding:10px 13px; border-radius:9px; background:#f8faff; border:1px solid transparent; cursor:pointer; font-size:12.5px; font-weight:600; color:#2d3a55; text-align:left; transition:.15s; }
        .bp-toc-link:hover { background:#eef2fb; border-color:#c9d4ea; color:var(--marine); }
        .bp-toc-link i:first-child { color:var(--marine); width:16px; text-align:center; flex:none; }
        .bp-toc-link .tl-page { margin-left:auto; font-size:10.5px; color:#9aa4b6; font-weight:700; flex:none; }

        /* Page chapitre */
        .bp-chapter { padding:40px 50px; align-items:center; text-align:center; justify-content:center; overflow-y:auto; }
        .bp-chapter .ch-icon { width:64px; height:64px; border-radius:16px; background:#eef2fb; color:var(--marine); display:flex; align-items:center; justify-content:center; font-size:26px; margin-bottom:18px; }
        .bp-chapter h2 { font-size:22px; font-weight:800; color:#2d3a55; margin-bottom:10px; }
        .bp-chapter p { font-size:13.5px; color:#7280a0; max-width:520px; margin-bottom:26px; line-height:1.7; }
        .bp-chapter .ch-list { display:flex; flex-direction:column; gap:6px; width:100%; max-width:440px; text-align:left; }
        .bp-chapter .ch-list button { display:flex; align-items:center; gap:10px; background:#f8faff; border:1px solid #eef1f8; border-radius:8px; padding:9px 14px; font-size:12px; font-weight:600; color:#45516e; cursor:pointer; transition:.15s; }
        .bp-chapter .ch-list button:hover { background:#eef2fb; color:var(--marine); border-color:#c9d4ea; }
        .bp-chapter .ch-list button .ch-dot { width:6px; height:6px; border-radius:50%; background:#c9d4ea; flex:none; }

        /* Page capture */
        .bp-shot { padding:0; }
        .bp-shot-imgwrap { flex:1 1 auto; min-height:0; background:#f4f7fe; display:flex; align-items:center; justify-content:center; overflow:hidden; }
        .bp-shot-imgwrap img { max-width:100%; max-height:100%; object-fit:contain; cursor:zoom-in; }
        .bp-shot-caption { flex:none; padding:16px 26px; border-top:1px solid #eef1f8; max-height:180px; overflow-y:auto; }
        .bp-shot-eyebrow { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:#9aa4b6; margin-bottom:4px; }
        .bp-shot-title { font-size:14.5px; font-weight:700; color:#2d3a55; margin-bottom:6px; }
        .bp-shot-text { font-size:12.5px; color:#5a6a85; line-height:1.65; margin:0; }
        .bp-shot-badge { display:inline-block; background:#f5a623; color:#fff; font-size:9px; font-weight:700; text-transform:uppercase; letter-spacing:.03em; padding:2px 8px; border-radius:10px; margin-left:8px; vertical-align:middle; }

        /* Page capture sans image (nouveauté texte plein écran) */
        .bp-shot-textonly { flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; text-align:center; padding:50px 70px; background:#fffaf0; }
        .bp-shot-textonly .ts-icon { width:56px; height:56px; border-radius:14px; background:#f5a623; color:#fff; display:flex; align-items:center; justify-content:center; font-size:22px; margin-bottom:18px; }
        .bp-shot-textonly .bp-shot-title { font-size:17px; margin-bottom:4px; }
        .bp-shot-textonly .bp-shot-eyebrow { margin-bottom:8px; }
        .bp-shot-textonly .bp-shot-text { font-size:13px; max-width:560px; line-height:1.75; }

        /* Couverture de fin */
        .bp-backcover { justify-content:center; align-items:center; text-align:center; background:linear-gradient(135deg, #001a3d 0%, #000080 100%); color:#fff; padding:40px; }
        .bp-backcover h2 { font-weight:800; margin-bottom:10px; font-size:1.6rem; }
        .bp-backcover p { opacity:.85; font-size:.95rem; margin-bottom:4px; }
        .bp-backcover .bc-contact { display:inline-flex; align-items:center; gap:8px; background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.25); padding:8px 18px; border-radius:20px; margin:6px; font-size:.85rem; color:#fff; text-decoration:none; }
        .bp-backcover .bc-contact:hover { background:rgba(255,255,255,.22); color:#fff; }
        .bp-backcover .bc-restart { margin-top:20px; display:inline-flex; align-items:center; gap:8px; background:transparent; color:#fff; border:1.5px solid rgba(255,255,255,.4); padding:9px 20px; border-radius:22px; font-weight:700; font-size:12.5px; cursor:pointer; transition:.15s; }
        .bp-backcover .bc-restart:hover { background:rgba(255,255,255,.12); }

        /* Lightbox */
        #imgLightbox { display:none; position:fixed; inset:0; background:rgba(10,14,25,.9); z-index:3000; align-items:center; justify-content:center; padding:30px; cursor:zoom-out; }
        #imgLightbox img { max-width:100%; max-height:100%; border-radius:8px; box-shadow:0 10px 40px rgba(0,0,0,.5); }
        #imgLightbox .lb-close { position:absolute; top:20px; right:28px; color:#fff; font-size:26px; cursor:pointer; }

        @media (max-width: 767px) {
            .book-breadcrumb { display:none; }
            .bp-toc-grid { grid-template-columns:1fr; }
            .bp-chapter { padding:24px; }
            .bp-shot-caption { max-height:38vh; }
        }

        /* ── Impression : toutes les pages s'enchaînent, une par feuille ── */
        @media print {
            .book-toolbar, .book-navbtn, #imgLightbox { display:none !important; }
            .main-content:has(> .book-app) { overflow:visible !important; }
            .book-app { height:auto !important; background:#fff !important; display:block !important; }
            .book-viewport { position:static !important; overflow:visible !important; padding:0 !important; display:block !important; }
            .book-stage { position:static !important; width:auto !important; height:auto !important; transform:none !important; }
            .book-page {
                position:static !important; opacity:1 !important; visibility:visible !important; transform:none !important;
                width:100% !important; height:100vh !important; overflow:visible !important;
                box-shadow:none !important; border-radius:0 !important;
                page-break-after:always; break-after:page;
            }
            .book-page:last-of-type { page-break-after:auto; break-after:auto; }
            .bp-toc, .bp-chapter, .bp-shot-caption { overflow:visible !important; max-height:none !important; }
            .bp-shot-imgwrap img, #imgLightbox img { cursor:default !important; }
            .bp-toc-link, .ch-list button { cursor:default !important; }
            @page { size:landscape; margin:10mm; }
        }

        html[data-theme="dark"] .book-app { background:#161a22 !important; }
        html[data-theme="dark"] .book-toolbar { background:#1e222b !important; border-color:#2e333d !important; }
        html[data-theme="dark"] .book-btn { background:#1e222b !important; border-color:#2e333d !important; color:#e4e6eb !important; }
        html[data-theme="dark"] .book-btn:hover:not(:disabled) { background:#262b35 !important; }
        html[data-theme="dark"] .book-breadcrumb { color:#e4e6eb !important; }
        html[data-theme="dark"] .book-pagecount, html[data-theme="dark"] .book-zoom-val { color:#9aa4b6 !important; }
        html[data-theme="dark"] .book-navbtn { background:rgba(30,34,43,.9) !important; color:#e4e6eb !important; }
        html[data-theme="dark"] .book-navbtn:hover:not(:disabled) { background:#262b35 !important; }
        html[data-theme="dark"] .book-page { background:#1e222b !important; }
        html[data-theme="dark"] .bp-toc h2, html[data-theme="dark"] .bp-chapter h2, html[data-theme="dark"] .bp-shot-title { color:#e4e6eb !important; }
        html[data-theme="dark"] .bp-toc-link { background:#262b35 !important; color:#e4e6eb !important; }
        html[data-theme="dark"] .bp-toc-link:hover { background:#2e333d !important; }
        html[data-theme="dark"] .bp-toc-link .tl-page { color:#828da3 !important; }
        html[data-theme="dark"] .bp-chapter .ch-icon { background:#262b35 !important; }
        html[data-theme="dark"] .bp-chapter p, html[data-theme="dark"] .bp-shot-text { color:#9aa4b6 !important; }
        html[data-theme="dark"] .bp-chapter .ch-list button { background:#262b35 !important; border-color:#2e333d !important; color:#c3cad9 !important; }
        html[data-theme="dark"] .bp-chapter .ch-list button:hover { background:#2e333d !important; }
        html[data-theme="dark"] .bp-shot-imgwrap { background:#161a22 !important; }
        html[data-theme="dark"] .bp-shot-caption { border-color:#2e333d !important; }
        html[data-theme="dark"] .bp-shot-textonly { background:#241f14 !important; }
        html[data-theme="dark"] .book-page-num { color:#4a5164 !important; }
    </style>
</head>
<body>
<?php include('../includes/sidebar.php'); ?>

<div class="main-content">
<div class="book-app" id="bookApp" data-total="<?= $totalPages ?>">

    <!-- ══ BARRE D'OUTILS ══ -->
    <div class="book-toolbar">
        <div class="book-toolbar-group">
            <button class="book-btn" id="btnToc" title="Aller au sommaire"><i class="fa fa-list-ol"></i>Sommaire</button>
            <span class="book-breadcrumb" id="bookBreadcrumb"></span>
        </div>
        <div class="book-toolbar-group">
            <button class="book-btn icon-only" id="btnZoomOut" title="Zoom arrière (touche -)"><i class="fa fa-magnifying-glass-minus"></i></button>
            <span class="book-zoom-val" id="zoomVal">100%</span>
            <button class="book-btn icon-only" id="btnZoomIn" title="Zoom avant (touche +)"><i class="fa fa-magnifying-glass-plus"></i></button>
            <button class="book-btn icon-only" id="btnZoomReset" title="Ajuster à l'écran (touche 0)"><i class="fa fa-compress"></i></button>
        </div>
        <div class="book-toolbar-group">
            <button class="book-btn" id="btnPrint" title="Imprimer tout le guide"><i class="fa fa-print"></i>Imprimer</button>
            <span class="book-pagecount" id="bookPagecount"></span>
        </div>
    </div>

    <!-- ══ ZONE DE LECTURE ══ -->
    <div class="book-viewport" id="bookViewport">
        <button class="book-navbtn prev" id="btnPrev" title="Page précédente (←)"><i class="fa fa-chevron-left"></i></button>

        <div class="book-stage" id="bookStage">
            <?php foreach ($pages as $i => $p): ?>
                <?php if ($p['type'] === 'cover'): ?>
                    <div class="book-page bp-cover" data-index="<?= $i ?>">
                        <div class="cover-logo">
                            <?php if (!empty($entreprise['logo_url']) && file_exists("../uploads/" . $entreprise['logo_url'])): ?>
                                <img src="../uploads/<?= htmlspecialchars($entreprise['logo_url']) ?>" alt="Logo">
                            <?php else: ?>
                                <i class="fa fa-house-chimney" style="font-size:30px;color:var(--marine);"></i>
                            <?php endif; ?>
                        </div>
                        <h1>BAIL<span>MANAGER</span></h1>
                        <div class="cover-sub">Guide d'utilisation complet du logiciel de gestion locative et immobilière</div>
                        <div class="cover-meta">
                            <?= htmlspecialchars($entreprise['nom_entreprise']) ?>
                            <?= !empty($entreprise['adresse_siege']) ? ' · ' . htmlspecialchars(explode("\n", $entreprise['adresse_siege'])[0]) : '' ?>
                        </div>
                        <div class="cover-badge"><i class="fa fa-tag me-1"></i>Version <?= htmlspecialchars(APP_VERSION) ?></div>
                        <button class="cover-start" onclick="bookGoTo(1)"><i class="fa fa-book-open"></i>Commencer la lecture</button>
                        <div class="cover-hint">Utilisez les flèches ← → du clavier pour tourner les pages, + / - pour zoomer</div>
                    </div>

                <?php elseif ($p['type'] === 'toc'): ?>
                    <div class="book-page bp-toc" data-index="<?= $i ?>">
                        <h2><i class="fa fa-list-ol" style="color:var(--marine);"></i>Sommaire</h2>
                        <div class="bp-toc-grid">
                            <?php foreach ($p['entries'] as $e): ?>
                                <button class="bp-toc-link" onclick="bookGoTo(<?= $e['page'] ?>)">
                                    <i class="fa <?= $e['icon'] ?>"></i><?= htmlspecialchars($e['title']) ?>
                                    <span class="tl-page">p.<?= $e['page'] + 1 ?></span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                <?php elseif ($p['type'] === 'chapter'): ?>
                    <?php $s = $p['section']; ?>
                    <div class="book-page bp-chapter" data-index="<?= $i ?>" data-chapter="<?= htmlspecialchars($s['title']) ?>">
                        <div class="ch-icon"><i class="fa <?= $s['icon'] ?>"></i></div>
                        <h2><?= htmlspecialchars($s['title']) ?></h2>
                        <p><?= htmlspecialchars($s['desc']) ?></p>
                        <div class="ch-list">
                            <?php foreach ($s['shots'] as $k => $shot): ?>
                                <button onclick="bookGoTo(<?= $p['firstShotIndex'] + $k ?>)"><span class="ch-dot"></span><?= htmlspecialchars($shot[1]) ?></button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                <?php elseif ($p['type'] === 'shot'): ?>
                    <?php $s = $p['section']; ?>
                    <div class="book-page bp-shot" data-index="<?= $i ?>" data-chapter="<?= htmlspecialchars($s['title']) ?>">
                        <?php if ($p['img']): ?>
                            <div class="bp-shot-imgwrap">
                                <img src="../uploads/<?= htmlspecialchars($p['img']) ?>" alt="<?= htmlspecialchars($p['title']) ?>" onclick="openLightbox(this.src)">
                            </div>
                            <div class="bp-shot-caption">
                                <div class="bp-shot-eyebrow"><?= htmlspecialchars($s['title']) ?></div>
                                <div class="bp-shot-title"><?= htmlspecialchars($p['title']) ?></div>
                                <p class="bp-shot-text"><?= htmlspecialchars($p['text']) ?></p>
                            </div>
                        <?php else: ?>
                            <div class="bp-shot-textonly">
                                <div class="ts-icon"><i class="fa fa-circle-info"></i></div>
                                <div class="bp-shot-eyebrow"><?= htmlspecialchars($s['title']) ?></div>
                                <div class="bp-shot-title"><?= htmlspecialchars($p['title']) ?><?php if ($p['badge']): ?><span class="bp-shot-badge"><?= htmlspecialchars($p['badge']) ?></span><?php endif; ?></div>
                                <p class="bp-shot-text"><?= htmlspecialchars($p['text']) ?></p>
                            </div>
                        <?php endif; ?>
                    </div>

                <?php elseif ($p['type'] === 'backcover'): ?>
                    <div class="book-page bp-backcover" data-index="<?= $i ?>">
                        <h2>Fin du guide</h2>
                        <p>Vous avez maintenant une vue complète des fonctionnalités de BailManager.</p>
                        <p>Pour toute question, l'équipe support reste à votre disposition.</p>
                        <div class="mt-3">
                            <a href="tel:+2250749791287" class="bc-contact"><i class="fa fa-phone-alt"></i> (+225) 07 49 79 12 87</a>
                            <a href="mailto:kkjoss01@gmail.com" class="bc-contact"><i class="fa fa-envelope"></i> kkjoss01@gmail.com</a>
                        </div>
                        <p class="cover-meta mt-3" style="opacity:.6;font-size:.8rem;">BailManager — Version <?= htmlspecialchars(APP_VERSION) ?> · <?= htmlspecialchars($entreprise['nom_entreprise']) ?></p>
                        <button class="bc-restart" onclick="bookGoTo(0)"><i class="fa fa-rotate-left"></i>Revenir à la couverture</button>
                    </div>
                <?php endif; ?>
                <span class="book-page-num"><?= $i + 1 ?> / <?= $totalPages ?></span>
            <?php endforeach; ?>
        </div>

        <button class="book-navbtn next" id="btnNext" title="Page suivante (→)"><i class="fa fa-chevron-right"></i></button>
    </div>
</div>
</div>

<div id="imgLightbox" onclick="closeLightbox()">
    <span class="lb-close"><i class="fa fa-times"></i></span>
    <img id="imgLightboxSrc" src="" alt="Aperçu agrandi">
</div>

<script src="../js/bootstrap.bundle.min.js"></script>
<script>
    function openLightbox(src) {
        event.stopPropagation();
        document.getElementById('imgLightboxSrc').src = src;
        document.getElementById('imgLightbox').style.display = 'flex';
    }
    function closeLightbox() {
        document.getElementById('imgLightbox').style.display = 'none';
    }

    (function() {
        var app = document.getElementById('bookApp');
        var total = parseInt(app.dataset.total, 10);
        var pages = Array.prototype.slice.call(document.querySelectorAll('.book-page'));
        var stage = document.getElementById('bookStage');
        var viewport = document.getElementById('bookViewport');
        var btnPrev = document.getElementById('btnPrev');
        var btnNext = document.getElementById('btnNext');
        var btnToc = document.getElementById('btnToc');
        var btnPrint = document.getElementById('btnPrint');
        var btnZoomIn = document.getElementById('btnZoomIn');
        var btnZoomOut = document.getElementById('btnZoomOut');
        var btnZoomReset = document.getElementById('btnZoomReset');
        var zoomVal = document.getElementById('zoomVal');
        var pagecountEl = document.getElementById('bookPagecount');
        var breadcrumbEl = document.getElementById('bookBreadcrumb');
        var BASE_W = 960, BASE_H = 720;

        var current = 0;
        try {
            var saved = parseInt(localStorage.getItem('guideBookPage'), 10);
            if (!isNaN(saved) && saved >= 0 && saved < total) current = saved;
        } catch (e) {}

        var fitScale = 1, zoom = null, userZoomed = false;

        function computeFit() {
            var padding = 48;
            var availW = viewport.clientWidth - padding;
            var availH = viewport.clientHeight - padding;
            return Math.max(0.25, Math.min(availW / BASE_W, availH / BASE_H, 1.5));
        }

        function applyZoom() {
            var z = userZoomed ? zoom : fitScale;
            stage.style.transform = 'scale(' + z + ')';
            zoomVal.textContent = Math.round((z / fitScale) * 100) + '%';
        }

        function refreshFit() {
            fitScale = computeFit();
            if (!userZoomed) zoom = fitScale;
            applyZoom();
        }

        function setZoom(factor) {
            userZoomed = true;
            zoom = Math.max(fitScale * 0.4, Math.min(fitScale * 3, zoom * factor));
            applyZoom();
        }

        function resetZoom() {
            userZoomed = false;
            zoom = fitScale;
            applyZoom();
        }

        function render() {
            pages.forEach(function(el) {
                var idx = parseInt(el.dataset.index, 10);
                el.classList.toggle('active', idx === current);
            });
            btnPrev.disabled = current === 0;
            btnNext.disabled = current === total - 1;
            pagecountEl.textContent = 'Page ' + (current + 1) + ' / ' + total;
            var activeEl = pages[current];
            var chapter = activeEl ? activeEl.dataset.chapter : '';
            breadcrumbEl.textContent = chapter || '';
            try { localStorage.setItem('guideBookPage', current); } catch (e) {}
        }

        function bookGoToImpl(idx) {
            current = Math.max(0, Math.min(total - 1, idx));
            render();
        }
        window.bookGoTo = bookGoToImpl;

        btnPrev.addEventListener('click', function() { bookGoToImpl(current - 1); });
        btnNext.addEventListener('click', function() { bookGoToImpl(current + 1); });
        btnToc.addEventListener('click', function() { bookGoToImpl(1); });
        btnPrint.addEventListener('click', function() { window.print(); });
        btnZoomIn.addEventListener('click', function() { setZoom(1.18); });
        btnZoomOut.addEventListener('click', function() { setZoom(1 / 1.18); });
        btnZoomReset.addEventListener('click', resetZoom);

        document.addEventListener('keydown', function(e) {
            var tag = document.activeElement ? document.activeElement.tagName : '';
            if (tag === 'INPUT' || tag === 'TEXTAREA') return;
            if (document.getElementById('imgLightbox').style.display === 'flex') {
                if (e.key === 'Escape') closeLightbox();
                return;
            }
            switch (e.key) {
                case 'ArrowLeft': case 'PageUp': bookGoToImpl(current - 1); break;
                case 'ArrowRight': case 'PageDown': case ' ': bookGoToImpl(current + 1); e.preventDefault(); break;
                case 'Home': bookGoToImpl(0); break;
                case 'End': bookGoToImpl(total - 1); break;
                case '+': case '=': setZoom(1.18); break;
                case '-': case '_': setZoom(1 / 1.18); break;
                case '0': resetZoom(); break;
            }
        });

        viewport.addEventListener('wheel', function(e) {
            if (!e.ctrlKey) return;
            e.preventDefault();
            setZoom(e.deltaY < 0 ? 1.08 : 1 / 1.08);
        }, { passive: false });

        window.addEventListener('resize', refreshFit);

        refreshFit();
        render();
    })();
</script>
</body>
</html>
