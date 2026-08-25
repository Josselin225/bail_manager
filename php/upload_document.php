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

$entityType = $_POST['entity_type'] ?? '';
$entityId   = (int)($_POST['entity_id'] ?? 0);
$redirect   = "../pages/documents.php?type=" . urlencode($entityType) . "&id=" . $entityId;

$allowedTypes = ['bailleur', 'locataire', 'contrat', 'maison'];
if (!in_array($entityType, $allowedTypes, true) || $entityId <= 0) {
    flash('error', "Élément invalide.");
    header('Location: ../pages/documents.php');
    exit();
}

if (!isset($_FILES['document']) || $_FILES['document']['error'] !== 0) {
    flash('error', "Veuillez choisir un fichier.");
    header('Location: ' . $redirect);
    exit();
}

if ($_FILES['document']['size'] > 8 * 1024 * 1024) {
    flash('error', "Le fichier dépasse la taille maximale autorisée (8 Mo).");
    header('Location: ' . $redirect);
    exit();
}

$allowedMimes = [
    'image/jpeg'      => 'jpg',
    'image/png'       => 'png',
    'image/webp'      => 'webp',
    'application/pdf' => 'pdf',
];
$mime = mime_content_type($_FILES['document']['tmp_name']);
$ext  = $allowedMimes[$mime] ?? null;
if ($ext === null) {
    flash('error', "Format non autorisé. Formats acceptés : JPG, PNG, WEBP, PDF.");
    header('Location: ' . $redirect);
    exit();
}

$uploadDir = "../uploads/documents/";
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$newName = "doc_{$entityType}_{$entityId}_" . time() . "_" . uniqid() . "." . $ext;

if (move_uploaded_file($_FILES['document']['tmp_name'], $uploadDir . $newName)) {
    $nomOriginal = mb_substr(basename($_FILES['document']['name']), 0, 255);
    $pdo->prepare(
        "INSERT INTO documents (entity_type, entity_id, nom_original, nom_fichier, type_mime, taille, uploaded_by)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    )->execute([$entityType, $entityId, $nomOriginal, $newName, $mime, $_FILES['document']['size'], $_SESSION['user_id']]);

    insertLog($pdo, "Document ajouté", "Fichier \"$nomOriginal\" ajouté à $entityType #$entityId");
    flash('success', "Document ajouté avec succès.");
} else {
    error_log("Échec de l'enregistrement du document : " . $newName);
    flash('error', "Une erreur technique est survenue lors de l'envoi.");
}

header('Location: ' . $redirect);
exit();
