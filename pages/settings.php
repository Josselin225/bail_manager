<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { header('Location: dashboard.php'); exit(); }
require_once('../config/db.php');

// 1. RÉCUPÉRATION DES DONNÉES ACTUELLES
$stmt = $pdo->query("SELECT * FROM settings WHERE id = 1");
$current = $stmt->fetch();

$clauses = $pdo->query("SELECT * FROM clauses_contrat ORDER BY ordre_affichage ASC, id ASC")->fetchAll();

// 2. TRAITEMENT DU FORMULAIRE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();

    $nom = $_POST['nom_entreprise'];
    $email = $_POST['contact_email'];
    $tel = $_POST['contact_telephone'];
    $taux = $_POST['taux_commission'];
    $adresse = $_POST['adresse_siege'];
    $rappels_actifs = isset($_POST['rappels_actifs']) ? 1 : 0;

    // Par défaut, on garde l'ancien logo stocké en DB
    $logo_name = $current['logo_url'];

    // Gestion du téléchargement du nouveau logo
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === 0) {
        $upload_dir = "../uploads/";

        // Créer le dossier s'il n'existe pas
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
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
            taux_commission = ?, rappels_actifs = ?, adresse_siege = ?, logo_url = ?
            WHERE id = 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$nom, $email, $tel, $taux, $rappels_actifs, $adresse, $logo_name]);

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

<div class="main-content">

            <div class="card shadow-sm border-0 p-4">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="token" value="<?= csrf_generate() ?>">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label fw-bold">Logo de l'agence</label>
                            <input type="file" name="logo" class="form-control mb-2">
                            <?php if(!empty($current['logo_url'])): ?>
                                <div class="p-2 border d-inline-block bg-light rounded">
                                    <img src="../uploads/<?= htmlspecialchars($current['logo_url']) ?>" width="100" class="img-fluid rounded shadow-sm">
                                </div>
                            <?php endif; ?>
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

                        <div class="col-md-12 mb-4">
                            <label class="form-label fw-bold">Adresse du Siège Social</label>
                            <textarea name="adresse_siege" class="form-control" rows="3"><?= htmlspecialchars($current['adresse_siege'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary px-5 shadow-sm" style="background-color: var(--marine); border: none;">
                            <i class="fa fa-save me-2"></i> Enregistrer les modifications
                        </button>
                    </div>
                </form>
            </div>

            <div class="card shadow-sm border-0 p-4 mt-4">
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
</script>
</body>
</html>