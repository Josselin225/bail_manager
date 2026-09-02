<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once('../config/db.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_role    = $_SESSION['role'] ?? 'visiteur';
$search       = trim($_GET['search']     ?? '');
$filtreStatut = trim($_GET['statut']     ?? '');
$filtreDate   = trim($_GET['date_debut'] ?? '');
$perPage      = 12;
$page         = max(1, (int)($_GET['page'] ?? 1));

$conds = ['1=1']; $bind = [];
if ($search)       { $conds[] = "(l.nom LIKE :s OR m.designation LIKE :s2)"; $bind[':s']=$bind[':s2']="%$search%"; }
if ($filtreStatut) { $conds[] = "c.statut_contrat = :statut"; $bind[':statut'] = $filtreStatut; }
if ($filtreDate)   { $conds[] = "c.date_debut = :date_debut"; $bind[':date_debut'] = $filtreDate; }
$where = implode(" AND ", $conds);

$base  = "FROM contrats c JOIN locataires l ON c.locataire_id=l.id JOIN maisons m ON c.maison_id=m.id WHERE $where";
$stmtC = $pdo->prepare("SELECT COUNT(*) $base"); $stmtC->execute($bind); $totalRows = (int)$stmtC->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

$stmtList = $pdo->prepare("SELECT c.*, l.nom AS nom_locataire, m.designation AS nom_maison $base ORDER BY c.id DESC LIMIT :lim OFFSET :off");
foreach ($bind as $k => $v) $stmtList->bindValue($k, $v);
$stmtList->bindValue(':lim', $perPage, PDO::PARAM_INT);
$stmtList->bindValue(':off', $offset,  PDO::PARAM_INT);
$stmtList->execute();
$contrats = $stmtList->fetchAll();

// KPI
$totalContrats = (int)  $pdo->query("SELECT COUNT(*) FROM contrats")->fetchColumn();
$nbActifs      = (int)  $pdo->query("SELECT COUNT(*) FROM contrats WHERE statut_contrat='actif'")->fetchColumn();
$nbResilies    = (int)  $pdo->query("SELECT COUNT(*) FROM contrats WHERE statut_contrat='résilié'")->fetchColumn();
$loyerTotal    = (float)$pdo->query("SELECT COALESCE(SUM(loyer_mensuel),0) FROM contrats WHERE statut_contrat='actif'")->fetchColumn();

$maisons_libres   = $pdo->query("SELECT id, designation, loyer, `condition` FROM maisons WHERE statut='disponible'")->fetchAll();
$locataires_liste = $pdo->query("SELECT id, nom FROM locataires ORDER BY nom")->fetchAll();

$renewContrat = null;
$renewId = (int)($_GET['renew_id'] ?? 0);
if ($renewId > 0) {
    $stmtRenew = $pdo->prepare("SELECT c.id, c.date_fin, l.nom AS nom_locataire FROM contrats c JOIN locataires l ON c.locataire_id = l.id WHERE c.id = ? AND c.statut_contrat = 'actif'");
    $stmtRenew->execute([$renewId]);
    $renewContrat = $stmtRenew->fetch();
}

function buildUrlCt(array $extra = []): string {
    global $search, $page, $filtreStatut, $filtreDate;
    $p = array_filter(['search'=>$search,'statut'=>$filtreStatut,'date_debut'=>$filtreDate,'page'=>$page], fn($v)=>$v!==''&&$v!==null&&$v!==0);
    return '?' . http_build_query(array_merge($p, $extra));
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../favicon.svg">
    <title>Gestion des Contrats — BailManager</title>
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
        .b-card-body { padding:16px; }
        .b-name { font-size:13px; font-weight:700; color:#1e293b; }
        .b-sub  { font-size:11px; color:#8896b0; margin-top:2px; }
        .b-card-info { display:flex; flex-direction:column; gap:5px; margin-top:10px; }
        .b-info-row { display:flex; align-items:center; gap:7px; font-size:11px; color:#5a6a85; }
        .b-info-row i { width:13px; text-align:center; color:#8896b0; flex-shrink:0; }
        .b-card-actions { padding:10px 14px; border-top:1px solid #f0f3fa; display:flex; gap:5px; }
        .b-card-actions a { flex:1; text-align:center; padding:5px 4px; border-radius:8px; font-size:11px; font-weight:600; text-decoration:none; border:1.5px solid; transition:opacity .15s; }
        .b-card-actions a:hover { opacity:.75; }
        .st-actif    { background:#d1fae5; color:#065f46; font-size:11px; font-weight:600; padding:3px 9px; border-radius:20px; }
        .st-resilié  { background:#f1f5f9; color:#64748b; font-size:11px; font-weight:600; padding:3px 9px; border-radius:20px; }
        .pag { display:flex; align-items:center; gap:4px; }
        .pag a, .pag span { display:inline-flex; align-items:center; justify-content:center; width:32px; height:32px; border-radius:8px; font-size:12px; font-weight:600; text-decoration:none; border:1.5px solid #e0e6f0; color:#6b7a99; }
        .pag a:hover { border-color:var(--marine); color:var(--marine); }
        .pag span.cur { background:var(--marine); border-color:var(--marine); color:#fff; }
        .pag a.off { opacity:.35; pointer-events:none; }
    </style>
</head>
<body>
<?php include('../includes/sidebar.php'); ?>

<!-- ══ MODAL NOUVEAU CONTRAT ══ -->
<div class="modal fade" id="modalNouveauContrat" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <form class="modal-content border-0 shadow-lg" action="../php/add_contrat.php" method="POST">
            <input type="hidden" name="token" value="<?= csrf_generate() ?>">
            <div class="modal-header text-white border-0" style="background:linear-gradient(135deg,#7f1d1d,#dc2626);">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-white bg-opacity-10 d-flex align-items-center justify-content-center" style="width:40px;height:40px;"><i class="fa fa-file-contract text-white"></i></div>
                    <div><h5 class="modal-title fw-bold mb-0">Établir un Contrat de Bail</h5><small class="opacity-75">Nouveau contrat de location</small></div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="px-4 pt-4 pb-3">
                    <span class="badge rounded-pill text-bg-primary px-3 py-2 mb-3 d-inline-block"><i class="fa fa-handshake me-1"></i>Parties</span>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">Maison disponible <span class="text-danger">*</span></label>
                            <div class="input-group"><span class="input-group-text bg-light border-end-0"><i class="fa fa-home text-muted"></i></span>
                            <select name="maison_id" id="selectMaison" class="form-select border-start-0 js-search-select" data-placeholder="Rechercher une maison…" style="border-radius:0 .375rem .375rem 0" required>
                                <option value="" data-loyer="0">Choisir une maison…</option>
                                <?php foreach($maisons_libres as $m): ?><option value="<?= $m['id'] ?>" data-loyer="<?= $m['loyer'] ?>"><?= htmlspecialchars($m['designation']) ?></option><?php endforeach; ?>
                            </select></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">Locataire <span class="text-danger">*</span></label>
                            <div class="input-group"><span class="input-group-text bg-light border-end-0"><i class="fa fa-user text-muted"></i></span>
                            <select name="locataire_id" class="form-select border-start-0 js-search-select" data-placeholder="Rechercher un locataire…" style="border-radius:0 .375rem .375rem 0" required>
                                <option value="">Choisir un locataire…</option>
                                <?php foreach($locataires_liste as $l): ?><option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['nom']) ?></option><?php endforeach; ?>
                            </select></div>
                        </div>
                    </div>
                </div>
                <hr class="mx-4 my-0 opacity-10">
                <div class="px-4 pt-3 pb-3">
                    <span class="badge rounded-pill text-bg-success px-3 py-2 mb-3 d-inline-block"><i class="fa fa-coins me-1"></i>Conditions financières</span>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">Loyer mensuel</label>
                            <div class="input-group"><span class="input-group-text bg-light border-end-0"><i class="fa fa-money-bill text-muted"></i></span><input type="number" name="loyer_mensuel" id="inputLoyer" class="form-control border-start-0 ps-0 bg-light fst-italic" readonly required></div>
                            <small class="text-muted" style="font-size:10px;">Rempli automatiquement</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">Dépôt de garantie <span class="text-danger">*</span></label>
                            <div class="input-group"><span class="input-group-text bg-light border-end-0"><i class="fa fa-shield-alt text-muted"></i></span><input type="number" name="depot_garantie" id="inputCaution" class="form-control border-start-0 ps-0 bg-light fst-italic" readonly required></div>
                            <small class="text-muted" style="font-size:10px;">2 mois de loyer — rempli automatiquement</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">Avance de loyer <span class="text-danger">*</span></label>
                            <div class="input-group"><span class="input-group-text bg-light border-end-0"><i class="fa fa-money-bill-wave text-muted"></i></span><input type="number" name="avance_loyer" id="inputAvance" class="form-control border-start-0 ps-0" required></div>
                            <small class="text-muted" style="font-size:10px;">2 mois de loyer par défaut — modifiable</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">Droit d'agence <span class="text-danger">*</span></label>
                            <div class="input-group"><span class="input-group-text bg-light border-end-0"><i class="fa fa-handshake text-muted"></i></span><input type="number" name="droit_agence" id="inputDroitAgence" class="form-control border-start-0 ps-0" required></div>
                            <small class="text-muted" style="font-size:10px;">1 mois de loyer par défaut — modifiable</small>
                        </div>
                        <div class="col-12">
                            <div class="d-flex align-items-center justify-content-between p-3 rounded-3" style="background:#f0fdf4; border:1px solid #bbf7d0;">
                                <span class="fw-bold text-uppercase small" style="letter-spacing:.04em; color:#166534;"><i class="fa fa-calculator me-2"></i>Total à payer à la signature</span>
                                <span class="fw-bold fs-5" style="color:#166534;"><span id="inputTotal">0</span> FCFA</span>
                            </div>
                            <small class="text-muted" style="font-size:10px;">Dépôt de garantie + Avance de loyer + Droit d'agence</small>
                        </div>
                    </div>
                </div>
                <hr class="mx-4 my-0 opacity-10">
                <div class="px-4 pt-3 pb-4">
                    <span class="badge rounded-pill text-bg-secondary px-3 py-2 mb-3 d-inline-block"><i class="fa fa-calendar me-1"></i>Dates</span>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">Date de signature <span class="text-danger">*</span></label>
                            <div class="input-group"><span class="input-group-text bg-light border-end-0"><i class="fa fa-pen text-muted"></i></span><input type="date" name="date_contrat" class="form-control border-start-0 ps-0" value="<?= date('Y-m-d') ?>" required></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">Date de prise d'effet <span class="text-danger">*</span></label>
                            <div class="input-group"><span class="input-group-text bg-light border-end-0"><i class="fa fa-play text-muted"></i></span><input type="date" name="date_debut" class="form-control border-start-0 ps-0" value="<?= date('Y-m-d') ?>" required></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top bg-light px-4">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal"><i class="fa fa-times me-1"></i>Annuler</button>
                <button type="submit" class="btn btn-danger px-5 fw-semibold"><i class="fa fa-check me-2"></i>Enregistrer le contrat</button>
            </div>
        </form>
    </div>
</div>

<!-- ══ MODAL RÉSILIATION ══ -->
<div class="modal fade" id="modalResiliation" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content border-0 shadow-lg" action="../php/resilier_contrat.php" method="POST">
            <input type="hidden" name="token" value="<?= csrf_generate() ?>">
            <input type="hidden" name="contrat_id" id="res_id">
            <div class="modal-header text-white border-0" style="background:linear-gradient(135deg,#7f1d1d,#dc2626);">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-white bg-opacity-10 d-flex align-items-center justify-content-center" style="width:40px;height:40px;"><i class="fa fa-times-circle text-white"></i></div>
                    <div><h5 class="modal-title fw-bold mb-0">Résiliation de Contrat</h5><small class="opacity-75" id="res_locataire"></small></div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 py-4">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">Date de résiliation <span class="text-danger">*</span></label>
                        <div class="input-group"><span class="input-group-text bg-light border-end-0"><i class="fa fa-calendar text-muted"></i></span><input type="date" name="date_resiliation" class="form-control border-start-0 ps-0" value="<?= date('Y-m-d') ?>" required></div>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">Motif</label>
                        <textarea name="motif_resiliation" class="form-control" rows="2" placeholder="Raison de la résiliation…"></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">Caution retenue (FCFA)</label>
                        <div class="input-group"><span class="input-group-text bg-light border-end-0"><i class="fa fa-shield-alt text-muted"></i></span><input type="number" name="caution_retenue" id="res_caution" class="form-control border-start-0 ps-0" value="0"></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">Solde locataire</label>
                        <div class="input-group"><span class="input-group-text bg-light border-end-0"><i class="fa fa-money-bill text-muted"></i></span><input type="number" name="solde_locataire" id="res_solde" class="form-control border-start-0 ps-0" value="0"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top bg-light px-4">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal"><i class="fa fa-times me-1"></i>Annuler</button>
                <button type="submit" class="btn btn-danger px-5 fw-semibold"><i class="fa fa-check me-2"></i>Confirmer la résiliation</button>
            </div>
        </form>
    </div>
</div>

<!-- ══ MODAL ÉCHÉANCE / RENOUVELLEMENT ══ -->
<div class="modal fade" id="modalDateFin" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content border-0 shadow-lg" action="../php/definir_date_fin.php" method="POST">
            <input type="hidden" name="token" value="<?= csrf_generate() ?>">
            <input type="hidden" name="contrat_id" id="fin_id">
            <div class="modal-header text-white border-0" style="background:linear-gradient(135deg,#4338ca,#6366f1);">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-white bg-opacity-10 d-flex align-items-center justify-content-center" style="width:40px;height:40px;"><i class="fa fa-calendar-days text-white"></i></div>
                    <div><h5 class="modal-title fw-bold mb-0">Échéance du Contrat</h5><small class="opacity-75" id="fin_locataire"></small></div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 py-4">
                <label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">Date de fin du bail</label>
                <div class="input-group"><span class="input-group-text bg-light border-end-0"><i class="fa fa-calendar text-muted"></i></span><input type="date" name="date_fin" id="fin_date" class="form-control border-start-0 ps-0"></div>
                <small class="text-muted" style="font-size:10.5px;">Laisser vide pour retirer toute échéance programmée. Une alerte apparaît sur le tableau de bord et le calendrier dans les 30 jours précédant cette date.</small>
            </div>
            <div class="modal-footer border-top bg-light px-4">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal"><i class="fa fa-times me-1"></i>Annuler</button>
                <button type="submit" class="btn btn-primary px-5 fw-semibold"><i class="fa fa-check me-2"></i>Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<div class="main-content">
<div class="top-fixed">

    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <div>
            <p class="text-muted small mb-0 mt-1"><?= $totalContrats ?> contrat<?= $totalContrats>1?'s':'' ?> au total</p>
        </div>
        <button class="btn btn-danger btn-sm shadow-sm" style="border-radius:8px;" data-bs-toggle="modal" data-bs-target="#modalNouveauContrat">
            <i class="fa fa-plus-circle me-2"></i>Établir un Bail
        </button>
    </div>

    <div class="row g-2 mb-3">
        <div class="col-6 col-md-3">
            <div class="kpi-card"><div class="kpi-icon" style="background:#eef2fb;"><i class="fa fa-file-contract" style="color:var(--marine);"></i></div><div><div class="kpi-val" style="color:var(--marine);"><?= $totalContrats ?></div><div class="kpi-lbl">Contrats</div><div class="kpi-sub">au total</div></div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card"><div class="kpi-icon" style="background:#d1fae5;"><i class="fa fa-circle-check" style="color:var(--green);"></i></div><div><div class="kpi-val" style="color:var(--green);"><?= $nbActifs ?></div><div class="kpi-lbl">Actifs</div><div class="kpi-sub">en cours</div></div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card"><div class="kpi-icon" style="background:#f1f5f9;"><i class="fa fa-circle-xmark" style="color:#64748b;"></i></div><div><div class="kpi-val" style="color:#64748b;"><?= $nbResilies ?></div><div class="kpi-lbl">Résiliés</div><div class="kpi-sub">archivés</div></div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card"><div class="kpi-icon" style="background:#d1fae5;"><i class="fa fa-money-bill-wave" style="color:var(--green);"></i></div><div><div class="kpi-val" style="color:var(--green);"><?= number_format($loyerTotal,0,',',' ') ?></div><div class="kpi-lbl">Loyers/mois</div><div class="kpi-sub">FCFA actifs</div></div></div>
        </div>
    </div>

    <div class="filter-bar mb-0">
        <form method="GET" class="d-flex align-items-center gap-2 flex-wrap w-100">
            <i class="fa fa-search text-muted" style="font-size:13px;"></i>
            <input type="text" id="searchInput" name="search" value="<?= htmlspecialchars($search) ?>"
                   class="form-control form-control-sm" style="max-width:220px;border-radius:8px;"
                   placeholder="Locataire, maison…" autocomplete="off">
            <select name="statut" class="form-select form-select-sm" style="max-width:140px;border-radius:8px;" onchange="this.form.submit()">
                <option value="">— Tous statuts —</option>
                <option value="actif"    <?= $filtreStatut==='actif'?'selected':'' ?>>Actif</option>
                <option value="résilié"  <?= $filtreStatut==='résilié'?'selected':'' ?>>Résilié</option>
            </select>
            <input type="date" name="date_debut" value="<?= htmlspecialchars($filtreDate) ?>" class="form-control form-control-sm" style="max-width:150px;border-radius:8px;" onchange="this.form.submit()">
            <?php if ($search || $filtreStatut || $filtreDate): ?>
            <a href="contrats.php" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;"><i class="fa fa-times"></i></a>
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

<?php if (empty($contrats)): ?>
<div class="text-center text-muted py-5"><i class="fa fa-inbox fa-3x mb-3 d-block" style="opacity:.2;"></i>Aucun contrat trouvé.</div>
<?php else: ?>

<!-- VUE LISTE -->
<div id="vueListe">
<table class="table mb-0 tbl-full">
    <thead><tr>
        <th style="width:24%;">Maison</th>
        <th style="width:20%;">Locataire</th>
        <th class="text-end" style="width:14%;">Loyer/mois</th>
        <th style="width:12%;">Début</th>
        <th style="width:10%;">Statut</th>
        <th class="text-center" style="width:20%;">Actions</th>
    </tr></thead>
    <tbody>
    <?php foreach ($contrats as $c): $isActif = $c['statut_contrat']==='actif'; ?>
    <tr>
        <td>
            <div class="fw-semibold" style="color:#2d3a55;"><?= htmlspecialchars($c['nom_maison']) ?></div>
            <div class="text-muted" style="font-size:10px;">Signé le <?= date('d/m/Y', strtotime($c['date_contrat'])) ?></div>
        </td>
        <td><i class="fa fa-user-circle me-1 text-muted"></i><?= htmlspecialchars($c['nom_locataire']) ?></td>
        <td class="text-end fw-bold" style="color:var(--marine);"><?= number_format($c['loyer_mensuel'],0,',',' ') ?> <small class="text-muted fw-normal">FCFA</small></td>
        <td class="text-muted small"><?= date('d/m/Y', strtotime($c['date_debut'])) ?></td>
        <td><span class="<?= $isActif?'st-actif':'st-resilié' ?>"><?= ucfirst($c['statut_contrat']) ?></span></td>
        <td class="text-center" style="white-space:nowrap;">
            <a href="recu_contrat.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-info" style="border-radius:6px;" title="PDF"><i class="fa fa-print"></i></a>
            <a href="documents.php?type=contrat&id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-dark ms-1" style="border-radius:6px;" title="Documents"><i class="fa fa-paperclip"></i></a>
            <?php if ($isActif): ?>
            <button class="btn btn-sm btn-outline-primary ms-1 btn-echeance" style="border-radius:6px;" title="<?= $c['date_fin'] ? 'Renouveler' : 'Définir échéance' ?>"
                    data-bs-toggle="modal" data-bs-target="#modalDateFin"
                    data-id="<?= $c['id'] ?>" data-locataire="<?= htmlspecialchars($c['nom_locataire']) ?>" data-datefin="<?= $c['date_fin'] ?? '' ?>">
                <i class="fa fa-calendar-days"></i>
            </button>
            <?php endif; ?>
            <?php if ($isActif && $user_role==='admin'): ?>
            <button class="btn btn-sm btn-outline-danger ms-1 btn-resilier" style="border-radius:6px;" title="Résilier"
                    data-bs-toggle="modal" data-bs-target="#modalResiliation"
                    data-id="<?= $c['id'] ?>" data-locataire="<?= htmlspecialchars($c['nom_locataire']) ?>"
                    data-caution="<?= $c['depot_garantie'] ?>" data-solde="<?= $c['solde_actuel'] ?? 0 ?>">
                <i class="fa fa-times-circle"></i> Résilier
            </button>
            <?php elseif (!$isActif): ?>
            <a href="voir_recu_resiliation.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-success ms-1" style="border-radius:6px;" title="Reçu résiliation"><i class="fa fa-file-invoice"></i></a>
            <?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<!-- VUE CARTES -->
<div id="vueCartes" style="padding:20px 28px 0;">
<div class="row g-3">
<?php foreach ($contrats as $c): $isActif = $c['statut_contrat']==='actif'; ?>
<div class="col-sm-6 col-lg-4">
    <div class="b-card h-100">
        <div class="b-card-body">
            <div class="d-flex justify-content-between align-items-start gap-2">
                <div>
                    <div class="b-name"><?= htmlspecialchars($c['nom_maison']) ?></div>
                    <div class="b-sub">Signé le <?= date('d/m/Y', strtotime($c['date_contrat'])) ?></div>
                </div>
                <span class="<?= $isActif?'st-actif':'st-resilié' ?>" style="white-space:nowrap;"><?= ucfirst($c['statut_contrat']) ?></span>
            </div>
            <div class="b-card-info">
                <div class="b-info-row"><i class="fa fa-user"></i><span><?= htmlspecialchars($c['nom_locataire']) ?></span></div>
                <div class="b-info-row"><i class="fa fa-money-bill"></i><span class="fw-bold" style="color:var(--marine);"><?= number_format($c['loyer_mensuel'],0,',',' ') ?> FCFA/mois</span></div>
                <div class="b-info-row"><i class="fa fa-calendar"></i><span>Depuis le <?= date('d/m/Y', strtotime($c['date_debut'])) ?></span></div>
            </div>
        </div>
        <div class="b-card-actions">
            <a href="recu_contrat.php?id=<?= $c['id'] ?>" style="color:#0891b2;border-color:#bae6fd;background:#f0f9ff;" title="PDF"><i class="fa fa-print"></i></a>
            <a href="documents.php?type=contrat&id=<?= $c['id'] ?>" style="color:#374151;border-color:#e5e7eb;background:#f9fafb;" title="Documents"><i class="fa fa-paperclip"></i></a>
            <?php if ($isActif): ?>
            <button class="btn-echeance" style="color:#4338ca;border-color:#c7d2fe;background:#eef2ff;" title="<?= $c['date_fin'] ? 'Renouveler' : 'Définir échéance' ?>"
                    data-bs-toggle="modal" data-bs-target="#modalDateFin"
                    data-id="<?= $c['id'] ?>" data-locataire="<?= htmlspecialchars($c['nom_locataire']) ?>" data-datefin="<?= $c['date_fin'] ?? '' ?>">
                <i class="fa fa-calendar-days"></i>
            </button>
            <?php endif; ?>
            <?php if ($isActif && $user_role==='admin'): ?>
            <button class="btn-resilier" style="flex:1;text-align:center;padding:5px 4px;border-radius:8px;font-size:11px;font-weight:600;border:1.5px solid #fecaca;color:#991b1b;background:#fff1f2;cursor:pointer;"
                    data-bs-toggle="modal" data-bs-target="#modalResiliation"
                    data-id="<?= $c['id'] ?>" data-locataire="<?= htmlspecialchars($c['nom_locataire']) ?>"
                    data-caution="<?= $c['depot_garantie'] ?>" data-solde="<?= $c['solde_actuel'] ?? 0 ?>">
                <i class="fa fa-times-circle"></i> Résilier
            </button>
            <?php elseif (!$isActif): ?>
            <a href="voir_recu_resiliation.php?id=<?= $c['id'] ?>" style="color:#065f46;border-color:#a7f3d0;background:#ecfdf5;" title="Reçu"><i class="fa fa-file-invoice"></i></a>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div>
</div>

<?php endif; ?>

<?php if ($totalPages > 1): ?>
<div class="d-flex justify-content-between align-items-center" style="padding:14px 28px;background:#f8faff;border-top:1px solid #e8ecf4;">
    <small class="text-muted">Page <?= $page ?> / <?= $totalPages ?> — <?= $totalRows ?> résultat<?= $totalRows>1?'s':'' ?></small>
    <div class="pag">
        <a href="<?= buildUrlCt(['page'=>$page-1]) ?>" class="<?= $page<=1?'off':'' ?>"><i class="fa fa-chevron-left" style="font-size:10px;"></i></a>
        <?php $s=max(1,$page-2);$e=min($totalPages,$page+2);if($s>1)echo'<span style="border:none;width:auto;color:#aab;">…</span>';for($i=$s;$i<=$e;$i++):?>
        <?php if($i===$page):?><span class="cur"><?=$i?></span><?php else:?><a href="<?=buildUrlCt(['page'=>$i])?>"><?=$i?></a><?php endif;endfor;if($e<$totalPages)echo'<span style="border:none;width:auto;color:#aab;">…</span>';?>
        <a href="<?= buildUrlCt(['page'=>$page+1]) ?>" class="<?= $page>=$totalPages?'off':'' ?>"><i class="fa fa-chevron-right" style="font-size:10px;"></i></a>
    </div>
</div>
<?php endif; ?>

</div><!-- /bottom-scroll -->
</div><!-- /main-content -->

<script src="../js/bootstrap.bundle.min.js"></script>
<script src="../js/searchable-select.js"></script>
<script>
function setView(v) {
    document.getElementById('vueListe').style.display  = v==='liste'  ? 'block':'none';
    document.getElementById('vueCartes').style.display = v==='cartes' ? 'block':'none';
    document.getElementById('btnVueListe').classList.toggle('active', v==='liste');
    document.getElementById('btnVueCarte').classList.toggle('active', v==='cartes');
    localStorage.setItem('contratView', v);
}
setView(localStorage.getItem('contratView') || 'liste');

var searchTimer;
document.getElementById('searchInput').addEventListener('input', function() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(function() { document.querySelector('.filter-bar form').submit(); }, 350);
});

function updateTotalAPayer() {
    var caution = parseFloat(document.getElementById('inputCaution').value) || 0;
    var avance  = parseFloat(document.getElementById('inputAvance').value) || 0;
    var droit   = parseFloat(document.getElementById('inputDroitAgence').value) || 0;
    document.getElementById('inputTotal').textContent = (caution + avance + droit).toLocaleString('fr-FR');
}

document.getElementById('selectMaison').addEventListener('change', function() {
    var loyer = this.options[this.selectedIndex].getAttribute('data-loyer');
    document.getElementById('inputLoyer').value = loyer > 0 ? loyer : '';
    document.getElementById('inputCaution').value = loyer > 0 ? loyer * 2 : '';
    document.getElementById('inputAvance').value = loyer > 0 ? loyer * 2 : '';
    document.getElementById('inputDroitAgence').value = loyer > 0 ? loyer * 1 : '';
    updateTotalAPayer();
});

['inputAvance', 'inputDroitAgence'].forEach(function(id) {
    document.getElementById(id).addEventListener('input', updateTotalAPayer);
});

document.querySelectorAll('.btn-resilier').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.getElementById('res_id').value         = this.dataset.id;
        document.getElementById('res_locataire').textContent = 'Locataire : ' + this.dataset.locataire;
        document.getElementById('res_caution').value    = this.dataset.caution || 0;
        document.getElementById('res_solde').value      = this.dataset.solde   || 0;
    });
});

document.querySelectorAll('.btn-echeance').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.getElementById('fin_id').value = this.dataset.id;
        document.getElementById('fin_locataire').textContent = 'Locataire : ' + this.dataset.locataire;
        document.getElementById('fin_date').value = this.dataset.datefin || '';
    });
});

<?php if ($renewContrat): ?>
document.getElementById('fin_id').value = <?= (int)$renewContrat['id'] ?>;
document.getElementById('fin_locataire').textContent = 'Locataire : ' + <?= json_encode($renewContrat['nom_locataire']) ?>;
document.getElementById('fin_date').value = <?= json_encode($renewContrat['date_fin'] ?? '') ?>;
new bootstrap.Modal(document.getElementById('modalDateFin')).show();
<?php endif; ?>
</script>
</body>
</html>
