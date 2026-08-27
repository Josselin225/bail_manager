<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once('../config/db.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$filtreUser = trim($_GET['user']    ?? '');
$filtreDate = trim($_GET['date_op'] ?? '');
$filtreMois = trim($_GET['mois']    ?? '');
$namedParams  = [];
$whereClauses = [];
if ($filtreUser) { $whereClauses[] = "effectue_par = :user";           $namedParams[':user']    = $filtreUser; }
if ($filtreMois) { $whereClauses[] = "DATE_FORMAT(date_operation,'%Y-%m') = :mois"; $namedParams[':mois'] = $filtreMois; }
elseif ($filtreDate) { $whereClauses[] = "DATE(date_operation) = :date_op"; $namedParams[':date_op'] = $filtreDate; }
$whereSql = $whereClauses ? "WHERE " . implode(" AND ", $whereClauses) : "";

$perPage    = 5;
$page       = max(1, (int)($_GET['page'] ?? 1));
$stmtCount  = $pdo->prepare("SELECT COUNT(*) FROM mouvements_caisse_entreprise $whereSql");
$stmtCount->execute($namedParams);
$totalRows  = (int)$stmtCount->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

$stmtMouv = $pdo->prepare("SELECT * FROM mouvements_caisse_entreprise $whereSql ORDER BY date_operation DESC LIMIT :lim OFFSET :off");
foreach ($namedParams as $k => $v) $stmtMouv->bindValue($k, $v);
$stmtMouv->bindValue(':lim', $perPage, PDO::PARAM_INT);
$stmtMouv->bindValue(':off', $offset,  PDO::PARAM_INT);
$stmtMouv->execute();
$mouvements = $stmtMouv->fetchAll();

$soldeNet     = (float)$pdo->query("SELECT COALESCE(SUM(montant),0)      FROM mouvements_caisse_entreprise")->fetchColumn();
$totalEntrees = (float)$pdo->query("SELECT COALESCE(SUM(montant),0)      FROM mouvements_caisse_entreprise WHERE montant>0")->fetchColumn();
$totalSorties = (float)$pdo->query("SELECT COALESCE(SUM(ABS(montant)),0) FROM mouvements_caisse_entreprise WHERE montant<0")->fetchColumn();
$nbOps        = (int)  $pdo->query("SELECT COUNT(*)                      FROM mouvements_caisse_entreprise")->fetchColumn();

$usersList = $pdo->query("SELECT DISTINCT effectue_par FROM mouvements_caisse_entreprise WHERE effectue_par IS NOT NULL ORDER BY effectue_par")->fetchAll(PDO::FETCH_COLUMN);

$periodeOptions = [3 => '3 mois', 6 => '6 mois', 12 => '12 mois'];
$periodeRaw = (int)($_GET['periode'] ?? 6);
$periode    = in_array($periodeRaw, array_keys($periodeOptions)) ? $periodeRaw : 6;

$chartRaw = $pdo->query(
    "SELECT DATE_FORMAT(date_operation,'%Y-%m') AS mois,
            SUM(CASE WHEN montant>0 THEN montant ELSE 0 END) AS entrees,
            SUM(CASE WHEN montant<0 THEN ABS(montant) ELSE 0 END) AS sorties
     FROM mouvements_caisse_entreprise
     WHERE date_operation >= DATE_SUB(NOW(), INTERVAL $periode MONTH)
     GROUP BY mois ORDER BY mois"
)->fetchAll();
$chartLabels = $chartEntrees = $chartSorties = [];
foreach ($chartRaw as $r) {
    $dt = DateTime::createFromFormat('Y-m', $r['mois']);
    $chartLabels[]  = $dt ? $dt->format('M Y') : $r['mois'];
    $chartEntrees[] = (float)$r['entrees'];
    $chartSorties[] = (float)$r['sorties'];
}

$csrfToken = csrf_generate();
$jCaisseLabels  = json_encode($chartLabels  ?: [], JSON_UNESCAPED_UNICODE) ?: '[]';
$jCaisseEntrees = json_encode($chartEntrees ?: []) ?: '[]';
$jCaisseSorties = json_encode($chartSorties ?: []) ?: '[]';

function buildUrlC(array $extra = []): string {
    global $filtreUser, $filtreDate, $filtreMois, $page, $periode;
    $p = array_filter(['user' => $filtreUser, 'date_op' => $filtreDate, 'mois' => $filtreMois, 'page' => $page, 'periode' => $periode]);
    return '?' . http_build_query(array_merge($p, $extra));
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../favicon.svg">
    <title>Caisse Entreprise — BailManager</title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/fontawesome/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <script src="../js/chart.min.js"></script>
    <style>
        :root { --marine:#002147; --red:#e53e3e; --green:#059669; --amber:#d97706; }
        .main-content { background:#f4f7fe; min-height:100vh; padding:24px 28px; }
        .kpi-card { background:#fff; border-radius:14px; padding:18px 20px; display:flex; align-items:center; gap:14px; box-shadow:0 2px 10px rgba(0,0,0,.06); border:1px solid #e8ecf4; height:100%; }
        .kpi-icon { width:48px; height:48px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:18px; flex-shrink:0; }
        .kpi-val  { font-size:1.25rem; font-weight:800; line-height:1.1; }
        .kpi-lbl  { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.05em; color:#8896b0; margin-top:2px; }
        .kpi-sub  { font-size:11px; color:#aab; margin-top:1px; }
        .filter-bar { background:#fff; border-radius:12px; padding:14px 18px; display:flex; align-items:center; gap:10px; box-shadow:0 2px 8px rgba(0,0,0,.05); border:1px solid #e8ecf4; flex-wrap:wrap; }
        .chart-card { background:#fff; border-radius:14px; box-shadow:0 2px 10px rgba(0,0,0,.06); border:1px solid #e8ecf4; }
        .chart-hdr  { padding:14px 18px 10px; border-bottom:1px solid #f0f3fa; }
        .chart-hdr .ttl { font-size:13px; font-weight:700; color:#2d3a55; }
        .chart-hdr .sub { font-size:11px; color:#8896b0; margin-top:1px; }
        .tbl-card { background:#fff; border-radius:14px; box-shadow:0 2px 10px rgba(0,0,0,.06); border:1px solid #e8ecf4; overflow:hidden; }
        .tbl-card thead th { background:#f8faff; color:#6b7a99; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; padding:11px 16px; border-bottom:1px solid #e8ecf4; }
        .tbl-card tbody td { padding:12px 16px; border-bottom:1px solid #f0f3fa; vertical-align:middle; font-size:13px; }
        .tbl-card tbody tr:last-child td { border-bottom:none; }
        .tbl-card tbody tr:hover td { background:#f8faff; }
        .op-badge  { font-size:11px; font-weight:600; padding:4px 10px; border-radius:20px; display:inline-block; }
        .op-entree { background:#d1fae5; color:#065f46; }
        .op-sortie { background:#fee2e2; color:#7f1d1d; }
        .amt-pos { color:var(--green); font-weight:700; }
        .amt-neg { color:var(--red);   font-weight:700; }
        .user-pill { display:inline-flex; align-items:center; gap:5px; font-size:12px; font-weight:600; padding:3px 10px; border-radius:20px; background:#eef2fb; color:var(--marine); }
        .pag { display:flex; align-items:center; gap:4px; }
        .pag a, .pag span { display:inline-flex; align-items:center; justify-content:center; width:30px; height:30px; border-radius:8px; font-size:12px; font-weight:600; text-decoration:none; border:1.5px solid #e0e6f0; color:#6b7a99; }
        .pag a:hover { border-color:var(--marine); color:var(--marine); }
        .pag span.cur { background:var(--marine); border-color:var(--marine); color:#fff; }
        .pag a.off { opacity:.35; pointer-events:none; }
        .solde-positive { color:var(--green); }
        .solde-negative { color:var(--red); }
        @media print { .app-sidebar,.app-topbar,.no-print{display:none!important} body{padding-top:0!important} .main-content{margin-left:0!important;width:100%!important;padding:0!important} }

        /* ── Modal overlay ── */
        #modalRetrait {
            position: fixed;
            top: 0; right: 0; bottom: 0; left: 0;
            background: rgba(0,0,0,.55);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            visibility: hidden;
            opacity: 0;
            pointer-events: none;
        }
        #modalRetrait.is-open {
            visibility: visible;
            opacity: 1;
            pointer-events: auto;
        }
        #modalRetrait .modal-box {
            background: #fff;
            border-radius: 12px;
            width: 90%;
            max-width: 480px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,.35);
        }
    </style>
</head>
<body>
<?php include('../includes/sidebar.php'); ?>
<script src="../js/bootstrap.bundle.min.js"></script>
<script>
function closeModal() {
    document.getElementById('modalRetrait').classList.remove('is-open');
    document.body.style.overflow = '';
}
document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeModal(); });
document.addEventListener('DOMContentLoaded', function() {
    var m = document.getElementById('modalRetrait');
    if (m) m.addEventListener('click', function(e) { if (e.target === this) closeModal(); });
});
</script>
<?php if (!empty($chartLabels)): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof Chart === 'undefined') return;
    var ctx = document.getElementById('chartCaisse');
    if (!ctx) return;
    var fmt = function(v) { return new Intl.NumberFormat('fr-FR').format(v); };
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= $jCaisseLabels ?>,
            datasets: [
                { label:'Entrées', data: <?= $jCaisseEntrees ?>, backgroundColor:'rgba(0,33,71,.75)',   borderColor:'#002147', borderWidth:1, borderRadius:6 },
                { label:'Sorties', data: <?= $jCaisseSorties ?>, backgroundColor:'rgba(217,119,6,.7)',  borderColor:'#d97706', borderWidth:1, borderRadius:6 }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { position:'top', align:'end', labels:{ boxWidth:12, font:{size:11} } },
                tooltip: { callbacks: { label: function(c){ return c.dataset.label+' : '+fmt(c.parsed.y)+' FCFA'; } } }
            },
            scales: { y: { beginAtZero:true, grid:{color:'rgba(0,0,0,.04)'} }, x: { grid:{display:false} } }
        }
    });
});
</script>
<?php endif; ?>

<!-- ══ MODAL RETRAIT ══ -->
<div id="modalRetrait" role="dialog" aria-modal="true" aria-labelledby="titreSortie">
    <div class="modal-box">
        <form method="POST" action="../php/add_retrait_agence.php">
            <input type="hidden" name="token" value="<?= htmlspecialchars($csrfToken) ?>">
            <div style="background:linear-gradient(135deg,#7f1d1d,#dc2626);padding:18px 22px;display:flex;align-items:center;justify-content:space-between;border-radius:12px 12px 0 0;">
                <div style="display:flex;align-items:center;gap:12px;">
                    <div style="width:40px;height:40px;border-radius:50%;background:rgba(255,255,255,.15);display:flex;align-items:center;justify-content:center;">
                        <i class="fa fa-cash-register" style="color:#fff;"></i>
                    </div>
                    <div>
                        <div id="titreSortie" style="color:#fff;font-weight:700;font-size:15px;">Sortie de Caisse</div>
                        <div style="color:rgba(255,255,255,.75);font-size:12px;">Agence — retrait de fonds</div>
                    </div>
                </div>
                <button type="button" id="btnFermerRetrait" onclick="document.getElementById('modalRetrait').classList.remove('is-open');document.body.style.overflow='';" style="background:none;border:none;color:#fff;font-size:22px;cursor:pointer;line-height:1;">&times;</button>
            </div>
            <div style="padding:24px 22px;">
                <div style="margin-bottom:16px;">
                    <label for="inputMotif" style="display:block;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:#8896b0;margin-bottom:6px;">Motif <span style="color:#e53e3e;">*</span></label>
                    <div style="display:flex;border:1.5px solid #dee2e6;border-radius:8px;overflow:hidden;">
                        <span style="background:#f8f9fa;padding:8px 12px;display:flex;align-items:center;border-right:1.5px solid #dee2e6;"><i class="fa fa-tag" style="color:#8896b0;font-size:13px;"></i></span>
                        <input type="text" id="inputMotif" name="motif" maxlength="150" style="flex:1;border:none;padding:8px 12px;font-size:14px;outline:none;" placeholder="Ex : Achat fournitures, Facture loyer…" required>
                    </div>
                </div>
                <div style="margin-bottom:16px;">
                    <label for="inputMontant" style="display:block;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:#8896b0;margin-bottom:6px;">Montant (FCFA) <span style="color:#e53e3e;">*</span></label>
                    <div style="display:flex;border:1.5px solid #dee2e6;border-radius:8px;overflow:hidden;">
                        <span style="background:#f8f9fa;padding:8px 12px;display:flex;align-items:center;border-right:1.5px solid #dee2e6;"><i class="fa fa-money-bill" style="color:#8896b0;font-size:13px;"></i></span>
                        <input type="number" id="inputMontant" name="montant" min="1" step="1" style="flex:1;border:none;padding:8px 12px;font-size:14px;outline:none;" placeholder="Ex : 50 000" required>
                    </div>
                </div>
                <div>
                    <label for="inputCommentaire" style="display:block;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:#8896b0;margin-bottom:6px;">Note (optionnel)</label>
                    <div style="display:flex;border:1.5px solid #dee2e6;border-radius:8px;overflow:hidden;">
                        <span style="background:#f8f9fa;padding:8px 12px;display:flex;align-items:center;border-right:1.5px solid #dee2e6;"><i class="fa fa-pen" style="color:#8896b0;font-size:13px;"></i></span>
                        <input type="text" id="inputCommentaire" name="commentaire" maxlength="255" style="flex:1;border:none;padding:8px 12px;font-size:14px;outline:none;" placeholder="Précision supplémentaire…">
                    </div>
                </div>
            </div>
            <div style="padding:14px 22px;background:#f8f9fa;border-top:1px solid #e8ecf4;display:flex;justify-content:flex-end;gap:10px;border-radius:0 0 12px 12px;">
                <button type="button" id="btnAnnulerRetrait" onclick="document.getElementById('modalRetrait').classList.remove('is-open');document.body.style.overflow='';" style="padding:8px 20px;border:1.5px solid #dee2e6;border-radius:8px;background:#fff;font-weight:600;font-size:14px;cursor:pointer;">
                    <i class="fa fa-times" style="margin-right:6px;"></i>Annuler
                </button>
                <button type="submit" style="padding:8px 28px;background:#dc2626;border:none;border-radius:8px;color:#fff;font-weight:600;font-size:14px;cursor:pointer;">
                    <i class="fa fa-check" style="margin-right:6px;"></i>Confirmer
                </button>
            </div>
        </form>
    </div>
</div>

<div class="main-content">

    <!-- En-tête -->
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <p class="text-muted small mb-0 mt-1">Flux financiers internes — recettes et dépenses</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <?php
            $rapportParams = [];
            if ($filtreUser) $rapportParams['user'] = $filtreUser;
            if ($filtreMois) {
                $rapportParams['date_debut'] = $filtreMois . '-01';
                $rapportParams['date_fin']   = date('Y-m-t', strtotime($filtreMois . '-01'));
            } elseif ($filtreDate) {
                $rapportParams['date_debut'] = $filtreDate;
                $rapportParams['date_fin']   = $filtreDate;
            }
            $rapportUrl = 'rapport_caisse.php' . ($rapportParams ? '?' . http_build_query($rapportParams) : '');
            ?>
            <a href="<?= htmlspecialchars($rapportUrl) ?>" class="btn btn-sm btn-outline-dark shadow-sm" style="border-radius:8px;">
                <i class="fa fa-print me-2"></i>Rapport détaillé
            </a>
            <button type="button" class="btn btn-sm btn-danger shadow-sm" style="border-radius:8px;" id="btnOuvrirRetrait"
                    onclick="document.getElementById('modalRetrait').classList.add('is-open');document.body.style.overflow='hidden';">
                <i class="fa fa-minus-circle me-2"></i>Nouveau retrait
            </button>
        </div>
    </div>


    <!-- KPI -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="kpi-card">
                <div class="kpi-icon" style="background:#d1fae5;"><i class="fa fa-arrow-down" style="color:var(--green);"></i></div>
                <div>
                    <div class="kpi-val" style="color:var(--green);"><?= number_format($totalEntrees,0,',',' ') ?></div>
                    <div class="kpi-lbl">Total entrées</div><div class="kpi-sub">FCFA</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card">
                <div class="kpi-icon" style="background:#fee2e2;"><i class="fa fa-arrow-up" style="color:var(--red);"></i></div>
                <div>
                    <div class="kpi-val" style="color:var(--red);"><?= number_format($totalSorties,0,',',' ') ?></div>
                    <div class="kpi-lbl">Total sorties</div><div class="kpi-sub">FCFA</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card">
                <div class="kpi-icon" style="background:<?= $soldeNet>=0?'#d1fae5':'#fee2e2' ?>;"><i class="fa fa-wallet" style="color:<?= $soldeNet>=0?'var(--green)':'var(--red)' ?>;"></i></div>
                <div>
                    <div class="kpi-val <?= $soldeNet>=0?'solde-positive':'solde-negative' ?>"><?= ($soldeNet<0?'-':'').number_format(abs($soldeNet),0,',',' ') ?></div>
                    <div class="kpi-lbl">Solde disponible</div><div class="kpi-sub">FCFA</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card">
                <div class="kpi-icon" style="background:#eef2fb;"><i class="fa fa-list-check" style="color:var(--marine);"></i></div>
                <div>
                    <div class="kpi-val" style="color:var(--marine);"><?= $nbOps ?></div>
                    <div class="kpi-lbl">Opérations</div><div class="kpi-sub">au total</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtre -->
    <div class="filter-bar mb-4 no-print">
        <form method="GET" class="d-flex align-items-center gap-2 flex-wrap w-100">
            <i class="fa fa-filter text-muted" style="font-size:13px;"></i>
            <select name="user" class="form-select form-select-sm" style="max-width:200px;border-radius:8px;" onchange="this.form.submit()">
                <option value="">— Tous les utilisateurs —</option>
                <?php foreach ($usersList as $u): ?>
                <option value="<?= htmlspecialchars($u) ?>" <?= $filtreUser===$u?'selected':'' ?>><?= htmlspecialchars($u) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="month" name="mois" class="form-control form-control-sm" style="max-width:150px;border-radius:8px;" value="<?= htmlspecialchars($filtreMois) ?>" onchange="this.form.date_op.value='';this.form.submit()">
            <input type="date" name="date_op" class="form-control form-control-sm" style="max-width:160px;border-radius:8px;" value="<?= htmlspecialchars($filtreDate) ?>" onchange="this.form.mois.value='';this.form.submit()">
            <?php if ($filtreUser || $filtreDate || $filtreMois): ?>
            <a href="caisse_entreprise.php" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;"><i class="fa fa-times"></i> Réinitialiser</a>
            <?php endif; ?>
            <div class="ms-auto text-muted small"><?= $totalRows ?> opération<?= $totalRows>1?'s':'' ?></div>
        </form>
    </div>

    <!-- Graphique -->
    <div class="chart-card mb-4">
        <div class="chart-hdr d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <div class="ttl"><i class="fa fa-chart-bar me-2" style="color:var(--marine);"></i>Flux des <?= $periode ?> derniers mois</div>
                <div class="sub">Entrées encaissées vs sorties réglées</div>
            </div>
            <div class="d-flex gap-1 no-print">
                <?php foreach ($periodeOptions as $val => $label): ?>
                <a href="<?= buildUrlC(['periode'=>$val,'page'=>1]) ?>"
                   style="font-size:11px;font-weight:600;padding:4px 12px;border-radius:20px;text-decoration:none;<?= $periode===$val?'background:var(--marine);color:#fff;':'background:#eef2fb;color:var(--marine);' ?>">
                    <?= $label ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php if (empty($chartLabels)): ?>
        <div style="padding:16px 20px;display:flex;align-items:center;justify-content:center;color:#8896b0;text-align:center;min-height:180px;">
            <div>
                <i class="fa fa-chart-bar fa-2x mb-2 d-block" style="opacity:.25;"></i>
                Aucune opération sur les <?= $periode ?> derniers mois
            </div>
        </div>
        <?php else: ?>
        <div style="height:240px;">
            <canvas id="chartCaisse"></canvas>
        </div>
        <?php endif; ?>
    </div>

    <!-- Tableau -->
    <div class="tbl-card">
        <div style="padding:14px 18px 10px;border-bottom:1px solid #f0f3fa;display:flex;align-items:center;justify-content:space-between;">
            <div style="font-size:13px;font-weight:700;color:#2d3a55;">
                <i class="fa fa-clock-rotate-left me-2" style="color:var(--marine);"></i>Historique des opérations
            </div>
        </div>
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Date</th><th>Type</th><th>Description</th><th>Effectué par</th><th class="text-end">Montant</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($mouvements)): ?>
                <tr><td colspan="5" class="text-center text-muted py-5">
                    <i class="fa fa-inbox fa-2x mb-2 d-block" style="opacity:.3;"></i>Aucune opération trouvée.
                </td></tr>
                <?php else: foreach ($mouvements as $m): $isEntree = $m['montant'] >= 0; ?>
                <tr>
                    <td>
                        <div class="fw-semibold small" style="color:#2d3a55;"><?= date('d/m/Y', strtotime($m['date_operation'])) ?></div>
                        <div class="text-muted" style="font-size:10px;"><?= date('H:i', strtotime($m['date_operation'])) ?></div>
                    </td>
                    <td>
                        <span class="op-badge <?= $isEntree?'op-entree':'op-sortie' ?>">
                            <i class="fa fa-<?= $isEntree?'circle-plus':'circle-minus' ?> me-1"></i><?= $isEntree?'Entrée':'Sortie' ?>
                        </span>
                    </td>
                    <td>
                        <div class="fw-semibold small" style="color:#2d3a55;"><?= htmlspecialchars($m['type_mouvement']??'') ?></div>
                        <?php if (!empty($m['commentaire'])): ?>
                        <div class="text-muted" style="font-size:11px;"><?= htmlspecialchars($m['commentaire']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><span class="user-pill"><i class="fa fa-user-circle"></i><?= htmlspecialchars($m['effectue_par']??'Admin') ?></span></td>
                    <td class="text-end">
                        <span class="<?= $isEntree?'amt-pos':'amt-neg' ?>"><?= $isEntree?'+':'-' ?><?= number_format(abs($m['montant']),0,',',' ') ?></span>
                        <small class="text-muted d-block" style="font-size:10px;">FCFA</small>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
        <div class="d-flex justify-content-between align-items-center px-4 py-3 border-top no-print" style="background:#f8faff;">
            <small class="text-muted">Page <?= $page ?> / <?= $totalPages ?> — <?= $totalRows ?> opération<?= $totalRows>1?'s':'' ?></small>
            <div class="pag">
                <a href="<?= buildUrlC(['page'=>$page-1]) ?>" class="<?= $page<=1?'off':'' ?>"><i class="fa fa-chevron-left" style="font-size:10px;"></i></a>
                <?php
                $start=max(1,$page-2); $end=min($totalPages,$page+2);
                if ($start>1) echo '<span style="border:none;width:auto;color:#aab;">…</span>';
                for ($i=$start;$i<=$end;$i++):
                ?>
                <?php if ($i===$page): ?><span class="cur"><?= $i ?></span>
                <?php else: ?><a href="<?= buildUrlC(['page'=>$i]) ?>"><?= $i ?></a>
                <?php endif; endfor;
                if ($end<$totalPages) echo '<span style="border:none;width:auto;color:#aab;">…</span>';
                ?>
                <a href="<?= buildUrlC(['page'=>$page+1]) ?>" class="<?= $page>=$totalPages?'off':'' ?>"><i class="fa fa-chevron-right" style="font-size:10px;"></i></a>
            </div>
        </div>
    </div>

</div><!-- /main-content -->
</body>
</html>
