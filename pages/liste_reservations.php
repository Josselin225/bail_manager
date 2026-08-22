<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once('../config/db.php');

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }

$filtreStatut = isset($_GET['statut']) && in_array($_GET['statut'], ['en_attente','termine']) ? $_GET['statut'] : '';
$search       = trim($_GET['search'] ?? '');
$perPage      = 12;
$page         = max(1, (int)($_GET['page'] ?? 1));

$counts       = $pdo->query("SELECT statut, COUNT(*) AS n FROM reservations GROUP BY statut")->fetchAll(PDO::FETCH_KEY_PAIR);
$totalAll     = array_sum($counts);
$totalAttente = $counts['en_attente'] ?? 0;
$totalTermine = $counts['termine']    ?? 0;

$conds = []; $bind = [];
if ($filtreStatut) { $conds[] = "r.statut = :statut"; $bind[':statut'] = $filtreStatut; }
if ($search)       { $conds[] = "(r.nom_visiteur LIKE :s OR r.tel_visiteur LIKE :s2 OR m.designation LIKE :s3)"; $bind[':s']=$bind[':s2']=$bind[':s3']="%$search%"; }
$where  = $conds ? "WHERE ".implode(" AND ",$conds) : "";
$base   = "FROM reservations r JOIN maisons m ON r.maison_id=m.id $where";

$stmtC = $pdo->prepare("SELECT COUNT(*) $base"); $stmtC->execute($bind);
$totalRows  = (int)$stmtC->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

$stmtList = $pdo->prepare("SELECT r.*, m.designation AS maison_nom $base ORDER BY r.created_at DESC LIMIT :lim OFFSET :off");
foreach ($bind as $k => $v) $stmtList->bindValue($k, $v);
$stmtList->bindValue(':lim', $perPage, PDO::PARAM_INT);
$stmtList->bindValue(':off', $offset,  PDO::PARAM_INT);
$stmtList->execute();
$reservations = $stmtList->fetchAll();

function buildUrlR(array $extra = []): string {
    global $filtreStatut, $search, $page;
    $p = array_filter(['statut'=>$filtreStatut,'search'=>$search,'page'=>$page], fn($v)=>$v!==''&&$v!==null&&$v!==0);
    return '?' . http_build_query(array_merge($p, $extra));
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réservations — BailManager</title>
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
        .filter-pill { font-size:12px; font-weight:600; padding:5px 14px; border-radius:20px; border:1.5px solid #e0e6f0; background:transparent; color:#6b7a99; cursor:pointer; text-decoration:none; transition:all .15s; }
        .filter-pill:hover { border-color:var(--marine); color:var(--marine); }
        .filter-pill.active          { background:var(--marine); border-color:var(--marine); color:#fff; }
        .filter-pill.amber.active    { background:var(--amber);  border-color:var(--amber);  color:#fff; }
        .filter-pill.green.active    { background:var(--green);  border-color:var(--green);  color:#fff; }
        .tbl-full { background:#fff; border-top:1px solid #e8ecf4; width:100%; }
        .tbl-full thead th { background:#f8faff; color:#6b7a99; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; padding:13px 20px; border-bottom:1px solid #e8ecf4; position:sticky; top:0; z-index:2; }
        .tbl-full tbody td { padding:11px 20px; border-bottom:1px solid #f0f3fa; vertical-align:middle; font-size:13px; }
        .tbl-full tbody tr:last-child td { border-bottom:none; }
        .tbl-full tbody tr:hover td { background:#f8faff; }
        .visitor-cell { display:flex; align-items:center; gap:10px; }
        .visitor-avatar { width:32px; height:32px; border-radius:50%; background:linear-gradient(135deg,#002147,#004080); color:#fff; font-size:11px; font-weight:700; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
        .badge-attente { background:#fef3c7; color:#92400e; border:1px solid #fde68a; font-size:11px; font-weight:600; padding:3px 9px; border-radius:20px; }
        .badge-termine { background:#d1fae5; color:#065f46; border:1px solid #6ee7b7; font-size:11px; font-weight:600; padding:3px 9px; border-radius:20px; }
        .action-btn { width:32px; height:32px; border-radius:8px; border:1.5px solid transparent; display:inline-flex; align-items:center; justify-content:center; font-size:13px; cursor:pointer; transition:all .15s; text-decoration:none; }
        .action-btn.whatsapp { border-color:#22c55e; color:#22c55e; }
        .action-btn.whatsapp:hover { background:#22c55e; color:#fff; }
        .action-btn.validate { border-color:var(--marine); color:var(--marine); }
        .action-btn.validate:hover { background:var(--marine); color:#fff; }
        .action-btn.delete { border-color:var(--red); color:var(--red); }
        .action-btn.delete:hover { background:var(--red); color:#fff; }
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

    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <div>
            <p class="text-muted small mb-0 mt-1">Gérez les demandes de visite des prospects</p>
        </div>
    </div>

    <div class="row g-2 mb-3">
        <div class="col-6 col-md-4">
            <div class="kpi-card"><div class="kpi-icon" style="background:#eef2fb;"><i class="fa fa-calendar-alt" style="color:var(--marine);"></i></div><div><div class="kpi-val" style="color:var(--marine);"><?= $totalAll ?></div><div class="kpi-lbl">Total</div><div class="kpi-sub">demandes</div></div></div>
        </div>
        <div class="col-6 col-md-4">
            <div class="kpi-card"><div class="kpi-icon" style="background:#fef3c7;"><i class="fa fa-clock" style="color:var(--amber);"></i></div><div><div class="kpi-val" style="color:var(--amber);"><?= $totalAttente ?></div><div class="kpi-lbl">En attente</div><div class="kpi-sub">à traiter</div></div></div>
        </div>
        <div class="col-6 col-md-4">
            <div class="kpi-card"><div class="kpi-icon" style="background:#d1fae5;"><i class="fa fa-check-circle" style="color:var(--green);"></i></div><div><div class="kpi-val" style="color:var(--green);"><?= $totalTermine ?></div><div class="kpi-lbl">Effectuées</div><div class="kpi-sub">visites</div></div></div>
        </div>
    </div>

    <div class="filter-bar mb-0">
        <a href="liste_reservations.php" class="filter-pill <?= $filtreStatut==='' && !$search ? 'active' : '' ?>">Toutes <span style="opacity:.7">(<?= $totalAll ?>)</span></a>
        <a href="<?= buildUrlR(['statut'=>'en_attente','page'=>1]) ?>" class="filter-pill amber <?= $filtreStatut==='en_attente' ? 'active' : '' ?>"><i class="fa fa-clock me-1"></i>En attente <span style="opacity:.7">(<?= $totalAttente ?>)</span></a>
        <a href="<?= buildUrlR(['statut'=>'termine','page'=>1]) ?>" class="filter-pill green <?= $filtreStatut==='termine' ? 'active' : '' ?>"><i class="fa fa-check me-1"></i>Terminées <span style="opacity:.7">(<?= $totalTermine ?>)</span></a>
        <div class="ms-auto" style="position:relative;">
            <form method="GET" id="searchForm">
                <?php if ($filtreStatut): ?><input type="hidden" name="statut" value="<?= htmlspecialchars($filtreStatut) ?>"><?php endif; ?>
                <i class="fa fa-search" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#aab;font-size:12px;"></i>
                <input type="text" id="searchInput" name="search" value="<?= htmlspecialchars($search) ?>" autocomplete="off"
                       style="padding:6px 12px 6px 28px;border:1.5px solid #e0e6f0;border-radius:8px;font-size:13px;outline:none;width:100%;max-width:200px;"
                       placeholder="Rechercher…">
            </form>
        </div>
    </div>

</div><!-- /top-fixed -->
<div class="bottom-scroll">

<?php if (empty($reservations)): ?>
<div class="text-center text-muted py-5"><i class="fa fa-calendar-xmark fa-3x mb-3 d-block" style="opacity:.2;"></i>Aucune demande trouvée.</div>
<?php else: ?>

<table class="table mb-0 tbl-full" id="resTable">
    <thead><tr>
        <th style="width:22%;">Visiteur</th>
        <th style="width:14%;">Téléphone</th>
        <th style="width:22%;">Maison</th>
        <th style="width:12%;">Date visite</th>
        <th style="width:14%;">Demande reçue</th>
        <th style="width:10%;">Statut</th>
        <th class="text-center" style="width:6%;">Actions</th>
    </tr></thead>
    <tbody>
    <?php foreach ($reservations as $res):
        $initials = strtoupper(mb_substr($res['nom_visiteur'],0,1).(strpos($res['nom_visiteur'],' ')!==false?mb_substr(strrchr($res['nom_visiteur'],' '),1,1):''));
        $waNum    = preg_replace('/[^0-9]/', '', $res['tel_visiteur']);
    ?>
    <tr>
        <td>
            <div class="visitor-cell">
                <div class="visitor-avatar"><?= $initials ?></div>
                <div>
                    <div style="font-size:13px;font-weight:600;color:#2d3a55;"><?= htmlspecialchars($res['nom_visiteur']) ?></div>
                    <?php if (!empty($res['email_visiteur'])): ?><div style="font-size:11px;color:#8896b0;"><?= htmlspecialchars($res['email_visiteur']) ?></div><?php endif; ?>
                </div>
            </div>
        </td>
        <td><a href="tel:<?= htmlspecialchars($res['tel_visiteur']) ?>" class="text-decoration-none fw-semibold small" style="color:var(--marine);"><i class="fa fa-phone me-1 text-muted" style="font-size:10px;"></i><?= htmlspecialchars($res['tel_visiteur']) ?></a></td>
        <td class="fw-semibold small" style="color:#2d3a55;"><?= htmlspecialchars($res['maison_nom']) ?></td>
        <td class="fw-bold small" style="color:var(--marine);"><?= date('d/m/Y', strtotime($res['date_visite'])) ?></td>
        <td class="text-muted small"><?= date('d/m/Y H:i', strtotime($res['created_at'])) ?></td>
        <td><?= $res['statut']==='en_attente' ? '<span class="badge-attente"><i class="fa fa-clock me-1"></i>En attente</span>' : '<span class="badge-termine"><i class="fa fa-circle-check me-1"></i>Effectuée</span>' ?></td>
        <td>
            <div class="d-flex gap-1 justify-content-center">
                <a href="https://wa.me/<?= $waNum ?>" target="_blank" class="action-btn whatsapp" title="WhatsApp"><i class="fa-brands fa-whatsapp"></i></a>
                <?php if ($res['statut']==='en_attente'): ?>
                <a href="../php/process_reservation.php?action=terminer&id=<?= $res['id'] ?>" class="action-btn validate" onclick="return confirm('Marquer comme effectuée ?')" title="Valider"><i class="fa fa-check"></i></a>
                <?php endif; ?>
                <a href="../php/process_reservation.php?action=supprimer&id=<?= $res['id'] ?>" class="action-btn delete" onclick="return confirm('Supprimer définitivement ?')" title="Supprimer"><i class="fa fa-trash"></i></a>
            </div>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php endif; ?>

<?php if ($totalPages > 1): ?>
<div class="d-flex justify-content-between align-items-center" style="padding:14px 28px;background:#f8faff;border-top:1px solid #e8ecf4;">
    <small class="text-muted">Page <?= $page ?> / <?= $totalPages ?> — <?= $totalRows ?> résultat<?= $totalRows>1?'s':'' ?></small>
    <div class="pag">
        <a href="<?= buildUrlR(['page'=>$page-1]) ?>" class="<?= $page<=1?'off':'' ?>"><i class="fa fa-chevron-left" style="font-size:10px;"></i></a>
        <?php $s=max(1,$page-2);$e=min($totalPages,$page+2);if($s>1)echo'<span style="border:none;width:auto;color:#aab;">…</span>';for($i=$s;$i<=$e;$i++):?>
        <?php if($i===$page):?><span class="cur"><?=$i?></span><?php else:?><a href="<?=buildUrlR(['page'=>$i])?>"><?=$i?></a><?php endif;endfor;if($e<$totalPages)echo'<span style="border:none;width:auto;color:#aab;">…</span>';?>
        <a href="<?= buildUrlR(['page'=>$page+1]) ?>" class="<?= $page>=$totalPages?'off':'' ?>"><i class="fa fa-chevron-right" style="font-size:10px;"></i></a>
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
    searchTimer = setTimeout(function() { document.getElementById('searchForm').submit(); }, 350);
});
</script>
</body>
</html>
