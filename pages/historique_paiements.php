<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once('../config/db.php');

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }

$contrat_id = isset($_GET['contrat_id']) ? (int)$_GET['contrat_id'] : 0;

$check = $pdo->prepare("SELECT * FROM contrats WHERE id = ?");
$check->execute([$contrat_id]);
$contrat = $check->fetch();
if (!$contrat) { header('Location: encaissements.php'); exit(); }

$info = $pdo->prepare("SELECT l.nom, m.designation FROM contrats c LEFT JOIN locataires l ON c.locataire_id=l.id LEFT JOIN maisons m ON c.maison_id=m.id WHERE c.id=?");
$info->execute([$contrat_id]);
$infos = $info->fetch();

$nom_affiche    = $infos['nom']         ?? 'Locataire inconnu';
$maison_affiche = $infos['designation'] ?? 'Maison inconnue';

$stmtH = $pdo->prepare("SELECT * FROM encaissements WHERE contrat_id=? ORDER BY date_encaissement DESC");
$stmtH->execute([$contrat_id]);
$historique = $stmtH->fetchAll();

$loyer        = (float)$contrat['loyer_mensuel'];
$total_verse  = array_sum(array_column($historique, 'montant_recu'));
$nb_paiements = count($historique);

$reliquats = [];
foreach ($historique as $p) {
    $per = $p['periode_concernee'];
    if (!isset($reliquats[$per])) {
        $s = $pdo->prepare("SELECT SUM(montant_recu) FROM encaissements WHERE contrat_id=? AND periode_concernee=?");
        $s->execute([$contrat_id, $per]);
        $reliquats[$per] = $loyer - (float)$s->fetchColumn();
    }
}
$total_du = array_sum(array_filter($reliquats, fn($v) => $v > 0));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../favicon.svg">
    <title>Historique — <?= htmlspecialchars($nom_affiche) ?></title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/fontawesome/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        :root { --marine:#002147; --green:#059669; --red:#e53e3e; }
        .main-content  { background:#f4f7fe; height:calc(100vh - var(--tb-h, 60px)); display:flex; flex-direction:column; overflow:hidden; }
        .top-fixed     { padding:18px 28px 0; flex-shrink:0; }
        .bottom-scroll { flex:1; overflow-y:auto; overflow-x:hidden; }
        .kpi-card { background:#fff; border-radius:12px; padding:12px 16px; display:flex; align-items:center; gap:12px; box-shadow:0 2px 10px rgba(0,0,0,.06); border:1px solid #e8ecf4; height:100%; }
        .kpi-icon { width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:16px; flex-shrink:0; }
        .kpi-val  { font-size:1.1rem; font-weight:800; line-height:1.1; }
        .kpi-lbl  { font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:.05em; color:#8896b0; margin-top:1px; }
        .kpi-sub  { font-size:10px; color:#aab; }
        .tbl-full { background:#fff; border-top:1px solid #e8ecf4; width:100%; }
        .tbl-full thead th { background:#f8faff; color:#6b7a99; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; padding:13px 24px; border-bottom:1px solid #e8ecf4; position:sticky; top:0; z-index:2; }
        .tbl-full tbody td { padding:11px 24px; border-bottom:1px solid #f0f3fa; vertical-align:middle; font-size:13px; }
        .tbl-full tbody tr:last-child td { border-bottom:none; }
        .tbl-full tbody tr:hover td { background:#f8faff; }
    </style>
</head>
<body>
<?php include('../includes/sidebar.php'); ?>

<div class="main-content">
<div class="top-fixed">

    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <div>
            <p class="text-muted small mb-0 mt-1">
                <i class="fa fa-user me-1"></i><?= htmlspecialchars($nom_affiche) ?>
                &nbsp;·&nbsp;<i class="fa fa-home me-1"></i><?= htmlspecialchars($maison_affiche) ?>
            </p>
        </div>
        <a href="encaissements.php" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;"><i class="fa fa-arrow-left me-1"></i>Retour</a>
    </div>

    <div class="row g-2 mb-3">
        <div class="col-6 col-md-4">
            <div class="kpi-card" style="background:var(--marine);">
                <div class="kpi-icon" style="background:rgba(255,255,255,.15);"><i class="fa fa-wallet" style="color:#fff;"></i></div>
                <div>
                    <div class="kpi-val" style="color:#fff;"><?= number_format($total_verse,0,',',' ') ?></div>
                    <div class="kpi-lbl" style="color:rgba(255,255,255,.65);">Total versé</div>
                    <div class="kpi-sub" style="color:rgba(255,255,255,.5);">FCFA</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4">
            <div class="kpi-card"><div class="kpi-icon" style="background:#d1fae5;"><i class="fa fa-receipt" style="color:var(--green);"></i></div><div><div class="kpi-val" style="color:var(--green);"><?= $nb_paiements ?></div><div class="kpi-lbl">Versements</div><div class="kpi-sub">reçus</div></div></div>
        </div>
        <div class="col-6 col-md-4">
            <?php if ($total_du > 0): ?>
            <div class="kpi-card" style="background:#fee2e2;"><div class="kpi-icon" style="background:rgba(255,255,255,.5);"><i class="fa fa-scale-unbalanced" style="color:var(--red);"></i></div><div><div class="kpi-val" style="color:var(--red);"><?= number_format($total_du,0,',',' ') ?></div><div class="kpi-lbl">Restant dû</div><div class="kpi-sub">FCFA</div></div></div>
            <?php else: ?>
            <div class="kpi-card"><div class="kpi-icon" style="background:#d1fae5;"><i class="fa fa-circle-check" style="color:var(--green);"></i></div><div><div class="kpi-val" style="color:var(--green);">Soldé</div><div class="kpi-lbl">Statut global</div><div class="kpi-sub">à jour</div></div></div>
            <?php endif; ?>
        </div>
    </div>

</div><!-- /top-fixed -->
<div class="bottom-scroll">

<?php if (empty($historique)): ?>
<div class="text-center text-muted py-5"><i class="fa fa-inbox fa-3x mb-3 d-block" style="opacity:.2;"></i>Aucun paiement enregistré.</div>
<?php else: ?>

<table class="table mb-0 tbl-full">
    <thead><tr>
        <th style="width:14%;">Référence</th>
        <th style="width:14%;">Période</th>
        <th style="width:16%;">Date</th>
        <th class="text-end" style="width:14%;">Montant versé</th>
        <th style="width:16%;">Reliquat période</th>
        <th style="width:14%;">Mode</th>
        <th class="text-center" style="width:12%;">Quittance</th>
    </tr></thead>
    <tbody>
    <?php foreach ($historique as $h): $reste = $reliquats[$h['periode_concernee']] ?? 0; ?>
    <tr>
        <td class="fw-bold" style="color:var(--marine);font-size:12px;"><?= htmlspecialchars($h['reference_recu']) ?></td>
        <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($h['periode_concernee']) ?></span></td>
        <td class="text-muted small"><?= date('d/m/Y H:i', strtotime($h['date_encaissement'])) ?></td>
        <td class="text-end fw-bold" style="color:var(--green);"><?= number_format($h['montant_recu'],0,',',' ') ?> <small class="text-muted fw-normal">FCFA</small></td>
        <td>
            <?php if ($reste > 0): ?>
            <span class="badge bg-danger-subtle text-danger border border-danger">Il reste <?= number_format($reste,0,',',' ') ?></span>
            <?php else: ?>
            <span class="badge bg-success-subtle text-success border border-success">Soldé</span>
            <?php endif; ?>
        </td>
        <td><span class="badge bg-info text-dark" style="font-size:11px;"><?= ucfirst($h['mode_paiement']) ?></span></td>
        <td class="text-center"><a href="quittance.php?id=<?= $h['id'] ?>" class="btn btn-sm btn-outline-dark" style="border-radius:6px;"><i class="fa fa-print"></i></a></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php endif; ?>

</div><!-- /bottom-scroll -->
</div><!-- /main-content -->

<script src="../js/bootstrap.bundle.min.js"></script>
</body>
</html>
