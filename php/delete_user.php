<?php
session_start();
require_once('../config/db.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/gestion_users.php');
    exit();
}
csrf_validate();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../pages/dashboard.php?error=unauthorized');
    exit();
}

if (isset($_POST['id']) && !empty($_POST['id'])) {
    $id_to_delete    = intval($_POST['id']);
    $current_admin_id = $_SESSION['user_id'];

    if ($id_to_delete === $current_admin_id) {
        header('Location: ../pages/gestion_users.php?error=self_deletion');
        exit();
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $result = $stmt->execute([$id_to_delete]);
        insertLog($pdo, "Suppression Utilisateur", "ID supprimé : " . $id_to_delete);

        header($result
            ? 'Location: ../pages/gestion_users.php?success=deleted'
            : 'Location: ../pages/gestion_users.php?error=not_found'
        );
    } catch (Exception $e) {
        header('Location: ../pages/gestion_users.php?error=integrity_constraint');
    }
} else {
    header('Location: ../pages/gestion_users.php');
}
exit();
