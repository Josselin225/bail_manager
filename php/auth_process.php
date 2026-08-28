<?php
session_start();
require_once('../config/db.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/login.php');
    exit();
}

// ─── Validation CSRF ─────────────────────────────────────────────────────────
csrf_validate();

// ─── Protection brute force : max 5 tentatives par IP sur 10 minutes ─────────
// Persisté en base (table rate_limits), pas en session : un compteur en session
// se réinitialise dès qu'un script n'envoie pas de cookie, ce qui le rend inopérant.
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

$wait = rateLimitEstBloque($pdo, 'login', $ip);
if ($wait > 0) {
    header('Location: ../pages/login.php?error=locked&wait=' . $wait);
    exit();
}

// ─── Authentification ─────────────────────────────────────────────────────────
$email          = trim($_POST['email'] ?? '');
$password_saisi = $_POST['password'] ?? '';

$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if ($user && password_verify($password_saisi, $user['mot_de_passe'])) {

    // Succès : réinitialiser le compteur
    rateLimitReinitialiser($pdo, 'login', $ip);

    // Régénérer session (anti-fixation)
    session_regenerate_id(true);

    $_SESSION['user_id']    = $user['id'];
    $_SESSION['nom_complet'] = $user['nom_complet'];
    $_SESSION['role']       = $user['role'];
    $_SESSION['last_activity'] = time();
    $_SESSION['session_timeout'] = (int)($user['session_timeout_minutes'] ?? 10) * 60;

    $pdo->prepare("UPDATE users SET dernier_acces = NOW() WHERE id = ?")->execute([$user['id']]);
    insertLog($pdo, "Connexion", "Connexion réussie depuis $ip");

    header('Location: ' . ($_SESSION['role'] === 'admin' ? '../pages/dashboard.php' : '../pages/dashboard.php'));
    exit();

} else {

    // Échec : incrémenter le compteur
    rateLimitEnregistrerEchec($pdo, 'login', $ip);

    header('Location: ../pages/login.php?error=invalid');
    exit();
}
