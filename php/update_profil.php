<?php
session_start();
require_once('../config/db.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id'])) {
    header('Location: ../pages/login.php');
    exit();
}
csrf_validate();

$user_id     = $_SESSION['user_id'];
$nom_complet = trim($_POST['nom_complet'] ?? '');
$email       = trim($_POST['email'] ?? '');
$sessionTimeoutMin = (int)($_POST['session_timeout_minutes'] ?? 10);

if ($nom_complet === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    flash('error', "Veuillez saisir un nom et un email valides.");
    header('Location: ../pages/profil.php');
    exit();
}

if ($sessionTimeoutMin < 5 || $sessionTimeoutMin > 120) {
    flash('error', "La durée d'inactivité doit être comprise entre 5 et 120 minutes.");
    header('Location: ../pages/profil.php');
    exit();
}

$stmtDup = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
$stmtDup->execute([$email, $user_id]);
if ($stmtDup->fetch()) {
    flash('error', "Cet email est déjà utilisé par un autre compte.");
    header('Location: ../pages/profil.php');
    exit();
}

try {
    $pdo->prepare("UPDATE users SET nom_complet = ?, email = ?, session_timeout_minutes = ? WHERE id = ?")
        ->execute([$nom_complet, $email, $sessionTimeoutMin, $user_id]);
    $_SESSION['nom_complet']     = $nom_complet;
    $_SESSION['session_timeout'] = $sessionTimeoutMin * 60;
    insertLog($pdo, "Profil", "Mise à jour des informations personnelles.");
    flash('success', "Profil mis à jour avec succès.");
} catch (Exception $e) {
    error_log("Erreur update profil : " . $e->getMessage());
    flash('error', "Une erreur technique est survenue.");
}
header('Location: ../pages/profil.php');
exit();
