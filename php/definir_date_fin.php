<?php
session_start();
require_once('../config/db.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: ../pages/login.php');
    exit();
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/contrats.php');
    exit();
}
csrf_validate();

$contrat_id = (int)($_POST['contrat_id'] ?? 0);
$date_fin   = trim($_POST['date_fin'] ?? '');

$stmt = $pdo->prepare("SELECT id FROM contrats WHERE id = ? AND statut_contrat = 'actif'");
$stmt->execute([$contrat_id]);
if (!$stmt->fetch()) {
    flash('error', "Contrat introuvable ou déjà terminé.");
    header('Location: ../pages/contrats.php');
    exit();
}

if ($date_fin === '') {
    $pdo->prepare("UPDATE contrats SET date_fin = NULL WHERE id = ?")->execute([$contrat_id]);
    insertLog($pdo, "Échéance contrat", "Échéance retirée du contrat #$contrat_id");
    flash('success', "Échéance retirée du contrat.");
} else {
    $d = DateTime::createFromFormat('Y-m-d', $date_fin);
    if (!$d || $d->format('Y-m-d') !== $date_fin) {
        flash('error', "Date invalide.");
        header('Location: ../pages/contrats.php');
        exit();
    }
    $pdo->prepare("UPDATE contrats SET date_fin = ? WHERE id = ?")->execute([$date_fin, $contrat_id]);
    insertLog($pdo, "Échéance contrat", "Échéance du contrat #$contrat_id fixée au $date_fin");
    flash('success', "Échéance du contrat mise à jour au " . date('d/m/Y', strtotime($date_fin)) . ".");
}

header('Location: ../pages/contrats.php');
exit();
