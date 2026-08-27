<?php
require_once(__DIR__ . '/../config/db.php');
require_once('check_session.php');
ob_start(); // empêche toute troncature PHP
$countRes = $pdo->query("SELECT COUNT(*) FROM reservations WHERE statut = 'en_attente'")->fetchColumn() ?: 0;
$countMsg = $pdo->query("SELECT COUNT(*) FROM messages WHERE statut = 'non_lu'")->fetchColumn() ?: 0;
$countMsgLocataires = $pdo->query("SELECT COUNT(*) FROM messages_locataires WHERE expediteur='locataire' AND lu=0")->fetchColumn() ?: 0;

$photoProfilCourant = null;
if (isset($_SESSION['user_id'])) {
    $stmtPhotoNav = $pdo->prepare("SELECT photo_profil FROM users WHERE id = ?");
    $stmtPhotoNav->execute([$_SESSION['user_id']]);
    $photoProfilCourant = $stmtPhotoNav->fetchColumn();
    if ($photoProfilCourant && !file_exists(__DIR__ . '/../uploads/users/' . $photoProfilCourant)) {
        $photoProfilCourant = null;
    }
}
$currentPageFile = basename($_SERVER['PHP_SELF']);

$groupPatrimoine = in_array($currentPageFile, ['bailleurs.php','maisons.php','locataires.php']);
$groupContrats   = in_array($currentPageFile, ['contrats.php','encaissements.php','calendrier.php']);
$groupGestion    = in_array($currentPageFile, ['charges_locatives.php','revisions_loyer.php']);
$groupFinances   = in_array($currentPageFile, ['compte_bailleur.php','caisse_entreprise.php','gestion_cautions.php']);

/* Fil d'Ariane de la topbar : [groupe ou null, titre, icône] par page connue du menu.
   Comble l'espace vide de la topbar sans avoir à modifier chaque page individuellement. */
$breadcrumbMap = [
    'dashboard.php'          => [null,               'Dashboard',           'fa-chart-line'],
    'bailleurs.php'          => ['Patrimoine',        'Bailleurs',           'fa-user-tie'],
    'maisons.php'            => ['Patrimoine',        'Maisons',             'fa-home'],
    'locataires.php'         => ['Patrimoine',        'Locataires',          'fa-users'],
    'contrats.php'           => ['Contrats & Loyers', 'Contrats',            'fa-file-contract'],
    'encaissements.php'      => ['Contrats & Loyers', 'Encaissements',       'fa-hand-holding-dollar'],
    'calendrier.php'         => ['Contrats & Loyers', 'Calendrier',          'fa-calendar-alt'],
    'charges_locatives.php'  => ['Gestion locative',  'Charges locatives',   'fa-bolt'],
    'revisions_loyer.php'    => ['Gestion locative',  'Révisions de loyer',  'fa-arrow-trend-up'],
    'compte_bailleur.php'    => ['Finances',          'Compte Bailleur',     'fa-money-bill-transfer'],
    'caisse_entreprise.php'  => ['Finances',          'Caisse Entreprise',   'fa-cash-register'],
    'gestion_cautions.php'   => ['Finances',          'Cautions',            'fa-shield-halved'],
    'liste_reservations.php' => [null,                'Réservations',        'fa-calendar-check'],
    'liste_messages.php'     => [null,                'Messages',            'fa-envelope'],
    'messages_locataires.php'=> [null,                'Messages Locataires', 'fa-comments'],
    'rapports.php'           => [null,                'Rapports',            'fa-chart-bar'],
    'journal_activites.php'  => [null,                'Journal',             'fa-history'],
    'profil.php'             => [null,                'Mon Profil',          'fa-user-circle'],
    'guide.php'              => [null,                'Guide d\'utilisation','fa-book-open'],
    'a_propos.php'           => [null,                'À propos',            'fa-circle-info'],
];
if (isset($breadcrumbMap[$currentPageFile])) {
    [$breadcrumbGroup, $breadcrumbTitle, $breadcrumbIcon] = $breadcrumbMap[$currentPageFile];
} else {
    $breadcrumbGroup = null;
    $breadcrumbTitle = ucfirst(str_replace(['_', '.php'], [' ', ''], $currentPageFile));
    $breadcrumbIcon  = 'fa-circle';
}
?>
<link rel="stylesheet" href="../css/theme.css">
<link rel="stylesheet" href="../css/toast.css">
<script>
window.__bmFlashMessages = <?= json_encode(array_map(
    fn($f) => ['type' => $f['type'], 'message' => $f['message']],
    $_SESSION['flash'] ?? []
), JSON_UNESCAPED_UNICODE) ?>;
</script>
<?php unset($_SESSION['flash']); ?>
<script>
/* Applique thème/couleur/état du menu sauvegardés avant le rendu (limite le flash) */
(function(){
    var t = localStorage.getItem('bm_theme') || 'light';
    document.documentElement.setAttribute('data-theme', t);
    var c = localStorage.getItem('bm_menu_color');
    if (c) document.documentElement.style.setProperty('--menu-color', c);
    if (localStorage.getItem('bm_sidebar_collapsed') === '1') {
        document.documentElement.classList.add('sidebar-collapsed');
    }
})();
</script>
<?php include __DIR__ . '/page_loader.php'; ?>
<style>
/* ══════════════════ Layout : sidebar verticale + topbar ══════════════════ */
:root { --sb-w: 240px; --sb-w-c: 72px; --tb-h: 60px; }

/* Sans padding-right sur .main-content (design "plein bord"), les marges négatives
   des .row Bootstrap dépassent légèrement à droite et provoquent un scroll horizontal
   indésirable sur toute l'app — on le neutralise une bonne fois ici. */
html, body { overflow-x: hidden; }

body { padding-top: var(--tb-h) !important; }
.main-content {
    margin-left: var(--sb-w) !important;
    width: calc(100% - var(--sb-w)) !important;
    padding: 28px 0;
    transition: margin-left .2s ease, width .2s ease;
}
html.sidebar-collapsed .main-content {
    margin-left: var(--sb-w-c) !important;
    width: calc(100% - var(--sb-w-c)) !important;
}
/* Petit espace pour ne pas coller le contenu contre le sidebar */
.main-content {
    padding-left:  24px !important;
    padding-right: 0 !important;
}
.top-fixed, .bottom-scroll {
    padding-left:  0 !important;
    padding-right: 0 !important;
}
/* Plusieurs pages déclarent .bottom-scroll{overflow-x:hidden} pour leur propre
   layout desktop — sur petit écran ça masque les colonnes qui dépassent au lieu
   de permettre de les atteindre. On autorise le scroll horizontal quand le
   contenu (souvent un tableau) est plus large que l'écran. */
.bottom-scroll { overflow-x: auto !important; }
/* Pages avec un bloc .top-fixed : celui-ci gère déjà son propre espacement haut,
   sinon ça double avec le padding-top de .main-content (espace vide sous la topbar) */
.main-content:has(> .top-fixed) { padding-top: 0 !important; }
/* Vues cartes (padding inline défini dans chaque page) */
#vueCartes, #vueListe, #vueBailleurs { padding-left: 0 !important; padding-right: 0 !important; }

/* ── Sidebar verticale ────────────────────────────────────── */
.app-sidebar {
    position: fixed;
    top: 0; left: 0; bottom: 0;
    width: var(--sb-w);
    background: var(--menu-color);
    z-index: 1050;
    display: flex;
    flex-direction: column;
    box-shadow: 2px 0 16px rgba(0,0,0,.15);
    transition: width .2s ease;
    overflow: visible;
}
html.sidebar-collapsed .app-sidebar { width: var(--sb-w-c); }

.sidebar-brand {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 16px 18px;
    flex-shrink: 0;
    text-decoration: none;
    white-space: nowrap;
    overflow: hidden;
    border-bottom: 1px solid rgba(255,255,255,.1);
}
.sidebar-brand-mark {
    width: 32px; height: 32px; border-radius: 8px; flex-shrink: 0;
    background: rgba(255,255,255,.12);
    display: flex; align-items: center; justify-content: center;
    font-size: 13px; font-weight: 800; color: #fff;
}
.sidebar-brand-text { font-size: 15px; font-weight: 800; letter-spacing: 1px; color: #fff; }
html.sidebar-collapsed .sidebar-brand-text { display: none; }

.sidebar-scroll { flex: 1; overflow-y: auto; overflow-x: hidden; padding: 12px 10px; }
.sidebar-scroll::-webkit-scrollbar { width: 5px; }
.sidebar-scroll::-webkit-scrollbar-thumb { background: rgba(255,255,255,.15); border-radius: 3px; }

.sidebar-nav { display: flex; flex-direction: column; gap: 2px; }
.snav-sep { height: 1px; background: rgba(255,255,255,.1); margin: 8px 4px; flex-shrink: 0; }

.snav-link, .snav-group > .snav-btn {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 12px;
    border-radius: 8px;
    color: rgba(255,255,255,.78);
    font-size: 13.5px;
    text-decoration: none;
    white-space: nowrap;
    border: none;
    background: transparent;
    width: 100%;
    text-align: left;
    cursor: pointer;
    transition: background .15s ease, color .15s ease;
}
.snav-link:hover, .snav-group > .snav-btn:hover { background: rgba(255,255,255,.1); color: #fff; }
.snav-link.is-active { background: rgba(255,255,255,.15); color: #fff; font-weight: 600; }
/* Le bouton de groupe reste discret (juste en gras) quand une de ses sous-pages est active :
   le vrai indicateur "page courante" est le sous-lien surligné dans .snav-submenu, pas ce bouton. */
.snav-group > .snav-btn.is-active { background: transparent; color: #fff; font-weight: 600; }
.snav-group > .snav-btn.is-active:hover { background: rgba(255,255,255,.1); }
.snav-icon { width: 16px; text-align: center; font-size: 14px; flex-shrink: 0; }
.snav-label { flex: 1; overflow: hidden; text-overflow: ellipsis; }
.snav-badge {
    background: #e53e3e; color: #fff; font-size: 9px; font-weight: 700;
    padding: 1px 5px; border-radius: 10px; min-width: 16px; text-align: center; flex-shrink: 0;
}
.snav-arr { font-size: 9px; opacity: .6; transition: transform .2s; flex-shrink: 0; }
.snav-group > .snav-btn[aria-expanded="true"] .snav-arr { transform: rotate(180deg); }

.snav-submenu { display: flex; flex-direction: column; gap: 1px; padding: 3px 0 3px 30px; }
.snav-submenu a {
    display: flex; align-items: center; gap: 9px;
    padding: 8px 10px; border-radius: 7px;
    font-size: 12.5px; color: rgba(255,255,255,.65); text-decoration: none;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.snav-submenu a:hover { background: rgba(255,255,255,.08); color: #fff; }
.snav-submenu a.is-active { background: rgba(255,255,255,.12); color: #fff; font-weight: 600; }
.snav-submenu a i { width: 13px; text-align: center; font-size: 11px; flex-shrink: 0; }

/* Repli en rail d'icônes : label/flèche/sous-menu masqués, un panneau flottant apparaît au survol.
   Le panneau est positionné en `fixed` (calculé en JS) plutôt qu'`absolute` car son ancêtre
   `.sidebar-scroll` a overflow-x:hidden (nécessaire pour le scroll vertical), ce qui couperait
   tout panneau positionné en absolute dépassant la largeur du rail. */
html.sidebar-collapsed .snav-label,
html.sidebar-collapsed .snav-arr { display: none; }
html.sidebar-collapsed .snav-link,
html.sidebar-collapsed .snav-group > .snav-btn { justify-content: center; padding: 10px; }
html.sidebar-collapsed .snav-submenu { display: none; }
.snav-flyout {
    display: none;
    flex-direction: column;
    position: fixed;
    background: #fff;
    border: 1px solid rgba(0,33,71,.15);
    border-radius: 10px;
    box-shadow: 0 8px 30px rgba(0,0,0,.2);
    padding: 6px;
    min-width: 200px;
    z-index: 1100;
}
.snav-flyout .flyout-title {
    font-size: 11px; font-weight: 700; color: #8896b0; text-transform: uppercase;
    letter-spacing: .05em; padding: 8px 10px 4px;
}
.snav-flyout a {
    display: flex; align-items: center; gap: 10px;
    padding: 9px 12px; border-radius: 7px; font-size: 13px;
    color: var(--menu-color); text-decoration: none;
}
.snav-flyout a:hover { background: var(--menu-color); color: #fff; }
.snav-flyout a i { width: 14px; text-align: center; font-size: 12px; }

.sidebar-collapse-btn {
    flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    gap: 8px;
    padding: 12px;
    border: none; border-top: 1px solid rgba(255,255,255,.1);
    background: rgba(255,255,255,.04);
    color: rgba(255,255,255,.7);
    font-size: 12px;
    cursor: pointer;
    transition: background .15s ease, color .15s ease;
}
.sidebar-collapse-btn:hover { background: rgba(255,255,255,.1); color: #fff; }
html.sidebar-collapsed .sidebar-collapse-btn .scb-label { display: none; }
html.sidebar-collapsed .sidebar-collapse-btn i { transform: rotate(180deg); }

/* ── Topbar (barre haute légère) ──────────────────────────── */
.app-topbar {
    position: fixed;
    top: 0; right: 0; left: var(--sb-w);
    height: var(--tb-h);
    background: #fff;
    border-bottom: 1px solid #e8ecf4;
    z-index: 1040;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 4px;
    padding: 0 20px;
    transition: left .2s ease;
}
html.sidebar-collapsed .app-topbar { left: var(--sb-w-c); }

.topbar-toggler {
    display: none;
    background: transparent;
    border: 1px solid #e0e6f0;
    border-radius: 7px;
    padding: 6px 10px;
    color: var(--menu-color);
    cursor: pointer;
}

/* Fil d'Ariane — comble l'espace vide à gauche de la topbar */
.topbar-title {
    display: flex;
    align-items: center;
    gap: 9px;
    margin-right: auto;
    min-width: 0;
    color: #2d3a55;
    font-size: 14px;
    white-space: nowrap;
    overflow: hidden;
}
.topbar-title .tt-icon {
    width: 30px; height: 30px; border-radius: 8px; flex-shrink: 0;
    background: #eef2fb; color: var(--menu-color);
    display: flex; align-items: center; justify-content: center; font-size: 13px;
}
.topbar-title .tt-group { color: #8896b0; font-weight: 500; }
.topbar-title .tt-sep { color: #c3cad9; }
.topbar-title .tt-current { font-weight: 700; overflow: hidden; text-overflow: ellipsis; }
@media (max-width: 575px) { .topbar-title .tt-group, .topbar-title .tt-sep { display: none; } }

/* Recherche globale */
.tb-search-wrap { position: relative; margin-right: 14px; flex-shrink: 0; }
.tb-search-wrap .tb-search-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #aab; font-size: 12px; pointer-events: none; }
.tb-search-input { width: 210px; height: 34px; padding-left: 32px; border: 1.5px solid #e0e6f0; border-radius: 8px; font-size: 13px; outline: none; transition: border-color .15s, width .15s; background: #fff; color: #2d3a55; }
.tb-search-input:focus { border-color: var(--menu-color); width: 250px; }
.tb-search-results { display: none; position: absolute; top: 40px; left: 0; width: 320px; max-height: 420px; overflow-y: auto; background: #fff; border-radius: 10px; box-shadow: 0 8px 30px rgba(0,0,0,.15); border: 1px solid #e8ecf4; z-index: 2000; }
.tb-search-group-title { padding: 8px 14px 4px; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: #8896b0; }
.tb-search-item { display: block; padding: 9px 14px; text-decoration: none; color: #2d3a55; border-bottom: 1px solid #f0f3fa; }
.tb-search-item:hover { background: #f4f7fe; }
.tb-search-item .tsi-label { font-size: 13px; font-weight: 600; }
.tb-search-item .tsi-sub { font-size: 11px; color: #8896b0; margin-left: 22px; }
.tb-search-empty { padding: 16px; text-align: center; color: #aab; font-size: 13px; }
@media (max-width: 820px) { .tb-search-wrap { display: none; } }

.tb-link {
    display: flex; align-items: center; justify-content: center;
    width: 34px; height: 34px; border-radius: 50%;
    color: #4a5568; text-decoration: none; font-size: 14px;
    transition: background .15s ease;
}
.tb-link:hover { background: #f0f3fa; color: var(--menu-color); }

.topnav-user {
    display: flex; align-items: center; gap: 8px;
    padding: 5px 12px; border-radius: 20px;
    background: #f0f3fa;
    border: 1px solid #e0e6f0;
    color: #2d3a55;
    font-size: 13px;
    cursor: pointer;
    transition: background .15s ease;
}
.topnav-user:hover { background: #e5eaf5; }
.topnav-user .avatar {
    width: 26px; height: 26px; border-radius: 50%;
    background: var(--menu-color);
    color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-size: 10px; flex-shrink: 0;
}

.dropdown-menu {
    border: 1px solid rgba(0,33,71,.15);
    border-radius: 10px;
    box-shadow: 0 8px 30px rgba(0,0,0,.15);
    padding: 6px;
}
.dropdown-item {
    font-size: 13px;
    padding: 9px 13px;
    border-radius: 7px;
    color: var(--menu-color);
    display: flex;
    align-items: center;
    gap: 10px;
    transition: background .13s ease, color .13s ease;
}
.dropdown-item:hover { background: var(--menu-color); color: #fff; }
.dropdown-item i { color: var(--menu-color); font-size: 12px; width: 16px; text-align: center; }
.dropdown-item:hover i { color: #fff !important; }
.dropdown-item.danger { color: #dc2626; }
.dropdown-item.danger:hover { background: #dc2626; color: #fff; }
.dropdown-item.danger i { color: #dc2626; }
.dropdown-item.danger:hover i { color: #fff; }

/* ── Mobile : sidebar en panneau coulissant, topbar pleine largeur ── */
@media (max-width: 991px) {
    .topbar-toggler { display: flex; align-items: center; justify-content: center; }
    .app-sidebar { transform: translateX(-100%); box-shadow: none; }
    .app-sidebar.mobile-open { transform: translateX(0); box-shadow: 4px 0 24px rgba(0,0,0,.25); }
    .app-topbar, html.sidebar-collapsed .app-topbar { left: 0 !important; }
    .main-content, html.sidebar-collapsed .main-content { margin-left: 0 !important; width: 100% !important; padding: 16px 0; }
    .sidebar-collapse-btn { display: none; }
    .sidebar-backdrop { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.4); z-index: 1045; }
    .sidebar-backdrop.show { display: block; }
}

@media print {
    .app-sidebar, .app-topbar { display: none !important; }
    body { padding-top: 0 !important; }
    .main-content { margin-left: 0 !important; width: 100% !important; }
}
</style>

<!-- ══ SIDEBAR ══ -->
<aside class="app-sidebar" id="appSidebar">
    <a href="dashboard.php" class="sidebar-brand">
        <span class="sidebar-brand-mark">B</span>
        <span class="sidebar-brand-text">BAIL<span class="text-danger">MANAGER</span></span>
    </a>

    <div class="sidebar-scroll">
        <div class="sidebar-nav" id="sidebarAccordion">

            <a href="dashboard.php" class="snav-link <?= $currentPageFile === 'dashboard.php' ? 'is-active' : '' ?>">
                <i class="fa fa-chart-line snav-icon"></i><span class="snav-label">Dashboard</span>
            </a>

            <div class="snav-sep"></div>

            <!-- Patrimoine -->
            <div class="snav-group">
                <button class="snav-btn <?= $groupPatrimoine ? 'is-active' : '' ?>" data-bs-toggle="collapse" data-bs-target="#sgPatrimoine" aria-expanded="<?= $groupPatrimoine ? 'true' : 'false' ?>">
                    <i class="fa fa-building snav-icon"></i><span class="snav-label">Patrimoine</span><i class="fa fa-chevron-down snav-arr"></i>
                </button>
                <div class="collapse snav-submenu <?= $groupPatrimoine ? 'show' : '' ?>" id="sgPatrimoine" data-bs-parent="#sidebarAccordion">
                    <a class="<?= $currentPageFile === 'bailleurs.php' ? 'is-active' : '' ?>" href="bailleurs.php"><i class="fa fa-user-tie"></i>Bailleurs</a>
                    <a class="<?= $currentPageFile === 'maisons.php' ? 'is-active' : '' ?>" href="maisons.php"><i class="fa fa-home"></i>Maisons</a>
                    <a class="<?= $currentPageFile === 'locataires.php' ? 'is-active' : '' ?>" href="locataires.php"><i class="fa fa-users"></i>Locataires</a>
                </div>
                <div class="snav-flyout">
                    <div class="flyout-title">Patrimoine</div>
                    <a href="bailleurs.php"><i class="fa fa-user-tie"></i>Bailleurs</a>
                    <a href="maisons.php"><i class="fa fa-home"></i>Maisons</a>
                    <a href="locataires.php"><i class="fa fa-users"></i>Locataires</a>
                </div>
            </div>

            <!-- Contrats & Loyers -->
            <div class="snav-group">
                <button class="snav-btn <?= $groupContrats ? 'is-active' : '' ?>" data-bs-toggle="collapse" data-bs-target="#sgContrats" aria-expanded="<?= $groupContrats ? 'true' : 'false' ?>">
                    <i class="fa fa-file-contract snav-icon"></i><span class="snav-label">Contrats &amp; Loyers</span><i class="fa fa-chevron-down snav-arr"></i>
                </button>
                <div class="collapse snav-submenu <?= $groupContrats ? 'show' : '' ?>" id="sgContrats" data-bs-parent="#sidebarAccordion">
                    <a class="<?= $currentPageFile === 'contrats.php' ? 'is-active' : '' ?>" href="contrats.php"><i class="fa fa-file-contract"></i>Contrats</a>
                    <a class="<?= $currentPageFile === 'encaissements.php' ? 'is-active' : '' ?>" href="encaissements.php"><i class="fa fa-hand-holding-dollar"></i>Encaissements</a>
                    <a class="<?= $currentPageFile === 'calendrier.php' ? 'is-active' : '' ?>" href="calendrier.php"><i class="fa fa-calendar-alt"></i>Calendrier</a>
                </div>
                <div class="snav-flyout">
                    <div class="flyout-title">Contrats &amp; Loyers</div>
                    <a href="contrats.php"><i class="fa fa-file-contract"></i>Contrats</a>
                    <a href="encaissements.php"><i class="fa fa-hand-holding-dollar"></i>Encaissements</a>
                    <a href="calendrier.php"><i class="fa fa-calendar-alt"></i>Calendrier</a>
                </div>
            </div>

            <!-- Gestion locative -->
            <div class="snav-group">
                <button class="snav-btn <?= $groupGestion ? 'is-active' : '' ?>" data-bs-toggle="collapse" data-bs-target="#sgGestion" aria-expanded="<?= $groupGestion ? 'true' : 'false' ?>">
                    <i class="fa fa-file-invoice snav-icon"></i><span class="snav-label">Gestion locative</span><i class="fa fa-chevron-down snav-arr"></i>
                </button>
                <div class="collapse snav-submenu <?= $groupGestion ? 'show' : '' ?>" id="sgGestion" data-bs-parent="#sidebarAccordion">
                    <a class="<?= $currentPageFile === 'charges_locatives.php' ? 'is-active' : '' ?>" href="charges_locatives.php"><i class="fa fa-bolt"></i>Charges locatives</a>
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                    <a class="<?= $currentPageFile === 'revisions_loyer.php' ? 'is-active' : '' ?>" href="revisions_loyer.php"><i class="fa fa-arrow-trend-up"></i>Révisions de loyer</a>
                    <?php endif; ?>
                </div>
                <div class="snav-flyout">
                    <div class="flyout-title">Gestion locative</div>
                    <a href="charges_locatives.php"><i class="fa fa-bolt"></i>Charges locatives</a>
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                    <a href="revisions_loyer.php"><i class="fa fa-arrow-trend-up"></i>Révisions de loyer</a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Finances -->
            <div class="snav-group">
                <button class="snav-btn <?= $groupFinances ? 'is-active' : '' ?>" data-bs-toggle="collapse" data-bs-target="#sgFinances" aria-expanded="<?= $groupFinances ? 'true' : 'false' ?>">
                    <i class="fa fa-wallet snav-icon"></i><span class="snav-label">Finances</span><i class="fa fa-chevron-down snav-arr"></i>
                </button>
                <div class="collapse snav-submenu <?= $groupFinances ? 'show' : '' ?>" id="sgFinances" data-bs-parent="#sidebarAccordion">
                    <a class="<?= $currentPageFile === 'compte_bailleur.php' ? 'is-active' : '' ?>" href="compte_bailleur.php"><i class="fa fa-money-bill-transfer"></i>Compte Bailleur</a>
                    <a class="<?= $currentPageFile === 'caisse_entreprise.php' ? 'is-active' : '' ?>" href="caisse_entreprise.php"><i class="fa fa-cash-register"></i>Caisse Entreprise</a>
                    <a class="<?= $currentPageFile === 'gestion_cautions.php' ? 'is-active' : '' ?>" href="gestion_cautions.php"><i class="fa fa-shield-halved"></i>Cautions</a>
                </div>
                <div class="snav-flyout">
                    <div class="flyout-title">Finances</div>
                    <a href="compte_bailleur.php"><i class="fa fa-money-bill-transfer"></i>Compte Bailleur</a>
                    <a href="caisse_entreprise.php"><i class="fa fa-cash-register"></i>Caisse Entreprise</a>
                    <a href="gestion_cautions.php"><i class="fa fa-shield-halved"></i>Cautions</a>
                </div>
            </div>

            <div class="snav-sep"></div>

            <a href="liste_reservations.php" class="snav-link <?= $currentPageFile === 'liste_reservations.php' ? 'is-active' : '' ?>">
                <i class="fa fa-calendar-check snav-icon"></i><span class="snav-label">Réservations</span>
                <?php if ($countRes > 0): ?><span class="snav-badge" id="navBadgeReservations"><?= $countRes ?></span><?php endif; ?>
            </a>

            <a href="liste_messages.php" class="snav-link <?= $currentPageFile === 'liste_messages.php' ? 'is-active' : '' ?>">
                <i class="fa fa-envelope snav-icon"></i><span class="snav-label">Messages</span>
                <?php if ($countMsg > 0): ?><span class="snav-badge" id="navBadgeMessages"><?= $countMsg ?></span><?php endif; ?>
            </a>

            <a href="messages_locataires.php" class="snav-link <?= $currentPageFile === 'messages_locataires.php' ? 'is-active' : '' ?>">
                <i class="fa fa-comments snav-icon"></i><span class="snav-label">Messages Locataires</span>
                <?php if ($countMsgLocataires > 0): ?><span class="snav-badge"><?= $countMsgLocataires ?></span><?php endif; ?>
            </a>

            <a href="rapports.php" class="snav-link <?= $currentPageFile === 'rapports.php' ? 'is-active' : '' ?>">
                <i class="fa fa-chart-bar snav-icon"></i><span class="snav-label">Rapports</span>
            </a>

            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <a href="journal_activites.php" class="snav-link <?= $currentPageFile === 'journal_activites.php' ? 'is-active' : '' ?>">
                <i class="fa fa-history snav-icon"></i><span class="snav-label">Journal</span>
            </a>
            <?php endif; ?>

        </div>
    </div>

    <a href="guide.php" class="snav-link <?= $currentPageFile === 'guide.php' ? 'is-active' : '' ?>" style="margin:4px 10px;">
        <i class="fa fa-book-open snav-icon"></i><span class="snav-label">Guide d'utilisation</span>
    </a>

    <a href="a_propos.php" class="snav-link <?= $currentPageFile === 'a_propos.php' ? 'is-active' : '' ?>" style="margin:4px 10px;">
        <i class="fa fa-circle-info snav-icon"></i><span class="snav-label">À propos</span>
    </a>

    <button class="sidebar-collapse-btn" id="sidebarCollapseBtn" title="Réduire / agrandir le menu">
        <i class="fa fa-angles-left"></i><span class="scb-label">Réduire le menu</span>
    </button>
</aside>
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<!-- ══ TOPBAR ══ -->
<div class="app-topbar">
    <button class="topbar-toggler" id="topbarToggler" aria-label="Menu">
        <i class="fa fa-bars"></i>
    </button>

    <!-- Fil d'Ariane -->
    <div class="topbar-title">
        <span class="tt-icon"><i class="fa <?= $breadcrumbIcon ?>"></i></span>
        <?php if ($breadcrumbGroup): ?>
        <span class="tt-group"><?= htmlspecialchars($breadcrumbGroup) ?></span>
        <span class="tt-sep"><i class="fa fa-chevron-right" style="font-size:9px;"></i></span>
        <?php endif; ?>
        <span class="tt-current"><?= htmlspecialchars($breadcrumbTitle) ?></span>
    </div>

    <!-- Recherche globale -->
    <div class="tb-search-wrap">
        <i class="fa fa-search tb-search-icon"></i>
        <input type="text" id="globalSearchInput" class="tb-search-input" placeholder="Rechercher…" autocomplete="off">
        <div id="globalSearchResults" class="tb-search-results"></div>
    </div>

    <!-- Sélecteur de couleur du menu -->
    <div class="dropdown">
        <button class="theme-btn" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" title="Couleur du menu">
            <i class="fa fa-palette"></i>
        </button>
        <div class="dropdown-menu dropdown-menu-end p-0">
            <div class="color-picker-panel">
                <div class="cp-title">Couleur du menu</div>
                <div class="color-swatches">
                    <button type="button" class="color-swatch" data-color="#002147" style="background:#002147;" title="Marine"></button>
                    <button type="button" class="color-swatch" data-color="#111827" style="background:#111827;" title="Noir"></button>
                    <button type="button" class="color-swatch" data-color="#0f172a" style="background:#0f172a;" title="Anthracite"></button>
                    <button type="button" class="color-swatch" data-color="#1e293b" style="background:#1e293b;" title="Ardoise"></button>
                    <button type="button" class="color-swatch" data-color="#27272a" style="background:#27272a;" title="Charbon"></button>
                    <button type="button" class="color-swatch" data-color="#7c2d12" style="background:#7c2d12;" title="Bordeaux"></button>
                    <button type="button" class="color-swatch" data-color="#831843" style="background:#831843;" title="Framboise"></button>
                    <button type="button" class="color-swatch" data-color="#065f46" style="background:#065f46;" title="Vert"></button>
                    <button type="button" class="color-swatch" data-color="#115e59" style="background:#115e59;" title="Sarcelle"></button>
                    <button type="button" class="color-swatch" data-color="#4c1d95" style="background:#4c1d95;" title="Violet"></button>
                    <button type="button" class="color-swatch" data-color="#3730a3" style="background:#3730a3;" title="Indigo"></button>
                    <button type="button" class="color-swatch" data-color="#1e40af" style="background:#1e40af;" title="Bleu roi"></button>
                    <button type="button" class="color-swatch" data-color="#0c4a6e" style="background:#0c4a6e;" title="Bleu pétrole"></button>
                    <button type="button" class="color-swatch" data-color="#78350f" style="background:#78350f;" title="Brun"></button>
                </div>
                <div class="cp-title">Teinte précise</div>
                <div class="spectrum-bar" id="spectrumBar" title="Cliquez pour choisir une teinte">
                    <div class="spectrum-handle" id="spectrumHandle"></div>
                </div>
                <input type="color" id="menuColorInput" value="#002147" title="Couleur personnalisée">
            </div>
        </div>
    </div>

    <!-- Bascule mode sombre / clair -->
    <button class="theme-btn" id="themeToggleBtn" title="Mode sombre / clair">
        <i class="fa fa-moon" id="themeToggleIcon"></i>
    </button>

    <!-- Espace Locataire -->
    <a href="../pages/locataire_portail.php" class="tb-link" title="Espace Locataire">
        <i class="fa fa-door-open"></i>
    </a>

    <!-- User dropdown -->
    <div class="dropdown">
        <button class="topnav-user" data-bs-toggle="dropdown" aria-expanded="false">
            <?php if ($photoProfilCourant): ?>
            <div class="avatar" style="padding:0;overflow:hidden;"><img src="../uploads/users/<?= htmlspecialchars($photoProfilCourant) ?>" style="width:100%;height:100%;object-fit:cover;"></div>
            <?php else: ?>
            <div class="avatar"><i class="fa fa-user-shield" style="font-size:10px;"></i></div>
            <?php endif; ?>
            <span class="fw-semibold text-truncate d-none d-lg-inline" style="max-width:130px;">
                <?= htmlspecialchars($_SESSION['nom_complet'] ?? 'Admin') ?>
            </span>
            <i class="fa fa-chevron-down" style="font-size:9px;opacity:.6;"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li>
                <div class="px-3 py-2 mb-1" style="border-bottom:1px solid rgba(0,33,71,.12);">
                    <div class="fw-bold small" style="color:var(--menu-color);"><?= htmlspecialchars($_SESSION['nom_complet'] ?? 'Admin') ?></div>
                    <span class="badge bg-danger mt-1" style="font-size:9px;"><?= strtoupper($_SESSION['role'] ?? 'user') ?></span>
                </div>
            </li>
            <li><a class="dropdown-item" href="profil.php"><i class="fa fa-user-circle"></i>Mon Profil</a></li>
            <li>
                <button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#modalPassword">
                    <i class="fa fa-key"></i>Changer le mot de passe
                </button>
            </li>
            <li>
                <button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#modalSupportTech">
                    <i class="fa fa-headset"></i>Support Tech
                </button>
            </li>
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <li><a class="dropdown-item" href="gestion_users.php"><i class="fa fa-users-gear"></i>Gestion utilisateurs</a></li>
            <li><a class="dropdown-item" href="settings.php"><i class="fa fa-gear"></i>Paramètres</a></li>
            <?php endif; ?>
            <li><hr class="dropdown-divider"></li>
            <li>
                <a class="dropdown-item danger" href="../php/logout.php" onclick="return confirm('Se déconnecter ?')">
                    <i class="fa fa-sign-out-alt"></i>Déconnexion
                </a>
            </li>
        </ul>
    </div>
</div>

<!-- Modal Changement de mot de passe -->
<div class="modal fade" id="modalPassword" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header text-white border-0 menu-gradient-header">
                <h5 class="modal-title fw-bold"><i class="fa fa-key me-2"></i>Changer le mot de passe</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="../php/update_password.php">
                <input type="hidden" name="token" value="<?= csrf_generate() ?>">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Nouveau mot de passe</label>
                        <input type="password" name="new_password" class="form-control" placeholder="••••••••" required minlength="6">
                    </div>
                    <div class="mb-1">
                        <label class="form-label fw-semibold small">Confirmer le mot de passe</label>
                        <input type="password" name="confirm_password" class="form-control" placeholder="••••••••" required minlength="6">
                    </div>
                </div>
                <div class="modal-footer border-0 pb-4">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-danger rounded-pill px-4"><i class="fa fa-check me-1"></i>Confirmer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Support Tech -->
<div class="modal fade" id="modalSupportTech" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header text-white border-0 menu-gradient-header">
                <h5 class="modal-title fw-bold"><i class="fa fa-headset me-2"></i>Assistance Technique</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width:70px;height:70px;">
                    <i class="fa fa-user-gear fa-2x text-danger"></i>
                </div>
                <h6 class="fw-bold">Développeur — M. KOFFI</h6>
                <p class="text-muted small mb-3">Une difficulté ? Je suis à votre disposition.</p>
                <div class="list-group list-group-flush text-start">
                    <a href="tel:+2250749791287" class="list-group-item list-group-item-action d-flex align-items-center py-3 border-0 bg-light rounded mb-2">
                        <i class="fa fa-phone-alt fa-lg me-3 text-success"></i>
                        <div>
                            <small class="text-muted d-block" style="font-size:10px;">Appeler</small>
                            <span class="fw-bold small">(+225) 07 49 79 12 87 / 01 00 01 43 30</span>
                        </div>
                    </a>
                    <a href="mailto:kkjoss01@gmail.com" class="list-group-item list-group-item-action d-flex align-items-center py-3 border-0 bg-light rounded">
                        <i class="fa fa-envelope fa-lg me-3 text-primary"></i>
                        <div>
                            <small class="text-muted d-block" style="font-size:10px;">Email</small>
                            <span class="fw-bold small">kkjoss01@gmail.com</span>
                        </div>
                    </a>
                </div>
            </div>
            <div class="modal-footer border-0 justify-content-center pb-4">
                <button type="button" class="btn btn-secondary px-4 rounded-pill" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<script>
// Repli / dépli du menu (desktop) — persisté
document.getElementById('sidebarCollapseBtn').addEventListener('click', function() {
    var collapsed = document.documentElement.classList.toggle('sidebar-collapsed');
    localStorage.setItem('bm_sidebar_collapsed', collapsed ? '1' : '0');
});

// Panneau flottant des groupes (Patrimoine, Finances...) quand le menu est replié
document.querySelectorAll('.snav-group').forEach(function(group) {
    var btn    = group.querySelector('.snav-btn');
    var flyout = group.querySelector('.snav-flyout');
    group.addEventListener('mouseenter', function() {
        if (!document.documentElement.classList.contains('sidebar-collapsed')) return;
        var r = btn.getBoundingClientRect();
        flyout.style.top  = r.top + 'px';
        flyout.style.left = (r.right + 8) + 'px';
        flyout.style.display = 'flex';
    });
    group.addEventListener('mouseleave', function() {
        flyout.style.display = 'none';
    });
});

// Ouverture / fermeture en panneau coulissant (mobile)
var sidebarEl   = document.getElementById('appSidebar');
var backdropEl  = document.getElementById('sidebarBackdrop');
function closeMobileSidebar() {
    sidebarEl.classList.remove('mobile-open');
    backdropEl.classList.remove('show');
}
document.getElementById('topbarToggler').addEventListener('click', function() {
    sidebarEl.classList.toggle('mobile-open');
    backdropEl.classList.toggle('show');
});
backdropEl.addEventListener('click', closeMobileSidebar);
sidebarEl.querySelectorAll('.snav-link, .snav-submenu a').forEach(function(el) {
    el.addEventListener('click', closeMobileSidebar);
});

// ── Recherche globale ────────────────────────────────────────
(function() {
    var input = document.getElementById('globalSearchInput');
    var panel = document.getElementById('globalSearchResults');
    if (!input || !panel) return;

    var timer;
    input.addEventListener('input', function() {
        clearTimeout(timer);
        var q = this.value.trim();
        if (q.length < 2) { panel.style.display = 'none'; panel.innerHTML = ''; return; }
        timer = setTimeout(function() {
            fetch('../php/recherche_globale.php?q=' + encodeURIComponent(q))
                .then(function(r) { return r.json(); })
                .then(renderResults)
                .catch(function() {});
        }, 300);
    });

    document.addEventListener('click', function(e) {
        if (e.target !== input && !panel.contains(e.target)) panel.style.display = 'none';
    });

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s || '';
        return d.innerHTML;
    }

    function section(title, items, icon) {
        if (!items || !items.length) return '';
        var html = '<div class="tb-search-group-title">' + esc(title) + '</div>';
        items.forEach(function(it) {
            html += '<a href="' + esc(it.url) + '" class="tb-search-item">' +
                    '<i class="fa ' + icon + ' me-2"></i>' +
                    '<span class="tsi-label">' + esc(it.label) + '</span>' +
                    (it.sub ? '<div class="tsi-sub">' + esc(it.sub) + '</div>' : '') +
                    '</a>';
        });
        return html;
    }

    function renderResults(data) {
        var html = section('Bailleurs', data.bailleurs, 'fa-user-tie') +
                   section('Locataires', data.locataires, 'fa-users') +
                   section('Maisons', data.maisons, 'fa-home') +
                   section('Contrats', data.contrats, 'fa-file-contract');
        panel.innerHTML = html || '<div class="tb-search-empty">Aucun résultat</div>';
        panel.style.display = 'block';
    }
})();
</script>
<script src="../js/theme.js"></script>
<script src="../js/toast.js"></script>
