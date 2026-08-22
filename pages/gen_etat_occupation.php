<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once('../config/db.php');

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }

$agence = $pdo->query("SELECT * FROM settings LIMIT 1")->fetch() ?: [];

// Stats globales
$allStats = $pdo->query(
    "SELECT m.id, l.id AS loc_id
     FROM maisons m
     LEFT JOIN contrats c ON m.id=c.maison_id AND c.statut_contrat='actif'
     LEFT JOIN locataires l ON c.locataire_id=l.id"
)->fetchAll();

$total    = count($allStats);
$louees   = count(array_filter($allStats, fn($s) => $s['loc_id']));
$vacantes = $total - $louees;
$taux     = $total > 0 ? round(($louees / $total) * 100, 1) : 0;

// Pagination
$perPage = 10;
$page    = max(1, (int)($_GET['page'] ?? 1));
$totalPages = max(1, (int)ceil($total / $perPage));
$page    = min($page, $totalPages);
$offset  = ($page - 1) * $perPage;

$stmt = $pdo->prepare(
    "SELECT m.*, l.nom AS locataire, c.loyer_mensuel
     FROM maisons m
     LEFT JOIN contrats c ON m.id=c.maison_id AND c.statut_contrat='actif'
     LEFT JOIN locataires l ON c.locataire_id=l.id
     ORDER BY m.designation ASC LIMIT :lim OFFSET :off"
);
$stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset,  PDO::PARAM_INT);
$stmt->execute();
$maisons = $stmt->fetchAll();

// JSON pour graphe
$jOcc = json_encode([$louees, $vacantes]);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>État d'Occupation — BailManager</title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/fontawesome/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <script src="../js/chart.min.js"></script>
    <style>
        :root { --marine:#002147; --green:#059669; --red:#e53e3e; --amber:#d97706; }
        .main-content { background:#f4f7fe; min-height:100vh; padding:24px 28px; }
        .stat-card { background:#fff; border-radius:12px; padding:14px 18px; border-left:4px solid var(--marine); box-shadow:0 2px 8px rgba(0,0,0,.05); height:100%; }
        .tbl-card { background:#fff; border-radius:14px; box-shadow:0 2px 10px rgba(0,0,0,.06); border:1px solid #e8ecf4; overflow:hidden; }
        .tbl-card thead th { background:#f8faff; color:#6b7a99; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; padding:11px 16px; border-bottom:1px solid #e8ecf4; }
        .tbl-card tbody td { padding:11px 16px; border-bottom:1px solid #f0f3fa; vertical-align:middle; font-size:13px; }
        .tbl-card tbody tr:last-child td { border-bottom:none; }
        .tbl-card tbody tr:hover td { background:#f8faff; }
        .pag { display:flex; align-items:center; gap:4px; }
        .pag a, .pag span { display:inline-flex; align-items:center; justify-content:center; width:30px; height:30px; border-radius:8px; font-size:12px; font-weight:600; text-decoration:none; border:1.5px solid #e0e6f0; color:#6b7a99; }
        .pag a:hover { border-color:var(--marine); color:var(--marine); }
        .pag span.cur { background:var(--marine); border-color:var(--marine); color:#fff; }
        .pag a.off { opacity:.35; pointer-events:none; }
        @media print { .no-print { display:none !important; } body { padding-top:0 !important; } .main-content { padding:0 !important; background:#fff !important; } }
    </style>
</head>
<body>
<?php include('../includes/sidebar.php'); ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var ctx = document.getElementById('occupationChart');
    if (!ctx || typeof Chart === 'undefined') return;
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Occupés', 'Vacants'],
            datasets: [{ data: <?= $jOcc ?>, backgroundColor:['#e53e3e','#059669'], borderWidth:2, borderColor:'#fff' }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display:false } },
            cutout: '72%'
        }
    });
});
</script>

<div class="main-content">

    <!-- En-tête -->
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2 no-print">
        <div>
            <p class="text-muted small mb-0 mt-1">Répartition des biens loués et vacants</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button onclick="window.print()" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;">
                <i class="fa fa-print me-1"></i>Imprimer
            </button>
            <a href="rapports.php" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;">
                <i class="fa fa-arrow-left me-1"></i>Retour
            </a>
        </div>
    </div>

    <!-- Stats + graphe -->
    <div class="row g-3 mb-4 align-items-stretch">
        <div class="col-auto no-print">
            <div style="width:180px;height:180px;position:relative;background:#fff;border-radius:14px;box-shadow:0 2px 10px rgba(0,0,0,.06);display:flex;align-items:center;justify-content:center;padding:16px;">
                <canvas id="occupationChart"></canvas>
                <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);text-align:center;pointer-events:none;">
                    <div style="font-size:22px;font-weight:800;color:var(--marine);line-height:1;"><?= $taux ?>%</div>
                    <div style="font-size:9px;font-weight:600;color:#8896b0;text-transform:uppercase;letter-spacing:.06em;">Occupé</div>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="row g-3 h-100">
                <div class="col-6 col-md-3">
                    <div class="stat-card">
                        <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#8896b0;">Total biens</div>
                        <div style="font-size:1.6rem;font-weight:800;color:var(--marine);line-height:1.2;margin-top:4px;"><?= $total ?></div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card" style="border-left-color:var(--red);">
                        <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--red);">Occupés</div>
                        <div style="font-size:1.6rem;font-weight:800;color:var(--red);line-height:1.2;margin-top:4px;"><?= $louees ?></div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card" style="border-left-color:var(--green);">
                        <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--green);">Vacants</div>
                        <div style="font-size:1.6rem;font-weight:800;color:var(--green);line-height:1.2;margin-top:4px;"><?= $vacantes ?></div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card" style="border-left-color:var(--amber);">
                        <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--amber);">Taux d'occupation</div>
                        <div style="font-size:1.6rem;font-weight:800;color:var(--amber);line-height:1.2;margin-top:4px;"><?= $taux ?>%</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tableau -->
    <div class="tbl-card">
        <div style="padding:14px 16px 10px;border-bottom:1px solid #f0f3fa;display:flex;align-items:center;justify-content:space-between;">
            <div style="font-size:13px;font-weight:700;color:#2d3a55;">
                <i class="fa fa-list me-2" style="color:var(--marine);"></i>Détail par bien
            </div>
            <small class="text-muted"><?= $total ?> bien<?= $total>1?'s':'' ?></small>
        </div>
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Désignation</th>
                    <th>Type</th>
                    <th>Statut</th>
                    <th>Locataire</th>
                    <th class="text-end">Loyer mensuel</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($maisons)): ?>
                <tr><td colspan="5" class="text-center text-muted py-4"><i class="fa fa-inbox fa-2x d-block mb-2" style="opacity:.3;"></i>Aucun bien trouvé.</td></tr>
                <?php else: foreach ($maisons as $m): $isOcc = !empty($m['locataire']); ?>
                <tr>
                    <td class="fw-semibold" style="color:#2d3a55;"><?= htmlspecialchars($m['designation']) ?></td>
                    <td><span class="badge bg-light text-dark border" style="font-size:10px;"><?= htmlspecialchars($m['type_maison'] ?? '') ?></span></td>
                    <td>
                        <span class="badge rounded-pill <?= $isOcc ? 'bg-danger' : 'bg-success' ?>" style="font-size:11px;">
                            <?= $isOcc ? 'OCCUPÉ' : 'VACANT' ?>
                        </span>
                    </td>
                    <td class="text-muted small"><?= htmlspecialchars($m['locataire'] ?? '—') ?></td>
                    <td class="text-end fw-bold" style="color:var(--marine);">
                        <?= $isOcc ? number_format($m['loyer_mensuel'],0,',',' ').' <small class="text-muted fw-normal">FCFA</small>' : '<span class="text-muted fw-normal">—</span>' ?>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
        <?php if ($totalPages > 1): ?>
        <div class="d-flex justify-content-between align-items-center px-4 py-3 border-top no-print" style="background:#f8faff;">
            <small class="text-muted">Page <?= $page ?> / <?= $totalPages ?></small>
            <div class="pag">
                <a href="?page=<?= $page-1 ?>" class="<?= $page<=1?'off':'' ?>"><i class="fa fa-chevron-left" style="font-size:10px;"></i></a>
                <?php for ($i=1;$i<=$totalPages;$i++): ?>
                <?php if($i===$page):?><span class="cur"><?=$i?></span><?php else:?><a href="?page=<?=$i?>"><?=$i?></a><?php endif; ?>
                <?php endfor; ?>
                <a href="?page=<?= $page+1 ?>" class="<?= $page>=$totalPages?'off':'' ?>"><i class="fa fa-chevron-right" style="font-size:10px;"></i></a>
            </div>
        </div>
        <?php endif; ?>
    </div>

</div><!-- /main-content -->
</body>
</html>
