<?php
require_once('../config/db.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    csrf_validate();
    $email = $_POST['email'];
    
    // 1. Vérifier si l'email existe
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        // 2. Générer un token unique
        $token = bin2hex(random_bytes(32));
        $expire = date("Y-m-d H:i:s", strtotime('+1 hour')); // Expire dans 1h

        // 3. Sauvegarder en base
        $update = $pdo->prepare("UPDATE users SET reset_token = ?, token_expire = ? WHERE email = ?");
        $update->execute([$token, $expire, $email]);

        insertLog($pdo, "Sécurité", "Demande de réinitialisation de mot de passe pour $email");

        // 4. En local (Laragon), on ne peut pas envoyer de mail facilement
        // On va rediriger vers une page de test qui affiche le lien
        header("Location: ../pages/reset_password_step2.php?token=" . $token);
        exit();
    } else {
        header("Location: ../pages/forgot_password.php?error=not_found");
    }
}