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
    ['id' => 'patrimoine', 'icon' => 'fa-building', 'title' => 'Patrimoine', 'desc' => "Gestion des propriétaires (bailleurs) et des biens immobiliers (maisons).", 'shots' => [
        ['guide/g08.png', "Répertoire des Bailleurs",
            "Liste de tous les propriétaires enregistrés, avec leur code unique, leurs coordonnées, leur adresse et leur solde actuel (la somme que l'agence leur doit reverser). La barre de recherche filtre par nom/code/téléphone, et des filtres par date affinent par période d'enregistrement. Les boutons Excel et PDF exportent la liste affichée. Pour chaque bailleur, trois actions sont disponibles : voir le détail, modifier ses informations, ou ouvrir son compte courant."],
        ['guide/g09.png', "Ajout d'un bailleur",
            "Formulaire d'enregistrement d'un nouveau propriétaire, organisé en deux blocs : « Identité » (photo, nom complet, sexe, code généré automatiquement, numéro de CNI/passeport, adresse) puis « Contacts » (téléphone principal obligatoire, téléphone secondaire optionnel, email). Une fois enregistré, le bailleur apparaît dans le répertoire et peut être associé à une ou plusieurs maisons."],
        ['guide/g10.png', "Parc immobilier (Maisons)",
            "Liste de tous les biens immobiliers avec leur photo, désignation, type (appartement, studio, duplex...), propriétaire, adresse, statut (Disponible/Occupé), locataire actuel le cas échéant et loyer mensuel. Les cartes du haut résument le nombre total de biens, ceux disponibles, ceux occupés, ainsi que le revenu mensuel généré par les biens occupés avec le pourcentage d'occupation global."],
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
    ['id' => 'rapports', 'icon' => 'fa-chart-bar', 'title' => 'Rapports', 'desc' => "Génération de documents imprimables : journalier, mensuel, annuel, occupation, impayés.", 'shots' => [
        ['guide/g29.png', "Centre de Rapports",
            "Point d'entrée unique pour générer cinq types de documents : le Rapport Journalier (activités et encaissements d'une date précise), le Rapport Mensuel (bilan complet d'un mois donné), le Rapport Annuel (statistiques de l'année entière), l'État d'Occupation (liste des biens loués et vacants) et la Liste des Impayés (suivi des retards de paiement)."],
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
        .main-content { background:#f4f7fe; min-height:100vh; padding:28px; }

        /* Page de garde */
        .cover-page {
            border-radius:18px;
            background:linear-gradient(135deg, #000080 0%, #002147 60%, #000033 100%);
            color:#fff; padding:60px 40px; text-align:center; margin-bottom:24px;
            box-shadow:0 8px 30px rgba(0,0,0,.18);
        }
        .cover-page .cover-logo { width:76px; height:76px; border-radius:16px; background:#fff; display:flex; align-items:center; justify-content:center; margin:0 auto 20px; }
        .cover-page .cover-logo img { max-width:60px; max-height:60px; object-fit:contain; }
        .cover-page h1 { font-weight:800; font-size:2.4rem; margin-bottom:6px; }
        .cover-page h1 span { color:#ff4d4d; }
        .cover-page .cover-sub { font-size:1.05rem; opacity:.9; margin-bottom:22px; }
        .cover-page .cover-meta { font-size:.85rem; opacity:.75; }
        .cover-page .cover-badge { display:inline-block; background:rgba(255,255,255,.15); border:1px solid rgba(255,255,255,.3); padding:6px 16px; border-radius:20px; font-size:.8rem; margin-top:16px; }

        /* Sommaire */
        .toc-card { background:#fff; border-radius:14px; border:1px solid #e8ecf4; box-shadow:0 2px 10px rgba(0,0,0,.06); padding:22px; margin-bottom:24px; }
        .toc-title { font-size:14px; font-weight:700; color:#2d3a55; margin-bottom:14px; display:flex; align-items:center; gap:8px; }
        .toc-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(220px,1fr)); gap:8px; }
        .toc-link { display:flex; align-items:center; gap:10px; padding:9px 12px; border-radius:9px; text-decoration:none; color:#2d3a55; font-size:13px; font-weight:600; background:#f8faff; transition:.15s; }
        .toc-link:hover { background:#eef2fb; color:var(--marine); }
        .toc-link i { color:var(--marine); width:16px; text-align:center; }

        /* Sections */
        .guide-section { background:#fff; border-radius:14px; border:1px solid #e8ecf4; box-shadow:0 2px 10px rgba(0,0,0,.06); padding:26px; margin-bottom:22px; scroll-margin-top:20px; }
        .guide-section-header { display:flex; align-items:center; gap:12px; margin-bottom:6px; }
        .guide-section-icon { width:38px; height:38px; border-radius:10px; background:#eef2fb; color:var(--marine); display:flex; align-items:center; justify-content:center; font-size:16px; flex:none; }
        .guide-section-title { font-size:17px; font-weight:700; color:#2d3a55; margin:0; }
        .guide-section-desc { font-size:13px; color:#7280a0; margin:6px 0 18px 50px; }
        .shot-grid { display:grid; grid-template-columns:1fr; gap:18px; }
        .shot-card { border:1px solid #eef1f8; border-radius:12px; overflow:hidden; background:#fafbfe; }
        .shot-card img { width:100%; display:block; cursor:zoom-in; border-bottom:1px solid #eef1f8; }
        .shot-caption { padding:14px 16px; }
        .shot-title { font-size:13.5px; font-weight:700; color:#2d3a55; margin-bottom:6px; }
        .shot-text { font-size:12.5px; color:#5a6a85; margin:0; line-height:1.6; }

        /* Couverture de fin */
        .back-cover { border-radius:18px; background:linear-gradient(135deg, #001a3d 0%, #000080 100%); color:#fff; padding:56px 40px; text-align:center; margin-top:6px; }
        .back-cover h2 { font-weight:800; margin-bottom:10px; }
        .back-cover p { opacity:.85; font-size:.95rem; margin-bottom:4px; }
        .back-cover .bc-contact { display:inline-flex; align-items:center; gap:8px; background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.25); padding:8px 18px; border-radius:20px; margin:6px; font-size:.85rem; color:#fff; text-decoration:none; }
        .back-cover .bc-contact:hover { background:rgba(255,255,255,.22); color:#fff; }

        /* Lightbox */
        #imgLightbox { display:none; position:fixed; inset:0; background:rgba(10,14,25,.9); z-index:3000; align-items:center; justify-content:center; padding:30px; cursor:zoom-out; }
        #imgLightbox img { max-width:100%; max-height:100%; border-radius:8px; box-shadow:0 10px 40px rgba(0,0,0,.5); }
        #imgLightbox .lb-close { position:absolute; top:20px; right:28px; color:#fff; font-size:26px; cursor:pointer; }

        html[data-theme="dark"] .toc-card,
        html[data-theme="dark"] .guide-section { background:#1e222b !important; border-color:#2e333d !important; }
        html[data-theme="dark"] .toc-title,
        html[data-theme="dark"] .guide-section-title { color:#e4e6eb !important; }
        html[data-theme="dark"] .guide-section-desc { color:#9aa4b6 !important; }
        html[data-theme="dark"] .toc-link { background:#262b35 !important; color:#e4e6eb !important; }
        html[data-theme="dark"] .toc-link:hover { background:#2e333d !important; }
        html[data-theme="dark"] .guide-section-icon { background:#262b35 !important; }
        html[data-theme="dark"] .shot-card { background:#20242e !important; border-color:#2e333d !important; }
        html[data-theme="dark"] .shot-card img { border-color:#2e333d !important; }
        html[data-theme="dark"] .shot-title { color:#e4e6eb !important; }
        html[data-theme="dark"] .shot-text { color:#9aa4b6 !important; }
    </style>
</head>
<body>
<?php include('../includes/sidebar.php'); ?>

<div class="main-content">
    <div class="row justify-content-center">
        <div class="col-lg-9">

            <!-- ══ PAGE DE GARDE ══ -->
            <div class="cover-page">
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
            </div>

            <!-- ══ SOMMAIRE ══ -->
            <div class="toc-card">
                <div class="toc-title"><i class="fa fa-list-ol" style="color:var(--marine);"></i>Sommaire</div>
                <div class="toc-grid">
                    <?php foreach ($sections as $s): ?>
                        <a href="#<?= $s['id'] ?>" class="toc-link"><i class="fa <?= $s['icon'] ?>"></i><?= htmlspecialchars($s['title']) ?></a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- ══ SECTIONS ══ -->
            <?php foreach ($sections as $s): ?>
                <div class="guide-section" id="<?= $s['id'] ?>">
                    <div class="guide-section-header">
                        <div class="guide-section-icon"><i class="fa <?= $s['icon'] ?>"></i></div>
                        <h3 class="guide-section-title"><?= htmlspecialchars($s['title']) ?></h3>
                    </div>
                    <p class="guide-section-desc"><?= htmlspecialchars($s['desc']) ?></p>
                    <div class="shot-grid">
                        <?php foreach ($s['shots'] as [$img, $shotTitle, $shotText]): ?>
                            <div class="shot-card">
                                <img src="../uploads/<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($shotTitle) ?>" onclick="openLightbox(this.src)">
                                <div class="shot-caption">
                                    <div class="shot-title"><i class="fa fa-circle-info me-1" style="color:var(--marine);"></i><?= htmlspecialchars($shotTitle) ?></div>
                                    <p class="shot-text"><?= htmlspecialchars($shotText) ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- ══ COUVERTURE DE FIN ══ -->
            <div class="back-cover">
                <h2>Fin du guide</h2>
                <p>Vous avez maintenant une vue complète des fonctionnalités de BailManager.</p>
                <p>Pour toute question, l'équipe support reste à votre disposition.</p>
                <div class="mt-3">
                    <a href="tel:+2250749791287" class="bc-contact"><i class="fa fa-phone-alt"></i> (+225) 07 49 79 12 87</a>
                    <a href="mailto:kkjoss01@gmail.com" class="bc-contact"><i class="fa fa-envelope"></i> kkjoss01@gmail.com</a>
                </div>
                <p class="cover-meta mt-4" style="opacity:.6;font-size:.8rem;">BailManager — Version <?= htmlspecialchars(APP_VERSION) ?> · <?= htmlspecialchars($entreprise['nom_entreprise']) ?></p>
            </div>

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
        document.getElementById('imgLightboxSrc').src = src;
        document.getElementById('imgLightbox').style.display = 'flex';
    }
    function closeLightbox() {
        document.getElementById('imgLightbox').style.display = 'none';
    }
</script>
</body>
</html>
