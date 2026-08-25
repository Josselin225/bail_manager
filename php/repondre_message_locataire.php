<?php
session_start();
require_once('../config/db.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: ../pages/login.php');
    exit();
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/messages_locataires.php');
    exit();
}
csrf_validate();

$locataire_id = (int)($_POST['locataire_id'] ?? 0);
$contenu      = trim($_POST['contenu'] ?? '');

if ($locataire_id <= 0 || $contenu === '') {
    flash('error', "Message invalide.");
    header('Location: ../pages/messages_locataires.php');
    exit();
}

$stmt = $pdo->prepare("SELECT id FROM locataires WHERE id = ?");
$stmt->execute([$locataire_id]);
if (!$stmt->fetch()) {
    flash('error', "Locataire introuvable.");
    header('Location: ../pages/messages_locataires.php');
    exit();
}

$contenu = mb_substr($contenu, 0, 2000);

$pdo->prepare("INSERT INTO messages_locataires (locataire_id, expediteur, contenu) VALUES (?, 'agence', ?)")
    ->execute([$locataire_id, $contenu]);

insertLog($pdo, "Message Locataire", "Réponse envoyée au locataire #$locataire_id");
flash('success', "Réponse envoyée.");
header('Location: ../pages/messages_locataires.php?locataire_id=' . $locataire_id);
exit();
