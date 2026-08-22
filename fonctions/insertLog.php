<?php
    session_start();
    require_once('../config/db.php');
    function insertLog($pdo, $utilisateur_id, $action, $details) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $stmt = $pdo->prepare("INSERT INTO logs (utilisateur_id, action, details, ip_adresse) VALUES (?, ?, ?, ?)");
    $stmt->execute([$utilisateur_id, $action, $details, $ip]);
}

?>