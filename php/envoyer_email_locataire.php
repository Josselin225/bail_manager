<?php
session_start();
require_once('../config/db.php');
require_once('../config/mailer.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: ../pages/login.php');
    exit();
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/locataires.php');
    exit();
}
csrf_validate();

$locataire_id = (int)($_POST['locataire_id'] ?? 0);
$sujet        = trim($_POST['sujet'] ?? '');
$contenu      = trim($_POST['contenu'] ?? '');

if ($locataire_id <= 0 || $sujet === '' || $contenu === '') {
    flash('error', "Merci de renseigner un sujet et un message.");
    header('Location: ../pages/locataires.php');
    exit();
}

$stmt = $pdo->prepare("SELECT nom, email FROM locataires WHERE id = ?");
$stmt->execute([$locataire_id]);
$locataire = $stmt->fetch();

if (!$locataire) {
    flash('error', "Locataire introuvable.");
    header('Location: ../pages/locataires.php');
    exit();
}
if (empty($locataire['email'])) {
    flash('error', "Ce locataire n'a pas d'adresse email enregistrée.");
    header('Location: ../pages/locataires.php');
    exit();
}

$sujet   = mb_substr($sujet, 0, 200);
$contenu = mb_substr($contenu, 0, 5000);

$settings      = $pdo->query("SELECT nom_entreprise FROM settings LIMIT 1")->fetch();
$nomEntreprise = $settings['nom_entreprise'] ?? 'BailManager';

$corps = "<p>Bonjour " . htmlspecialchars($locataire['nom']) . ",</p>"
       . "<p>" . nl2br(htmlspecialchars($contenu)) . "</p>"
       . "<p style='color:#888;font-size:12px;margin-top:20px;'>$nomEntreprise</p>";

if (sendMail($locataire['email'], $locataire['nom'], $sujet, $corps)) {
    insertLog($pdo, "Email Locataire", "Email envoyé au locataire #$locataire_id ({$locataire['email']}) — sujet : $sujet");
    flash('success', "Email envoyé à " . $locataire['nom'] . ".");
} else {
    flash('error', "Échec de l'envoi de l'email. Vérifiez la configuration SMTP dans .env.");
}

header('Location: ../pages/locataires.php');
exit();
