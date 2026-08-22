<?php
session_start();
require_once('../config/db.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../pages/maisons.php");
    exit();
}
csrf_validate();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    flash('error', "Droits insuffisants.");
    header("Location: ../pages/maisons.php");
    exit();
}

if (isset($_POST['id'])) {
    $id = (int)$_POST['id'];

    try {
        $check = $pdo->prepare("SELECT statut FROM maisons WHERE id = ?");
        $check->execute([$id]);
        $maison = $check->fetch();

        if ($maison && $maison['statut'] === 'occupe') {
            flash('warning', "Maison occupée — résiliez le contrat d'abord.");
            header("Location: ../pages/maisons.php");
            exit();
        }

        $stmt = $pdo->prepare("SELECT image1, image2, image3 FROM maisons WHERE id = ?");
        $stmt->execute([$id]);
        $imgs = $stmt->fetch();

        if ($imgs) {
            $upload_dir = "../uploads/maisons/";
            foreach (['image1', 'image2', 'image3'] as $img_field) {
                if (!empty($imgs[$img_field])) {
                    $file_to_delete = $upload_dir . $imgs[$img_field];
                    if (file_exists($file_to_delete)) unlink($file_to_delete);
                }
            }
        }

        $delete = $pdo->prepare("DELETE FROM maisons WHERE id = ?");
        $delete->execute([$id]);

        insertLog($pdo, "Suppression Maison", "Maison #$id supprimée");
        flash('success', "Maison supprimée avec succès.");
        header("Location: ../pages/maisons.php");

    } catch (PDOException $e) {
        flash('error', "Une erreur est survenue lors de la suppression.");
        header("Location: ../pages/maisons.php");
    }
}
exit();
