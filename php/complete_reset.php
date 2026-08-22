<?php
require_once('../config/db.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'];
    $pass = $_POST['password'];
    $confirm = $_POST['confirm_password'];

    if ($pass !== $confirm) {
        header('Location: ../pages/reset_password.php?token=' . urlencode($token) . '&error=mismatch');
        exit();
    }

    // 1. On hache le nouveau mot de passe
    $hash = password_hash($pass, PASSWORD_DEFAULT);

    // Récupérer l'utilisateur concerné avant d'invalider le token, pour le journal
    $stmtUser = $pdo->prepare("SELECT id, email FROM users WHERE reset_token = ?");
    $stmtUser->execute([$token]);
    $userConcerne = $stmtUser->fetch();

    // 2. On met à jour l'utilisateur et on vide le token
    $stmt = $pdo->prepare("UPDATE users SET mot_de_passe = ?, reset_token = NULL, token_expire = NULL WHERE reset_token = ?");
    $result = $stmt->execute([$hash, $token]);

    if ($result) {
        if ($userConcerne) {
            insertLog($pdo, "Sécurité", "Mot de passe réinitialisé pour " . $userConcerne['email']);
        }
        header('Location: ../pages/login.php?msg=reset_success');
        exit();
    } else {
        header('Location: ../pages/reset_password.php?token=' . urlencode($token) . '&error=update_failed');
        exit();
    }
}