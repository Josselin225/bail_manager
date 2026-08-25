<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once('../config/db.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$bailleur_id = isset($_GET['bailleur_id']) && is_numeric($_GET['bailleur_id']) ? (int)$_GET['bailleur_id'] : null;
$filtreDate  = trim($_GET['date_op'] ?? '');

$periodeOptions = [3 => '3 mois', 6 => '6 mois', 12 => '12 mois'];
$periodeRaw     = (int)($_GET['periode'] ?? 6);
$periode        = in_array($periodeRaw, array_keys($periodeOptions)) ? $periodeRaw : 6;

// Bailleurs
$bailleurs = $pdo->query(
    "SELECT b.id, b.nom, COALESCE(SUM(c.montant),0) AS solde
     FROM bailleurs b
     LEFT JOIN compte_courant_bailleur c ON c.bailleur_id = b.id
     GROUP BY b.id, b.nom ORDER BY b.nom"
)->fetchAll();

// WHERE clauses pour KPI (sans date)
$baseConditions = [];
$bind = [];
if ($bailleur_id) { $baseConditions[] = "bailleur_id = :bid"; $bind[':bid'] = $bailleur_id; }
$wBase = $baseConditions ? "WHERE " . implode(" AND ", $baseConditions) : "";
$wCr   = ($baseConditions ? $wBase . " AND " : "WHERE ") . "montant > 0";
$wRt   = ($baseConditions ? $wBase . " AND " : "WHERE ") . "montant < 0";
$wSd   = $wBase;

// KPI
$s = $pdo->prepare("SELECT COALESCE(SUM(montant),0)      FROM compte_courant_bailleur $wCr"); $s->execute($bind); $totalCredits  = (float)$s->fetchColumn();
$s = $pdo->prepare("SELECT COALESCE(SUM(ABS(montant)),0) FROM compte_courant_bailleur $wRt"); $s->execute($bind); $totalRetraits = (float)$s->fetchColumn();
$s = $pdo->prepare("SELECT COALESCE(SUM(montant),0)      FROM compte_courant_bailleur $wSd"); $s->execute($bind); $soldeNet      = (float)$s->fetchColumn();
$s = $pdo->prepare("SELECT COUNT(*)                      FROM compte_courant_bailleur $wSd"); $s->execute($bind); $nbOperations  = (int)$s->fetchColumn();

// Graphique (sans filtre date, avec période)
$chartWhere = $bailleur_id ? "AND bailleur_id=$bailleur_id" : "";
$chartRaw = $pdo->query(
    "SELECT DATE_FORMAT(date_operation,'%Y-%m') AS mois,
            SUM(CASE WHEN montant>0 THEN montant ELSE 0 END) AS credits,
            SUM(CASE WHEN montant<0 THEN ABS(montant) ELSE 0 END) AS retraits
     FROM compte_courant_bailleur
     WHERE date_operation >= DATE_SUB(NOW(), INTERVAL $periode MONTH) $chartWhere
     GROUP BY mois ORDER BY mois"
)->fetchAll();
$chartLabels = $chartCredits = $chartRetraits = [];
foreach ($chartRaw as $r) {
    $dt = DateTime::createFromFormat('Y-m', $r['mois']);
    $chartLabels[]   = $dt ? $dt->format('M Y') : $r['mois'];
    $chartCredits[]  = (float)$r['credits'];
    $chartRetraits[] = (float)$r['retraits'];
}

// Doughnut bailleurs
$topBailleurs = array_filter($bailleurs, fn($b) => $b['solde'] > 0);
usort($topBailleurs, fn($a,$b) => $b['solde'] <=> $a['solde']);
$top5        = array_slice($topBailleurs, 0, 5);
$autresSolde = array_sum(array_map(fn($b) => (float)$b['solde'], array_slice($topBailleurs, 5)));
$chartBailNoms  = array_column($top5, 'nom');
$chartBailSolde = array_map(fn($b) => (float)$b['solde'], $top5);
if ($autresSolde > 0) { $chartBailNoms[] = 'Autres'; $chartBailSolde[] = $autresSolde; }

// Pagination + table (avec filtre date)
$cntConds = []; $cntBind = [];
if ($bailleur_id) { $cntConds[] = "bailleur_id = :bid";           $cntBind[':bid']     = $bailleur_id; }
if ($filtreDate)  { $cntConds[] = "DATE(date_operation) = :date_op"; $cntBind[':date_op'] = $filtreDate; }
$cntWhere = $cntConds ? "WHERE " . implode(" AND ", $cntConds) : "";

$perPage    = 8;
$page       = max(1, (int)($_GET['page'] ?? 1));
$s = $pdo->prepare("SELECT COUNT(*) FROM compte_courant_bailleur $cntWhere");
$s->execute($cntBind);
$totalRows  = (int)$s->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

$movConds = []; $movBind = [];
if ($bailleur_id) { $movConds[] = "c.bailleur_id = :bid";           $movBind[':bid']     = $bailleur_id; }
if ($filtreDate)  { $movConds[] = "DATE(c.date_operation) = :date_op"; $movBind[':date_op'] = $filtreDate; }
$movWhere = $movConds ? "WHERE " . implode(" AND ", $movConds) : "";

$sm = $pdo->prepare(
    "SELECT c.*, b.nom AS nom_bailleur
     FROM compte_courant_bailleur c
     JOIN bailleurs b ON c.bailleur_id = b.id
     $movWhere
     ORDER BY c.date_operation DESC
     LIMIT :lim OFFSET :off"
);
foreach ($movBind as $k => $v) $sm->bindValue($k, $v);
$sm->bindValue(':lim', $perPage, PDO::PARAM_INT);
$sm->bindValue(':off', $offset,  PDO::PARAM_INT);
$sm->execute();
$mouvements = $sm->fetchAll();

$csrfToken = csrf_generate();

// JSON pour graphiques (préparé ici, utilisé tôt dans le body)
$jLabels   = json_encode($chartLabels  ?: ['Aucune donnée'], JSON_UNESCAPED_UNICODE) ?: '["?"]';
$jCredits  = json_encode($chartCredits  ?: [0]) ?: '[0]';
$jRetraits = json_encode($chartRetraits ?: [0]) ?: '[0]';
$jBailNoms = json_encode($chartBailNoms ?: ['Aucune donnée'], JSON_UNESCAPED_UNICODE) ?: '["?"]';
$jBailSolde= json_encode($chartBailSolde ?: [1]) ?: '[1]';

function buildUrl(array $extra = []): string {
    global $bailleur_id, $page, $periode, $filtreDate;
    $p = array_filter(['bailleur_id' => $bailleur_id, 'date_op' => $filtreDate, 'periode' => $periode, 'page' => $page]);
    return '?' . http_build_query(array_merge($p, $extra));
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../favicon.svg">
    <title>Compte Courant Bailleurs — BailManager</title>
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
        .chart-card { background:#fff; border-radius:14px; box-shadow:0 2px 10px rgba(0,0,0,.06); border:1px solid #e8ecf4; overflow:hidden; }
        .chart-hdr  { padding:14px 18px 10px; border-bottom:1px solid #f0f3fa; }
        .chart-hdr .ttl { font-size:13px; font-weight:700; color:#2d3a55; }
        .chart-hdr .sub { font-size:11px; color:#8896b0; margin-top:1px; }
        .tbl-card { background:#fff; border-radius:14px; box-shadow:0 2px 10px rgba(0,0,0,.06); border:1px solid #e8ecf4; overflow:hidden; }
        .tbl-card thead th { background:#f8faff; color:#6b7a99; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; padding:11px 16px; border-bottom:1px solid #e8ecf4; }
        .tbl-card tbody td { padding:12px 16px; border-bottom:1px solid #f0f3fa; vertical-align:middle; font-size:13px; }
        .tbl-card tbody tr:last-child td { border-bottom:none; }
        .tbl-card tbody tr:hover td { background:#f8faff; }
        .op-badge   { font-size:11px; font-weight:600; padding:4px 10px; border-radius:20px; display:inline-block; }
        .op-credit  { background:#d1fae5; color:#065f46; }
        .op-retrait { background:#fee2e2; color:#7f1d1d; }
        .amt-pos { color:var(--green); font-weight:700; }
        .amt-neg { color:var(--red);   font-weight:700; }
        .bailleur-pill { display:inline-flex; align-items:center; gap:6px; font-size:12px; font-weight:600; padding:4px 10px; border-radius:20px; background:#eef2fb; color:var(--marine); }
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
            position: fixed; top:0; right:0; bottom:0; left:0;
            background: rgba(0,0,0,.55);
            z-index: 9999;
            display: flex; align-items: center; justify-content: center;
            visibility: hidden; opacity: 0; pointer-events: none;
            transition: opacity .15s ease, visibility .15s ease;
        }
        #modalRetrait.is-open { visibility: visible; opacity: 1; pointer-events: auto; }
        #modalRetrait .modal-box {
            background: #fff; border-radius: 12px;
            width: 90%; max-width: 500px; max-height: 90vh; overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,.35);
        }
    </style>
</head>
<body>
<?php include('../includes/sidebar.php'); ?>
<script src="../js/bootstrap.bundle.min.js"></script>
<script>
function closeModalBailleur() {
    document.getElementById('modalRetrait').classList.remove('is-open');
    document.body.style.overflow = '';
}
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeModalBailleur();
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof Chart === 'undefined') return;
    var elMouv = document.getElementById('chartMouvements');
    var elBail = document.getElementById('chartBailleurs');
    var dbgEl  = document.getElementById('chartJsDebug');
    if (dbgEl) dbgEl.textContent = 'elMouv:'+(elMouv?'FOUND':'NULL')+' elBail:'+(elBail?'FOUND':'NULL');
    var fmtC = function(v) { return new Intl.NumberFormat('fr-FR').format(v); };
    if (elMouv) {
        new Chart(elMouv, {
            type: 'bar',
            data: {
                labels: <?= $jLabels ?>,
                datasets: [
                    { label:'Crédits',  data: <?= $jCredits ?>,  backgroundColor:'rgba(5,150,105,.7)',  borderRadius:6 },
                    { label:'Retraits', data: <?= $jRetraits ?>, backgroundColor:'rgba(229,62,62,.65)', borderRadius:6 }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: {
                    legend: { position:'top', align:'end', labels:{ boxWidth:12, font:{size:11} } },
                    tooltip: { callbacks: { label: function(c){ return c.dataset.label+' : '+fmtC(c.parsed.y)+' FCFA'; } } }
                },
                scales: { y: { beginAtZero:true, grid:{color:'rgba(0,0,0,.04)'} }, x: { grid:{display:false} } }
            }
        });
    }
    if (elBail) {
        new Chart(elBail, {
            type: 'doughnut',
            data: {
                labels: <?= $jBailNoms ?>,
                datasets: [{ data: <?= $jBailSolde ?>, backgroundColor:['#002147','#004080','#1d4ed8','#6366f1','#a78bfa','#c4b5fd'], borderWidth:2, borderColor:'#fff', hoverOffset:6 }]
            },
            options: {
                responsive: true, maintainAspectRatio: false, cutout:'65%',
                plugins: { legend:{display:false}, tooltip:{ callbacks:{ label:function(c){ return c.label+' : '+fmtC(c.parsed)+' FCFA'; } } } }
            }
        });
    }
});
</script>

<!-- ══ MODAL RETRAIT ══ -->
<div id="modalRetrait" role="dialog" aria-modal="true" aria-labelledby="titreModalRetrait"
     onclick="if(event.target===this) closeModalBailleur();">
    <div class="modal-box">
        <form method="POST" action="../php/add_versement_bailleur.php">
            <input type="hidden" name="token" value="<?= htmlspecialchars($csrfToken) ?>">
            <div style="background:linear-gradient(135deg,#7f1d1d,#dc2626);padding:18px 22px;display:flex;align-items:center;justify-content:space-between;border-radius:12px 12px 0 0;">
                <div style="display:flex;align-items:center;gap:12px;">
                    <div style="width:40px;height:40px;border-radius:50%;background:rgba(255,255,255,.15);display:flex;align-items:center;justify-content:center;">
                        <i class="fa fa-money-bill-transfer" style="color:#fff;"></i>
                    </div>
                    <div>
                        <div id="titreModalRetrait" style="color:#fff;font-weight:700;font-size:15px;">Retrait Bailleur</div>
                        <div style="color:rgba(255,255,255,.75);font-size:12px;">Versement du solde au propriétaire</div>
                    </div>
                </div>
                <button type="button" onclick="closeModalBailleur();"
                        style="background:none;border:none;color:#fff;font-size:22px;cursor:pointer;line-height:1;">&times;</button>
            </div>
            <div style="padding:24px 22px;">
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:#8896b0;margin-bottom:6px;">
                        Bailleur <span style="color:#e53e3e;">*</span>
                    </label>
                    <div style="display:flex;border:1.5px solid #dee2e6;border-radius:8px;overflow:hidden;">
                        <span style="background:#f8f9fa;padding:8px 12px;display:flex;align-items:center;border-right:1.5px solid #dee2e6;">
                            <i class="fa fa-user-tie" style="color:#8896b0;font-size:13px;"></i>
                        </span>
                        <select name="bailleur_id" id="selectBailleur" style="flex:1;border:none;padding:8px 12px;font-size:14px;outline:none;background:#fff;" required>
                            <option value="">— Choisir un bailleur —</option>
                            <?php foreach ($bailleurs as $b): ?>
                            <option value="<?= $b['id'] ?>" <?= $bailleur_id==$b['id']?'selected':'' ?>>
                                <?= htmlspecialchars($b['nom']) ?> — Solde : <?= number_format($b['solde'],0,',',' ') ?> FCFA
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:#8896b0;margin-bottom:6px;">
                        Montant (FCFA) <span style="color:#e53e3e;">*</span>
                    </label>
                    <div style="display:flex;border:1.5px solid #dee2e6;border-radius:8px;overflow:hidden;">
                        <span style="background:#f8f9fa;padding:8px 12px;display:flex;align-items:center;border-right:1.5px solid #dee2e6;">
                            <i class="fa fa-money-bill" style="color:#8896b0;font-size:13px;"></i>
                        </span>
                        <input type="number" name="montant" min="1" step="1"
                               style="flex:1;border:none;padding:8px 12px;font-size:14px;outline:none;"
                               placeholder="Ex : 200 000" required>
                    </div>
                </div>
                <div>
                    <label style="display:block;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:#8896b0;margin-bottom:6px;">
                        Référence / Note
                    </label>
                    <div style="display:flex;border:1.5px solid #dee2e6;border-radius:8px;overflow:hidden;">
                        <span style="background:#f8f9fa;padding:8px 12px;display:flex;align-items:center;border-right:1.5px solid #dee2e6;">
                            <i class="fa fa-pen" style="color:#8896b0;font-size:13px;"></i>
                        </span>
                        <input type="text" name="commentaire"
                               style="flex:1;border:none;padding:8px 12px;font-size:14px;outline:none;"
                               placeholder="Ex : Chèque N°12345, virement du 20/05…">
                    </div>
                </div>
            </div>
            <div style="padding:14px 22px;background:#f8f9fa;border-top:1px solid #e8ecf4;display:flex;justify-content:flex-end;gap:10px;border-radius:0 0 12px 12px;">
                <button type="button" onclick="closeModalBailleur();"
                        style="padding:8px 20px;border:1.5px solid #dee2e6;border-radius:8px;background:#fff;font-weight:600;font-size:14px;cursor:pointer;">
                    <i class="fa fa-times" style="margin-right:6px;"></i>Annuler
                </button>
                <button type="submit" name="enregistrer_retrait"
                        style="padding:8px 28px;background:#dc2626;border:none;border-radius:8px;color:#fff;font-weight:600;font-size:14px;cursor:pointer;">
                    <i class="fa fa-check" style="margin-right:6px;"></i>Confirmer
                </button>
            </div>
        </form>
    </div>
</div>

<div class="main-content">

    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <p class="text-muted small mb-0 mt-1">
                <?php
                if ($bailleur_id) {
                    $nomB = '';
                    foreach ($bailleurs as $b) { if ($b['id'] == $bailleur_id) { $nomB = $b['nom']; break; } }
                    echo 'Vue filtrée — ' . htmlspecialchars($nomB);
                } else {
                    echo 'Vue globale — tous les bailleurs';
                }
                ?>
            </p>
        </div>
        <div class="d-flex gap-2 no-print flex-wrap">
            <?php if ($bailleur_id): ?>
            <a href="../php/print_releve_bailleur.php?bailleur_id=<?= $bailleur_id ?>"
               class="btn btn-sm btn-outline-secondary" style="border-radius:8px;">
                <i class="fa fa-print me-1"></i>Imprimer le relevé
            </a>
            <?php endif; ?>
            <button type="button" class="btn btn-sm btn-danger shadow-sm" style="border-radius:8px;"
                    onclick="document.getElementById('modalRetrait').classList.add('is-open');document.body.style.overflow='hidden';">
                <i class="fa fa-minus-circle me-2"></i>Enregistrer un retrait
            </button>
        </div>
    </div>


    <!-- KPI -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="kpi-card">
                <div class="kpi-icon" style="background:#d1fae5;"><i class="fa fa-arrow-down" style="color:var(--green);"></i></div>
                <div>
                    <div class="kpi-val" style="color:var(--green);"><?= number_format($totalCredits,0,',',' ') ?></div>
                    <div class="kpi-lbl">Total crédités</div>
                    <div class="kpi-sub">FCFA</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card">
                <div class="kpi-icon" style="background:#fee2e2;"><i class="fa fa-arrow-up" style="color:var(--red);"></i></div>
                <div>
                    <div class="kpi-val" style="color:var(--red);"><?= number_format($totalRetraits,0,',',' ') ?></div>
                    <div class="kpi-lbl">Total retirés</div>
                    <div class="kpi-sub">FCFA</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card">
                <div class="kpi-icon" style="background:<?= $soldeNet>=0?'#d1fae5':'#fee2e2' ?>;"><i class="fa fa-scale-balanced" style="color:<?= $soldeNet>=0?'var(--green)':'var(--red)' ?>;"></i></div>
                <div>
                    <div class="kpi-val <?= $soldeNet>=0?'solde-positive':'solde-negative' ?>"><?= ($soldeNet<0?'-':'').number_format(abs($soldeNet),0,',',' ') ?></div>
                    <div class="kpi-lbl">Solde à reverser</div>
                    <div class="kpi-sub">FCFA</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card">
                <div class="kpi-icon" style="background:#eef2fb;"><i class="fa fa-list-check" style="color:var(--marine);"></i></div>
                <div>
                    <div class="kpi-val" style="color:var(--marine);"><?= $nbOperations ?></div>
                    <div class="kpi-lbl">Opérations</div>
                    <div class="kpi-sub"><?= $bailleur_id?'ce bailleur':'au total' ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtre -->
    <div class="filter-bar mb-4 no-print">
        <form method="GET" class="d-flex align-items-center gap-2 flex-wrap w-100">
            <input type="hidden" name="periode" value="<?= $periode ?>">
            <i class="fa fa-filter text-muted" style="font-size:13px;"></i>
            <select name="bailleur_id" class="form-select form-select-sm" style="max-width:260px;border-radius:8px;" onchange="this.form.submit()">
                <option value="">— Tous les bailleurs —</option>
                <?php foreach ($bailleurs as $b): ?>
                <option value="<?= $b['id'] ?>" <?= $bailleur_id==$b['id']?'selected':'' ?>>
                    <?= htmlspecialchars($b['nom']) ?> (<?= $b['solde']>=0?'+':'' ?><?= number_format($b['solde'],0,',',' ') ?> FCFA)
                </option>
                <?php endforeach; ?>
            </select>
            <input type="date" name="date_op" class="form-control form-control-sm" style="max-width:160px;border-radius:8px;"
                   value="<?= htmlspecialchars($filtreDate) ?>" onchange="this.form.submit()">
            <?php if ($bailleur_id || $filtreDate): ?>
            <a href="compte_bailleur.php" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;">
                <i class="fa fa-times"></i> Réinitialiser
            </a>
            <?php endif; ?>
            <div class="ms-auto text-muted small"><?= $totalRows ?> opération<?= $totalRows>1?'s':'' ?></div>
        </form>
    </div>

    <!-- Graphiques -->
    <div class="row g-3 mb-4">
        <div class="col-lg-7">
            <div class="chart-card h-100">
                <div class="chart-hdr d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div>
                        <div class="ttl"><i class="fa fa-chart-bar me-2" style="color:var(--marine);"></i>Mouvements des <?= $periode ?> derniers mois</div>
                        <div class="sub">Crédits encaissés vs retraits versés</div>
                    </div>
                    <div class="d-flex gap-1 no-print">
                        <?php foreach ($periodeOptions as $val => $label): ?>
                        <a href="<?= buildUrl(['periode'=>$val,'page'=>1]) ?>"
                           style="font-size:11px;font-weight:600;padding:4px 12px;border-radius:20px;text-decoration:none;<?= $periode===$val?'background:var(--marine);color:#fff;':'background:#eef2fb;color:var(--marine);' ?>">
                            <?= $label ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php if (empty($chartLabels)): ?>
                <div style="height:260px;display:flex;align-items:center;justify-content:center;color:#8896b0;text-align:center;">
                    <div>
                        <i class="fa fa-chart-bar fa-2x mb-2 d-block" style="opacity:.25;"></i>
                        Aucune opération sur les <?= $periode ?> derniers mois<?= $bailleur_id ? '' : ' — sélectionnez un bailleur ou élargissez la période' ?>
                    </div>
                </div>
                <?php else: ?>
                <div style="height:260px;">
                    <canvas id="chartMouvements"></canvas>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="chart-card h-100">
                <div class="chart-hdr">
                    <div class="ttl"><i class="fa fa-users me-2" style="color:var(--amber);"></i>Répartition des soldes</div>
                    <div class="sub"><?= count($bailleurs) ?> bailleur<?= count($bailleurs)>1?'s':'' ?> — top 5</div>
                </div>
                <div style="padding:16px 20px;display:flex;align-items:center;justify-content:center;gap:20px;">
                    <div style="position:relative;width:180px;height:180px;flex-shrink:0;">
                        <canvas id="chartBailleurs" width="180" height="180" style="display:block;"></canvas>
                    </div>
                    <div style="font-size:11px;line-height:2;min-width:120px;">
                        <?php
                        $bailColors = ['#002147','#004080','#1d4ed8','#6366f1','#a78bfa','#c4b5fd'];
                        foreach ($chartBailNoms as $i => $nom):
                            $c = $bailColors[$i] ?? '#ccc';
                        ?>
                        <div style="display:flex;align-items:center;gap:6px;">
                            <span style="width:10px;height:10px;border-radius:3px;background:<?= $c ?>;display:inline-block;flex-shrink:0;"></span>
                            <span style="overflow:hidden;text-overflow:ellipsis;max-width:130px;" title="<?= htmlspecialchars($nom) ?>"><?= htmlspecialchars($nom) ?></span>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($chartBailNoms)): ?><span class="text-muted">Aucune donnée</span><?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tableau -->
    <div class="tbl-card">
        <div style="padding:14px 18px 10px;border-bottom:1px solid #f0f3fa;display:flex;align-items:center;justify-content:space-between;">
            <div style="font-size:13px;font-weight:700;color:#2d3a55;">
                <i class="fa fa-clock-rotate-left me-2" style="color:var(--marine);"></i>Historique des opérations
            </div>
            <?php if ($bailleur_id): ?>
            <span class="bailleur-pill"><i class="fa fa-user-tie"></i><?php foreach($bailleurs as $b){ if($b['id']==$bailleur_id){ echo htmlspecialchars($b['nom']); break; } } ?></span>
            <?php endif; ?>
        </div>
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Date</th>
                    <?php if (!$bailleur_id): ?><th>Bailleur</th><?php endif; ?>
                    <th>Type</th>
                    <th>Commentaire</th>
                    <th class="text-end">Montant</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($mouvements)): ?>
                <tr><td colspan="<?= $bailleur_id?4:5 ?>" class="text-center text-muted py-5">
                    <i class="fa fa-inbox fa-2x mb-2 d-block" style="opacity:.3;"></i>Aucune opération trouvée.
                </td></tr>
                <?php else: foreach ($mouvements as $m):
                    $isCredit  = $m['montant'] > 0;
                    $typeLabel = htmlspecialchars($m['type_operation'] ?? ($isCredit ? 'Crédit' : 'Retrait'));
                ?>
                <tr>
                    <td>
                        <div class="fw-semibold small" style="color:#2d3a55;"><?= date('d/m/Y', strtotime($m['date_operation'])) ?></div>
                        <div class="text-muted" style="font-size:10px;"><?= date('H:i', strtotime($m['date_operation'])) ?></div>
                    </td>
                    <?php if (!$bailleur_id): ?>
                    <td><span class="bailleur-pill"><i class="fa fa-user-tie"></i><?= htmlspecialchars($m['nom_bailleur']) ?></span></td>
                    <?php endif; ?>
                    <td>
                        <span class="op-badge <?= $isCredit?'op-credit':'op-retrait' ?>">
                            <i class="fa fa-<?= $isCredit?'circle-plus':'circle-minus' ?> me-1"></i><?= $typeLabel ?>
                        </span>
                    </td>
                    <td class="text-muted small" style="max-width:220px;"><?= htmlspecialchars($m['commentaire'] ?: '—') ?></td>
                    <td class="text-end">
                        <span class="<?= $isCredit?'amt-pos':'amt-neg' ?>"><?= $isCredit?'+':'-' ?><?= number_format(abs($m['montant']),0,',',' ') ?></span>
                        <small class="text-muted d-block" style="font-size:10px;">FCFA</small>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>

        <div class="d-flex justify-content-between align-items-center px-4 py-3 border-top no-print" style="background:#f8faff;">
            <small class="text-muted">Page <?= $page ?> / <?= $totalPages ?> — <?= $totalRows ?> opération<?= $totalRows>1?'s':'' ?></small>
            <?php if ($totalPages > 1): ?>
            <div class="pag">
                <a href="<?= buildUrl(['page'=>$page-1]) ?>" class="<?= $page<=1?'off':'' ?>"><i class="fa fa-chevron-left" style="font-size:10px;"></i></a>
                <?php
                $start=max(1,$page-2); $end=min($totalPages,$page+2);
                if ($start>1) echo '<span style="border:none;width:auto;color:#aab;">…</span>';
                for ($i=$start;$i<=$end;$i++):
                ?>
                <?php if ($i===$page): ?><span class="cur"><?= $i ?></span>
                <?php else: ?><a href="<?= buildUrl(['page'=>$i]) ?>"><?= $i ?></a>
                <?php endif; endfor;
                if ($end<$totalPages) echo '<span style="border:none;width:auto;color:#aab;">…</span>';
                ?>
                <a href="<?= buildUrl(['page'=>$page+1]) ?>" class="<?= $page>=$totalPages?'off':'' ?>"><i class="fa fa-chevron-right" style="font-size:10px;"></i></a>
            </div>
            <?php endif; ?>
        </div>

    </div><!-- /main-content -->
</body>
</html>
