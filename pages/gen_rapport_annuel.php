<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }
require_once('../config/db.php');

$annee = isset($_GET['annee']) ? $_GET['annee'] : date('Y');

// --- RÉCUPÉRATION DES INFOS DE L'AGENCE ---
$query_agence = $pdo->query("SELECT * FROM settings LIMIT 1");
$agence = $query_agence->fetch();

// --- 1. STATISTIQUES GLOBALES DE L'ANNÉE ---
// Nouveaux contrats
$stmt = $pdo->prepare("SELECT COUNT(*) FROM contrats WHERE YEAR(date_contrat) = ?");
$stmt->execute([$annee]);
$nb_contrats = $stmt->fetchColumn();

// Résiliations
$stmt = $pdo->prepare("SELECT COUNT(*) FROM contrats WHERE statut_contrat='termine' AND YEAR(date_fin) = ?");
$stmt->execute([$annee]);
$nb_resiliations = $stmt->fetchColumn();

// Maisons actives (Total)
$total_maisons = $pdo->query("SELECT COUNT(*) FROM maisons")->fetchColumn();

// --- 2. FINANCES GROUPÉES PAR MOIS ---
// Recettes (Encaissements)
$stmt = $pdo->prepare("SELECT MONTH(date_encaissement) as mois, SUM(montant_recu) as total FROM encaissements WHERE YEAR(date_encaissement) = ? GROUP BY mois");
$stmt->execute([$annee]);
$stats_loyers = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Sorties Agence
$stmt = $pdo->prepare("SELECT MONTH(date_operation) as mois, SUM(ABS(montant)) FROM mouvements_caisse_entreprise WHERE YEAR(date_operation) = ? AND montant < 0 GROUP BY mois");
$stmt->execute([$annee]);
$stats_sorties = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Versements Bailleurs (Correction : montant_verse)
$stmt = $pdo->prepare("SELECT MONTH(date_versement) as mois, SUM(montant_verse) FROM versements_bailleurs WHERE YEAR(date_versement) = ? GROUP BY mois");
$stmt->execute([$annee]);
$stats_bailleurs = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// --- 3. CALCUL DES IMPAYÉS ANNUELS (Estimation) ---
// On compare le cumul des loyers théoriques des contrats actifs vs les encaissements
$stmt = $pdo->prepare("SELECT SUM(loyer_mensuel) FROM contrats WHERE statut_contrat='actif'");
$stmt->execute();
$loyer_theorique_mensuel = $stmt->fetchColumn();
$total_encaisse_annee = array_sum($stats_loyers);
$impayes_estimes = ($loyer_theorique_mensuel * 12) - $total_encaisse_annee;
if($impayes_estimes < 0) $impayes_estimes = 0; // Sécurité si avances

$mois_fr = [1=>"Janvier", 2=>"Février", 3=>"Mars", 4=>"Avril", 5=>"Mai", 6=>"Juin", 7=>"Juillet", 8=>"Août", 9=>"Septembre", 10=>"Octobre", 11=>"Novembre", 12=>"Décembre"];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bilan Annuel <?= $annee ?></title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/fontawesome/all.min.css">
    <style>
        body { background: #f8f9fa; font-size: 0.82rem; }
        .card-stat { border-left: 4px solid #002d72; }
        .section-title { border-bottom: 2px solid #002d72; color: #002d72; font-weight: bold; margin-top: 20px; text-transform: uppercase; }
        @media print {
            @page { size: landscape; margin: 5mm; }
            .no-print { display: none; }
            .footer-agence { display: block !important; position: fixed; bottom: 0; width: 100%; text-align: center; border-top: 1px solid #000; font-size: 10px; }
        }
        .footer-agence { display: none; }
    </style>
</head>
<body>
<div class="no-print" style="position:fixed;top:10px;right:10px;z-index:999;"><a href="rapports.php" class="btn btn-sm btn-secondary"><i class="fa fa-arrow-left me-1"></i>Retour</a></div>

<div class="container-fluid py-3">
    <div class="row mb-4 align-items-center g-3">
        <div class="col-12 col-md-4">
            <?php $logo = "../uploads/" . $agence['logo_url']; if(file_exists($logo)): ?>
                <img src="<?= $logo ?>" style="max-height: 60px;">
            <?php endif; ?>
            <h5 class="fw-bold mb-0"><?= strtoupper($agence['nom_entreprise']) ?></h5>
        </div>
        <div class="col-12 col-md-4 text-center border-start border-end">
            <h2 class="fw-bold mb-0">RAPPORT ANNUEL</h2>
            <span class="badge bg-dark">EXERCICE <?= $annee ?></span>
        </div>
        <div class="col-12 col-md-4 text-md-end">
            <button onclick="window.print()" class="btn btn-sm btn-primary no-print mb-2"><i class="fa fa-print"></i> Imprimer</button>
            <p class="mb-0 small text-muted"><?= $agence['adresse_siege'] ?><br>Tél: <?= $agence['contact_telephone'] ?></p>
        </div>
    </div>

    <div class="row g-2 mb-4">
        <div class="col-6 col-md">
            <div class="card card-stat p-2 shadow-sm">
                <small class="text-muted text-uppercase">Maisons en Gestion</small>
                <h4 class="fw-bold mb-0"><?= $total_maisons ?></h4>
            </div>
        </div>
        <div class="col-6 col-md">
            <div class="card card-stat p-2 shadow-sm border-primary">
                <small class="text-muted text-uppercase">Nouveaux Contrats</small>
                <h4 class="fw-bold mb-0"><?= $nb_contrats ?></h4>
            </div>
        </div>
        <div class="col-6 col-md">
            <div class="card card-stat p-2 shadow-sm border-danger">
                <small class="text-muted text-uppercase">Contrats Résiliés</small>
                <h4 class="fw-bold mb-0"><?= $nb_resiliations ?></h4>
            </div>
        </div>
        <div class="col-6 col-md">
            <div class="card card-stat p-2 shadow-sm border-warning">
                <small class="text-muted text-uppercase">Impayés Estimés</small>
                <h4 class="fw-bold mb-0 text-danger"><?= number_format($impayes_estimes, 0, ',', ' ') ?></h4>
            </div>
        </div>
    </div>

    <div class="section-title">Analyse Financière Mensuelle</div>
    <table class="table table-sm table-bordered bg-white shadow-sm mt-2">
        <thead class="table-dark text-center">
            <tr>
                <th>Mois</th>
                <th>Recettes (Loyers Encaissés)</th>
                <th>Dépenses & Versements</th>
                <th>Solde Agence</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $t_recette = 0; $t_depense = 0;
            foreach($mois_fr as $num => $nom): 
                $r = $stats_loyers[$num] ?? 0;
                $d = ($stats_sorties[$num] ?? 0) + ($stats_bailleurs[$num] ?? 0);
                $s = $r - $d;
                $t_recette += $r; $t_depense += $d;
            ?>
            <tr>
                <td class="fw-bold"><?= $nom ?></td>
                <td class="text-end text-success"><?= number_format($r, 0, ',', ' ') ?></td>
                <td class="text-end text-danger"><?= number_format($d, 0, ',', ' ') ?></td>
                <td class="text-end fw-bold <?= $s >= 0 ? 'text-primary' : 'text-danger' ?>"><?= number_format($s, 0, ',', ' ') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot class="table-secondary h5">
            <tr>
                <th class="text-center">TOTAL ANNUEL</th>
                <th class="text-end text-success"><?= number_format($t_recette, 0, ',', ' ') ?></th>
                <th class="text-end text-danger"><?= number_format($t_depense, 0, ',', ' ') ?></th>
                <th class="text-end text-primary"><?= number_format($t_recette - $t_depense, 0, ',', ' ') ?> FCFA</th>
            </tr>
        </tfoot>
    </table>

    <div class="row mt-4">
        <div class="col-6">
            <div class="p-3 border rounded">
                <h6 class="fw-bold"><i class="fa fa-info-circle"></i> Note sur les impayés</h6>
                <small>Les impayés sont calculés sur la base des contrats actifs multipliés par 12 mois, moins les encaissements réels enregistrés.</small>
            </div>
        </div>
        <div class="col-6 text-center">
            <div class="border-bottom mx-auto mb-4" style="width: 200px;"></div>
            <p>Signature de la Direction</p>
        </div>
    </div>

    <div class="footer-agence">
        <?= $agence['nom_entreprise'] ?> - <?= $agence['adresse_siege'] ?> - <?= $agence['contact_telephone'] ?>
    </div>
</div>

</body>
</html>