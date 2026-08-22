<?php
session_start();
require_once('../config/db.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    $id = (int)$_POST['id'];
    $nom = htmlspecialchars($_POST['nom']);
    $tel1 = htmlspecialchars($_POST['telephone1']);
    $tel2 = htmlspecialchars($_POST['telephone2']);
    $piece = htmlspecialchars($_POST['piece_identite']);

    try {
        // 1. Récupérer les anciennes photos au cas où on n'en télécharge pas de nouvelles
        $stmt = $pdo->prepare("SELECT photo_locataire FROM locataires WHERE id = ?");
        $stmt->execute([$id]);
        $old_data = $stmt->fetch();
        
        $photo_name = $old_data['photo_locataire'];

        // 2. Gestion du téléchargement de la nouvelle photo de profil (si présente)
        if (isset($_FILES['photo_locataire']) && $_FILES['photo_locataire']['error'] === 0) {
            $allowed_mime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array(mime_content_type($_FILES['photo_locataire']['tmp_name']), $allowed_mime)) {
                flash('error', "Format de fichier non autorisé.");
                header('Location: ../pages/locataires.php');
                exit();
            }
            $upload_dir = '../uploads/locataires/';
            $extension = strtolower(pathinfo($_FILES['photo_locataire']['name'], PATHINFO_EXTENSION));
            $new_filename = 'loc_' . time() . '_' . uniqid() . '.' . $extension;

            if (move_uploaded_file($_FILES['photo_locataire']['tmp_name'], $upload_dir . $new_filename)) {
                // Supprimer l'ancienne photo physiquement si elle existe et n'est pas l'image par défaut
                if ($photo_name && $photo_name != 'default_user.png' && file_exists($upload_dir . $photo_name)) {
                    unlink($upload_dir . $photo_name);
                }
                $photo_name = $new_filename;
            }
        }

        // 3. Mise à jour de la base de données
        $sql = "UPDATE locataires SET 
                nom = :nom, 
                telephone1 = :tel1, 
                telephone2 = :tel2, 
                piece_identite = :piece, 
                photo_locataire = :photo 
                WHERE id = :id";
        
        $update = $pdo->prepare($sql);
        $update->execute([
            ':nom' => $nom,
            ':tel1' => $tel1,
            ':tel2' => $tel2,
            ':piece' => $piece,
            ':photo' => $photo_name,
            ':id' => $id
        ]);

        insertLog($pdo, "Modification Locataire", "Locataire #$id modifié : $nom");
        flash('success', "Locataire mis à jour avec succès !");
        header('Location: ../pages/locataires.php');

    } catch (PDOException $e) {
        error_log($e->getMessage());
        flash('error', "Erreur lors de la mise à jour du locataire.");
        header('Location: ../pages/locataires.php');
        exit();
    }
} else {
    header('Location: ../pages/locataires.php');
}