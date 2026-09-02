<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once('../config/db.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$search  = trim($_GET['search'] ?? '');
$filtre  = $_GET['filtre'] ?? 'tous'; // tous | actif | libre
$filtreDateEnr  = trim($_GET['date_enr']  ?? '');
$filtreMoisEnr  = trim($_GET['mois_enr']  ?? '');
$filtreAnneeEnr = trim($_GET['annee_enr'] ?? '');
$perPage = 12;
$page    = max(1, (int)($_GET['page'] ?? 1));

// Conditions WHERE
$conditions = [];
$bindSearch = [];

if ($search) {
    $conditions[] = "(l.nom LIKE :s OR l.telephone1 LIKE :s2 OR l.piece_identite LIKE :s3)";
    $bindSearch[':s']  = "%$search%";
    $bindSearch[':s2'] = "%$search%";
    $bindSearch[':s3'] = "%$search%";
}
if ($filtre === 'actif') {
    $conditions[] = "c.id IS NOT NULL";
} elseif ($filtre === 'libre') {
    $conditions[] = "c.id IS NULL";
}
if ($filtreDateEnr)       { $conditions[] = "DATE(l.created_at) = :date_enr";              $bindSearch[':date_enr']  = $filtreDateEnr; }
elseif ($filtreMoisEnr)  { $conditions[] = "DATE_FORMAT(l.created_at,'%Y-%m') = :mois_enr"; $bindSearch[':mois_enr']  = $filtreMoisEnr; }
elseif ($filtreAnneeEnr) { $conditions[] = "YEAR(l.created_at) = :annee_enr";               $bindSearch[':annee_enr'] = $filtreAnneeEnr; }
$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$totalLocataires = (int)$pdo->query("SELECT COUNT(*) FROM locataires")->fetchColumn();
$avecContrat     = (int)$pdo->query("SELECT COUNT(DISTINCT locataire_id) FROM contrats WHERE statut_contrat='actif'")->fetchColumn();
$sansContrat     = $totalLocataires - $avecContrat;
$avecHistorique  = (int)$pdo->query("SELECT COUNT(DISTINCT locataire_id) FROM contrats")->fetchColumn();

// Loyer total collecté (actifs)
$loyerTotal = (int)$pdo->query("SELECT COALESCE(SUM(loyer_mensuel),0) FROM contrats WHERE statut_contrat='actif'")->fetchColumn();

// Requête enrichie avec infos contrat actif
$baseQuery = "
    SELECT l.*,
           c.id AS contrat_id, c.loyer_mensuel, c.date_debut, c.depot_garantie,
           m.designation AS nom_maison, m.adresse AS adresse_maison
    FROM locataires l
    LEFT JOIN contrats c ON c.locataire_id = l.id AND c.statut_contrat = 'actif'
    LEFT JOIN maisons m ON m.id = c.maison_id
    $where
";

$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM ($baseQuery) sub");
$stmtCount->execute($bindSearch);
$totalRows  = (int)$stmtCount->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

$stmtList = $pdo->prepare("$baseQuery ORDER BY l.nom ASC LIMIT :lim OFFSET :off");
foreach ($bindSearch as $k => $v) $stmtList->bindValue($k, $v);
$stmtList->bindValue(':lim', $perPage, PDO::PARAM_INT);
$stmtList->bindValue(':off', $offset,  PDO::PARAM_INT);
$stmtList->execute();
$locataires = $stmtList->fetchAll();

// Jeu de données complet (toutes pages confondues, mêmes filtres) pour les exports PDF/Excel
$stmtAll = $pdo->prepare("$baseQuery ORDER BY l.nom ASC");
$stmtAll->execute($bindSearch);
$exportRows = array_map(function ($l) {
    $isActif = !empty($l['contrat_id']);
    return [
        'nom'       => $l['nom'],
        'piece'     => $l['piece_identite'] ?: '—',
        'statut'    => $isActif ? 'Actif' : 'Libre',
        'maison'    => $isActif && !empty($l['nom_maison']) ? $l['nom_maison'] : '—',
        'adresse'   => $isActif ? ($l['adresse_maison'] ?? '') : '',
        'loyer'     => $isActif && !empty($l['loyer_mensuel']) ? number_format($l['loyer_mensuel'], 0, ',', ' ') . ' FCFA' : '—',
        'loyer_num' => $isActif && !empty($l['loyer_mensuel']) ? (float)$l['loyer_mensuel'] : null,
        'tel1'      => $l['telephone1'] ?? '',
        'tel2'      => $l['telephone2'] ?? '',
        'date_enr'  => !empty($l['created_at']) ? date('d/m/Y', strtotime($l['created_at'])) : '—',
    ];
}, $stmtAll->fetchAll());

// Coordonnées agence (en-tête des exports)
$entreprise = $pdo->query("SELECT * FROM settings LIMIT 1")->fetch();
if (!$entreprise) {
    $entreprise = ['nom_entreprise' => 'BailManager', 'adresse_siege' => '', 'contact_telephone' => '', 'contact_email' => ''];
}
$footerLinesExport = buildFooterLines($entreprise);
$activitesExport = array_filter(array_map('trim', explode("\n", $entreprise['activites'] ?? '')));

function buildUrlL(array $extra = []): string {
    global $search, $page, $filtre, $filtreDateEnr, $filtreMoisEnr, $filtreAnneeEnr;
    $p = array_filter(['search'=>$search,'page'=>$page,'filtre'=>$filtre,'date_enr'=>$filtreDateEnr,'mois_enr'=>$filtreMoisEnr,'annee_enr'=>$filtreAnneeEnr], fn($v)=>$v!==''&&$v!==null&&$v!==0&&$v!=='tous');
    return '?' . http_build_query(array_merge($p, $extra));
}

$avatarColors = [
    ['bg'=>'#dbeafe','fg'=>'#1e40af'],['bg'=>'#fce7f3','fg'=>'#9d174d'],
    ['bg'=>'#d1fae5','fg'=>'#065f46'],['bg'=>'#fef3c7','fg'=>'#92400e'],
    ['bg'=>'#ede9fe','fg'=>'#5b21b6'],['bg'=>'#fee2e2','fg'=>'#991b1b'],
    ['bg'=>'#e0f2fe','fg'=>'#0369a1'],['bg'=>'#f0fdf4','fg'=>'#166534'],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../favicon.svg">
    <title>Répertoire des Locataires — BailManager</title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/fontawesome/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        :root { --marine:#002147; --green:#059669; --amber:#d97706; --red:#dc2626; }
        .main-content  { background:#f4f7fe; height:100vh; display:flex; flex-direction:column; overflow:hidden; }
        .top-fixed     { padding:18px 28px 0; flex-shrink:0; }
        .bottom-scroll { flex:1; overflow-y:auto; overflow-x:hidden; }

        /* KPI */
        .kpi-card { background:#fff; border-radius:14px; padding:14px 16px; display:flex; align-items:center; gap:12px; box-shadow:0 2px 10px rgba(0,0,0,.06); border:1px solid #e8ecf4; height:100%; }
        .kpi-icon { width:42px; height:42px; border-radius:11px; display:flex; align-items:center; justify-content:center; font-size:16px; flex-shrink:0; }
        .kpi-val  { font-size:1.15rem; font-weight:800; line-height:1.1; }
        .kpi-lbl  { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:#8896b0; margin-top:1px; }
        .kpi-sub  { font-size:10px; color:#aab; }

        /* Filtre tabs */
        .filter-tabs { display:flex; gap:4px; }
        .filter-tabs a { padding:5px 13px; border-radius:8px; font-size:12px; font-weight:600; text-decoration:none; border:1.5px solid #e0e6f0; color:#6b7a99; background:#fff; transition:all .15s; }
        .filter-tabs a.active { background:var(--marine); border-color:var(--marine); color:#fff; }
        .filter-tabs a:hover:not(.active) { border-color:var(--marine); color:var(--marine); }

        /* Barre filtre */
        .filter-bar { background:#fff; border-radius:10px; padding:10px 16px; display:flex; align-items:center; gap:10px; box-shadow:0 2px 8px rgba(0,0,0,.05); border:1px solid #e8ecf4; flex-wrap:wrap; }
        .view-toggle { display:flex; border:1.5px solid #e0e6f0; border-radius:8px; overflow:hidden; }
        .view-toggle button { background:#fff; border:none; padding:6px 10px; cursor:pointer; color:#8896b0; font-size:13px; transition:background .15s,color .15s; }
        .view-toggle button.active { background:var(--marine); color:#fff; }

        /* Table */
        .tbl-full { background:#fff; border-top:1px solid #e8ecf4; width:100%; }
        .tbl-full thead th { background:#f8faff; color:#6b7a99; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; padding:12px 20px; border-bottom:1px solid #e8ecf4; position:sticky; top:0; z-index:2; white-space:nowrap; }
        .tbl-full tbody td { padding:10px 20px; border-bottom:1px solid #f0f3fa; vertical-align:middle; font-size:13px; }
        .tbl-full tbody tr:last-child td { border-bottom:none; }
        .tbl-full tbody tr:hover td { background:#f8faff; }

        /* Avatar */
        .avatar-sm { width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:13px; flex-shrink:0; object-fit:cover; }

        /* Statut badge */
        .st-actif  { display:inline-flex; align-items:center; gap:4px; padding:3px 9px; border-radius:20px; font-size:10px; font-weight:700; background:#d1fae5; color:#065f46; }
        .st-libre  { display:inline-flex; align-items:center; gap:4px; padding:3px 9px; border-radius:20px; font-size:10px; font-weight:700; background:#f1f5f9; color:#64748b; }
        .st-dot    { width:6px; height:6px; border-radius:50%; display:inline-block; }

        /* Cards */
        .b-card { background:#fff; border-radius:16px; border:1px solid #e8ecf4; box-shadow:0 2px 10px rgba(0,0,0,.05); overflow:hidden; transition:box-shadow .18s,transform .18s; display:flex; flex-direction:column; }
        .b-card:hover { box-shadow:0 8px 28px rgba(0,33,71,.13); transform:translateY(-2px); }
        .b-card-banner { height:6px; }
        .b-card-top { padding:18px 16px 10px; display:flex; flex-direction:column; align-items:center; text-align:center; }
        .b-photo  { width:62px; height:62px; border-radius:50%; object-fit:cover; margin-bottom:10px; border:2px solid #e8ecf4; }
        .b-avatar { width:62px; height:62px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:22px; font-weight:800; margin-bottom:10px; }
        .b-name   { font-size:13px; font-weight:700; color:#1e293b; line-height:1.3; }
        .b-sub    { font-size:10px; color:#94a3b8; margin-top:2px; }
        .b-card-info { padding:0 14px 10px; display:flex; flex-direction:column; gap:5px; flex:1; }
        .b-info-row { display:flex; align-items:center; gap:7px; font-size:11px; color:#5a6a85; }
        .b-info-row i { width:13px; text-align:center; color:#94a3b8; flex-shrink:0; }
        .b-info-row span { white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .b-card-actions { padding:10px 12px; border-top:1px solid #f0f3fa; display:flex; gap:5px; }
        .b-card-actions a, .b-card-actions button { flex:1; text-align:center; padding:6px 4px; border-radius:8px; font-size:12px; font-weight:600; text-decoration:none; border:1.5px solid; cursor:pointer; transition:opacity .15s; background:none; line-height:1; }
        .b-card-actions a:hover, .b-card-actions button:hover { opacity:.75; }
        .b-maison { display:flex; align-items:center; gap:5px; font-size:11px; font-weight:600; color:var(--marine); background:#eef2ff; border-radius:6px; padding:3px 8px; margin-top:5px; max-width:100%; }
        .b-maison i { flex-shrink:0; }

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

<?php if (isset($_SESSION['code_locataire_genere'])):
    $codeGenereAffiche = $_SESSION['code_locataire_genere'];
    unset($_SESSION['code_locataire_genere'], $_SESSION['code_locataire_loc_id']);
?>
<div class="alert alert-warning alert-dismissible fade show mx-4 mt-3" role="alert">
    <i class="fa fa-triangle-exclamation me-2"></i>
    Code d'accès généré : <strong style="letter-spacing:1px;"><?= htmlspecialchars($codeGenereAffiche) ?></strong>
    — communiquez-le au locataire maintenant, il ne sera plus jamais réaffiché.
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<!-- ══ MODAL AJOUT ══ -->
<div class="modal fade" id="modalLocataire" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <form class="modal-content border-0 shadow-lg" action="../php/add_locataire.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="token" value="<?= csrf_generate() ?>">
            <div class="modal-header text-white border-0" style="background:linear-gradient(135deg,#002147,#004080);">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-white bg-opacity-10 d-flex align-items-center justify-content-center" style="width:40px;height:40px;"><i class="fa fa-user-plus text-white"></i></div>
                    <div><h5 class="modal-title fw-bold mb-0">Nouveau Locataire</h5><small class="opacity-75">Enregistrez les informations du locataire</small></div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="px-4 pt-4 pb-3">
                    <span class="badge rounded-pill text-bg-primary px-3 py-2 mb-3 d-inline-block"><i class="fa fa-id-card me-1"></i>Identité</span>
                    <div class="row g-3">
                        <div class="col-md-2 d-flex flex-column align-items-center justify-content-center">
                            <div id="previewLocataire" onclick="document.getElementById('photoLocInput').click()"
                                 class="rounded-circle border border-2 d-flex align-items-center justify-content-center bg-light overflow-hidden"
                                 style="width:72px;height:72px;cursor:pointer;border-style:dashed!important;">
                                <i class="fa fa-camera text-muted fs-5"></i>
                            </div>
                            <small class="text-muted mt-1" style="font-size:10px;">Photo</small>
                            <input type="file" id="photoLocInput" name="photo_locataire" accept="image/*" class="d-none">
                        </div>
                        <div class="col-md-10">
                            <label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">Nom complet <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="fa fa-user text-muted"></i></span>
                                <input type="text" name="nom" class="form-control border-start-0 ps-0" required placeholder="Ex: KOFFI OZIAS">
                            </div>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">N° Pièce d'identité <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="fa fa-id-badge text-muted"></i></span>
                                <input type="text" name="piece_identite" class="form-control border-start-0 ps-0" required placeholder="CI0012345">
                            </div>
                        </div>
                    </div>
                </div>
                <hr class="mx-4 my-0 opacity-10">
                <div class="px-4 pt-3 pb-3">
                    <span class="badge rounded-pill text-bg-success px-3 py-2 mb-3 d-inline-block"><i class="fa fa-phone me-1"></i>Contacts</span>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">Téléphone 1 <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="fa fa-phone text-muted"></i></span>
                                <input type="text" name="telephone1" class="form-control border-start-0 ps-0" required placeholder="07XXXXXXXX">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">Téléphone 2</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="fa fa-mobile-alt text-muted"></i></span>
                                <input type="text" name="telephone2" class="form-control border-start-0 ps-0" placeholder="01XXXXXXXX">
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">Email (optionnel)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="fa fa-envelope text-muted"></i></span>
                                <input type="email" name="email" class="form-control border-start-0 ps-0" placeholder="locataire@exemple.com">
                            </div>
                            <small class="text-muted" style="font-size:10px;">Utilisé pour les rappels automatiques de loyer.</small>
                        </div>
                    </div>
                </div>
                <hr class="mx-4 my-0 opacity-10">
                <div class="px-4 pt-3 pb-4">
                    <span class="badge rounded-pill text-bg-secondary px-3 py-2 mb-3 d-inline-block"><i class="fa fa-file-image me-1"></i>Documents CNI</span>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">CNI Recto</label>
                            <input type="file" name="photo_cni_recto" class="form-control form-control-sm" accept="image/*">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">CNI Verso</label>
                            <input type="file" name="photo_cni_verso" class="form-control form-control-sm" accept="image/*">
                        </div>
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

<!-- ══ MODAL MODIFICATION ══ -->
<div class="modal fade" id="modalEditLocataire" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <form class="modal-content border-0 shadow-lg" action="../php/update_locataire.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="token" value="<?= csrf_generate() ?>">
            <input type="hidden" name="id" id="edit_id">
            <div class="modal-header text-white border-0" style="background:linear-gradient(135deg,#1e3a5f,#1d4ed8);">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-white bg-opacity-10 d-flex align-items-center justify-content-center" style="width:40px;height:40px;"><i class="fa fa-user-edit text-white"></i></div>
                    <div><h5 class="modal-title fw-bold mb-0">Modifier le Locataire</h5><small class="opacity-75">Mettez à jour les informations</small></div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 py-4">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">Nom complet <span class="text-danger">*</span></label>
                        <div class="input-group"><span class="input-group-text bg-light border-end-0"><i class="fa fa-user text-muted"></i></span><input type="text" name="nom" id="edit_nom" class="form-control border-start-0 ps-0" required></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">Téléphone 1 <span class="text-danger">*</span></label>
                        <div class="input-group"><span class="input-group-text bg-light border-end-0"><i class="fa fa-phone text-muted"></i></span><input type="text" name="telephone1" id="edit_tel1" class="form-control border-start-0 ps-0" required></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">Téléphone 2</label>
                        <div class="input-group"><span class="input-group-text bg-light border-end-0"><i class="fa fa-mobile-alt text-muted"></i></span><input type="text" name="telephone2" id="edit_tel2" class="form-control border-start-0 ps-0"></div>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">Email (optionnel)</label>
                        <div class="input-group"><span class="input-group-text bg-light border-end-0"><i class="fa fa-envelope text-muted"></i></span><input type="email" name="email" id="edit_email" class="form-control border-start-0 ps-0" placeholder="locataire@exemple.com"></div>
                        <small class="text-muted" style="font-size:10px;">Utilisé pour les rappels automatiques de loyer.</small>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">N° Pièce d'identité <span class="text-danger">*</span></label>
                        <div class="input-group"><span class="input-group-text bg-light border-end-0"><i class="fa fa-id-badge text-muted"></i></span><input type="text" name="piece_identite" id="edit_piece" class="form-control border-start-0 ps-0" required></div>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">Changer la photo (optionnel)</label>
                        <input type="file" name="photo_locataire" class="form-control form-control-sm" accept="image/*">
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top bg-light px-4">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal"><i class="fa fa-times me-1"></i>Annuler</button>
                <button type="submit" class="btn btn-primary px-5 fw-semibold"><i class="fa fa-save me-2"></i>Mettre à jour</button>
            </div>
        </form>
    </div>
</div>

<!-- ══ MODAL ENVOYER UN EMAIL ══ -->
<div class="modal fade" id="modalEmailLocataire" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form class="modal-content border-0 shadow-lg" action="../php/envoyer_email_locataire.php" method="POST">
            <input type="hidden" name="token" value="<?= csrf_generate() ?>">
            <input type="hidden" name="locataire_id" id="emailLocataireId">
            <div class="modal-header text-white border-0" style="background:linear-gradient(135deg,#002147,#004080);">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-white bg-opacity-10 d-flex align-items-center justify-content-center" style="width:40px;height:40px;"><i class="fa fa-envelope text-white"></i></div>
                    <div><h5 class="modal-title fw-bold mb-0">Envoyer un email</h5><small class="opacity-75" id="emailLocataireNom">—</small></div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div id="emailLocataireAlertNoEmail" class="alert alert-warning m-4 mb-0 small d-none">
                    <i class="fa fa-triangle-exclamation me-1"></i>Ce locataire n'a pas d'adresse email enregistrée. Ajoutez-en une via « Modifier » pour pouvoir lui écrire.
                </div>
                <div class="px-4 pt-4 pb-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">Destinataire</label>
                            <input type="text" id="emailLocataireDest" class="form-control bg-light" readonly tabindex="-1">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">Sujet <span class="text-danger">*</span></label>
                            <input type="text" name="sujet" id="emailLocataireSujet" class="form-control" maxlength="200" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing:.04em;">Message <span class="text-danger">*</span></label>
                            <textarea name="contenu" id="emailLocataireContenu" class="form-control" rows="6" maxlength="5000" required></textarea>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top bg-light px-4">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal"><i class="fa fa-times me-1"></i>Annuler</button>
                <button type="submit" id="emailLocataireBtnEnvoyer" class="btn btn-danger px-5 fw-semibold"><i class="fa fa-paper-plane me-2"></i>Envoyer</button>
            </div>
        </form>
    </div>
</div>

<div class="main-content">
<div class="top-fixed">

    <!-- En-tête -->
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <div>
            <p class="text-muted small mb-0 mt-1"><?= $totalLocataires ?> locataire<?= $totalLocataires>1?'s':'' ?> enregistré<?= $totalLocataires>1?'s':'' ?></p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button onclick="exportToExcel()" class="btn btn-sm btn-outline-success" style="border-radius:8px;"><i class="fa fa-file-excel me-1"></i>Excel</button>
            <button onclick="exportToPDF()"  class="btn btn-sm btn-outline-danger"  style="border-radius:8px;"><i class="fa fa-file-pdf me-1"></i>PDF</button>
            <button class="btn btn-sm shadow-sm fw-semibold" style="border-radius:8px;background:var(--marine);color:#fff;border:none;" data-bs-toggle="modal" data-bs-target="#modalLocataire">
                <i class="fa fa-user-plus me-2"></i>Nouveau
            </button>
        </div>
    </div>

    <!-- KPI -->
    <div class="row g-2 mb-3">
        <div class="col-6 col-md-3">
            <div class="kpi-card">
                <div class="kpi-icon" style="background:#eef2fb;"><i class="fa fa-users" style="color:var(--marine);"></i></div>
                <div><div class="kpi-val" style="color:var(--marine);"><?= $totalLocataires ?></div><div class="kpi-lbl">Total</div><div class="kpi-sub">locataires</div></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card">
                <div class="kpi-icon" style="background:#d1fae5;"><i class="fa fa-house-user" style="color:var(--green);"></i></div>
                <div><div class="kpi-val" style="color:var(--green);"><?= $avecContrat ?></div><div class="kpi-lbl">Locataires actifs</div><div class="kpi-sub">contrat en cours</div></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card">
                <div class="kpi-icon" style="background:#fee2e2;"><i class="fa fa-user-slash" style="color:var(--red);"></i></div>
                <div><div class="kpi-val" style="color:var(--red);"><?= $sansContrat ?></div><div class="kpi-lbl">Sans logement</div><div class="kpi-sub">pas de contrat actif</div></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card">
                <div class="kpi-icon" style="background:#fef3c7;"><i class="fa fa-coins" style="color:var(--amber);"></i></div>
                <div><div class="kpi-val" style="color:var(--amber);"><?= number_format($loyerTotal,0,',',' ') ?></div><div class="kpi-lbl">Loyers/mois</div><div class="kpi-sub">FCFA actifs</div></div>
            </div>
        </div>
    </div>

    <!-- Filtres + recherche -->
    <div class="filter-bar mb-0">
        <div class="filter-tabs">
            <a href="<?= buildUrlL(['filtre'=>'tous','page'=>1]) ?>" class="<?= $filtre==='tous'?'active':'' ?>">Tous <span class="ms-1 text-muted" style="font-weight:400;">(<?= $totalLocataires ?>)</span></a>
            <a href="<?= buildUrlL(['filtre'=>'actif','page'=>1]) ?>" class="<?= $filtre==='actif'?'active':'' ?>">Actifs <span class="ms-1 text-muted" style="font-weight:400;">(<?= $avecContrat ?>)</span></a>
            <a href="<?= buildUrlL(['filtre'=>'libre','page'=>1]) ?>" class="<?= $filtre==='libre'?'active':'' ?>">Libres <span class="ms-1 text-muted" style="font-weight:400;">(<?= $sansContrat ?>)</span></a>
        </div>
        <form method="GET" id="searchForm" class="d-flex align-items-center gap-2 flex-wrap ms-auto">
            <input type="hidden" name="filtre" value="<?= htmlspecialchars($filtre) ?>">
            <div class="input-group input-group-sm" style="width:100%;max-width:240px;">
                <span class="input-group-text bg-light border-end-0"><i class="fa fa-search text-muted" style="font-size:11px;"></i></span>
                <input type="text" id="searchInput" name="search" value="<?= htmlspecialchars($search) ?>"
                       class="form-control border-start-0 ps-0" style="border-radius:0 8px 8px 0;"
                       placeholder="Nom, téléphone, pièce…" autocomplete="off">
                <?php if ($search): ?>
                <a href="<?= buildUrlL(['search'=>'','page'=>1]) ?>" class="btn btn-sm btn-outline-secondary ms-1" style="border-radius:8px;"><i class="fa fa-times"></i></a>
                <?php endif; ?>
            </div>
            <span class="text-muted small">Enregistré le :</span>
            <input type="date" name="date_enr" value="<?= htmlspecialchars($filtreDateEnr) ?>" class="form-control form-control-sm" style="max-width:150px;border-radius:8px;" onchange="this.form.mois_enr.value='';this.form.annee_enr.value='';this.form.submit()">
            <input type="month" name="mois_enr" value="<?= htmlspecialchars($filtreMoisEnr) ?>" class="form-control form-control-sm" style="max-width:140px;border-radius:8px;" onchange="this.form.date_enr.value='';this.form.annee_enr.value='';this.form.submit()">
            <input type="number" name="annee_enr" value="<?= htmlspecialchars($filtreAnneeEnr) ?>" placeholder="Année" min="2000" max="2100" class="form-control form-control-sm" style="max-width:100px;border-radius:8px;" onchange="this.form.date_enr.value='';this.form.mois_enr.value='';this.form.submit()">
            <?php if ($filtreDateEnr || $filtreMoisEnr || $filtreAnneeEnr): ?>
            <a href="<?= buildUrlL(['date_enr'=>'','mois_enr'=>'','annee_enr'=>'','page'=>1]) ?>" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;"><i class="fa fa-times"></i></a>
            <?php endif; ?>
            <div class="text-muted small"><?= $totalRows ?> résultat<?= $totalRows>1?'s':'' ?></div>
            <div class="view-toggle">
                <button type="button" id="btnVueListe" onclick="setView('liste')" title="Vue liste"><i class="fa fa-list-ul"></i></button>
                <button type="button" id="btnVueCarte" onclick="setView('cartes')" title="Vue cartes"><i class="fa fa-grip"></i></button>
            </div>
        </form>
    </div>

</div><!-- /top-fixed -->
<div class="bottom-scroll">

<?php if (empty($locataires)): ?>
<div class="text-center text-muted py-5">
    <i class="fa fa-inbox fa-3x mb-3 d-block" style="opacity:.2;"></i>
    <?= $search ? 'Aucun résultat pour «&nbsp;<strong>' . htmlspecialchars($search) . '</strong>&nbsp;»' : 'Aucun locataire trouvé.' ?>
</div>
<?php else: ?>

<!-- ══ VUE LISTE ══ -->
<div id="vueListe">
<table class="table mb-0 tbl-full" id="locatairesTable">
    <thead><tr>
        <th style="width:24%;">Locataire</th>
        <th style="width:12%;">Statut</th>
        <th style="width:18%;">Maison actuelle</th>
        <th style="width:12%;">Loyer / mois</th>
        <th style="width:12%;">Contact</th>
        <th style="width:12%;">Enregistré le</th>
        <th class="text-center" style="width:10%;">Actions</th>
    </tr></thead>
    <tbody>
    <?php foreach ($locataires as $i => $l):
        $initiale  = mb_strtoupper(mb_substr($l['nom'], 0, 1));
        $col       = $avatarColors[$i % count($avatarColors)];
        $photo     = !empty($l['photo_locataire']) ? '../uploads/locataires/' . $l['photo_locataire'] : null;
        $isActif   = !empty($l['contrat_id']);
        $waNum     = preg_replace('/[^0-9]/', '', $l['telephone1'] ?? '');
        if (strlen($waNum) === 10 && $waNum[0] === '0') $waNum = '225' . substr($waNum, 1);
    ?>
    <tr>
        <td>
            <div class="d-flex align-items-center gap-3">
                <?php if ($photo): ?>
                <img src="<?= htmlspecialchars($photo) ?>" class="avatar-sm" onerror="this.style.display='none'">
                <?php else: ?>
                <div class="avatar-sm" style="background:<?= $col['bg'] ?>;color:<?= $col['fg'] ?>;"><?= htmlspecialchars($initiale) ?></div>
                <?php endif; ?>
                <div>
                    <div class="fw-semibold" style="color:#2d3a55;font-size:13px;"><?= htmlspecialchars($l['nom']) ?></div>
                    <div class="text-muted" style="font-size:10px;"><?= htmlspecialchars($l['piece_identite'] ?? '—') ?></div>
                </div>
            </div>
        </td>
        <td>
            <?php if ($isActif): ?>
            <span class="st-actif"><span class="st-dot" style="background:#10b981;"></span>Actif</span>
            <?php else: ?>
            <span class="st-libre"><span class="st-dot" style="background:#94a3b8;"></span>Libre</span>
            <?php endif; ?>
        </td>
        <td>
            <?php if ($isActif && !empty($l['nom_maison'])): ?>
            <div class="fw-semibold" style="font-size:12px;color:var(--marine);"><?= htmlspecialchars($l['nom_maison']) ?></div>
            <?php if (!empty($l['adresse_maison'])): ?>
            <div class="text-muted" style="font-size:10px;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($l['adresse_maison']) ?></div>
            <?php endif; ?>
            <?php else: ?>
            <span class="text-muted" style="font-size:12px;">—</span>
            <?php endif; ?>
        </td>
        <td>
            <?php if ($isActif && !empty($l['loyer_mensuel'])): ?>
            <span class="fw-bold" style="color:var(--green);font-size:12px;"><?= number_format($l['loyer_mensuel'],0,',',' ') ?></span>
            <span class="text-muted" style="font-size:10px;"> FCFA</span>
            <?php else: ?>
            <span class="text-muted" style="font-size:12px;">—</span>
            <?php endif; ?>
        </td>
        <td>
            <div class="small"><i class="fa fa-phone me-1 text-muted"></i><?= htmlspecialchars($l['telephone1'] ?? '') ?></div>
            <?php if (!empty($l['telephone2'])): ?><div class="small text-muted"><i class="fa fa-mobile-alt me-1"></i><?= htmlspecialchars($l['telephone2']) ?></div><?php endif; ?>
        </td>
        <td class="text-muted small"><?= !empty($l['created_at']) ? date('d/m/Y', strtotime($l['created_at'])) : '—' ?></td>
        <td class="text-center" style="white-space:nowrap;">
            <div class="d-flex justify-content-center gap-1">
            <button class="btn btn-sm btn-outline-primary btn-edit" style="border-radius:6px;"
                    data-bs-toggle="modal" data-bs-target="#modalEditLocataire"
                    data-id="<?= $l['id'] ?>" data-nom="<?= htmlspecialchars($l['nom']) ?>"
                    data-tel1="<?= htmlspecialchars($l['telephone1']) ?>" data-tel2="<?= htmlspecialchars($l['telephone2'] ?? '') ?>" data-email="<?= htmlspecialchars($l['email'] ?? '') ?>"
                    data-piece="<?= htmlspecialchars($l['piece_identite']) ?>" title="Modifier"><i class="fa fa-edit"></i></button>
            <?php if ($waNum): ?>
            <a href="https://wa.me/<?= $waNum ?>" target="_blank" class="btn btn-sm btn-outline-success" style="border-radius:6px;" title="WhatsApp"><i class="fa-brands fa-whatsapp"></i></a>
            <?php endif; ?>
            <a href="documents.php?type=locataire&id=<?= $l['id'] ?>" class="btn btn-sm btn-outline-dark" style="border-radius:6px;" title="Documents"><i class="fa fa-paperclip"></i></a>
            <button type="button" class="btn btn-sm btn-outline-info btn-email-locataire" style="border-radius:6px;" title="Envoyer un email"
                    data-bs-toggle="modal" data-bs-target="#modalEmailLocataire"
                    data-id="<?= $l['id'] ?>" data-nom="<?= htmlspecialchars($l['nom']) ?>" data-email="<?= htmlspecialchars($l['email'] ?? '') ?>"
            ><i class="fa fa-envelope"></i></button>
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <form action="../php/delete_locataire.php" method="POST" style="display:inline" onsubmit="return confirm('Supprimer définitivement ?')">
                <input type="hidden" name="token" value="<?= csrf_generate() ?>">
                <input type="hidden" name="id" value="<?= $l['id'] ?>">
                <button type="submit" class="btn btn-sm btn-outline-danger" style="border-radius:6px;" title="Supprimer"><i class="fa fa-trash"></i></button>
            </form>
            <?php endif; ?>
            </div>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<!-- ══ VUE CARTES ══ -->
<div id="vueCartes" style="padding:20px 20px 0;">
<div class="row g-3">
<?php foreach ($locataires as $i => $l):
    $initiale = mb_strtoupper(mb_substr($l['nom'], 0, 1));
    $col      = $avatarColors[$i % count($avatarColors)];
    $photo    = !empty($l['photo_locataire']) ? '../uploads/locataires/' . $l['photo_locataire'] : null;
    $isActif  = !empty($l['contrat_id']);
    $waNum    = preg_replace('/[^0-9]/', '', $l['telephone1'] ?? '');
    if (strlen($waNum) === 10 && $waNum[0] === '0') $waNum = '225' . substr($waNum, 1);
    $bannerColor = $isActif ? '#10b981' : '#94a3b8';
?>
<div class="col-sm-6 col-lg-4 col-xl-3">
    <div class="b-card h-100">
        <div class="b-card-banner" style="background:<?= $bannerColor ?>;"></div>
        <div class="b-card-top">
            <?php if ($photo): ?>
            <img src="<?= htmlspecialchars($photo) ?>" class="b-photo" onerror="this.style.display='none'">
            <?php else: ?>
            <div class="b-avatar" style="background:<?= $col['bg'] ?>;color:<?= $col['fg'] ?>;"><?= htmlspecialchars($initiale) ?></div>
            <?php endif; ?>
            <div class="b-name"><?= htmlspecialchars($l['nom']) ?></div>
            <div class="b-sub"><?= htmlspecialchars($l['piece_identite'] ?? '') ?></div>
            <?php if ($isActif): ?>
            <span class="st-actif mt-2"><span class="st-dot" style="background:#10b981;"></span>Locataire actif</span>
            <?php else: ?>
            <span class="st-libre mt-2"><span class="st-dot" style="background:#94a3b8;"></span>Sans logement</span>
            <?php endif; ?>
        </div>
        <div class="b-card-info">
            <?php if (!empty($l['telephone1'])): ?>
            <div class="b-info-row"><i class="fa fa-phone"></i><span><?= htmlspecialchars($l['telephone1']) ?></span></div>
            <?php endif; ?>
            <?php if (!empty($l['telephone2'])): ?>
            <div class="b-info-row"><i class="fa fa-mobile-alt"></i><span><?= htmlspecialchars($l['telephone2']) ?></span></div>
            <?php endif; ?>
            <?php if ($isActif && !empty($l['nom_maison'])): ?>
            <div class="b-maison"><i class="fa fa-house fa-xs"></i><span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($l['nom_maison']) ?></span></div>
            <?php endif; ?>
            <?php if ($isActif && !empty($l['loyer_mensuel'])): ?>
            <div class="b-info-row mt-1"><i class="fa fa-coins" style="color:#059669;"></i><span class="fw-bold" style="color:#059669;"><?= number_format($l['loyer_mensuel'],0,',',' ') ?> FCFA/mois</span></div>
            <?php endif; ?>
            <?php if ($isActif && !empty($l['date_debut'])): ?>
            <div class="b-info-row"><i class="fa fa-calendar-check"></i><span>Depuis le <?= date('d/m/Y', strtotime($l['date_debut'])) ?></span></div>
            <?php endif; ?>
        </div>
        <div class="b-card-actions">
            <button class="btn-edit" style="color:#1d4ed8;border-color:#bfdbfe;background:#eff6ff;" title="Modifier"
                    data-bs-toggle="modal" data-bs-target="#modalEditLocataire"
                    data-id="<?= $l['id'] ?>" data-nom="<?= htmlspecialchars($l['nom']) ?>"
                    data-tel1="<?= htmlspecialchars($l['telephone1']) ?>" data-tel2="<?= htmlspecialchars($l['telephone2'] ?? '') ?>" data-email="<?= htmlspecialchars($l['email'] ?? '') ?>"
                    data-piece="<?= htmlspecialchars($l['piece_identite']) ?>"><i class="fa fa-edit"></i></button>
            <?php if ($waNum): ?>
            <a href="https://wa.me/<?= $waNum ?>" target="_blank" style="color:#16a34a;border-color:#bbf7d0;background:#f0fdf4;" title="WhatsApp"><i class="fa-brands fa-whatsapp"></i></a>
            <?php endif; ?>
            <a href="documents.php?type=locataire&id=<?= $l['id'] ?>" style="color:#374151;border-color:#e5e7eb;background:#f9fafb;" title="Documents"><i class="fa fa-paperclip"></i></a>
            <button type="button" class="btn-email-locataire" style="color:#0e7490;border-color:#a5f3fc;background:#ecfeff;" title="Envoyer un email"
                    data-bs-toggle="modal" data-bs-target="#modalEmailLocataire"
                    data-id="<?= $l['id'] ?>" data-nom="<?= htmlspecialchars($l['nom']) ?>" data-email="<?= htmlspecialchars($l['email'] ?? '') ?>"
            ><i class="fa fa-envelope"></i></button>
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <form action="../php/delete_locataire.php" method="POST" style="display:contents" onsubmit="return confirm('Supprimer définitivement ?')">
                <input type="hidden" name="token" value="<?= csrf_generate() ?>">
                <input type="hidden" name="id" value="<?= $l['id'] ?>">
                <button type="submit" style="color:#991b1b;border-color:#fecaca;background:#fff1f2;" title="Supprimer"><i class="fa fa-trash"></i></button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div>
</div>

<?php endif; ?>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<div class="d-flex justify-content-between align-items-center" style="padding:14px 28px;background:#f8faff;border-top:1px solid #e8ecf4;">
    <small class="text-muted">Page <?= $page ?> / <?= $totalPages ?> — <?= $totalRows ?> résultat<?= $totalRows>1?'s':'' ?></small>
    <div class="pag">
        <a href="<?= buildUrlL(['page'=>$page-1]) ?>" class="<?= $page<=1?'off':'' ?>"><i class="fa fa-chevron-left" style="font-size:10px;"></i></a>
        <?php
        $start = max(1,$page-2); $end = min($totalPages,$page+2);
        if ($start>1) echo '<span style="border:none;width:auto;color:#aab;">…</span>';
        for ($ii=$start; $ii<=$end; $ii++):
        ?>
        <?php if($ii===$page):?><span class="cur"><?=$ii?></span><?php else:?><a href="<?=buildUrlL(['page'=>$ii])?>"><?=$ii?></a><?php endif;
        endfor;
        if($end<$totalPages) echo '<span style="border:none;width:auto;color:#aab;">…</span>';?>
        <a href="<?= buildUrlL(['page'=>$page+1]) ?>" class="<?= $page>=$totalPages?'off':'' ?>"><i class="fa fa-chevron-right" style="font-size:10px;"></i></a>
    </div>
</div>
<?php endif; ?>

</div><!-- /bottom-scroll -->
</div><!-- /main-content -->

<script src="../js/xlsx.full.min.js"></script>
<script src="../js/jspdf.umd.min.js"></script>
<script src="../js/jspdf.plugin.autotable.min.js"></script>
<script src="../js/bootstrap.bundle.min.js"></script>
<script>
function setView(v) {
    document.getElementById('vueListe').style.display  = v==='liste'  ? 'block':'none';
    document.getElementById('vueCartes').style.display = v==='cartes' ? 'block':'none';
    document.getElementById('btnVueListe').classList.toggle('active', v==='liste');
    document.getElementById('btnVueCarte').classList.toggle('active', v==='cartes');
    localStorage.setItem('locataireView', v);
}
setView(localStorage.getItem('locataireView') || 'liste');

var searchTimer;
document.getElementById('searchInput').addEventListener('input', function() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(function() { document.getElementById('searchForm').submit(); }, 350);
});

document.querySelectorAll('.btn-edit').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.getElementById('edit_id').value    = this.dataset.id;
        document.getElementById('edit_nom').value   = this.dataset.nom;
        document.getElementById('edit_tel1').value  = this.dataset.tel1;
        document.getElementById('edit_tel2').value  = this.dataset.tel2;
        document.getElementById('edit_email').value = this.dataset.email || '';
        document.getElementById('edit_piece').value = this.dataset.piece;
    });
});

document.getElementById('modalEmailLocataire').addEventListener('show.bs.modal', function(event) {
    var btn = event.relatedTarget;
    var d = btn.dataset;

    document.getElementById('emailLocataireId').value  = d.id;
    document.getElementById('emailLocataireNom').textContent = d.nom;
    document.getElementById('emailLocataireSujet').value   = '';
    document.getElementById('emailLocataireContenu').value = '';

    var alerte  = document.getElementById('emailLocataireAlertNoEmail');
    var btnSend = document.getElementById('emailLocataireBtnEnvoyer');
    if (d.email) {
        document.getElementById('emailLocataireDest').value = d.nom + ' <' + d.email + '>';
        alerte.classList.add('d-none');
        btnSend.disabled = false;
    } else {
        document.getElementById('emailLocataireDest').value = '';
        alerte.classList.remove('d-none');
        btnSend.disabled = true;
    }
});

document.getElementById('photoLocInput').addEventListener('change', function() {
    var file = this.files[0]; if (!file) return;
    var reader = new FileReader();
    reader.onload = function(e) {
        document.getElementById('previewLocataire').innerHTML = '<img src="'+e.target.result+'" style="width:100%;height:100%;object-fit:cover;">';
    };
    reader.readAsDataURL(file);
});

var exportRows = <?= json_encode($exportRows, JSON_UNESCAPED_UNICODE) ?>;
var agenceInfo = {
    nom: <?= json_encode($entreprise['nom_entreprise'], JSON_UNESCAPED_UNICODE) ?>,
    adresse: <?= json_encode($entreprise['adresse_siege'] ?? '', JSON_UNESCAPED_UNICODE) ?>,
    tel: <?= json_encode($entreprise['contact_telephone'] ?? '', JSON_UNESCAPED_UNICODE) ?>,
    email: <?= json_encode($entreprise['contact_email'] ?? '', JSON_UNESCAPED_UNICODE) ?>,
    activites: <?= json_encode(array_values($activitesExport), JSON_UNESCAPED_UNICODE) ?>,
    footerLines: <?= json_encode($footerLinesExport, JSON_UNESCAPED_UNICODE) ?>,
    logo: <?= (!empty($entreprise['logo_url']) && file_exists('../uploads/' . $entreprise['logo_url']))
        ? json_encode('../uploads/' . $entreprise['logo_url'], JSON_UNESCAPED_UNICODE)
        : 'null' ?>
};

function loadImageAsDataURL(url) {
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
    var rows = exportRows.map(function(r) {
        return {
            'Locataire': r.nom,
            'Pièce d\'identité': r.piece,
            'Statut': r.statut,
            'Maison actuelle': r.maison,
            'Adresse': r.adresse,
            'Loyer / mois (FCFA)': r.loyer_num,
            'Téléphone 1': r.tel1,
            'Téléphone 2': r.tel2,
            'Date d\'enregistrement': r.date_enr
        };
    });
    var ws = XLSX.utils.json_to_sheet(rows);
    ws['!cols'] = [{wch:24},{wch:16},{wch:10},{wch:24},{wch:28},{wch:16},{wch:14},{wch:14},{wch:16}];
    var wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Locataires');
    XLSX.writeFile(wb, 'Liste_Locataires.xlsx');
}

function exportToPDF() {
    loadImageAsDataURL(agenceInfo.logo).then(function(logo) {
    var doc = new window.jspdf.jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' });
    var marine = [0, 33, 71];
    var pageW = doc.internal.pageSize.getWidth();

    // En-tête agence (identique aux documents imprimés : logo à gauche, activités à droite, ruban de couleur)
    if (logo) {
        var logoH = 16, logoW = logoH * logo.ratio;
        doc.addImage(logo.dataUrl, 'PNG', 14, 8, logoW, logoH);
    }

    doc.setFontSize(7.5);
    doc.setFont(undefined, 'bold');
    doc.setTextColor(marine[0], marine[1], marine[2]);
    (agenceInfo.activites || []).forEach(function(act, i) {
        doc.text(act, pageW - 14, 10 + i * 3.4, { align: 'right' });
    });

    doc.setFillColor(240, 173, 0);
    doc.rect(14, 27, pageW - 28, 1.1, 'F');
    doc.setFillColor(marine[0], marine[1], marine[2]);
    doc.rect(14, 28.1, pageW - 28, 1.1, 'F');

    // Titre + méta
    doc.setFontSize(12.5);
    doc.setFont(undefined, 'bold');
    doc.setTextColor(30, 30, 30);
    doc.text('Répertoire des Locataires', 14, 36);

    var nbActifs = exportRows.filter(function(r) { return r.statut === 'Actif'; }).length;
    doc.setFontSize(9);
    doc.setFont(undefined, 'normal');
    doc.setTextColor(100, 100, 100);
    doc.text(
        exportRows.length + ' locataire' + (exportRows.length > 1 ? 's' : '') +
        ' — ' + nbActifs + ' actif' + (nbActifs > 1 ? 's' : '') + ', ' + (exportRows.length - nbActifs) + ' libre' + ((exportRows.length - nbActifs) > 1 ? 's' : ''),
        14, 42
    );
    doc.text('Généré le ' + new Date().toLocaleDateString('fr-FR'), pageW - 14, 42, { align: 'right' });

    doc.autoTable({
        startY: 47,
        head: [['Locataire', 'Statut', 'Maison actuelle', 'Loyer / mois', 'Contact', 'Enregistré le']],
        body: exportRows.map(function(r) {
            var identite = r.nom + (r.piece && r.piece !== '—' ? '\n' + r.piece : '');
            var maison   = r.maison + (r.adresse ? '\n' + r.adresse : '');
            var contact  = [r.tel1, r.tel2].filter(Boolean).join('\n');
            return [identite, r.statut, maison, r.loyer, contact, r.date_enr];
        }),
        theme: 'striped',
        styles: { fontSize: 9, cellPadding: 3, valign: 'middle' },
        headStyles: { fillColor: marine, textColor: 255, fontStyle: 'bold' },
        alternateRowStyles: { fillColor: [245, 247, 252] },
        margin: { bottom: 8 + Math.max(0, (agenceInfo.footerLines || []).length - 1) * 3.3 + 6 },
        columnStyles: {
            0: { cellWidth: 46 },
            1: { cellWidth: 18 },
            3: { cellWidth: 26, halign: 'right' },
            5: { cellWidth: 22 }
        },
        didParseCell: function(data) {
            if (data.section === 'body' && data.column.index === 1) {
                data.cell.styles.textColor = data.cell.raw === 'Actif' ? [5, 150, 105] : [148, 163, 184];
                data.cell.styles.fontStyle = 'bold';
            }
        },
        didDrawPage: function(data) {
            var lines = agenceInfo.footerLines || [];
            if (!lines.length) return;
            var pageH = doc.internal.pageSize.getHeight();
            var lineH = 3.3, bottomMargin = 8;
            var startY = pageH - bottomMargin - (lines.length - 1) * lineH;
            doc.setDrawColor(marine[0], marine[1], marine[2]);
            doc.setLineWidth(0.3);
            doc.line(14, startY - 3.5, pageW - 14, startY - 3.5);
            lines.forEach(function(line, i) {
                var isLast = i === lines.length - 1;
                doc.setFontSize(6.5);
                doc.setFont(undefined, isLast ? 'bold' : 'normal');
                if (isLast) { doc.setTextColor(marine[0], marine[1], marine[2]); } else { doc.setTextColor(90, 90, 90); }
                doc.text(line, pageW / 2, startY + i * lineH, { align: 'center' });
            });
        }
    });

    doc.save('Liste_Locataires.pdf');
    });
}
</script>
</body>
</html>
