<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once('../config/db.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: ../pages/login.php');
    exit();
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/bailleurs.php');
    exit();
}
csrf_validate();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    flash('error', "Seul un administrateur peut modifier un mandat existant.");
    header('Location: ../pages/bailleurs.php');
    exit();
}

$mandat_id       = (int)($_POST['mandat_id'] ?? 0);
$date_signature  = $_POST['date_signature'] ?? '';
$date_debut      = $_POST['date_debut'] ?? '';
$date_fin        = trim($_POST['date_fin'] ?? '') ?: null;
$taux_commission = $_POST['taux_commission'] ?? '';

if ($mandat_id <= 0 || $date_signature === '' || $date_debut === '' || $taux_commission === '') {
    flash('error', "Veuillez renseigner la date de signature, la date de début et le taux de commission.");
    header('Location: ../pages/bailleurs.php');
    exit();
}

$stmtM = $pdo->prepare("SELECT mg.*, b.nom AS bailleur_nom FROM mandats_gestion mg JOIN bailleurs b ON b.id = mg.bailleur_id WHERE mg.id = ?");
$stmtM->execute([$mandat_id]);
$mandat = $stmtM->fetch();
if (!$mandat) {
    flash('error', "Mandat introuvable.");
    header('Location: ../pages/bailleurs.php');
    exit();
}

// Document signé (optionnel) : ne remplace l'existant que si un nouveau fichier est envoyé.
$document_signe = $mandat['document_signe'];
$ancienDocument = null;
if (isset($_FILES['document_signe']) && $_FILES['document_signe']['error'] === UPLOAD_ERR_OK) {
    if ($_FILES['document_signe']['size'] > 8 * 1024 * 1024) {
        flash('error', "Le document dépasse la taille maximale autorisée (8 Mo).");
        header('Location: ../pages/bailleurs.php');
        exit();
    }
    $allowedMimes = [
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
        'image/webp'      => 'webp',
        'application/pdf' => 'pdf',
    ];
    $mime = mime_content_type($_FILES['document_signe']['tmp_name']);
    $ext  = $allowedMimes[$mime] ?? null;
    if ($ext === null) {
        flash('error', "Format non autorisé pour le document signé. Formats acceptés : JPG, PNG, WEBP, PDF.");
        header('Location: ../pages/bailleurs.php');
        exit();
    }
    $uploadDir = "../uploads/mandats/";
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    $nouveauDocument = "mandat_{$mandat['bailleur_id']}_" . time() . "_" . uniqid() . "." . $ext;
    if (!move_uploaded_file($_FILES['document_signe']['tmp_name'], $uploadDir . $nouveauDocument)) {
        error_log("Échec de l'enregistrement du document de mandat : " . $nouveauDocument);
        flash('error', "Une erreur technique est survenue lors de l'envoi du document.");
        header('Location: ../pages/bailleurs.php');
        exit();
    }
    $ancienDocument = $document_signe;
    $document_signe = $nouveauDocument;
}

try {
    $pdo->prepare(
        "UPDATE mandats_gestion SET date_signature = ?, date_debut = ?, date_fin = ?, taux_commission = ?, document_signe = ? WHERE id = ?"
    )->execute([$date_signature, $date_debut, $date_fin, $taux_commission, $document_signe, $mandat_id]);

    if ($ancienDocument && is_file("../uploads/mandats/" . $ancienDocument)) {
        unlink("../uploads/mandats/" . $ancienDocument);
    }

    insertLog($pdo, "Mandat de gestion", "Mandat #$mandat_id modifié pour le bailleur \"{$mandat['bailleur_nom']}\" (#{$mandat['bailleur_id']})");
    flash('success', "Mandat de gestion modifié avec succès.");
} catch (PDOException $e) {
    error_log($e->getMessage());
    flash('error', "Une erreur est survenue lors de la modification du mandat.");
}

header('Location: ../pages/bailleurs.php');
exit();
