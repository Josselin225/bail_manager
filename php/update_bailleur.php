<?php
session_start();
require_once('../config/db.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: ../pages/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    csrf_validate();
    $id = $_POST['id'];
    $nom = $_POST['nom'];
    $sexe = $_POST['sexe'];
    $numero_cni = $_POST['numero_cni'];
    $email = $_POST['email'];
    $telephone1 = $_POST['telephone1'];
    $telephone2 = $_POST['telephone2'];
    $adresse = $_POST['adresse'];

    // Gestion de la nouvelle photo (facultative — on garde l'ancienne si absente)
    $photo_sql = '';
    $photo_name = null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
        if (uploadDepasseLimite($_FILES['photo'])) {
            flash('error', "Le fichier dépasse la taille maximale autorisée (8 Mo).");
            header('Location: ../pages/bailleurs.php');
            exit();
        }
        $mime = mime_content_type($_FILES['photo']['tmp_name']);
        $ext  = mimeToImageExt($mime);
        if ($ext === null) {
            flash('error', "Format de fichier non autorisé.");
            header('Location: ../pages/bailleurs.php');
            exit();
        }
        $upload_dir = "../uploads/bailleurs/";
        $new_name = "BA_" . time() . "." . $ext;
        if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_dir . $new_name)) {
            // Supprimer l'ancienne photo physiquement si elle existe et n'est pas l'image par défaut
            $stmtOld = $pdo->prepare("SELECT photo FROM bailleurs WHERE id = ?");
            $stmtOld->execute([$id]);
            $ancienne_photo = $stmtOld->fetchColumn();
            if ($ancienne_photo && $ancienne_photo != 'default_user.png' && file_exists($upload_dir . $ancienne_photo)) {
                unlink($upload_dir . $ancienne_photo);
            }
            $photo_name = $new_name;
            $photo_sql = ', photo = ?';
        } else {
            error_log("Échec de l'enregistrement de la photo bailleur : " . $new_name);
        }
    }

    try {
        $sql = "UPDATE bailleurs SET
                nom = ?,
                sexe = ?,
                numero_cni = ?,
                email = ?,
                telephone1 = ?,
                telephone2 = ?,
                adresse = ?
                $photo_sql
                WHERE id = ?";

        $params = [$nom, $sexe, $numero_cni, $email, $telephone1, $telephone2, $adresse];
        if ($photo_name) $params[] = $photo_name;
        $params[] = $id;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        insertLog($pdo, "Modification Bailleur", "Bailleur #$id modifié : $nom");
        flash('success', "Bailleur mis à jour avec succès.");
        header("Location: ../pages/bailleurs.php");
    } catch (PDOException $e) {
        error_log($e->getMessage());
        flash('error', "Une erreur est survenue lors de la mise à jour.");
        header('Location: ../pages/bailleurs.php');
        exit();
    }
}