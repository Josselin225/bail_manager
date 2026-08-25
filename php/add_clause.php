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

$titre           = trim($_POST['titre'] ?? '');
$contenu         = trim($_POST['contenu'] ?? '');
$ordre_affichage = intval($_POST['ordre_affichage'] ?? 0);
$actif           = isset($_POST['actif']) ? 1 : 0;

if ($titre === '' || $contenu === '') {
    flash('error', "Le titre et le contenu de la clause sont obligatoires.");
    header('Location: ../pages/settings.php');
    exit();
}

try {
    $stmt = $pdo->prepare(
        "INSERT INTO clauses_contrat (titre, contenu, ordre_affichage, actif) VALUES (?, ?, ?, ?)"
    );
    $stmt->execute([$titre, $contenu, $ordre_affichage, $actif]);
    insertLog($pdo, "Clause contrat", "Ajout de la clause : $titre");
    flash('success', "Clause ajoutée avec succès.");
} catch (Exception $e) {
    error_log("Erreur ajout clause : " . $e->getMessage());
    flash('error', "Une erreur technique est survenue.");
}
header('Location: ../pages/settings.php');
exit();
