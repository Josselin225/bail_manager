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

$id              = intval($_POST['id'] ?? 0);
$titre           = trim($_POST['titre'] ?? '');
$contenu         = trim($_POST['contenu'] ?? '');
$ordre_affichage = intval($_POST['ordre_affichage'] ?? 0);
$actif           = isset($_POST['actif']) ? 1 : 0;

if ($id <= 0 || $titre === '' || $contenu === '') {
    flash('error', "Le titre et le contenu de la clause sont obligatoires.");
    header('Location: ../pages/settings.php');
    exit();
}

try {
    $stmt = $pdo->prepare(
        "UPDATE clauses_mandat SET titre = ?, contenu = ?, ordre_affichage = ?, actif = ? WHERE id = ?"
    );
    $stmt->execute([$titre, $contenu, $ordre_affichage, $actif, $id]);
    insertLog($pdo, "Clause mandat", "Modification de la clause #$id : $titre");
    flash('success', "Clause mise à jour avec succès.");
} catch (Exception $e) {
    error_log("Erreur modification clause mandat : " . $e->getMessage());
    flash('error', "Une erreur technique est survenue.");
}
header('Location: ../pages/settings.php');
exit();
