<?php
session_start();
require_once('../config/db.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();

    $token   = $_POST['reset_token'] ?? '';
    $pass    = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($token === '') {
        header('Location: ../pages/reset_password_step2.php?error=invalid');
        exit();
    }

    if ($pass !== $confirm || strlen($pass) < 6) {
        header('Location: ../pages/reset_password_step2.php?token=' . urlencode($token) . '&error=mismatch');
        exit();
    }

    // Le token doit exister ET ne pas être expiré — vérifié ici même (pas seulement
    // à l'affichage du formulaire), sinon un token périmé reste utilisable indéfiniment.
    $stmtUser = $pdo->prepare("SELECT id, email FROM users WHERE reset_token = ? AND token_expire > NOW()");
    $stmtUser->execute([$token]);
    $userConcerne = $stmtUser->fetch();

    if (!$userConcerne) {
        header('Location: ../pages/reset_password_step2.php?error=expired');
        exit();
    }

    $hash = password_hash($pass, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("UPDATE users SET mot_de_passe = ?, reset_token = NULL, token_expire = NULL WHERE id = ? AND reset_token = ? AND token_expire > NOW()");
    $result = $stmt->execute([$hash, $userConcerne['id'], $token]);

    if ($result && $stmt->rowCount() === 1) {
        insertLog($pdo, "Sécurité", "Mot de passe réinitialisé pour " . $userConcerne['email']);
        header('Location: ../pages/login.php?msg=reset_success');
        exit();
    } else {
        header('Location: ../pages/reset_password_step2.php?token=' . urlencode($token) . '&error=update_failed');
        exit();
    }
}
