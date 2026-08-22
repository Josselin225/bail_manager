<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { header('Location: dashboard.php'); exit(); }
require_once('../config/db.php');

// 1. RÉCUPÉRATION DES DONNÉES ACTUELLES
$stmt = $pdo->query("SELECT * FROM settings WHERE id = 1");
$current = $stmt->fetch();

// 2. TRAITEMENT DU FORMULAIRE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = $_POST['nom_entreprise'];
    $email = $_POST['contact_email'];
    $tel = $_POST['contact_telephone'];
    $taux = $_POST['taux_commission'];
    $adresse = $_POST['adresse_siege'];
    
    // Par défaut, on garde l'ancien logo stocké en DB
    $logo_name = $current['logo_url']; 

    // Gestion du téléchargement du nouveau logo
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === 0) {
        $upload_dir = "../uploads/"; 
        
        // Créer le dossier s'il n'existe pas
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (in_array($ext, $allowed)) {
            $new_name = "logo_" . time() . "." . $ext;
            
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $upload_dir . $new_name)) {
                $logo_name = $new_name; // Nouveau nom à enregistrer
            }
        }
    }

    // 3. MISE À JOUR DE LA BASE DE DONNÉES
    $sql = "UPDATE settings SET 
            nom_entreprise = ?, contact_email = ?, contact_telephone = ?, 
            taux_commission = ?, adresse_siege = ?, logo_url = ? 
            WHERE id = 1";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$nom, $email, $tel, $taux, $adresse, $logo_name]);

    flash('success', "Paramètres mis à jour avec succès !");
    header('Location: settings.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paramètres - BailManager</title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/fontawesome/all.min.css">
    <style>
        :root { --marine: #000080; }
    </style>
</head>
<body>
<?php include('../includes/sidebar.php'); ?>
<div class="main-content">

            <div class="card shadow-sm border-0 p-4">
                <form method="POST" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label fw-bold">Logo de l'agence</label>
                            <input type="file" name="logo" class="form-control mb-2">
                            <?php if(!empty($current['logo_url'])): ?>
                                <div class="p-2 border d-inline-block bg-light rounded">
                                    <img src="../uploads/<?= htmlspecialchars($current['logo_url']) ?>" width="100" class="img-fluid rounded shadow-sm">
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Nom de l'agence / Entreprise</label>
                            <input type="text" name="nom_entreprise" class="form-control" value="<?= htmlspecialchars($current['nom_entreprise'] ?? '') ?>" required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Commission sur loyer payé (%)</label>
                            <div class="input-group">
                                <input type="number" step="0.01" name="taux_commission" class="form-control" value="<?= $current['taux_commission'] ?? '10.00' ?>" required>
                                <span class="input-group-text bg-light">%</span>
                            </div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Email de contact professionnel</label>
                            <input type="email" name="contact_email" class="form-control" value="<?= htmlspecialchars($current['contact_email'] ?? '') ?>" required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Téléphone de l'agence</label>
                            <input type="text" name="contact_telephone" class="form-control" value="<?= htmlspecialchars($current['contact_telephone'] ?? '') ?>" placeholder="+225 0102030405">
                        </div>

                        <div class="col-md-12 mb-4">
                            <label class="form-label fw-bold">Adresse du Siège Social</label>
                            <textarea name="adresse_siege" class="form-control" rows="3"><?= htmlspecialchars($current['adresse_siege'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary px-5 shadow-sm" style="background-color: var(--marine); border: none;">
                            <i class="fa fa-save me-2"></i> Enregistrer les modifications
                        </button>
                    </div>
                </form>
            </div>
</div>
<script src="../js/bootstrap.bundle.min.js"></script>
</body>
</html>