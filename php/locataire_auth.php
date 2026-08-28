<?php
session_start();
require_once('../config/db.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/locataire_portail.php');
    exit();
}
csrf_validate();

$telephone  = trim($_POST['telephone'] ?? '');
$code_acces = trim($_POST['code_acces'] ?? '');

if (empty($telephone) || empty($code_acces)) {
    header('Location: ../pages/locataire_portail.php?error=champs');
    exit();
}

// ─── Anti brute-force : max 5 tentatives par IP sur 10 minutes ───────────────
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
if (rateLimitEstBloque($pdo, 'locataire', $ip) > 0) {
    header('Location: ../pages/locataire_portail.php?error=locked');
    exit();
}

// Recherche du locataire par téléphone
$stmt = $pdo->prepare(
    "SELECT l.id, l.nom, la.code_acces, la.actif
     FROM locataires l
     JOIN locataire_acces la ON la.locataire_id = l.id
     WHERE (l.telephone1 = ? OR l.telephone2 = ?) AND la.actif = 1"
);
$stmt->execute([$telephone, $telephone]);
$locataire = $stmt->fetch();

if ($locataire && password_verify($code_acces, $locataire['code_acces'])) {
    rateLimitReinitialiser($pdo, 'locataire', $ip);
    session_regenerate_id(true);

    $_SESSION['locataire_id']   = $locataire['id'];
    $_SESSION['locataire_nom']  = $locataire['nom'];
    $_SESSION['locataire_mode'] = true;
    $_SESSION['last_activity']  = time();

    header('Location: ../pages/locataire_espace.php');
} else {
    rateLimitEnregistrerEchec($pdo, 'locataire', $ip);
    header('Location: ../pages/locataire_portail.php?error=invalid');
}
exit();
