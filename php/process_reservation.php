<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once('../config/db.php');

if (!isset($_SESSION['user_id'])) { header('Location: ../pages/login.php'); exit(); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/liste_reservations.php');
    exit();
}
csrf_validate();

$id     = isset($_POST['id'])     ? (int)$_POST['id']     : 0;
$action = isset($_POST['action']) ? trim($_POST['action']) : '';

if (!$id || !in_array($action, ['terminer','supprimer'])) {
    header('Location: ../pages/liste_reservations.php');
    exit();
}

try {
    if ($action === 'terminer') {
        $pdo->prepare("UPDATE reservations SET statut='termine' WHERE id=?")->execute([$id]);
        insertLog($pdo, "Réservation", "Réservation #$id marquée terminée");
    } elseif ($action === 'supprimer') {
        $pdo->prepare("DELETE FROM reservations WHERE id=?")->execute([$id]);
        insertLog($pdo, "Réservation", "Réservation #$id supprimée");
    }
    header('Location: ../pages/liste_reservations.php?msg=success');
} catch (Exception $e) {
    error_log($e->getMessage());
    header('Location: ../pages/liste_reservations.php?error=db');
}
exit();
