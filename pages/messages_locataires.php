<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once('../config/db.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$locataire_id = (int)($_GET['locataire_id'] ?? 0);

// Liste des locataires ayant échangé au moins un message, avec dernier message et compteur de non-lus
$threads = $pdo->query(
    "SELECT l.id, l.nom, l.telephone1,
            MAX(ml.created_at) AS dernier_le,
            (SELECT contenu FROM messages_locataires WHERE locataire_id = l.id ORDER BY created_at DESC LIMIT 1) AS dernier_contenu,
            SUM(CASE WHEN ml.expediteur = 'locataire' AND ml.lu = 0 THEN 1 ELSE 0 END) AS non_lus
     FROM locataires l
     JOIN messages_locataires ml ON ml.locataire_id = l.id
     GROUP BY l.id, l.nom, l.telephone1
     ORDER BY dernier_le DESC"
)->fetchAll();

$locataireActif = null;
$thread = [];
if ($locataire_id > 0) {
    $stmtL = $pdo->prepare("SELECT * FROM locataires WHERE id = ?");
    $stmtL->execute([$locataire_id]);
    $locataireActif = $stmtL->fetch();

    if ($locataireActif) {
        $stmtT = $pdo->prepare("SELECT * FROM messages_locataires WHERE locataire_id = ? ORDER BY created_at ASC");
        $stmtT->execute([$locataire_id]);
        $thread = $stmtT->fetchAll();

        // Marquer comme lus les messages du locataire non encore vus
        $pdo->prepare("UPDATE messages_locataires SET lu = 1 WHERE locataire_id = ? AND expediteur = 'locataire' AND lu = 0")
            ->execute([$locataire_id]);
    }
}

$totalNonLus = (int)$pdo->query("SELECT COUNT(*) FROM messages_locataires WHERE expediteur='locataire' AND lu=0")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../favicon.svg">
    <title>Messages Locataires — BailManager</title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/fontawesome/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        :root { --marine:#002147; }
        .main-content { background:#f4f7fe; padding:24px; height:100vh; box-sizing:border-box; display:flex; flex-direction:column; }
        .ml-wrap { display:flex; flex:1; min-height:0; border-radius:14px; overflow:hidden; box-shadow:0 4px 24px rgba(0,0,0,.08); background:#fff; }
        .ml-list { width:300px; flex-shrink:0; border-right:1px solid #e8ecf4; overflow-y:auto; }
        .ml-thread-item { display:block; padding:13px 16px; border-bottom:1px solid #f0f3fa; text-decoration:none; color:inherit; }
        .ml-thread-item:hover { background:#f4f7fe; }
        .ml-thread-item.active { background:#eef2fb; border-left:3px solid var(--marine); }
        .ml-name { font-size:13px; font-weight:700; color:#2d3a55; display:flex; align-items:center; justify-content:space-between; gap:6px; }
        .ml-preview { font-size:12px; color:#7a88aa; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-top:2px; }
        .ml-badge { background:#e53e3e; color:#fff; border-radius:10px; font-size:10px; padding:1px 7px; flex-shrink:0; }
        .ml-detail { flex:1; display:flex; flex-direction:column; min-width:0; background:#f8faff; }
        .ml-detail-header { background:linear-gradient(135deg,#002147,#004080); color:#fff; padding:16px 22px; flex-shrink:0; }
        .ml-detail-body { flex:1; overflow-y:auto; padding:22px; display:flex; flex-direction:column; gap:12px; }
        .bubble { max-width:65%; padding:12px 16px; border-radius:14px; font-size:13.5px; line-height:1.55; }
        .bubble-locataire { align-self:flex-start; background:#fff; box-shadow:0 2px 8px rgba(0,0,0,.05); color:#2d3a55; border-bottom-left-radius:4px; }
        .bubble-agence { align-self:flex-end; background:var(--marine); color:#fff; border-bottom-right-radius:4px; }
        .bubble-time { font-size:10px; opacity:.6; margin-top:5px; }
        .ml-reply { border-top:1px solid #e8ecf4; background:#fff; padding:14px 20px; flex-shrink:0; }
        #emptyState { flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; color:#c0c8dc; }
        #emptyState i { font-size:48px; margin-bottom:14px; }

        html[data-theme="dark"] .ml-wrap { background:#1e222b; }
        html[data-theme="dark"] .ml-list { border-right-color:#2e333d; }
        html[data-theme="dark"] .ml-thread-item { border-bottom-color:#262b35; }
        html[data-theme="dark"] .ml-thread-item:hover, html[data-theme="dark"] .ml-thread-item.active { background:#262b35; }
        html[data-theme="dark"] .ml-name { color:#e4e6eb; }
        html[data-theme="dark"] .ml-preview { color:#9aa0aa; }
        html[data-theme="dark"] .ml-detail { background:#181c24; }
        html[data-theme="dark"] .bubble-locataire { background:#262b35; color:#e4e6eb; }
        html[data-theme="dark"] .ml-reply { background:#1e222b; border-top-color:#2e333d; }
        html[data-theme="dark"] #emptyState { color:#4a515e; }
    </style>
</head>
<body>
<?php include('../includes/sidebar.php'); ?>

<div class="main-content">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h5 class="fw-bold mb-0" style="color:var(--marine);"><i class="fa fa-comments me-2"></i>Messages Locataires</h5>
        <?php if ($totalNonLus > 0): ?>
        <span class="badge rounded-pill" style="background:#e53e3e;"><?= $totalNonLus ?> non lu<?= $totalNonLus>1?'s':'' ?></span>
        <?php endif; ?>
    </div>

    <div class="ml-wrap">
        <div class="ml-list">
            <?php if (empty($threads)): ?>
                <div class="text-center text-muted small p-4">Aucun échange pour le moment.<br>Les locataires peuvent écrire depuis leur espace.</div>
            <?php else: ?>
                <?php foreach ($threads as $t): ?>
                <a href="?locataire_id=<?= $t['id'] ?>" class="ml-thread-item <?= $t['id']==$locataire_id?'active':'' ?>">
                    <div class="ml-name">
                        <span><?= htmlspecialchars($t['nom']) ?></span>
                        <?php if ($t['non_lus'] > 0): ?><span class="ml-badge"><?= $t['non_lus'] ?></span><?php endif; ?>
                    </div>
                    <div class="ml-preview"><?= htmlspecialchars(mb_strimwidth($t['dernier_contenu'], 0, 60, '…')) ?></div>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="ml-detail">
            <?php if (!$locataireActif): ?>
                <div id="emptyState">
                    <i class="fa fa-comments"></i>
                    <p>Sélectionnez un locataire pour voir la conversation</p>
                </div>
            <?php else: ?>
                <div class="ml-detail-header">
                    <div class="fw-bold"><?= htmlspecialchars($locataireActif['nom']) ?></div>
                    <div class="small opacity-75"><?= htmlspecialchars($locataireActif['telephone1'] ?? '') ?></div>
                </div>
                <div class="ml-detail-body">
                    <?php foreach ($thread as $m): ?>
                        <div class="bubble bubble-<?= $m['expediteur'] ?>">
                            <?= nl2br(htmlspecialchars($m['contenu'])) ?>
                            <div class="bubble-time"><?= date('d/m/Y H:i', strtotime($m['created_at'])) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <form class="ml-reply d-flex gap-2" action="../php/repondre_message_locataire.php" method="POST">
                    <input type="hidden" name="token" value="<?= csrf_generate() ?>">
                    <input type="hidden" name="locataire_id" value="<?= $locataireActif['id'] ?>">
                    <input type="text" name="contenu" class="form-control" placeholder="Écrire une réponse…" maxlength="2000" required autocomplete="off">
                    <button type="submit" class="btn btn-danger px-4"><i class="fa fa-paper-plane"></i></button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="../js/bootstrap.bundle.min.js"></script>
</body>
</html>
