<?php
session_start();
require_once('../config/db.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/maisons.php');
    exit();
}
csrf_validate();

$id          = $_POST['id'];
$designation = $_POST['designation'];
$type_maison = $_POST['type_maison'];
$bailleur_id = $_POST['bailleur_id'];
$adresse     = $_POST['adresse'];
$description = $_POST['description'] ?? '';
$loyer       = $_POST['loyer'];
$statut      = $_POST['statut'] ?? 'disponible';

// Gestion des nouvelles images
$upload_dir = "../uploads/maisons/";
$sql_images = "";
$params_images = [];
$allowed_mime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

foreach (['img1' => 'image1', 'img2' => 'image2', 'img3' => 'image3'] as $input => $col) {
    if (isset($_FILES[$input]) && $_FILES[$input]['error'] == 0) {
        if (!in_array(mime_content_type($_FILES[$input]['tmp_name']), $allowed_mime)) {
            flash('error', "Format de fichier non autorisé.");
            header("Location: ../pages/maisons.php");
            exit();
        }
        $new_name = "maison_" . time() . "_" . uniqid() . "." . strtolower(pathinfo($_FILES[$input]['name'], PATHINFO_EXTENSION));
        if (move_uploaded_file($_FILES[$input]['tmp_name'], $upload_dir . $new_name)) {
            // Optionnel : Supprimer l'ancienne image ici si nécessaire
            $sql_images .= ", $col = ?";
            $params_images[] = $new_name;
        }
    }
}

// Mise à jour finale
$sql = "UPDATE maisons SET
            designation = ?,
            type_maison = ?,
            bailleur_id = ?,
            adresse = ?,
            description = ?,
            loyer = ?,
            `condition` = ?,
            statut = ?
            $sql_images
        WHERE id = ?";

$stmt = $pdo->prepare($sql);
$final_params = array_merge(
    [$designation, $type_maison, $bailleur_id, $adresse, $description, $loyer, $_POST['condition'], $statut],
    $params_images,
    [$id]
);
$stmt->execute($final_params);

insertLog($pdo, "Modification Maison", "Maison #$id modifiée : $designation");
flash('success', "Maison mise à jour avec succès.");
header("Location: ../pages/maisons.php");