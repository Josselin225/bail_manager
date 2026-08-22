<?php
/**
 * Génère ou regénère le code d'accès d'un locataire (admin only).
 * Appelé en POST depuis la page locataires.php.
 */
session_start();
require_once('../config/db.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/locataires.php');
    exit();
}
csrf_validate();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../pages/locataires.php?error=droits');
    exit();
}

$locataire_id = intval($_POST['locataire_id']);

// Générer un code à 8 caractères alphanumériques lisibles
$code = strtoupper(substr(bin2hex(random_bytes(5)), 0, 8));

try {
    $stmt = $pdo->prepare(
        "INSERT INTO locataire_acces (locataire_id, code_acces, actif)
         VALUES (?, ?, 1)
         ON DUPLICATE KEY UPDATE code_acces = VALUES(code_acces), actif = 1"
    );
    $stmt->execute([$locataire_id, $code]);

    insertLog($pdo, "Code accès locataire", "Code généré pour locataire #$locataire_id");
    header('Location: ../pages/locataires.php?code_genere=' . urlencode($code) . '&loc_id=' . $locataire_id);
} catch (Exception $e) {
    error_log("Erreur génération code : " . $e->getMessage());
    header('Location: ../pages/locataires.php?error=technique');
}
exit();
