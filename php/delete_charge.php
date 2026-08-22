<?php
session_start();
require_once('../config/db.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/charges_locatives.php');
    exit();
}
csrf_validate();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    flash('error', "Droits insuffisants.");
    header('Location: ../pages/charges_locatives.php');
    exit();
}

$id = intval($_POST['id']);
try {
    $pdo->prepare("DELETE FROM charges_locatives WHERE id = ?")->execute([$id]);
    insertLog($pdo, "Suppression Charge", "Charge #$id supprimée");
    flash('success', "Charge supprimée.");
    header('Location: ../pages/charges_locatives.php');
} catch (Exception $e) {
    flash('error', "Une erreur technique est survenue.");
    header('Location: ../pages/charges_locatives.php');
}
exit();
