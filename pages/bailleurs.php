<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once('../config/db.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$search  = trim($_GET['search'] ?? '');
$perPage = 12;
$page    = max(1, (int)($_GET['page'] ?? 1));

$where      = '';
$bindSearch = [];
if ($search) {
    $where      = "WHERE nom LIKE :s OR code_bailleur LIKE :s2 OR telephone1 LIKE :s3";
    $bindSearch = [':s' => "%$search%", ':s2' => "%$search%", ':s3' => "%$search%"];
}

$totalBailleurs = (int)$pdo->query("SELECT COUNT(*) FROM bailleurs")->fetchColumn();

$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM bailleurs $where");
$stmtCount->execute($bindSearch);
$totalRows  = (int)$stmtCount->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

$stmtList = $pdo->prepare("SELECT * FROM bailleurs $where ORDER BY nom ASC LIMIT :lim OFFSET :off");
foreach ($bindSearch as $k => $v) $stmtList->bindValue($k, $v);
$stmtList->bindValue(':lim', $perPage, PDO::PARAM_INT);
$stmtList->bindValue(':off', $offset,  PDO::PARAM_INT);
$stmtList->execute();
$bailleurs = $stmtList->fetchAll();

$totalSolde       = (float)$pdo->query("SELECT COALESCE(SUM(solde_du_bailleur),0)             FROM bailleurs")->fetchColumn();
$totalCommissions = (float)$pdo->query("SELECT COALESCE(SUM(total_commissions_entreprises),0) FROM bailleurs")->fetchColumn();
$nbAvecSolde      = (int)  $pdo->query("SELECT COUNT(*) FROM bailleurs WHERE solde_du_bailleur > 0")->fetchColumn();

function buildUrlB(array $extra = []): string {
    global $search, $page;
    $p = array_filter(['search' => $search, 'page' => $page], fn($v) => $v !== '' && $v !== null && $v !== 0);
    return '?' . http_build_query(array_merge($p, $extra));
}

$csrfToken = csrf_generate();

$avatarColors = [
    ['bg' => '#dbeafe', 'fg' => '#1e40af'],
    ['bg' => '#fce7f3', 'fg' => '#9d174d'],
    ['bg' => '#d1fae5', 'fg' => '#065f46'],
    ['bg' => '#fef3c7', 'fg' => '#92400e'],
    ['bg' => '#ede9fe', 'fg' => '#5b21b6'],
    ['bg' => '#fee2e2', 'fg' => '#991b1b'],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Répertoire des Bailleurs — BailManager</title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/fontawesome/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        :root { --marine:#002147; --green:#059669; --amber:#d97706; --red:#e53e3e; }
        .main-content { background:#f4f7fe; height:100vh; display:flex; flex-direction:column; overflow:hidden; }
        .top-fixed    { padding:18px 28px 0; flex-shrink:0; }
        .bottom-scroll { flex:1; overflow-y:auto; overflow-x:hidden; }

        /* KPI */
        .kpi-card { background:#fff; border-radius:12px; padding:12px 16px; display:flex; align-items:center; gap:12px; box-shadow:0 2px 10px rgba(0,0,0,.06); border:1px solid #e8ecf4; height:100%; }
        .kpi-icon { width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:16px; flex-shrink:0; }
        .kpi-val  { font-size:1.1rem; font-weight:800; line-height:1.1; }
        .kpi-lbl  { font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:.05em; color:#8896b0; margin-top:1px; }
        .kpi-sub  { font-size:10px; color:#aab; margin-top:0; }

        /* Barre filtre + toggle */
        .filter-bar { background:#fff; border-radius:10px; padding:10px 16px; display:flex; align-items:center; gap:10px; box-shadow:0 2px 8px rgba(0,0,0,.05); border:1px solid #e8ecf4; flex-wrap:wrap; }
        .view-toggle { display:flex; border:1.5px solid #e0e6f0; border-radius:8px; overflow:hidden; }
        .view-toggle button { background:#fff; border:none; padding:6px 10px; cursor:pointer; color:#8896b0; font-size:13px; transition:background .15s, color .15s; }
        .view-toggle button.active { background:var(--marine); color:#fff; }

        /* ─── Vue LISTE (pleine largeur, pas de card wrapper) ─── */
        .tbl-full { background:#fff; border-top:1px solid #e8ecf4; width:100%; }
        .tbl-full thead th { background:#f8faff; color:#6b7a99; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; padding:13px 24px; border-bottom:1px solid #e8ecf4; position:sticky; top:0; z-index:2; }
        .tbl-full tbody td { padding:13px 24px; border-bottom:1px solid #f0f3fa; vertical-align:middle; font-size:13px; }
        .tbl-full tbody tr:last-child td { border-bottom:none; }
        .tbl-full tbody tr:hover td { background:#f8faff; }
        .avatar-sm { width:34px; height:34px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:12px; flex-shrink:0; }
        .solde-pos { color:var(--green); font-weight:700; }
        .solde-neg { color:var(--red);   font-weight:700; }
        .comm-pill { font-size:11px; font-weight:600; padding:3px 10px; border-radius:20px; background:#eef2fb; color:var(--marine); }

        /* ─── Vue GRILLE — tuiles compactes ─── */
        .grid-tile { background:#fff; border-radius:14px; border:1px solid #e8ecf4; box-shadow:0 2px 10px rgba(0,0,0,.05); padding:16px; height:100%; transition:box-shadow .15s ease, transform .15s ease; }
        .grid-tile:hover { box-shadow:0 8px 24px rgba(0,0,0,.1); transform:translateY(-2px); }
        .grid-tile-header { display:flex; align-items:center; gap:12px; margin-bottom:12px; }
        .grid-tile-avatar { width:46px; height:46px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:16px; flex-shrink:0; overflow:hidden; }
        .grid-tile-avatar img { width:100%; height:100%; object-fit:cover; }
        .grid-tile-name { font-size:14px; font-weight:700; color:#2d3a55; line-height:1.3; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .grid-tile-code { font-size:10px; font-weight:600; color:#8896b0; letter-spacing:.04em; }
        .grid-tile-info { display:flex; flex-direction:column; gap:5px; margin-bottom:12px; min-height:20px; }
        .grid-tile-info div { font-size:12px; color:#5a6a85; display:flex; align-items:center; gap:7px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .grid-tile-info i { width:13px; text-align:center; color:#94a3b8; font-size:11px; flex-shrink:0; }
        .grid-tile-finance { display:flex; justify-content:space-between; align-items:center; padding:10px 12px; background:#f8faff; border-radius:9px; margin-bottom:12px; }
        .grid-tile-finance .blk .val { font-size:13px; font-weight:800; line-height:1.2; }
        .grid-tile-finance .blk .lbl { font-size:9px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:#94a3b8; margin-top:1px; }
        .grid-tile-actions { display:flex; gap:6px; }
        .grid-tile-actions a, .grid-tile-actions button { flex:1; display:flex; align-items:center; justify-content:center; padding:7px; border-radius:8px; font-size:12px; border:1.5px solid; text-decoration:none; transition:opacity .15s; appearance:none; -webkit-appearance:none; cursor:pointer; }
        .grid-tile-actions a:hover, .grid-tile-actions button:hover { opacity:.8; }

        /* Pagination */
        .pag { display:flex; align-items:center; gap:4px; }
        .pag a, .pag span { display:inline-flex; align-items:center; justify-content:center; width:32px; height:32px; border-radius:8px; font-size:12px; font-weight:600; text-decoration:none; border:1.5px solid #e0e6f0; color:#6b7a99; }
        .pag a:hover { border-color:var(--marine); color:var(--marine); }
        .pag span.cur { background:var(--marine); border-color:var(--marine); color:#fff; }
        .pag a.off { opacity:.35; pointer-events:none; }
    </style>
</head>
<body>
<?php include('../includes/sidebar.php'); ?>

<!-- ══ MODAL NOUVEAU BAILLEUR ══ -->
<div class="modal fade" id="modalBailleur" tabindex="-1" aria-labelledby="titreModal" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <form class="modal-content border-0 shadow-lg" action="../php/add_bailleur.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="token" value="<?= htmlspecialchars($csrfToken) ?>">
            <div class="modal-header text-white border-0" style="background:linear-gradient(135deg,#002147,#004080);">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-white bg-opacity-10 d-flex align-items-center justify-content-center" style="width:40px;height:40px;">
                        <i class="fa fa-user-tie text-white"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="titreModal">Nouveau Bailleur</h5>
                        <small class="opacity-75">Remplissez les informations du propriétaire</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="px-4 pt-4 pb-3">
                    <span class="badge rounded-pill text-bg-primary px-3 py-2 mb-3 d-inline-block"><i class="fa fa-id-card me-1"></i>Identité</span>
                    <div class="row g-3">
                        <div class="col-md-2 d-flex flex-column align-items-center justify-content-center">
                            <div id="photoPreview" onclick="document.getElementById('photoInput').click()"
                                 class="rounded-circle border border-2 d-flex align-items-center justify-content-center bg-light overflow-hidden"
                                 style="width:72px;height:72px;cursor:pointer;border-style:dashed!important;">
                                <i class="fa fa-camera text-muted fs-5"></i>
                            </div>
                            <small class="text-muted mt-1" style="font-size:10px;">Photo</small>
                            <input type="file" id="photoInput" name="photo" accept="image/*" class="d-none">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">Nom complet <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="fa fa-user text-muted"></i></span>
                                <input type="text" name="nom" class="form-control border-start-0 ps-0" placeholder="Ex : KOUASSI Jean-Marc" required>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">Sexe</label>
                            <select name="sexe" class="form-select">
                                <option value="M">Masculin</option>
                                <option value="F">Féminin</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">Code</label>
                            <input type="text" class="form-control bg-light text-muted fst-italic" placeholder="Auto" readonly tabindex="-1">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">N° CNI / Passeport</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="fa fa-id-badge text-muted"></i></span>
                                <input type="text" name="numero_cni" class="form-control border-start-0 ps-0" placeholder="CI0012345">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">Adresse</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="fa fa-map-marker-alt text-muted"></i></span>
                                <input type="text" name="adresse" class="form-control border-start-0 ps-0" placeholder="Cocody, Abidjan">
                            </div>
                        </div>
                    </div>
                </div>
                <hr class="mx-4 my-0 opacity-10">
                <div class="px-4 pt-3 pb-4">
                    <span class="badge rounded-pill text-bg-success px-3 py-2 mb-3 d-inline-block"><i class="fa fa-phone me-1"></i>Contacts</span>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">Téléphone principal <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="fa fa-phone text-muted"></i></span>
                                <input type="tel" name="telephone1" class="form-control border-start-0 ps-0" placeholder="07 XX XX XX XX" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">Téléphone secondaire</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="fa fa-mobile-alt text-muted"></i></span>
                                <input type="tel" name="telephone2" class="form-control border-start-0 ps-0" placeholder="01 XX XX XX XX">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">Email</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="fa fa-envelope text-muted"></i></span>
                                <input type="email" name="email" class="form-control border-start-0 ps-0" placeholder="exemple@mail.com">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top bg-light px-4">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">
                    <i class="fa fa-times me-1"></i>Annuler
                </button>
                <button type="submit" class="btn btn-danger px-5 fw-semibold">
                    <i class="fa fa-check me-2"></i>Enregistrer
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ══ MODAL MODIFIER BAILLEUR ══ -->
<div class="modal fade" id="modalEditBailleur" tabindex="-1" aria-labelledby="titreModalEdit" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <form class="modal-content border-0 shadow-lg" action="../php/update_bailleur.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="id" id="editId">
            <div class="modal-header text-white border-0" style="background:linear-gradient(135deg,#002147,#004080);">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-white bg-opacity-10 d-flex align-items-center justify-content-center" style="width:40px;height:40px;">
                        <i class="fa fa-user-edit text-white"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="titreModalEdit">Modifier Bailleur</h5>
                        <small class="opacity-75" id="sousTitreModalEdit">Mettez à jour les informations du propriétaire</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="px-4 pt-4 pb-3">
                    <span class="badge rounded-pill text-bg-primary px-3 py-2 mb-3 d-inline-block"><i class="fa fa-id-card me-1"></i>Identité</span>
                    <div class="row g-3">
                        <div class="col-md-2 d-flex flex-column align-items-center justify-content-center">
                            <div id="photoPreviewEdit" onclick="document.getElementById('photoInputEdit').click()"
                                 class="rounded-circle border border-2 d-flex align-items-center justify-content-center bg-light overflow-hidden"
                                 style="width:72px;height:72px;cursor:pointer;border-style:dashed!important;">
                                <i class="fa fa-camera text-muted fs-5"></i>
                            </div>
                            <small class="text-muted mt-1" style="font-size:10px;">Photo</small>
                            <input type="file" id="photoInputEdit" name="photo" accept="image/*" class="d-none">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">Nom complet <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="fa fa-user text-muted"></i></span>
                                <input type="text" name="nom" id="editNom" class="form-control border-start-0 ps-0" required>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">Sexe</label>
                            <select name="sexe" id="editSexe" class="form-select">
                                <option value="M">Masculin</option>
                                <option value="F">Féminin</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">Code</label>
                            <input type="text" id="editCode" class="form-control bg-light text-muted fst-italic" readonly tabindex="-1">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">N° CNI / Passeport</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="fa fa-id-badge text-muted"></i></span>
                                <input type="text" name="numero_cni" id="editCni" class="form-control border-start-0 ps-0">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">Adresse</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="fa fa-map-marker-alt text-muted"></i></span>
                                <input type="text" name="adresse" id="editAdresse" class="form-control border-start-0 ps-0">
                            </div>
                        </div>
                    </div>
                </div>
                <hr class="mx-4 my-0 opacity-10">
                <div class="px-4 pt-3 pb-3">
                    <span class="badge rounded-pill text-bg-success px-3 py-2 mb-3 d-inline-block"><i class="fa fa-phone me-1"></i>Contacts</span>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">Téléphone principal <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="fa fa-phone text-muted"></i></span>
                                <input type="tel" name="telephone1" id="editTel1" class="form-control border-start-0 ps-0" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">Téléphone secondaire</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="fa fa-mobile-alt text-muted"></i></span>
                                <input type="tel" name="telephone2" id="editTel2" class="form-control border-start-0 ps-0">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">Email</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="fa fa-envelope text-muted"></i></span>
                                <input type="email" name="email" id="editEmail" class="form-control border-start-0 ps-0">
                            </div>
                        </div>
                    </div>
                </div>
                <hr class="mx-4 my-0 opacity-10">
                <div class="px-4 pt-3 pb-4">
                    <span class="badge rounded-pill text-bg-warning px-3 py-2 mb-3 d-inline-block"><i class="fa fa-wallet me-1"></i>Finances</span>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-success text-uppercase" style="letter-spacing:.04em;">Solde actuel (FCFA)</label>
                            <input type="number" step="0.01" id="editSolde" class="form-control border-success bg-light" readonly title="Calculé automatiquement depuis le compte courant — voir Compte Bailleur pour le détail">
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top bg-light px-4">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">
                    <i class="fa fa-times me-1"></i>Annuler
                </button>
                <button type="submit" class="btn btn-danger px-5 fw-semibold">
                    <i class="fa fa-check me-2"></i>Enregistrer les modifications
                </button>
            </div>
        </form>
    </div>
</div>

<div class="main-content">
<div class="top-fixed">

    <!-- En-tête -->
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <div>
            <p class="text-muted small mb-0 mt-1"><?= $totalBailleurs ?> propriétaire<?= $totalBailleurs>1?'s':'' ?> enregistré<?= $totalBailleurs>1?'s':'' ?></p>
        </div>
        <button class="btn btn-danger btn-sm shadow-sm" style="border-radius:8px;"
                data-bs-toggle="modal" data-bs-target="#modalBailleur">
            <i class="fa fa-plus-circle me-2"></i>Nouveau Bailleur
        </button>
    </div>

    <!-- KPI -->
    <div class="row g-2 mb-3">
        <div class="col-6 col-md-3">
            <div class="kpi-card">
                <div class="kpi-icon" style="background:#eef2fb;"><i class="fa fa-users" style="color:var(--marine);"></i></div>
                <div>
                    <div class="kpi-val" style="color:var(--marine);"><?= $totalBailleurs ?></div>
                    <div class="kpi-lbl">Bailleurs</div><div class="kpi-sub">au total</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card">
                <div class="kpi-icon" style="background:#d1fae5;"><i class="fa fa-scale-balanced" style="color:var(--green);"></i></div>
                <div>
                    <div class="kpi-val" style="color:var(--green);"><?= number_format($totalSolde,0,',',' ') ?></div>
                    <div class="kpi-lbl">Solde global</div><div class="kpi-sub">FCFA</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card">
                <div class="kpi-icon" style="background:#fef3c7;"><i class="fa fa-percent" style="color:var(--amber);"></i></div>
                <div>
                    <div class="kpi-val" style="color:var(--amber);"><?= number_format($totalCommissions,0,',',' ') ?></div>
                    <div class="kpi-lbl">Commissions</div><div class="kpi-sub">FCFA</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card">
                <div class="kpi-icon" style="background:#d1fae5;"><i class="fa fa-circle-check" style="color:var(--green);"></i></div>
                <div>
                    <div class="kpi-val" style="color:var(--green);"><?= $nbAvecSolde ?></div>
                    <div class="kpi-lbl">Avec solde</div><div class="kpi-sub">à reverser</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtre + toggle de vue -->
    <div class="filter-bar mb-0">
        <form method="GET" class="d-flex align-items-center gap-2 flex-wrap w-100">
            <i class="fa fa-search text-muted" style="font-size:13px;"></i>
            <input type="text" id="searchInput" name="search" value="<?= htmlspecialchars($search) ?>"
                   class="form-control form-control-sm" style="max-width:300px;border-radius:8px;"
                   placeholder="Nom, code, téléphone…" autocomplete="off">
            <?php if ($search): ?>
            <a href="bailleurs.php" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;"><i class="fa fa-times"></i></a>
            <?php endif; ?>
            <div class="ms-auto text-muted small me-2"><?= $totalRows ?> résultat<?= $totalRows>1?'s':'' ?></div>
            <div class="view-toggle" title="Changer la vue">
                <button type="button" id="btnVueListe" onclick="setView('liste')" title="Vue liste">
                    <i class="fa fa-list-ul"></i>
                </button>
                <button type="button" id="btnVueCarte" onclick="setView('cartes')" title="Vue grille">
                    <i class="fa fa-grip"></i>
                </button>
            </div>
        </form>
    </div>

</div><!-- /top-fixed -->
<div class="bottom-scroll">

    <?php if (empty($bailleurs)): ?>
    <div class="text-center text-muted py-5">
        <i class="fa fa-inbox fa-3x mb-3 d-block" style="opacity:.2;"></i>
        <?= $search ? 'Aucun résultat pour «&nbsp;<strong>' . htmlspecialchars($search) . '</strong>&nbsp;»' : 'Aucun bailleur enregistré.' ?>
    </div>
    <?php else: ?>

    <!-- ══════════════════════════════════ VUE LISTE ══════════════════════════════════ -->
    <div id="vueListe">
        <table class="table mb-0 tbl-full">
                <thead>
                    <tr>
                        <th style="width:28%;">Bailleur</th>
                        <th style="width:20%;">Contact</th>
                        <th style="width:22%;">Adresse</th>
                        <th class="text-end" style="width:14%;">Solde actuel</th>
                        <th class="text-end" style="width:10%;">Commissions</th>
                        <th class="text-center" style="width:6%;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bailleurs as $i => $b):
                        $initiale = mb_strtoupper(mb_substr($b['nom'], 0, 1));
                        $col      = $avatarColors[$i % count($avatarColors)];
                        $solde    = (float)($b['solde_du_bailleur'] ?? 0);
                        $comm     = (float)($b['total_commissions_entreprises'] ?? 0);
                    ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-3">
                                <div class="avatar-sm" style="background:<?= $col['bg'] ?>;color:<?= $col['fg'] ?>;">
                                    <?= htmlspecialchars($initiale) ?>
                                </div>
                                <div>
                                    <div class="fw-semibold" style="color:#2d3a55;"><?= htmlspecialchars($b['nom']) ?></div>
                                    <div class="text-muted" style="font-size:10px;font-weight:600;letter-spacing:.04em;"><?= htmlspecialchars($b['code_bailleur'] ?? '') ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="small"><i class="fa fa-phone me-1 text-muted"></i><?= htmlspecialchars($b['telephone1'] ?? '') ?></div>
                            <?php if (!empty($b['email'])): ?>
                            <div class="small text-muted"><i class="fa fa-envelope me-1"></i><?= htmlspecialchars($b['email']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small"><?= htmlspecialchars($b['adresse'] ?? '—') ?></td>
                        <td class="text-end">
                            <span class="<?= $solde>=0?'solde-pos':'solde-neg' ?>"><?= ($solde<0?'-':'') . number_format(abs($solde),0,',',' ') ?></span>
                            <small class="text-muted d-block" style="font-size:10px;">FCFA</small>
                        </td>
                        <td class="text-end"><span class="comm-pill"><?= number_format($comm,0,',',' ') ?> FCFA</span></td>
                        <td class="text-center" style="white-space:nowrap;">
                            <a href="voir_bailleur.php?id=<?= (int)$b['id'] ?>" class="btn btn-sm btn-outline-primary"   style="border-radius:6px;" title="Voir"><i class="fa fa-eye"></i></a>
                            <button type="button" class="btn btn-sm btn-outline-secondary btn-edit-bailleur" style="border-radius:6px;" title="Modifier"
                                    data-bs-toggle="modal" data-bs-target="#modalEditBailleur"
                                    data-id="<?= (int)$b['id'] ?>"
                                    data-code="<?= htmlspecialchars($b['code_bailleur'] ?? '') ?>"
                                    data-nom="<?= htmlspecialchars($b['nom']) ?>"
                                    data-sexe="<?= htmlspecialchars($b['sexe'] ?? 'M') ?>"
                                    data-cni="<?= htmlspecialchars($b['numero_cni'] ?? '') ?>"
                                    data-email="<?= htmlspecialchars($b['email'] ?? '') ?>"
                                    data-tel1="<?= htmlspecialchars($b['telephone1'] ?? '') ?>"
                                    data-tel2="<?= htmlspecialchars($b['telephone2'] ?? '') ?>"
                                    data-adresse="<?= htmlspecialchars($b['adresse'] ?? '') ?>"
                                    data-solde="<?= htmlspecialchars($b['solde_du_bailleur'] ?? 0) ?>"
                                    data-photo="<?= !empty($b['photo']) ? htmlspecialchars('../uploads/bailleurs/' . $b['photo']) : '' ?>"
                                    data-initiale="<?= htmlspecialchars($initiale) ?>"
                            ><i class="fa fa-edit"></i></button>
                            <a href="compte_bailleur.php?bailleur_id=<?= (int)$b['id'] ?>" class="btn btn-sm btn-outline-success" style="border-radius:6px;" title="Compte courant"><i class="fa fa-wallet"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
        </table>
    </div>

    <!-- ══════════════════════════════════ VUE GRILLE — tuiles compactes ══════════════════════════════════ -->
    <div id="vueCartes" style="padding:20px 20px 0;">
        <div class="row g-3">
            <?php foreach ($bailleurs as $i => $b):
                $initiale = mb_strtoupper(mb_substr($b['nom'], 0, 1));
                $col      = $avatarColors[$i % count($avatarColors)];
                $solde    = (float)($b['solde_du_bailleur'] ?? 0);
                $comm     = (float)($b['total_commissions_entreprises'] ?? 0);
                $photoSrc = !empty($b['photo']) ? '../uploads/bailleurs/' . $b['photo'] : null;
            ?>
            <div class="col-6 col-md-4 col-xl-3">
                <div class="grid-tile">

                    <div class="grid-tile-header">
                        <div class="grid-tile-avatar" style="<?= $photoSrc ? '' : 'background:' . $col['bg'] . ';color:' . $col['fg'] . ';' ?>">
                            <?php if ($photoSrc): ?>
                            <img src="<?= htmlspecialchars($photoSrc) ?>" alt="" onerror="this.parentElement.innerHTML='<?= htmlspecialchars($initiale) ?>';">
                            <?php else: ?>
                            <?= htmlspecialchars($initiale) ?>
                            <?php endif; ?>
                        </div>
                        <div class="min-w-0">
                            <div class="grid-tile-name"><?= htmlspecialchars($b['nom']) ?></div>
                            <?php if (!empty($b['code_bailleur'])): ?>
                            <div class="grid-tile-code"><?= htmlspecialchars($b['code_bailleur']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="grid-tile-info">
                        <?php if (!empty($b['telephone1'])): ?>
                        <div><i class="fa fa-phone"></i><span><?= htmlspecialchars($b['telephone1']) ?></span></div>
                        <?php endif; ?>
                        <?php if (!empty($b['email'])): ?>
                        <div><i class="fa fa-envelope"></i><span><?= htmlspecialchars($b['email']) ?></span></div>
                        <?php endif; ?>
                        <?php if (!empty($b['adresse'])): ?>
                        <div><i class="fa fa-map-marker-alt"></i><span><?= htmlspecialchars($b['adresse']) ?></span></div>
                        <?php endif; ?>
                    </div>

                    <div class="grid-tile-finance">
                        <div class="blk">
                            <div class="val" style="color:<?= $solde >= 0 ? 'var(--green)' : 'var(--red)' ?>;"><?= ($solde < 0 ? '-' : '') . number_format(abs($solde), 0, ',', ' ') ?></div>
                            <div class="lbl">Solde</div>
                        </div>
                        <div class="blk" style="text-align:right;">
                            <div class="val" style="color:var(--amber);"><?= number_format($comm, 0, ',', ' ') ?></div>
                            <div class="lbl">Commissions</div>
                        </div>
                    </div>

                    <div class="grid-tile-actions">
                        <a href="voir_bailleur.php?id=<?= (int)$b['id'] ?>" style="color:#1d4ed8;border-color:#bfdbfe;background:#eff6ff;" title="Voir"><i class="fa fa-eye"></i></a>
                        <button type="button" class="btn-edit-bailleur" style="color:#374151;border-color:#e5e7eb;background:#f9fafb;" title="Modifier"
                                data-bs-toggle="modal" data-bs-target="#modalEditBailleur"
                                data-id="<?= (int)$b['id'] ?>"
                                data-code="<?= htmlspecialchars($b['code_bailleur'] ?? '') ?>"
                                data-nom="<?= htmlspecialchars($b['nom']) ?>"
                                data-sexe="<?= htmlspecialchars($b['sexe'] ?? 'M') ?>"
                                data-cni="<?= htmlspecialchars($b['numero_cni'] ?? '') ?>"
                                data-email="<?= htmlspecialchars($b['email'] ?? '') ?>"
                                data-tel1="<?= htmlspecialchars($b['telephone1'] ?? '') ?>"
                                data-tel2="<?= htmlspecialchars($b['telephone2'] ?? '') ?>"
                                data-adresse="<?= htmlspecialchars($b['adresse'] ?? '') ?>"
                                data-solde="<?= htmlspecialchars($b['solde_du_bailleur'] ?? 0) ?>"
                                data-photo="<?= !empty($b['photo']) ? htmlspecialchars('../uploads/bailleurs/' . $b['photo']) : '' ?>"
                                data-initiale="<?= htmlspecialchars($initiale) ?>"
                        ><i class="fa fa-edit"></i></button>
                        <a href="compte_bailleur.php?bailleur_id=<?= (int)$b['id'] ?>" style="color:#065f46;border-color:#a7f3d0;background:#ecfdf5;" title="Compte courant"><i class="fa fa-wallet"></i></a>
                    </div>

                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php endif; ?>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="d-flex justify-content-between align-items-center mt-2" style="padding:16px 28px;background:#f8faff;border-top:1px solid #e8ecf4;">
        <small class="text-muted">Page <?= $page ?> / <?= $totalPages ?> — <?= $totalRows ?> résultat<?= $totalRows>1?'s':'' ?></small>
        <div class="pag">
            <a href="<?= buildUrlB(['page'=>$page-1]) ?>" class="<?= $page<=1?'off':'' ?>"><i class="fa fa-chevron-left" style="font-size:10px;"></i></a>
            <?php
            $start=max(1,$page-2); $end=min($totalPages,$page+2);
            if ($start>1) echo '<span style="border:none;width:auto;color:#aab;">…</span>';
            for ($i=$start;$i<=$end;$i++):
            ?>
            <?php if ($i===$page): ?><span class="cur"><?= $i ?></span>
            <?php else: ?><a href="<?= buildUrlB(['page'=>$i]) ?>"><?= $i ?></a>
            <?php endif; endfor;
            if ($end<$totalPages) echo '<span style="border:none;width:auto;color:#aab;">…</span>';
            ?>
            <a href="<?= buildUrlB(['page'=>$page+1]) ?>" class="<?= $page>=$totalPages?'off':'' ?>"><i class="fa fa-chevron-right" style="font-size:10px;"></i></a>
        </div>
    </div>
    <?php endif; ?>

</div><!-- /bottom-scroll -->
</div><!-- /main-content -->

<script src="../js/bootstrap.bundle.min.js"></script>
<script>
function setView(v) {
    document.getElementById('vueListe').style.display  = v === 'liste'  ? 'block' : 'none';
    document.getElementById('vueCartes').style.display = v === 'cartes' ? 'block' : 'none';
    document.getElementById('btnVueListe').classList.toggle('active', v === 'liste');
    document.getElementById('btnVueCarte').classList.toggle('active', v === 'cartes');
    localStorage.setItem('bailleurView', v);
}

setView(localStorage.getItem('bailleurView') || 'cartes');

// Recherche live avec debounce 350ms
var searchTimer;
document.getElementById('searchInput').addEventListener('input', function() {
    clearTimeout(searchTimer);
    var val = this.value;
    searchTimer = setTimeout(function() {
        document.querySelector('.filter-bar form').submit();
    }, 350);
});

document.getElementById('photoInput').addEventListener('change', function() {
    var file = this.files[0];
    if (!file) return;
    var reader = new FileReader();
    reader.onload = function(e) {
        var p = document.getElementById('photoPreview');
        p.innerHTML = '<img src="' + e.target.result + '" style="width:100%;height:100%;object-fit:cover;">';
    };
    reader.readAsDataURL(file);
});

// ── Modale Modifier Bailleur : pré-remplissage depuis les data-* du bouton cliqué ──
document.getElementById('modalEditBailleur').addEventListener('show.bs.modal', function(event) {
    var btn = event.relatedTarget;
    var d = btn.dataset;

    document.getElementById('editId').value      = d.id;
    document.getElementById('editCode').value    = d.code;
    document.getElementById('editNom').value     = d.nom;
    document.getElementById('editSexe').value    = d.sexe || 'M';
    document.getElementById('editCni').value     = d.cni;
    document.getElementById('editEmail').value   = d.email;
    document.getElementById('editTel1').value    = d.tel1;
    document.getElementById('editTel2').value    = d.tel2;
    document.getElementById('editAdresse').value = d.adresse;
    document.getElementById('editSolde').value   = d.solde;
    document.getElementById('sousTitreModalEdit').textContent = d.nom;

    var preview = document.getElementById('photoPreviewEdit');
    preview.innerHTML = '';
    if (d.photo) {
        var img = document.createElement('img');
        img.src = d.photo;
        img.style.cssText = 'width:100%;height:100%;object-fit:cover;';
        img.onerror = function () { preview.innerHTML = '<i class="fa fa-camera text-muted fs-5"></i>'; };
        preview.appendChild(img);
    } else {
        preview.innerHTML = '<i class="fa fa-camera text-muted fs-5"></i>';
    }
    document.getElementById('photoInputEdit').value = '';
});

document.getElementById('photoInputEdit').addEventListener('change', function() {
    var file = this.files[0];
    if (!file) return;
    var reader = new FileReader();
    reader.onload = function(e) {
        var p = document.getElementById('photoPreviewEdit');
        p.innerHTML = '<img src="' + e.target.result + '" style="width:100%;height:100%;object-fit:cover;">';
    };
    reader.readAsDataURL(file);
});
</script>
</body>
</html>
