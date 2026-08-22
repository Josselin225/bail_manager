<?php
session_start();
require_once('../config/db.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/locataires.php');
    exit();
}
csrf_validate();

if (!isset($_SESSION['user_id'])) {
    flash('error', "Veuillez vous connecter.");
    header('Location: ../index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$stmtRole = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmtRole->execute([$user_id]);
$userRole = $stmtRole->fetchColumn();

if ($userRole !== 'admin') {
    flash('error', "Accès refusé : Seul l'administrateur peut supprimer un locataire.");
    header('Location: ../pages/locataires.php');
    exit();
}

if (isset($_POST['id'])) {
    $id = (int)$_POST['id'];

    try {
        $checkContrat = $pdo->prepare("
            SELECT COUNT(*) FROM contrats
            WHERE locataire_id = ? AND statut_contrat != 'résilié'
        ");
        $checkContrat->execute([$id]);

        if ($checkContrat->fetchColumn() > 0) {
            flash('error', "Suppression impossible : ce locataire a un contrat en cours.");
            header('Location: ../pages/locataires.php');
            exit();
        }

        $stmtFiles = $pdo->prepare("SELECT photo_locataire, photo_cni_recto, photo_cni_verso FROM locataires WHERE id = ?");
        $stmtFiles->execute([$id]);
        $locataireFiles = $stmtFiles->fetch();

        $delete = $pdo->prepare("DELETE FROM locataires WHERE id = ?");
        if ($delete->execute([$id])) {
            $upload_dir = "../uploads/locataires/";
            foreach (['photo_locataire', 'photo_cni_recto', 'photo_cni_verso'] as $field) {
                if (!empty($locataireFiles[$field]) && $locataireFiles[$field] != 'default_user.png') {
                    $path = $upload_dir . $locataireFiles[$field];
                    if (file_exists($path)) unlink($path);
                }
            }
            insertLog($pdo, "Suppression Locataire", "Locataire #$id supprimé");
            flash('success', "Locataire supprimé avec succès.");
        }

    } catch (PDOException $e) {
        flash('error', "Erreur SQL : " . $e->getMessage());
    }
}

header('Location: ../pages/locataires.php');
exit();
