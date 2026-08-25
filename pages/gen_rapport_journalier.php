<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }
require_once('../config/db.php');

$date_rapport = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$date_mots = date('d/m/Y', strtotime($date_rapport));

// --- RÉCUPÉRATION DES INFOS DE L'AGENCE (Table settings) ---
$query_agence = $pdo->query("SELECT * FROM settings LIMIT 1");
$agence = $query_agence->fetch();

// --- 1. STATISTIQUES GLOBALES ---
$total_bailleurs = $pdo->query("SELECT COUNT(*) FROM bailleurs")->fetchColumn();
$total_locataires = $pdo->query("SELECT COUNT(*) FROM locataires")->fetchColumn();
$total_maisons = $pdo->query("SELECT COUNT(*) FROM maisons")->fetchColumn();

// --- 2. CONTRATS ---
$nb_signes = $pdo->prepare("SELECT COUNT(*) FROM contrats WHERE DATE(date_contrat) = ?");
$nb_signes->execute([$date_rapport]);
$signes = $nb_signes->fetchColumn();

$nb_resilies = $pdo->prepare("SELECT COUNT(*) FROM contrats WHERE statut_contrat='termine' AND DATE(date_fin) = ?");
$nb_resilies->execute([$date_rapport]);
$resilies = $nb_resilies->fetchColumn();

// --- 3. ENCAISSEMENTS ---
$loyers = $pdo->prepare("
    SELECT e.*, l.nom as locataire, m.designation as maison 
    FROM encaissements e
    JOIN contrats c ON e.contrat_id = c.id
    JOIN locataires l ON c.locataire_id = l.id
    JOIN maisons m ON c.maison_id = m.id
    WHERE DATE(e.date_encaissement) = ?
");
$loyers->execute([$date_rapport]);
$liste_loyers = $loyers->fetchAll();

// --- 4. CAUTIONS ---
$cautions = $pdo->prepare("
    SELECT mc.*, l.nom as locataire 
    FROM mouvements_caution mc
    JOIN locataires l ON mc.locataire_id = l.id
    WHERE DATE(mc.date_operation) = ?
");
$cautions->execute([$date_rapport]);
$liste_cautions = $cautions->fetchAll();

// --- 5. CAISSE & BAILLEURS ---
$mouv_agence = $pdo->prepare("SELECT * FROM mouvements_caisse_entreprise WHERE DATE(date_operation) = ?");
$mouv_agence->execute([$date_rapport]);
$liste_mouv_agence = $mouv_agence->fetchAll();

$retraits_b = $pdo->prepare("SELECT v.*, b.nom FROM versements_bailleurs v JOIN bailleurs b ON v.bailleur_id = b.id WHERE DATE(v.date_versement) = ?");
$retraits_b->execute([$date_rapport]);
$liste_retraits = $retraits_b->fetchAll();

$total_loyers = array_sum(array_column($liste_loyers, 'montant_recu'));
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../favicon.svg">
    <title>Rapport Journalier - <?= $date_mots ?></title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/fontawesome/all.min.css">
    <style>
        body { background: #f8f9fa; font-size: 0.82rem; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .container-fluid { max-width: 98%; }
        .section-title { border-bottom: 2px solid #002d72; color: #002d72; font-weight: bold; margin: 20px 0 10px; padding-bottom: 5px; text-transform: uppercase; letter-spacing: 1px; }
        .card-stat { border: 1px solid #dee2e6; border-radius: 8px; }
        
        /* Style Pied de page Agence */
        .footer-agence { display: none; text-align: center; border-top: 1px solid #000; padding-top: 10px; font-size: 10px; }

        @media print {
            @page { size: landscape; margin: 5mm; }
            body { background: white; margin: 0; padding: 0; }
            .no-print { display: none !important; }
            .container-fluid { width: 100%; max-width: 100%; margin: 0; padding: 0; }
            .shadow-sm { box-shadow: none !important; }
            .bg-white { background-color: white !important; }
            
            /* Affichage du pied de page uniquement à l'impression */
            .footer-agence { 
                display: block; 
                position: fixed; 
                bottom: 0; 
                width: 100%; 
            }
            .container-fluid { padding-bottom: 50px; } /* Espace pour le footer */
        }
    </style>
</head>
<body>
<div class="no-print" style="position:fixed;top:10px;right:10px;z-index:999;"><a href="rapports.php" class="btn btn-sm btn-secondary"><i class="fa fa-arrow-left me-1"></i>Retour</a></div>

<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3 no-print bg-white p-2 rounded shadow-sm">
        <div>
            <a href="rapports.php" class="btn btn-outline-secondary btn-sm"><i class="fa fa-arrow-left"></i> Retour</a>
        </div>
        <div class="text-center">
            <span class="badge bg-info text-dark">Impression Paysage - Marges Minimes</span>
        </div>
        <button onclick="window.print()" class="btn btn-primary btn-sm"><i class="fa fa-print"></i> Imprimer</button>
    </div>

    <div class="row mb-4 align-items-center g-3">
    <div class="col-12 col-md-4">
        <?php
        // 1. On récupère le nom du fichier depuis la base (ex: logo.png)
        $nom_fichier_logo = $agence['logo_url']; 
        
        // 2. On construit le chemin vers le dossier uploads (on remonte d'un dossier car on est dans /pages/)
        $chemin_complet_logo = "../uploads/" . $nom_fichier_logo;

        // 3. Affichage
        if(!empty($nom_fichier_logo) && file_exists($chemin_complet_logo)): ?>
            <img src="<?= $chemin_complet_logo ?>" alt="Logo" style="max-height: 80px; width: auto; object-fit: contain;" class="mb-2">
        <?php else: ?>
            <h4 class="fw-bold mb-0" style="color: #002d72;"><?= strtoupper($agence['nom_entreprise']) ?></h4>
        <?php endif; ?>
        
        <div class="mt-2">
            <h5 class="fw-bold mb-0" style="color: #002d72;"><?= strtoupper($agence['nom_entreprise']) ?></h5>
            <small class="text-muted d-block"><?= $agence['adresse_siege'] ?></small>
        </div>
    </div>
    
    <div class="col-12 col-md-4 text-center">
        <h3 class="fw-bold mb-0">RAPPORT JOURNALIER</h3>
        <p class="mb-0 text-uppercase fw-bold text-muted" style="letter-spacing: 2px;">Situation du <?= $date_mots ?></p>
    </div>

    <div class="col-12 col-md-4 text-md-end border-start">
        <p class="mb-1 fw-bold"><i class="fa fa-phone me-2"></i><?= $agence['contact_telephone'] ?></p>
        <p class="mb-1"><i class="fa fa-envelope me-2"></i><?= $agence['contact_email'] ?></p>
        <small class="text-muted">Généré le <?= date('d/m/Y à H:i') ?></small>
    </div>
</div>

    <div class="row g-2 mb-3">
        <?php 
            $stats = [
                ['label' => 'Bailleurs', 'val' => $total_bailleurs, 'icon' => 'fa-user-tie'],
                ['label' => 'Locataires', 'val' => $total_locataires, 'icon' => 'fa-users'],
                ['label' => 'Maisons', 'val' => $total_maisons, 'icon' => 'fa-home'],
                ['label' => 'Nvx Contrats', 'val' => $signes, 'icon' => 'fa-file-signature'],
                ['label' => 'Résiliations', 'val' => $resilies, 'icon' => 'fa-file-contract']
            ];
            foreach($stats as $s):
        ?>
        <div class="col-6 col-md">
            <div class="card card-stat p-2 text-center bg-white shadow-sm">
                <small class="text-muted d-block text-uppercase" style="font-size: 0.65rem;"><?= $s['label'] ?></small>
                <span class="fw-bold h5 mb-0"><?= $s['val'] ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="row g-3">
        <div class="col-md-7">
            <div class="section-title"><i class="fa fa-money-bill-wave me-2"></i>I. Encaissements de Loyers</div>
            <table class="table table-sm table-striped table-bordered bg-white">
                <thead class="table-dark">
                    <tr>
                        <th>Locataire</th>
                        <th>Bien / Maison</th>
                        <th>Période</th>
                        <th class="text-end">Montant Recu</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($liste_loyers as $l): ?>
                    <tr>
                        <td><?= $l['locataire'] ?></td>
                        <td><?= $l['maison'] ?></td>
                        <td><?= $l['periode_concernee'] ?></td>
                        <td class="text-end fw-bold"><?= number_format($l['montant_recu'], 0, ',', ' ') ?></td>
                    </tr>
                    <?php endforeach; if(empty($liste_loyers)) echo "<tr><td colspan='4' class='text-center'>Néant</td></tr>"; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <th colspan="3" class="text-end">TOTAL ENCAISSÉ</th>
                        <th class="text-end text-primary h6 mb-0"><?= number_format($total_loyers, 0, ',', ' ') ?> FCFA</th>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="col-md-5">
            <div class="section-title"><i class="fa fa-shield-alt me-2"></i>II. Mouvements de Cautions</div>
            <table class="table table-sm table-bordered bg-white mb-4">
                <thead class="table-light">
                    <tr>
                        <th>Locataire</th>
                        <th>Type</th>
                        <th class="text-end">Montant</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($liste_cautions as $c): ?>
                    <tr>
                        <td><small><?= $c['locataire'] ?></small></td>
                        <td><small><?= $c['type_mouvement'] ?></small></td>
                        <td class="text-end fw-bold"><?= number_format($c['montant'], 0, ',', ' ') ?></td>
                    </tr>
                    <?php endforeach; if(empty($liste_cautions)) echo "<tr><td colspan='3' class='text-center'>Néant</td></tr>"; ?>
                </tbody>
            </table>

            <div class="section-title"><i class="fa fa-arrow-circle-down me-2"></i>III. Sorties (Caisse & Bailleurs)</div>
            <table class="table table-sm table-bordered bg-white">
                <thead class="table-light">
                    <tr>
                        <th>Bailleur / Motif</th>
                        <th class="text-end">Montant</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                        $total_sorties = 0;
                        foreach($liste_retraits as $r): $total_sorties += $r['montant']; ?>
                        <tr><td>[RETRAIT] <?= $r['nom'] ?></td><td class="text-end text-danger">-<?= number_format($r['montant'], 0, ',', ' ') ?></td></tr>
                    <?php endforeach; ?>
                    <?php foreach($liste_mouv_agence as $m): if($m['montant'] < 0): $total_sorties += abs($m['montant']); ?>
                        <tr><td>[AGENCE] <?= $m['commentaire'] ?></td><td class="text-end text-danger">-<?= number_format(abs($m['montant']), 0, ',', ' ') ?></td></tr>
                    <?php endif; endforeach; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <th class="text-end">TOTAL DÉBOURSÉ</th>
                        <th class="text-end text-danger"><?= number_format($total_sorties, 0, ',', ' ') ?> FCFA</th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="row mt-4 p-3 bg-dark text-white rounded g-2">
        <div class="col-12 col-sm-4 text-center border-end border-secondary">
            <small class="d-block opacity-75">RECETTES TOTALES</small>
            <span class="h4 fw-bold text-success">+ <?= number_format($total_loyers, 0, ',', ' ') ?></span>
        </div>
        <div class="col-12 col-sm-4 text-center border-end border-secondary">
            <small class="d-block opacity-75">DÉPENSES TOTALES</small>
            <span class="h4 fw-bold text-danger">- <?= number_format($total_sorties, 0, ',', ' ') ?></span>
        </div>
        <div class="col-12 col-sm-4 text-center">
            <small class="d-block opacity-75">SOLDE NET EN CAISSE</small>
            <span class="h4 fw-bold text-info"><?= number_format($total_loyers - $total_sorties, 0, ',', ' ') ?> FCFA</span>
        </div>
    </div>

    <div class="row mt-5 text-center">
        <div class="col-6">
            <p class="mb-5">Le Comptable</p>
            <div class="border-bottom mx-auto" style="width: 150px;"></div>
        </div>
        <div class="col-6">
            <p class="mb-5">La Direction</p>
            <div class="border-bottom mx-auto" style="width: 150px;"></div>
        </div>
    </div>

    <div class="footer-agence">
        <?= $agence['nom_entreprise'] ?> - <?= $agence['adresse_siege'] ?> - Tél: <?= $agence['contact_telephone'] ?> - Email: <?= $agence['contact_email'] ?>
    </div>
</div>

</body>
</html>