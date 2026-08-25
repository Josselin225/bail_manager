<?php
session_start();
require_once('../config/db.php');

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit();
}

$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 2) {
    echo json_encode(['bailleurs' => [], 'locataires' => [], 'maisons' => [], 'contrats' => []]);
    exit();
}
$like = '%' . $q . '%';

$stmtB = $pdo->prepare(
    "SELECT id, nom, code_bailleur, telephone1 FROM bailleurs
     WHERE nom LIKE ? OR code_bailleur LIKE ? OR telephone1 LIKE ?
     LIMIT 6"
);
$stmtB->execute([$like, $like, $like]);
$bailleurs = array_map(fn($r) => [
    'id' => $r['id'], 'label' => $r['nom'], 'sub' => $r['code_bailleur'] ?? $r['telephone1'] ?? '',
    'url' => 'voir_bailleur.php?id=' . $r['id'],
], $stmtB->fetchAll());

$stmtL = $pdo->prepare(
    "SELECT id, nom, telephone1 FROM locataires
     WHERE nom LIKE ? OR telephone1 LIKE ?
     LIMIT 6"
);
$stmtL->execute([$like, $like]);
$locataires = array_map(fn($r) => [
    'id' => $r['id'], 'label' => $r['nom'], 'sub' => $r['telephone1'] ?? '',
    'url' => 'locataires.php?search=' . urlencode($r['nom']),
], $stmtL->fetchAll());

$stmtM = $pdo->prepare(
    "SELECT id, designation, adresse FROM maisons
     WHERE designation LIKE ? OR adresse LIKE ?
     LIMIT 6"
);
$stmtM->execute([$like, $like]);
$maisons = array_map(fn($r) => [
    'id' => $r['id'], 'label' => $r['designation'], 'sub' => $r['adresse'] ?? '',
    'url' => 'maisons.php?search=' . urlencode($r['designation']),
], $stmtM->fetchAll());

$stmtC = $pdo->prepare(
    "SELECT c.id, l.nom AS locataire, m.designation AS maison
     FROM contrats c
     JOIN locataires l ON c.locataire_id = l.id
     JOIN maisons m ON c.maison_id = m.id
     WHERE l.nom LIKE ? OR m.designation LIKE ?
     LIMIT 6"
);
$stmtC->execute([$like, $like]);
$contrats = array_map(fn($r) => [
    'id' => $r['id'], 'label' => $r['maison'], 'sub' => $r['locataire'],
    'url' => 'recu_contrat.php?id=' . $r['id'],
], $stmtC->fetchAll());

echo json_encode([
    'bailleurs'   => $bailleurs,
    'locataires'  => $locataires,
    'maisons'     => $maisons,
    'contrats'    => $contrats,
]);
