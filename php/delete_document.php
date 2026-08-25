<?php
session_start();
require_once('../config/db.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: ../pages/login.php');
    exit();
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/documents.php');
    exit();
}
csrf_validate();

$id = (int)($_POST['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM documents WHERE id = ?");
$stmt->execute([$id]);
$doc = $stmt->fetch();

$redirect = $doc ? "../pages/documents.php?type=" . urlencode($doc['entity_type']) . "&id=" . $doc['entity_id'] : "../pages/documents.php";

if (!$doc) {
    flash('error', "Document introuvable.");
    header('Location: ' . $redirect);
    exit();
}

$path = "../uploads/documents/" . $doc['nom_fichier'];
if (file_exists($path)) {
    unlink($path);
}
$pdo->prepare("DELETE FROM documents WHERE id = ?")->execute([$id]);

insertLog($pdo, "Document supprimé", "Fichier \"{$doc['nom_original']}\" supprimé de {$doc['entity_type']} #{$doc['entity_id']}");
flash('success', "Document supprimé.");
header('Location: ' . $redirect);
exit();
