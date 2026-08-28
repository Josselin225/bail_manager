<?php
session_start();
require_once('../config/db.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Sécurité : Vérifier si l'ID est présent dans l'URL
if (!isset($_GET['id'])) {
    header("Location: bailleurs.php");
    exit();
}

$id = $_GET['id'];

// 1. Infos du bailleur
$stmt = $pdo->prepare("SELECT * FROM bailleurs WHERE id = ?");
$stmt->execute([$id]);
$bailleur = $stmt->fetch();

// Si le bailleur n'existe pas
if (!$bailleur) {
    die("Bailleur introuvable.");
}

// 2. Liste de ses maisons (CORRECTION DU POINT PAR LA FLÈCHE)
$stmtM = $pdo->prepare("SELECT * FROM maisons WHERE bailleur_id = ?");
$stmtM->execute([$id]); // Changement du . par ->
$maisons = $stmtM->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../favicon.svg">
    <title>Détails Bailleur - <?= htmlspecialchars($bailleur['nom']) ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/fontawesome/all.min.css">
</head>
<body>
    <?php include('../includes/sidebar.php'); ?>
    
    <div class="main-content">
        <div class="d-flex justify-content-end align-items-center mb-4">
            <a href="bailleurs.php" class="btn btn-outline-secondary btn-sm">Retour</a>
        </div>

        <div class="card border-0 shadow-sm p-4">
             <div class="d-flex align-items-center">
                 <div class="rounded-circle bg-light p-3 me-3">
                     <i class="fa fa-home fa-2x text-primary"></i>
                 </div>
                 <div>
                     <p class="text-muted mb-0">Total des maisons gérées</p>
                     <h3 class="fw-bold mb-0"><?= count($maisons) ?> Maison(s)</h3>
                 </div>
             </div>
        </div>

        <div class="mt-4">
            <h4 class="fw-bold mb-3"><i class="fa fa-list me-2"></i>Détails du Patrimoine</h4>
            <div class="card border-0 shadow-sm">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Désignation</th>
                                <th>Type / Pièces</th>
                                <th>Quartier</th>
                                <th>Loyer Mensuel</th>
                                <th class="text-center">Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($maisons) > 0): ?>
                                <?php foreach($maisons as $m): ?>
                                    <tr>
                                        <td class="fw-bold text-dark"><?= htmlspecialchars($m['designation']) ?></td>
                                        
                                        <td><?= htmlspecialchars($m['type_maison'] ?? 'Non défini') ?></td>
                                        
                                        <td><?= htmlspecialchars($m['adresse'] ?? 'Quartier inconnu') ?></td>
                                        
                                        <td class="fw-bold text-primary">
                                            <?= number_format($m['loyer'] ?? 0, 0, ',', ' ') ?> FCFA
                                        </td>
                                        
                                        <td class="text-center">
                                            <span class="badge bg-info text-uppercase"><?= htmlspecialchars($m['statut'] ?? 'Libre') ?></span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">Ce bailleur n'a aucune maison enregistrée pour le moment.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        </div>
</body>
</html>