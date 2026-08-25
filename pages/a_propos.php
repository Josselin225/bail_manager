<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once('../config/db.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$entreprise = $pdo->query("SELECT * FROM settings LIMIT 1")->fetch();
if (!$entreprise) {
    $entreprise = ['nom_entreprise' => '—', 'adresse_siege' => '', 'contact_telephone' => '', 'contact_email' => ''];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../favicon.svg">
    <title>À propos — BailManager</title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/fontawesome/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        :root { --marine:#002147; }
        .main-content { background:#f4f7fe; min-height:100vh; padding:28px; }
        .about-card { background:#fff; border-radius:14px; border:1px solid #e8ecf4; box-shadow:0 2px 10px rgba(0,0,0,.06); padding:24px; margin-bottom:20px; }
        .section-title { font-size:14px; font-weight:700; color:#2d3a55; margin-bottom:16px; display:flex; align-items:center; gap:8px; }
        .feature-item { display:flex; align-items:flex-start; gap:10px; padding:8px 0; font-size:13px; color:#2d3a55; }
        .feature-item i { color:var(--marine); margin-top:2px; }
        .contact-link { display:flex; align-items:center; gap:12px; padding:12px 14px; background:#f8faff; border-radius:10px; text-decoration:none; color:inherit; margin-bottom:8px; }
        .contact-link:hover { background:#eef2fb; }
        .tech-badge { background:#eef2fb; color:var(--marine); font-size:12px; font-weight:600; padding:6px 14px; }

        html[data-theme="dark"] .about-card { background:#1e222b !important; border-color:#2e333d !important; }
        html[data-theme="dark"] .section-title { color:#e4e6eb !important; }
        html[data-theme="dark"] .feature-item { color:#e4e6eb !important; }
        html[data-theme="dark"] .contact-link { background:#262b35 !important; }
        html[data-theme="dark"] .contact-link:hover { background:#2e333d !important; }
        html[data-theme="dark"] .version-badge { background:#262b35 !important; }
        html[data-theme="dark"] .tech-badge { background:#262b35 !important; }
    </style>
</head>
<body>
<?php include('../includes/sidebar.php'); ?>

<div class="main-content">
    <div class="row justify-content-center">
        <div class="col-lg-7">

            <!-- En-tête -->
            <div class="about-card text-center">
                <h2 class="fw-bold mb-1" style="color:var(--marine);">BAIL<span style="color:#dc3545;">MANAGER</span></h2>
                <p class="text-muted mb-1">Logiciel de gestion locative et immobilière</p>
                <span class="badge rounded-pill version-badge" style="background:#eef2fb;color:var(--marine);font-size:11px;">Version <?= htmlspecialchars(APP_VERSION) ?></span>
            </div>

            <!-- Description -->
            <div class="about-card">
                <div class="section-title"><i class="fa fa-circle-info" style="color:var(--marine);"></i>À propos de l'application</div>
                <p class="mb-0" style="font-size:13px;color:#5a6a85;">
                    BailManager centralise la gestion d'une agence immobilière : bailleurs, biens, locataires,
                    contrats de bail, encaissements de loyers, cautions, charges locatives, caisse de
                    l'entreprise et rapports imprimables — le tout depuis une seule interface.
                </p>
            </div>

            <!-- Fonctionnalités -->
            <div class="about-card">
                <div class="section-title"><i class="fa fa-list-check" style="color:var(--marine);"></i>Fonctionnalités principales</div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="feature-item"><i class="fa fa-user-tie"></i>Gestion des bailleurs et de leur compte courant</div>
                        <div class="feature-item"><i class="fa fa-home"></i>Parc immobilier (maisons, statuts, loyers)</div>
                        <div class="feature-item"><i class="fa fa-users"></i>Locataires et espace portail dédié</div>
                        <div class="feature-item"><i class="fa fa-file-contract"></i>Contrats de bail et révisions de loyer</div>
                    </div>
                    <div class="col-md-6">
                        <div class="feature-item"><i class="fa fa-hand-holding-dollar"></i>Encaissements et suivi des impayés</div>
                        <div class="feature-item"><i class="fa fa-shield-halved"></i>Gestion des cautions et restitutions</div>
                        <div class="feature-item"><i class="fa fa-cash-register"></i>Caisse de l'entreprise</div>
                        <div class="feature-item"><i class="fa fa-chart-bar"></i>Rapports détaillés PDF / Excel</div>
                    </div>
                </div>
            </div>

            <!-- Technologies -->
            <div class="about-card">
                <div class="section-title"><i class="fa fa-code" style="color:var(--marine);"></i>Technologies utilisées</div>
                <div class="d-flex flex-wrap gap-2">
                    <span class="badge rounded-pill tech-badge"><i class="fa-brands fa-php me-1"></i>PHP</span>
                    <span class="badge rounded-pill tech-badge"><i class="fa fa-database me-1"></i>MySQL</span>
                    <span class="badge rounded-pill tech-badge"><i class="fa-brands fa-js me-1"></i>JavaScript</span>
                    <span class="badge rounded-pill tech-badge"><i class="fa-brands fa-html5 me-1"></i>HTML5</span>
                    <span class="badge rounded-pill tech-badge"><i class="fa-brands fa-css3-alt me-1"></i>CSS3</span>
                    <span class="badge rounded-pill tech-badge"><i class="fa-brands fa-bootstrap me-1"></i>Bootstrap 5</span>
                    <span class="badge rounded-pill tech-badge"><i class="fa fa-chart-column me-1"></i>Chart.js</span>
                    <span class="badge rounded-pill tech-badge"><i class="fa fa-file-pdf me-1"></i>jsPDF</span>
                    <span class="badge rounded-pill tech-badge"><i class="fa fa-file-excel me-1"></i>SheetJS (XLSX)</span>
                    <span class="badge rounded-pill tech-badge"><i class="fa fa-icons me-1"></i>Font Awesome</span>
                </div>
            </div>

            <!-- Agence -->
            <div class="about-card">
                <div class="section-title"><i class="fa fa-building" style="color:var(--marine);"></i>Agence utilisatrice</div>
                <p class="mb-1 fw-semibold" style="color:#2d3a55;"><?= htmlspecialchars($entreprise['nom_entreprise']) ?></p>
                <?php if (!empty($entreprise['adresse_siege'])): ?>
                <p class="text-muted small mb-1"><?= nl2br(htmlspecialchars($entreprise['adresse_siege'])) ?></p>
                <?php endif; ?>
                <p class="text-muted small mb-0">
                    <?= htmlspecialchars($entreprise['contact_telephone'] ?? '') ?>
                    <?= !empty($entreprise['contact_email']) ? ' · ' . htmlspecialchars($entreprise['contact_email']) : '' ?>
                </p>
            </div>

            <!-- Développeur / Support -->
            <div class="about-card">
                <div class="section-title"><i class="fa fa-user-gear" style="color:var(--marine);"></i>Développeur &amp; Support</div>
                <p class="text-muted small mb-3">Développé par M. KOFFI — une difficulté ? Contactez-moi directement.</p>
                <a href="tel:+2250749791287" class="contact-link">
                    <i class="fa fa-phone-alt fa-lg text-success"></i>
                    <div>
                        <small class="text-muted d-block" style="font-size:10px;">Appeler</small>
                        <span class="fw-bold small">(+225) 07 49 79 12 87 / 01 00 01 43 30</span>
                    </div>
                </a>
                <a href="mailto:kkjoss01@gmail.com" class="contact-link mb-0">
                    <i class="fa fa-envelope fa-lg text-primary"></i>
                    <div>
                        <small class="text-muted d-block" style="font-size:10px;">Email</small>
                        <span class="fw-bold small">kkjoss01@gmail.com</span>
                    </div>
                </a>
            </div>

        </div>
    </div>
</div>

<script src="../js/bootstrap.bundle.min.js"></script>
</body>
</html>
