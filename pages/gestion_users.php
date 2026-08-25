<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once('../config/db.php');

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { header('Location: dashboard.php'); exit(); }

$search = trim($_GET['search'] ?? '');
$cond = $search ? "WHERE nom_complet LIKE :s OR email LIKE :s2" : "";
$bind = $search ? [':s'=>"%$search%",':s2'=>"%$search%"] : [];

$totalUsers = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$stmtCount  = $pdo->prepare("SELECT COUNT(*) FROM users $cond"); $stmtCount->execute($bind);
$totalRows  = (int)$stmtCount->fetchColumn();

$stmtList = $pdo->prepare("SELECT * FROM users $cond ORDER BY created_at DESC");
$stmtList->execute($bind);
$users = $stmtList->fetchAll();

$nbAdmins  = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
$nbAgents  = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='agent'")->fetchColumn();
$dernierConnecte = $pdo->query("SELECT nom_complet FROM users WHERE dernier_acces IS NOT NULL ORDER BY dernier_acces DESC LIMIT 1")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../favicon.svg">
    <title>Gestion du Personnel — BailManager</title>
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
        .view-toggle { display:flex; border:1.5px solid #e0e6f0; border-radius:8px; overflow:hidden; }
        .view-toggle button { background:#fff; border:none; padding:6px 10px; cursor:pointer; color:#8896b0; font-size:13px; transition:background .15s,color .15s; }
        .view-toggle button.active { background:var(--marine); color:#fff; }
        .tbl-full { background:#fff; border-top:1px solid #e8ecf4; width:100%; }
        .tbl-full thead th { background:#f8faff; color:#6b7a99; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; padding:13px 24px; border-bottom:1px solid #e8ecf4; position:sticky; top:0; z-index:2; }
        .tbl-full tbody td { padding:11px 24px; border-bottom:1px solid #f0f3fa; vertical-align:middle; font-size:13px; }
        .tbl-full tbody tr:last-child td { border-bottom:none; }
        .tbl-full tbody tr:hover td { background:#f8faff; }
        .b-card { background:#fff; border-radius:16px; border:1px solid #e8ecf4; box-shadow:0 2px 10px rgba(0,0,0,.05); overflow:hidden; transition:box-shadow .18s,transform .18s; }
        .b-card:hover { box-shadow:0 8px 28px rgba(0,33,71,.12); transform:translateY(-2px); }
        .avatar-sm { width:34px; height:34px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:12px; flex-shrink:0; }
        .avatar-lg { width:52px; height:52px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:18px; flex-shrink:0; margin-bottom:8px; }
    </style>
</head>
<body>
<?php include('../includes/sidebar.php'); ?>

<!-- ══ MODAL AJOUT ══ -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content border-0 shadow-lg" action="../php/add_user_process.php" method="POST">
            <input type="hidden" name="token" value="<?= csrf_generate() ?>">
            <div class="modal-header text-white border-0" style="background:linear-gradient(135deg,#002147,#004080);">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-white bg-opacity-10 d-flex align-items-center justify-content-center" style="width:40px;height:40px;"><i class="fa fa-user-shield text-white"></i></div>
                    <div><h5 class="modal-title fw-bold mb-0">Créer un compte</h5><small class="opacity-75">Accès à l'application</small></div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 py-4">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">Nom complet <span class="text-danger">*</span></label>
                        <div class="input-group"><span class="input-group-text bg-light border-end-0"><i class="fa fa-user text-muted"></i></span><input type="text" name="nom" class="form-control border-start-0 ps-0" placeholder="Jean Kouassi" required></div>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">Email <span class="text-danger">*</span></label>
                        <div class="input-group"><span class="input-group-text bg-light border-end-0"><i class="fa fa-envelope text-muted"></i></span><input type="email" name="email" class="form-control border-start-0 ps-0" placeholder="email@exemple.com" required></div>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">Mot de passe <span class="text-danger">*</span></label>
                        <div class="input-group"><span class="input-group-text bg-light border-end-0"><i class="fa fa-lock text-muted"></i></span><input type="password" name="password" class="form-control border-start-0 ps-0" required></div>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">Rôle</label>
                        <select name="role" class="form-select">
                            <option value="agent">👤 Agent — Accès limité</option>
                            <option value="admin">🛡️ Administrateur — Tout accès</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top bg-light px-4">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal"><i class="fa fa-times me-1"></i>Annuler</button>
                <button type="submit" class="btn btn-danger px-5 fw-semibold"><i class="fa fa-check me-2"></i>Créer le compte</button>
            </div>
        </form>
    </div>
</div>

<div class="main-content">
<div class="top-fixed">

    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <div>
            <p class="text-muted small mb-0 mt-1"><?= $totalUsers ?> utilisateur<?= $totalUsers>1?'s':'' ?> enregistré<?= $totalUsers>1?'s':'' ?></p>
        </div>
        <button class="btn btn-danger btn-sm shadow-sm" style="border-radius:8px;" data-bs-toggle="modal" data-bs-target="#addUserModal">
            <i class="fa fa-user-plus me-2"></i>Nouvel Utilisateur
        </button>
    </div>

    <div class="row g-2 mb-3">
        <div class="col-6 col-md-3">
            <div class="kpi-card"><div class="kpi-icon" style="background:#eef2fb;"><i class="fa fa-users" style="color:var(--marine);"></i></div><div><div class="kpi-val" style="color:var(--marine);"><?= $totalUsers ?></div><div class="kpi-lbl">Utilisateurs</div><div class="kpi-sub">au total</div></div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card"><div class="kpi-icon" style="background:#fee2e2;"><i class="fa fa-shield-halved" style="color:var(--red);"></i></div><div><div class="kpi-val" style="color:var(--red);"><?= $nbAdmins ?></div><div class="kpi-lbl">Admins</div><div class="kpi-sub">tout accès</div></div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card"><div class="kpi-icon" style="background:#eef2fb;"><i class="fa fa-user-check" style="color:var(--marine);"></i></div><div><div class="kpi-val" style="color:var(--marine);"><?= $nbAgents ?></div><div class="kpi-lbl">Agents</div><div class="kpi-sub">accès limité</div></div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card"><div class="kpi-icon" style="background:#d1fae5;"><i class="fa fa-circle-dot" style="color:var(--green);"></i></div><div><div class="kpi-val" style="color:var(--green);font-size:.85rem;"><?= htmlspecialchars(mb_substr($dernierConnecte?:'—',0,14)) ?></div><div class="kpi-lbl">Dernier actif</div></div></div>
        </div>
    </div>

    <div class="filter-bar mb-0">
        <form method="GET" class="d-flex align-items-center gap-2 flex-wrap w-100">
            <i class="fa fa-search text-muted" style="font-size:13px;"></i>
            <input type="text" id="searchInput" name="search" value="<?= htmlspecialchars($search) ?>"
                   class="form-control form-control-sm" style="max-width:300px;border-radius:8px;"
                   placeholder="Nom, email…" autocomplete="off">
            <?php if ($search): ?>
            <a href="gestion_users.php" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;"><i class="fa fa-times"></i></a>
            <?php endif; ?>
            <div class="ms-auto text-muted small me-2"><?= $totalRows ?> résultat<?= $totalRows>1?'s':'' ?></div>
            <div class="view-toggle">
                <button type="button" id="btnVueListe" onclick="setView('liste')" title="Vue liste"><i class="fa fa-list-ul"></i></button>
                <button type="button" id="btnVueCarte" onclick="setView('cartes')" title="Vue cartes"><i class="fa fa-grip"></i></button>
            </div>
        </form>
    </div>

</div><!-- /top-fixed -->
<div class="bottom-scroll">

<?php if (empty($users)): ?>
<div class="text-center text-muted py-5"><i class="fa fa-inbox fa-3x mb-3 d-block" style="opacity:.2;"></i>Aucun utilisateur trouvé.</div>
<?php else: ?>

<!-- VUE LISTE -->
<div id="vueListe">
<table class="table mb-0 tbl-full">
    <thead><tr>
        <th style="width:30%;">Nom complet</th>
        <th style="width:28%;">Email</th>
        <th style="width:12%;">Rôle</th>
        <th style="width:20%;">Dernier accès</th>
        <th class="text-center" style="width:10%;">Actions</th>
    </tr></thead>
    <tbody>
    <?php foreach ($users as $u):
        $initiale = mb_strtoupper(mb_substr($u['nom_complet'], 0, 1));
        $isAdmin  = $u['role']==='admin';
    ?>
    <tr>
        <td>
            <div class="d-flex align-items-center gap-3">
                <div class="avatar-sm" style="background:<?= $isAdmin?'#fee2e2':'#eef2fb' ?>;color:<?= $isAdmin?'var(--red)':'var(--marine)' ?>;"><?= htmlspecialchars($initiale) ?></div>
                <div class="fw-semibold" style="color:#2d3a55;"><?= htmlspecialchars($u['nom_complet']) ?></div>
            </div>
        </td>
        <td class="text-muted"><?= htmlspecialchars($u['email']) ?></td>
        <td><span class="badge rounded-pill <?= $isAdmin?'bg-danger':'bg-primary' ?>"><?= strtoupper($u['role']) ?></span></td>
        <td class="text-muted small"><?= $u['dernier_acces'] ? date('d/m/Y H:i', strtotime($u['dernier_acces'])) : 'Jamais' ?></td>
        <td class="text-center" style="white-space:nowrap;">
            <?php if ($u['id'] != $_SESSION['user_id']): ?>
            <form action="../php/delete_user.php" method="POST" style="display:inline" onsubmit="return confirm('Supprimer cet utilisateur ?')">
                <input type="hidden" name="token" value="<?= csrf_generate() ?>">
                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                <button type="submit" class="btn btn-sm btn-outline-danger" style="border-radius:6px;"><i class="fa fa-trash"></i></button>
            </form>
            <?php else: ?><span class="text-muted small">Vous</span><?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<!-- VUE CARTES -->
<div id="vueCartes" style="padding:20px 28px 24px;">
<div class="row g-3">
<?php foreach ($users as $u):
    $initiale = mb_strtoupper(mb_substr($u['nom_complet'], 0, 1));
    $isAdmin  = $u['role']==='admin';
?>
<div class="col-sm-6 col-lg-4 col-xl-3">
    <div class="b-card h-100" style="padding:20px;text-align:center;">
        <div class="d-flex flex-column align-items-center mb-2">
            <div class="avatar-lg" style="background:<?= $isAdmin?'#fee2e2':'#eef2fb' ?>;color:<?= $isAdmin?'var(--red)':'var(--marine)' ?>;"><?= htmlspecialchars($initiale) ?></div>
            <div class="fw-bold" style="color:#1e293b;font-size:13px;"><?= htmlspecialchars($u['nom_complet']) ?></div>
            <div class="text-muted" style="font-size:11px;"><?= htmlspecialchars($u['email']) ?></div>
            <span class="badge rounded-pill mt-1 <?= $isAdmin?'bg-danger':'bg-primary' ?>"><?= strtoupper($u['role']) ?></span>
        </div>
        <div class="text-muted" style="font-size:10px;border-top:1px solid #f0f3fa;padding-top:10px;">
            <?= $u['dernier_acces'] ? 'Vu le '.date('d/m/Y',strtotime($u['dernier_acces'])) : 'Jamais connecté' ?>
        </div>
        <?php if ($u['id'] != $_SESSION['user_id']): ?>
        <div style="margin-top:10px;border-top:1px solid #f0f3fa;padding-top:10px;">
            <form action="../php/delete_user.php" method="POST" style="display:inline" onsubmit="return confirm('Supprimer ?')">
                <input type="hidden" name="token" value="<?= csrf_generate() ?>">
                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                <button type="submit" class="btn btn-sm btn-outline-danger w-100" style="border-radius:8px;font-size:12px;"><i class="fa fa-trash me-1"></i>Supprimer</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>
</div>
</div>

<?php endif; ?>

</div><!-- /bottom-scroll -->
</div><!-- /main-content -->

<script src="../js/bootstrap.bundle.min.js"></script>
<script>
function setView(v) {
    document.getElementById('vueListe').style.display  = v==='liste'  ? 'block':'none';
    document.getElementById('vueCartes').style.display = v==='cartes' ? 'block':'none';
    document.getElementById('btnVueListe').classList.toggle('active', v==='liste');
    document.getElementById('btnVueCarte').classList.toggle('active', v==='cartes');
    localStorage.setItem('userView', v);
}
setView(localStorage.getItem('userView') || 'liste');
var searchTimer;
document.getElementById('searchInput').addEventListener('input', function() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(function() { document.querySelector('.filter-bar form').submit(); }, 350);
});
</script>
</body>
</html>
