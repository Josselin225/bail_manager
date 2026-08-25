<?php
session_start();
require_once('../config/db.php');

if (empty($_SESSION['locataire_mode']) || empty($_SESSION['locataire_id'])) {
    header('Location: ../pages/locataire_portail.php');
    exit();
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/locataire_espace.php');
    exit();
}
csrf_validate();

$locataire_id = (int)$_SESSION['locataire_id'];
$contenu = trim($_POST['contenu'] ?? '');

if ($contenu === '') {
    flash('error', "Le message ne peut pas être vide.");
    header('Location: ../pages/locataire_espace.php#messages');
    exit();
}

$contenu = mb_substr($contenu, 0, 2000);

$pdo->prepare("INSERT INTO messages_locataires (locataire_id, expediteur, contenu) VALUES (?, 'locataire', ?)")
    ->execute([$locataire_id, $contenu]);

flash('success', "Message envoyé à l'agence.");
header('Location: ../pages/locataire_espace.php#messages');
exit();
