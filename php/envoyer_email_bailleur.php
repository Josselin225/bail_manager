<?php
session_start();
require_once('../config/db.php');
require_once('../config/mailer.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: ../pages/login.php');
    exit();
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/bailleurs.php');
    exit();
}
csrf_validate();

$bailleur_id = (int)($_POST['bailleur_id'] ?? 0);
$sujet       = trim($_POST['sujet'] ?? '');
$contenu     = trim($_POST['contenu'] ?? '');

if ($bailleur_id <= 0 || $sujet === '' || $contenu === '') {
    flash('error', "Merci de renseigner un sujet et un message.");
    header('Location: ../pages/bailleurs.php');
    exit();
}

$stmt = $pdo->prepare("SELECT nom, email FROM bailleurs WHERE id = ?");
$stmt->execute([$bailleur_id]);
$bailleur = $stmt->fetch();

if (!$bailleur) {
    flash('error', "Bailleur introuvable.");
    header('Location: ../pages/bailleurs.php');
    exit();
}
if (empty($bailleur['email'])) {
    flash('error', "Ce bailleur n'a pas d'adresse email enregistrée.");
    header('Location: ../pages/bailleurs.php');
    exit();
}

$sujet   = mb_substr($sujet, 0, 200);
$contenu = mb_substr($contenu, 0, 5000);

$settings      = $pdo->query("SELECT nom_entreprise FROM settings LIMIT 1")->fetch();
$nomEntreprise = $settings['nom_entreprise'] ?? 'BailManager';

$corps = "<p>Bonjour " . htmlspecialchars($bailleur['nom']) . ",</p>"
       . "<p>" . nl2br(htmlspecialchars($contenu)) . "</p>"
       . "<p style='color:#888;font-size:12px;margin-top:20px;'>$nomEntreprise</p>";

if (sendMail($bailleur['email'], $bailleur['nom'], $sujet, $corps)) {
    insertLog($pdo, "Email Bailleur", "Email envoyé au bailleur #$bailleur_id ({$bailleur['email']}) — sujet : $sujet");
    flash('success', "Email envoyé à " . $bailleur['nom'] . ".");
} else {
    flash('error', "Échec de l'envoi de l'email. Vérifiez la configuration SMTP dans .env.");
}

header('Location: ../pages/bailleurs.php');
exit();
