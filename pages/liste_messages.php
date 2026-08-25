<?php
session_start();
require_once('../config/db.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$messages     = $pdo->query("SELECT * FROM messages ORDER BY statut ASC, date_envoi DESC")->fetchAll();
$totalMessages = count($messages);
$unreadCount   = count(array_filter($messages, fn($m) => $m['statut'] === 'non_lu'));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../favicon.svg">
    <title>Messages — BailManager</title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/fontawesome/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        :root { --marine: #002147; --red: #e53e3e; }

        body { background: #f4f7fe; overflow: hidden; }

        /* ── Page layout ──
           Cette page ne passe pas par .main-content (layout messagerie plein écran
           avec sa propre gestion de hauteur/scroll) — il faut donc reproduire ici
           le décalage réservé au sidebar, sinon le contenu passe dessous. */
        .msg-page {
            display: flex;
            flex-direction: column;
            height: calc(100vh - var(--tb-h, 60px));
            margin-left: var(--sb-w, 240px);
            width: calc(100% - var(--sb-w, 240px));
            padding: 20px 24px;
            gap: 16px;
            box-sizing: border-box;
            transition: margin-left .2s ease, width .2s ease;
        }
        html.sidebar-collapsed .msg-page {
            margin-left: var(--sb-w-c, 72px);
            width: calc(100% - var(--sb-w-c, 72px));
        }
        @media (max-width: 991px) {
            .msg-page { margin-left: 0; width: 100%; }
        }

        /* ── Header bar ── */
        .msg-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-shrink: 0;
        }
        .msg-topbar h2 {
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--marine);
            margin: 0;
        }

        /* ── Messenger container ── */
        .messenger {
            display: flex;
            flex: 1;
            min-height: 0;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 4px 24px rgba(0,0,0,.08);
            background: #fff;
        }

        /* ══ LEFT PANEL ══ */
        .msg-list-panel {
            width: 320px;
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            border-right: 1px solid #e8ecf4;
        }

        .msg-list-header {
            padding: 14px 16px 0;
            background: #fff;
        }
        .msg-list-header .filters {
            display: flex;
            gap: 6px;
            margin-bottom: 10px;
        }
        .filter-btn {
            font-size: 11px;
            font-weight: 600;
            padding: 4px 14px;
            border-radius: 20px;
            border: 1.5px solid #e0e6f0;
            background: transparent;
            color: #6b7a99;
            cursor: pointer;
            transition: all .15s ease;
        }
        .filter-btn.active {
            background: var(--marine);
            border-color: var(--marine);
            color: #fff;
        }
        .search-wrap {
            position: relative;
            margin-bottom: 10px;
        }
        .search-wrap i {
            position: absolute;
            left: 11px; top: 50%;
            transform: translateY(-50%);
            color: #aab;
            font-size: 12px;
        }
        .search-wrap input {
            padding-left: 30px;
            border-radius: 8px;
            border: 1.5px solid #e0e6f0;
            font-size: 13px;
            width: 100%;
            height: 34px;
            outline: none;
        }
        .search-wrap input:focus { border-color: var(--marine); }

        .msg-scroll { overflow-y: auto; flex: 1; }

        /* ── Message item ── */
        .msg-item {
            display: flex;
            align-items: flex-start;
            gap: 11px;
            padding: 13px 16px;
            border-bottom: 1px solid #f0f3fa;
            cursor: pointer;
            transition: background .13s ease;
            position: relative;
        }
        .msg-item:hover { background: #f4f7fe; }
        .msg-item.active { background: #eef2fb; border-left: 3px solid var(--marine); padding-left: 13px; }
        .msg-item.unread .msg-name { font-weight: 700; color: var(--marine); }
        .msg-item.read { opacity: .72; }

        .msg-avatar {
            width: 38px; height: 38px;
            border-radius: 50%;
            background: linear-gradient(135deg,#002147,#004080);
            color: #fff;
            font-size: 13px;
            font-weight: 700;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
            letter-spacing: .5px;
        }
        .msg-item.unread .msg-avatar { background: linear-gradient(135deg,#b91c1c,#e53e3e); }

        .msg-meta { flex: 1; min-width: 0; }
        .msg-name  { font-size: 13px; color: #2d3a55; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .msg-subj  { font-size: 12px; color: #7a88aa; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 2px; }
        .msg-date  { font-size: 10px; color: #aab; white-space: nowrap; flex-shrink: 0; }

        .unread-dot {
            width: 8px; height: 8px;
            background: var(--red);
            border-radius: 50%;
            position: absolute;
            right: 14px; top: 50%;
            transform: translateY(-50%);
            display: none;
        }
        .msg-item.unread .unread-dot { display: block; }

        /* ══ RIGHT PANEL ══ */
        .msg-detail-panel {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: #f8faff;
            min-width: 0;
        }

        /* Empty state */
        #emptyState {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #c0c8dc;
        }
        #emptyState i { font-size: 52px; margin-bottom: 14px; }
        #emptyState p  { font-size: 14px; margin: 0; }

        /* Loader */
        #loader {
            display: none;
            flex: 1;
            align-items: center;
            justify-content: center;
        }

        /* Content */
        #msgContent { display: none; flex-direction: column; flex: 1; min-height: 0; overflow-y: auto; }

        /* Detail header */
        .detail-header {
            background: linear-gradient(135deg,#002147,#004080);
            padding: 18px 24px;
            display: flex;
            align-items: center;
            gap: 14px;
            flex-shrink: 0;
        }
        .detail-avatar {
            width: 46px; height: 46px;
            border-radius: 50%;
            background: rgba(255,255,255,.18);
            border: 2px solid rgba(255,255,255,.3);
            color: #fff;
            font-size: 16px;
            font-weight: 700;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .detail-info { flex: 1; min-width: 0; }
        .detail-name  { font-size: 15px; font-weight: 700; color: #fff; margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .detail-email { font-size: 12px; color: rgba(255,255,255,.65); margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .detail-actions { display: flex; gap: 8px; flex-shrink: 0; }
        .detail-actions .btn {
            border-radius: 8px;
            font-size: 12px;
            padding: 6px 14px;
            font-weight: 600;
        }

        /* Subject strip */
        .detail-subject {
            padding: 10px 24px;
            background: #fff;
            border-bottom: 1px solid #e8ecf4;
            font-size: 13px;
            font-weight: 600;
            color: var(--marine);
            flex-shrink: 0;
        }
        .detail-subject small { font-weight: 400; color: #8896b0; margin-left: 6px; }

        /* Message body */
        .detail-body {
            padding: 24px;
            flex: 1;
        }
        .msg-bubble {
            background: #fff;
            border-radius: 14px;
            padding: 20px 24px;
            box-shadow: 0 2px 10px rgba(0,0,0,.05);
            font-size: 14px;
            line-height: 1.7;
            color: #3a4560;
            max-width: 720px;
            white-space: pre-line;
        }

        @media (max-width: 992px) {
            .msg-list-panel { width: 260px; }
        }
        /* Sur téléphone, la vue liste+détail côte à côte ne tient plus : on bascule
           en vue plein écran (liste OU détail), comme une appli de messagerie. */
        @media (max-width: 576px) {
            .msg-page { padding: 12px; }
            .msg-list-panel { width: 100%; }
            .msg-detail-panel { display: none; }
            .messenger.showing-detail .msg-list-panel { display: none; }
            .messenger.showing-detail .msg-detail-panel { display: flex; width: 100%; }
            .detail-back-btn { display: inline-flex !important; }
            .detail-header { flex-wrap: wrap; row-gap: 10px; padding: 14px 16px; }
            .detail-actions { width: 100%; justify-content: flex-end; }
        }

        /* ── Mode sombre (classes propres à cette page, non couvertes par theme.css) ── */
        html[data-theme="dark"] body { background: #14181f; }
        html[data-theme="dark"] .msg-topbar h2 { color: #e4e6eb; }
        html[data-theme="dark"] #totalBadge { background: #1e222b !important; color: #cfd3da !important; border-color: #2e333d !important; }
        html[data-theme="dark"] .messenger { background: #1e222b; box-shadow: 0 4px 24px rgba(0,0,0,.3); }
        html[data-theme="dark"] .msg-list-panel { border-right-color: #2e333d; }
        html[data-theme="dark"] .msg-list-header { background: #1e222b; }
        html[data-theme="dark"] .filter-btn { border-color: #3a4150; color: #9aa0aa; }
        html[data-theme="dark"] .filter-btn.active { background: var(--menu-color); border-color: var(--menu-color); color: #fff; }
        html[data-theme="dark"] .search-wrap input { background: #262b35; border-color: #3a4150; color: #e4e6eb; }
        html[data-theme="dark"] .search-wrap input::placeholder { color: #7a8291; }
        html[data-theme="dark"] .search-wrap input:focus { border-color: var(--menu-color); }
        html[data-theme="dark"] .msg-item { border-bottom-color: #262b35; }
        html[data-theme="dark"] .msg-item:hover { background: #262b35; }
        html[data-theme="dark"] .msg-item.active { background: #262b35; border-left-color: var(--menu-color); }
        html[data-theme="dark"] .msg-item.unread .msg-name { color: #cfe0ff; }
        html[data-theme="dark"] .msg-name { color: #e4e6eb; }
        html[data-theme="dark"] .msg-subj { color: #9aa0aa; }
        html[data-theme="dark"] .msg-date { color: #6b7280; }
        html[data-theme="dark"] .msg-detail-panel { background: #181c24; }
        html[data-theme="dark"] #emptyState { color: #4a515e; }
        html[data-theme="dark"] .detail-subject { background: #1e222b; border-bottom-color: #2e333d; color: #cfe0ff; }
        html[data-theme="dark"] .detail-subject small { color: #8896b0; }
        html[data-theme="dark"] .msg-bubble { background: #1e222b; color: #cfd3da; box-shadow: 0 2px 10px rgba(0,0,0,.2); }

        @media print {
            .app-sidebar, .app-topbar { display: none !important; }
        }
    </style>
</head>
<body>
<?php include('../includes/sidebar.php'); ?>

<div class="msg-page">

    <!-- Header -->
    <div class="msg-topbar">
        <div class="d-flex align-items-center gap-3">
            <?php if ($unreadCount > 0): ?>
            <span class="badge rounded-pill" style="background:#e53e3e;font-size:11px;"><?= $unreadCount ?> non lu<?= $unreadCount > 1 ? 's' : '' ?></span>
            <?php endif; ?>
        </div>
        <span class="badge rounded-pill text-bg-light border" style="font-size:11px;color:#6b7a99;" id="totalBadge">
            <?= $totalMessages ?> message<?= $totalMessages > 1 ? 's' : '' ?>
        </span>
    </div>

    <!-- Messenger -->
    <div class="messenger">

        <!-- Left: list -->
        <div class="msg-list-panel">
            <div class="msg-list-header">
                <div class="filters">
                    <button class="filter-btn active" onclick="applyFilter('all', this)">Tous</button>
                    <button class="filter-btn" onclick="applyFilter('unread', this)">
                        Non lus <?php if ($unreadCount > 0): ?><span style="background:#e53e3e;color:#fff;border-radius:10px;padding:0 5px;font-size:10px;"><?= $unreadCount ?></span><?php endif; ?>
                    </button>
                </div>
                <div class="search-wrap">
                    <i class="fa fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Rechercher..." onkeyup="filterMessages()">
                </div>
            </div>
            <div class="msg-scroll">
                <?php foreach ($messages as $msg):
                    $cls     = $msg['statut'] === 'non_lu' ? 'unread' : 'read';
                    $initials = strtoupper(mb_substr($msg['nom_visiteur'], 0, 1) . (strpos($msg['nom_visiteur'], ' ') !== false ? mb_substr(strrchr($msg['nom_visiteur'], ' '), 1, 1) : ''));
                ?>
                <div class="msg-item <?= $cls ?>" id="msg-item-<?= $msg['id'] ?>"
                     onclick="viewMessage(<?= htmlspecialchars(json_encode($msg)) ?>, this)">
                    <div class="msg-avatar"><?= $initials ?></div>
                    <div class="msg-meta">
                        <div class="msg-name"><?= htmlspecialchars($msg['nom_visiteur']) ?></div>
                        <div class="msg-subj"><?= htmlspecialchars($msg['sujet']) ?></div>
                    </div>
                    <div class="msg-date"><?= date('d/m H:i', strtotime($msg['date_envoi'])) ?></div>
                    <div class="unread-dot"></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Right: detail -->
        <div class="msg-detail-panel">

            <div id="emptyState">
                <i class="fa fa-envelope-open-text"></i>
                <p>Sélectionnez un message pour le lire</p>
            </div>

            <div id="loader" style="display:none;flex:1;align-items:center;justify-content:center;">
                <div class="spinner-border" style="color:var(--marine);" role="status"></div>
            </div>

            <div id="msgContent">
                <div class="detail-header">
                    <button type="button" class="detail-back-btn" id="detailBackBtn" style="display:none;background:rgba(255,255,255,.15);border:none;color:#fff;width:34px;height:34px;border-radius:50%;flex-shrink:0;">
                        <i class="fa fa-arrow-left"></i>
                    </button>
                    <div class="detail-avatar" id="detailAvatar"></div>
                    <div class="detail-info">
                        <p class="detail-name" id="detailName"></p>
                        <p class="detail-email" id="detailEmail"></p>
                    </div>
                    <div class="detail-actions">
                        <a id="replyBtn" href="#" class="btn btn-light btn-sm">
                            <i class="fa fa-reply me-1"></i>Répondre
                        </a>
                        <button onclick="deleteCurrentMessage()" class="btn btn-sm" style="background:rgba(229,62,62,.18);color:#fca5a5;border:1px solid rgba(229,62,62,.3);">
                            <i class="fa fa-trash me-1"></i>Supprimer
                        </button>
                    </div>
                </div>
                <div class="detail-subject">
                    <i class="fa fa-tag me-1" style="color:var(--red);"></i>
                    <span id="detailSubject"></span>
                    <small id="detailDate"></small>
                </div>
                <div class="detail-body">
                    <div class="msg-bubble" id="detailText"></div>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
document.getElementById('detailBackBtn').addEventListener('click', function () {
    document.querySelector('.messenger').classList.remove('showing-detail');
});

function getInitials(name) {
    const parts = name.trim().split(' ');
    return parts.length >= 2
        ? (parts[0][0] + parts[parts.length - 1][0]).toUpperCase()
        : name.substring(0, 2).toUpperCase();
}

function viewMessage(msg, element) {
    const content    = document.getElementById('msgContent');
    const loader     = document.getElementById('loader');
    const emptyState = document.getElementById('emptyState');
    const wasUnread  = element.classList.contains('unread');

    document.querySelectorAll('.msg-item').forEach(i => i.classList.remove('active'));
    element.classList.add('active');

    emptyState.style.display = 'none';
    content.style.display    = 'none';
    loader.style.display     = 'flex';

    // Sur téléphone (<=576px) : bascule en vue plein écran pour le détail
    document.querySelector('.messenger').classList.add('showing-detail');

    setTimeout(() => {
        loader.style.display  = 'none';
        content.style.display = 'flex';

        document.getElementById('detailAvatar').innerText  = getInitials(msg.nom_visiteur);
        document.getElementById('detailName').innerText    = msg.nom_visiteur;
        document.getElementById('detailEmail').innerText   = msg.email_visiteur;
        document.getElementById('detailSubject').innerText = msg.sujet;
        document.getElementById('detailDate').innerText    = new Date(msg.date_envoi).toLocaleDateString('fr-FR', {day:'2-digit', month:'long', year:'numeric', hour:'2-digit', minute:'2-digit'});
        document.getElementById('detailText').innerText    = msg.contenu;
        document.getElementById('replyBtn').href           = 'mailto:' + msg.email_visiteur + '?subject=RE: ' + encodeURIComponent(msg.sujet);

        if (wasUnread) {
            fetch('../php/mark_as_read.php?id=' + msg.id).then(() => {
                element.classList.remove('unread');
                element.classList.add('read');
                element.querySelector('.unread-dot').style.display = 'none';
                // Update sidebar badge
                const navBadge = document.getElementById('navBadgeMessages');
                if (navBadge) {
                    let n = parseInt(navBadge.innerText);
                    if (n > 1) navBadge.innerText = n - 1;
                    else navBadge.remove();
                }
            });
        }
    }, 300);
}

function filterMessages() {
    const q = document.getElementById('searchInput').value.toLowerCase();
    document.querySelectorAll('.msg-item').forEach(item => {
        item.style.display = item.innerText.toLowerCase().includes(q) ? '' : 'none';
    });
}

function applyFilter(type, btn) {
    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('.msg-item').forEach(item => {
        item.style.display = (type === 'all' || item.classList.contains('unread')) ? '' : 'none';
    });
}

function deleteCurrentMessage() {
    const active = document.querySelector('.msg-item.active');
    if (!active) return;

    if (!confirm('Supprimer ce message définitivement ?')) return;

    const msgId = active.id.replace('msg-item-', '');
    const csrfToken = <?= json_encode(csrf_generate()) ?>;
    const fd = new FormData();
    fd.append('id', msgId);
    fd.append('token', csrfToken);

    fetch('../php/delete_message.php', { method: 'POST', body: fd })
        .then(r => r.text())
        .then(data => {
            if (data.trim() !== 'success') { alert('Erreur serveur : ' + data); return; }
            active.style.transition = 'all .25s ease';
            active.style.opacity    = '0';
            active.style.transform  = 'translateX(-15px)';
            setTimeout(() => {
                active.remove();
                document.getElementById('msgContent').style.display = 'none';
                document.getElementById('emptyState').style.display = 'flex';
                const badge = document.getElementById('totalBadge');
                if (badge) {
                    let n = parseInt(badge.innerText);
                    badge.innerText = (n - 1) + ' message' + (n - 1 > 1 ? 's' : '');
                }
            }, 250);
        })
        .catch(() => alert('Erreur de connexion.'));
}
</script>

<script src="../js/bootstrap.bundle.min.js"></script>
</body>
</html>
