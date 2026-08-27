<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once('../config/db.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$search         = trim($_GET['search']    ?? '');
$filtreMode     = trim($_GET['mode']      ?? '');
$filtreDateEnr  = trim($_GET['date_enr']  ?? '');
$filtreMoisEnr  = trim($_GET['mois_enr']  ?? '');
$filtreAnneeEnr = trim($_GET['annee_enr'] ?? '');
$perPage = 15;
$page    = max(1, (int)($_GET['page'] ?? 1));

$conds = []; $bind = [];
if ($search)     { $conds[] = "(l.nom LIKE :s OR m.designation LIKE :s2 OR e.reference_recu LIKE :s3)"; $bind[':s']=$bind[':s2']=$bind[':s3']="%$search%"; }
if ($filtreMode) { $conds[] = "e.mode_paiement = :mode"; $bind[':mode'] = $filtreMode; }
if ($filtreDateEnr)       { $conds[] = "DATE(e.date_encaissement) = :date_enr";              $bind[':date_enr']  = $filtreDateEnr; }
elseif ($filtreMoisEnr)  { $conds[] = "DATE_FORMAT(e.date_encaissement,'%Y-%m') = :mois_enr"; $bind[':mois_enr']  = $filtreMoisEnr; }
elseif ($filtreAnneeEnr) { $conds[] = "YEAR(e.date_encaissement) = :annee_enr";               $bind[':annee_enr'] = $filtreAnneeEnr; }
$where = $conds ? "WHERE " . implode(" AND ", $conds) : "";

$base = "FROM encaissements e
          JOIN contrats c ON e.contrat_id = c.id
          JOIN locataires l ON c.locataire_id = l.id
          JOIN maisons m ON c.maison_id = m.id
          $where";

$stmtCount = $pdo->prepare("SELECT COUNT(*) $base");
$stmtCount->execute($bind);
$totalRows  = (int)$stmtCount->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

$stmtList = $pdo->prepare("SELECT e.*, l.nom AS nom_locataire, m.designation AS nom_maison $base ORDER BY e.date_encaissement DESC LIMIT :lim OFFSET :off");
foreach ($bind as $k => $v) $stmtList->bindValue($k, $v);
$stmtList->bindValue(':lim', $perPage, PDO::PARAM_INT);
$stmtList->bindValue(':off', $offset,  PDO::PARAM_INT);
$stmtList->execute();
$encaissements = $stmtList->fetchAll();

// KPI (sur le résultat filtré, toutes pages confondues)
$stmtKpi = $pdo->prepare("SELECT COALESCE(SUM(e.montant_recu),0) AS total, COUNT(*) AS nb $base");
$stmtKpi->execute($bind);
$kpi = $stmtKpi->fetch();
$totalMontant = (float)$kpi['total'];
$nbPaiements  = (int)$kpi['nb'];
$montantMoyen = $nbPaiements > 0 ? $totalMontant / $nbPaiements : 0;

// Jeu de données complet (toutes pages confondues, mêmes filtres) pour les exports PDF/Excel
$stmtAllE = $pdo->prepare("SELECT e.*, l.nom AS nom_locataire, m.designation AS nom_maison $base ORDER BY e.date_encaissement DESC");
$stmtAllE->execute($bind);
$exportRowsEnc = array_map(function ($e) {
    return [
        'date'      => date('d/m/Y H:i', strtotime($e['date_encaissement'])),
        'locataire' => $e['nom_locataire'],
        'maison'    => $e['nom_maison'],
        'periode'   => $e['periode_concernee'] ?: '—',
        'montant_num' => (float)$e['montant_recu'],
        'montant'   => number_format((float)$e['montant_recu'], 0, ',', ' ') . ' FCFA',
        'mode'      => ucfirst(str_replace('_', ' ', $e['mode_paiement'] ?? '')),
        'reference' => $e['reference_recu'] ?: '—',
    ];
}, $stmtAllE->fetchAll());

$entrepriseExport = $pdo->query("SELECT * FROM settings LIMIT 1")->fetch();
if (!$entrepriseExport) {
    $entrepriseExport = ['nom_entreprise' => 'BailManager', 'adresse_siege' => '', 'contact_telephone' => '', 'contact_email' => ''];
}
$footerLinesExport = buildFooterLines($entrepriseExport);
$activitesExport = array_filter(array_map('trim', explode("\n", $entrepriseExport['activites'] ?? '')));

function buildUrlEnc(array $extra = []): string {
    global $search, $page, $filtreMode, $filtreDateEnr, $filtreMoisEnr, $filtreAnneeEnr;
    $p = array_filter(['search'=>$search,'mode'=>$filtreMode,'page'=>$page,'date_enr'=>$filtreDateEnr,'mois_enr'=>$filtreMoisEnr,'annee_enr'=>$filtreAnneeEnr], fn($v)=>$v!==''&&$v!==null&&$v!==0);
    return '?' . http_build_query(array_merge($p, $extra));
}

// Pour le lien "Rapport détaillé", on convertit les filtres période en date_debut/date_fin
$rapportParams = [];
if ($filtreMoisEnr) {
    $rapportParams['date_debut'] = $filtreMoisEnr . '-01';
    $rapportParams['date_fin']   = date('Y-m-t', strtotime($filtreMoisEnr . '-01'));
} elseif ($filtreDateEnr) {
    $rapportParams['date_debut'] = $filtreDateEnr;
    $rapportParams['date_fin']   = $filtreDateEnr;
} elseif ($filtreAnneeEnr) {
    $rapportParams['date_debut'] = $filtreAnneeEnr . '-01-01';
    $rapportParams['date_fin']   = $filtreAnneeEnr . '-12-31';
}
$rapportUrl = 'rapport_encaissements.php' . ($rapportParams ? '?' . http_build_query($rapportParams) : '');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../favicon.svg">
    <title>Tous les Encaissements — BailManager</title>
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
            <p class="text-muted small mb-0 mt-1"><?= $totalRows ?> encaissement<?= $totalRows>1?'s':'' ?> — tous contrats confondus</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button onclick="exportToExcel()" class="btn btn-sm btn-outline-success" style="border-radius:8px;"><i class="fa fa-file-excel me-1"></i>Excel</button>
            <button onclick="exportToPDF()"  class="btn btn-sm btn-outline-danger"  style="border-radius:8px;"><i class="fa fa-file-pdf me-1"></i>PDF</button>
            <a href="<?= htmlspecialchars($rapportUrl) ?>" class="btn btn-sm btn-outline-dark shadow-sm" style="border-radius:8px;">
                <i class="fa fa-print me-2"></i>Rapport détaillé
            </a>
            <a href="encaissements.php" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;"><i class="fa fa-arrow-left me-1"></i>Retour</a>
        </div>
    </div>

    <div class="row g-2 mb-3">
        <div class="col-6 col-md-3">
            <div class="kpi-card"><div class="kpi-icon" style="background:#eef2fb;"><i class="fa fa-receipt" style="color:var(--marine);"></i></div><div><div class="kpi-val" style="color:var(--marine);"><?= $nbPaiements ?></div><div class="kpi-lbl">Paiements</div><div class="kpi-sub">(filtre actuel)</div></div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card"><div class="kpi-icon" style="background:#d1fae5;"><i class="fa fa-money-bill-wave" style="color:var(--green);"></i></div><div><div class="kpi-val" style="color:var(--green);"><?= number_format($totalMontant,0,',',' ') ?></div><div class="kpi-lbl">Total encaissé</div><div class="kpi-sub">FCFA</div></div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card"><div class="kpi-icon" style="background:#fef3c7;"><i class="fa fa-calculator" style="color:var(--amber);"></i></div><div><div class="kpi-val" style="color:var(--amber);"><?= number_format($montantMoyen,0,',',' ') ?></div><div class="kpi-lbl">Montant moyen</div><div class="kpi-sub">FCFA</div></div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card"><div class="kpi-icon" style="background:#eef2fb;"><i class="fa fa-list" style="color:var(--marine);"></i></div><div><div class="kpi-val" style="color:var(--marine);"><?= $totalRows ?></div><div class="kpi-lbl">Résultats</div><div class="kpi-sub">au total</div></div></div>
        </div>
    </div>

    <div class="filter-bar mb-0">
        <form method="GET" class="d-flex align-items-center gap-2 flex-wrap w-100">
            <i class="fa fa-search text-muted" style="font-size:13px;"></i>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                   class="form-control form-control-sm" style="max-width:220px;border-radius:8px;"
                   placeholder="Locataire, maison, référence…" autocomplete="off">
            <select name="mode" class="form-select form-select-sm" style="max-width:150px;border-radius:8px;" onchange="this.form.submit()">
                <option value="">— Tous modes —</option>
                <option value="especes"      <?= $filtreMode==='especes'?'selected':'' ?>>Espèces</option>
                <option value="virement"     <?= $filtreMode==='virement'?'selected':'' ?>>Virement</option>
                <option value="mobile_money" <?= $filtreMode==='mobile_money'?'selected':'' ?>>Mobile Money</option>
                <option value="cheque"       <?= $filtreMode==='cheque'?'selected':'' ?>>Chèque</option>
            </select>
            <span class="text-muted small">Enregistré le :</span>
            <input type="date" name="date_enr" value="<?= htmlspecialchars($filtreDateEnr) ?>" class="form-control form-control-sm" style="max-width:150px;border-radius:8px;" onchange="this.form.mois_enr.value='';this.form.annee_enr.value='';this.form.submit()">
            <input type="month" name="mois_enr" value="<?= htmlspecialchars($filtreMoisEnr) ?>" class="form-control form-control-sm" style="max-width:140px;border-radius:8px;" onchange="this.form.date_enr.value='';this.form.annee_enr.value='';this.form.submit()">
            <input type="number" name="annee_enr" value="<?= htmlspecialchars($filtreAnneeEnr) ?>" placeholder="Année" min="2000" max="2100" class="form-control form-control-sm" style="max-width:100px;border-radius:8px;" onchange="this.form.date_enr.value='';this.form.mois_enr.value='';this.form.submit()">
            <?php if ($search || $filtreMode || $filtreDateEnr || $filtreMoisEnr || $filtreAnneeEnr): ?>
            <a href="liste_encaissements.php" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;"><i class="fa fa-times"></i></a>
            <?php endif; ?>
            <div class="ms-auto text-muted small"><?= $totalRows ?> résultat<?= $totalRows>1?'s':'' ?></div>
        </form>
    </div>

</div><!-- /top-fixed -->
<div class="bottom-scroll">

<?php if (empty($encaissements)): ?>
<div class="text-center text-muted py-5"><i class="fa fa-inbox fa-3x mb-3 d-block" style="opacity:.2;"></i>Aucun encaissement trouvé.</div>
<?php else: ?>

<table class="table mb-0 tbl-full">
    <thead><tr>
        <th style="width:14%;">Enregistré le</th>
        <th style="width:18%;">Locataire</th>
        <th style="width:18%;">Maison</th>
        <th style="width:12%;">Période</th>
        <th class="text-end" style="width:12%;">Montant</th>
        <th style="width:10%;">Mode</th>
        <th style="width:10%;">Référence</th>
        <th class="text-center" style="width:6%;">Quittance</th>
    </tr></thead>
    <tbody>
    <?php foreach ($encaissements as $e): ?>
    <tr>
        <td class="text-muted small"><?= date('d/m/Y H:i', strtotime($e['date_encaissement'])) ?></td>
        <td class="fw-semibold" style="color:#2d3a55;"><?= htmlspecialchars($e['nom_locataire']) ?></td>
        <td class="text-muted small"><?= htmlspecialchars($e['nom_maison']) ?></td>
        <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($e['periode_concernee'] ?? '—') ?></span></td>
        <td class="text-end fw-bold" style="color:var(--green);"><?= number_format($e['montant_recu'],0,',',' ') ?> <small class="text-muted fw-normal">FCFA</small></td>
        <td><span class="badge bg-info text-dark" style="font-size:11px;"><?= ucfirst(str_replace('_',' ',$e['mode_paiement'] ?? '')) ?></span></td>
        <td class="text-muted small"><?= htmlspecialchars($e['reference_recu'] ?? '—') ?></td>
        <td class="text-center"><a href="quittance.php?id=<?= $e['id'] ?>" class="btn btn-sm btn-outline-dark" style="border-radius:6px;" title="Imprimer"><i class="fa fa-print"></i></a></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<div class="d-flex justify-content-between align-items-center px-4 py-3 border-top" style="background:#f8faff;">
    <small class="text-muted">Page <?= $page ?> / <?= $totalPages ?> — <?= $totalRows ?> résultat<?= $totalRows>1?'s':'' ?></small>
    <div class="pag">
        <a href="<?= buildUrlEnc(['page'=>$page-1]) ?>" class="<?= $page<=1?'off':'' ?>"><i class="fa fa-chevron-left" style="font-size:10px;"></i></a>
        <?php
        $start=max(1,$page-2); $end=min($totalPages,$page+2);
        if ($start>1) echo '<span style="border:none;width:auto;color:#aab;">…</span>';
        for ($i=$start;$i<=$end;$i++):
        ?>
        <?php if ($i===$page): ?><span class="cur"><?= $i ?></span>
        <?php else: ?><a href="<?= buildUrlEnc(['page'=>$i]) ?>"><?= $i ?></a>
        <?php endif; endfor;
        if ($end<$totalPages) echo '<span style="border:none;width:auto;color:#aab;">…</span>';
        ?>
        <a href="<?= buildUrlEnc(['page'=>$page+1]) ?>" class="<?= $page>=$totalPages?'off':'' ?>"><i class="fa fa-chevron-right" style="font-size:10px;"></i></a>
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
var exportRowsEnc = <?= json_encode($exportRowsEnc, JSON_UNESCAPED_UNICODE) ?>;
var agenceInfoEnc = {
    nom: <?= json_encode($entrepriseExport['nom_entreprise'], JSON_UNESCAPED_UNICODE) ?>,
    adresse: <?= json_encode($entrepriseExport['adresse_siege'] ?? '', JSON_UNESCAPED_UNICODE) ?>,
    tel: <?= json_encode($entrepriseExport['contact_telephone'] ?? '', JSON_UNESCAPED_UNICODE) ?>,
    email: <?= json_encode($entrepriseExport['contact_email'] ?? '', JSON_UNESCAPED_UNICODE) ?>,
    activites: <?= json_encode(array_values($activitesExport), JSON_UNESCAPED_UNICODE) ?>,
    footerLines: <?= json_encode($footerLinesExport, JSON_UNESCAPED_UNICODE) ?>,
    logo: <?= (!empty($entrepriseExport['logo_url']) && file_exists('../uploads/' . $entrepriseExport['logo_url']))
        ? json_encode('../uploads/' . $entrepriseExport['logo_url'], JSON_UNESCAPED_UNICODE)
        : 'null' ?>
};

function loadImageAsDataURLEnc(url) {
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
    var rows = exportRowsEnc.map(function(r) {
        return {
            'Enregistré le': r.date,
            'Locataire': r.locataire,
            'Maison': r.maison,
            'Période concernée': r.periode,
            'Montant (FCFA)': r.montant_num,
            'Mode de paiement': r.mode,
            'Référence': r.reference
        };
    });
    var ws = XLSX.utils.json_to_sheet(rows);
    ws['!cols'] = [{wch:18},{wch:22},{wch:22},{wch:16},{wch:14},{wch:16},{wch:14}];
    var wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Encaissements');
    XLSX.writeFile(wb, 'Liste_Encaissements.xlsx');
}

function fmtNumPdf(n) {
    // jsPDF (police standard) ne sait pas afficher l'espace fine insécable
    // que produit Intl.NumberFormat('fr-FR') pour les milliers : le texte
    // apparaît alors éclaté lettre par lettre. On force un espace normal.
    return Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
}

function exportToPDF() {
    loadImageAsDataURLEnc(agenceInfoEnc.logo).then(function(logo) {
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
    (agenceInfoEnc.activites || []).forEach(function(act, i) {
        doc.text(act, pageW - 14, 10 + i * 3.4, { align: 'right' });
    });

    doc.setFillColor(240, 173, 0);
    doc.rect(14, 27, pageW - 28, 1.1, 'F');
    doc.setFillColor(marine[0], marine[1], marine[2]);
    doc.rect(14, 28.1, pageW - 28, 1.1, 'F');

    doc.setFontSize(12.5);
    doc.setFont(undefined, 'bold');
    doc.setTextColor(30, 30, 30);
    doc.text('Liste des Encaissements', 14, 36);

    var total = exportRowsEnc.reduce(function(s, r) { return s + r.montant_num; }, 0);
    doc.setFontSize(9);
    doc.setFont(undefined, 'normal');
    doc.setTextColor(100, 100, 100);
    doc.text(
        exportRowsEnc.length + ' paiement' + (exportRowsEnc.length > 1 ? 's' : '') + ' — Total : ' + fmtNumPdf(total) + ' FCFA',
        14, 42
    );
    doc.text('Généré le ' + new Date().toLocaleDateString('fr-FR'), pageW - 14, 42, { align: 'right' });

    doc.autoTable({
        startY: 47,
        head: [['Enregistré le', 'Locataire', 'Maison', 'Période', 'Montant', 'Mode', 'Référence']],
        body: exportRowsEnc.map(function(r) {
            return [r.date, r.locataire, r.maison, r.periode, r.montant, r.mode, r.reference];
        }),
        theme: 'striped',
        styles: { fontSize: 9, cellPadding: 3, valign: 'middle' },
        headStyles: { fillColor: marine, textColor: 255, fontStyle: 'bold' },
        alternateRowStyles: { fillColor: [245, 247, 252] },
        margin: { bottom: 8 + Math.max(0, (agenceInfoEnc.footerLines || []).length - 1) * 3.3 + 6 },
        columnStyles: {
            4: { halign: 'right' }
        },
        didDrawPage: function(data) {
            var lines = agenceInfoEnc.footerLines || [];
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

    doc.save('Liste_Encaissements.pdf');
    });
}
</script>
</body>
</html>
