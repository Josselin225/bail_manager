<?php
session_start();
require_once('../config/db.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    $nom = $_POST['nom'];
    $tel1 = $_POST['telephone1'];
    $tel2 = $_POST['telephone2'];
    $piece = $_POST['piece_identite'];

    $target_dir = "../uploads/locataires/";
    if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);

    $files = ['photo_locataire' => '', 'photo_cni_recto' => '', 'photo_cni_verso' => ''];
    $allowed_mime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    foreach ($files as $key => $value) {
        if (!empty($_FILES[$key]['name']) && $_FILES[$key]['error'] === 0) {
            $mime = mime_content_type($_FILES[$key]['tmp_name']);
            if (!in_array($mime, $allowed_mime)) {
                header('Location: ../pages/locataires.php?error=filetype');
                exit();
            }
            $ext = strtolower(pathinfo($_FILES[$key]['name'], PATHINFO_EXTENSION));
            $filename = strtoupper($key) . "_" . time() . "_" . rand(100, 999) . "." . $ext;
            if (move_uploaded_file($_FILES[$key]['tmp_name'], $target_dir . $filename)) {
                $files[$key] = $filename;
            }
        }
    }

    $sql = "INSERT INTO locataires (nom, telephone1, telephone2, piece_identite, photo_locataire, photo_cni_recto, photo_cni_verso) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $nom, $tel1, $tel2, $piece,
            $files['photo_locataire'], $files['photo_cni_recto'], $files['photo_cni_verso']
        ]);
        insertLog($pdo, "Création Locataire", "Locataire créé : $nom");
        header('Location: ../pages/locataires.php?success=1');
    } catch (PDOException $e) {
        error_log($e->getMessage());
        header('Location: ../pages/locataires.php?error=db');
        exit();
    }
}