<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }
require_once('../config/db.php');

$agence = $pdo->query("SELECT * FROM settings LIMIT 1")->fetch();
$mois_actuel = date('m/Y');

// Requête avec gestion d'erreur sur la colonne 'contact'
try {
    $query = "SELECT c.*, l.nom as locataire, l.contact, m.designation as maison
              FROM contrats c
              JOIN locataires l ON c.locataire_id = l.id
              JOIN maisons m ON c.maison_id = m.id
              WHERE c.statut_contrat = 'actif'
              AND c.id NOT IN (SELECT contrat_id FROM encaissements WHERE periode_concernee LIKE :periode)";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['periode' => "%$mois_actuel%"]);
    $impayes = $stmt->fetchAll();
} catch (Exception $e) {
    // Repli si 'contact' n'existe pas
    $query = "SELECT c.*, l.nom as locataire, m.designation as maison
              FROM contrats c
              JOIN locataires l ON c.locataire_id = l.id
              JOIN maisons m ON c.maison_id = m.id
              WHERE c.statut_contrat = 'actif'
              AND c.id NOT IN (SELECT contrat_id FROM encaissements WHERE periode_concernee LIKE :periode)";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['periode' => "%$mois_actuel%"]);
    $impayes = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liste des Impayés - <?= $mois_actuel ?></title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/fontawesome/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .main-content { min-height: 100vh; padding: 24px 28px; background: #f4f7fe; }
        .report-header { border-bottom: 2px solid #dc3545; padding-bottom: 10px; margin-bottom: 25px; }

        @media print {
            .no-print { display: none !important; }
            .main-content { margin-left: 0 !important; width: 100% !important; padding: 0 !important; }
        }
    </style>
</head>
<body>
<?php include('../includes/sidebar.php'); ?>
<div class="main-content">
        <div class="container-fluid">
            
            <div class="row report-header align-items-center">
                <div class="col-8">
                    <h2 class="text-danger fw-bold"><i class="fa fa-exclamation-circle"></i> LISTE DES IMPAYÉS</h2>
                    <p class="text-muted">Situation du mois de : <strong><?= $mois_actuel ?></strong></p>
                </div>
                <div class="col-4 text-end no-print">
                    <button onclick="window.print()" class="btn btn-primary"><i class="fa fa-print"></i> Imprimer</button>
                    <a href="rapports.php" class="btn btn-secondary text-white">Retour</a>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Locataire</th>
                                <th>Contact</th>
                                <th>Maison</th>
                                <th class="text-end">Loyer Dû</th>
                                <th class="text-center no-print">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($impayes as $i): ?>
                            <tr>
                                <td class="fw-bold"><?= $i['locataire'] ?></td>
                                <td><?= $i['contact'] ?? '<span class="text-muted">Non renseigné</span>' ?></td>
                                <td><?= $i['maison'] ?></td>
                                <td class="text-end fw-bold text-danger"><?= number_format($i['loyer_mensuel'], 0, ',', ' ') ?> FCFA</td>
                                <td class="text-center no-print">
                                    <?php if(!empty($i['contact'])): ?>
                                        <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $i['contact']) ?>" target="_blank" class="btn btn-sm btn-success rounded-pill">
                                            <i class="fab fa-whatsapp"></i> Relancer
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
</div>
<script src="../js/bootstrap.bundle.min.js"></script>
</body>
</html>