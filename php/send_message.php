<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once('../config/db.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    $nom = htmlspecialchars($_POST['nom']);
    $email = htmlspecialchars($_POST['email']);
    $sujet = htmlspecialchars($_POST['sujet']);
    $message = htmlspecialchars($_POST['message']);

    try {
        $sql = "INSERT INTO messages (nom_visiteur, email_visiteur, sujet, contenu) VALUES (?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nom, $email, $sujet, $message]);

        insertLog($pdo, "Nouveau Message", "De : $nom ($email) — Sujet : $sujet");
        header("Location: ../pages/contact.php?sent=1");
        exit();
    } catch (PDOException $e) {
        error_log($e->getMessage());
        header('Location: ../pages/contact.php?sent=error');
        exit();
    }
}