<?php
session_start();
require_once('../config/db.php');
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non authentifié']);
    exit();
}

$contrat_id = $_GET['contrat_id'];
$mois_selectionne = $_GET['mois'];
$annee_selectionnee = $_GET['annee'];

$mois_liste = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
$index_selectionne = array_search($mois_selectionne, $mois_liste);

// Récupérer le loyer mensuel du contrat
$stmtLoyer = $pdo->prepare("SELECT loyer_mensuel FROM contrats WHERE id = ?");
$stmtLoyer->execute([$contrat_id]);
$loyer_fixe = $stmtLoyer->fetchColumn();

// On vérifie les mois de l'année en cours AVANT le mois sélectionné
for ($i = 0; $i < $index_selectionne; $i++) {
    $mois_a_verifier = $mois_liste[$i] . " " . $annee_selectionnee;
    
    $stmt = $pdo->prepare("SELECT SUM(montant_recu) FROM encaissements WHERE contrat_id = ? AND periode_concernee = ?");
    $stmt->execute([$contrat_id, $mois_a_verifier]);
    $total_paye = $stmt->fetchColumn() ?: 0;

    if ($total_paye < $loyer_fixe) {
        echo json_encode([
            'a_des_dettes' => true, 
            'mois_du' => $mois_a_verifier,
            'premier_mois_du' => $mois_liste[$i]
        ]);
        exit;
    }
}

echo json_encode(['a_des_dettes' => false]);