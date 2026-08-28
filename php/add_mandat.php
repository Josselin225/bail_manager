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

$bailleur_id     = (int)($_POST['bailleur_id'] ?? 0);
$date_signature  = $_POST['date_signature'] ?? '';
$date_debut      = $_POST['date_debut'] ?? '';
$date_fin        = trim($_POST['date_fin'] ?? '') ?: null;
$taux_commission = $_POST['taux_commission'] ?? '';

if ($bailleur_id <= 0 || $date_signature === '' || $date_debut === '' || $taux_commission === '') {
    flash('error', "Veuillez renseigner la date de signature, la date de début et le taux de commission.");
    header('Location: ../pages/bailleurs.php');
    exit();
}

$stmtB = $pdo->prepare("SELECT nom FROM bailleurs WHERE id = ?");
$stmtB->execute([$bailleur_id]);
$bailleur = $stmtB->fetch();
if (!$bailleur) {
    flash('error', "Bailleur introuvable.");
    header('Location: ../pages/bailleurs.php');
    exit();
}

// Document signé (optionnel) : même validation que le module Documents (jpg/png/webp/pdf, 8 Mo max)
$document_signe = null;
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
    $document_signe = "mandat_{$bailleur_id}_" . time() . "_" . uniqid() . "." . $ext;
    if (!move_uploaded_file($_FILES['document_signe']['tmp_name'], $uploadDir . $document_signe)) {
        error_log("Échec de l'enregistrement du document de mandat : " . $document_signe);
        flash('error', "Une erreur technique est survenue lors de l'envoi du document.");
        header('Location: ../pages/bailleurs.php');
        exit();
    }
}

try {
    $pdo->beginTransaction();

    // Un seul mandat actif à la fois par bailleur : tout mandat actif existant
    // est marqué résilié lorsqu'un nouveau mandat est enregistré (renouvellement).
    $pdo->prepare("UPDATE mandats_gestion SET statut = 'resilie' WHERE bailleur_id = ? AND statut = 'actif'")
        ->execute([$bailleur_id]);

    $pdo->prepare(
        "INSERT INTO mandats_gestion (bailleur_id, date_signature, date_debut, date_fin, taux_commission, document_signe)
         VALUES (?, ?, ?, ?, ?, ?)"
    )->execute([$bailleur_id, $date_signature, $date_debut, $date_fin, $taux_commission, $document_signe]);

    $mandatId = $pdo->lastInsertId();
    $pdo->commit();

    insertLog($pdo, "Mandat de gestion", "Mandat #$mandatId enregistré pour le bailleur \"{$bailleur['nom']}\" (#$bailleur_id)");
    flash('success', "Mandat de gestion enregistré avec succès.");
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log($e->getMessage());
    flash('error', "Une erreur est survenue lors de l'enregistrement du mandat.");
}

header('Location: ../pages/bailleurs.php');
exit();
