<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once('../config/db.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: ../pages/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/caisse_entreprise.php');
    exit();
}

csrf_validate();

$motif       = trim($_POST['motif']       ?? '');
$montant     = (float)($_POST['montant']  ?? 0);
$commentaire = trim($_POST['commentaire'] ?? '');
$auteur      = $_SESSION['nom_complet']   ?? 'Administrateur';

if (!$motif || $montant <= 0) {
    flash('error', "Motif ou montant invalide.");
    header('Location: ../pages/caisse_entreprise.php');
    exit();
}

$description = $motif . ($commentaire ? ' — ' . $commentaire : '');

try {
    $stmt = $pdo->prepare(
        "INSERT INTO mouvements_caisse_entreprise
            (type_mouvement, montant, commentaire, effectue_par, date_operation)
         VALUES ('Retrait/Dépense', :montant, :commentaire, :auteur, NOW())"
    );
    $stmt->execute([
        ':montant'     => -$montant,
        ':commentaire' => $description,
        ':auteur'      => $auteur,
    ]);

    insertLog($pdo, 'Retrait caisse', "Motif: $motif — Montant: $montant FCFA");
    flash('success', "Retrait enregistré avec succès.");
    header('Location: ../pages/caisse_entreprise.php');
} catch (Exception $e) {
    error_log($e->getMessage());
    flash('error', "Une erreur est survenue. Veuillez réessayer.");
    header('Location: ../pages/caisse_entreprise.php');
}
exit();
