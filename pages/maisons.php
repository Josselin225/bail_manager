<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once('../config/db.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$search  = trim($_GET['search']  ?? '');
$filtreType   = trim($_GET['type']   ?? '');
$filtreStatut = trim($_GET['statut'] ?? '');
$filtreDateEnr  = trim($_GET['date_enr']  ?? '');
$filtreMoisEnr  = trim($_GET['mois_enr']  ?? '');
$filtreAnneeEnr = trim($_GET['annee_enr'] ?? '');
$perPage = 12;
$page    = max(1, (int)($_GET['page'] ?? 1));

$conds = []; $bind = [];
if ($search)       { $conds[] = "(m.designation LIKE :s OR m.adresse LIKE :s2 OR b.nom LIKE :s3)"; $bind[':s']=$bind[':s2']=$bind[':s3']="%$search%"; }
if ($filtreType)   { $conds[] = "m.type_maison = :type";   $bind[':type']   = $filtreType; }
if ($filtreStatut) { $conds[] = "m.statut = :statut";      $bind[':statut'] = $filtreStatut; }
if ($filtreDateEnr)       { $conds[] = "DATE(m.created_at) = :date_enr";              $bind[':date_enr']  = $filtreDateEnr; }
elseif ($filtreMoisEnr)  { $conds[] = "DATE_FORMAT(m.created_at,'%Y-%m') = :mois_enr"; $bind[':mois_enr']  = $filtreMoisEnr; }
elseif ($filtreAnneeEnr) { $conds[] = "YEAR(m.created_at) = :annee_enr";               $bind[':annee_enr'] = $filtreAnneeEnr; }
$where = $conds ? "WHERE " . implode(" AND ", $conds) : "";

$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM maisons m JOIN bailleurs b ON m.bailleur_id=b.id $where");
$stmtCount->execute($bind);
$totalRows  = (int)$stmtCount->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

$stmtList = $pdo->prepare("SELECT m.*, b.nom AS nom_bailleur, l.nom AS locataire_actuel, c.loyer_mensuel AS loyer_contrat
     FROM maisons m JOIN bailleurs b ON m.bailleur_id=b.id
     LEFT JOIN contrats c ON m.id=c.maison_id AND c.statut_contrat='actif'
     LEFT JOIN locataires l ON c.locataire_id=l.id
     $where ORDER BY m.id DESC LIMIT :lim OFFSET :off");
foreach ($bind as $k => $v) $stmtList->bindValue($k, $v);
$stmtList->bindValue(':lim', $perPage, PDO::PARAM_INT);
$stmtList->bindValue(':off', $offset,  PDO::PARAM_INT);
$stmtList->execute();
$maisons = $stmtList->fetchAll();

// Jeu de données complet (toutes pages confondues, mêmes filtres) pour les exports PDF/Excel
$stmtAllM = $pdo->prepare("SELECT m.*, b.nom AS nom_bailleur, l.nom AS locataire_actuel, c.loyer_mensuel AS loyer_contrat
     FROM maisons m JOIN bailleurs b ON m.bailleur_id=b.id
     LEFT JOIN contrats c ON m.id=c.maison_id AND c.statut_contrat='actif'
     LEFT JOIN locataires l ON c.locataire_id=l.id
     $where ORDER BY m.id DESC");
$stmtAllM->execute($bind);
$exportRowsMaisons = array_map(function ($m) {
    return [
        'designation' => $m['designation'],
        'type'        => $m['type_maison'] ?: '—',
        'bailleur'    => $m['nom_bailleur'],
        'adresse'     => $m['adresse'] ?: '—',
        'statut'      => $m['statut'] === 'disponible' ? 'Disponible' : 'Occupé',
        'locataire'   => $m['locataire_actuel'] ?: '—',
        'loyer_num'   => (float)($m['loyer'] ?? 0),
        'date_enr'    => !empty($m['created_at']) ? date('d/m/Y', strtotime($m['created_at'])) : '—',
    ];
}, $stmtAllM->fetchAll());

$entrepriseExport = $pdo->query("SELECT * FROM settings LIMIT 1")->fetch();
if (!$entrepriseExport) {
    $entrepriseExport = ['nom_entreprise' => 'BailManager', 'adresse_siege' => '', 'contact_telephone' => '', 'contact_email' => ''];
}

// KPI
$totalMaisons = (int)$pdo->query("SELECT COUNT(*) FROM maisons")->fetchColumn();
$nbDispos     = (int)$pdo->query("SELECT COUNT(*) FROM maisons WHERE statut='disponible'")->fetchColumn();
$nbOccupes    = (int)$pdo->query("SELECT COUNT(*) FROM maisons WHERE statut='occupe'")->fetchColumn();
$loyerMoyen     = (float)$pdo->query("SELECT AVG(loyer) FROM maisons")->fetchColumn();
$revenuMensuel  = (float)$pdo->query("SELECT COALESCE(SUM(loyer),0) FROM maisons WHERE statut='occupe'")->fetchColumn();
$tauxOccupation = $totalMaisons > 0 ? round(($nbOccupes / $totalMaisons) * 100) : 0;

$bailleurs = $pdo->query("SELECT id, nom FROM bailleurs ORDER BY nom ASC")->fetchAll();

function buildUrlM(array $extra = []): string {
    global $search, $page, $filtreType, $filtreStatut, $filtreDateEnr, $filtreMoisEnr, $filtreAnneeEnr;
    $p = array_filter(['search'=>$search,'type'=>$filtreType,'statut'=>$filtreStatut,'page'=>$page,'date_enr'=>$filtreDateEnr,'mois_enr'=>$filtreMoisEnr,'annee_enr'=>$filtreAnneeEnr], fn($v)=>$v!==''&&$v!==null&&$v!==0);
    return '?' . http_build_query(array_merge($p, $extra));
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../favicon.svg">
    <title>Parc Immobilier — BailManager</title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/fontawesome/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        :root { --marine:#002147; --green:#059669; --amber:#d97706; --red:#e53e3e; }
        .main-content  { background:#f4f7fe; height:100vh; display:flex; flex-direction:column; overflow:hidden; }
        .top-fixed     { padding:18px 28px 0; flex-shrink:0; }
        .bottom-scroll { flex:1; overflow-y:auto; overflow-x:hidden; }
        .kpi-card  { background:#fff; border-radius:12px; padding:12px 16px; display:flex; align-items:center; gap:12px; box-shadow:0 2px 10px rgba(0,0,0,.06); border:1px solid #e8ecf4; height:100%; }
        .kpi-icon  { width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:16px; flex-shrink:0; }
        .kpi-val   { font-size:1.1rem; font-weight:800; line-height:1.1; }
        .kpi-lbl   { font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:.05em; color:#8896b0; margin-top:1px; }
        .kpi-sub   { font-size:10px; color:#aab; }
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
        .b-card-img { width:100%; height:140px; object-fit:cover; }
        .b-card-img-ph { width:100%; height:140px; background:#f1f5f9; display:flex; align-items:center; justify-content:center; font-size:2rem; color:#cbd5e1; }
        .b-card-body { padding:12px 14px; }
        .b-name { font-size:13px; font-weight:700; color:#1e293b; line-height:1.3; }
        .b-code { font-size:10px; color:#8896b0; margin-top:1px; }
        .b-card-info { display:flex; flex-direction:column; gap:4px; margin-top:8px; }
        .b-info-row { display:flex; align-items:center; gap:7px; font-size:11px; color:#5a6a85; }
        .b-info-row i { width:13px; text-align:center; color:#8896b0; flex-shrink:0; }
        .b-card-actions { padding:10px 14px; border-top:1px solid #f0f3fa; display:flex; gap:5px; }
        .b-card-actions a, .b-card-actions button.btn-edit-maison { flex:1; text-align:center; padding:5px 4px; border-radius:8px; font-size:12px; font-weight:600; text-decoration:none; border:1.5px solid; transition:opacity .15s; appearance:none; -webkit-appearance:none; cursor:pointer; }
        .b-card-actions a:hover, .b-card-actions button.btn-edit-maison:hover { opacity:.75; }
        .stat-dispo  { background:#d1fae5; color:#065f46; font-size:11px; font-weight:600; padding:3px 9px; border-radius:20px; }
        .stat-occupe { background:#fee2e2; color:#991b1b; font-size:11px; font-weight:600; padding:3px 9px; border-radius:20px; }
        .pag { display:flex; align-items:center; gap:4px; }
        .pag a, .pag span { display:inline-flex; align-items:center; justify-content:center; width:32px; height:32px; border-radius:8px; font-size:12px; font-weight:600; text-decoration:none; border:1.5px solid #e0e6f0; color:#6b7a99; }
        .pag a:hover { border-color:var(--marine); color:var(--marine); }
        .pag span.cur { background:var(--marine); border-color:var(--marine); color:#fff; }
        .pag a.off { opacity:.35; pointer-events:none; }
    </style>
</head>
<body>
<?php include('../includes/sidebar.php'); ?>

<!-- ══ MODAL AJOUT MAISON ══ -->
<div class="modal fade" id="modalMaison" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <form class="modal-content border-0 shadow-lg" action="../php/add_maison.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="token" value="<?= csrf_generate() ?>">
            <div class="modal-header text-white border-0" style="background:linear-gradient(135deg,#002147,#004080);">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-white bg-opacity-10 d-flex align-items-center justify-content-center" style="width:40px;height:40px;"><i class="fa fa-home text-white"></i></div>
                    <div><h5 class="modal-title fw-bold mb-0">Nouvelle Unité d'Habitation</h5><small class="opacity-75">Enregistrez le bien immobilier</small></div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="px-4 pt-4 pb-3">
                    <span class="badge rounded-pill text-bg-primary px-3 py-2 mb-3 d-inline-block"><i class="fa fa-building me-1"></i>Bien immobilier</span>
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">Désignation <span class="text-danger">*</span></label>
                            <div class="input-group"><span class="input-group-text bg-light border-end-0"><i class="fa fa-tag text-muted"></i></span><input type="text" name="designation" class="form-control border-start-0 ps-0" placeholder="Villa Duplex Cité Verte" required></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">Type <span class="text-danger">*</span></label>
                            <select name="type_maison" class="form-select" required>
                                <option value="Studio">Studio</option><option value="2 Pièces">2 Pièces</option><option value="3 Pièces">3 Pièces</option>
                                <option value="4 Pièces">4 Pièces</option><option value="Appartement">Appartement</option><option value="Villa">Villa</option><option value="Magasin">Magasin</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">Propriétaire <span class="text-danger">*</span></label>
                            <div class="input-group"><span class="input-group-text bg-light border-end-0"><i class="fa fa-user-tie text-muted"></i></span>
                            <select name="bailleur_id" class="form-select border-start-0" style="border-radius:0 .375rem .375rem 0" required>
                                <option value="">Choisir…</option>
                                <?php foreach($bailleurs as $b): ?><option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['nom']) ?></option><?php endforeach; ?>
                            </select></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">Adresse <span class="text-danger">*</span></label>
                            <div class="input-group"><span class="input-group-text bg-light border-end-0"><i class="fa fa-map-marker-alt text-muted"></i></span><input type="text" name="adresse" class="form-control border-start-0 ps-0" placeholder="Quartier, Commune…" required></div>
                        </div>
                        <div class="col-12"><label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">Description</label><textarea name="description" class="form-control" rows="2" placeholder="Caractéristiques, équipements…"></textarea></div>
                    </div>
                </div>
                <hr class="mx-4 my-0 opacity-10">
                <div class="px-4 pt-3 pb-3">
                    <span class="badge rounded-pill text-bg-success px-3 py-2 mb-3 d-inline-block"><i class="fa fa-coins me-1"></i>Financier</span>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">Loyer mensuel (FCFA) <span class="text-danger">*</span></label>
                            <div class="input-group"><span class="input-group-text bg-light border-end-0"><i class="fa fa-money-bill text-muted"></i></span><input type="number" name="loyer" class="form-control border-start-0 ps-0" required></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">Condition (mois)</label>
                            <div class="input-group"><span class="input-group-text bg-light border-end-0"><i class="fa fa-calendar text-muted"></i></span><input type="number" name="condition" class="form-control border-start-0 ps-0" value="1"></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">Statut</label>
                            <select name="statut" class="form-select"><option value="disponible">🟢 Disponible</option><option value="occupe">🔴 Occupé</option></select>
                        </div>
                    </div>
                </div>
                <hr class="mx-4 my-0 opacity-10">
                <div class="px-4 pt-3 pb-4">
                    <span class="badge rounded-pill text-bg-secondary px-3 py-2 mb-3 d-inline-block"><i class="fa fa-camera me-1"></i>Photos</span>
                    <div class="row g-3">
                        <div class="col-md-4"><label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">Photo 1 (principale)</label><input type="file" name="img1" class="form-control form-control-sm" accept="image/*"></div>
                        <div class="col-md-4"><label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">Photo 2</label><input type="file" name="img2" class="form-control form-control-sm" accept="image/*"></div>
                        <div class="col-md-4"><label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">Photo 3</label><input type="file" name="img3" class="form-control form-control-sm" accept="image/*"></div>
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

<!-- ══ MODAL MODIFIER MAISON ══ -->
<div class="modal fade" id="modalEditMaison" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <form class="modal-content border-0 shadow-lg" action="../php/update_maison.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="token" value="<?= csrf_generate() ?>">
            <input type="hidden" name="id" id="editMaisonId">
            <div class="modal-header text-white border-0" style="background:linear-gradient(135deg,#002147,#004080);">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-white bg-opacity-10 d-flex align-items-center justify-content-center" style="width:40px;height:40px;"><i class="fa fa-edit text-white"></i></div>
                    <div><h5 class="modal-title fw-bold mb-0">Modifier : <span id="editMaisonTitre"></span></h5><small class="opacity-75">Mettez à jour le bien immobilier</small></div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="px-4 pt-4 pb-3">
                    <span class="badge rounded-pill text-bg-primary px-3 py-2 mb-3 d-inline-block"><i class="fa fa-building me-1"></i>Bien immobilier</span>
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">Désignation <span class="text-danger">*</span></label>
                            <div class="input-group"><span class="input-group-text bg-light border-end-0"><i class="fa fa-tag text-muted"></i></span><input type="text" name="designation" id="editDesignation" class="form-control border-start-0 ps-0" required></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">Type <span class="text-danger">*</span></label>
                            <select name="type_maison" id="editTypeMaison" class="form-select" required>
                                <option value="Studio">Studio</option><option value="2 Pièces">2 Pièces</option><option value="3 Pièces">3 Pièces</option>
                                <option value="4 Pièces">4 Pièces</option><option value="Appartement">Appartement</option><option value="Villa">Villa</option><option value="Magasin">Magasin</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">Propriétaire <span class="text-danger">*</span></label>
                            <div class="input-group"><span class="input-group-text bg-light border-end-0"><i class="fa fa-user-tie text-muted"></i></span>
                            <select name="bailleur_id" id="editBailleurId" class="form-select border-start-0" style="border-radius:0 .375rem .375rem 0" required>
                                <?php foreach($bailleurs as $b): ?><option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['nom']) ?></option><?php endforeach; ?>
                            </select></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">Adresse <span class="text-danger">*</span></label>
                            <div class="input-group"><span class="input-group-text bg-light border-end-0"><i class="fa fa-map-marker-alt text-muted"></i></span><input type="text" name="adresse" id="editAdresse" class="form-control border-start-0 ps-0" required></div>
                        </div>
                        <div class="col-12"><label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">Description</label><textarea name="description" id="editDescription" class="form-control" rows="2"></textarea></div>
                    </div>
                </div>
                <hr class="mx-4 my-0 opacity-10">
                <div class="px-4 pt-3 pb-3">
                    <span class="badge rounded-pill text-bg-success px-3 py-2 mb-3 d-inline-block"><i class="fa fa-coins me-1"></i>Financier</span>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">Loyer mensuel (FCFA) <span class="text-danger">*</span></label>
                            <div class="input-group"><span class="input-group-text bg-light border-end-0"><i class="fa fa-money-bill text-muted"></i></span><input type="number" name="loyer" id="editLoyer" class="form-control border-start-0 ps-0" required></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">Condition (mois)</label>
                            <div class="input-group"><span class="input-group-text bg-light border-end-0"><i class="fa fa-calendar text-muted"></i></span><input type="number" name="condition" id="editCondition" class="form-control border-start-0 ps-0"></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">Statut</label>
                            <select name="statut" id="editStatut" class="form-select"><option value="disponible">🟢 Disponible</option><option value="occupe">🔴 Occupé</option></select>
                        </div>
                    </div>
                </div>
                <hr class="mx-4 my-0 opacity-10">
                <div class="px-4 pt-3 pb-4">
                    <span class="badge rounded-pill text-bg-secondary px-3 py-2 mb-3 d-inline-block"><i class="fa fa-camera me-1"></i>Photos</span>
                    <div class="row g-3">
                        <div class="col-md-4 text-center">
                            <img id="editImg1Preview" src="" class="img-thumbnail mb-2" style="height:100px;width:100%;object-fit:cover;display:none;">
                            <label class="form-label fw-semibold small text-muted text-uppercase d-block" style="letter-spacing:.04em;">Remplacer photo 1</label>
                            <input type="file" name="img1" class="form-control form-control-sm" accept="image/*">
                        </div>
                        <div class="col-md-4 text-center">
                            <img id="editImg2Preview" src="" class="img-thumbnail mb-2" style="height:100px;width:100%;object-fit:cover;display:none;">
                            <label class="form-label fw-semibold small text-muted text-uppercase d-block" style="letter-spacing:.04em;">Remplacer photo 2</label>
                            <input type="file" name="img2" class="form-control form-control-sm" accept="image/*">
                        </div>
                        <div class="col-md-4 text-center">
                            <img id="editImg3Preview" src="" class="img-thumbnail mb-2" style="height:100px;width:100%;object-fit:cover;display:none;">
                            <label class="form-label fw-semibold small text-muted text-uppercase d-block" style="letter-spacing:.04em;">Remplacer photo 3</label>
                            <input type="file" name="img3" class="form-control form-control-sm" accept="image/*">
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top bg-light px-4">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal"><i class="fa fa-times me-1"></i>Annuler</button>
                <button type="submit" class="btn btn-danger px-5 fw-semibold"><i class="fa fa-check me-2"></i>Enregistrer les modifications</button>
            </div>
        </form>
    </div>
</div>

<div class="main-content">
<div class="top-fixed">

    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <div>
            <p class="text-muted small mb-0 mt-1"><?= $totalMaisons ?> bien<?= $totalMaisons>1?'s':'' ?> immobilier<?= $totalMaisons>1?'s':'' ?></p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button onclick="exportToExcel()" class="btn btn-sm btn-outline-success" style="border-radius:8px;"><i class="fa fa-file-excel me-1"></i>Excel</button>
            <button onclick="exportToPDF()"  class="btn btn-sm btn-outline-danger"  style="border-radius:8px;"><i class="fa fa-file-pdf me-1"></i>PDF</button>
            <button class="btn btn-danger btn-sm shadow-sm" style="border-radius:8px;" data-bs-toggle="modal" data-bs-target="#modalMaison">
                <i class="fa fa-plus-circle me-2"></i>Ajouter une Maison
            </button>
        </div>
    </div>


    <div class="row g-2 mb-3">
        <div class="col-6 col-md-3">
            <div class="kpi-card"><div class="kpi-icon" style="background:#eef2fb;"><i class="fa fa-building" style="color:var(--marine);"></i></div><div><div class="kpi-val" style="color:var(--marine);"><?= $totalMaisons ?></div><div class="kpi-lbl">Biens</div><div class="kpi-sub">au total</div></div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card"><div class="kpi-icon" style="background:#d1fae5;"><i class="fa fa-circle-check" style="color:var(--green);"></i></div><div><div class="kpi-val" style="color:var(--green);"><?= $nbDispos ?></div><div class="kpi-lbl">Disponibles</div><div class="kpi-sub">libres</div></div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card"><div class="kpi-icon" style="background:#fee2e2;"><i class="fa fa-circle-xmark" style="color:var(--red);"></i></div><div><div class="kpi-val" style="color:var(--red);"><?= $nbOccupes ?></div><div class="kpi-lbl">Occupés</div><div class="kpi-sub">en location</div></div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card">
                <div class="kpi-icon" style="background:#fef3c7;"><i class="fa fa-coins" style="color:var(--amber);"></i></div>
                <div style="flex:1;">
                    <div class="kpi-val" style="color:var(--amber);"><?= number_format($revenuMensuel,0,',',' ') ?></div>
                    <div class="kpi-lbl">Revenu mensuel</div>
                    <div class="kpi-sub">FCFA — biens occupés</div>
                    <div style="height:3px;background:#fde68a;border-radius:3px;margin-top:6px;">
                        <div style="height:100%;width:<?= $tauxOccupation ?>%;background:var(--amber);border-radius:3px;"></div>
                    </div>
                    <div style="font-size:9px;color:#8896b0;margin-top:2px;"><?= $tauxOccupation ?>% d'occupation</div>
                </div>
            </div>
        </div>
    </div>

    <div class="filter-bar mb-0">
        <form method="GET" class="d-flex align-items-center gap-2 flex-wrap w-100">
            <i class="fa fa-search text-muted" style="font-size:13px;"></i>
            <input type="text" id="searchInput" name="search" value="<?= htmlspecialchars($search) ?>"
                   class="form-control form-control-sm" style="max-width:220px;border-radius:8px;"
                   placeholder="Désignation, adresse…" autocomplete="off">
            <select name="type" class="form-select form-select-sm" style="max-width:140px;border-radius:8px;" onchange="this.form.submit()">
                <option value="">— Tous types —</option>
                <?php foreach(['Studio','2 Pièces','3 Pièces','4 Pièces','Appartement','Villa','Magasin'] as $t): ?>
                <option value="<?= $t ?>" <?= $filtreType===$t?'selected':'' ?>><?= $t ?></option>
                <?php endforeach; ?>
            </select>
            <select name="statut" class="form-select form-select-sm" style="max-width:140px;border-radius:8px;" onchange="this.form.submit()">
                <option value="">— Tous statuts —</option>
                <option value="disponible" <?= $filtreStatut==='disponible'?'selected':'' ?>>Disponible</option>
                <option value="occupe"     <?= $filtreStatut==='occupe'?'selected':'' ?>>Occupé</option>
            </select>
            <span class="text-muted small">Enregistré le :</span>
            <input type="date" name="date_enr" value="<?= htmlspecialchars($filtreDateEnr) ?>" class="form-control form-control-sm" style="max-width:150px;border-radius:8px;" onchange="this.form.mois_enr.value='';this.form.annee_enr.value='';this.form.submit()">
            <input type="month" name="mois_enr" value="<?= htmlspecialchars($filtreMoisEnr) ?>" class="form-control form-control-sm" style="max-width:140px;border-radius:8px;" onchange="this.form.date_enr.value='';this.form.annee_enr.value='';this.form.submit()">
            <input type="number" name="annee_enr" value="<?= htmlspecialchars($filtreAnneeEnr) ?>" placeholder="Année" min="2000" max="2100" class="form-control form-control-sm" style="max-width:100px;border-radius:8px;" onchange="this.form.date_enr.value='';this.form.mois_enr.value='';this.form.submit()">
            <?php if ($search || $filtreType || $filtreStatut || $filtreDateEnr || $filtreMoisEnr || $filtreAnneeEnr): ?>
            <a href="maisons.php" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;"><i class="fa fa-times"></i></a>
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

<?php if (empty($maisons)): ?>
<div class="text-center text-muted py-5">
    <i class="fa fa-inbox fa-3x mb-3 d-block" style="opacity:.2;"></i>
    <?= $search ? 'Aucun résultat pour «&nbsp;<strong>'.htmlspecialchars($search).'</strong>&nbsp;»' : 'Aucun bien immobilier enregistré.' ?>
</div>
<?php else: ?>

<!-- VUE LISTE -->
<div id="vueListe">
<table class="table mb-0 tbl-full">
    <thead><tr>
        <th style="width:6%;">Photo</th>
        <th style="width:17%;">Désignation</th>
        <th style="width:11%;">Propriétaire</th>
        <th style="width:12%;">Adresse</th>
        <th style="width:8%;">Statut</th>
        <th style="width:12%;">Locataire actuel</th>
        <th class="text-end" style="width:10%;">Loyer</th>
        <th style="width:10%;">Enregistré le</th>
        <th class="text-center" style="width:12%;">Actions</th>
    </tr></thead>
    <tbody>
    <?php foreach ($maisons as $m):
        $img = !empty($m['image1']) ? '../uploads/maisons/'.trim($m['image1']) : null;
        $isDispo = $m['statut'] === 'disponible';
    ?>
    <tr>
        <td>
            <?php if ($img && file_exists($img)): ?>
            <img src="<?= htmlspecialchars($img) ?>" width="56" height="42" class="rounded" style="object-fit:cover;" onerror="this.src='../assets/img/default_maison.jpg'">
            <?php else: ?>
            <div style="width:56px;height:42px;background:#f1f5f9;border-radius:6px;display:flex;align-items:center;justify-content:center;"><i class="fa fa-home text-muted"></i></div>
            <?php endif; ?>
        </td>
        <td>
            <div class="fw-semibold" style="color:#2d3a55;"><?= htmlspecialchars($m['designation']) ?></div>
            <span class="badge bg-light text-dark border" style="font-size:10px;"><?= htmlspecialchars($m['type_maison']) ?></span>
        </td>
        <td><small><i class="fa fa-user-tie me-1 text-muted"></i><?= htmlspecialchars($m['nom_bailleur']) ?></small></td>
        <td class="text-muted small"><?= htmlspecialchars($m['adresse']) ?></td>
        <td><span class="<?= $isDispo?'stat-dispo':'stat-occupe' ?>"><?= $isDispo?'Disponible':'Occupé' ?></span></td>
        <td>
            <?php if (!empty($m['locataire_actuel'])): ?>
            <div class="d-flex align-items-center gap-2">
                <div style="width:26px;height:26px;border-radius:50%;background:#eef2fb;color:var(--marine);display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;flex-shrink:0;"><?= mb_strtoupper(mb_substr($m['locataire_actuel'],0,1)) ?></div>
                <span class="small fw-semibold" style="color:#2d3a55;"><?= htmlspecialchars($m['locataire_actuel']) ?></span>
            </div>
            <?php else: ?>
            <span class="text-muted small">—</span>
            <?php endif; ?>
        </td>
        <td class="text-end fw-bold" style="color:var(--marine);"><?= number_format($m['loyer'],0,',',' ') ?> <small class="text-muted fw-normal">FCFA</small></td>
        <td class="text-muted small"><?= !empty($m['created_at']) ? date('d/m/Y', strtotime($m['created_at'])) : '—' ?></td>
        <td class="text-center" style="white-space:nowrap;">
            <button type="button" class="btn btn-sm btn-outline-primary btn-edit-maison" style="border-radius:6px;" title="Modifier"
                    data-bs-toggle="modal" data-bs-target="#modalEditMaison"
                    data-id="<?= (int)$m['id'] ?>"
                    data-designation="<?= htmlspecialchars($m['designation']) ?>"
                    data-type="<?= htmlspecialchars($m['type_maison']) ?>"
                    data-bailleur="<?= (int)$m['bailleur_id'] ?>"
                    data-adresse="<?= htmlspecialchars($m['adresse']) ?>"
                    data-description="<?= htmlspecialchars($m['description'] ?? '') ?>"
                    data-loyer="<?= htmlspecialchars($m['loyer']) ?>"
                    data-condition="<?= htmlspecialchars($m['condition'] ?? '') ?>"
                    data-statut="<?= htmlspecialchars($m['statut']) ?>"
                    data-img1="<?= !empty($m['image1']) ? htmlspecialchars('../uploads/maisons/'.trim($m['image1'])) : '' ?>"
                    data-img2="<?= !empty($m['image2']) ? htmlspecialchars('../uploads/maisons/'.trim($m['image2'])) : '' ?>"
                    data-img3="<?= !empty($m['image3']) ? htmlspecialchars('../uploads/maisons/'.trim($m['image3'])) : '' ?>"
            ><i class="fa fa-edit"></i></button>
            <a href="documents.php?type=maison&id=<?= (int)$m['id'] ?>" class="btn btn-sm btn-outline-dark ms-1" style="border-radius:6px;" title="Documents"><i class="fa fa-paperclip"></i></a>
            <?php if (isset($_SESSION['role']) && $_SESSION['role']==='admin' && $m['statut']!=='occupe'): ?>
            <form action="../php/delete_maison.php" method="POST" style="display:inline" onsubmit="return confirm('Supprimer définitivement ?')">
                <input type="hidden" name="token" value="<?= csrf_generate() ?>">
                <input type="hidden" name="id" value="<?= $m['id'] ?>">
                <button type="submit" class="btn btn-sm btn-outline-danger ms-1" style="border-radius:6px;"><i class="fa fa-trash"></i></button>
            </form>
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
<?php foreach ($maisons as $m):
    $img = !empty($m['image1']) && file_exists('../uploads/maisons/'.trim($m['image1'])) ? '../uploads/maisons/'.trim($m['image1']) : null;
    $isDispo = $m['statut'] === 'disponible';
?>
<div class="col-sm-6 col-lg-4 col-xl-3">
    <div class="b-card h-100">
        <?php if ($img): ?>
        <img src="<?= htmlspecialchars($img) ?>" class="b-card-img" onerror="this.src='../assets/img/default_maison.jpg'">
        <?php else: ?>
        <div class="b-card-img-ph"><i class="fa fa-home"></i></div>
        <?php endif; ?>
        <div class="b-card-body">
            <div class="d-flex align-items-start justify-content-between gap-2">
                <div class="b-name"><?= htmlspecialchars($m['designation']) ?></div>
                <span class="<?= $isDispo?'stat-dispo':'stat-occupe' ?>" style="white-space:nowrap;flex-shrink:0;"><?= $isDispo?'Libre':'Occupé' ?></span>
            </div>
            <div class="b-code"><?= htmlspecialchars($m['type_maison']) ?> — <?= number_format($m['loyer'],0,',',' ') ?> FCFA/mois</div>
            <div class="b-card-info">
                <div class="b-info-row"><i class="fa fa-user-tie"></i><span><?= htmlspecialchars($m['nom_bailleur']) ?></span></div>
                <div class="b-info-row"><i class="fa fa-map-marker-alt"></i><span><?= htmlspecialchars($m['adresse']) ?></span></div>
                <?php if (!empty($m['locataire_actuel'])): ?>
                <div class="b-info-row"><i class="fa fa-user" style="color:#059669;"></i><span style="color:#059669;font-weight:600;"><?= htmlspecialchars($m['locataire_actuel']) ?></span></div>
                <?php endif; ?>
                <?php if (!empty($m['condition']) && $m['condition'] > 0): ?>
                <div class="b-info-row"><i class="fa fa-shield-halved"></i><span>Caution : <?= htmlspecialchars($m['condition']) ?> mois</span></div>
                <?php endif; ?>
            </div>
        </div>
        <div class="b-card-actions">
            <button type="button" class="btn-edit-maison" style="color:#1d4ed8;border-color:#bfdbfe;background:#eff6ff;" title="Modifier"
                    data-bs-toggle="modal" data-bs-target="#modalEditMaison"
                    data-id="<?= (int)$m['id'] ?>"
                    data-designation="<?= htmlspecialchars($m['designation']) ?>"
                    data-type="<?= htmlspecialchars($m['type_maison']) ?>"
                    data-bailleur="<?= (int)$m['bailleur_id'] ?>"
                    data-adresse="<?= htmlspecialchars($m['adresse']) ?>"
                    data-description="<?= htmlspecialchars($m['description'] ?? '') ?>"
                    data-loyer="<?= htmlspecialchars($m['loyer']) ?>"
                    data-condition="<?= htmlspecialchars($m['condition'] ?? '') ?>"
                    data-statut="<?= htmlspecialchars($m['statut']) ?>"
                    data-img1="<?= !empty($m['image1']) ? htmlspecialchars('../uploads/maisons/'.trim($m['image1'])) : '' ?>"
                    data-img2="<?= !empty($m['image2']) ? htmlspecialchars('../uploads/maisons/'.trim($m['image2'])) : '' ?>"
                    data-img3="<?= !empty($m['image3']) ? htmlspecialchars('../uploads/maisons/'.trim($m['image3'])) : '' ?>"
            ><i class="fa fa-edit"></i></button>
            <a href="documents.php?type=maison&id=<?= (int)$m['id'] ?>" style="color:#374151;border-color:#e5e7eb;background:#f9fafb;" title="Documents"><i class="fa fa-paperclip"></i></a>
            <?php if (isset($_SESSION['role']) && $_SESSION['role']==='admin' && $m['statut']!=='occupe'): ?>
            <form action="../php/delete_maison.php" method="POST" style="display:contents" onsubmit="return confirm('Supprimer définitivement ?')">
                <input type="hidden" name="token" value="<?= csrf_generate() ?>">
                <input type="hidden" name="id" value="<?= $m['id'] ?>">
                <button type="submit" style="flex:1;text-align:center;padding:5px 4px;border-radius:8px;font-size:12px;font-weight:600;text-decoration:none;border:1.5px solid;color:#991b1b;border-color:#fecaca;background:#fff1f2;cursor:pointer;"><i class="fa fa-trash"></i></button>
            </form>
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
        <a href="<?= buildUrlM(['page'=>$page-1]) ?>" class="<?= $page<=1?'off':'' ?>"><i class="fa fa-chevron-left" style="font-size:10px;"></i></a>
        <?php $s=max(1,$page-2);$e=min($totalPages,$page+2);if($s>1)echo'<span style="border:none;width:auto;color:#aab;">…</span>';for($i=$s;$i<=$e;$i++):?>
        <?php if($i===$page):?><span class="cur"><?=$i?></span><?php else:?><a href="<?=buildUrlM(['page'=>$i])?>"><?=$i?></a><?php endif;endfor;if($e<$totalPages)echo'<span style="border:none;width:auto;color:#aab;">…</span>';?>
        <a href="<?= buildUrlM(['page'=>$page+1]) ?>" class="<?= $page>=$totalPages?'off':'' ?>"><i class="fa fa-chevron-right" style="font-size:10px;"></i></a>
    </div>
</div>
<?php endif; ?>

</div><!-- /bottom-scroll -->
</div><!-- /main-content -->

<script src="../js/bootstrap.bundle.min.js"></script>
<script src="../js/xlsx.full.min.js"></script>
<script src="../js/jspdf.umd.min.js"></script>
<script src="../js/jspdf.plugin.autotable.min.js"></script>
<script>
function setView(v) {
    document.getElementById('vueListe').style.display  = v==='liste'  ? 'block':'none';
    document.getElementById('vueCartes').style.display = v==='cartes' ? 'block':'none';
    document.getElementById('btnVueListe').classList.toggle('active', v==='liste');
    document.getElementById('btnVueCarte').classList.toggle('active', v==='cartes');
    localStorage.setItem('maisonView', v);
}
setView(localStorage.getItem('maisonView') || 'cartes');

var searchTimer;
document.getElementById('searchInput').addEventListener('input', function() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(function() { document.querySelector('.filter-bar form').submit(); }, 350);
});

// ── Modale Modifier Maison : pré-remplissage depuis les data-* du bouton cliqué ──
document.getElementById('modalEditMaison').addEventListener('show.bs.modal', function(event) {
    var d = event.relatedTarget.dataset;

    document.getElementById('editMaisonId').value   = d.id;
    document.getElementById('editMaisonTitre').textContent = d.designation;
    document.getElementById('editDesignation').value = d.designation;
    document.getElementById('editTypeMaison').value   = d.type;
    document.getElementById('editBailleurId').value   = d.bailleur;
    document.getElementById('editAdresse').value      = d.adresse;
    document.getElementById('editDescription').value  = d.description;
    document.getElementById('editLoyer').value        = d.loyer;
    document.getElementById('editCondition').value    = d.condition;
    document.getElementById('editStatut').value       = d.statut;

    [1, 2, 3].forEach(function(n) {
        var img = document.getElementById('editImg' + n + 'Preview');
        var src = d['img' + n];
        if (src) { img.src = src; img.style.display = 'block'; }
        else { img.style.display = 'none'; }
    });
});

// ── Exports PDF / Excel (répertoire complet, indépendant de la pagination) ──
var exportRowsMaisons = <?= json_encode($exportRowsMaisons, JSON_UNESCAPED_UNICODE) ?>;
var agenceInfoMaisons = {
    nom: <?= json_encode($entrepriseExport['nom_entreprise'], JSON_UNESCAPED_UNICODE) ?>,
    adresse: <?= json_encode($entrepriseExport['adresse_siege'] ?? '', JSON_UNESCAPED_UNICODE) ?>,
    tel: <?= json_encode($entrepriseExport['contact_telephone'] ?? '', JSON_UNESCAPED_UNICODE) ?>,
    email: <?= json_encode($entrepriseExport['contact_email'] ?? '', JSON_UNESCAPED_UNICODE) ?>,
    logo: <?= (!empty($entrepriseExport['logo_url']) && file_exists('../uploads/' . $entrepriseExport['logo_url']))
        ? json_encode('../uploads/' . $entrepriseExport['logo_url'], JSON_UNESCAPED_UNICODE)
        : 'null' ?>
};

function loadImageAsDataURLMaisons(url) {
    return new Promise(function(resolve) {
        if (!url) { resolve(null); return; }
        var img = new Image();
        img.onload = function() {
            try {
                var canvas = document.createElement('canvas');
                canvas.width = img.naturalWidth;
                canvas.height = img.naturalHeight;
                canvas.getContext('2d').drawImage(img, 0, 0);
                resolve({ dataUrl: canvas.toDataURL('image/png'), ratio: img.naturalWidth / img.naturalHeight });
            } catch (e) { resolve(null); }
        };
        img.onerror = function() { resolve(null); };
        img.src = url;
    });
}

function exportToExcel() {
    var rows = exportRowsMaisons.map(function(r) {
        return {
            'Désignation': r.designation,
            'Type': r.type,
            'Propriétaire': r.bailleur,
            'Adresse': r.adresse,
            'Statut': r.statut,
            'Locataire actuel': r.locataire,
            'Loyer (FCFA)': r.loyer_num,
            'Date d\'enregistrement': r.date_enr
        };
    });
    var ws = XLSX.utils.json_to_sheet(rows);
    ws['!cols'] = [{wch:26},{wch:14},{wch:20},{wch:28},{wch:12},{wch:20},{wch:14},{wch:16}];
    var wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Maisons');
    XLSX.writeFile(wb, 'Liste_Maisons.xlsx');
}

function fmtNumPdf(n) {
    // jsPDF (police standard) ne sait pas afficher l'espace fine insécable
    // que produit Intl.NumberFormat('fr-FR') pour les milliers : le texte
    // apparaît alors éclaté lettre par lettre. On force un espace normal.
    return Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
}

function exportToPDF() {
    loadImageAsDataURLMaisons(agenceInfoMaisons.logo).then(function(logo) {
    var doc = new window.jspdf.jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' });
    var marine = [0, 33, 71];
    var pageW = doc.internal.pageSize.getWidth();

    doc.setFontSize(14);
    doc.setFont(undefined, 'bold');
    doc.setTextColor(marine[0], marine[1], marine[2]);
    doc.text(agenceInfoMaisons.nom || 'BailManager', 14, 16);

    doc.setFontSize(8.5);
    doc.setFont(undefined, 'normal');
    doc.setTextColor(90, 90, 90);
    var coordLine = [agenceInfoMaisons.tel, agenceInfoMaisons.email].filter(Boolean).join('  •  ');
    if (agenceInfoMaisons.adresse) doc.text(agenceInfoMaisons.adresse, 14, 21);
    if (coordLine) doc.text(coordLine, 14, 25);

    if (logo) {
        var logoH = 16, logoW = logoH * logo.ratio;
        doc.addImage(logo.dataUrl, 'PNG', pageW - 14 - logoW, 8, logoW, logoH);
    }

    doc.setDrawColor(marine[0], marine[1], marine[2]);
    doc.setLineWidth(0.6);
    doc.line(14, 28, pageW - 14, 28);

    doc.setFontSize(12.5);
    doc.setFont(undefined, 'bold');
    doc.setTextColor(30, 30, 30);
    doc.text('Répertoire des Maisons', 14, 36);

    var nbDispo = exportRowsMaisons.filter(function(r) { return r.statut === 'Disponible'; }).length;
    doc.setFontSize(9);
    doc.setFont(undefined, 'normal');
    doc.setTextColor(100, 100, 100);
    doc.text(
        exportRowsMaisons.length + ' bien' + (exportRowsMaisons.length > 1 ? 's' : '') +
        ' — ' + nbDispo + ' disponible' + (nbDispo > 1 ? 's' : '') + ', ' + (exportRowsMaisons.length - nbDispo) + ' occupé' + ((exportRowsMaisons.length - nbDispo) > 1 ? 's' : ''),
        14, 42
    );
    doc.text('Généré le ' + new Date().toLocaleDateString('fr-FR'), pageW - 14, 42, { align: 'right' });

    doc.autoTable({
        startY: 47,
        head: [['Désignation', 'Propriétaire', 'Adresse', 'Statut', 'Locataire actuel', 'Loyer', 'Enregistré le']],
        body: exportRowsMaisons.map(function(r) {
            return [r.designation + (r.type && r.type !== '—' ? '\n' + r.type : ''), r.bailleur, r.adresse, r.statut, r.locataire, fmtNumPdf(r.loyer_num) + ' FCFA', r.date_enr];
        }),
        theme: 'striped',
        styles: { fontSize: 9, cellPadding: 3, valign: 'middle' },
        headStyles: { fillColor: marine, textColor: 255, fontStyle: 'bold' },
        alternateRowStyles: { fillColor: [245, 247, 252] },
        columnStyles: {
            0: { cellWidth: 42 },
            5: { cellWidth: 26, halign: 'right' },
            6: { cellWidth: 22 }
        },
        didParseCell: function(data) {
            if (data.section === 'body' && data.column.index === 3) {
                data.cell.styles.textColor = data.cell.raw === 'Disponible' ? [5, 150, 105] : [217, 119, 6];
                data.cell.styles.fontStyle = 'bold';
            }
        },
        didDrawPage: function(data) {
            var pageCount = doc.internal.getNumberOfPages();
            doc.setFontSize(8);
            doc.setTextColor(150, 150, 150);
            doc.text(
                'BailManager — page ' + data.pageNumber + '/' + pageCount,
                pageW / 2,
                doc.internal.pageSize.getHeight() - 8,
                { align: 'center' }
            );
        }
    });

    doc.save('Liste_Maisons.pdf');
    });
}
</script>
</body>
</html>
