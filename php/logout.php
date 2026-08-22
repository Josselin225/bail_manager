<?php
session_start();
if (isset($_SESSION['user_id'])) {
    require_once('../config/db.php');
    insertLog($pdo, "Déconnexion", "Déconnexion de " . ($_SESSION['nom_complet'] ?? 'utilisateur'));
}
session_unset();
session_destroy();
header("Location: ../index.php"); // Redirige vers la page de connexion
exit();
?>