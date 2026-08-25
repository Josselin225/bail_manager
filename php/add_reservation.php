<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once('../config/db.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    $maison_id = $_POST['maison_id'];
    // Stocké tel quel (non échappé) : l'échappement se fait à l'affichage (HTML) selon
    // le contexte, pas à l'enregistrement — sinon les entités survivent au JSON/JS et
    // sont ré-interprétées comme du HTML actif une fois décodées côté navigateur.
    $nom = trim($_POST['nom_visiteur']);
    $tel = trim($_POST['tel_visiteur']);
    $date_visite = $_POST['date_visite'];

    try {
        $sql = "INSERT INTO reservations (maison_id, nom_visiteur, tel_visiteur, date_visite)
                VALUES (?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$maison_id, $nom, $tel, $date_visite]);

        insertLog($pdo, "Nouvelle Réservation", "Visiteur : $nom — Maison #$maison_id");
        // Redirection avec un message de succès
        header("Location: ../pages/nos_maisons.php?res=success");
        exit();
    } catch (PDOException $e) {
        error_log($e->getMessage());
        header('Location: ../pages/nos_maisons.php?res=error');
        exit();
    }
}