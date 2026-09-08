<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { header('Location: dashboard.php'); exit(); }
require_once('../config/db.php');

// 1. RÉCUPÉRATION DES DONNÉES ACTUELLES
$stmt = $pdo->query("SELECT * FROM settings WHERE id = 1");
$current = $stmt->fetch();

$clauses = $pdo->query("SELECT * FROM clauses_contrat ORDER BY ordre_affichage ASC, id ASC")->fetchAll();
$clausesMandat = $pdo->query("SELECT * FROM clauses_mandat ORDER BY ordre_affichage ASC, id ASC")->fetchAll();
$articlesBailCI = require('../config/articles_bail_ci.php');
$titresClausesExistantes = array_flip(array_map(fn($cl) => $cl['titre'], $clauses));

// 2. TRAITEMENT DU FORMULAIRE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();

    $nom = $_POST['nom_entreprise'];
    $email = $_POST['contact_email'];
    $tel = $_POST['contact_telephone'];
    $taux = $_POST['taux_commission'];
    $adresse = $_POST['adresse_siege'];
    $rappels_actifs = isset($_POST['rappels_actifs']) ? 1 : 0;
    $activites = trim($_POST['activites'] ?? '');
    $cc_numero = trim($_POST['cc_numero'] ?? '');
    $regime_imposition = trim($_POST['regime_imposition'] ?? '');
    $rccm_numero = trim($_POST['rccm_numero'] ?? '');
    $compte_bancaire = trim($_POST['compte_bancaire'] ?? '');
    $iban = trim($_POST['iban'] ?? '');
    $swift = trim($_POST['swift'] ?? '');
    $site_web = trim($_POST['site_web'] ?? '');

    // Par défaut, on garde l'ancien logo stocké en DB
    $logo_name = $current['logo_url'];

    // Gestion du téléchargement du nouveau logo
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === 0) {
        $upload_dir = "../uploads/";

        // Créer le dossier s'il n'existe pas
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        if (uploadDepasseLimite($_FILES['logo'])) {
            flash('error', "Le logo dépasse la taille maximale autorisée (8 Mo).");
            header('Location: settings.php');
            exit();
        }

        $mime = mime_content_type($_FILES['logo']['tmp_name']);
        $ext  = mimeToImageExt($mime);

        if ($ext !== null) {
            $new_name = "logo_" . time() . "." . $ext;

            if (move_uploaded_file($_FILES['logo']['tmp_name'], $upload_dir . $new_name)) {
                $logo_name = $new_name; // Nouveau nom à enregistrer
            }
        } else {
            flash('error', "Format de logo non autorisé.");
            header('Location: settings.php');
            exit();
        }
    }

    // 3. MISE À JOUR DE LA BASE DE DONNÉES
    $sql = "UPDATE settings SET
            nom_entreprise = ?, contact_email = ?, contact_telephone = ?,
            taux_commission = ?, rappels_actifs = ?, adresse_siege = ?, logo_url = ?,
            activites = ?, cc_numero = ?, regime_imposition = ?, rccm_numero = ?,
            compte_bancaire = ?, iban = ?, swift = ?, site_web = ?
            WHERE id = 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $nom, $email, $tel, $taux, $rappels_actifs, $adresse, $logo_name,
        $activites, $cc_numero, $regime_imposition, $rccm_numero,
        $compte_bancaire, $iban, $swift, $site_web,
    ]);

    flash('success', "Paramètres mis à jour avec succès !");
    header('Location: settings.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../favicon.svg">
    <title>Paramètres - BailManager</title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/fontawesome/all.min.css">
    <style>
        :root { --marine: #000080; }

        .main-content { background:#f4f7fe; height:calc(100vh - var(--tb-h, 60px)); display:flex; flex-direction:column; overflow:hidden; }
        .settings-top { padding:0 28px 16px; flex-shrink:0; }
        .settings-scroll { flex:1; overflow-y:auto; padding:0 28px 28px; }

        /* ── Barre d'onglets ─────────────────────────────────────────── */
        .settings-tabs { display:flex; flex-wrap:wrap; gap:4px; background:#fff; border:1px solid #e8ecf4; border-radius:13px; padding:5px; box-shadow:0 2px 10px rgba(0,0,0,.05); width:fit-content; max-width:100%; }
        .settings-tab { display:flex; align-items:center; gap:9px; border:none; background:transparent; padding:10px 18px; border-radius:9px; font-size:13px; font-weight:600; color:#6b7a99; white-space:nowrap; transition:.15s; }
        .settings-tab:hover:not(.active) { background:#f4f7fe; color:var(--marine); }
        .settings-tab.active { background:var(--marine); color:#fff; box-shadow:0 3px 10px rgba(0,0,128,.25); }
        .settings-tab i { font-size:13px; width:15px; text-align:center; flex:none; }
        .settings-tab-badge { background:rgba(0,0,0,.06); color:inherit; font-size:10.5px; font-weight:700; padding:1px 7px; border-radius:10px; }
        .settings-tab.active .settings-tab-badge { background:rgba(255,255,255,.22); }

        html[data-theme="dark"] .main-content { background:#161a22 !important; }
        html[data-theme="dark"] .settings-tabs { background:#1e222b !important; border-color:#2e333d !important; }
        html[data-theme="dark"] .settings-tab { color:#9aa4b6 !important; }
        html[data-theme="dark"] .settings-tab:hover:not(.active) { background:#262b35 !important; color:#e4e6eb !important; }
        html[data-theme="dark"] .card { background:#1e222b !important; border-color:#2e333d !important; }
        html[data-theme="dark"] h5, html[data-theme="dark"] h6 { color:#e4e6eb !important; }
        html[data-theme="dark"] .form-control, html[data-theme="dark"] .form-select { background:#20242e !important; border-color:#2e333d !important; color:#e4e6eb !important; }
        html[data-theme="dark"] .table { color:#c3cad9 !important; }
        html[data-theme="dark"] .table thead { color:#9aa4b6 !important; }
        html[data-theme="dark"] .table > :not(caption) > * > * { background:transparent !important; border-color:#2e333d !important; }
    </style>
</head>
<body>
<?php include('../includes/sidebar.php'); ?>

<!-- MODAL AJOUT CLAUSE -->
<div class="modal fade" id="modalAddClause" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form class="modal-content border-0 shadow-lg" action="../php/add_clause.php" method="POST">
            <input type="hidden" name="token" value="<?= csrf_generate() ?>">
            <div class="modal-header text-white border-0" style="background:linear-gradient(135deg,#000080,#0000b3);">
                <h5 class="modal-title fw-bold mb-0">Ajouter une clause</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 py-4">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label fw-semibold small text-muted text-uppercase">Titre <span class="text-danger">*</span></label>
                        <input type="text" name="titre" class="form-control" placeholder="Ex: Préavis de résiliation" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small text-muted text-uppercase">Ordre d'affichage</label>
                        <input type="number" name="ordre_affichage" class="form-control" value="<?= count($clauses) + 1 ?>" min="0">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold small text-muted text-uppercase">Contenu de la clause <span class="text-danger">*</span></label>
                        <textarea name="contenu" class="form-control" rows="4" required placeholder="Texte de la clause tel qu'il apparaîtra sur le contrat…"></textarea>
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="actif" value="1" id="addClauseActif" checked>
                            <label class="form-check-label" for="addClauseActif">Clause active (affichée sur le contrat)</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top bg-light px-4">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" class="btn btn-primary px-5 fw-semibold" style="background-color:var(--marine); border:none;">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL MODIFIER CLAUSE -->
<div class="modal fade" id="modalEditClause" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form class="modal-content border-0 shadow-lg" action="../php/update_clause.php" method="POST">
            <input type="hidden" name="token" value="<?= csrf_generate() ?>">
            <input type="hidden" name="id" id="editClauseId">
            <div class="modal-header text-white border-0" style="background:linear-gradient(135deg,#000080,#0000b3);">
                <h5 class="modal-title fw-bold mb-0">Modifier la clause</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 py-4">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label fw-semibold small text-muted text-uppercase">Titre <span class="text-danger">*</span></label>
                        <input type="text" name="titre" id="editClauseTitre" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small text-muted text-uppercase">Ordre d'affichage</label>
                        <input type="number" name="ordre_affichage" id="editClauseOrdre" class="form-control" min="0">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold small text-muted text-uppercase">Contenu de la clause <span class="text-danger">*</span></label>
                        <textarea name="contenu" id="editClauseContenu" class="form-control" rows="4" required></textarea>
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="actif" value="1" id="editClauseActif">
                            <label class="form-check-label" for="editClauseActif">Clause active (affichée sur le contrat)</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top bg-light px-4">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" class="btn btn-primary px-5 fw-semibold" style="background-color:var(--marine); border:none;">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL AJOUT CLAUSE MANDAT -->
<div class="modal fade" id="modalAddClauseMandat" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form class="modal-content border-0 shadow-lg" action="../php/add_clause_mandat.php" method="POST">
            <input type="hidden" name="token" value="<?= csrf_generate() ?>">
            <div class="modal-header text-white border-0" style="background:linear-gradient(135deg,#000080,#0000b3);">
                <h5 class="modal-title fw-bold mb-0">Ajouter une clause de mandat</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 py-4">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label fw-semibold small text-muted text-uppercase">Titre <span class="text-danger">*</span></label>
                        <input type="text" name="titre" class="form-control" placeholder="Ex: Confidentialité" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small text-muted text-uppercase">Ordre d'affichage</label>
                        <input type="number" name="ordre_affichage" class="form-control" value="<?= count($clausesMandat) + 1 ?>" min="0">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold small text-muted text-uppercase">Contenu de la clause <span class="text-danger">*</span></label>
                        <textarea name="contenu" class="form-control" rows="4" required placeholder="Texte de la clause tel qu'il apparaîtra sur le mandat…"></textarea>
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="actif" value="1" id="addClauseMandatActif" checked>
                            <label class="form-check-label" for="addClauseMandatActif">Clause active (affichée sur le mandat)</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top bg-light px-4">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" class="btn btn-primary px-5 fw-semibold" style="background-color:var(--marine); border:none;">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL MODIFIER CLAUSE MANDAT -->
<div class="modal fade" id="modalEditClauseMandat" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form class="modal-content border-0 shadow-lg" action="../php/update_clause_mandat.php" method="POST">
            <input type="hidden" name="token" value="<?= csrf_generate() ?>">
            <input type="hidden" name="id" id="editClauseMandatId">
            <div class="modal-header text-white border-0" style="background:linear-gradient(135deg,#000080,#0000b3);">
                <h5 class="modal-title fw-bold mb-0">Modifier la clause de mandat</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 py-4">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label fw-semibold small text-muted text-uppercase">Titre <span class="text-danger">*</span></label>
                        <input type="text" name="titre" id="editClauseMandatTitre" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small text-muted text-uppercase">Ordre d'affichage</label>
                        <input type="number" name="ordre_affichage" id="editClauseMandatOrdre" class="form-control" min="0">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold small text-muted text-uppercase">Contenu de la clause <span class="text-danger">*</span></label>
                        <textarea name="contenu" id="editClauseMandatContenu" class="form-control" rows="4" required></textarea>
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="actif" value="1" id="editClauseMandatActif">
                            <label class="form-check-label" for="editClauseMandatActif">Clause active (affichée sur le mandat)</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top bg-light px-4">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" class="btn btn-primary px-5 fw-semibold" style="background-color:var(--marine); border:none;">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<div class="main-content">
<div class="settings-top top-fixed">
    <div class="settings-tabs" role="tablist">
        <button class="settings-tab active" data-bs-toggle="tab" data-bs-target="#tabAgence" type="button" role="tab" aria-selected="true">
            <i class="fa fa-building"></i>Agence
        </button>
        <button class="settings-tab" data-bs-toggle="tab" data-bs-target="#tabClausesBail" type="button" role="tab" aria-selected="false">
            <i class="fa fa-file-contract"></i>Clauses du bail <span class="settings-tab-badge"><?= count($clauses) ?></span>
        </button>
        <button class="settings-tab" data-bs-toggle="tab" data-bs-target="#tabClausesMandat" type="button" role="tab" aria-selected="false">
            <i class="fa fa-file-signature"></i>Clauses du mandat <span class="settings-tab-badge"><?= count($clausesMandat) ?></span>
        </button>
        <button class="settings-tab" data-bs-toggle="tab" data-bs-target="#tabBiblioLegale" type="button" role="tab" aria-selected="false">
            <i class="fa fa-scale-balanced"></i>Bibliothèque légale
        </button>
    </div>
</div>

<div class="settings-scroll">
<div class="tab-content">

    <div class="tab-pane fade show active" id="tabAgence" role="tabpanel">
            <div class="card shadow-sm border-0 p-4">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="token" value="<?= csrf_generate() ?>">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label fw-bold">Logo de l'agence</label>
                            <div class="d-flex align-items-center gap-3">
                                <input type="file" name="logo" class="form-control" style="max-width:420px;">
                                <?php if(!empty($current['logo_url'])): ?>
                                    <div class="p-1 border bg-light rounded flex-shrink-0 d-flex align-items-center justify-content-center" style="width:48px;height:48px;">
                                        <img src="../uploads/<?= htmlspecialchars($current['logo_url']) ?>" style="max-width:100%;max-height:100%;object-fit:contain;">
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Nom de l'agence / Entreprise</label>
                            <input type="text" name="nom_entreprise" class="form-control" value="<?= htmlspecialchars($current['nom_entreprise'] ?? '') ?>" required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Commission sur loyer payé (%)</label>
                            <div class="input-group">
                                <input type="number" step="0.01" name="taux_commission" class="form-control" value="<?= $current['taux_commission'] ?? '10.00' ?>" required>
                                <span class="input-group-text bg-light">%</span>
                            </div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Email de contact professionnel</label>
                            <input type="email" name="contact_email" class="form-control" value="<?= htmlspecialchars($current['contact_email'] ?? '') ?>" required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Téléphone de l'agence</label>
                            <input type="text" name="contact_telephone" class="form-control" value="<?= htmlspecialchars($current['contact_telephone'] ?? '') ?>" placeholder="+225 0102030405">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold d-block">Rappels automatiques</label>
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" role="switch" name="rappels_actifs" id="rappels_actifs" <?= !empty($current['rappels_actifs']) ? 'checked' : '' ?>>
                                <label class="form-check-label small text-muted" for="rappels_actifs">Envoyer chaque jour un rappel de loyer aux locataires (si email renseigné) et une synthèse à l'agence</label>
                            </div>
                        </div>

                        <div class="col-md-12 mb-3">
                            <label class="form-label fw-bold">Adresse du Siège Social</label>
                            <textarea name="adresse_siege" class="form-control" rows="2"><?= htmlspecialchars($current['adresse_siege'] ?? '') ?></textarea>
                        </div>

                        <div class="col-12"><hr class="mt-0"><h6 class="fw-bold text-muted text-uppercase small mb-3"><i class="fa fa-scale-balanced me-2"></i>Informations légales et bancaires (en-tête / pied de page des documents)</h6></div>

                        <div class="col-md-12 mb-3">
                            <label class="form-label fw-bold">Activités (une par ligne, affichées dans l'en-tête)</label>
                            <textarea name="activites" class="form-control" rows="5" placeholder="LOTISSEMENTS&#10;BATIMENTS TRAVAUX PUBLICS&#10;IMPORT &amp; EXPORT"><?= htmlspecialchars($current['activites'] ?? '') ?></textarea>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">CC N° (Compte Contribuable)</label>
                            <input type="text" name="cc_numero" class="form-control" value="<?= htmlspecialchars($current['cc_numero'] ?? '') ?>">
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Régime d'imposition</label>
                            <input type="text" name="regime_imposition" class="form-control" value="<?= htmlspecialchars($current['regime_imposition'] ?? '') ?>" placeholder="TEE – CDI / Yamoussoukro">
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">N° RCCM</label>
                            <input type="text" name="rccm_numero" class="form-control" value="<?= htmlspecialchars($current['rccm_numero'] ?? '') ?>">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Compte bancaire</label>
                            <input type="text" name="compte_bancaire" class="form-control" value="<?= htmlspecialchars($current['compte_bancaire'] ?? '') ?>" placeholder="Banque N°XXXXXXXXXXXX">
                        </div>

                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-bold">IBAN</label>
                            <input type="text" name="iban" class="form-control" value="<?= htmlspecialchars($current['iban'] ?? '') ?>">
                        </div>

                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-bold">SWIFT</label>
                            <input type="text" name="swift" class="form-control" value="<?= htmlspecialchars($current['swift'] ?? '') ?>">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Site web</label>
                            <input type="text" name="site_web" class="form-control" value="<?= htmlspecialchars($current['site_web'] ?? '') ?>" placeholder="www.exemple.com">
                        </div>
                    </div>

                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary px-5 shadow-sm" style="background-color: var(--marine); border: none;">
                            <i class="fa fa-save me-2"></i> Enregistrer les modifications
                        </button>
                    </div>
                </form>
            </div>
    </div>

    <div class="tab-pane fade" id="tabClausesBail" role="tabpanel">
            <div class="card shadow-sm border-0 p-4">
                <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
                    <h5 class="fw-bold mb-0"><i class="fa fa-file-contract me-2" style="color:var(--marine);"></i>Clauses du contrat de bail</h5>
                    <button type="button" class="btn btn-sm btn-primary" style="background-color: var(--marine); border:none;" data-bs-toggle="modal" data-bs-target="#modalAddClause">
                        <i class="fa fa-plus me-1"></i> Ajouter une clause
                    </button>
                </div>
                <p class="text-muted small">Ces clauses apparaissent, dans l'ordre ci-dessous, sur le contrat imprimé (section « Principales clauses »). Seules les clauses actives sont affichées.</p>

                <?php if (empty($clauses)): ?>
                <div class="text-center text-muted py-4"><i class="fa fa-inbox fa-2x mb-2 d-block" style="opacity:.2;"></i>Aucune clause enregistrée.</div>
                <?php else: ?>
                <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th style="width:6%;">Ordre</th>
                            <th style="width:22%;">Titre</th>
                            <th>Contenu</th>
                            <th style="width:8%;" class="text-center">Statut</th>
                            <th style="width:10%;" class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($clauses as $cl): ?>
                    <tr>
                        <td class="text-muted"><?= (int)$cl['ordre_affichage'] ?></td>
                        <td class="fw-semibold"><?= htmlspecialchars($cl['titre']) ?></td>
                        <td class="text-muted small"><?= htmlspecialchars(mb_strimwidth($cl['contenu'], 0, 120, '…')) ?></td>
                        <td class="text-center">
                            <?php if ($cl['actif']): ?><span class="badge bg-success-subtle text-success border">Active</span>
                            <?php else: ?><span class="badge bg-secondary-subtle text-secondary border">Inactive</span><?php endif; ?>
                        </td>
                        <td class="text-center text-nowrap">
                            <button type="button" class="btn btn-sm btn-outline-primary btn-edit-clause" title="Modifier"
                                    data-bs-toggle="modal" data-bs-target="#modalEditClause"
                                    data-id="<?= (int)$cl['id'] ?>"
                                    data-titre="<?= htmlspecialchars($cl['titre']) ?>"
                                    data-contenu="<?= htmlspecialchars($cl['contenu']) ?>"
                                    data-ordre="<?= (int)$cl['ordre_affichage'] ?>"
                                    data-actif="<?= (int)$cl['actif'] ?>">
                                <i class="fa fa-edit"></i>
                            </button>
                            <form action="../php/delete_clause.php" method="POST" style="display:inline" onsubmit="return confirm('Supprimer cette clause ?')">
                                <input type="hidden" name="token" value="<?= csrf_generate() ?>">
                                <input type="hidden" name="id" value="<?= $cl['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Supprimer"><i class="fa fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <?php endif; ?>
            </div>
    </div>

    <div class="tab-pane fade" id="tabClausesMandat" role="tabpanel">
            <div class="card shadow-sm border-0 p-4">
                <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
                    <h5 class="fw-bold mb-0"><i class="fa fa-file-signature me-2" style="color:var(--marine);"></i>Clauses du mandat de gestion</h5>
                    <button type="button" class="btn btn-sm btn-primary" style="background-color: var(--marine); border:none;" data-bs-toggle="modal" data-bs-target="#modalAddClauseMandat">
                        <i class="fa fa-plus me-1"></i> Ajouter une clause
                    </button>
                </div>
                <p class="text-muted small">Ces clauses apparaissent, dans l'ordre ci-dessous, sur le mandat de gestion imprimé (section « Principales clauses »). Seules les clauses actives sont affichées. Les clauses « Rémunération » et « Durée et renouvellement » sont générées automatiquement à partir des données de chaque mandat (taux de commission, dates) et ne figurent pas ici.</p>

                <?php if (empty($clausesMandat)): ?>
                <div class="text-center text-muted py-4"><i class="fa fa-inbox fa-2x mb-2 d-block" style="opacity:.2;"></i>Aucune clause enregistrée.</div>
                <?php else: ?>
                <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th style="width:6%;">Ordre</th>
                            <th style="width:22%;">Titre</th>
                            <th>Contenu</th>
                            <th style="width:8%;" class="text-center">Statut</th>
                            <th style="width:10%;" class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($clausesMandat as $cl): ?>
                    <tr>
                        <td class="text-muted"><?= (int)$cl['ordre_affichage'] ?></td>
                        <td class="fw-semibold"><?= htmlspecialchars($cl['titre']) ?></td>
                        <td class="text-muted small"><?= htmlspecialchars(mb_strimwidth($cl['contenu'], 0, 120, '…')) ?></td>
                        <td class="text-center">
                            <?php if ($cl['actif']): ?><span class="badge bg-success-subtle text-success border">Active</span>
                            <?php else: ?><span class="badge bg-secondary-subtle text-secondary border">Inactive</span><?php endif; ?>
                        </td>
                        <td class="text-center text-nowrap">
                            <button type="button" class="btn btn-sm btn-outline-primary btn-edit-clause-mandat" title="Modifier"
                                    data-bs-toggle="modal" data-bs-target="#modalEditClauseMandat"
                                    data-id="<?= (int)$cl['id'] ?>"
                                    data-titre="<?= htmlspecialchars($cl['titre']) ?>"
                                    data-contenu="<?= htmlspecialchars($cl['contenu']) ?>"
                                    data-ordre="<?= (int)$cl['ordre_affichage'] ?>"
                                    data-actif="<?= (int)$cl['actif'] ?>">
                                <i class="fa fa-edit"></i>
                            </button>
                            <form action="../php/delete_clause_mandat.php" method="POST" style="display:inline" onsubmit="return confirm('Supprimer cette clause ?')">
                                <input type="hidden" name="token" value="<?= csrf_generate() ?>">
                                <input type="hidden" name="id" value="<?= $cl['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Supprimer"><i class="fa fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <?php endif; ?>
            </div>
    </div>

    <div class="tab-pane fade" id="tabBiblioLegale" role="tabpanel">
            <div class="card shadow-sm border-0 p-4">
                <h5 class="fw-bold mb-1"><i class="fa fa-scale-balanced me-2" style="color:var(--marine);"></i>Bibliothèque légale — Bail d'habitation (Côte d'Ivoire)</h5>
                <p class="text-muted small mb-3">
                    Extraits de la loi n° 2019-576 du 26 juin 2019 instituant le Code de la Construction et de l'Habitat (Sous-titre 2 « Bail à usage d'habitation », articles 408 à 456), reformulés en clauses prêtes à insérer. Cliquez sur « Insérer » pour l'ajouter telle quelle à la liste des clauses ci-dessus — vous pourrez ensuite la modifier ou l'ordonner comme les autres.
                </p>
                <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width:12%;">Référence</th>
                            <th style="width:16%;">Thème</th>
                            <th>Texte proposé</th>
                            <th style="width:8%;" class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($articlesBailCI as $art):
                        $titreArt = $art['article'] . ' — ' . $art['theme'];
                        $dejaAjoutee = isset($titresClausesExistantes[$titreArt]);
                    ?>
                    <tr>
                        <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($art['article']) ?></span></td>
                        <td class="fw-semibold small"><?= htmlspecialchars($art['theme']) ?></td>
                        <td class="text-muted small"><?= htmlspecialchars($art['texte']) ?></td>
                        <td class="text-center">
                            <?php if ($dejaAjoutee): ?>
                            <span class="badge bg-success-subtle text-success border" title="Déjà présente dans les clauses"><i class="fa fa-check"></i></span>
                            <?php else: ?>
                            <form action="../php/add_clause.php" method="POST">
                                <input type="hidden" name="token" value="<?= csrf_generate() ?>">
                                <input type="hidden" name="titre" value="<?= htmlspecialchars($titreArt) ?>">
                                <input type="hidden" name="contenu" value="<?= htmlspecialchars($art['texte']) ?>">
                                <input type="hidden" name="ordre_affichage" value="<?= count($clauses) + 1 ?>">
                                <input type="hidden" name="actif" value="1">
                                <button type="submit" class="btn btn-sm btn-outline-primary" title="Insérer comme clause"><i class="fa fa-plus"></i></button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <p class="text-muted mt-3 mb-0" style="font-size:11px;"><i class="fa fa-circle-info me-1"></i>Ces reformulations sont fournies à titre pratique ; en cas de litige, seul le texte légal officiel fait foi. La loi n° 2019-576 a abrogé la précédente loi n° 2018-575 du 13 juin 2018 sur le même objet.</p>
            </div>
    </div>

</div>
</div>
</div>
<script src="../js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('modalEditClause').addEventListener('show.bs.modal', function(event) {
    var d = event.relatedTarget.dataset;
    document.getElementById('editClauseId').value      = d.id;
    document.getElementById('editClauseTitre').value   = d.titre;
    document.getElementById('editClauseContenu').value = d.contenu;
    document.getElementById('editClauseOrdre').value   = d.ordre;
    document.getElementById('editClauseActif').checked = d.actif === '1';
});

document.getElementById('modalEditClauseMandat').addEventListener('show.bs.modal', function(event) {
    var d = event.relatedTarget.dataset;
    document.getElementById('editClauseMandatId').value      = d.id;
    document.getElementById('editClauseMandatTitre').value   = d.titre;
    document.getElementById('editClauseMandatContenu').value = d.contenu;
    document.getElementById('editClauseMandatOrdre').value   = d.ordre;
    document.getElementById('editClauseMandatActif').checked = d.actif === '1';
});
</script>
</body>
</html>