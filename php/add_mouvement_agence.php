<?php
session_start();
require_once('../config/db.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    $type = $_POST['type_mouvement'];
    $montant = floatval($_POST['montant']);
    $commentaire = $_POST['commentaire'];

    try {
        // Insertion selon votre structure HeidiSQL
        $sql = "INSERT INTO mouvements_caisse_entreprise (type_mouvement, montant, commentaire, date_operation) 
                VALUES (?, ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$type, $montant, $commentaire]);

        insertLog($pdo, "Mouvement Caisse", "$type : $montant FCFA — $commentaire");
        flash('success', "Mouvement enregistré avec succès.");
        header('Location: ../pages/caisse_entreprise.php');
    } catch (Exception $e) {
        error_log($e->getMessage());
        flash('error', "Une erreur est survenue.");
        header('Location: ../pages/caisse_entreprise.php');
        exit();
    }
}