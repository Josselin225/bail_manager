<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once('../config/db.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { header('Location: dashboard.php'); exit(); }

$search        = trim($_GET['search']       ?? '');
$filtreDate    = trim($_GET['date_filtre']  ?? '');
$filtreMois    = trim($_GET['mois_filtre']  ?? '');
$filtreAnnee   = trim($_GET['annee_filtre'] ?? '');
$filtreAction  = trim($_GET['action_filtre']?? '');
$perPage       = 15;
$page          = max(1, (int)($_GET['page'] ?? 1));

$conds = []; $bind = [];
if ($filtreDate)        { $conds[] = "DATE(l.date_action) = :date_filtre";               $bind[':date_filtre']  = $filtreDate; }
elseif ($filtreMois)    { $conds[] = "DATE_FORMAT(l.date_action,'%Y-%m') = :mois_filtre"; $bind[':mois_filtre']  = $filtreMois; }
elseif ($filtreAnnee)   { $conds[] = "YEAR(l.date_action) = :annee_filtre";               $bind[':annee_filtre'] = $filtreAnnee; }
if ($filtreAction) { $conds[] = "l.action LIKE :action_filter";       $bind[':action_filter'] = "%$filtreAction%"; }
if ($search)       { $conds[] = "(u.nom_complet LIKE :search OR l.details LIKE :search2)"; $bind[':search']=$bind[':search2']="%$search%"; }
$where = $conds ? "WHERE ".implode(" AND ",$conds) : "";
$base  = "FROM logs l LEFT JOIN users u ON l.utilisateur_id=u.id $where";

$stmtC = $pdo->prepare("SELECT COUNT(*) $base"); $stmtC->execute($bind);
$totalRows  = (int)$stmtC->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

$stmtList = $pdo->prepare("SELECT l.*, u.nom_complet $base ORDER BY l.date_action DESC LIMIT :lim OFFSET :off");
foreach ($bind as $k => $v) $stmtList->bindValue($k, $v);
$stmtList->bindValue(':lim', $perPage, PDO::PARAM_INT);
$stmtList->bindValue(':off', $offset,  PDO::PARAM_INT);
$stmtList->execute();
$allLogs = $stmtList->fetchAll();

$totalLogs   = (int)$pdo->query("SELECT COUNT(*) FROM logs")->fetchColumn();
$logAujourd  = (int)$pdo->query("SELECT COUNT(*) FROM logs WHERE DATE(date_action)=CURDATE()")->fetchColumn();
$nbUtilisateurs = (int)$pdo->query("SELECT COUNT(DISTINCT utilisateur_id) FROM logs WHERE DATE(date_action)=CURDATE()")->fetchColumn();

function buildUrlJ(array $extra = []): string {
    global $search, $page, $filtreDate, $filtreMois, $filtreAnnee, $filtreAction;
    $p = array_filter(['search'=>$search,'date_filtre'=>$filtreDate,'mois_filtre'=>$filtreMois,'annee_filtre'=>$filtreAnnee,'action_filtre'=>$filtreAction,'page'=>$page], fn($v)=>$v!==''&&$v!==null&&$v!==0);
    return '?' . http_build_query(array_merge($p, $extra));
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../favicon.svg">
    <title>Journal d'activités — BailManager</title>
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
        .tbl-full tbody td { padding:10px 24px; border-bottom:1px solid #f0f3fa; vertical-align:middle; font-size:12px; }
        .tbl-full tbody tr:last-child td { border-bottom:none; }
        .tbl-full tbody tr:hover td { background:#f8faff; }
        .pag { display:flex; align-items:center; gap:4px; }
        .pag a, .pag span { display:inline-flex; align-items:center; justify-content:center; width:32px; height:32px; border-radius:8px; font-size:12px; font-weight:600; text-decoration:none; border:1.5px solid #e0e6f0; color:#6b7a99; }
        .pag a:hover { border-color:var(--marine); color:var(--marine); }
        .pag span.cur { background:var(--marine); border-color:var(--marine); color:#fff; }
        .pag a.off { opacity:.35; pointer-events:none; }
    </style>
</head>
<body>
<?php include('../includes/sidebar.php'); ?>

<div class="main-content">
<div class="top-fixed">

    <div class="row g-2 mb-3">
        <div class="col-6 col-md-3">
            <div class="kpi-card"><div class="kpi-icon" style="background:#eef2fb;"><i class="fa fa-scroll" style="color:var(--marine);"></i></div><div><div class="kpi-val" style="color:var(--marine);"><?= $totalLogs ?></div><div class="kpi-lbl">Total logs</div><div class="kpi-sub">tous temps</div></div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card"><div class="kpi-icon" style="background:#d1fae5;"><i class="fa fa-calendar-day" style="color:var(--green);"></i></div><div><div class="kpi-val" style="color:var(--green);"><?= $logAujourd ?></div><div class="kpi-lbl">Aujourd'hui</div><div class="kpi-sub">actions</div></div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card"><div class="kpi-icon" style="background:#fef3c7;"><i class="fa fa-users" style="color:var(--amber);"></i></div><div><div class="kpi-val" style="color:var(--amber);"><?= $nbUtilisateurs ?></div><div class="kpi-lbl">Utilisateurs</div><div class="kpi-sub">actifs auj.</div></div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card"><div class="kpi-icon" style="background:#eef2fb;"><i class="fa fa-filter" style="color:var(--marine);"></i></div><div><div class="kpi-val" style="color:var(--marine);"><?= $totalRows ?></div><div class="kpi-lbl">Filtrés</div><div class="kpi-sub">résultats</div></div></div>
        </div>
    </div>

    <div class="filter-bar mb-0">
        <form method="GET" class="d-flex align-items-center gap-2 flex-wrap flex-grow-1">
            <i class="fa fa-search text-muted" style="font-size:13px;"></i>
            <input type="text" id="searchInput" name="search" value="<?= htmlspecialchars($search) ?>"
                   class="form-control form-control-sm" style="max-width:200px;border-radius:8px;"
                   placeholder="Nom, détails…" autocomplete="off">
            <input type="date" name="date_filtre" value="<?= htmlspecialchars($filtreDate) ?>" class="form-control form-control-sm" style="max-width:150px;border-radius:8px;" onchange="this.form.mois_filtre.value='';this.form.annee_filtre.value='';this.form.submit()">
            <input type="month" name="mois_filtre" value="<?= htmlspecialchars($filtreMois) ?>" class="form-control form-control-sm" style="max-width:140px;border-radius:8px;" onchange="this.form.date_filtre.value='';this.form.annee_filtre.value='';this.form.submit()">
            <input type="number" name="annee_filtre" value="<?= htmlspecialchars($filtreAnnee) ?>" placeholder="Année" min="2000" max="2100" class="form-control form-control-sm" style="max-width:100px;border-radius:8px;" onchange="this.form.date_filtre.value='';this.form.mois_filtre.value='';this.form.submit()">
            <select name="action_filtre" class="form-select form-select-sm" style="max-width:160px;border-radius:8px;" onchange="this.form.submit()">
                <option value="">— Toutes actions —</option>
                <option value="Connexion"   <?= $filtreAction==='Connexion'?'selected':'' ?>>Connexions</option>
                <option value="Création"    <?= $filtreAction==='Création'?'selected':'' ?>>Créations</option>
                <option value="Suppression" <?= $filtreAction==='Suppression'?'selected':'' ?>>Suppressions</option>
            </select>
            <?php if ($search || $filtreDate || $filtreMois || $filtreAnnee || $filtreAction): ?>
            <a href="journal_activites.php" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;"><i class="fa fa-times"></i></a>
            <?php endif; ?>
            <div class="ms-auto text-muted small"><?= $totalRows ?> entrée<?= $totalRows>1?'s':'' ?></div>
        </form>
        <form action="../php/vider_logs.php" method="POST" onsubmit="return confirm('Supprimer définitivement tous les logs ?')" class="flex-shrink-0">
            <input type="hidden" name="token" value="<?= csrf_generate() ?>">
            <button type="submit" class="btn btn-sm btn-outline-danger" style="border-radius:8px;white-space:nowrap;"><i class="fa fa-trash-alt me-1"></i>Vider le journal</button>
        </form>
    </div>

</div><!-- /top-fixed -->
<div class="bottom-scroll">

<?php if (empty($allLogs)): ?>
<div class="text-center text-muted py-5"><i class="fa fa-inbox fa-3x mb-3 d-block" style="opacity:.2;"></i>Aucun log trouvé.</div>
<?php else: ?>

<table class="table mb-0 tbl-full">
    <thead><tr>
        <th style="width:16%;">Date &amp; Heure</th>
        <th style="width:16%;">Utilisateur</th>
        <th style="width:16%;">Action</th>
        <th style="width:40%;">Détails</th>
        <th style="width:12%;">IP</th>
    </tr></thead>
    <tbody>
    <?php foreach ($allLogs as $log): ?>
    <tr>
        <td class="text-muted"><?= date('d/m/Y H:i:s', strtotime($log['date_action'])) ?></td>
        <td><span class="badge bg-info text-dark"><?= htmlspecialchars($log['nom_complet'] ?? 'Système') ?></span></td>
        <td class="fw-semibold" style="color:#2d3a55;"><?= htmlspecialchars($log['action']) ?></td>
        <td class="text-muted"><?= htmlspecialchars($log['details']) ?></td>
        <td class="text-muted"><?= htmlspecialchars($log['ip_adresse']) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php endif; ?>

<?php if ($totalPages > 1): ?>
<div class="d-flex justify-content-between align-items-center" style="padding:14px 28px;background:#f8faff;border-top:1px solid #e8ecf4;">
    <small class="text-muted">Page <?= $page ?> / <?= $totalPages ?> — <?= $totalRows ?> entrée<?= $totalRows>1?'s':'' ?></small>
    <div class="pag">
        <a href="<?= buildUrlJ(['page'=>$page-1]) ?>" class="<?= $page<=1?'off':'' ?>"><i class="fa fa-chevron-left" style="font-size:10px;"></i></a>
        <?php $s=max(1,$page-2);$e=min($totalPages,$page+2);if($s>1)echo'<span style="border:none;width:auto;color:#aab;">…</span>';for($i=$s;$i<=$e;$i++):?>
        <?php if($i===$page):?><span class="cur"><?=$i?></span><?php else:?><a href="<?=buildUrlJ(['page'=>$i])?>"><?=$i?></a><?php endif;endfor;if($e<$totalPages)echo'<span style="border:none;width:auto;color:#aab;">…</span>';?>
        <a href="<?= buildUrlJ(['page'=>$page+1]) ?>" class="<?= $page>=$totalPages?'off':'' ?>"><i class="fa fa-chevron-right" style="font-size:10px;"></i></a>
    </div>
</div>
<?php endif; ?>

</div><!-- /bottom-scroll -->
</div><!-- /main-content -->

<script src="../js/bootstrap.bundle.min.js"></script>
<script>
var searchTimer;
document.getElementById('searchInput').addEventListener('input', function() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(function() { document.querySelector('.filter-bar form').submit(); }, 350);
});
</script>
</body>
</html>
