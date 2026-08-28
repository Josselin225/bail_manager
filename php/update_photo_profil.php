<?php
session_start();
require_once('../config/db.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id'])) {
    header('Location: ../pages/login.php');
    exit();
}
csrf_validate();

$user_id = $_SESSION['user_id'];

if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== 0) {
    flash('error', "Veuillez choisir une image.");
    header('Location: ../pages/profil.php');
    exit();
}
if (uploadDepasseLimite($_FILES['photo'])) {
    flash('error', "Le fichier dépasse la taille maximale autorisée (8 Mo).");
    header('Location: ../pages/profil.php');
    exit();
}

$mime = mime_content_type($_FILES['photo']['tmp_name']);
$ext  = mimeToImageExt($mime);
if ($ext === null) {
    flash('error', "Format de fichier non autorisé.");
    header('Location: ../pages/profil.php');
    exit();
}

$upload_dir = "../uploads/users/";
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

$new_name = "user_" . $user_id . "_" . time() . "." . $ext;

if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_dir . $new_name)) {
    $stmt = $pdo->prepare("SELECT photo_profil FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $ancienne_photo = $stmt->fetchColumn();

    $pdo->prepare("UPDATE users SET photo_profil = ? WHERE id = ?")->execute([$new_name, $user_id]);

    if ($ancienne_photo && file_exists($upload_dir . $ancienne_photo)) {
        unlink($upload_dir . $ancienne_photo);
    }

    insertLog($pdo, "Profil", "Mise à jour de la photo de profil.");
    flash('success', "Photo de profil mise à jour.");
} else {
    error_log("Échec de l'enregistrement de la photo de profil : " . $new_name);
    flash('error', "Une erreur technique est survenue lors de l'envoi.");
}

header('Location: ../pages/profil.php');
exit();
