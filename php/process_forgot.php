<?php
session_start();
require_once('../config/db.php');
require_once('../config/mailer.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    csrf_validate();

    // Anti-spam / anti-enumération : limite le nombre de demandes par IP sur une
    // fenêtre de 10 minutes, persisté en base (pas en session — voir auth_process.php).
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if (rateLimitEstBloque($pdo, 'forgot', $ip) > 0) {
        header("Location: ../pages/forgot_password.php?error=too_many");
        exit();
    }
    rateLimitEnregistrerEchec($pdo, 'forgot', $ip);

    $email = trim($_POST['email']);

    $stmt = $pdo->prepare("SELECT id, nom_complet, email FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // Le token est généré et l'email envoyé UNIQUEMENT si le compte existe, mais la
    // réponse à l'utilisateur est strictement identique dans les deux cas, pour ne
    // pas révéler quels emails ont un compte (énumération).
    if ($user) {
        $token  = bin2hex(random_bytes(32));
        $expire = date("Y-m-d H:i:s", strtotime('+1 hour'));

        $update = $pdo->prepare("UPDATE users SET reset_token = ?, token_expire = ? WHERE id = ?");
        $update->execute([$token, $expire, $user['id']]);

        insertLog($pdo, "Sécurité", "Demande de réinitialisation de mot de passe pour " . $user['email']);

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $lien   = "$scheme://$host/bail_manager/pages/reset_password_step2.php?token=" . $token;

        $sujet = "Réinitialisation de votre mot de passe — BailManager";
        $corps = "<p>Bonjour " . htmlspecialchars($user['nom_complet']) . ",</p>"
               . "<p>Une demande de réinitialisation de mot de passe a été effectuée pour votre compte.</p>"
               . "<p><a href=\"" . htmlspecialchars($lien) . "\">Cliquez ici pour choisir un nouveau mot de passe</a></p>"
               . "<p>Ce lien expire dans 1 heure. Si vous n'êtes pas à l'origine de cette demande, ignorez cet email.</p>";

        $envoye = sendMail($user['email'], $user['nom_complet'], $sujet, $corps);

        if (!$envoye) {
            // Envoi de mail non configuré ou en échec (ex : environnement local sans SMTP) :
            // le lien est journalisé côté serveur pour le développeur, jamais renvoyé au navigateur.
            error_log("Lien de réinitialisation (email non envoyé) pour {$user['email']} : $lien");
        }
    }

    header("Location: ../pages/forgot_password.php?sent=1");
    exit();
}
