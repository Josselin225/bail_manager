<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once('../config/db.php');

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }

$search      = trim($_GET['search'] ?? '');
$perPage     = 10;
$page        = max(1, (int)($_GET['page'] ?? 1));
$searchParam = "%$search%";

$totalActifs = (int)$pdo->query("SELECT COUNT(DISTINCT c.id) FROM contrats c WHERE c.statut_contrat='actif'")->fetchColumn();

$stmtCnt = $pdo->prepare("SELECT COUNT(DISTINCT c.id) FROM contrats c JOIN locataires l ON c.locataire_id=l.id WHERE l.nom LIKE ? AND c.statut_contrat='actif'");
$stmtCnt->execute([$searchParam]);
$totalRows  = (int)$stmtCnt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

$total_cautions = (float)$pdo->query(
    "SELECT ((SELECT IFNULL(SUM(depot_garantie),0) FROM contrats WHERE statut_contrat='actif')
           + (SELECT IFNULL(SUM(montant),0) FROM mouvements_caution))"
)->fetchColumn();

$nbSansMouvement = (int)$pdo->query(
    "SELECT COUNT(DISTINCT c.id) FROM contrats c
     WHERE c.statut_contrat='actif'
       AND c.locataire_id NOT IN (SELECT locataire_id FROM mouvements_caution)"
)->fetchColumn();

$locataires = $pdo->query("SELECT l.id, l.nom FROM locataires l JOIN contrats c ON l.id=c.locataire_id WHERE c.statut_contrat='actif' ORDER BY l.nom ASC")->fetchAll();

$stmtList = $pdo->prepare(
    "SELECT l.id AS locataire_id, l.nom AS nom_locataire, m.designation AS nom_propriete,
            c.id AS contrat_id, c.depot_garantie AS caution_initiale, c.date_debut,
            IFNULL((SELECT SUM(mc.montant) FROM mouvements_caution mc WHERE mc.locataire_id=l.id),0) AS total_mouvements
     FROM contrats c
     JOIN locataires l ON c.locataire_id=l.id
     JOIN maisons m ON c.maison_id=m.id
     WHERE l.nom LIKE ? AND c.statut_contrat='actif'
     GROUP BY c.id ORDER BY l.nom ASC LIMIT $perPage OFFSET $offset"
);
$stmtList->execute([$searchParam]);
$liste_cautions = $stmtList->fetchAll();

function buildUrlCaution(array $extra = []): string {
    global $search, $page;
    $p = array_filter(['search'=>$search,'page'=>$page], fn($v)=>$v!==''&&$v!==null&&$v!==0);
    return '?' . http_build_query(array_merge($p, $extra));
}

$avatarColors = [
    ['bg'=>'#dbeafe','fg'=>'#1e40af'],['bg'=>'#fce7f3','fg'=>'#9d174d'],
    ['bg'=>'#d1fae5','fg'=>'#065f46'],['bg'=>'#fef3c7','fg'=>'#92400e'],
    ['bg'=>'#ede9fe','fg'=>'#5b21b6'],['bg'=>'#fee2e2','fg'=>'#991b1b'],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Cautions — BailManager</title>
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

        /* ── Table ── */
        .tbl-full { background:#fff; border-top:1px solid #e8ecf4; width:100%; }
        .tbl-full thead th { background:#f8faff; color:#6b7a99; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; padding:13px 20px; border-bottom:1px solid #e8ecf4; position:sticky; top:0; z-index:2; }
        .tbl-full tbody td { padding:11px 20px; border-bottom:1px solid #f0f3fa; vertical-align:middle; font-size:13px; }
        .tbl-full tbody tr:last-child td { border-bottom:none; }
        .tbl-full tbody tr:hover td { background:#f8faff; }
        .avatar-sm { width:34px; height:34px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:12px; flex-shrink:0; }

        /* ── Progress bar caution ── */
        .caution-bar { height:5px; border-radius:3px; background:#e8ecf4; overflow:hidden; margin-top:5px; }
        .caution-bar-fill { height:100%; border-radius:3px; transition:width .3s; }

        /* ── Status badges ── */
        .badge-intact    { background:#d1fae5; color:#065f46; font-size:10px; font-weight:700; padding:3px 8px; border-radius:20px; }
        .badge-partiel   { background:#fef3c7; color:#92400e; font-size:10px; font-weight:700; padding:3px 8px; border-radius:20px; }
        .badge-excedent  { background:#dbeafe; color:#1e40af; font-size:10px; font-weight:700; padding:3px 8px; border-radius:20px; }
        .badge-negatif   { background:#fee2e2; color:#991b1b; font-size:10px; font-weight:700; padding:3px 8px; border-radius:20px; }

        /* ── Cards view ── */
        .c-card { background:#fff; border-radius:14px; border:1px solid #e8ecf4; box-shadow:0 2px 8px rgba(0,0,0,.05); overflow:hidden; transition:box-shadow .18s,transform .18s; }
        .c-card:hover { box-shadow:0 6px 20px rgba(0,33,71,.1); transform:translateY(-2px); }
        .c-card-body { padding:16px; }
        .c-card-actions { padding:10px 16px; border-top:1px solid #f0f3fa; display:flex; gap:6px; }
        .c-card-actions button { flex:1; padding:6px; border-radius:8px; font-size:12px; font-weight:600; border:1.5px solid; cursor:pointer; transition:opacity .15s; background:transparent; }
        .c-card-actions button:hover { opacity:.75; }

        /* ── Mode sombre ── */
        html[data-theme="dark"] .c-card { background:#1e222b; border-color:#2e333d; }
        html[data-theme="dark"] .c-card-actions { border-top-color:#2e333d; }
        html[data-theme="dark"] .btn-historique { background:#262b35 !important; border-color:#3a4150 !important; color:#8fb4ff !important; }
        html[data-theme="dark"] .badge-intact   { background:rgba(5,150,105,.18); color:#5eead4; }
        html[data-theme="dark"] .badge-partiel  { background:rgba(217,119,6,.18); color:#fbbf24; }
        html[data-theme="dark"] .badge-excedent { background:rgba(29,78,216,.2);  color:#93c5fd; }
        html[data-theme="dark"] .badge-negatif  { background:rgba(220,38,38,.18); color:#fca5a5; }

        /* ── Modals ── */
        .vmodal-overlay { display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,.55); z-index:9999; align-items:center; justify-content:center; }
        .vmodal-box { background:#fff; border-radius:12px; width:90%; max-width:560px; max-height:90vh; overflow-y:auto; box-shadow:0 20px 60px rgba(0,0,0,.3); }
        .vmodal-box-lg { max-width:820px; }

        /* ── Impression du relevé d'historique de caution ──
           window.print() imprime par défaut toute la page (menu, tableau...) en plus
           du relevé affiché dans la modale : on isole #printableArea (injecté par
           get_historique_caution.php dans la modale Historique) et on masque le reste. */
        @media print {
            body * { visibility: hidden; }
            #printableArea, #printableArea * { visibility: visible; }
            #printableArea {
                position: absolute;
                top: 0; left: 0;
                width: 100%;
                padding: 0;
            }
            .vmodal-overlay { position: static !important; background: none !important; }
            .vmodal-box { max-width: none !important; max-height: none !important; box-shadow: none !important; overflow: visible !important; }
        }

        /* ── Pagination ── */
        .pag { display:flex; align-items:center; gap:4px; }
        .pag a, .pag span { display:inline-flex; align-items:center; justify-content:center; width:32px; height:32px; border-radius:8px; font-size:12px; font-weight:600; text-decoration:none; border:1.5px solid #e0e6f0; color:#6b7a99; }
        .pag a:hover { border-color:var(--marine); color:var(--marine); }
        .pag span.cur { background:var(--marine); border-color:var(--marine); color:#fff; }
        .pag a.off { opacity:.35; pointer-events:none; }
    </style>
</head>
<body>
<?php include('../includes/sidebar.php'); ?>

<!-- ══ MODAL RESTITUTION ══ -->
<div id="modalRestitution" class="vmodal-overlay" onclick="if(event.target===this)hideModal('modalRestitution')">
    <div class="vmodal-box">
        <form method="POST" action="../php/process_restitution_caution.php">
            <input type="hidden" name="token" value="<?= csrf_generate() ?>">
            <div style="background:linear-gradient(135deg,#78350f,#d97706);padding:18px 22px;display:flex;align-items:center;justify-content:space-between;border-radius:12px 12px 0 0;">
                <div style="display:flex;align-items:center;gap:12px;">
                    <div style="width:40px;height:40px;border-radius:50%;background:rgba(255,255,255,.15);display:flex;align-items:center;justify-content:center;"><i class="fa fa-door-open" style="color:#fff;"></i></div>
                    <div>
                        <div style="color:#fff;font-weight:700;font-size:15px;">Départ & Restitution</div>
                        <div style="color:rgba(255,255,255,.75);font-size:12px;">Traitement du départ locataire</div>
                    </div>
                </div>
                <button type="button" onclick="hideModal('modalRestitution')" style="background:none;border:none;color:#fff;font-size:22px;cursor:pointer;line-height:1;">&times;</button>
            </div>
            <div style="padding:20px 22px;">
                <div style="margin-bottom:14px;">
                    <label style="display:block;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:#8896b0;margin-bottom:6px;">Locataire partant <span style="color:#e53e3e;">*</span></label>
                    <div style="display:flex;border:1.5px solid #dee2e6;border-radius:8px;overflow:hidden;">
                        <span style="background:#f8f9fa;padding:8px 12px;display:flex;align-items:center;border-right:1.5px solid #dee2e6;"><i class="fa fa-user" style="color:#8896b0;font-size:13px;"></i></span>
                        <select name="locataire_id" id="selectLocataire" style="flex:1;border:none;padding:8px 12px;font-size:14px;outline:none;background:#fff;" required>
                            <option value="">-- Sélectionner --</option>
                            <?php foreach($liste_cautions as $row): $solde = $row['caution_initiale'] + $row['total_mouvements']; ?>
                            <option value="<?= $row['locataire_id'] ?>" data-caution="<?= $solde ?>"><?= htmlspecialchars($row['nom_locataire']) ?> — <?= number_format($solde,0,',',' ') ?> FCFA</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div style="margin-bottom:14px;padding:12px;background:#f8f9fa;border-radius:8px;">
                    <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                        <input type="checkbox" name="cloturer_contrat" style="width:18px;height:18px;cursor:pointer;">
                        <span style="font-weight:600;color:#1d4ed8;font-size:13px;">Clôturer le contrat et libérer le bien</span>
                    </label>
                </div>
                <div style="margin-bottom:14px;">
                    <label style="display:block;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:#8896b0;margin-bottom:6px;">Solde caution disponible</label>
                    <div style="display:flex;border:1.5px solid #dee2e6;border-radius:8px;overflow:hidden;">
                        <span style="background:#f8f9fa;padding:8px 12px;border-right:1.5px solid #dee2e6;"><i class="fa fa-lock" style="color:#8896b0;font-size:13px;"></i></span>
                        <input type="number" id="cautionInitiale" name="montant_total_caution" style="flex:1;border:none;padding:8px 12px;font-size:14px;outline:none;background:#f8f9fa;" readonly required>
                        <span style="background:#f8f9fa;padding:8px 12px;border-left:1.5px solid #dee2e6;font-size:13px;color:#6b7a99;">FCFA</span>
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
                    <div>
                        <label style="display:block;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:#e53e3e;margin-bottom:6px;">Retenue réparations</label>
                        <div style="display:flex;border:1.5px solid #dee2e6;border-radius:8px;overflow:hidden;">
                            <span style="background:#f8f9fa;padding:8px 10px;border-right:1.5px solid #dee2e6;"><i class="fa fa-tools" style="color:#8896b0;font-size:13px;"></i></span>
                            <input type="number" id="retenueReparation" name="retenue_reparation" style="flex:1;border:none;padding:8px 10px;font-size:14px;outline:none;" value="0" min="0">
                        </div>
                    </div>
                    <div>
                        <label style="display:block;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:#e53e3e;margin-bottom:6px;">Retenue loyer dû</label>
                        <div style="display:flex;border:1.5px solid #dee2e6;border-radius:8px;overflow:hidden;">
                            <span style="background:#f8f9fa;padding:8px 10px;border-right:1.5px solid #dee2e6;"><i class="fa fa-home" style="color:#8896b0;font-size:13px;"></i></span>
                            <input type="number" id="retenueLoyer" name="retenue_loyer" style="flex:1;border:none;padding:8px 10px;font-size:14px;outline:none;" value="0" min="0">
                        </div>
                    </div>
                </div>
                <div style="padding:14px 16px;background:#d1fae5;border-radius:8px;display:flex;justify-content:space-between;align-items:center;">
                    <span style="font-size:12px;font-weight:700;color:#065f46;text-transform:uppercase;"><i class="fa fa-check-circle me-1"></i>Montant à restituer :</span>
                    <span id="montantFinalAffichage" style="font-size:1.2rem;font-weight:800;color:var(--green);">0 FCFA</span>
                </div>
            </div>
            <div style="padding:14px 22px;background:#f8f9fa;border-top:1px solid #e8ecf4;display:flex;justify-content:flex-end;gap:10px;border-radius:0 0 12px 12px;">
                <button type="button" onclick="hideModal('modalRestitution')" style="padding:8px 20px;border:1.5px solid #dee2e6;border-radius:8px;background:#fff;font-weight:600;font-size:14px;cursor:pointer;"><i class="fa fa-times" style="margin-right:6px;"></i>Annuler</button>
                <button type="submit" id="btnValider" style="padding:8px 28px;background:#d97706;border:none;border-radius:8px;color:#fff;font-weight:600;font-size:14px;cursor:pointer;"><i class="fa fa-check" style="margin-right:6px;"></i>Valider</button>
            </div>
        </form>
    </div>
</div>

<!-- ══ MODAL HISTORIQUE ══ -->
<div id="modalHistorique" class="vmodal-overlay" onclick="if(event.target===this)hideModal('modalHistorique')">
    <div class="vmodal-box vmodal-box-lg">
        <div style="background:linear-gradient(135deg,#002147,#004080);padding:16px 20px;border-radius:12px 12px 0 0;display:flex;align-items:center;justify-content:space-between;">
            <div style="display:flex;align-items:center;gap:10px;">
                <i class="fa fa-history" style="color:rgba(255,255,255,.7);"></i>
                <h5 style="margin:0;font-weight:700;color:#fff;font-size:14px;">Historique caution — <span id="nomLocataireTitre"></span></h5>
            </div>
            <button type="button" onclick="hideModal('modalHistorique')" style="background:none;border:none;font-size:20px;cursor:pointer;color:rgba(255,255,255,.8);line-height:1;">&times;</button>
        </div>
        <div style="padding:20px;" id="contenuHistorique">
            <div class="text-center py-3 text-muted"><i class="fa fa-spinner fa-spin"></i></div>
        </div>
    </div>
</div>

<div class="main-content">
<div class="top-fixed">

    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <div style="flex:1;">
            <p class="text-muted small mb-0 mt-1">Suivi des dépôts de garantie et restitutions</p>
        </div>
        <button onclick="showModal('modalRestitution')" class="btn btn-warning btn-sm fw-bold shadow-sm" style="border-radius:8px;">
            <i class="fa fa-hand-holding-dollar me-2"></i>Restituer / Retenue
        </button>
    </div>

    <div class="row g-2 mb-3">
        <div class="col-6 col-md-3">
            <div class="kpi-card" style="background:linear-gradient(135deg,#1e3c72,#2a5298);">
                <div class="kpi-icon" style="background:rgba(255,255,255,.15);"><i class="fa fa-vault" style="color:#fff;"></i></div>
                <div>
                    <div class="kpi-val" style="color:#fff;"><?= number_format($total_cautions,0,',',' ') ?></div>
                    <div class="kpi-lbl" style="color:rgba(255,255,255,.65);">Cautions en main</div>
                    <div class="kpi-sub" style="color:rgba(255,255,255,.5);">FCFA</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card"><div class="kpi-icon" style="background:#d1fae5;"><i class="fa fa-file-contract" style="color:var(--green);"></i></div><div><div class="kpi-val" style="color:var(--green);"><?= $totalActifs ?></div><div class="kpi-lbl">Contrats actifs</div><div class="kpi-sub">avec caution</div></div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card"><div class="kpi-icon" style="background:#d1fae5;"><i class="fa fa-shield-halved" style="color:var(--green);"></i></div><div><div class="kpi-val" style="color:var(--green);"><?= $nbSansMouvement ?></div><div class="kpi-lbl">Intact</div><div class="kpi-sub">aucun mouvement</div></div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card"><div class="kpi-icon" style="background:#fef3c7;"><i class="fa fa-arrows-rotate" style="color:var(--amber);"></i></div><div><div class="kpi-val" style="color:var(--amber);"><?= $totalActifs - $nbSansMouvement ?></div><div class="kpi-lbl">Avec mouvement</div><div class="kpi-sub">retenue / surplus</div></div></div>
        </div>
    </div>

    <div class="filter-bar mb-0">
        <form method="GET" class="d-flex align-items-center gap-2 flex-wrap w-100">
            <i class="fa fa-search text-muted" style="font-size:13px;"></i>
            <input type="text" id="searchInput" name="search" value="<?= htmlspecialchars($search) ?>"
                   class="form-control form-control-sm" style="max-width:300px;border-radius:8px;"
                   placeholder="Nom du locataire…" autocomplete="off">
            <?php if ($search): ?>
            <a href="gestion_cautions.php" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;"><i class="fa fa-times"></i></a>
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

<?php if (empty($liste_cautions)): ?>
<div class="text-center text-muted py-5"><i class="fa fa-inbox fa-3x mb-3 d-block" style="opacity:.2;"></i>Aucune caution trouvée.</div>
<?php else: ?>

<!-- ══ VUE LISTE ══ -->
<div id="vueListe">
<table class="table mb-0 tbl-full">
    <thead><tr>
        <th style="width:26%;">Locataire</th>
        <th style="width:20%;">Bien &amp; Contrat</th>
        <th style="width:12%;">Statut</th>
        <th class="text-end" style="width:16%;">Dépôt initial</th>
        <th style="width:18%;">Solde &amp; utilisation</th>
        <th class="text-center" style="width:8%;">Actions</th>
    </tr></thead>
    <tbody>
    <?php foreach ($liste_cautions as $i => $row):
        $solde    = (float)$row['caution_initiale'] + (float)$row['total_mouvements'];
        $caution  = (float)$row['caution_initiale'];
        $pct      = $caution > 0 ? max(0, min(100, round(($solde / $caution) * 100))) : 0;
        $col      = $avatarColors[$i % count($avatarColors)];
        $initiale = mb_strtoupper(mb_substr($row['nom_locataire'], 0, 1));

        // Statut
        if ($row['total_mouvements'] == 0)       { $badge = '<span class="badge-intact">Intact</span>';   $barColor = '#059669'; }
        elseif ($solde >= $caution)               { $badge = '<span class="badge-excedent">Excédent</span>'; $barColor = '#1d4ed8'; }
        elseif ($solde > 0)                       { $badge = '<span class="badge-partiel">Partiel</span>'; $barColor = '#d97706'; }
        else                                      { $badge = '<span class="badge-negatif">Négatif</span>'; $barColor = '#e53e3e'; }
    ?>
    <tr>
        <td>
            <div class="d-flex align-items-center gap-3">
                <div class="avatar-sm" style="background:<?= $col['bg'] ?>;color:<?= $col['fg'] ?>;"><?= htmlspecialchars($initiale) ?></div>
                <div>
                    <div class="fw-semibold" style="color:#2d3a55;"><?= htmlspecialchars($row['nom_locataire']) ?></div>
                    <?php if (!empty($row['date_debut'])): ?>
                    <div class="text-muted" style="font-size:10px;"><i class="fa fa-calendar me-1"></i>Depuis <?= date('d/m/Y', strtotime($row['date_debut'])) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </td>
        <td>
            <div class="fw-semibold small" style="color:#2d3a55;"><?= htmlspecialchars($row['nom_propriete']) ?></div>
            <div class="text-muted" style="font-size:10px;">N° <?= $row['contrat_id'] ?></div>
        </td>
        <td><?= $badge ?></td>
        <td class="text-end fw-bold text-muted"><?= number_format($caution,0,',',' ') ?> <small class="fw-normal">FCFA</small></td>
        <td>
            <div class="d-flex align-items-center justify-content-between mb-1">
                <span class="fw-bold" style="font-size:13px;<?= $solde<0?'color:var(--red)':($solde>=$caution?'color:#1d4ed8':'color:#2d3a55') ?>"><?= number_format($solde,0,',',' ') ?> FCFA</span>
                <span style="font-size:10px;color:#8896b0;"><?= $pct ?>%</span>
            </div>
            <div class="caution-bar">
                <div class="caution-bar-fill" style="width:<?= $pct ?>%;background:<?= $barColor ?>;"></div>
            </div>
            <?php if ($row['total_mouvements'] != 0): ?>
            <div style="font-size:10px;color:#8896b0;margin-top:2px;">Mouv: <?= ($row['total_mouvements']>0?'+':'').number_format($row['total_mouvements'],0,',',' ') ?> FCFA</div>
            <?php endif; ?>
        </td>
        <td class="text-center">
            <button class="btn btn-sm btn-outline-primary btn-historique" style="border-radius:6px;"
                    data-id="<?= $row['contrat_id'] ?>" data-nom="<?= htmlspecialchars($row['nom_locataire']) ?>"
                    onclick="showModal('modalHistorique')" title="Historique"><i class="fa fa-history"></i></button>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<!-- ══ VUE CARTES ══ -->
<div id="vueCartes" style="padding:20px 28px 0;">
<div class="row g-3">
<?php foreach ($liste_cautions as $i => $row):
    $solde    = (float)$row['caution_initiale'] + (float)$row['total_mouvements'];
    $caution  = (float)$row['caution_initiale'];
    $pct      = $caution > 0 ? max(0, min(100, round(($solde / $caution) * 100))) : 0;
    $col      = $avatarColors[$i % count($avatarColors)];
    $initiale = mb_strtoupper(mb_substr($row['nom_locataire'], 0, 1));

    if ($row['total_mouvements'] == 0)     { $badge = '<span class="badge-intact">Intact</span>';   $barColor = '#059669'; }
    elseif ($solde >= $caution)            { $badge = '<span class="badge-excedent">Excédent</span>'; $barColor = '#1d4ed8'; }
    elseif ($solde > 0)                    { $badge = '<span class="badge-partiel">Partiel</span>'; $barColor = '#d97706'; }
    else                                   { $badge = '<span class="badge-negatif">Négatif</span>'; $barColor = '#e53e3e'; }
?>
<div class="col-sm-6 col-lg-4">
    <div class="c-card h-100">
        <div class="c-card-body">
            <div class="d-flex align-items-center gap-3 mb-3">
                <div class="avatar-sm" style="background:<?= $col['bg'] ?>;color:<?= $col['fg'] ?>;width:40px;height:40px;font-size:14px;"><?= htmlspecialchars($initiale) ?></div>
                <div style="flex:1;min-width:0;">
                    <div class="fw-bold" style="color:#1e293b;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($row['nom_locataire']) ?></div>
                    <div style="font-size:11px;color:#8896b0;"><?= htmlspecialchars($row['nom_propriete']) ?></div>
                </div>
                <?= $badge ?>
            </div>

            <div style="background:#f8faff;border-radius:8px;padding:10px 12px;margin-bottom:10px;">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span style="font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:#8896b0;">Solde caution</span>
                    <span style="font-size:10px;color:#8896b0;"><?= $pct ?>% restant</span>
                </div>
                <div class="d-flex justify-content-between align-items-baseline">
                    <span class="fw-bold" style="font-size:16px;<?= $solde<0?'color:var(--red)':($solde>=$caution?'color:#1d4ed8':'color:#2d3a55') ?>"><?= number_format($solde,0,',',' ') ?></span>
                    <span style="font-size:10px;color:#8896b0;">/ <?= number_format($caution,0,',',' ') ?> FCFA</span>
                </div>
                <div class="caution-bar mt-2" style="height:6px;">
                    <div class="caution-bar-fill" style="width:<?= $pct ?>%;background:<?= $barColor ?>;"></div>
                </div>
            </div>

            <div style="font-size:11px;color:#8896b0;display:flex;gap:12px;flex-wrap:wrap;">
                <span><i class="fa fa-hashtag me-1"></i>Contrat N° <?= $row['contrat_id'] ?></span>
                <?php if (!empty($row['date_debut'])): ?>
                <span><i class="fa fa-calendar me-1"></i><?= date('d/m/Y', strtotime($row['date_debut'])) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="c-card-actions">
            <button class="btn-historique" style="color:#1d4ed8;border-color:#bfdbfe;background:#eff6ff;"
                    data-id="<?= $row['contrat_id'] ?>" data-nom="<?= htmlspecialchars($row['nom_locataire']) ?>"
                    onclick="showModal('modalHistorique')"><i class="fa fa-history me-1"></i>Historique</button>
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
        <a href="<?= buildUrlCaution(['page'=>$page-1]) ?>" class="<?= $page<=1?'off':'' ?>"><i class="fa fa-chevron-left" style="font-size:10px;"></i></a>
        <?php $s=max(1,$page-2);$e=min($totalPages,$page+2);if($s>1)echo'<span style="border:none;width:auto;color:#aab;">…</span>';for($i=$s;$i<=$e;$i++):?>
        <?php if($i===$page):?><span class="cur"><?=$i?></span><?php else:?><a href="<?=buildUrlCaution(['page'=>$i])?>"><?=$i?></a><?php endif;endfor;if($e<$totalPages)echo'<span style="border:none;width:auto;color:#aab;">…</span>';?>
        <a href="<?= buildUrlCaution(['page'=>$page+1]) ?>" class="<?= $page>=$totalPages?'off':'' ?>"><i class="fa fa-chevron-right" style="font-size:10px;"></i></a>
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
    localStorage.setItem('cautionView', v);
}
setView(localStorage.getItem('cautionView') || 'liste');

function showModal(id) { var el=document.getElementById(id); if(el){el.style.display='flex';document.body.style.overflow='hidden';} }
function hideModal(id) { var el=document.getElementById(id); if(el){el.style.display='none';document.body.style.overflow='';} }

document.querySelectorAll('.btn-historique').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var id = this.getAttribute('data-id');
        document.getElementById('nomLocataireTitre').innerText = this.getAttribute('data-nom');
        document.getElementById('contenuHistorique').innerHTML = '<div class="text-center p-4"><i class="fa fa-spinner fa-spin"></i> Chargement…</div>';
        fetch('get_historique_caution.php?id='+id).then(function(r){return r.text();}).then(function(data){document.getElementById('contenuHistorique').innerHTML=data;});
    });
});

document.addEventListener('DOMContentLoaded', function() {
    var selectLoc = document.getElementById('selectLocataire');
    var inputBase = document.getElementById('cautionInitiale');
    var inputRep  = document.getElementById('retenueReparation');
    var inputLoy  = document.getElementById('retenueLoyer');
    var affichage = document.getElementById('montantFinalAffichage');
    var btnV      = document.getElementById('btnValider');

    function calculer() {
        var base  = parseFloat(inputBase.value) || 0;
        var reste = base - (parseFloat(inputRep.value)||0) - (parseFloat(inputLoy.value)||0);
        affichage.innerText = new Intl.NumberFormat('fr-FR').format(Math.max(0,reste)) + ' FCFA';
        if (reste < 0) { affichage.style.color='var(--red)'; btnV.disabled=true; btnV.innerHTML='<i class="fa fa-exclamation-triangle me-1"></i>Retenues insuffisantes'; }
        else           { affichage.style.color='var(--green)'; btnV.disabled=false; btnV.innerHTML='<i class="fa fa-check" style="margin-right:6px;"></i>Valider'; }
    }
    selectLoc.addEventListener('change', function() { inputBase.value = this.options[this.selectedIndex].getAttribute('data-caution') || 0; calculer(); });
    inputRep.addEventListener('input', calculer);
    inputLoy.addEventListener('input', calculer);
});

var searchTimer;
document.getElementById('searchInput').addEventListener('input', function() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(function() { document.querySelector('.filter-bar form').submit(); }, 350);
});

document.addEventListener('keydown', function(e) {
    if (e.key==='Escape') { hideModal('modalRestitution'); hideModal('modalHistorique'); }
});
</script>
</body>
</html>
