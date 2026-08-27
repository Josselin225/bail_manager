<?php
session_start();
require_once('../config/db.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$id_contrat = $_GET['id'] ?? 0;

// 1. Paramètres de l'entreprise (en-tête)
$entreprise = $pdo->query("SELECT * FROM settings LIMIT 1")->fetch();
if (!$entreprise) {
    $entreprise = [
        'nom_entreprise' => 'BailManager',
        'logo_url' => '',
        'contact_email' => 'contact@bailmanager.com',
        'contact_telephone' => '+225 00 00 00 00',
        'adresse_siege' => 'Yamoussoukro, Côte d\'Ivoire'
    ];
}

// 2. Contrat / locataire / bien
$sqlContrat = "SELECT c.id AS num_contrat, c.depot_garantie, c.date_debut, c.date_contrat,
                      l.nom AS nom_locataire, l.telephone1 AS locataire_tel,
                      m.designation AS nom_propriete, m.adresse AS adresse_propriete
               FROM contrats c
               JOIN locataires l ON c.locataire_id = l.id
               JOIN maisons m ON c.maison_id = m.id
               WHERE c.id = ?";
$stmtC = $pdo->prepare($sqlContrat);
$stmtC->execute([$id_contrat]);
$contrat = $stmtC->fetch();

if (!$contrat) {
    flash('error', "Contrat introuvable.");
    header('Location: gestion_cautions.php');
    exit();
}

$caution_initiale = (float)($contrat['depot_garantie'] ?? 0);

// 3. Mouvements de la caution
$stmtM = $pdo->prepare(
    "SELECT * FROM mouvements_caution
     WHERE locataire_id = (SELECT locataire_id FROM contrats WHERE id = ?)
     ORDER BY date_operation ASC"
);
$stmtM->execute([$id_contrat]);
$mouvements = $stmtM->fetchAll();

$total_mouvements = 0;
foreach ($mouvements as $m) { $total_mouvements += $m['montant']; }
$solde_final = $caution_initiale + $total_mouvements;

$seuil_alerte  = $caution_initiale * 0.3;
$solde_couleur = ($solde_final <= $seuil_alerte) ? '#dc2626' : '#059669';

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
    <title>Relevé de Compte Caution - <?= htmlspecialchars($contrat['nom_locataire']) ?></title>
    <style>
        @page {
            size: A4;
            margin: 8mm 12mm 20mm 12mm;
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
            font-family: 'Segoe UI', Helvetica, Arial, sans-serif;
            font-size: 12pt;
            line-height: 1.25;
            color: #222;
            margin: 0;
        }

        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 80pt;
            color: rgba(200, 200, 200, 0.15);
            z-index: -1000;
            font-weight: bold;
            text-transform: uppercase;
        }

        .header-agency {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 2px solid #333;
            padding-bottom: 6px;
            margin-bottom: 10px;
        }

        .agency-info h2 { margin: 0; font-size: 13pt; color: #000080; text-transform: uppercase; }
        .agency-info p { margin: 0; font-size: 8pt; color: #555; }
        .agency-logo { max-height: 42px; }

        .contract-title { text-align: center; margin-bottom: 10px; }
        .contract-title h1 { font-size: 12pt; text-decoration: underline; text-transform: uppercase; margin: 0; }

        .section { margin-bottom: 8px; }
        .section h3 {
            font-size: 9.5pt;
            margin: 6px 0 4px 0;
            background: #f4f4f4;
            padding: 3px 8px;
            border-left: 5px solid #000080;
            text-transform: uppercase;
        }

        table { width: 100%; border-collapse: collapse; margin-top: 4px; }
        .info-table td { padding: 2px 0; vertical-align: top; }

        .movements-table { border: 1px solid #333; table-layout: fixed; width: calc(100% - 2px); }
        .movements-table th, .movements-table td { border: 1px solid #333; padding: 4px 6px; font-size: 8.5pt; }
        .movements-table th { background: #f4f4f4; text-transform: uppercase; font-size: 8pt; text-align: left; }
        .movements-table td.text-end { text-align: right; }
        .movements-table tr.row-initial { background: #eef4ff; }

        .badge {
            display: inline-block;
            padding: 1px 8px;
            border-radius: 10px;
            font-size: 7.5pt;
            font-weight: bold;
            text-transform: uppercase;
        }
        .badge-initial  { background: #dbeafe; color: #1e40af; }
        .badge-retenue  { background: #fee2e2; color: #991b1b; }
        .badge-ajout    { background: #d1fae5; color: #065f46; }

        .solde-box {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #0f172a;
            color: #fff;
            border-radius: 6px;
            padding: 10px 16px;
            margin-top: 8px;
        }
        .solde-box .lbl { font-size: 9pt; text-transform: uppercase; letter-spacing: .05em; color: #cbd5e1; }
        .solde-box .val { font-size: 14pt; font-weight: bold; }

        .signature-table { margin-top: 15px; width: calc(100% - 2px); table-layout: fixed; }
        .signature-cell {
            border: 1px solid #333;
            height: 70px;
            padding: 6px;
            vertical-align: top;
            width: 50%;
        }

        @media print {
            .no-print { display: none !important; }
            body { background: white; }
        }
    </style>
</head>
<body>

    <div class="watermark">ORIGINAL</div>

    <?php if (!empty($_SESSION['flash'])): ?>
    <?php foreach ($_SESSION['flash'] as $f): ?>
    <div class="no-print" style="padding: 12px 15px; text-align: center; color: #fff; font-weight: bold; background: <?= $f['type'] === 'success' ? '#28a745' : '#dc3545' ?>;">
        <?= htmlspecialchars($f['message']) ?>
    </div>
    <?php endforeach; ?>
    <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>

    <div class="no-print" style="padding: 15px; background: #333; text-align: center; color: white;">
        <strong>Mode Aperçu du Relevé</strong>
        <button onclick="window.print()" style="margin-left:20px; padding: 8px 20px; cursor:pointer; background:#28a745; color:white; border:none; border-radius:3px; font-weight:bold;">Imprimer le relevé</button>
        <a href="gestion_cautions.php" style="margin-left:15px; text-decoration:none; color:#bbb;">Fermer</a>
    </div>

    <?php include('../includes/print_header.php'); ?>

    <div class="contract-title">
        <h1>Relevé de Compte Caution</h1>
        <p style="margin-top: 5px; font-weight: bold;">RÉF : CAUTION-<?= str_pad($contrat['num_contrat'], 4, '0', STR_PAD_LEFT) ?></p>
    </div>

    <div class="section">
        <h3>1. Locataire & Bien Concerné</h3>
        <table class="info-table">
            <tr>
                <td width="20%"><strong>LOCATAIRE :</strong></td>
                <td>
                    <strong><?= htmlspecialchars($contrat['nom_locataire']) ?></strong><br>
                    Téléphone : <?= htmlspecialchars($contrat['locataire_tel'] ?? '—') ?>
                </td>
            </tr>
            <tr>
                <td><strong>BIEN LOUÉ :</strong></td>
                <td>
                    <strong><?= htmlspecialchars($contrat['nom_propriete']) ?></strong><br>
                    <?= htmlspecialchars($contrat['adresse_propriete'] ?? '') ?>
                </td>
            </tr>
            <tr>
                <td><strong>CONTRAT :</strong></td>
                <td>N°<?= (int)$contrat['num_contrat'] ?> — signé le <?= date('d/m/Y', strtotime($contrat['date_contrat'] ?? $contrat['date_debut'])) ?></td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h3>2. Historique des Mouvements</h3>
        <table class="movements-table">
            <tr>
                <th style="width:14%;">Date</th>
                <th style="width:20%;">Type</th>
                <th>Description / Commentaire</th>
                <th style="width:18%;" class="text-end">Montant</th>
            </tr>
            <tr class="row-initial">
                <td>Signature</td>
                <td><span class="badge badge-initial">Dépôt initial</span></td>
                <td>Versement de la caution à la signature du bail</td>
                <td class="text-end" style="color:#059669;"><strong>+<?= number_format($caution_initiale, 0, ',', ' ') ?> FCFA</strong></td>
            </tr>
            <?php foreach ($mouvements as $m):
                $negatif = $m['montant'] < 0;
                $color   = $negatif ? '#dc2626' : '#059669';
                $prefix  = $negatif ? '' : '+';
                $badgeCl = $negatif ? 'badge-retenue' : 'badge-ajout';
            ?>
            <tr>
                <td><?= date('d/m/Y', strtotime($m['date_operation'])) ?></td>
                <td><span class="badge <?= $badgeCl ?>"><?= htmlspecialchars(ucfirst($m['type_mouvement'])) ?></span></td>
                <td><?= htmlspecialchars($m['commentaire']) ?></td>
                <td class="text-end" style="color:<?= $color ?>;"><strong><?= $prefix . number_format($m['montant'], 0, ',', ' ') ?> FCFA</strong></td>
            </tr>
            <?php endforeach; ?>
        </table>

        <div class="solde-box">
            <span class="lbl">Solde actuel net</span>
            <span class="val" style="color:<?= $solde_couleur ?>;"><?= number_format($solde_final, 0, ',', ' ') ?> FCFA</span>
        </div>
    </div>

    <p style="margin-top: 10px;">
        Fait à <?= htmlspecialchars(explode(',', $entreprise['adresse_siege'])[0]) ?>, le <strong><?= date('d/m/Y') ?></strong>.
    </p>

    <div class="signature-table">
        <table>
            <tr>
                <td class="signature-cell">
                    <strong>LE LOCATAIRE</strong><br>
                    <em style="font-size: 8pt;">(Précéder de la mention "Lu et approuvé")</em>
                </td>
                <td class="signature-cell" style="text-align: right;">
                    <strong>POUR L'AGENCE (L'ADMINISTRATEUR)</strong><br>
                    <em style="font-size: 8pt;">(Signature et Cachet)</em>
                </td>
            </tr>
        </table>
    </div>

</body>
</html>
