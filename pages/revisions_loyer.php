<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once('../config/db.php');

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { header('Location: dashboard.php'); exit(); }

$contrat_id = isset($_GET['contrat_id']) ? (int)$_GET['contrat_id'] : 0;

$contratsActifs = $pdo->query(
    "SELECT c.id, l.nom AS locataire, m.designation AS maison, c.loyer_mensuel FROM contrats c JOIN locataires l ON c.locataire_id=l.id JOIN maisons m ON c.maison_id=m.id WHERE c.statut_contrat='actif' ORDER BY l.nom"
)->fetchAll();

$params = []; $where = "";
if ($contrat_id) { $where = "WHERE r.contrat_id = ?"; $params = [$contrat_id]; }

$stmtRev = $pdo->prepare(
    "SELECT r.*, l.nom AS locataire, m.designation AS maison, u.nom_complet AS effectue_par_nom
     FROM revisions_loyer r JOIN contrats c ON r.contrat_id=c.id JOIN locataires l ON c.locataire_id=l.id JOIN maisons m ON c.maison_id=m.id LEFT JOIN users u ON r.effectue_par=u.id
     $where ORDER BY r.created_at DESC LIMIT 100"
);
$stmtRev->execute($params);
$historique = $stmtRev->fetchAll();

$nbRevisions   = (int)  $pdo->query("SELECT COUNT(*) FROM revisions_loyer")->fetchColumn();
$revHausse     = (int)  $pdo->query("SELECT COUNT(*) FROM revisions_loyer WHERE nouveau_loyer > ancien_loyer")->fetchColumn();
$revBaisse     = (int)  $pdo->query("SELECT COUNT(*) FROM revisions_loyer WHERE nouveau_loyer < ancien_loyer")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../favicon.svg">
    <title>Révisions de loyer — BailManager</title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/fontawesome/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        :root { --marine:#002147; --green:#059669; --amber:#d97706; --red:#e53e3e; }
        .main-content  { background:#f4f7fe; height:100vh; display:flex; flex-direction:column; overflow:hidden; }
        .top-fixed     { padding:18px 28px 0; flex-shrink:0; }
        .bottom-scroll { flex:1; overflow-y:auto; overflow-x:hidden; }
        .kpi-card { background:#fff; border-radius:12px; padding:12px 16px; display:flex; align-items:center; gap:12px; box-shadow:0 2px 10px rgba(0,0,0,.06); border:1px solid #e8ecf4; height:100%; }
        .kpi-icon { width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:16px; flex-shrink:0; }
        .kpi-val  { font-size:1.1rem; font-weight:800; line-height:1.1; }
        .kpi-lbl  { font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:.05em; color:#8896b0; margin-top:1px; }
        .kpi-sub  { font-size:10px; color:#aab; }
        .filter-bar { background:#fff; border-radius:10px; padding:10px 16px; display:flex; align-items:center; gap:10px; box-shadow:0 2px 8px rgba(0,0,0,.05); border:1px solid #e8ecf4; flex-wrap:wrap; }
        .tbl-full { background:#fff; border-top:1px solid #e8ecf4; width:100%; }
        .tbl-full thead th { background:#f8faff; color:#6b7a99; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; padding:13px 24px; border-bottom:1px solid #e8ecf4; position:sticky; top:0; z-index:2; }
        .tbl-full tbody td { padding:11px 24px; border-bottom:1px solid #f0f3fa; vertical-align:middle; font-size:13px; }
        .tbl-full tbody tr:last-child td { border-bottom:none; }
        .tbl-full tbody tr:hover td { background:#f8faff; }
        .badge-up   { background:#d4edda; color:#155724; }
        .badge-down { background:#f8d7da; color:#721c24; }
    </style>
</head>
<body>
<?php include('../includes/sidebar.php'); ?>

<?php if (isset($_SESSION['role']) && $_SESSION['role']==='admin'): ?>
<!-- MODAL RÉVISION -->
<div class="modal fade" id="modalRevision" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content border-0 shadow-lg" action="../php/add_revision_loyer.php" method="POST">
            <input type="hidden" name="token" value="<?= csrf_generate() ?>">
            <div class="modal-header text-white border-0" style="background:linear-gradient(135deg,#002147,#004080);">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-white bg-opacity-10 d-flex align-items-center justify-content-center" style="width:40px;height:40px;"><i class="fa fa-arrow-trend-up text-white"></i></div>
                    <div><h5 class="modal-title fw-bold mb-0">Réviser un loyer</h5><small class="opacity-75">Mise à jour du loyer mensuel</small></div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 py-4">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">Contrat <span class="text-danger">*</span></label>
                        <div class="input-group"><span class="input-group-text bg-light border-end-0"><i class="fa fa-file-contract text-muted"></i></span>
                        <select name="contrat_id" id="selectContratRev" class="form-select border-start-0" style="border-radius:0 .375rem .375rem 0" required>
                            <option value="">— Sélectionner —</option>
                            <?php foreach($contratsActifs as $c): ?><option value="<?= $c['id'] ?>" data-loyer="<?= $c['loyer_mensuel'] ?>" <?= $contrat_id==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['locataire']) ?> — <?= htmlspecialchars($c['maison']) ?> (<?= number_format($c['loyer_mensuel'],0,',',' ') ?> FCFA)</option><?php endforeach; ?>
                        </select></div>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">Loyer actuel</label>
                        <div class="input-group"><span class="input-group-text bg-light border-end-0"><i class="fa fa-money-bill text-muted"></i></span><input type="text" id="ancienLoyerAffiche" class="form-control border-start-0 ps-0 bg-light fst-italic" readonly></div>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">Nouveau loyer (FCFA) <span class="text-danger">*</span></label>
                        <div class="input-group"><span class="input-group-text bg-light border-end-0"><i class="fa fa-arrow-up text-muted"></i></span><input type="number" name="nouveau_loyer" class="form-control border-start-0 ps-0" min="1000" step="1000" required></div>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">Motif</label>
                        <input type="text" name="motif" class="form-control" placeholder="Ex: Révision annuelle, indexation…">
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top bg-light px-4">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal"><i class="fa fa-times me-1"></i>Annuler</button>
                <button type="submit" class="btn btn-danger px-5 fw-semibold"><i class="fa fa-check me-2"></i>Enregistrer</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="main-content">
<div class="top-fixed">

    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <div>
            <p class="text-muted small mb-0 mt-1"><?= $nbRevisions ?> révision<?= $nbRevisions>1?'s':'' ?> enregistrée<?= $nbRevisions>1?'s':'' ?></p>
        </div>
        <?php if (isset($_SESSION['role']) && $_SESSION['role']==='admin'): ?>
        <button class="btn btn-danger btn-sm shadow-sm" style="border-radius:8px;" data-bs-toggle="modal" data-bs-target="#modalRevision">
            <i class="fa fa-plus-circle me-2"></i>Nouvelle révision
        </button>
        <?php endif; ?>
    </div>


    <div class="row g-2 mb-3">
        <div class="col-6 col-md-3">
            <div class="kpi-card"><div class="kpi-icon" style="background:#eef2fb;"><i class="fa fa-history" style="color:var(--marine);"></i></div><div><div class="kpi-val" style="color:var(--marine);"><?= $nbRevisions ?></div><div class="kpi-lbl">Révisions</div><div class="kpi-sub">total</div></div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card"><div class="kpi-icon" style="background:#fee2e2;"><i class="fa fa-arrow-trend-up" style="color:var(--red);"></i></div><div><div class="kpi-val" style="color:var(--red);"><?= $revHausse ?></div><div class="kpi-lbl">Hausses</div><div class="kpi-sub">de loyer</div></div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card"><div class="kpi-icon" style="background:#d1fae5;"><i class="fa fa-arrow-trend-down" style="color:var(--green);"></i></div><div><div class="kpi-val" style="color:var(--green);"><?= $revBaisse ?></div><div class="kpi-lbl">Baisses</div><div class="kpi-sub">de loyer</div></div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card"><div class="kpi-icon" style="background:#eef2fb;"><i class="fa fa-file-contract" style="color:var(--marine);"></i></div><div><div class="kpi-val" style="color:var(--marine);"><?= count($contratsActifs) ?></div><div class="kpi-lbl">Contrats</div><div class="kpi-sub">actifs</div></div></div>
        </div>
    </div>

    <div class="filter-bar mb-0">
        <form method="GET" class="d-flex align-items-center gap-2 flex-wrap w-100">
            <i class="fa fa-filter text-muted" style="font-size:13px;"></i>
            <select name="contrat_id" class="form-select form-select-sm" style="max-width:300px;border-radius:8px;" onchange="this.form.submit()">
                <option value="">— Tous les contrats —</option>
                <?php foreach($contratsActifs as $c): ?><option value="<?= $c['id'] ?>" <?= $contrat_id==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['locataire']) ?> — <?= htmlspecialchars($c['maison']) ?></option><?php endforeach; ?>
            </select>
            <?php if ($contrat_id): ?><a href="revisions_loyer.php" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;"><i class="fa fa-times"></i></a><?php endif; ?>
            <div class="ms-auto text-muted small"><?= count($historique) ?> résultat<?= count($historique)>1?'s':'' ?></div>
        </form>
    </div>

</div><!-- /top-fixed -->
<div class="bottom-scroll">

<?php if (empty($historique)): ?>
<div class="text-center text-muted py-5"><i class="fa fa-inbox fa-3x mb-3 d-block" style="opacity:.2;"></i>Aucune révision enregistrée.</div>
<?php else: ?>

<table class="table mb-0 tbl-full">
    <thead><tr>
        <th style="width:14%;">Date</th>
        <th style="width:24%;">Locataire / Maison</th>
        <th class="text-end" style="width:14%;">Ancien loyer</th>
        <th class="text-end" style="width:14%;">Nouveau loyer</th>
        <th style="width:10%;">Variation</th>
        <th style="width:14%;">Motif</th>
        <th style="width:10%;">Effectué par</th>
    </tr></thead>
    <tbody>
    <?php foreach ($historique as $r):
        $diff = $r['nouveau_loyer'] - $r['ancien_loyer'];
        $pct  = $r['ancien_loyer'] > 0 ? round(($diff / $r['ancien_loyer']) * 100, 1) : 0;
        $up   = $diff >= 0;
    ?>
    <tr>
        <td class="text-muted small"><?= date('d/m/Y H:i', strtotime($r['created_at'])) ?></td>
        <td>
            <div class="fw-semibold" style="color:#2d3a55;"><?= htmlspecialchars($r['locataire']) ?></div>
            <div class="text-muted" style="font-size:11px;"><?= htmlspecialchars($r['maison']) ?></div>
        </td>
        <td class="text-end text-muted small"><?= number_format($r['ancien_loyer'],0,',',' ') ?> FCFA</td>
        <td class="text-end fw-bold <?= $up?'text-danger':'text-success' ?>"><?= number_format($r['nouveau_loyer'],0,',',' ') ?> FCFA</td>
        <td><span class="badge <?= $up?'badge-up':'badge-down' ?> small"><?= $up?'+':'' ?><?= $pct ?> %</span></td>
        <td class="text-muted small"><?= htmlspecialchars($r['motif'] ?: '—') ?></td>
        <td class="small"><?= htmlspecialchars($r['effectue_par_nom'] ?? 'Système') ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php endif; ?>

</div><!-- /bottom-scroll -->
</div><!-- /main-content -->

<script src="../js/bootstrap.bundle.min.js"></script>
<?php if (isset($_SESSION['role']) && $_SESSION['role']==='admin'): ?>
<script>
var sel = document.getElementById('selectContratRev');
if (sel) {
    sel.addEventListener('change', function() {
        var loyer = this.options[this.selectedIndex].dataset.loyer ?? '';
        document.getElementById('ancienLoyerAffiche').value = loyer ? new Intl.NumberFormat('fr-FR').format(loyer)+' FCFA' : '';
    });
    sel.dispatchEvent(new Event('change'));
}
</script>
<?php endif; ?>
</body>
</html>
