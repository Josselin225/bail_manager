<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }
require_once('../config/db.php');

// Récupération du mois et de l'année (par défaut mois actuel)
$mois = isset($_GET['mois']) ? $_GET['mois'] : date('m');
$annee = isset($_GET['annee']) ? $_GET['annee'] : date('Y');

// Nom du mois en français
// S'assurer que le mois est toujours sur 2 chiffres (01, 02...)
$mois = str_pad($mois, 2, "0", STR_PAD_LEFT);

$mois_fr = [
    "01"=>"Janvier", "02"=>"Février", "03"=>"Mars", "04"=>"Avril", 
    "05"=>"Mai", "06"=>"Juin", "07"=>"Juillet", "08"=>"Août", 
    "09"=>"Septembre", "10"=>"Octobre", "11"=>"Novembre", "12"=>"Décembre"
];

// Vérification de sécurité avant d'afficher
$titre_periode = (isset($mois_fr[$mois]) ? $mois_fr[$mois] : "Inconnu") . " " . $annee;

// --- RÉCUPÉRATION DES INFOS DE L'AGENCE ---
$query_agence = $pdo->query("SELECT * FROM settings LIMIT 1");
$agence = $query_agence->fetch();

// --- 1. STATISTIQUES MENSUELLES ---
$nb_signes = $pdo->prepare("SELECT COUNT(*) FROM contrats WHERE MONTH(date_contrat) = ? AND YEAR(date_contrat) = ?");
$nb_signes->execute([$mois, $annee]);
$signes = $nb_signes->fetchColumn();

$nb_resilies = $pdo->prepare("SELECT COUNT(*) FROM contrats WHERE statut_contrat='termine' AND MONTH(date_fin) = ? AND YEAR(date_fin) = ?");
$nb_resilies->execute([$mois, $annee]);
$resilies = $nb_resilies->fetchColumn();

// --- 2. ENCAISSEMENTS DU MOIS ---
$loyers = $pdo->prepare("
    SELECT e.*, l.nom as locataire, m.designation as maison 
    FROM encaissements e
    JOIN contrats c ON e.contrat_id = c.id
    JOIN locataires l ON c.locataire_id = l.id
    JOIN maisons m ON c.maison_id = m.id
    WHERE MONTH(e.date_encaissement) = ? AND YEAR(e.date_encaissement) = ?
    ORDER BY e.date_encaissement ASC
");
$loyers->execute([$mois, $annee]);
$liste_loyers = $loyers->fetchAll();

// --- 3. CAUTIONS DU MOIS ---
$cautions = $pdo->prepare("
    SELECT mc.*, l.nom as locataire 
    FROM mouvements_caution mc
    JOIN locataires l ON mc.locataire_id = l.id
    WHERE MONTH(mc.date_operation) = ? AND YEAR(mc.date_operation) = ?
");
$cautions->execute([$mois, $annee]);
$liste_cautions = $cautions->fetchAll();

// --- 4. DÉPENSES & VERSEMENTS BAILLEURS ---
$mouv_agence = $pdo->prepare("SELECT * FROM mouvements_caisse_entreprise WHERE MONTH(date_operation) = ? AND YEAR(date_operation) = ?");
$mouv_agence->execute([$mois, $annee]);
$liste_mouv_agence = $mouv_agence->fetchAll();

$retraits_b = $pdo->prepare("SELECT v.*, b.nom FROM versements_bailleurs v JOIN bailleurs b ON v.bailleur_id = b.id WHERE MONTH(v.date_versement) = ? AND YEAR(v.date_versement) = ?");
$retraits_b->execute([$mois, $annee]);
$liste_retraits = $retraits_b->fetchAll();

$total_loyers = array_sum(array_column($liste_loyers, 'montant_recu'));
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapport Mensuel - <?= $titre_periode ?></title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/fontawesome/all.min.css">
    <style>
        body { background: #f8f9fa; font-size: 0.82rem; font-family: 'Segoe UI', sans-serif; }
        .section-title { border-bottom: 2px solid #002d72; color: #002d72; font-weight: bold; margin: 20px 0 10px; padding-bottom: 5px; text-transform: uppercase; }
        .footer-agence { display: none; text-align: center; border-top: 1px solid #000; padding-top: 10px; font-size: 10px; }

        @media print {
            @page { size: landscape; margin: 5mm; }
            body { background: white; }
            .no-print { display: none !important; }
            .footer-agence { display: block; position: fixed; bottom: 0; width: 100%; }
        }
    </style>
</head>
<body>
<div class="no-print" style="position:fixed;top:10px;right:10px;z-index:999;"><a href="rapports.php" class="btn btn-sm btn-secondary"><i class="fa fa-arrow-left me-1"></i>Retour</a></div>

<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3 no-print bg-white p-2 rounded shadow-sm">
        <a href="rapports.php" class="btn btn-outline-secondary btn-sm"><i class="fa fa-arrow-left"></i> Retour</a>
        <h5 class="mb-0">Période : <?= $titre_periode ?></h5>
        <button onclick="window.print()" class="btn btn-primary btn-sm"><i class="fa fa-print"></i> Imprimer le Rapport Mensuel</button>
    </div>

    <div class="row mb-4 align-items-center g-3">
        <div class="col-12 col-md-4">
            <?php
            $logo = "../uploads/" . $agence['logo_url'];
            if(!empty($agence['logo_url']) && file_exists($logo)): ?>
                <img src="<?= $logo ?>" alt="Logo" style="max-height: 70px;" class="mb-2">
            <?php endif; ?>
            <h4 class="fw-bold mb-0" style="color: #002d72;"><?= strtoupper($agence['nom_entreprise']) ?></h4>
            <small><?= $agence['adresse_siege'] ?></small>
        </div>
        <div class="col-12 col-md-4 text-center border-start border-end">
            <h2 class="fw-bold mb-0">BILAN MENSUEL</h2>
            <p class="h5 mb-0 text-primary"><?= strtoupper($titre_periode) ?></p>
        </div>
        <div class="col-12 col-md-4 text-md-end">
            <p class="mb-0 fw-bold"><?= $agence['contact_telephone'] ?></p>
            <p class="mb-0"><?= $agence['contact_email'] ?></p>
            <small class="text-muted">Édité le <?= date('d/m/Y') ?></small>
        </div>
    </div>

    <div class="row g-2 mb-4">
        <div class="col-12 col-sm-4"><div class="card p-2 text-center shadow-sm"><b><?= $signes ?></b><br><small>Nouveaux Contrats</small></div></div>
        <div class="col-12 col-sm-4"><div class="card p-2 text-center shadow-sm text-danger"><b><?= $resilies ?></b><br><small>Résiliations</small></div></div>
        <div class="col-12 col-sm-4"><div class="card p-2 text-center shadow-sm text-success"><b><?= number_format($total_loyers, 0, ',', ' ') ?></b><br><small>Total Encaissé</small></div></div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="section-title">I. Récapitulatif des Encaissements (Loyers)</div>
            <table class="table table-sm table-bordered table-striped bg-white">
                <thead class="table-dark text-center">
                    <tr>
                        <th>Date</th>
                        <th>Locataire</th>
                        <th>Bien Immobilier</th>
                        <th>Période</th>
                        <th class="text-end">Montant Payé</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($liste_loyers as $l): ?>
                    <tr>
                        <td class="text-center"><?= date('d/m', strtotime($l['date_encaissement'])) ?></td>
                        <td><?= $l['locataire'] ?></td>
                        <td><?= $l['maison'] ?></td>
                        <td class="text-center"><?= $l['periode_concernee'] ?></td>
                        <td class="text-end fw-bold"><?= number_format($l['montant_recu'], 0, ',', ' ') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="table-secondary">
                        <th colspan="4" class="text-end text-uppercase">Total Recettes Mensuelles :</th>
                        <th class="text-end text-primary h6"><?= number_format($total_loyers, 0, ',', ' ') ?> FCFA</th>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="col-md-4">
            <div class="section-title">II. Dépenses & Retraits</div>
            <table class="table table-sm table-bordered bg-white">
                <thead class="table-light">
                    <tr><th>Motif / Bénéficiaire</th><th class="text-end">Montant</th></tr>
                </thead>
                <tbody>
                    <?php 
                    $total_sorties = 0;
                    foreach($liste_retraits as $r): $total_sorties += $r['montant']; ?>
                        <tr><td>[Bailleur] <?= $r['nom'] ?></td><td class="text-end text-danger">-<?= number_format($r['montant'], 0, ',', ' ') ?></td></tr>
                    <?php endforeach; ?>
                    <?php foreach($liste_mouv_agence as $m): if($m['montant'] < 0): $total_sorties += abs($m['montant']); ?>
                        <tr><td>[Agence] <?= $m['commentaire'] ?></td><td class="text-end text-danger">-<?= number_format(abs($m['montant']), 0, ',', ' ') ?></td></tr>
                    <?php endif; endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="table-danger">
                        <th>TOTAL DÉBOURSÉ</th>
                        <th class="text-end"><?= number_format($total_sorties, 0, ',', ' ') ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="row mt-4 p-3 bg-dark text-white rounded g-0 text-center">
        <div class="col-6 border-end border-secondary">
            <small class="opacity-75">CUMUL DES RECETTES</small><br>
            <span class="h3 fw-bold text-success">+ <?= number_format($total_loyers, 0, ',', ' ') ?> FCFA</span>
        </div>
        <div class="col-6">
            <small class="opacity-75">SOLDE NET DU MOIS</small><br>
            <span class="h3 fw-bold text-info"><?= number_format($total_loyers - $total_sorties, 0, ',', ' ') ?> FCFA</span>
        </div>
    </div>

    <div class="footer-agence">
        <?= strtoupper($agence['nom_entreprise']) ?> - <?= $agence['adresse_siege'] ?> - <?= $agence['contact_telephone'] ?> - <?= $agence['contact_email'] ?>
    </div>
</div>

</body>
</html>