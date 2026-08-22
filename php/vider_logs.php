<?php
session_start();
require_once('../config/db.php');

// Sécurité : Seul un admin peut vider les logs
if ($_SESSION['role'] !== 'admin') {
    header('Location: ../pages/journal_activites.php?error=unauthorized');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    // On vide la table
    $pdo->exec("DELETE FROM logs");
    
    // On enregistre quand même qui a vidé le journal !
    $stmt = $pdo->prepare("INSERT INTO logs (utilisateur_id, action, details, ip_adresse) VALUES (?, ?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'], 'Nettoyage', 'Le journal d\'activités a été entièrement vidé.', $_SERVER['REMOTE_ADDR']]);

    header('Location: ../pages/journal_activites.php?success=cleared');
    exit();
}