<?php
session_start();
require_once('../config/db.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/charges_locatives.php');
    exit();
}
csrf_validate();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../pages/login.php');
    exit();
}

$contrat_id  = intval($_POST['contrat_id']);
$type_charge = trim($_POST['type_charge']);
$montant     = floatval($_POST['montant']);
$date_charge = $_POST['date_charge'];
$description = trim($_POST['description'] ?? '');

if ($montant <= 0 || empty($type_charge) || empty($date_charge)) {
    flash('error', "Veuillez remplir tous les champs obligatoires.");
    header('Location: ../pages/charges_locatives.php');
    exit();
}

try {
    $stmt = $pdo->prepare(
        "INSERT INTO charges_locatives (contrat_id, type_charge, montant, date_charge, description)
         VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->execute([$contrat_id, $type_charge, $montant, $date_charge, $description]);
    insertLog($pdo, "Charge locative", "Ajout $type_charge : $montant FCFA — Contrat #$contrat_id");
    flash('success', "Charge enregistrée avec succès.");
    header('Location: ../pages/charges_locatives.php');
} catch (Exception $e) {
    error_log("Erreur charge : " . $e->getMessage());
    flash('error', "Une erreur technique est survenue.");
    header('Location: ../pages/charges_locatives.php');
}
exit();
