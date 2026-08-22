<?php
// Désactiver l'affichage des erreurs PHP à l'écran pour ne pas corrompre le JSON
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once('../config/db.php');

// On définit le header immédiatement
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'deja_paye' => 0, 'error' => 'Non authentifié']);
    exit();
}

try {
    $id = isset($_GET['contrat_id']) ? intval($_GET['contrat_id']) : 0;
    $periode = isset($_GET['periode']) ? $_GET['periode'] : '';

    if ($id > 0 && !empty($periode)) {
        $stmt = $pdo->prepare("SELECT SUM(montant_recu) FROM encaissements WHERE contrat_id = ? AND periode_concernee = ?");
        $stmt->execute([$id, $periode]);
        $deja_paye = $stmt->fetchColumn() ?: 0;

        echo json_encode(['success' => true, 'deja_paye' => floatval($deja_paye)]);
    } else {
        echo json_encode(['success' => false, 'deja_paye' => 0, 'error' => 'Paramètres manquants']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'deja_paye' => 0, 'error' => $e->getMessage()]);
}