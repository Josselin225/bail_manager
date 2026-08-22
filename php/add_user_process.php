<?php
session_start();
require_once('../config/db.php');

// Vérification si l'utilisateur est bien admin (on utilise la clé 'role' par cohérence)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    // Vérifie si la session contient bien le rôle et si c'est admin
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        header('Location: ../pages/dashboard.php?msg=access_denied');
        exit();
    }

    $nom = $_POST['nom'];
    $email = $_POST['email'];
    $role = $_POST['role'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    try {
        $stmt = $pdo->prepare("INSERT INTO users (nom_complet, email, mot_de_passe, role) VALUES (?, ?, ?, ?)");
        $stmt->execute([$nom, $email, $password, $role]);
        header('Location: ../pages/gestion_users.php?success=1');
        insertLog($pdo, "Création Utilisateur", "Nouvel utilisateur créé : " . $nom);
        exit(); // Toujours ajouter exit après une redirection header
    } catch (Exception $e) {
        // Optionnel : rediriger avec un message d'erreur précis
        header('Location: ../pages/gestion_users.php?error=email_exists');
        exit();
    }
    
}