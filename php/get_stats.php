<?php
// On récupère la connexion si elle n'est pas déjà là
require_once(__DIR__ . '/../config/db.php');

// Compter les réservations en attente
$countRes = $pdo->query("SELECT COUNT(*) FROM reservations WHERE statut = 'en_attente'")->fetchColumn();

// Compter les messages non lus
$countMsg = $pdo->query("SELECT COUNT(*) FROM messages WHERE statut = 'non_lu'")->fetchColumn();
?>