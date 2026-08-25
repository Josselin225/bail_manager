<?php
session_start();
require_once('../config/db.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$dateDebut = trim($_GET['date_debut'] ?? '') ?: date('Y-m-01');
$dateFin   = trim($_GET['date_fin']   ?? '') ?: date('Y-m-d');

$stmt = $pdo->prepare(
    "SELECT e.*, l.nom AS nom_locataire, m.designation AS nom_maison
     FROM encaissements e
     JOIN contrats c ON e.contrat_id = c.id
     JOIN locataires l ON c.locataire_id = l.id
     JOIN maisons m ON c.maison_id = m.id
     WHERE e.date_encaissement BETWEEN :debut AND :fin
     ORDER BY e.date_encaissement ASC, e.id ASC"
);
$stmt->execute([':debut' => $dateDebut . ' 00:00:00', ':fin' => $dateFin . ' 23:59:59']);
$encaissements = $stmt->fetchAll();

$totalMontant = 0;
$parMode = ['especes' => 0, 'virement' => 0, 'mobile_money' => 0, 'cheque' => 0];
foreach ($encaissements as $e) {
    $totalMontant += (float)$e['montant_recu'];
    $mode = $e['mode_paiement'] ?? 'especes';
    if (isset($parMode[$mode])) { $parMode[$mode] += (float)$e['montant_recu']; }
}
$nbPaiements  = count($encaissements);
$montantMoyen = $nbPaiements > 0 ? $totalMontant / $nbPaiements : 0;

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
    <title>Rapport des Encaissements — BailManager</title>
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

        .kpi-row { display: flex; gap: 8px; margin-bottom: 12px; }
        .kpi-box { flex: 1; border: 1px solid #ddd; border-radius: 6px; padding: 7px 10px; text-align: center; }
        .kpi-box .lbl { font-size: 7.5pt; text-transform: uppercase; letter-spacing: .04em; color: #888; }
        .kpi-box .val { font-size: 12pt; font-weight: bold; margin-top: 2px; }

        table.re-table { width: 100%; border-collapse: collapse; margin-top: 4px; }
        table.re-table thead th {
            background: #000080; color: #fff; font-size: 8.5pt; text-transform: uppercase;
            padding: 6px 8px; text-align: left; border: 1px solid #000080;
        }
        table.re-table tbody td { padding: 5px 8px; font-size: 9pt; border: 1px solid #ddd; }
        table.re-table tbody tr:nth-child(even) { background: #f7f9fc; }
        table.re-table tr.row-total td { background: #0f172a; color: #fff; font-weight: bold; }
        .text-end { text-align: right; }

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
            table.re-table thead { display: table-header-group; }
        }
    </style>
</head>
<body>

<div class="no-print" style="padding:15px;background:#333;text-align:center;color:#fff;">
    <strong>Aperçu du rapport</strong>
    <form method="GET" id="formFiltreEnc" style="display:inline-flex;gap:8px;align-items:center;margin-left:20px;">
        <label style="font-size:12px;">Mois</label>
        <input type="month" id="filtreMois" value="<?= date('Y-m', strtotime($dateDebut)) ?>" style="padding:4px 8px;border-radius:4px;border:none;">
        <label style="font-size:12px;">Du</label>
        <input type="date" name="date_debut" id="dateDebutInput" value="<?= htmlspecialchars($dateDebut) ?>" style="padding:4px 8px;border-radius:4px;border:none;">
        <label style="font-size:12px;">au</label>
        <input type="date" name="date_fin" id="dateFinInput" value="<?= htmlspecialchars($dateFin) ?>" style="padding:4px 8px;border-radius:4px;border:none;">
        <button type="submit" style="padding:5px 16px;background:#0d6efd;color:#fff;border:none;border-radius:4px;cursor:pointer;">Filtrer</button>
    </form>
    <button onclick="window.print()" style="margin-left:20px;padding:8px 20px;cursor:pointer;background:#28a745;color:#fff;border:none;border-radius:3px;font-weight:bold;">Imprimer</button>
    <a href="liste_encaissements.php" style="margin-left:15px;text-decoration:none;color:#bbb;">Fermer</a>
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
    <h1>Rapport Détaillé des Encaissements</h1>
    <p>
        Période du <strong><?= date('d/m/Y', strtotime($dateDebut)) ?></strong> au <strong><?= date('d/m/Y', strtotime($dateFin)) ?></strong>
        — Généré le <?= date('d/m/Y à H:i') ?>
    </p>
</div>

<div class="kpi-row">
    <div class="kpi-box">
        <div class="lbl">Paiements</div>
        <div class="val"><?= $nbPaiements ?></div>
    </div>
    <div class="kpi-box">
        <div class="lbl">Total encaissé</div>
        <div class="val" style="color:#059669;"><?= number_format($totalMontant, 0, ',', ' ') ?> FCFA</div>
    </div>
    <div class="kpi-box">
        <div class="lbl">Montant moyen</div>
        <div class="val"><?= number_format($montantMoyen, 0, ',', ' ') ?> FCFA</div>
    </div>
    <div class="kpi-box">
        <div class="lbl">Espèces / Mobile Money</div>
        <div class="val" style="font-size:10.5pt;"><?= number_format($parMode['especes'], 0, ',', ' ') ?> / <?= number_format($parMode['mobile_money'], 0, ',', ' ') ?></div>
    </div>
    <div class="kpi-box">
        <div class="lbl">Virement / Chèque</div>
        <div class="val" style="font-size:10.5pt;"><?= number_format($parMode['virement'], 0, ',', ' ') ?> / <?= number_format($parMode['cheque'], 0, ',', ' ') ?></div>
    </div>
</div>

<table class="re-table">
    <thead>
        <tr>
            <th style="width:12%;">Enregistré le</th>
            <th style="width:18%;">Locataire</th>
            <th style="width:18%;">Maison</th>
            <th style="width:12%;">Période</th>
            <th style="width:12%;" class="text-end">Montant</th>
            <th style="width:12%;">Mode</th>
            <th>Référence</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($encaissements)): ?>
        <tr><td colspan="7" style="text-align:center;color:#888;padding:20px;">Aucun encaissement sur cette période.</td></tr>
        <?php else: foreach ($encaissements as $e): ?>
        <tr>
            <td><?= date('d/m/Y H:i', strtotime($e['date_encaissement'])) ?></td>
            <td><?= htmlspecialchars($e['nom_locataire']) ?></td>
            <td><?= htmlspecialchars($e['nom_maison']) ?></td>
            <td><?= htmlspecialchars($e['periode_concernee'] ?? '—') ?></td>
            <td class="text-end"><strong><?= number_format($e['montant_recu'], 0, ',', ' ') ?></strong></td>
            <td><?= ucfirst(str_replace('_', ' ', $e['mode_paiement'] ?? '')) ?></td>
            <td><?= htmlspecialchars($e['reference_recu'] ?? '—') ?></td>
        </tr>
        <?php endforeach; endif; ?>
        <tr class="row-total">
            <td colspan="4">TOTAL PÉRIODE (<?= $nbPaiements ?> paiement<?= $nbPaiements > 1 ? 's' : '' ?>)</td>
            <td class="text-end"><?= number_format($totalMontant, 0, ',', ' ') ?></td>
            <td colspan="2"></td>
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
    document.getElementById('formFiltreEnc').submit();
});
</script>

</body>
</html>
