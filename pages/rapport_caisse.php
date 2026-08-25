<?php
session_start();
require_once('../config/db.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$dateDebut  = trim($_GET['date_debut'] ?? '') ?: date('Y-m-01');
$dateFin    = trim($_GET['date_fin']   ?? '') ?: date('Y-m-d');
$filtreUser = trim($_GET['user'] ?? '');

$params  = [':debut' => $dateDebut . ' 00:00:00', ':fin' => $dateFin . ' 23:59:59'];
$where   = "WHERE m.date_operation BETWEEN :debut AND :fin";
if ($filtreUser) { $where .= " AND m.effectue_par = :user"; $params[':user'] = $filtreUser; }

// Solde d'ouverture : cumul de tous les mouvements avant la période
$stmtOuverture = $pdo->prepare("SELECT COALESCE(SUM(montant),0) FROM mouvements_caisse_entreprise WHERE date_operation < :debut" . ($filtreUser ? " AND effectue_par = :user" : ""));
$paramsOuverture = [':debut' => $dateDebut . ' 00:00:00'];
if ($filtreUser) $paramsOuverture[':user'] = $filtreUser;
$stmtOuverture->execute($paramsOuverture);
$soldeOuverture = (float)$stmtOuverture->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT m.*, b.nom AS bailleur_nom
     FROM mouvements_caisse_entreprise m
     LEFT JOIN bailleurs b ON m.bailleur_id = b.id
     $where
     ORDER BY m.date_operation ASC, m.id ASC"
);
$stmt->execute($params);
$mouvements = $stmt->fetchAll();

$totalEntrees = 0; $totalSorties = 0;
$soldeCourant = $soldeOuverture;
foreach ($mouvements as &$m) {
    if ($m['montant'] >= 0) { $totalEntrees += $m['montant']; } else { $totalSorties += abs($m['montant']); }
    $soldeCourant += $m['montant'];
    $m['solde_apres'] = $soldeCourant;
}
unset($m);
$soldeCloture = $soldeCourant;

$usersList = $pdo->query("SELECT DISTINCT effectue_par FROM mouvements_caisse_entreprise WHERE effectue_par IS NOT NULL ORDER BY effectue_par")->fetchAll(PDO::FETCH_COLUMN);

// Coordonnées agence (en-tête)
$entreprise = $pdo->query("SELECT * FROM settings LIMIT 1")->fetch();
if (!$entreprise) {
    $entreprise = ['nom_entreprise' => 'BailManager', 'logo_url' => '', 'adresse_siege' => '', 'contact_telephone' => '', 'contact_email' => ''];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../favicon.svg">
    <title>Rapport de Caisse Entreprise — BailManager</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 10mm 12mm;
        }

        body {
            font-family: 'Segoe UI', Helvetica, Arial, sans-serif;
            font-size: 10.5pt;
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

        .kpi-row { display: flex; gap: 10px; margin-bottom: 12px; }
        .kpi-box { flex: 1; border: 1px solid #ddd; border-radius: 6px; padding: 8px 12px; text-align: center; }
        .kpi-box .lbl { font-size: 8pt; text-transform: uppercase; letter-spacing: .04em; color: #888; }
        .kpi-box .val { font-size: 13pt; font-weight: bold; margin-top: 2px; }

        table.mvt-table { width: 100%; border-collapse: collapse; margin-top: 4px; }
        table.mvt-table thead th {
            background: #000080; color: #fff; font-size: 8.5pt; text-transform: uppercase;
            padding: 6px 8px; text-align: left; border: 1px solid #000080;
        }
        table.mvt-table tbody td { padding: 5px 8px; font-size: 9pt; border: 1px solid #ddd; }
        table.mvt-table tbody tr:nth-child(even) { background: #f7f9fc; }
        table.mvt-table tr.row-ouverture td { background: #eef4ff; font-weight: bold; }
        table.mvt-table tr.row-cloture td { background: #0f172a; color: #fff; font-weight: bold; }
        .text-end { text-align: right; }
        .amt-pos { color: #059669; font-weight: bold; }
        .amt-neg { color: #dc2626; font-weight: bold; }

        .print-footer {
            position: fixed;
            bottom: 0; left: 0; right: 0;
            text-align: center;
            font-size: 7pt;
            color: #888;
            border-top: 1px solid #ddd;
            padding-top: 5px;
        }

        @media print {
            .no-print { display: none !important; }
            body { background: white; }
            table.mvt-table thead { display: table-header-group; }
        }
    </style>
</head>
<body>

<div class="no-print" style="padding:15px;background:#333;text-align:center;color:#fff;">
    <strong>Aperçu du rapport</strong>
    <form method="GET" id="formFiltreRapport" style="display:inline-flex;gap:8px;align-items:center;margin-left:20px;">
        <label style="font-size:12px;">Mois</label>
        <input type="month" id="filtreMois" value="<?= date('Y-m', strtotime($dateDebut)) ?>" style="padding:4px 8px;border-radius:4px;border:none;">
        <label style="font-size:12px;">Du</label>
        <input type="date" name="date_debut" id="dateDebutInput" value="<?= htmlspecialchars($dateDebut) ?>" style="padding:4px 8px;border-radius:4px;border:none;">
        <label style="font-size:12px;">au</label>
        <input type="date" name="date_fin" id="dateFinInput" value="<?= htmlspecialchars($dateFin) ?>" style="padding:4px 8px;border-radius:4px;border:none;">
        <select name="user" style="padding:4px 8px;border-radius:4px;border:none;">
            <option value="">— Tous les agents —</option>
            <?php foreach ($usersList as $u): ?>
            <option value="<?= htmlspecialchars($u) ?>" <?= $filtreUser === $u ? 'selected' : '' ?>><?= htmlspecialchars($u) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" style="padding:5px 16px;background:#0d6efd;color:#fff;border:none;border-radius:4px;cursor:pointer;">Filtrer</button>
    </form>
    <button onclick="window.print()" style="margin-left:20px;padding:8px 20px;cursor:pointer;background:#28a745;color:#fff;border:none;border-radius:3px;font-weight:bold;">Imprimer</button>
    <a href="caisse_entreprise.php" style="margin-left:15px;text-decoration:none;color:#bbb;">Fermer</a>
</div>

<div class="header-agency">
    <div class="agency-info">
        <h2><?= htmlspecialchars($entreprise['nom_entreprise']) ?></h2>
        <p><?= nl2br(htmlspecialchars($entreprise['adresse_siege'] ?? '')) ?></p>
        <p>Tél : <?= htmlspecialchars($entreprise['contact_telephone'] ?? '') ?> | Email : <?= htmlspecialchars($entreprise['contact_email'] ?? '') ?></p>
    </div>
    <?php if (!empty($entreprise['logo_url']) && file_exists('../uploads/' . $entreprise['logo_url'])): ?>
    <img src="../uploads/<?= htmlspecialchars($entreprise['logo_url']) ?>" class="agency-logo" alt="Logo">
    <?php endif; ?>
</div>

<div class="report-title">
    <h1>Rapport Détaillé de la Caisse Entreprise</h1>
    <p>
        Période du <strong><?= date('d/m/Y', strtotime($dateDebut)) ?></strong> au <strong><?= date('d/m/Y', strtotime($dateFin)) ?></strong>
        <?= $filtreUser ? ' — Agent : <strong>' . htmlspecialchars($filtreUser) . '</strong>' : '' ?>
        — Généré le <?= date('d/m/Y à H:i') ?>
    </p>
</div>

<div class="kpi-row">
    <div class="kpi-box">
        <div class="lbl">Solde d'ouverture</div>
        <div class="val" style="color:<?= $soldeOuverture >= 0 ? '#059669' : '#dc2626' ?>;"><?= number_format($soldeOuverture, 0, ',', ' ') ?> FCFA</div>
    </div>
    <div class="kpi-box">
        <div class="lbl">Total entrées</div>
        <div class="val" style="color:#059669;">+<?= number_format($totalEntrees, 0, ',', ' ') ?> FCFA</div>
    </div>
    <div class="kpi-box">
        <div class="lbl">Total sorties</div>
        <div class="val" style="color:#dc2626;">-<?= number_format($totalSorties, 0, ',', ' ') ?> FCFA</div>
    </div>
    <div class="kpi-box">
        <div class="lbl">Solde de clôture</div>
        <div class="val" style="color:<?= $soldeCloture >= 0 ? '#059669' : '#dc2626' ?>;"><?= number_format($soldeCloture, 0, ',', ' ') ?> FCFA</div>
    </div>
    <div class="kpi-box">
        <div class="lbl">Opérations</div>
        <div class="val"><?= count($mouvements) ?></div>
    </div>
</div>

<table class="mvt-table">
    <thead>
        <tr>
            <th style="width:9%;">Date</th>
            <th style="width:13%;">Type</th>
            <th style="width:14%;">Bailleur concerné</th>
            <th>Description</th>
            <th style="width:12%;">Effectué par</th>
            <th style="width:10%;" class="text-end">Montant</th>
            <th style="width:11%;" class="text-end">Solde cumulé</th>
        </tr>
    </thead>
    <tbody>
        <tr class="row-ouverture">
            <td colspan="6">Solde d'ouverture au <?= date('d/m/Y', strtotime($dateDebut)) ?></td>
            <td class="text-end"><?= number_format($soldeOuverture, 0, ',', ' ') ?></td>
        </tr>
        <?php if (empty($mouvements)): ?>
        <tr><td colspan="7" style="text-align:center;color:#888;padding:20px;">Aucune opération sur cette période.</td></tr>
        <?php else: foreach ($mouvements as $m): $isEntree = $m['montant'] >= 0; ?>
        <tr>
            <td><?= date('d/m/Y', strtotime($m['date_operation'])) ?></td>
            <td><?= htmlspecialchars($m['type_mouvement'] ?? '') ?></td>
            <td><?= htmlspecialchars($m['bailleur_nom'] ?? '—') ?></td>
            <td><?= htmlspecialchars($m['commentaire'] ?? '') ?></td>
            <td><?= htmlspecialchars($m['effectue_par'] ?? 'Admin') ?></td>
            <td class="text-end <?= $isEntree ? 'amt-pos' : 'amt-neg' ?>"><?= $isEntree ? '+' : '-' ?><?= number_format(abs($m['montant']), 0, ',', ' ') ?></td>
            <td class="text-end"><?= number_format($m['solde_apres'], 0, ',', ' ') ?></td>
        </tr>
        <?php endforeach; endif; ?>
        <tr class="row-cloture">
            <td colspan="6">Solde de clôture au <?= date('d/m/Y', strtotime($dateFin)) ?></td>
            <td class="text-end"><?= number_format($soldeCloture, 0, ',', ' ') ?></td>
        </tr>
    </tbody>
</table>

<div class="print-footer">
    Rapport généré par <?= htmlspecialchars($entreprise['nom_entreprise']) ?> - Logiciel BailManager
</div>

<script>
document.getElementById('filtreMois').addEventListener('change', function() {
    if (!this.value) return;
    var parts = this.value.split('-');
    var annee = parseInt(parts[0], 10);
    var mois  = parseInt(parts[1], 10);
    var dernierJour = new Date(annee, mois, 0).getDate();
    document.getElementById('dateDebutInput').value = this.value + '-01';
    document.getElementById('dateFinInput').value   = this.value + '-' + String(dernierJour).padStart(2, '0');
    document.getElementById('formFiltreRapport').submit();
});
</script>

</body>
</html>
