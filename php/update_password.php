<?php
session_start();
require_once('../config/db.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    csrf_validate();
    $pass1 = $_POST['new_password'];
    $pass2 = $_POST['confirm_password'];
    $user_id = $_SESSION['user_id'];

    if ($pass1 !== $pass2) {
        header('Location: ' . $_SERVER['HTTP_REFERER'] . '?error=mismatch');
        exit();
    }

    // Hachage du nouveau mot de passe
    $new_hash = password_hash($pass1, PASSWORD_DEFAULT);

    try {
        $stmt = $pdo->prepare("UPDATE users SET mot_de_passe = ? WHERE id = ?");
        $stmt->execute([$new_hash, $user_id]);
        insertLog($pdo, "Sécurité", "Changement de mot de passe par l'utilisateur.");

        // Déconnexion immédiate
        session_destroy();
        
        // Redirection vers login avec un message de succès
        header('Location: ../pages/login.php?msg=password_changed');
        exit();

    } catch (Exception $e) {
        error_log($e->getMessage());
        header('Location: ../pages/login.php?error=update_failed');
        exit();
    }
}