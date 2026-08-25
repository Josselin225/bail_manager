<?php
session_start();
require_once('../config/db.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/settings.php');
    exit();
}
csrf_validate();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    flash('error', "Droits insuffisants.");
    header('Location: ../pages/settings.php');
    exit();
}

$id = intval($_POST['id'] ?? 0);
try {
    $pdo->prepare("DELETE FROM clauses_contrat WHERE id = ?")->execute([$id]);
    insertLog($pdo, "Suppression Clause", "Clause #$id supprimée");
    flash('success', "Clause supprimée.");
} catch (Exception $e) {
    error_log("Erreur suppression clause : " . $e->getMessage());
    flash('error', "Une erreur technique est survenue.");
}
header('Location: ../pages/settings.php');
exit();
