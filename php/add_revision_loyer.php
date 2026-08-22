<?php
session_start();
require_once('../config/db.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/contrats.php');
    exit();
}
csrf_validate();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    flash('error', "Droits insuffisants.");
    header('Location: ../pages/contrats.php');
    exit();
}

$contrat_id    = intval($_POST['contrat_id']);
$nouveau_loyer = floatval($_POST['nouveau_loyer']);
$motif         = trim($_POST['motif'] ?? '');

if ($nouveau_loyer <= 0) {
    flash('error', "Montant invalide.");
    header('Location: ../pages/revisions_loyer.php?contrat_id=' . $contrat_id);
    exit();
}

try {
    $pdo->beginTransaction();

    // Récupérer l'ancien loyer
    $stmt = $pdo->prepare("SELECT loyer_mensuel FROM contrats WHERE id = ?");
    $stmt->execute([$contrat_id]);
    $contrat = $stmt->fetch();

    if (!$contrat) {
        $pdo->rollBack();
        flash('error', "Contrat introuvable.");
        header('Location: ../pages/contrats.php');
        exit();
    }

    $ancien_loyer = floatval($contrat['loyer_mensuel']);

    // Enregistrer la révision dans l'historique
    $stmtRev = $pdo->prepare(
        "INSERT INTO revisions_loyer (contrat_id, ancien_loyer, nouveau_loyer, motif, effectue_par)
         VALUES (?, ?, ?, ?, ?)"
    );
    $stmtRev->execute([$contrat_id, $ancien_loyer, $nouveau_loyer, $motif, $_SESSION['user_id']]);

    // Mettre à jour le loyer dans le contrat
    $stmtUpd = $pdo->prepare("UPDATE contrats SET loyer_mensuel = ? WHERE id = ?");
    $stmtUpd->execute([$nouveau_loyer, $contrat_id]);

    insertLog($pdo, "Révision loyer", "Contrat #$contrat_id : $ancien_loyer → $nouveau_loyer FCFA. Motif : $motif");

    $pdo->commit();
    flash('success', "Révision enregistrée.");
    header('Location: ../pages/revisions_loyer.php?contrat_id=' . $contrat_id);
    exit();

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Erreur révision loyer : " . $e->getMessage());
    flash('error', "Une erreur technique est survenue.");
    header('Location: ../pages/revisions_loyer.php?contrat_id=' . $contrat_id);
    exit();
}
