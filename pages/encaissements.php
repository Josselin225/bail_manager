<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once('../config/db.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$search  = trim($_GET['search'] ?? '');
$perPage = 10;
$page    = max(1, (int)($_GET['page'] ?? 1));

$conds = ["c.statut_contrat='actif'"]; $bind = [];
if ($search) { $conds[] = "(l.nom LIKE :s OR m.designation LIKE :s2)"; $bind[':s']=$bind[':s2']="%$search%"; }
$where = implode(" AND ", $conds);
$base  = "FROM contrats c JOIN locataires l ON c.locataire_id=l.id JOIN maisons m ON c.maison_id=m.id WHERE $where";

$stmtC = $pdo->prepare("SELECT COUNT(*) $base"); $stmtC->execute($bind); $totalRows = (int)$stmtC->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

$stmtList = $pdo->prepare("SELECT c.*, l.nom, m.designation $base ORDER BY c.date_prochain_loyer ASC LIMIT :lim OFFSET :off");
foreach ($bind as $k => $v) $stmtList->bindValue($k, $v);
$stmtList->bindValue(':lim', $perPage, PDO::PARAM_INT);
$stmtList->bindValue(':off', $offset,  PDO::PARAM_INT);
$stmtList->execute();
$contrats = $stmtList->fetchAll();

// Alertes loyers dus/en retard
$alertesEnc = $pdo->query(
    "SELECT c.id, c.date_prochain_loyer, c.loyer_mensuel, l.nom AS nom_locataire, m.designation AS nom_maison
     FROM contrats c JOIN locataires l ON c.locataire_id=l.id JOIN maisons m ON c.maison_id=m.id
     WHERE c.statut_contrat='actif' AND c.date_prochain_loyer <= CURDATE()
     ORDER BY c.date_prochain_loyer ASC LIMIT 10"
)->fetchAll();

// KPI
$totalActifs  = (int)  $pdo->query("SELECT COUNT(*) FROM contrats WHERE statut_contrat='actif'")->fetchColumn();
$nbRetard     = (int)  $pdo->query("SELECT COUNT(*) FROM contrats WHERE statut_contrat='actif' AND date_prochain_loyer < CURDATE()")->fetchColumn();
$nbAJour      = $totalActifs - $nbRetard;
$loyersDus    = (float)$pdo->query("SELECT COALESCE(SUM(loyer_mensuel),0) FROM contrats WHERE statut_contrat='actif' AND date_prochain_loyer <= CURDATE()")->fetchColumn();

function buildUrlE(array $extra = []): string {
    global $search, $page;
    $p = array_filter(['search'=>$search,'page'=>$page], fn($v)=>$v!==''&&$v!==null&&$v!==0);
    return '?' . http_build_query(array_merge($p, $extra));
}

$moisList = ['Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suivi des Encaissements — BailManager</title>
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
        .tbl-full { background:#fff; border-top:1px solid #e8ecf4; width:100%; }
        .tbl-full thead th { background:#f8faff; color:#6b7a99; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; padding:13px 24px; border-bottom:1px solid #e8ecf4; position:sticky; top:0; z-index:2; }
        .tbl-full tbody td { padding:11px 24px; border-bottom:1px solid #f0f3fa; vertical-align:middle; font-size:13px; }
        .tbl-full tbody tr:last-child td { border-bottom:none; }
        .tbl-full tbody tr:hover td { background:#f8faff; }
        .st-retard { background:#fee2e2; color:#991b1b; font-size:11px; font-weight:600; padding:3px 9px; border-radius:20px; }
        .st-ajour  { background:#d1fae5; color:#065f46; font-size:11px; font-weight:600; padding:3px 9px; border-radius:20px; }
        .st-incomp { background:#f1f5f9; color:#64748b; font-size:11px; font-weight:600; padding:3px 9px; border-radius:20px; }
        .pag { display:flex; align-items:center; gap:4px; }
        .pag a, .pag span { display:inline-flex; align-items:center; justify-content:center; width:32px; height:32px; border-radius:8px; font-size:12px; font-weight:600; text-decoration:none; border:1.5px solid #e0e6f0; color:#6b7a99; }
        .pag a:hover { border-color:var(--marine); color:var(--marine); }
        .pag span.cur { background:var(--marine); border-color:var(--marine); color:#fff; }
        .pag a.off { opacity:.35; pointer-events:none; }
    </style>
</head>
<body>
<?php include('../includes/sidebar.php'); ?>

<!-- ══ MODAL PAIEMENT ══ -->
<div class="modal fade" id="modalPaiement" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="../php/add_paiement.php" method="POST" id="formPaiement" class="modal-content shadow-lg border-0">
            <input type="hidden" name="token" value="<?= csrf_generate() ?>">
            <div class="modal-header text-white border-0" style="background:linear-gradient(135deg,#065f46,#059669);">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-white bg-opacity-10 d-flex align-items-center justify-content-center" style="width:40px;height:40px;"><i class="fa fa-hand-holding-dollar text-white"></i></div>
                    <div><h5 class="modal-title fw-bold mb-0">Encaisser un loyer</h5><small class="opacity-75">Enregistrement du paiement</small></div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" name="contrat_id" id="modal_contrat_id">
                <div class="mb-4 text-center">
                    <span class="text-muted small text-uppercase">Locataire</span>
                    <h4 id="modal_nom_locataire" class="fw-bold text-dark mb-0"></h4>
                    <div id="alerte_reliquat" class="mt-3"></div>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">Total Loyer (FCFA)</label>
                        <input type="number" id="modal_montant_total" class="form-control bg-light fw-bold" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-primary">Montant Versé (FCFA)</label>
                        <input type="number" name="montant_paye" id="modal_montant_verse" class="form-control border-primary fw-bold" required>
                    </div>
                    <div class="col-12">
                        <div class="p-2 rounded bg-warning bg-opacity-10 border border-warning border-opacity-25 d-flex justify-content-between align-items-center">
                            <span class="small fw-bold text-warning-emphasis text-uppercase">Reste à payer :</span>
                            <span id="modal_reste_payer" class="fw-bold text-danger">0 FCFA</span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">Mois concerné</label>
                        <select name="periode_mois" id="select_mois" class="form-select">
                            <?php $moisActuel=(int)date('m'); foreach($moisList as $idx=>$m): ?>
                            <option value="<?= $m ?>" <?= $moisActuel===($idx+1)?'selected':'' ?>><?= $m ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">Année</label>
                        <select name="periode_annee" id="select_annee" class="form-select">
                            <?php $y=(int)date('Y'); for($a=$y-1;$a<=$y+1;$a++): ?><option value="<?=$a?>" <?=$a===$y?'selected':''?>><?=$a?></option><?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold small">Mode de règlement</label>
                        <select name="mode_paiement" class="form-select">
                            <option value="Espèces">💵 Espèces</option>
                            <option value="Mobile Money">📱 Mobile Money</option>
                            <option value="Virement">🏦 Virement</option>
                            <option value="Chèque">✍️ Chèque</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 p-4">
                <button type="submit" id="btn_confirmer" class="btn btn-success w-100 py-2 fw-bold">CONFIRMER L'ENCAISSEMENT</button>
            </div>
        </form>
    </div>
</div>

<div class="main-content">
<div class="top-fixed">

    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <div>
            <p class="text-muted small mb-0 mt-1">Contrats actifs — loyers en attente</p>
        </div>
    </div>

    <?php if (!empty($alertesEnc)): ?>
    <div class="alert alert-danger alert-dismissible fade show mb-3 p-0 overflow-hidden" style="border-radius:10px;border-left:4px solid #dc2626;">
        <div class="d-flex align-items-center px-3 py-2" style="background:#fff5f5;">
            <i class="fa fa-exclamation-circle text-danger me-2"></i>
            <strong class="text-danger me-2">Loyers en retard :</strong>
            <span class="text-muted small"><?= count($alertesEnc) ?> contrat(s) à encaisser</span>
        </div>
        <div class="px-3 pb-2 pt-1 d-flex flex-wrap gap-2">
            <?php foreach($alertesEnc as $a): $retard=(int)((strtotime('today')-strtotime($a['date_prochain_loyer']))/86400); ?>
            <span class="badge bg-light text-dark border fw-normal py-1 px-2">
                <i class="fa fa-user-circle text-danger me-1"></i><?= htmlspecialchars($a['nom_locataire']) ?> — <?= htmlspecialchars($a['nom_maison']) ?>
                <span class="text-danger ms-1">(<?= $retard===0?"Aujourd'hui":"J+$retard" ?>)</span>
            </span>
            <?php endforeach; ?>
        </div>
        <button type="button" class="btn-close position-absolute top-0 end-0 m-2" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="row g-2 mb-3">
        <div class="col-6 col-md-3">
            <div class="kpi-card"><div class="kpi-icon" style="background:#eef2fb;"><i class="fa fa-file-contract" style="color:var(--marine);"></i></div><div><div class="kpi-val" style="color:var(--marine);"><?= $totalActifs ?></div><div class="kpi-lbl">Contrats</div><div class="kpi-sub">actifs</div></div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card"><div class="kpi-icon" style="background:#d1fae5;"><i class="fa fa-circle-check" style="color:var(--green);"></i></div><div><div class="kpi-val" style="color:var(--green);"><?= $nbAJour ?></div><div class="kpi-lbl">À jour</div><div class="kpi-sub">paiements</div></div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card"><div class="kpi-icon" style="background:#fee2e2;"><i class="fa fa-clock" style="color:var(--red);"></i></div><div><div class="kpi-val" style="color:var(--red);"><?= $nbRetard ?></div><div class="kpi-lbl">En retard</div><div class="kpi-sub">impayés</div></div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card"><div class="kpi-icon" style="background:#fef3c7;"><i class="fa fa-money-bill" style="color:var(--amber);"></i></div><div><div class="kpi-val" style="color:var(--amber);"><?= number_format($loyersDus,0,',',' ') ?></div><div class="kpi-lbl">Loyers dus</div><div class="kpi-sub">FCFA</div></div></div>
        </div>
    </div>

    <div class="filter-bar mb-0">
        <form method="GET" class="d-flex align-items-center gap-2 flex-wrap w-100">
            <i class="fa fa-search text-muted" style="font-size:13px;"></i>
            <input type="text" id="searchInput" name="search" value="<?= htmlspecialchars($search) ?>"
                   class="form-control form-control-sm" style="max-width:300px;border-radius:8px;"
                   placeholder="Locataire, maison…" autocomplete="off">
            <?php if ($search): ?>
            <a href="encaissements.php" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;"><i class="fa fa-times"></i></a>
            <?php endif; ?>
            <div class="ms-auto text-muted small"><?= $totalRows ?> contrat<?= $totalRows>1?'s':'' ?></div>
        </form>
    </div>

</div><!-- /top-fixed -->
<div class="bottom-scroll">

<?php if (empty($contrats)): ?>
<div class="text-center text-muted py-5"><i class="fa fa-inbox fa-3x mb-3 d-block" style="opacity:.2;"></i>Aucun contrat actif trouvé.</div>
<?php else: ?>

<table class="table mb-0 tbl-full">
    <thead><tr>
        <th style="width:26%;">Locataire & Maison</th>
        <th class="text-end" style="width:14%;">Loyer mensuel</th>
        <th style="width:14%;">Prochaine échéance</th>
        <th style="width:10%;">Statut</th>
        <th class="text-center" style="width:36%;">Actions</th>
    </tr></thead>
    <tbody>
    <?php foreach ($contrats as $c):
        $ts     = !empty($c['date_prochain_loyer']) ? strtotime($c['date_prochain_loyer']) : null;
        $retard = $ts && $ts < time();
    ?>
    <tr>
        <td>
            <div class="fw-semibold" style="color:#2d3a55;"><?= htmlspecialchars($c['nom']) ?></div>
            <div class="text-muted" style="font-size:11px;"><?= htmlspecialchars($c['designation']) ?></div>
        </td>
        <td class="text-end fw-bold" style="color:var(--marine);"><?= number_format($c['loyer_mensuel'],0,',',' ') ?> <small class="text-muted fw-normal">FCFA</small></td>
        <td>
            <?php if ($ts): ?>
            <span class="<?= $retard?'fw-bold':'text-muted' ?>" style="<?= $retard?'color:var(--red)':'' ?>"><?= date('d/m/Y', $ts) ?></span>
            <?php else: ?><span class="text-muted small">Non définie</span><?php endif; ?>
        </td>
        <td>
            <?php if (!$ts): ?><span class="st-incomp">Incomplet</span>
            <?php elseif ($retard): ?><span class="st-retard">Impayé</span>
            <?php else: ?><span class="st-ajour">À jour</span><?php endif; ?>
        </td>
        <td class="text-center" style="white-space:nowrap;">
            <button class="btn btn-sm btn-danger" style="border-radius:6px;"
                    onclick="ouvrirModalPaiement(<?= (int)$c['id'] ?>, <?= (float)$c['loyer_mensuel'] ?>, '<?= addslashes(htmlspecialchars($c['nom'])) ?>')">
                <i class="fa fa-hand-holding-dollar me-1"></i>Encaisser
            </button>
            <a href="historique_paiements.php?contrat_id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-secondary ms-1" style="border-radius:6px;" title="Historique">
                <i class="fa fa-history"></i>
            </a>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php endif; ?>

<?php if ($totalPages > 1): ?>
<div class="d-flex justify-content-between align-items-center" style="padding:14px 28px;background:#f8faff;border-top:1px solid #e8ecf4;">
    <small class="text-muted">Page <?= $page ?> / <?= $totalPages ?></small>
    <div class="pag">
        <a href="<?= buildUrlE(['page'=>$page-1]) ?>" class="<?= $page<=1?'off':'' ?>"><i class="fa fa-chevron-left" style="font-size:10px;"></i></a>
        <?php $s=max(1,$page-2);$e=min($totalPages,$page+2);if($s>1)echo'<span style="border:none;width:auto;color:#aab;">…</span>';for($i=$s;$i<=$e;$i++):?>
        <?php if($i===$page):?><span class="cur"><?=$i?></span><?php else:?><a href="<?=buildUrlE(['page'=>$i])?>"><?=$i?></a><?php endif;endfor;if($e<$totalPages)echo'<span style="border:none;width:auto;color:#aab;">…</span>';?>
        <a href="<?= buildUrlE(['page'=>$page+1]) ?>" class="<?= $page>=$totalPages?'off':'' ?>"><i class="fa fa-chevron-right" style="font-size:10px;"></i></a>
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

var montantHistoriquePaye = 0;

function calculerReste() {
    var loyer = parseFloat(document.getElementById('modal_montant_total').value) || 0;
    var verse = parseFloat(document.getElementById('modal_montant_verse').value) || 0;
    var reste = document.getElementById('modal_reste_payer');
    var btn   = document.getElementById('btn_confirmer');
    var nouveauReste = loyer - (montantHistoriquePaye + verse);
    var resteDu      = loyer - montantHistoriquePaye;
    reste.innerText  = (nouveauReste > 0 ? nouveauReste : 0).toLocaleString() + ' FCFA';
    if (verse > resteDu)      { reste.className='fw-bold text-warning'; reste.innerText='Trop perçu !'; btn.disabled=true; btn.innerText='MONTANT TROP ÉLEVÉ'; }
    else if (verse <= 0)      { btn.disabled=true; btn.innerText='SAISIR UN MONTANT'; reste.className='fw-bold text-danger'; }
    else                      { btn.disabled=false; btn.innerText="CONFIRMER L'ENCAISSEMENT"; reste.className=nouveauReste<=0?'fw-bold text-success':'fw-bold text-danger'; }
}

function chargerReliquat(id, periode, loyer) {
    var alerteBox  = document.getElementById('alerte_reliquat');
    var fieldVerse = document.getElementById('modal_montant_verse');
    fetch('../php/get_reliquat.php?contrat_id='+id+'&periode='+encodeURIComponent(periode))
        .then(function(r){return r.json();})
        .then(function(data) {
            montantHistoriquePaye = parseFloat(data.deja_paye) || 0;
            var resteReel = loyer - montantHistoriquePaye;
            if (montantHistoriquePaye > 0 && resteReel > 0) {
                alerteBox.innerHTML = '<div class="alert alert-info py-2 small">Déjà réglé : '+montantHistoriquePaye.toLocaleString()+' | Reste : <b>'+resteReel.toLocaleString()+' FCFA</b></div>';
                fieldVerse.value = resteReel;
            } else if (montantHistoriquePaye >= loyer) {
                alerteBox.innerHTML = '<div class="alert alert-success py-2 small text-center">Ce mois est déjà soldé.</div>';
                fieldVerse.value = 0;
            } else {
                alerteBox.innerHTML = ''; fieldVerse.value = loyer;
            }
            calculerReste();
        });
}

document.getElementById('modal_montant_verse').addEventListener('input', calculerReste);

function ouvrirModalPaiement(id, loyer, nom) {
    var elMois  = document.getElementById('select_mois');
    var elAnnee = document.getElementById('select_annee');
    document.getElementById('modal_contrat_id').value    = id;
    document.getElementById('modal_nom_locataire').innerText = nom;
    document.getElementById('modal_montant_total').value = loyer;

    function verifierSequence() {
        fetch('../php/check_dette_anterieure.php?contrat_id='+id+'&mois='+elMois.value+'&annee='+elAnnee.value)
            .then(function(r){return r.json();})
            .then(function(data) {
                if (data.a_des_dettes) { alert('Invalide : Le mois de '+data.mois_du+' doit être payé avant.'); elMois.value = data.premier_mois_du; }
                chargerReliquat(id, elMois.value+' '+elAnnee.value, loyer);
            });
    }
    elMois.onchange = verifierSequence;
    elAnnee.onchange = verifierSequence;
    verifierSequence();
    new bootstrap.Modal(document.getElementById('modalPaiement')).show();
}
</script>
</body>
</html>
