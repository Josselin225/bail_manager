<?php
session_start();
require_once('../config/db.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    $nom = trim($_POST['nom']);
    $annee = date('Y');
    $initiales = strtoupper(mb_substr($nom, 0, 2));

    // Gestion de la photo (nom par défaut si vide)
    $photo_name = "default.png";
    $allowed_mime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
        $mime = mime_content_type($_FILES['photo']['tmp_name']);
        if (!in_array($mime, $allowed_mime)) {
            flash('error', "Format de fichier non autorisé.");
            header("Location: ../pages/bailleurs.php");
            exit();
        }
        $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
        $new_name = "BA_" . time() . "." . strtolower($ext);
        if (move_uploaded_file($_FILES['photo']['tmp_name'], "../uploads/bailleurs/" . $new_name)) {
            $photo_name = $new_name;
        } else {
            error_log("Échec de l'enregistrement de la photo bailleur : " . $new_name);
        }
    }

    try {
        $pdo->beginTransaction();

        // 1. Calcul du numéro d'ordre basé sur l'année en cours
        $sqlCount = "SELECT COUNT(*) FROM bailleurs WHERE YEAR(created_at) = ?";
        $stmtCount = $pdo->prepare($sqlCount);
        $stmtCount->execute([$annee]);
        $total = $stmtCount->fetchColumn();
        $ordre = str_pad($total + 1, 4, "0", STR_PAD_LEFT);

        // 2. Génération du code (BA-XX2025-0000)
        $code_genere = "BA-" . $initiales . $annee . "-" . $ordre;

        // 3. Insertion avec les DEUX téléphones
        $sql = "INSERT INTO bailleurs (
                    code_bailleur, nom, sexe, numero_cni, 
                    telephone1, telephone2, email, adresse, 
                    photo, created_at, solde_du_bailleur
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 0.00)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $code_genere,
            $nom,
            $_POST['sexe'],
            $_POST['numero_cni'],
            $_POST['telephone1'], // Téléphone 1
            $_POST['telephone2'], // Téléphone 2
            $_POST['email'],
            $_POST['adresse'],
            $photo_name
        ]);

        $pdo->commit();
        insertLog($pdo, "Création Bailleur", "Bailleur créé : $nom ($code_genere)");
        flash('success', "Bailleur enregistré avec succès.");
        header("Location: ../pages/bailleurs.php");
        exit();

    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        error_log($e->getMessage());
        flash('error', "Une erreur est survenue lors de l'enregistrement.");
        header('Location: ../pages/bailleurs.php');
        exit();
    }
}