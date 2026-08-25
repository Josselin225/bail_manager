<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once('../config/db.php');

if (isset($_GET['id']) && isset($_SESSION['user_id'])) {
    $id = (int)$_GET['id'];
    try {
        // IMPORTANT : Utilisez 'lu' entre guillemets pour le ENUM
        $stmt = $pdo->prepare("UPDATE messages SET statut = 'lu' WHERE id = :id");
        $stmt->execute([':id' => $id]);
        insertLog($pdo, "Message Lu", "Message #$id marqué comme lu");
        echo "success";
    } catch (PDOException $e) {
        echo "error";
    }
}
?>