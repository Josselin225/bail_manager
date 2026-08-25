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
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

if (!isset($_SESSION['login_attempts']))   $_SESSION['login_attempts'] = 0;
if (!isset($_SESSION['login_last_time']))  $_SESSION['login_last_time'] = time();

// Réinitialiser le compteur si la fenêtre de 10 min est écoulée
if (time() - $_SESSION['login_last_time'] > 600) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['login_last_time'] = time();
}

if ($_SESSION['login_attempts'] >= 5) {
    $wait = 600 - (time() - $_SESSION['login_last_time']);
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
    $_SESSION['login_attempts'] = 0;

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
    $_SESSION['login_attempts']++;
    $_SESSION['login_last_time'] = time();

    header('Location: ../pages/login.php?error=invalid');
    exit();
}
