<?php
session_start();
require_once('../config/db.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$entreprise = $pdo->query("SELECT * FROM settings WHERE id = 1")->fetch()
    ?: ['nom_entreprise'=>'BailManager','logo_url'=>'','taux_commission'=>0,
        'contact_telephone'=>'','contact_email'=>'','adresse_siege'=>''];

$annee = date('Y');
$mois  = date('m');
$today = date('Y-m-d');

// ── KPI ───────────────────────────────────────────────────────────────────────
$q = fn(string $sql, array $p=[]) => (float)$pdo->prepare($sql) && false ?: (function($pdo,$sql,$p){ $s=$pdo->prepare($sql); $s->execute($p); return (float)$s->fetchColumn(); })($pdo,$sql,$p);

$s = $pdo->prepare("SELECT COALESCE(SUM(montant_recu),0) FROM encaissements WHERE YEAR(date_encaissement)=?");
$s->execute([$annee]); $recettesAnnuel = (float)$s->fetchColumn();

$s = $pdo->prepare("SELECT COALESCE(SUM(montant_recu),0) FROM encaissements WHERE YEAR(date_encaissement)=? AND MONTH(date_encaissement)=?");
$s->execute([$annee,$mois]); $recettesMois = (float)$s->fetchColumn();

$total_maisons    = (int)$pdo->query("SELECT COUNT(*) FROM maisons")->fetchColumn();
$maisons_occupees = (int)$pdo->query("SELECT COUNT(*) FROM contrats WHERE statut_contrat='actif'")->fetchColumn();
$maisonsLibres    = $total_maisons - $maisons_occupees;

$locatairesActifs = (int)$pdo->query("SELECT COUNT(DISTINCT locataire_id) FROM contrats WHERE statut_contrat='actif'")->fetchColumn();

$stmtImp = $pdo->prepare("SELECT COUNT(*) FROM contrats WHERE date_prochain_loyer < :today AND statut_contrat='actif'");
$stmtImp->execute([':today' => $today]);
$impayes = (int)$stmtImp->fetchColumn();

$totalAttendu = (float)$pdo->query("SELECT COALESCE(SUM(loyer_mensuel),0) FROM contrats WHERE statut_contrat='actif'")->fetchColumn();
$tauxRecouvrement = $totalAttendu > 0 ? min(100, round($recettesMois / $totalAttendu * 100, 1)) : 0;
$tauxNonRecouvre  = max(0, 100 - $tauxRecouvrement);

// Notifications topnav
$countRes = (int)$pdo->query("SELECT COUNT(*) FROM reservations WHERE statut='en_attente'")->fetchColumn();
$countMsg = (int)$pdo->query("SELECT COUNT(*) FROM messages WHERE statut='non_lu'")->fetchColumn();

// ── Alertes loyers J-7 ─────────────────────────────────────────────────────────
$s = $pdo->prepare(
    "SELECT c.date_prochain_loyer, c.loyer_mensuel, l.nom AS locataire, m.designation AS maison
     FROM contrats c
     JOIN locataires l ON c.locataire_id = l.id
     JOIN maisons m    ON c.maison_id    = m.id
     WHERE c.statut_contrat='actif'
       AND c.date_prochain_loyer BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
     ORDER BY c.date_prochain_loyer ASC LIMIT 6");
$s->execute(); $alertesLoyers = $s->fetchAll();

// ── Contrats expirant J-30 ─────────────────────────────────────────────────────
$s = $pdo->prepare(
    "SELECT c.date_fin, c.loyer_mensuel,
            l.nom AS locataire, m.designation AS maison,
            DATEDIFF(c.date_fin, CURDATE()) AS jours_restants
     FROM contrats c
     JOIN locataires l ON c.locataire_id = l.id
     JOIN maisons m    ON c.maison_id    = m.id
     WHERE c.statut_contrat='actif'
       AND c.date_fin BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
     ORDER BY c.date_fin ASC LIMIT 6");
$s->execute(); $contratsExpirants = $s->fetchAll();

// ── Graphique mensuel (12 mois année en cours) ─────────────────────────────────
$s = $pdo->prepare("SELECT MONTH(date_encaissement) AS m, SUM(montant_recu) AS t FROM encaissements WHERE YEAR(date_encaissement)=? GROUP BY MONTH(date_encaissement)");
$s->execute([$annee]);
$mMap = array_column($s->fetchAll(), 't', 'm');
$monthlyData = [];
for ($i=1;$i<=12;$i++) $monthlyData[] = (float)($mMap[$i] ?? 0);

// ── Graphique évolution 6 derniers mois (vs 6 mois précédents) ────────────────
$trend6Labels = $trend6Current = $trend6Previous = [];
for ($i = 5; $i >= 0; $i--) {
    $d = new DateTime("first day of -$i month");
    $trend6Labels[] = $d->format('M Y');
    $s = $pdo->prepare("SELECT COALESCE(SUM(montant_recu),0) FROM encaissements WHERE YEAR(date_encaissement)=? AND MONTH(date_encaissement)=?");
    $s->execute([$d->format('Y'), $d->format('n')]); $trend6Current[] = (float)$s->fetchColumn();
    $dp = new DateTime("first day of -".($i+12)." month");
    $s->execute([$dp->format('Y'), $dp->format('n')]); $trend6Previous[] = (float)$s->fetchColumn();
}

// ── Derniers paiements ─────────────────────────────────────────────────────────
$limit1    = 6;
$pagePay   = max(1,(int)($_GET['p_pay'] ?? 1));
$filtreDate= trim($_GET['filtre_date_pay'] ?? '');
$wherePay  = $filtreDate ? "WHERE DATE(e.date_encaissement)=:df" : "";

$sc = $pdo->prepare("SELECT COUNT(*) FROM encaissements e $wherePay");
if ($filtreDate) $sc->bindValue(':df', $filtreDate);
$sc->execute(); $totalRowsPay = (int)$sc->fetchColumn();
$pagesPay = max(1, (int)ceil($totalRowsPay / $limit1));
$pagePay  = min($pagePay, $pagesPay);
$offsetPay = ($pagePay-1)*$limit1;

$sp = $pdo->prepare(
    "SELECT e.reference_recu, e.montant_recu, e.date_encaissement, e.mode_paiement, e.periode_concernee,
            l.nom AS nom_locataire, m.designation AS nom_maison
     FROM encaissements e
     LEFT JOIN contrats   c ON e.contrat_id    = c.id
     LEFT JOIN locataires l ON c.locataire_id  = l.id
     LEFT JOIN maisons    m ON c.maison_id     = m.id
     $wherePay
     ORDER BY e.date_encaissement DESC
     LIMIT :offset, :lim");
if ($filtreDate) $sp->bindValue(':df', $filtreDate);
$sp->bindValue(':offset', $offsetPay, PDO::PARAM_INT);
$sp->bindValue(':lim', $limit1, PDO::PARAM_INT);
$sp->execute(); $paiements = $sp->fetchAll();

// ── Données enrichissement ─────────────────────────────────────────────────────
$totalBailleurs  = (int)  $pdo->query("SELECT COUNT(*) FROM bailleurs")->fetchColumn();
$soldeBailleurs  = (float)$pdo->query("SELECT COALESCE(SUM(solde_du_bailleur),0) FROM bailleurs")->fetchColumn();
$cautionsTotales = (float)$pdo->query("SELECT COALESCE(SUM(depot_garantie),0) FROM contrats WHERE statut_contrat='actif'")->fetchColumn();
$revenuPotentiel = (float)$pdo->query("SELECT COALESCE(SUM(loyer),0) FROM maisons WHERE statut='disponible'")->fetchColumn();

// Impayés détaillés avec contact
$stmtTopImp = $pdo->prepare("
    SELECT c.date_prochain_loyer, c.loyer_mensuel,
           l.nom AS locataire, l.telephone1,
           m.designation AS maison,
           DATEDIFF(CURDATE(), c.date_prochain_loyer) AS jours_retard
    FROM contrats c
    JOIN locataires l ON c.locataire_id = l.id
    JOIN maisons m    ON c.maison_id    = m.id
    WHERE c.statut_contrat='actif' AND c.date_prochain_loyer < CURDATE()
    ORDER BY jours_retard DESC LIMIT 10
");
$stmtTopImp->execute();
$topImpayes = $stmtTopImp->fetchAll();

// Modes de paiement du mois
$stmtModes = $pdo->prepare("
    SELECT mode_paiement, COUNT(*) as nb, COALESCE(SUM(montant_recu),0) as total
    FROM encaissements
    WHERE YEAR(date_encaissement)=? AND MONTH(date_encaissement)=?
    GROUP BY mode_paiement ORDER BY total DESC
");
$stmtModes->execute([$annee, $mois]);
$modesPaiement = $stmtModes->fetchAll();
$modesLabels = array_map('ucfirst', array_column($modesPaiement, 'mode_paiement'));
$modesNb     = array_column($modesPaiement, 'nb');
$modesTotaux = array_column($modesPaiement, 'total');

// Top 5 maisons par revenu annuel
$stmtTopMaisons = $pdo->prepare("
    SELECT m.designation, COALESCE(SUM(e.montant_recu),0) AS revenu
    FROM maisons m
    LEFT JOIN contrats c     ON c.maison_id   = m.id
    LEFT JOIN encaissements e ON e.contrat_id  = c.id AND YEAR(e.date_encaissement) = ?
    GROUP BY m.id, m.designation
    ORDER BY revenu DESC LIMIT 5
");
$stmtTopMaisons->execute([$annee]);
$topMaisons = $stmtTopMaisons->fetchAll();
$maxRevenu  = $topMaisons ? max(array_column($topMaisons, 'revenu')) : 1;

// ── Logs admin ─────────────────────────────────────────────────────────────────
$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
$logs = [];
if ($isAdmin) {
    $limit2   = 5;
    $pageAdm  = max(1,(int)($_GET['p_adm'] ?? 1));
    $totalAdm = (int)$pdo->query("SELECT COUNT(*) FROM logs")->fetchColumn();
    $pagesAdm = max(1,(int)ceil($totalAdm / $limit2));
    $pageAdm  = min($pageAdm, $pagesAdm);
    $offsetAdm = ($pageAdm-1)*$limit2;
    $stmtLogs = $pdo->prepare("SELECT l.*, u.nom_complet FROM logs l LEFT JOIN users u ON l.utilisateur_id=u.id ORDER BY l.date_action DESC LIMIT :lim OFFSET :off");
    $stmtLogs->bindValue(':lim', $limit2,    PDO::PARAM_INT);
    $stmtLogs->bindValue(':off', $offsetAdm, PDO::PARAM_INT);
    $stmtLogs->execute();
    $logs = $stmtLogs->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord — BailManager</title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/fontawesome/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <script src="../js/chart.min.js"></script>
    <style>
        :root { --marine: #002147; --red: #e53e3e; --green: #059669; --amber: #d97706; }

        html, body { overflow-x: hidden; max-width: 100%; }
        .main-content { background: #f4f7fe; min-height: 100vh; padding: 24px 28px; overflow-x: hidden; }
        .table-card, .chart-card { max-width: 100%; }
        .table-responsive-fix { overflow-x: auto; max-width: 100%; }
        table { width: 100%; table-layout: auto; }
        td, th { overflow: hidden; text-overflow: ellipsis; }

        /* ── KPI cards ── */
        .kpi-card {
            background: #fff;
            border-radius: 16px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,.06);
            border: 1px solid #e8ecf4;
            height: 100%;
            transition: transform .2s ease, box-shadow .2s ease;
            text-decoration: none;
            color: inherit;
        }
        .kpi-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,.1); color: inherit; }
        .kpi-icon {
            width: 52px; height: 52px; border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px; flex-shrink: 0;
        }
        .kpi-val  { font-size: 1.5rem; font-weight: 800; line-height: 1.1; }
        .kpi-lbl  { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: #8896b0; margin-top: 2px; }
        .kpi-sub  { font-size: 11px; color: #aab; margin-top: 1px; }

        /* ── Quick actions ── */
        .quick-btn {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 9px 18px;
            border-radius: 10px;
            font-size: 13px; font-weight: 600;
            text-decoration: none;
            transition: all .15s ease;
            border: 1.5px solid;
            white-space: nowrap;
        }
        .quick-btn.marine { background: var(--marine); border-color: var(--marine); color: #fff; }
        .quick-btn.marine:hover { background: #003580; color: #fff; }
        .quick-btn.outline { background: #fff; border-color: #dde3f0; color: #4a5568; }
        .quick-btn.outline:hover { border-color: var(--marine); color: var(--marine); background: #f0f4ff; }
        .quick-btn.green { background: var(--green); border-color: var(--green); color: #fff; }
        .quick-btn.green:hover { background: #047857; color: #fff; }
        .quick-btn.amber { background: var(--amber); border-color: var(--amber); color: #fff; }
        .quick-btn.amber:hover { background: #b45309; color: #fff; }

        /* ── Section title ── */
        .section-title {
            font-size: 13px; font-weight: 700; text-transform: uppercase;
            letter-spacing: .07em; color: #8896b0; margin-bottom: 12px;
            display: flex; align-items: center; gap: 8px;
        }
        .section-title::after { content:''; flex:1; height:1px; background:#e8ecf4; }

        /* ── Alert cards ── */
        .alert-card {
            background: #fff; border-radius: 12px;
            padding: 12px 14px;
            border: 1px solid #e8ecf4;
            border-left: 4px solid;
            box-shadow: 0 1px 6px rgba(0,0,0,.04);
        }
        .alert-card.amber-border { border-left-color: var(--amber); }
        .alert-card.red-border   { border-left-color: var(--red); }
        .alert-card.indigo-border { border-left-color: #6366f1; }

        /* ── Chart cards ── */
        .chart-card {
            background: #fff; border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,.06);
            border: 1px solid #e8ecf4;
            overflow: hidden;
        }
        .chart-card-header {
            padding: 16px 20px 12px;
            border-bottom: 1px solid #f0f3fa;
            display: flex; align-items: center; justify-content: space-between;
        }
        .chart-card-header .title { font-size: 13px; font-weight: 700; color: #2d3a55; }
        .chart-card-header .sub   { font-size: 11px; color: #8896b0; margin-top: 2px; }
        .chart-card-body { padding: 16px 20px; }

        /* Gauge center text */
        .gauge-wrap { position: relative; display: flex; align-items: center; justify-content: center; }
        .gauge-center {
            position: absolute;
            text-align: center;
            pointer-events: none;
        }
        .gauge-center .val { font-size: 22px; font-weight: 800; color: var(--marine); line-height: 1; }
        .gauge-center .lbl { font-size: 10px; color: #8896b0; font-weight: 600; }

        /* ── Table cards ── */
        .table-card {
            background: #fff; border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,.06);
            border: 1px solid #e8ecf4; overflow: hidden;
        }
        .table-card thead th {
            background: #f8faff; color: #6b7a99;
            font-size: 11px; font-weight: 700; text-transform: uppercase;
            letter-spacing: .05em; padding: 11px 14px;
            border-bottom: 1px solid #e8ecf4;
        }
        .table-card tbody td { padding: 11px 14px; border-bottom: 1px solid #f0f3fa; vertical-align: middle; font-size: 13px; }
        .table-card tbody tr:last-child td { border-bottom: none; }
        .table-card tbody tr:hover td { background: #f8faff; }

        /* Log item */
        .log-item { padding: 10px 16px; border-bottom: 1px solid #f0f3fa; }
        .log-item:last-child { border-bottom: none; }

        /* Pagination mini */
        .pag-mini { display: flex; align-items: center; gap: 4px; }
        .pag-mini a, .pag-mini span {
            display: inline-flex; align-items: center; justify-content: center;
            width: 28px; height: 28px; border-radius: 7px;
            font-size: 12px; font-weight: 600; text-decoration: none;
            border: 1.5px solid #e0e6f0; color: #6b7a99;
        }
        .pag-mini a:hover { border-color: var(--marine); color: var(--marine); }
        .pag-mini span.active { background: var(--marine); border-color: var(--marine); color: #fff; }
        .pag-mini a.disabled { opacity: .35; pointer-events: none; }

        /* ── KPI row 2 ── */
        .kpi2-card { background:#fff; border-radius:12px; padding:12px 14px; display:flex; align-items:center; gap:10px; box-shadow:0 1px 6px rgba(0,0,0,.05); border:1px solid #e8ecf4; height:100%; transition:transform .18s; text-decoration:none; color:inherit; }
        .kpi2-card:hover { transform:translateY(-2px); color:inherit; }
        .kpi2-icon { width:36px; height:36px; border-radius:9px; display:flex; align-items:center; justify-content:center; font-size:14px; flex-shrink:0; }
        .kpi2-val  { font-size:.95rem; font-weight:800; line-height:1.1; }
        .kpi2-lbl  { font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:.05em; color:#8896b0; }
        .kpi2-sub  { font-size:10px; color:#bbc; }

        /* ── Impayés table ── */
        .imp-badge-urgent { background:#fee2e2; color:#991b1b; padding:2px 8px; border-radius:20px; font-size:10px; font-weight:700; }
        .imp-badge-retard { background:#fef3c7; color:#92400e; padding:2px 8px; border-radius:20px; font-size:10px; font-weight:700; }

        /* ── Top maisons ── */
        .top-bar-wrap { background:#f0f3fa; border-radius:4px; height:7px; overflow:hidden; }
        .top-bar-fill { height:100%; border-radius:4px; background:linear-gradient(90deg,#002147,#0052a8); transition:width .6s ease; }

        /* ── Mode paiement ── */
        .mode-row { display:flex; align-items:center; justify-content:space-between; padding:6px 0; border-bottom:1px solid #f0f3fa; font-size:12px; }
        .mode-row:last-child { border-bottom:none; }
        .mode-dot  { width:10px; height:10px; border-radius:50%; flex-shrink:0; }

        @media print {
            .app-sidebar, .app-topbar, .quick-actions, .no-print { display: none !important; }
            body { padding-top: 0 !important; }
            .main-content { margin-left: 0 !important; width: 100% !important; padding: 0 !important; }
        }
    </style>
</head>
<body>
<?php include('../includes/sidebar.php'); ?>

<div class="main-content">

    <!-- ── Header ──────────────────────────────────────────────────────── -->
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div class="d-flex align-items-center gap-3">
            <div>
                <p class="text-muted small mb-0 mt-1"><?= date('l d F Y') ?></p>
            </div>
        </div>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <button class="quick-btn outline" data-bs-toggle="modal" data-bs-target="#companyInfoModal">
                <i class="fa fa-building"></i><?= htmlspecialchars($entreprise['nom_entreprise']) ?>
            </button>
            <a href="rapports.php" class="quick-btn outline no-print">
                <i class="fa fa-file-alt"></i>Rapports
            </a>
            <?php if ($isAdmin): ?>
            <a href="../php/backup.php" class="quick-btn outline no-print">
                <i class="fa fa-database"></i>Sauvegarde
            </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (isset($_GET['backup']) && $_GET['backup'] === 'success'): ?>
    <div class="alert alert-success alert-dismissible fade show mb-4">
        <strong>Succès !</strong> Sauvegarde créée : <?= htmlspecialchars($_GET['file'] ?? '') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- ── 5 KPI cards ─────────────────────────────────────────────────── -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-4 col-lg">
            <a href="encaissements.php" class="kpi-card" title="Voir les encaissements">
                <div class="kpi-icon" style="background:#eef2fb;">
                    <i class="fa fa-chart-line" style="color:var(--marine);"></i>
                </div>
                <div>
                    <div class="kpi-val" style="color:var(--marine);"><?= number_format($recettesAnnuel,0,',',' ') ?></div>
                    <div class="kpi-lbl">CA <?= $annee ?></div>
                    <div class="kpi-sub">FCFA</div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-4 col-lg">
            <a href="encaissements.php" class="kpi-card" title="Voir les encaissements">
                <div class="kpi-icon" style="background:#d1fae5;">
                    <i class="fa fa-wallet" style="color:var(--green);"></i>
                </div>
                <div>
                    <div class="kpi-val" style="color:var(--green);"><?= number_format($recettesMois,0,',',' ') ?></div>
                    <div class="kpi-lbl">Recettes <?= date('M') ?></div>
                    <div class="kpi-sub">FCFA</div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-4 col-lg">
            <a href="maisons.php?statut=occupe" class="kpi-card" title="Voir les maisons occupées">
                <div class="kpi-icon" style="background:#ede9fe;">
                    <i class="fa fa-home" style="color:#6366f1;"></i>
                </div>
                <div>
                    <div class="kpi-val" style="color:#6366f1;"><?= $maisons_occupees ?><span style="font-size:.9rem;font-weight:400;color:#aab;"> / <?= $total_maisons ?></span></div>
                    <div class="kpi-lbl">Occupation</div>
                    <div class="kpi-sub">maisons louées</div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-4 col-lg">
            <a href="locataires.php" class="kpi-card" title="Voir les locataires">
                <div class="kpi-icon" style="background:#e0f2fe;">
                    <i class="fa fa-users" style="color:#0ea5e9;"></i>
                </div>
                <div>
                    <div class="kpi-val" style="color:#0ea5e9;"><?= $locatairesActifs ?></div>
                    <div class="kpi-lbl">Locataires actifs</div>
                    <div class="kpi-sub">contrats en cours</div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-4 col-lg">
            <a href="encaissements.php" class="kpi-card" title="Voir les loyers en retard">
                <div class="kpi-icon" style="background:<?= $impayes > 0 ? '#fee2e2' : '#d1fae5' ?>;">
                    <i class="fa fa-exclamation-circle" style="color:<?= $impayes > 0 ? 'var(--red)' : 'var(--green)' ?>;"></i>
                </div>
                <div>
                    <div class="kpi-val" style="color:<?= $impayes > 0 ? 'var(--red)' : 'var(--green)' ?>;"><?= $impayes ?></div>
                    <div class="kpi-lbl">Retards</div>
                    <div class="kpi-sub">loyers en retard</div>
                </div>
            </a>
        </div>
    </div>

    <!-- ── KPI row 2 — Bailleurs & finance ──────────────────────────────── -->
    <div class="row g-2 mb-4">
        <div class="col-6 col-md-3">
            <a href="bailleurs.php" class="kpi2-card" title="Voir les bailleurs">
                <div class="kpi2-icon" style="background:#f0fdf4;"><i class="fa fa-address-book" style="color:#059669;"></i></div>
                <div>
                    <div class="kpi2-val" style="color:#059669;"><?= $totalBailleurs ?></div>
                    <div class="kpi2-lbl">Bailleurs</div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="compte_bailleur.php" class="kpi2-card" title="Voir le compte courant bailleurs">
                <div class="kpi2-icon" style="background:#fef9c3;"><i class="fa fa-scale-balanced" style="color:#ca8a04;"></i></div>
                <div>
                    <div class="kpi2-val" style="color:#ca8a04;"><?= number_format($soldeBailleurs,0,',',' ') ?></div>
                    <div class="kpi2-lbl">Solde bailleurs</div>
                    <div class="kpi2-sub">FCFA à reverser</div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="gestion_cautions.php" class="kpi2-card" title="Voir la gestion des cautions">
                <div class="kpi2-icon" style="background:#ede9fe;"><i class="fa fa-shield-halved" style="color:#7c3aed;"></i></div>
                <div>
                    <div class="kpi2-val" style="color:#7c3aed;"><?= number_format($cautionsTotales,0,',',' ') ?></div>
                    <div class="kpi2-lbl">Cautions</div>
                    <div class="kpi2-sub">FCFA déposées</div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="maisons.php?statut=disponible" class="kpi2-card" title="Voir les maisons disponibles">
                <div class="kpi2-icon" style="background:#e0f2fe;"><i class="fa fa-house-circle-check" style="color:#0284c7;"></i></div>
                <div>
                    <div class="kpi2-val" style="color:#0284c7;"><?= number_format($revenuPotentiel,0,',',' ') ?></div>
                    <div class="kpi2-lbl">Revenu potentiel</div>
                    <div class="kpi2-sub">maisons libres/mois</div>
                </div>
            </a>
        </div>
    </div>

    <!-- ── Raccourcis rapides ───────────────────────────────────────────── -->
    <div class="quick-actions d-flex gap-2 flex-wrap mb-4 no-print">
        <a href="encaissements.php" class="quick-btn marine"><i class="fa fa-plus"></i>Encaissement</a>
        <a href="contrats.php"      class="quick-btn green"><i class="fa fa-file-contract"></i>Nouveau contrat</a>
        <a href="locataires.php"    class="quick-btn outline"><i class="fa fa-user-plus"></i>Ajouter locataire</a>
        <a href="maisons.php"       class="quick-btn outline"><i class="fa fa-home"></i>Maisons</a>
        <a href="liste_reservations.php" class="quick-btn amber">
            <i class="fa fa-calendar-check"></i>Réservations
            <?php if ($countRes > 0): ?><span style="background:rgba(255,255,255,.3);border-radius:10px;padding:0 6px;font-size:11px;"><?= $countRes ?></span><?php endif; ?>
        </a>
        <a href="liste_messages.php" class="quick-btn outline">
            <i class="fa fa-envelope"></i>Messages
            <?php if ($countMsg > 0): ?><span style="background:var(--red);color:#fff;border-radius:10px;padding:0 6px;font-size:11px;"><?= $countMsg ?></span><?php endif; ?>
        </a>
    </div>

    <!-- ── Alertes ─────────────────────────────────────────────────────── -->
    <?php if (!empty($alertesLoyers) || !empty($contratsExpirants)): ?>
    <div class="row g-3 mb-4">

        <?php if (!empty($alertesLoyers)): ?>
        <div class="col-lg-6">
            <div class="section-title"><i class="fa fa-bell text-warning"></i>Loyers dus dans 7 jours <span class="badge rounded-pill" style="background:#fef3c7;color:#92400e;font-size:10px;"><?= count($alertesLoyers) ?></span></div>
            <div class="d-flex flex-column gap-2">
                <?php foreach ($alertesLoyers as $a):
                    $jr = (int)((strtotime($a['date_prochain_loyer']) - strtotime('today')) / 86400);
                    $urgent = $jr <= 2;
                ?>
                <div class="alert-card <?= $urgent ? 'red-border' : 'amber-border' ?>">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="fw-bold small" style="color:#2d3a55;"><?= htmlspecialchars($a['locataire']) ?></div>
                            <div class="text-muted" style="font-size:11px;"><?= htmlspecialchars($a['maison']) ?></div>
                        </div>
                        <div class="text-end">
                            <span class="badge rounded-pill <?= $urgent ? 'bg-danger' : '' ?>" style="<?= !$urgent ? 'background:#fef3c7;color:#92400e;' : '' ?>;font-size:11px;">
                                <?= $jr === 0 ? "Aujourd'hui" : "J-$jr" ?>
                            </span>
                            <div class="fw-bold" style="font-size:12px;color:var(--green);margin-top:2px;"><?= number_format($a['loyer_mensuel'],0,',',' ') ?> FCFA</div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($contratsExpirants)): ?>
        <div class="col-lg-6">
            <div class="section-title"><i class="fa fa-calendar-xmark" style="color:#6366f1;"></i>Contrats expirant dans 30 jours <span class="badge rounded-pill" style="background:#ede9fe;color:#4338ca;font-size:10px;"><?= count($contratsExpirants) ?></span></div>
            <div class="d-flex flex-column gap-2">
                <?php foreach ($contratsExpirants as $c):
                    $urgent = $c['jours_restants'] <= 7;
                ?>
                <div class="alert-card indigo-border">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="fw-bold small" style="color:#2d3a55;"><?= htmlspecialchars($c['locataire']) ?></div>
                            <div class="text-muted" style="font-size:11px;"><?= htmlspecialchars($c['maison']) ?></div>
                        </div>
                        <div class="text-end">
                            <span class="badge rounded-pill <?= $urgent ? 'bg-danger' : '' ?>" style="<?= !$urgent ? 'background:#ede9fe;color:#4338ca;' : '' ?>;font-size:11px;">
                                <?= $c['jours_restants'] === 0 ? 'Expire auj.' : 'J-'.$c['jours_restants'] ?>
                            </span>
                            <div class="text-muted" style="font-size:11px;margin-top:2px;"><?= date('d/m/Y', strtotime($c['date_fin'])) ?></div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>
    <?php endif; ?>

    <!-- ── Graphiques ──────────────────────────────────────────────────── -->
    <div class="row g-3 mb-4">

        <!-- Bar chart mensuel -->
        <div class="col-lg-8">
            <div class="chart-card h-100">
                <div class="chart-card-header">
                    <div>
                        <div class="title"><i class="fa fa-chart-bar me-2" style="color:var(--marine);"></i>Encaissements mensuels</div>
                        <div class="sub"><?= $annee ?> — total : <?= number_format($recettesAnnuel,0,',',' ') ?> FCFA</div>
                    </div>
                </div>
                <div class="chart-card-body" style="height:260px;">
                    <canvas id="chartBar"></canvas>
                </div>
            </div>
        </div>

        <!-- Doughnuts empilés -->
        <div class="col-lg-4 d-flex flex-column gap-3">

            <!-- Occupation -->
            <div class="chart-card flex-1">
                <div class="chart-card-header">
                    <div>
                        <div class="title"><i class="fa fa-home me-2" style="color:#6366f1;"></i>Taux d'occupation</div>
                        <div class="sub"><?= $maisons_occupees ?> / <?= $total_maisons ?> maisons</div>
                    </div>
                </div>
                <div class="chart-card-body py-2 d-flex align-items-center justify-content-center gap-4">
                    <div class="gauge-wrap" style="width:100px;height:100px;">
                        <canvas id="chartOccupation" width="100" height="100"></canvas>
                        <div class="gauge-center">
                            <div class="val"><?= $total_maisons > 0 ? round($maisons_occupees/$total_maisons*100) : 0 ?>%</div>
                            <div class="lbl">occupé</div>
                        </div>
                    </div>
                    <div style="font-size:12px;">
                        <div class="mb-1 d-flex align-items-center gap-2">
                            <span style="width:10px;height:10px;border-radius:3px;background:#6366f1;display:inline-block;"></span>
                            Occupées : <strong><?= $maisons_occupees ?></strong>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span style="width:10px;height:10px;border-radius:3px;background:#e0e7ff;display:inline-block;"></span>
                            Libres : <strong><?= $maisonsLibres ?></strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recouvrement -->
            <div class="chart-card flex-1">
                <div class="chart-card-header">
                    <div>
                        <div class="title"><i class="fa fa-circle-check me-2" style="color:var(--green);"></i>Taux de recouvrement</div>
                        <div class="sub"><?= date('F Y') ?> — attendu : <?= number_format($totalAttendu,0,',',' ') ?> FCFA</div>
                    </div>
                </div>
                <div class="chart-card-body py-2 d-flex align-items-center justify-content-center gap-4">
                    <div class="gauge-wrap" style="width:100px;height:100px;">
                        <canvas id="chartRecouvrement" width="100" height="100"></canvas>
                        <div class="gauge-center">
                            <div class="val" style="color:<?= $tauxRecouvrement >= 80 ? 'var(--green)' : ($tauxRecouvrement >= 50 ? 'var(--amber)' : 'var(--red)') ?>;"><?= $tauxRecouvrement ?>%</div>
                            <div class="lbl">collecté</div>
                        </div>
                    </div>
                    <div style="font-size:12px;">
                        <div class="mb-1 d-flex align-items-center gap-2">
                            <span style="width:10px;height:10px;border-radius:3px;background:#059669;display:inline-block;"></span>
                            Collecté : <strong><?= number_format($recettesMois,0,',',' ') ?></strong>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span style="width:10px;height:10px;border-radius:3px;background:#fee2e2;display:inline-block;"></span>
                            Restant : <strong><?= number_format(max(0,$totalAttendu-$recettesMois),0,',',' ') ?></strong>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Graphique tendance 6 mois -->
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="chart-card">
                <div class="chart-card-header">
                    <div>
                        <div class="title"><i class="fa fa-arrow-trend-up me-2" style="color:var(--green);"></i>Tendance des encaissements</div>
                        <div class="sub">6 derniers mois vs même période l'année précédente</div>
                    </div>
                </div>
                <div class="chart-card-body" style="height:200px;">
                    <canvas id="chartTrend"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Top maisons + Modes de paiement ─────────────────────────────── -->
    <div class="row g-3 mb-4">

        <!-- Top 5 maisons -->
        <div class="col-lg-7">
            <div class="chart-card h-100">
                <div class="chart-card-header">
                    <div>
                        <div class="title"><i class="fa fa-trophy me-2" style="color:var(--amber);"></i>Top maisons par revenu</div>
                        <div class="sub"><?= $annee ?></div>
                    </div>
                </div>
                <div class="chart-card-body">
                    <?php if (empty($topMaisons) || $maxRevenu == 0): ?>
                    <div class="text-muted small text-center py-3">Aucune donnée pour <?= $annee ?>.</div>
                    <?php else: ?>
                    <?php foreach ($topMaisons as $k => $tm):
                        $pct = $maxRevenu > 0 ? round($tm['revenu'] / $maxRevenu * 100) : 0;
                        $rank = $k + 1;
                    ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <div class="d-flex align-items-center gap-2">
                                <span style="width:20px;height:20px;border-radius:6px;display:inline-flex;align-items:center;justify-content:center;font-size:10px;font-weight:800;background:<?= $rank===1?'#fef3c7':($rank===2?'#f1f5f9':'#f8faff') ?>;color:<?= $rank===1?'#92400e':($rank===2?'#475569':'#94a3b8') ?>;"><?= $rank ?></span>
                                <span style="font-size:12px;font-weight:600;color:#2d3a55;"><?= htmlspecialchars($tm['designation']) ?></span>
                            </div>
                            <span style="font-size:11px;font-weight:700;color:var(--green);"><?= number_format($tm['revenu'],0,',',' ') ?> <span style="font-weight:400;color:#aab;">FCFA</span></span>
                        </div>
                        <div class="top-bar-wrap">
                            <div class="top-bar-fill" style="width:<?= $pct ?>%;<?= $rank===1?'background:linear-gradient(90deg,#d97706,#f59e0b);':'' ?>"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Modes de paiement -->
        <div class="col-lg-5">
            <div class="chart-card h-100">
                <div class="chart-card-header">
                    <div>
                        <div class="title"><i class="fa fa-credit-card me-2" style="color:#8b5cf6;"></i>Modes de paiement</div>
                        <div class="sub"><?= date('F Y') ?></div>
                    </div>
                </div>
                <div class="chart-card-body">
                    <?php if (empty($modesPaiement)): ?>
                    <div class="text-muted small text-center py-3">Aucun paiement ce mois.</div>
                    <?php else: ?>
                    <div class="d-flex align-items-center gap-4">
                        <div style="position:relative;width:120px;height:120px;flex-shrink:0;">
                            <canvas id="chartModes" width="120" height="120"></canvas>
                        </div>
                        <div style="flex:1;min-width:0;">
                            <?php
                            $modeColors = ['#6366f1','#0ea5e9','#059669','#d97706','#e53e3e'];
                            foreach ($modesPaiement as $ki => $mp):
                                $mc = $modeColors[$ki % count($modeColors)];
                            ?>
                            <div class="mode-row">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="mode-dot" style="background:<?= $mc ?>;"></span>
                                    <span><?= ucfirst(str_replace('_',' ',$mp['mode_paiement'])) ?></span>
                                </div>
                                <div class="text-end">
                                    <span class="fw-bold" style="color:<?= $mc ?>;"><?= $mp['nb'] ?></span>
                                    <div style="font-size:10px;color:#aab;"><?= number_format($mp['total'],0,',',' ') ?> F</div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Derniers paiements + Logs ───────────────────────────────────── -->
    <div class="row g-3">

        <!-- Paiements -->
        <div class="col-lg-<?= $isAdmin ? '7' : '12' ?>">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <div class="section-title mb-0" style="flex:1;"><i class="fa fa-receipt" style="color:var(--marine);"></i>Derniers paiements</div>
                <form method="GET" class="d-flex gap-2 no-print ms-3">
                    <input type="date" name="filtre_date_pay" class="form-control form-control-sm" style="border-radius:8px;font-size:12px;"
                           value="<?= htmlspecialchars($filtreDate) ?>" onchange="this.form.submit()">
                    <?php if ($filtreDate): ?>
                    <a href="?" class="btn btn-outline-secondary btn-sm" style="border-radius:8px;"><i class="fa fa-times"></i></a>
                    <?php endif; ?>
                </form>
            </div>
            <div class="table-card">
              <div style="overflow-x:auto;">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Locataire</th>
                            <th>Maison</th>
                            <th>Référence</th>
                            <th>Montant</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($paiements)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">Aucun paiement trouvé.</td></tr>
                        <?php else: foreach ($paiements as $p): ?>
                        <tr>
                            <td class="fw-semibold" style="color:#2d3a55;"><?= htmlspecialchars($p['nom_locataire'] ?? '—') ?></td>
                            <td class="text-muted small"><?= htmlspecialchars($p['nom_maison'] ?? '—') ?></td>
                            <td class="text-muted small"><?= htmlspecialchars($p['reference_recu']) ?></td>
                            <td class="fw-bold" style="color:var(--green);"><?= number_format($p['montant_recu'],0,',',' ') ?> <small>FCFA</small></td>
                            <td class="text-muted small"><?= date('d/m/Y', strtotime($p['date_encaissement'])) ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
              </div>
                <?php if ($pagesPay > 1): ?>
                <div class="d-flex justify-content-between align-items-center px-3 py-2 border-top no-print" style="background:#f8faff;">
                    <small class="text-muted"><?= $totalRowsPay ?> paiement<?= $totalRowsPay>1?'s':'' ?></small>
                    <div class="pag-mini">
                        <?php $up = $_GET; ?>
                        <a href="?<?= http_build_query(array_merge($up,['p_pay'=>$pagePay-1])) ?>" class="<?= $pagePay<=1?'disabled':'' ?>"><i class="fa fa-chevron-left" style="font-size:9px;"></i></a>
                        <span class="active"><?= $pagePay ?></span>
                        <span>/<?= $pagesPay ?></span>
                        <a href="?<?= http_build_query(array_merge($up,['p_pay'=>$pagePay+1])) ?>" class="<?= $pagePay>=$pagesPay?'disabled':'' ?>"><i class="fa fa-chevron-right" style="font-size:9px;"></i></a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Logs admin -->
        <?php if ($isAdmin): ?>
        <div class="col-lg-5">
            <div class="section-title"><i class="fa fa-shield-halved" style="color:var(--red);"></i>Activité récente</div>
            <div class="table-card">
                <?php if (empty($logs)): ?>
                <div class="text-center text-muted py-4 small">Aucune activité enregistrée.</div>
                <?php else: foreach ($logs as $l): ?>
                <div class="log-item">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="fw-semibold small" style="color:var(--marine);"><?= htmlspecialchars($l['nom_complet'] ?? 'Système') ?></div>
                        <small class="text-muted" style="font-size:10px;white-space:nowrap;margin-left:8px;"><?= date('d/m H:i', strtotime($l['date_action'])) ?></small>
                    </div>
                    <div>
                        <span class="badge rounded-pill" style="background:#eef2fb;color:var(--marine);font-size:10px;font-weight:600;"><?= htmlspecialchars($l['action']) ?></span>
                    </div>
                    <p class="mb-0 text-muted text-truncate" style="font-size:11px;max-width:280px;"><?= htmlspecialchars($l['details']) ?></p>
                </div>
                <?php endforeach; endif; ?>
                <?php if ($isAdmin && isset($pagesAdm) && $pagesAdm > 1): ?>
                <div class="d-flex justify-content-between align-items-center px-3 py-2 border-top no-print" style="background:#f8faff;">
                    <small class="text-muted"><?= $totalAdm ?> entrée<?= $totalAdm>1?'s':'' ?></small>
                    <div class="pag-mini">
                        <a href="?<?= http_build_query(array_merge($_GET,['p_adm'=>$pageAdm-1])) ?>" class="<?= $pageAdm<=1?'disabled':'' ?>"><i class="fa fa-chevron-left" style="font-size:9px;"></i></a>
                        <span class="active"><?= $pageAdm ?></span>
                        <span>/<?= $pagesAdm ?></span>
                        <a href="?<?= http_build_query(array_merge($_GET,['p_adm'=>$pageAdm+1])) ?>" class="<?= $pageAdm>=$pagesAdm?'disabled':'' ?>"><i class="fa fa-chevron-right" style="font-size:9px;"></i></a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>

</div><!-- /main-content -->

<!-- ── Modal Entreprise ────────────────────────────────────────────────── -->
<div class="modal fade" id="companyInfoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius:16px;">
            <div class="modal-header text-white border-0" style="background:linear-gradient(135deg,#002147,#004080);border-radius:16px 16px 0 0;">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-white bg-opacity-10 d-flex align-items-center justify-content-center" style="width:42px;height:42px;">
                        <?php if (!empty($entreprise['logo_url'])): ?>
                        <img src="../uploads/<?= htmlspecialchars($entreprise['logo_url']) ?>" style="width:36px;height:36px;object-fit:contain;border-radius:50%;">
                        <?php else: ?>
                        <i class="fa fa-building text-white"></i>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0"><?= htmlspecialchars($entreprise['nom_entreprise']) ?></h5>
                        <small class="opacity-75">Informations de l'établissement</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 py-4">
                <div class="d-flex flex-column gap-3">
                    <div class="d-flex align-items-center gap-3">
                        <div style="width:36px;height:36px;border-radius:10px;background:#fee2e2;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="fa fa-percent" style="color:var(--red);font-size:14px;"></i>
                        </div>
                        <div>
                            <div class="text-muted" style="font-size:11px;">Commission de gestion</div>
                            <div class="fw-bold"><?= $entreprise['taux_commission'] ?> %</div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <div style="width:36px;height:36px;border-radius:10px;background:#eef2fb;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="fa fa-phone" style="color:var(--marine);font-size:14px;"></i>
                        </div>
                        <div>
                            <div class="text-muted" style="font-size:11px;">Téléphone</div>
                            <div class="fw-bold"><?= htmlspecialchars($entreprise['contact_telephone'] ?: 'Non renseigné') ?></div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <div style="width:36px;height:36px;border-radius:10px;background:#eef2fb;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="fa fa-envelope" style="color:var(--marine);font-size:14px;"></i>
                        </div>
                        <div>
                            <div class="text-muted" style="font-size:11px;">Email</div>
                            <div class="fw-bold"><?= htmlspecialchars($entreprise['contact_email'] ?: 'Non renseigné') ?></div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <div style="width:36px;height:36px;border-radius:10px;background:#eef2fb;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="fa fa-map-marker-alt" style="color:var(--marine);font-size:14px;"></i>
                        </div>
                        <div>
                            <div class="text-muted" style="font-size:11px;">Adresse</div>
                            <div class="fw-bold"><?= nl2br(htmlspecialchars($entreprise['adresse_siege'] ?: 'Non renseignée')) ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top bg-light px-4" style="border-radius:0 0 16px 16px;">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Fermer</button>
                <?php if ($isAdmin): ?>
                <a href="settings.php" class="btn px-4" style="background:var(--marine);color:#fff;">
                    <i class="fa fa-edit me-1"></i>Modifier
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="../js/bootstrap.bundle.min.js"></script>
<script>
const marine = '#002147', green = '#059669', amber = '#d97706', red = '#e53e3e';
const fmt = v => new Intl.NumberFormat('fr-FR').format(v);

// ── 1. Bar chart mensuel ──────────────────────────────────────────────────────
const barData = <?= json_encode($monthlyData) ?>;
new Chart(document.getElementById('chartBar'), {
    type: 'bar',
    data: {
        labels: ['Jan','Fév','Mar','Avr','Mai','Jun','Jul','Aoû','Sep','Oct','Nov','Déc'],
        datasets: [{
            label: 'Encaissements FCFA',
            data: barData,
            backgroundColor: barData.map((v,i) => i+1 == <?= (int)$mois ?> ? marine : 'rgba(0,45,114,.45)'),
            borderRadius: 7,
            borderSkipped: false,
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: { callbacks: { label: c => fmt(c.parsed.y) + ' FCFA' } }
        },
        scales: {
            y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,.04)' },
                 ticks: { callback: v => new Intl.NumberFormat('fr-FR',{notation:'compact'}).format(v) } },
            x: { grid: { display: false } }
        }
    }
});

// ── 2. Doughnut occupation ────────────────────────────────────────────────────
new Chart(document.getElementById('chartOccupation'), {
    type: 'doughnut',
    data: {
        labels: ['Occupées','Libres'],
        datasets: [{ data: [<?= $maisons_occupees ?>, <?= $maisonsLibres ?>],
            backgroundColor: ['#6366f1','#e0e7ff'], borderWidth: 0, hoverOffset: 4 }]
    },
    options: {
        responsive: false, cutout: '72%',
        plugins: { legend: { display: false },
            tooltip: { callbacks: { label: c => c.label + ' : ' + c.parsed } } }
    }
});

// ── 3. Doughnut recouvrement ──────────────────────────────────────────────────
const recPct = <?= $tauxRecouvrement ?>;
const recColor = recPct >= 80 ? green : (recPct >= 50 ? amber : red);
new Chart(document.getElementById('chartRecouvrement'), {
    type: 'doughnut',
    data: {
        labels: ['Collecté','Restant'],
        datasets: [{ data: [recPct, <?= $tauxNonRecouvre ?>],
            backgroundColor: [recColor, '#fee2e2'], borderWidth: 0, hoverOffset: 4 }]
    },
    options: {
        responsive: false, cutout: '72%',
        plugins: { legend: { display: false },
            tooltip: { callbacks: { label: c => c.label + ' : ' + c.parsed + '%' } } }
    }
});

// ── 4. Doughnut modes de paiement ────────────────────────────────────────────
const elModes = document.getElementById('chartModes');
if (elModes) {
    new Chart(elModes, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($modesLabels) ?>,
            datasets: [{ data: <?= json_encode($modesNb) ?>,
                backgroundColor: ['#6366f1','#0ea5e9','#059669','#d97706','#e53e3e'],
                borderWidth: 0, hoverOffset: 4 }]
        },
        options: {
            responsive: false, cutout: '68%',
            plugins: { legend: { display: false },
                tooltip: { callbacks: { label: c => c.label + ' : ' + c.parsed + ' paiement(s)' } } }
        }
    });
}

// ── 5. Line chart tendance 6 mois ─────────────────────────────────────────────
new Chart(document.getElementById('chartTrend'), {
    type: 'line',
    data: {
        labels: <?= json_encode($trend6Labels) ?>,
        datasets: [
            {
                label: 'Cette période',
                data: <?= json_encode($trend6Current) ?>,
                borderColor: marine,
                backgroundColor: 'rgba(0,33,71,.08)',
                borderWidth: 2.5,
                pointBackgroundColor: marine,
                pointRadius: 4,
                tension: 0.4,
                fill: true,
            },
            {
                label: 'Année précédente',
                data: <?= json_encode($trend6Previous) ?>,
                borderColor: '#aab4cc',
                backgroundColor: 'transparent',
                borderWidth: 1.5,
                borderDash: [5,4],
                pointBackgroundColor: '#aab4cc',
                pointRadius: 3,
                tension: 0.4,
                fill: false,
            }
        ]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: {
            legend: { position: 'top', align: 'end', labels: { boxWidth: 12, font: { size: 11 } } },
            tooltip: { callbacks: { label: c => c.dataset.label + ' : ' + fmt(c.parsed.y) + ' FCFA' } }
        },
        scales: {
            y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,.04)' },
                 ticks: { callback: v => new Intl.NumberFormat('fr-FR',{notation:'compact'}).format(v) } },
            x: { grid: { display: false } }
        }
    }
});
</script>
</body>
</html>
