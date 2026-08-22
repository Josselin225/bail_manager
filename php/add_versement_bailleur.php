<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once('../config/db.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: ../pages/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['enregistrer_retrait'])) {
    header('Location: ../pages/compte_bailleur.php');
    exit();
}

csrf_validate();

$bailleur_id = (int)($_POST['bailleur_id'] ?? 0);
$montant     = abs((float)($_POST['montant'] ?? 0));
$commentaire = trim($_POST['commentaire'] ?? '');
$auteur      = $_SESSION['nom_complet'] ?? 'Administrateur';

if (!$bailleur_id || $montant <= 0) {
    flash('error', "Montant ou bailleur invalide.");
    header('Location: ../pages/compte_bailleur.php');
    exit();
}

try {
    $pdo->prepare(
        "INSERT INTO compte_courant_bailleur
            (bailleur_id, type_operation, montant, commentaire, date_operation)
         VALUES (?, 'versement_bailleur', ?, ?, NOW())"
    )->execute([$bailleur_id, -$montant, $commentaire ?: 'Versement au bailleur']);

    recalculerSoldeBailleur($pdo, $bailleur_id);

    insertLog($pdo, 'Retrait bailleur', "Bailleur ID: $bailleur_id — Montant: $montant FCFA");
    flash('success', "Retrait enregistré avec succès.");
    header("Location: ../pages/compte_bailleur.php?bailleur_id=$bailleur_id");
} catch (Exception $e) {
    error_log($e->getMessage());
    flash('error', "Une erreur est survenue. Veuillez réessayer.");
    header('Location: ../pages/compte_bailleur.php');
}
exit();