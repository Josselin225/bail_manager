<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once('../config/db.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$entityType = $_GET['type'] ?? '';
$entityId   = (int)($_GET['id'] ?? 0);
$allowedTypes = ['bailleur' => 'Bailleur', 'locataire' => 'Locataire', 'contrat' => 'Contrat', 'maison' => 'Maison'];

$entityLabel = null;
$entitySub   = '';
$documents   = [];

if (isset($allowedTypes[$entityType]) && $entityId > 0) {
    switch ($entityType) {
        case 'bailleur':
            $s = $pdo->prepare("SELECT nom, code_bailleur FROM bailleurs WHERE id = ?");
            $s->execute([$entityId]);
            if ($r = $s->fetch()) { $entityLabel = $r['nom']; $entitySub = $r['code_bailleur'] ?? ''; }
            break;
        case 'locataire':
            $s = $pdo->prepare("SELECT nom, telephone1 FROM locataires WHERE id = ?");
            $s->execute([$entityId]);
            if ($r = $s->fetch()) { $entityLabel = $r['nom']; $entitySub = $r['telephone1'] ?? ''; }
            break;
        case 'maison':
            $s = $pdo->prepare("SELECT designation, adresse FROM maisons WHERE id = ?");
            $s->execute([$entityId]);
            if ($r = $s->fetch()) { $entityLabel = $r['designation']; $entitySub = $r['adresse'] ?? ''; }
            break;
        case 'contrat':
            $s = $pdo->prepare(
                "SELECT m.designation AS maison, l.nom AS locataire
                 FROM contrats c JOIN maisons m ON c.maison_id=m.id JOIN locataires l ON c.locataire_id=l.id
                 WHERE c.id = ?"
            );
            $s->execute([$entityId]);
            if ($r = $s->fetch()) { $entityLabel = $r['maison']; $entitySub = 'Locataire : ' . $r['locataire']; }
            break;
    }

    if ($entityLabel !== null) {
        $stmtDocs = $pdo->prepare("SELECT d.*, u.nom_complet AS auteur FROM documents d LEFT JOIN users u ON d.uploaded_by = u.id WHERE d.entity_type = ? AND d.entity_id = ? ORDER BY d.created_at DESC");
        $stmtDocs->execute([$entityType, $entityId]);
        $documents = $stmtDocs->fetchAll();
    }
}

function iconForMime(?string $mime): string {
    if ($mime === 'application/pdf') return 'fa-file-pdf text-danger';
    if (str_starts_with((string)$mime, 'image/')) return 'fa-file-image text-primary';
    return 'fa-file';
}
function formatSize(?int $bytes): string {
    if (!$bytes) return '';
    if ($bytes < 1024) return $bytes . ' o';
    if ($bytes < 1024*1024) return round($bytes/1024, 1) . ' Ko';
    return round($bytes/1024/1024, 1) . ' Mo';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../favicon.svg">
    <title>Documents — BailManager</title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/fontawesome/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        :root { --marine:#002147; }
        .main-content { background:#f4f7fe; min-height:100vh; padding:28px; }
        .doc-card { background:#fff; border-radius:14px; border:1px solid #e8ecf4; box-shadow:0 2px 10px rgba(0,0,0,.06); padding:24px; margin-bottom:20px; }
        .doc-header { display:flex; align-items:center; gap:14px; margin-bottom:4px; }
        .doc-icon { width:44px; height:44px; border-radius:12px; background:#eef2fb; color:var(--marine); display:flex; align-items:center; justify-content:center; font-size:18px; flex-shrink:0; }
        .doc-title { font-size:16px; font-weight:700; color:#2d3a55; margin:0; }
        .doc-sub { font-size:12px; color:#8896b0; }
        .doc-row { display:flex; align-items:center; gap:12px; padding:12px 14px; border:1px solid #eef1f8; border-radius:10px; margin-bottom:10px; background:#fafbfe; }
        .doc-row .doc-name { font-size:13px; font-weight:600; color:#2d3a55; }
        .doc-row .doc-meta { font-size:11px; color:#8896b0; }
        html[data-theme="dark"] .doc-card { background:#1e222b !important; border-color:#2e333d !important; }
        html[data-theme="dark"] .doc-title { color:#e4e6eb !important; }
        html[data-theme="dark"] .doc-icon { background:#262b35 !important; }
        html[data-theme="dark"] .doc-row { background:#20242e !important; border-color:#2e333d !important; }
        html[data-theme="dark"] .doc-row .doc-name { color:#e4e6eb !important; }
    </style>
</head>
<body>
<?php include('../includes/sidebar.php'); ?>

<div class="main-content">
    <div class="row justify-content-center">
        <div class="col-lg-7">

            <?php if ($entityLabel === null): ?>
                <div class="doc-card text-center">
                    <i class="fa fa-folder-open mb-3" style="font-size:40px;color:#c0c8dc;"></i>
                    <p class="text-muted mb-3">Ouvrez la gestion documentaire depuis la fiche d'un bailleur, d'un locataire, d'une maison ou d'un contrat (icône <i class="fa fa-paperclip"></i>).</p>
                    <div class="d-flex justify-content-center gap-2 flex-wrap">
                        <a href="bailleurs.php" class="btn btn-outline-secondary btn-sm">Bailleurs</a>
                        <a href="locataires.php" class="btn btn-outline-secondary btn-sm">Locataires</a>
                        <a href="maisons.php" class="btn btn-outline-secondary btn-sm">Maisons</a>
                        <a href="contrats.php" class="btn btn-outline-secondary btn-sm">Contrats</a>
                    </div>
                </div>
            <?php else: ?>

                <div class="doc-card">
                    <div class="doc-header">
                        <div class="doc-icon"><i class="fa fa-folder-open"></i></div>
                        <div>
                            <h5 class="doc-title"><?= htmlspecialchars($entityLabel) ?></h5>
                            <div class="doc-sub"><?= htmlspecialchars($allowedTypes[$entityType]) ?><?= $entitySub ? ' · ' . htmlspecialchars($entitySub) : '' ?></div>
                        </div>
                    </div>
                </div>

                <div class="doc-card">
                    <h6 class="fw-bold mb-3" style="color:#2d3a55;"><i class="fa fa-upload me-2" style="color:var(--marine);"></i>Ajouter un document</h6>
                    <form action="../php/upload_document.php" method="POST" enctype="multipart/form-data" class="d-flex gap-2 flex-wrap">
                        <input type="hidden" name="token" value="<?= csrf_generate() ?>">
                        <input type="hidden" name="entity_type" value="<?= htmlspecialchars($entityType) ?>">
                        <input type="hidden" name="entity_id" value="<?= $entityId ?>">
                        <input type="file" name="document" class="form-control" accept=".jpg,.jpeg,.png,.webp,.pdf" required style="max-width:320px;">
                        <button type="submit" class="btn btn-danger"><i class="fa fa-upload me-1"></i>Envoyer</button>
                    </form>
                    <small class="text-muted d-block mt-2" style="font-size:11px;">Formats acceptés : JPG, PNG, WEBP, PDF — 8 Mo maximum.</small>
                </div>

                <div class="doc-card">
                    <h6 class="fw-bold mb-3" style="color:#2d3a55;"><i class="fa fa-paperclip me-2" style="color:var(--marine);"></i>Documents (<?= count($documents) ?>)</h6>
                    <?php if (empty($documents)): ?>
                        <p class="text-muted small mb-0">Aucun document pour le moment.</p>
                    <?php else: ?>
                        <?php foreach ($documents as $d): ?>
                        <div class="doc-row">
                            <i class="fa <?= iconForMime($d['type_mime']) ?>" style="font-size:22px;"></i>
                            <div class="flex-grow-1 min-w-0">
                                <div class="doc-name text-truncate"><?= htmlspecialchars($d['nom_original']) ?></div>
                                <div class="doc-meta"><?= date('d/m/Y H:i', strtotime($d['created_at'])) ?> · <?= formatSize($d['taille']) ?><?= $d['auteur'] ? ' · ' . htmlspecialchars($d['auteur']) : '' ?></div>
                            </div>
                            <a href="../uploads/documents/<?= htmlspecialchars($d['nom_fichier']) ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="Ouvrir"><i class="fa fa-eye"></i></a>
                            <form action="../php/delete_document.php" method="POST" onsubmit="return confirm('Supprimer ce document ?');">
                                <input type="hidden" name="token" value="<?= csrf_generate() ?>">
                                <input type="hidden" name="id" value="<?= $d['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Supprimer"><i class="fa fa-trash"></i></button>
                            </form>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            <?php endif; ?>

        </div>
    </div>
</div>

<script src="../js/bootstrap.bundle.min.js"></script>
</body>
</html>
