<?php
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }
    require_once('../config/db.php');
    $annee_actuelle = date('Y');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../favicon.svg">
    <title>Centre de Rapports - BailManager</title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/fontawesome/all.min.css">
    <style>
        .main-content { background: #f4f7fe; min-height: 100vh; padding: 24px 28px; }
        .report-card { transition: all 0.3s; border: none; border-radius: 15px; cursor: pointer; }
        .report-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
        .icon-circle { width: 55px; height: 55px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; }
        .bg-purple { background-color: #6f42c1 !important; color: white; }
    </style>
</head>
<body class="bg-light">
    <?php include('../includes/sidebar.php'); ?>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-5">
            <div>
                <p class="text-muted">Sélectionnez le type de document à générer</p>
            </div>
            <a href="dashboard.php" class="btn btn-outline-secondary"><i class="fa fa-arrow-left me-2"></i>Dashboard</a>
        </div>

        <div class="row g-4">
            <div class="col-md-4">
                <div class="card report-card shadow-sm h-100" data-bs-toggle="modal" data-bs-target="#modalJournalier">
                    <div class="card-body p-4 text-center">
                        <div class="icon-circle bg-info bg-opacity-10 text-info"><i class="fa fa-calendar-day fa-2x"></i></div>
                        <h5 class="fw-bold">Rapport Journalier</h5>
                        <p class="text-muted small">Activités et encaissements d'une date précise.</p>
                        <button class="btn btn-sm btn-info text-white w-100">Choisir la date</button>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card report-card shadow-sm h-100" data-bs-toggle="modal" data-bs-target="#modalMensuel">
                    <div class="card-body p-4 text-center">
                        <div class="icon-circle bg-primary bg-opacity-10 text-primary"><i class="fa fa-calendar-alt fa-2x"></i></div>
                        <h5 class="fw-bold">Rapport Mensuel</h5>
                        <p class="text-muted small">Bilan complet pour un mois et une année donnés.</p>
                        <button class="btn btn-sm btn-primary w-100">Choisir le mois</button>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card report-card shadow-sm h-100" data-bs-toggle="modal" data-bs-target="#modalAnnuel">
                    <div class="card-body p-4 text-center">
                        <div class="icon-circle bg-purple bg-opacity-10 text-purple" style="color:#6f42c1"><i class="fa fa-chart-line fa-2x"></i></div>
                        <h5 class="fw-bold">Rapport Annuel</h5>
                        <p class="text-muted small">Performance et statistiques de l'année entière.</p>
                        <button class="btn btn-sm btn-purple w-100" style="background:#6f42c1; color:white;">Choisir l'année</button>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card report-card shadow-sm h-100" onclick="location.href='gen_etat_occupation.php'">
                    <div class="card-body p-4 text-center">
                        <div class="icon-circle bg-success bg-opacity-10 text-success"><i class="fa fa-house-user fa-2x"></i></div>
                        <h5 class="fw-bold">État d'Occupation</h5>
                        <p class="text-muted small">Liste des biens loués et vacants.</p>
                        <button class="btn btn-sm btn-success w-100">Générer</button>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card report-card shadow-sm h-100" onclick="location.href='gen_liste_impayes.php'">
                    <div class="card-body p-4 text-center">
                        <div class="icon-circle bg-danger bg-opacity-10 text-danger"><i class="fa fa-exclamation-triangle fa-2x"></i></div>
                        <h5 class="fw-bold">Liste des Impayés</h5>
                        <p class="text-muted small">Suivi des retards de paiement.</p>
                        <button class="btn btn-sm btn-danger w-100">Générer</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalJournalier" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <form action="gen_rapport_journalier.php" method="GET" class="modal-content">
                <div class="modal-header border-0"><h5>Rapport Journalier</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <label class="form-label small fw-bold">Sélectionner la date</label>
                    <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="modal-footer border-0"><button type="submit" class="btn btn-info text-white w-100">Visualiser</button></div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="modalMensuel" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <form action="gen_rapport_mensuel.php" method="GET" class="modal-content">
                <div class="modal-header border-0"><h5>Rapport Mensuel</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Mois</label>
                        <select name="mois" class="form-select">
                            <?php 
                            $mois_fr = [1=>'Janvier',2=>'Février',3=>'Mars',4=>'Avril',5=>'Mai',6=>'Juin',7=>'Juillet',8=>'Août',9=>'Septembre',10=>'Octobre',11=>'Novembre',12=>'Décembre'];
                            foreach($mois_fr as $num => $nom) echo "<option value='$num' ".(date('n')==$num?'selected':'').">$nom</option>";
                            ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label small fw-bold">Année</label>
                        <input type="number" name="annee" class="form-control" value="<?= $annee_actuelle ?>">
                    </div>
                </div>
                <div class="modal-footer border-0"><button type="submit" class="btn btn-primary w-100">Visualiser</button></div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="modalAnnuel" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <form action="gen_rapport_annuel.php" method="GET" class="modal-content">
                <div class="modal-header border-0"><h5>Rapport Annuel</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <label class="form-label small fw-bold">Choisir l'année</label>
                    <input type="number" name="annee" class="form-control" value="<?= $annee_actuelle ?>">
                </div>
                <div class="modal-footer border-0"><button type="submit" class="btn btn-purple w-100" style="background:#6f42c1; color:white;">Visualiser</button></div>
            </form>
        </div>
    </div>

    <script src="../js/bootstrap.bundle.min.js"></script>
</body>
</html>