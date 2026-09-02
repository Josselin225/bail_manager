<?php
session_start();
require_once('../config/db.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$bailleur_id = (int)($_GET['bailleur_id'] ?? 0);

// 1. Paramètres de l'entreprise (Settings)
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

// 2. Bailleur
$stmtB = $pdo->prepare("SELECT * FROM bailleurs WHERE id = ?");
$stmtB->execute([$bailleur_id]);
$bailleur = $stmtB->fetch();

if (!$bailleur) {
    flash('error', "Bailleur introuvable.");
    header('Location: bailleurs.php');
    exit();
}

// 3. Mandat le plus récent de ce bailleur (actif de préférence, sinon le dernier enregistré)
$stmtM = $pdo->prepare(
    "SELECT * FROM mandats_gestion WHERE bailleur_id = ?
     ORDER BY (statut = 'actif') DESC, date_debut DESC, id DESC LIMIT 1"
);
$stmtM->execute([$bailleur_id]);
$mandat = $stmtM->fetch();

if (!$mandat) {
    flash('error', "Aucun mandat de gestion n'est enregistré pour ce bailleur.");
    header('Location: bailleurs.php');
    exit();
}

// 4. Pied de page complet (siège, CC, banque...), injecté comme contenu CSS @page pour se
// répéter fiablement sur CHAQUE page imprimée.
$footerLines = buildFooterLines($entreprise);
$footerCssParts = [];
foreach ($footerLines as $i => $line) {
    if ($i > 0) $footerCssParts[] = '"\A"';
    $footerCssParts[] = '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $line) . '"';
}
$footerCssContent = $footerCssParts ? implode(' ', $footerCssParts) : '""';

// 5. Clauses standard du mandat de gestion
$clausesMandat = [
    ['Objet', "Le mandant confie au mandataire, qui l'accepte, la gestion locative des biens immobiliers qu'il possède ou viendrait à posséder, aux fins de location, d'encaissement des loyers et de représentation auprès des locataires."],
    ['Pouvoirs du mandataire', "Le mandataire est habilité, au nom et pour le compte du mandant, à rechercher des locataires, signer les contrats de bail, encaisser les loyers, charges et dépôts de garantie, délivrer quittance, et assurer le suivi de l'entretien courant des biens confiés."],
    ['Représentation exclusive', "Pendant toute la durée du présent mandat, le mandant s'interdit de traiter directement avec les locataires des biens confiés pour tout ce qui relève de la gestion locative ; toute correspondance, notification ou autorisation relative à ces biens transite par le mandataire."],
    ['Obligations du mandataire', "Le mandataire s'engage à agir avec diligence et loyauté, à rendre compte de sa gestion, et à reverser au mandant les sommes lui revenant, déduction faite de sa commission et des frais justifiés, selon la périodicité convenue entre les parties."],
    ['Rémunération', "En contrepartie de ses diligences, le mandataire perçoit une commission de " . number_format((float)$mandat['taux_commission'], 2, ',', ' ') . " % sur les loyers encaissés pour le compte du mandant."],
    ['Obligations du mandant', "Le mandant s'engage à mettre les biens confiés à disposition en bon état d'usage, à fournir au mandataire les documents nécessaires à l'exercice de sa mission, et à s'acquitter de la commission convenue."],
    ['Durée et renouvellement', !empty($mandat['date_fin'])
        ? "Le présent mandat est conclu pour la durée indiquée ci-dessus. Il prend fin de plein droit à son échéance ; son renouvellement suppose l'établissement d'un nouveau mandat entre les parties avant cette date, sans préjudice de la possibilité pour l'une ou l'autre des parties d'y mettre fin par anticipation dans les conditions prévues ci-après."
        : "Le présent mandat est conclu pour une durée indéterminée et demeure en vigueur jusqu'à sa résiliation par l'une des parties dans les conditions prévues ci-après."],
    ['Résiliation', "Le présent mandat peut être résilié à tout moment par l'une ou l'autre des parties moyennant un préavis écrit de trois (03) mois, sans préjudice des engagements en cours (baux non échus, sommes dues)."],
    ['Droit applicable', "Le présent mandat est régi par les dispositions du Code civil relatives au contrat de mandat, sous réserve des dispositions impératives applicables aux baux à usage d'habitation en vigueur en Côte d'Ivoire."],
];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../favicon.svg">
    <title>Mandat de Gestion - <?= htmlspecialchars($bailleur['nom']) ?></title>
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

        .signature-page {
            page-break-inside: avoid;
            break-inside: avoid;
            margin-top: 15px;
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

        .financial-table { border: 1px solid #333; table-layout: fixed; width: calc(100% - 2px); }
        .financial-table td { border: 1px solid #333; padding: 4px; text-align: center; font-size: 8.5pt; }

        .signature-table { margin-top: 15px; width: calc(100% - 2px); table-layout: fixed; }
        .signature-cell {
            border: 1px solid #333;
            height: 70px;
            padding: 6px;
            vertical-align: top;
            width: 50%;
        }

        .clause-item {
            font-size: 9.5pt;
            line-height: 1.3;
            text-align: justify;
            margin: 0 0 5px 0;
            page-break-inside: avoid;
            break-inside: avoid;
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
        <strong>Mode Aperçu du Mandat de Gestion</strong>
        <button onclick="window.print()" style="margin-left:20px; padding: 8px 20px; cursor:pointer; background:#28a745; color:white; border:none; border-radius:3px; font-weight:bold;">Imprimer le mandat</button>
        <a href="bailleurs.php" style="margin-left:15px; text-decoration:none; color:#bbb;">Fermer</a>
    </div>

    <?php include('../includes/print_header.php'); ?>

    <div class="contract-title">
        <h1>Contrat de Mandat de Gestion Immobilière</h1>
        <p style="margin-top: 5px; font-weight: bold;">N° REF : MANDAT-<?= date('Y', strtotime($mandat['date_signature'])) ?>-<?= str_pad($mandat['id'], 4, '0', STR_PAD_LEFT) ?></p>
    </div>

    <div class="section">
        <h3>1. Les Parties</h3>
        <table class="info-table">
            <tr>
                <td width="20%"><strong>LE MANDANT :</strong></td>
                <td>
                    <strong><?= htmlspecialchars($bailleur['nom']) ?></strong>, propriétaire des biens confiés en gestion.<br>
                    <?php if (!empty($bailleur['numero_cni'])): ?>N° CNI : <?= htmlspecialchars($bailleur['numero_cni']) ?><br><?php endif; ?>
                    Téléphone : <?= htmlspecialchars($bailleur['telephone1'] ?? '—') ?>
                    <?php if (!empty($bailleur['adresse'])): ?><br>Adresse : <?= htmlspecialchars($bailleur['adresse']) ?><?php endif; ?>
                </td>
            </tr>
            <tr>
                <td><strong>LE MANDATAIRE :</strong></td>
                <td>
                    <strong><?= htmlspecialchars($entreprise['nom_entreprise']) ?></strong>, agissant en qualité d'agence de gestion immobilière.
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h3>2. Conditions du Mandat</h3>
        <table class="financial-table">
            <tr style="background:#f8f8f8; font-weight: bold;">
                <td>Date de Signature</td>
                <td>Date de Prise d'Effet</td>
                <td>Taux de Commission</td>
            </tr>
            <tr>
                <td><?= date('d/m/Y', strtotime($mandat['date_signature'])) ?></td>
                <td><?= date('d/m/Y', strtotime($mandat['date_debut'])) ?></td>
                <td><strong><?= number_format((float)$mandat['taux_commission'], 2, ',', ' ') ?> %</strong></td>
            </tr>
            <tr style="background:#f8f8f8; font-weight: bold;">
                <td colspan="3">Durée</td>
            </tr>
            <tr>
                <td colspan="3">
                    <?= !empty($mandat['date_fin'])
                        ? "Jusqu'au " . date('d/m/Y', strtotime($mandat['date_fin']))
                        : "Durée indéterminée, renouvelable par tacite reconduction" ?>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h3>3. Principales Clauses</h3>
        <?php foreach ($clausesMandat as [$titre, $contenu]): ?>
        <p class="clause-item">- <strong><?= htmlspecialchars($titre) ?> :</strong> <?= nl2br(htmlspecialchars($contenu)) ?></p>
        <?php endforeach; ?>
    </div>

    <div class="signature-page">
        <p style="margin-top: 10px;">
            Fait à <?= htmlspecialchars(explode(',', $entreprise['adresse_siege'])[0]) ?>, le <strong><?= date('d/m/Y', strtotime($mandat['date_signature'])) ?></strong>.
        </p>

        <div class="signature-table">
            <table>
                <tr>
                    <td class="signature-cell">
                        <strong>LE BAILLEUR (MANDANT)</strong><br>
                        <em style="font-size: 8pt;">(Précéder de la mention "Lu et approuvé")</em>
                    </td>
                    <td class="signature-cell" style="text-align: right;">
                        <strong>POUR L'AGENCE (LE MANDATAIRE)</strong><br>
                        <em style="font-size: 8pt;">(Signature et Cachet)</em>
                    </td>
                </tr>
            </table>
        </div>
    </div>

</body>
</html>
