<?php
session_start();
require_once('../config/db.php');

$id = $_GET['id'] ?? 0;

// 1. Récupération des paramètres de l'entreprise (Settings)
$query_settings = $pdo->query("SELECT * FROM settings LIMIT 1");
$entreprise = $query_settings->fetch();

if (!$entreprise) {
    $entreprise = [
        'nom_entreprise' => 'BailManager',
        'logo_url' => '',
        'contact_email' => 'contact@bailmanager.com',
        'contact_telephone' => '+225 00 00 00 00',
        'adresse_siege' => 'Yamoussoukro, Côte d\'Ivoire'
    ];
}

// 2. On récupère les détails du contrat, du locataire et de la maison
$sql = "SELECT c.*, l.nom AS locataire_nom, l.telephone1 AS locataire_tel, 
               m.designation AS maison_nom, m.adresse AS maison_adr
        FROM contrats c
        JOIN locataires l ON c.locataire_id = l.id
        JOIN maisons m ON c.maison_id = m.id
        WHERE c.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$c = $stmt->fetch();

if (!$c) die("Contrat introuvable.");

// Pied de page complet (siège, CC, banque...), injecté comme contenu CSS @page pour se répéter
// fiablement sur CHAQUE page imprimée (position:fixed casse sur les documents multi-pages sous Chrome).
$footerLines = [];
if (!empty($entreprise['adresse_siege']) || !empty($entreprise['contact_telephone'])) {
    $footerLines[] = trim(
        (!empty($entreprise['adresse_siege']) ? 'Siège social : ' . $entreprise['adresse_siege'] : '') .
        (!empty($entreprise['contact_telephone']) ? ' - Tel : ' . $entreprise['contact_telephone'] : '')
    );
}
$ligneCC = array_filter([
    !empty($entreprise['cc_numero']) ? 'CC N° : ' . $entreprise['cc_numero'] : '',
    !empty($entreprise['regime_imposition']) ? 'Régime d\'Imposition : ' . $entreprise['regime_imposition'] : '',
    !empty($entreprise['rccm_numero']) ? 'N° RCCM : ' . $entreprise['rccm_numero'] : '',
    !empty($entreprise['contact_email']) ? 'E-mail : ' . $entreprise['contact_email'] : '',
]);
if ($ligneCC) $footerLines[] = implode(' - ', $ligneCC);
$ligneBanque = array_filter([
    !empty($entreprise['compte_bancaire']) ? 'Compte bancaire : ' . $entreprise['compte_bancaire'] : '',
    !empty($entreprise['iban']) ? 'IBAN ' . $entreprise['iban'] : '',
    !empty($entreprise['swift']) ? 'SWIFT: ' . $entreprise['swift'] : '',
]);
if ($ligneBanque) $footerLines[] = implode(' - ', $ligneBanque);
if (!empty($entreprise['site_web'])) $footerLines[] = $entreprise['site_web'];

$footerCssParts = [];
foreach ($footerLines as $i => $line) {
    if ($i > 0) $footerCssParts[] = '"\A"';
    $footerCssParts[] = '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $line) . '"';
}
$footerCssContent = $footerCssParts ? implode(' ', $footerCssParts) : '""';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../favicon.svg">
    <title>Reçu de solde de tout compte - <?= htmlspecialchars($c['locataire_nom']) ?></title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/fontawesome/all.min.css">
    <style>
    /* Style pour l'affichage écran */
    .recu-box { 
        border: 1px solid #eee; 
        padding: 40px; 
        margin: 20px auto; 
        background-color: white; 
        max-width: 800px; /* Limite la largeur sur l'écran */
        box-shadow: 0 0 10px rgba(0,0,0,0.1);
    }
    .header-bail { border-bottom: 3px solid #000080; margin-bottom: 30px; }
    .logo-img { max-height: 80px; width: auto; }

    /* Correction des Marges d'Impression */
    @media print {
        @page {
            size: A4;
            margin: 10mm 10mm 20mm 10mm; /* Définit les marges de la feuille */
            @bottom-center {
                content: <?= $footerCssContent ?>;
                white-space: pre-line;
                font-family: 'Segoe UI', Helvetica, Arial, sans-serif;
                font-size: 6.5pt;
                color: #444;
                text-align: center;
                border-top: 1.5px solid #14305c;
                padding-top: 3px;
                line-height: 1.4;
            }
        }
        body {
            background-color: white !important;
            margin: 0;
            padding: 0;
        }
        .container {
            width: 100% !important;
            max-width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
        }
        .recu-box {
            border: none !important;
            box-shadow: none !important;
            padding: 0 !important;
            margin: 0 !important;
            width: 100% !important;
        }
        .btn-print, .btn-secondary { display: none !important; } /* Cache les boutons */

        .table {
            width: 100% !important;
        }
    }
</style>
</head>
<body class="bg-light">

<div class="container my-5">
    <div class="text-end mb-3">
        <button onclick="window.print()" class="btn btn-primary btn-print">
            <i class="fa fa-print"></i> Imprimer le Reçu
        </button>
        <a href="contrats.php" class="btn btn-secondary btn-print">Retour</a>
    </div>

    <div class="card shadow recu-box">
        <div class="header-bail pb-3">
            <?php include('../includes/print_header.php'); ?>
            <div class="row mt-2">
                <div class="col-12 text-center">
                    <h3 class="fw-bold text-uppercase mt-2">Arrêté de Compte</h3>
                    <p class="mb-0 text-muted">Fait le : <?= date('d/m/Y') ?></p>
                    <p class="small fw-bold">Réf Contrat : #<?= str_pad($c['id'], 5, '0', STR_PAD_LEFT) ?></p>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-6">
                <h6 class="text-decoration-underline"><strong>LOCATAIRE SORTANT :</strong></h6>
                <p class="fs-5 mb-0"><strong><?= htmlspecialchars($c['locataire_nom']) ?></strong></p>
                Tél : <?= htmlspecialchars($c['locataire_tel']) ?>
            </div>
            <div class="col-6 text-end">
                <h6 class="text-decoration-underline"><strong>BIEN CONCERNÉ :</strong></h6>
                <p class="mb-0 fw-bold"><?= htmlspecialchars($c['maison_nom']) ?></p>
                <p class="small"><?= htmlspecialchars($c['maison_adr']) ?></p>
            </div>
        </div>

        <h5 class="text-center bg-dark text-white py-2 my-4">DÉTAIL DU SOLDE DE TOUT COMPTE</h5>

        <table class="table table-bordered">
            <thead class="table-light">
                <tr>
                    <th>Désignation</th>
                    <th class="text-end" style="width: 200px;">Montant</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Dépôt de garantie versé à l'entrée</td>
                    <td class="text-end"><?= number_format($c['depot_garantie'], 0, ',', ' ') ?> FCFA</td>
                </tr>
                <tr>
                    <td>Dettes de loyers / Arriérés de paiement</td>
                    <td class="text-end text-danger">- <?= number_format($c['solde_actuel'], 0, ',', ' ') ?> FCFA</td>
                </tr>
                <tr>
                    <td>Retenues (Travaux de remise en état / Factures d'eau/élec)</td>
                    <td class="text-end text-danger">
                        <?php 
                        $retenues = $c['depot_garantie'] - $c['solde_actuel'] - $c['depot_garantie_actuel'];
                        echo "- " . number_format($retenues, 0, ',', ' ') . " FCFA";
                        ?>
                    </td>
                </tr>
                <tr class="fw-bold">
                    <td class="fs-5 py-3">MONTANT TOTAL À RESTITUER</td>
                    <td class="text-end fs-5 py-3 text-primary" style="background-color: #f8f9fa;">
                        <?= number_format($c['depot_garantie_actuel'], 0, ',', ' ') ?> FCFA
                    </td>
                </tr>
            </tbody>
        </table>

        <div class="mt-4">
            <p class="small text-muted" style="text-align: justify;">
                Le locataire reconnaît par la présente avoir reçu la somme mentionnée ci-dessus au titre de la restitution de son dépôt de garantie, après déduction de toutes dettes locatives. Ce règlement vaut solde de tout compte et décharge définitive entre les parties pour l'exécution du contrat de bail susmentionné.
            </p>
            
            <div class="row mt-5">
                <div class="col-6 text-center">
                    <p class="mb-4 text-decoration-underline"><strong>Bon pour accord, le locataire</strong></p>
                    <div style="height: 100px; border: 1px dashed #ccc; width: 80%; margin: 0 auto;"></div>
                    <p class="small mt-2"><?= htmlspecialchars($c['locataire_nom']) ?></p>
                </div>
                <div class="col-6 text-center">
                    <p class="mb-4 text-decoration-underline"><strong>Pour l'agence <?= htmlspecialchars($entreprise['nom_entreprise']) ?></strong></p>
                    <div style="height: 100px; border: 1px dashed #ccc; width: 80%; margin: 0 auto;"></div>
                    <p class="small mt-2">Cachet et Signature</p>
                </div>
            </div>

        </div>
    </div>
</div>


</body>
</html>