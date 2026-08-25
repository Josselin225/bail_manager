<?php
session_start();
require_once('../config/db.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "error";
    exit();
}
csrf_validate();

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo "error";
    exit();
}

if (isset($_POST['id'])) {
    $id = intval($_POST['id']);
    try {
        $stmt = $pdo->prepare("DELETE FROM messages WHERE id = ?");
        $stmt->execute([$id]);
        insertLog($pdo, "Suppression Message", "Message #$id supprimé");
        echo "success";
        exit();
    } catch (Exception $e) {
        echo "error";
        exit();
    }
}
echo "error";
