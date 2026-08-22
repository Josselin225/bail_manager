<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once('../config/db.php');

if (!isset($_SESSION['user_id'])) { header('Location: ../pages/login.php'); exit(); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ../pages/maisons.php'); exit(); }

csrf_validate();

if (true) {
    // 1. Récupération des textes
    $designation = $_POST['designation'];
    $type_maison = $_POST['type_maison'];
    $bailleur_id = $_POST['bailleur_id'];
    $adresse     = $_POST['adresse'];
    $loyer       = $_POST['loyer'];
    $condition   = $_POST['condition'] ?? 1;
    $statut      = $_POST['statut'] ?? 'disponible';
    $description = $_POST['description'] ?? '';

    // 2. Gestion des images
    $upload_dir = "../uploads/maisons/";
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $image_names = ['image1' => null, 'image2' => null, 'image3' => null];
    $input_names = ['img1' => 'image1', 'img2' => 'image2', 'img3' => 'image3'];
    $allowed_mime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    foreach ($input_names as $input => $col) {
        if (isset($_FILES[$input]) && $_FILES[$input]['error'] == 0) {
            $mime = mime_content_type($_FILES[$input]['tmp_name']);
            if (!in_array($mime, $allowed_mime)) {
                flash('error', "Format de fichier non autorisé.");
                header("Location: ../pages/maisons.php");
                exit();
            }
            $extension = strtolower(pathinfo($_FILES[$input]['name'], PATHINFO_EXTENSION));
            $new_name = "maison_" . time() . "_" . uniqid() . "." . $extension;

            if (move_uploaded_file($_FILES[$input]['tmp_name'], $upload_dir . $new_name)) {
                $image_names[$col] = $new_name;
            }
        }
    }

    // ... (haut du fichier identique pour la récupération des variables)

try {
    // Correction ici : changement de 'condition_bail' par 'condition'
    $sql = "INSERT INTO maisons (
                bailleur_id, designation, type_maison, adresse, 
                description, statut, `condition`, loyer, 
                image1, image2, image3
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $pdo->prepare($sql);
    
    $stmt->execute([
        $bailleur_id, 
        $designation, 
        $type_maison, 
        $adresse, 
        $description, 
        $statut, 
        $condition, // La variable récupérée depuis $_POST['condition']
        $loyer,
        $image_names['image1'],
        $image_names['image2'],
        $image_names['image3']
    ]);

    insertLog($pdo, "Création Maison", "Maison créée : $designation");
    flash('success', "Maison enregistrée avec succès.");
    header("Location: ../pages/maisons.php");
} catch (PDOException $e) {
    error_log($e->getMessage());
    flash('error', "Une erreur est survenue.");
    header('Location: ../pages/maisons.php');
    exit();
}
}