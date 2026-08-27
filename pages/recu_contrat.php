<?php
session_start();
require_once('../config/db.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$id = (int)($_GET['id'] ?? 0);

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

// 2. Requête du contrat avec les détails
$sql = "SELECT c.*, 
               l.nom AS locataire_nom, l.telephone1 AS locataire_tel,
               m.designation AS maison_nom, m.adresse AS maison_adr, m.loyer AS loyer_base,
               b.nom AS bailleur_nom
        FROM contrats c
        JOIN locataires l ON c.locataire_id = l.id
        JOIN maisons m ON c.maison_id = m.id
        JOIN bailleurs b ON m.bailleur_id = b.id
        WHERE c.id = ?";

$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$contrat = $stmt->fetch();

if (!$contrat) {
    flash('error', "Contrat introuvable.");
    header('Location: contrats.php');
    exit();
}

// 3. Clauses du contrat (gérées depuis Paramètres)
$clauses = $pdo->query("SELECT * FROM clauses_contrat WHERE actif = 1 ORDER BY ordre_affichage ASC, id ASC")->fetchAll();

// 4. Pied de page complet (siège, CC, banque...), injecté comme contenu CSS @page pour se
// répéter fiablement sur CHAQUE page imprimée — Chrome ne pagine pas correctement les éléments
// en position:fixed sur les documents multi-pages, contrairement aux marges @page.
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
    <title>Contrat de Bail - <?= htmlspecialchars($contrat['locataire_nom']) ?></title>
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

        /* Bloc signature : reste groupé (ne pas couper sur deux pages) mais s'enchaîne naturellement */
        .signature-page {
            page-break-inside: avoid;
            break-inside: avoid;
            margin-top: 15px;
        }
        /* Filigrane */
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

        /* En-tête Dynamique */
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
        <strong>Mode Aperçu du Contrat</strong>
        <button onclick="window.print()" style="margin-left:20px; padding: 8px 20px; cursor:pointer; background:#28a745; color:white; border:none; border-radius:3px; font-weight:bold;">Imprimer le contrat</button>
        <a href="contrats.php" style="margin-left:15px; text-decoration:none; color:#bbb;">Fermer</a>
    </div>

    <?php include('../includes/print_header.php'); ?>

    <div class="contract-title">
        <h1>Contrat de Bail à Usage d'Habitation</h1>
        <p style="margin-top: 5px; font-weight: bold;">N° REF : BAIL-<?= date('Y') ?>-<?= str_pad($contrat['id'], 4, '0', STR_PAD_LEFT) ?></p>
    </div>

    <div class="section">
        <h3>1. LES PARTIES</h3>
        <table class="info-table">
            <tr>
                <td width="20%"><strong>LE MANDATAIRE :</strong></td>
                <td>
                    <strong><?= htmlspecialchars($entreprise['nom_entreprise']) ?></strong>,
                    agissant au nom et pour le compte de <strong><?= htmlspecialchars($contrat['bailleur_nom']) ?></strong>, propriétaire du bien, en vertu d'un mandat de gestion.
                </td>
            </tr>
            <tr>
                <td><strong>LE PRENEUR :</strong></td>
                <td>
                    <strong><?= htmlspecialchars($contrat['locataire_nom']) ?></strong><br>
                    Téléphone : <?= htmlspecialchars($contrat['locataire_tel']) ?>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h3>2. DÉSIGNATION DU BIEN</h3>
        <p>Le bailleur donne en location au preneur qui accepte le bien immobilier suivant :</p>
        <p style="padding-left: 20px;"><strong>Désignation :</strong> <?= htmlspecialchars($contrat['maison_nom']) ?><br>
        <strong>Localisation :</strong> <?= htmlspecialchars($contrat['maison_adr']) ?></p>
    </div>

    <div class="section">
        <h3>3. CONDITIONS FINANCIÈRES</h3>
        <table class="financial-table">
            <tr style="background:#f8f8f8; font-weight: bold;">
                <td>Loyer Mensuel (Net)</td>
                <td>Dépôt de Garantie</td>
                <td>Avance de Loyer</td>
            </tr>
            <tr>
                <td><strong><?= number_format($contrat['loyer_mensuel'], 0, ',', ' ') ?> FCFA</strong></td>
                <td><strong><?= number_format($contrat['depot_garantie'], 0, ',', ' ') ?> FCFA</strong></td>
                <td><strong><?= number_format($contrat['avance_loyer'] ?? 0, 0, ',', ' ') ?> FCFA</strong></td>
            </tr>
            <tr style="background:#f8f8f8; font-weight: bold;">
                <td>Droit d'Agence</td>
                <td>Date de Prise d'Effet</td>
                <td>Total Payé à la Signature</td>
            </tr>
            <tr>
                <td><strong><?= number_format($contrat['droit_agence'] ?? 0, 0, ',', ' ') ?> FCFA</strong></td>
                <td><?= date('d/m/Y', strtotime($contrat['date_debut'])) ?></td>
                <td><strong><?= number_format(($contrat['depot_garantie'] ?? 0) + ($contrat['avance_loyer'] ?? 0) + ($contrat['droit_agence'] ?? 0), 0, ',', ' ') ?> FCFA</strong></td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h3>4. PRINCIPALES CLAUSES</h3>
        <?php if (empty($clauses)): ?>
        <p style="font-size: 9.5pt;">Aucune clause définie.</p>
        <?php else: ?>
            <?php foreach ($clauses as $cl): ?>
            <p class="clause-item">- <strong><?= htmlspecialchars($cl['titre']) ?> :</strong> <?= nl2br(htmlspecialchars($cl['contenu'])) ?></p>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="signature-page">
        <p style="margin-top: 10px;">
            Fait à <?= htmlspecialchars(explode(',', $entreprise['adresse_siege'])[0]) ?>, le <strong><?= date('d/m/Y', strtotime($contrat['date_contrat'])) ?></strong>.
        </p>

        <div class="signature-table">
            <table>
                <tr>
                    <td class="signature-cell">
                        <strong>LE PRENEUR (LOCATAIRE)</strong><br>
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