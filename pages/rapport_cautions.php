<?php
session_start();
require_once('../config/db.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$dateDebut = trim($_GET['date_debut'] ?? '') ?: date('Y-m-01');
$dateFin   = trim($_GET['date_fin']   ?? '') ?: date('Y-m-d');

// 1. Vue d'ensemble : toutes les cautions des contrats actifs (situation actuelle)
$liste_cautions = $pdo->query(
    "SELECT l.id AS locataire_id, l.nom AS nom_locataire, m.designation AS nom_propriete,
            c.id AS contrat_id, c.depot_garantie AS caution_initiale, c.date_debut,
            IFNULL((SELECT SUM(mc.montant) FROM mouvements_caution mc WHERE mc.locataire_id = l.id), 0) AS total_mouvements
     FROM contrats c
     JOIN locataires l ON c.locataire_id = l.id
     JOIN maisons m ON c.maison_id = m.id
     WHERE c.statut_contrat = 'actif'
     GROUP BY c.id ORDER BY l.nom ASC"
)->fetchAll();

$totalCautionsInitiales = 0;
$totalSoldeActuel       = 0;
$nbIntact = $nbPartiel = $nbExcedent = $nbNegatif = 0;
foreach ($liste_cautions as $row) {
    $caution = (float)$row['caution_initiale'];
    $solde   = $caution + (float)$row['total_mouvements'];
    $totalCautionsInitiales += $caution;
    $totalSoldeActuel       += $solde;
    if ((float)$row['total_mouvements'] == 0) { $nbIntact++; }
    elseif ($solde >= $caution) { $nbExcedent++; }
    elseif ($solde > 0) { $nbPartiel++; }
    else { $nbNegatif++; }
}

// 2. Mouvements détaillés sur la période sélectionnée (toutes cautions confondues)
$stmtMvt = $pdo->prepare(
    "SELECT mc.*, l.nom AS nom_locataire, m.designation AS nom_propriete
     FROM mouvements_caution mc
     JOIN locataires l ON mc.locataire_id = l.id
     LEFT JOIN contrats c ON c.locataire_id = l.id AND c.statut_contrat = 'actif'
     LEFT JOIN maisons m ON m.id = c.maison_id
     WHERE mc.date_operation BETWEEN :debut AND :fin
     ORDER BY mc.date_operation ASC, mc.id ASC"
);
$stmtMvt->execute([':debut' => $dateDebut . ' 00:00:00', ':fin' => $dateFin . ' 23:59:59']);
$mouvementsPeriode = $stmtMvt->fetchAll();

$totalRetenuesPeriode = 0; $totalAjoutsPeriode = 0;
foreach ($mouvementsPeriode as $m) {
    if ($m['montant'] < 0) { $totalRetenuesPeriode += abs($m['montant']); } else { $totalAjoutsPeriode += $m['montant']; }
}

// Coordonnées agence (en-tête)
$entreprise = $pdo->query("SELECT * FROM settings LIMIT 1")->fetch();
if (!$entreprise) {
    $entreprise = ['nom_entreprise' => 'BailManager', 'logo_url' => '', 'adresse_siege' => '', 'contact_telephone' => '', 'contact_email' => ''];
}

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
    <title>Rapport des Cautions — BailManager</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 10mm 12mm 20mm 12mm;
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
            font-size: 10pt;
            line-height: 1.3;
            color: #222;
            margin: 0;
        }

        .header-agency {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 2px solid #333;
            padding-bottom: 8px;
            margin-bottom: 10px;
        }
        .agency-info h2 { margin: 0; font-size: 14pt; color: #000080; text-transform: uppercase; }
        .agency-info p { margin: 0; font-size: 8.5pt; color: #555; }
        .agency-logo { max-height: 46px; }

        .report-title { text-align: center; margin-bottom: 10px; }
        .report-title h1 { font-size: 13pt; text-transform: uppercase; margin: 0; }
        .report-title p { margin: 3px 0 0; font-size: 9pt; color: #555; }

        .kpi-row { display: flex; gap: 8px; margin-bottom: 12px; }
        .kpi-box { flex: 1; border: 1px solid #ddd; border-radius: 6px; padding: 7px 10px; text-align: center; }
        .kpi-box .lbl { font-size: 7.5pt; text-transform: uppercase; letter-spacing: .04em; color: #888; }
        .kpi-box .val { font-size: 12pt; font-weight: bold; margin-top: 2px; }

        .section-title {
            font-size: 10.5pt; font-weight: bold; text-transform: uppercase;
            background: #f4f4f4; border-left: 5px solid #000080; padding: 4px 10px;
            margin: 14px 0 6px;
        }

        table.rc-table { width: 100%; border-collapse: collapse; margin-top: 4px; }
        table.rc-table thead th {
            background: #000080; color: #fff; font-size: 8.5pt; text-transform: uppercase;
            padding: 6px 8px; text-align: left; border: 1px solid #000080;
        }
        table.rc-table tbody td { padding: 5px 8px; font-size: 9pt; border: 1px solid #ddd; }
        table.rc-table tbody tr:nth-child(even) { background: #f7f9fc; }
        table.rc-table tr.row-total td { background: #0f172a; color: #fff; font-weight: bold; }
        .text-end { text-align: right; }
        .amt-pos { color: #059669; font-weight: bold; }
        .amt-neg { color: #dc2626; font-weight: bold; }

        .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 7.5pt; font-weight: bold; text-transform: uppercase; }
        .badge-intact   { background: #d1fae5; color: #065f46; }
        .badge-partiel  { background: #fef3c7; color: #92400e; }
        .badge-excedent { background: #dbeafe; color: #1e40af; }
        .badge-negatif  { background: #fee2e2; color: #991b1b; }

        @media print {
            .no-print { display: none !important; }
            body { background: white; }
            table.rc-table thead { display: table-header-group; }
        }
    </style>
</head>
<body>

<div class="no-print" style="padding:15px;background:#333;text-align:center;color:#fff;">
    <strong>Aperçu du rapport</strong>
    <form method="GET" id="formFiltreCautions" style="display:inline-flex;gap:8px;align-items:center;margin-left:20px;">
        <label style="font-size:12px;">Mois</label>
        <input type="month" id="filtreMois" value="<?= date('Y-m', strtotime($dateDebut)) ?>" style="padding:4px 8px;border-radius:4px;border:none;">
        <label style="font-size:12px;">Du</label>
        <input type="date" name="date_debut" id="dateDebutInput" value="<?= htmlspecialchars($dateDebut) ?>" style="padding:4px 8px;border-radius:4px;border:none;">
        <label style="font-size:12px;">au</label>
        <input type="date" name="date_fin" id="dateFinInput" value="<?= htmlspecialchars($dateFin) ?>" style="padding:4px 8px;border-radius:4px;border:none;">
        <button type="submit" style="padding:5px 16px;background:#0d6efd;color:#fff;border:none;border-radius:4px;cursor:pointer;">Filtrer</button>
    </form>
    <button onclick="window.print()" style="margin-left:20px;padding:8px 20px;cursor:pointer;background:#28a745;color:#fff;border:none;border-radius:3px;font-weight:bold;">Imprimer</button>
    <a href="gestion_cautions.php" style="margin-left:15px;text-decoration:none;color:#bbb;">Fermer</a>
</div>

<?php include('../includes/print_header.php'); ?>

<div class="report-title">
    <h1>Rapport Détaillé des Cautions</h1>
    <p>
        Situation au <strong><?= date('d/m/Y') ?></strong> — Mouvements du <strong><?= date('d/m/Y', strtotime($dateDebut)) ?></strong> au <strong><?= date('d/m/Y', strtotime($dateFin)) ?></strong>
        — Généré le <?= date('d/m/Y à H:i') ?>
    </p>
</div>

<div class="kpi-row">
    <div class="kpi-box">
        <div class="lbl">Contrats actifs</div>
        <div class="val"><?= count($liste_cautions) ?></div>
    </div>
    <div class="kpi-box">
        <div class="lbl">Total dépôts initiaux</div>
        <div class="val"><?= number_format($totalCautionsInitiales, 0, ',', ' ') ?> FCFA</div>
    </div>
    <div class="kpi-box">
        <div class="lbl">Solde total détenu</div>
        <div class="val" style="color:<?= $totalSoldeActuel >= 0 ? '#059669' : '#dc2626' ?>;"><?= number_format($totalSoldeActuel, 0, ',', ' ') ?> FCFA</div>
    </div>
    <div class="kpi-box">
        <div class="lbl">Intact / Partiel</div>
        <div class="val"><?= $nbIntact ?> / <?= $nbPartiel ?></div>
    </div>
    <div class="kpi-box">
        <div class="lbl">Excédent / Négatif</div>
        <div class="val"><?= $nbExcedent ?> / <?= $nbNegatif ?></div>
    </div>
</div>

<div class="section-title">1. Vue d'ensemble par contrat (situation actuelle)</div>
<table class="rc-table">
    <thead>
        <tr>
            <th style="width:20%;">Locataire</th>
            <th style="width:20%;">Bien loué</th>
            <th style="width:11%;">Date signature</th>
            <th style="width:13%;" class="text-end">Dépôt initial</th>
            <th style="width:13%;" class="text-end">Mouvements cumulés</th>
            <th style="width:13%;" class="text-end">Solde actuel</th>
            <th style="width:10%;">Statut</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($liste_cautions)): ?>
        <tr><td colspan="7" style="text-align:center;color:#888;padding:20px;">Aucun contrat actif.</td></tr>
        <?php else: foreach ($liste_cautions as $row):
            $caution = (float)$row['caution_initiale'];
            $solde   = $caution + (float)$row['total_mouvements'];
            if ((float)$row['total_mouvements'] == 0)  { $badgeCl = 'badge-intact';   $badgeLbl = 'Intact'; }
            elseif ($solde >= $caution)                { $badgeCl = 'badge-excedent'; $badgeLbl = 'Excédent'; }
            elseif ($solde > 0)                        { $badgeCl = 'badge-partiel';  $badgeLbl = 'Partiel'; }
            else                                        { $badgeCl = 'badge-negatif'; $badgeLbl = 'Négatif'; }
        ?>
        <tr>
            <td><?= htmlspecialchars($row['nom_locataire']) ?></td>
            <td><?= htmlspecialchars($row['nom_propriete']) ?></td>
            <td><?= date('d/m/Y', strtotime($row['date_debut'])) ?></td>
            <td class="text-end"><?= number_format($caution, 0, ',', ' ') ?></td>
            <td class="text-end <?= $row['total_mouvements'] < 0 ? 'amt-neg' : ($row['total_mouvements'] > 0 ? 'amt-pos' : '') ?>"><?= number_format((float)$row['total_mouvements'], 0, ',', ' ') ?></td>
            <td class="text-end"><strong><?= number_format($solde, 0, ',', ' ') ?></strong></td>
            <td><span class="badge <?= $badgeCl ?>"><?= $badgeLbl ?></span></td>
        </tr>
        <?php endforeach; endif; ?>
        <tr class="row-total">
            <td colspan="3">TOTAL</td>
            <td class="text-end"><?= number_format($totalCautionsInitiales, 0, ',', ' ') ?></td>
            <td class="text-end"><?= number_format($totalSoldeActuel - $totalCautionsInitiales, 0, ',', ' ') ?></td>
            <td class="text-end"><?= number_format($totalSoldeActuel, 0, ',', ' ') ?></td>
            <td></td>
        </tr>
    </tbody>
</table>

<div class="section-title">2. Mouvements détaillés de la période</div>
<table class="rc-table">
    <thead>
        <tr>
            <th style="width:9%;">Date</th>
            <th style="width:16%;">Locataire</th>
            <th style="width:16%;">Bien loué</th>
            <th style="width:12%;">Type</th>
            <th>Commentaire</th>
            <th style="width:11%;">Effectué par</th>
            <th style="width:10%;" class="text-end">Montant</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($mouvementsPeriode)): ?>
        <tr><td colspan="7" style="text-align:center;color:#888;padding:20px;">Aucun mouvement sur cette période.</td></tr>
        <?php else: foreach ($mouvementsPeriode as $m): $isPos = $m['montant'] >= 0; ?>
        <tr>
            <td><?= date('d/m/Y', strtotime($m['date_operation'])) ?></td>
            <td><?= htmlspecialchars($m['nom_locataire']) ?></td>
            <td><?= htmlspecialchars($m['nom_propriete'] ?? '—') ?></td>
            <td><?= htmlspecialchars(ucfirst($m['type_mouvement'] ?? '')) ?></td>
            <td><?= htmlspecialchars($m['commentaire'] ?? '') ?></td>
            <td><?= htmlspecialchars($m['effectue_par'] ?? '—') ?></td>
            <td class="text-end <?= $isPos ? 'amt-pos' : 'amt-neg' ?>"><?= $isPos ? '+' : '' ?><?= number_format($m['montant'], 0, ',', ' ') ?></td>
        </tr>
        <?php endforeach; endif; ?>
        <tr class="row-total">
            <td colspan="6">TOTAL PÉRIODE (Ajouts : +<?= number_format($totalAjoutsPeriode, 0, ',', ' ') ?> — Retenues : -<?= number_format($totalRetenuesPeriode, 0, ',', ' ') ?>)</td>
            <td class="text-end"><?= number_format($totalAjoutsPeriode - $totalRetenuesPeriode, 0, ',', ' ') ?></td>
        </tr>
    </tbody>
</table>

<script>
document.getElementById('filtreMois').addEventListener('change', function() {
    if (!this.value) return;
    var parts = this.value.split('-');
    var annee = parseInt(parts[0], 10);
    var mois  = parseInt(parts[1], 10);
    var dernierJour = new Date(annee, mois, 0).getDate();
    document.getElementById('dateDebutInput').value = this.value + '-01';
    document.getElementById('dateFinInput').value   = this.value + '-' + String(dernierJour).padStart(2, '0');
    document.getElementById('formFiltreCautions').submit();
});
</script>

</body>
</html>
