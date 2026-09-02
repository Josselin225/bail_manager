<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once('../config/db.php');

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }

$contratsActifs = $pdo->query(
    "SELECT c.id, l.nom AS locataire, m.designation AS maison FROM contrats c JOIN locataires l ON c.locataire_id=l.id JOIN maisons m ON c.maison_id=m.id WHERE c.statut_contrat='actif' ORDER BY l.nom"
)->fetchAll();

$filtre_contrat = isset($_GET['contrat_id']) ? (int)$_GET['contrat_id'] : 0;
$filtre_type    = trim($_GET['type'] ?? '');

$where = "WHERE 1=1"; $params = [];
if ($filtre_contrat) { $where .= " AND ch.contrat_id = ?"; $params[] = $filtre_contrat; }
if ($filtre_type)    { $where .= " AND ch.type_charge = ?"; $params[] = $filtre_type; }

$stmtList = $pdo->prepare("SELECT ch.*, l.nom AS locataire, m.designation AS maison FROM charges_locatives ch JOIN contrats c ON ch.contrat_id=c.id JOIN locataires l ON c.locataire_id=l.id JOIN maisons m ON c.maison_id=m.id $where ORDER BY ch.date_charge DESC LIMIT 200");
$stmtList->execute($params);
$charges = $stmtList->fetchAll();

$stmtTotal = $pdo->prepare("SELECT COALESCE(SUM(ch.montant),0) FROM charges_locatives ch $where"); $stmtTotal->execute($params);
$totalCharges = (float)$stmtTotal->fetchColumn();

$nbCharges = (int)$pdo->query("SELECT COUNT(*) FROM charges_locatives")->fetchColumn();
$totalAll  = (float)$pdo->query("SELECT COALESCE(SUM(montant),0) FROM charges_locatives")->fetchColumn();

$typesCharges = ['Eau','Électricité','Ordures','Entretien','Réparation','Copropriété','Autre'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../favicon.svg">
    <title>Charges locatives — BailManager</title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/fontawesome/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        :root { --marine:#002147; --green:#059669; --amber:#d97706; --red:#e53e3e; }
        .main-content  { background:#f4f7fe; height:calc(100vh - var(--tb-h, 60px)); display:flex; flex-direction:column; overflow:hidden; }
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
    </style>
</head>
<body>
<?php include('../includes/sidebar.php'); ?>

<!-- MODAL AJOUT -->
<div class="modal fade" id="modalCharge" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content border-0 shadow-lg" action="../php/add_charge.php" method="POST">
            <input type="hidden" name="token" value="<?= csrf_generate() ?>">
            <div class="modal-header text-white border-0" style="background:linear-gradient(135deg,#002147,#004080);">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-white bg-opacity-10 d-flex align-items-center justify-content-center" style="width:40px;height:40px;"><i class="fa fa-bolt text-white"></i></div>
                    <div><h5 class="modal-title fw-bold mb-0">Ajouter une charge</h5><small class="opacity-75">Charge locative du contrat</small></div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 py-4">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">Contrat concerné <span class="text-danger">*</span></label>
                        <div class="input-group"><span class="input-group-text bg-light border-end-0"><i class="fa fa-file-contract text-muted"></i></span>
                        <select name="contrat_id" class="form-select border-start-0" style="border-radius:0 .375rem .375rem 0" required>
                            <option value="">— Sélectionner —</option>
                            <?php foreach ($contratsActifs as $c): ?><option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['locataire']) ?> — <?= htmlspecialchars($c['maison']) ?></option><?php endforeach; ?>
                        </select></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">Type <span class="text-danger">*</span></label>
                        <select name="type_charge" class="form-select" required><?php foreach($typesCharges as $t): ?><option value="<?= $t ?>"><?= $t ?></option><?php endforeach; ?></select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">Montant (FCFA) <span class="text-danger">*</span></label>
                        <div class="input-group"><span class="input-group-text bg-light border-end-0"><i class="fa fa-money-bill text-muted"></i></span><input type="number" name="montant" class="form-control border-start-0 ps-0" min="100" step="100" required></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">Date <span class="text-danger">*</span></label>
                        <div class="input-group"><span class="input-group-text bg-light border-end-0"><i class="fa fa-calendar text-muted"></i></span><input type="date" name="date_charge" class="form-control border-start-0 ps-0" value="<?= date('Y-m-d') ?>" required></div>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">Description</label>
                        <input type="text" name="description" class="form-control" placeholder="Ex: Facture eau Août…">
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

<div class="main-content">
<div class="top-fixed">

    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <div>
            <p class="text-muted small mb-0 mt-1"><?= $nbCharges ?> charge<?= $nbCharges>1?'s':'' ?> enregistrée<?= $nbCharges>1?'s':'' ?></p>
        </div>
        <button class="btn btn-danger btn-sm shadow-sm" style="border-radius:8px;" data-bs-toggle="modal" data-bs-target="#modalCharge">
            <i class="fa fa-plus-circle me-2"></i>Ajouter une charge
        </button>
    </div>


    <div class="row g-2 mb-3">
        <div class="col-6 col-md-3">
            <div class="kpi-card"><div class="kpi-icon" style="background:#eef2fb;"><i class="fa fa-bolt" style="color:var(--marine);"></i></div><div><div class="kpi-val" style="color:var(--marine);"><?= $nbCharges ?></div><div class="kpi-lbl">Charges</div><div class="kpi-sub">enregistrées</div></div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card"><div class="kpi-icon" style="background:#fee2e2;"><i class="fa fa-coins" style="color:var(--red);"></i></div><div><div class="kpi-val" style="color:var(--red);"><?= number_format($totalAll,0,',',' ') ?></div><div class="kpi-lbl">Total global</div><div class="kpi-sub">FCFA</div></div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card"><div class="kpi-icon" style="background:#fef3c7;"><i class="fa fa-filter" style="color:var(--amber);"></i></div><div><div class="kpi-val" style="color:var(--amber);"><?= number_format($totalCharges,0,',',' ') ?></div><div class="kpi-lbl">Filtré</div><div class="kpi-sub">FCFA</div></div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card"><div class="kpi-icon" style="background:#d1fae5;"><i class="fa fa-file-contract" style="color:var(--green);"></i></div><div><div class="kpi-val" style="color:var(--green);"><?= count($contratsActifs) ?></div><div class="kpi-lbl">Contrats</div><div class="kpi-sub">actifs</div></div></div>
        </div>
    </div>

    <div class="filter-bar mb-0">
        <form method="GET" class="d-flex align-items-center gap-2 flex-wrap w-100">
            <i class="fa fa-filter text-muted" style="font-size:13px;"></i>
            <select name="contrat_id" class="form-select form-select-sm" style="max-width:260px;border-radius:8px;" onchange="this.form.submit()">
                <option value="">— Tous les contrats —</option>
                <?php foreach($contratsActifs as $c): ?><option value="<?= $c['id'] ?>" <?= $filtre_contrat==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['locataire']) ?> — <?= htmlspecialchars($c['maison']) ?></option><?php endforeach; ?>
            </select>
            <select name="type" class="form-select form-select-sm" style="max-width:150px;border-radius:8px;" onchange="this.form.submit()">
                <option value="">— Tous types —</option>
                <?php foreach($typesCharges as $t): ?><option value="<?= $t ?>" <?= $filtre_type===$t?'selected':'' ?>><?= $t ?></option><?php endforeach; ?>
            </select>
            <?php if ($filtre_contrat || $filtre_type): ?>
            <a href="charges_locatives.php" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;"><i class="fa fa-times"></i></a>
            <?php endif; ?>
            <div class="ms-auto text-muted small"><?= count($charges) ?> résultat<?= count($charges)>1?'s':'' ?></div>
        </form>
    </div>

</div><!-- /top-fixed -->
<div class="bottom-scroll">

<?php if (empty($charges)): ?>
<div class="text-center text-muted py-5"><i class="fa fa-inbox fa-3x mb-3 d-block" style="opacity:.2;"></i>Aucune charge enregistrée.</div>
<?php else: ?>

<table class="table mb-0 tbl-full">
    <thead><tr>
        <th style="width:12%;">Date</th>
        <th style="width:28%;">Locataire / Maison</th>
        <th style="width:14%;">Type</th>
        <th class="text-end" style="width:14%;">Montant</th>
        <th style="width:26%;">Description</th>
        <?php if (isset($_SESSION['role']) && $_SESSION['role']==='admin'): ?><th class="text-center" style="width:6%;">Action</th><?php endif; ?>
    </tr></thead>
    <tbody>
    <?php foreach ($charges as $ch): ?>
    <tr>
        <td class="text-muted small"><?= date('d/m/Y', strtotime($ch['date_charge'])) ?></td>
        <td>
            <div class="fw-semibold" style="color:#2d3a55;"><?= htmlspecialchars($ch['locataire']) ?></div>
            <div class="text-muted" style="font-size:11px;"><?= htmlspecialchars($ch['maison']) ?></div>
        </td>
        <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($ch['type_charge']) ?></span></td>
        <td class="text-end fw-bold" style="color:var(--red);"><?= number_format($ch['montant'],0,',',' ') ?> <small class="text-muted fw-normal">FCFA</small></td>
        <td class="text-muted small"><?= htmlspecialchars($ch['description'] ?: '—') ?></td>
        <?php if (isset($_SESSION['role']) && $_SESSION['role']==='admin'): ?>
        <td class="text-center">
            <form action="../php/delete_charge.php" method="POST" style="display:inline" onsubmit="return confirm('Supprimer ?')">
                <input type="hidden" name="token" value="<?= csrf_generate() ?>">
                <input type="hidden" name="id" value="<?= $ch['id'] ?>">
                <button type="submit" class="btn btn-sm btn-outline-danger" style="border-radius:6px;"><i class="fa fa-trash"></i></button>
            </form>
        </td>
        <?php endif; ?>
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
