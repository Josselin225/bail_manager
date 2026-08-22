<?php
session_start();
require_once('../config/db.php');

// Vérification session locataire
if (empty($_SESSION['locataire_mode']) || empty($_SESSION['locataire_id'])) {
    header('Location: locataire_portail.php');
    exit();
}

// Timeout 30 min
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
    session_unset(); session_destroy();
    header('Location: locataire_portail.php?error=timeout');
    exit();
}
$_SESSION['last_activity'] = time();

$locataire_id = (int)$_SESSION['locataire_id'];

// Infos locataire
$stmtLoc = $pdo->prepare("SELECT * FROM locataires WHERE id = ?");
$stmtLoc->execute([$locataire_id]);
$locataire = $stmtLoc->fetch();

if (!$locataire) {
    session_unset(); session_destroy();
    header('Location: locataire_portail.php');
    exit();
}

// Contrats actifs du locataire
$stmtContrats = $pdo->prepare(
    "SELECT c.*, m.designation AS maison, m.adresse, m.type_maison,
            b.nom AS bailleur,
            DATEDIFF(c.date_prochain_loyer, CURDATE()) AS jours_restants
     FROM contrats c
     JOIN maisons m ON c.maison_id = m.id
     JOIN bailleurs b ON m.bailleur_id = b.id
     WHERE c.locataire_id = ?
     ORDER BY c.statut_contrat ASC, c.date_debut DESC"
);
$stmtContrats->execute([$locataire_id]);
$contrats = $stmtContrats->fetchAll();

// Historique des paiements (30 derniers)
$stmtPay = $pdo->prepare(
    "SELECT e.*, m.designation AS maison
     FROM encaissements e
     JOIN contrats c ON e.contrat_id = c.id
     JOIN maisons m ON c.maison_id = m.id
     WHERE c.locataire_id = ?
     ORDER BY e.date_encaissement DESC
     LIMIT 30"
);
$stmtPay->execute([$locataire_id]);
$paiements = $stmtPay->fetchAll();

// Charges du locataire
$stmtCharges = $pdo->prepare(
    "SELECT ch.*, m.designation AS maison
     FROM charges_locatives ch
     JOIN contrats c ON ch.contrat_id = c.id
     JOIN maisons m ON c.maison_id = m.id
     WHERE c.locataire_id = ?
     ORDER BY ch.date_charge DESC
     LIMIT 20"
);
$stmtCharges->execute([$locataire_id]);
$charges = $stmtCharges->fetchAll();

$settings = $pdo->query("SELECT * FROM settings LIMIT 1")->fetch();
$nomEntreprise = htmlspecialchars($settings['nom_entreprise'] ?? 'BailManager');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon espace — <?= $nomEntreprise ?></title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/fontawesome/all.min.css">
    <style>
        :root { --marine: #002147; --rouge: #FF0000; }
        body { background: #f4f7fe; }
        .top-bar { background: var(--marine); color: white; padding: 14px 24px; }
        .card-contrat { border-left: 5px solid var(--marine); }
        .card-contrat.termine { border-left-color: #adb5bd; opacity: .8; }
    </style>
</head>
<body>

<!-- Top bar -->
<div class="top-bar d-flex justify-content-between align-items-center">
    <div>
        <span class="fw-bold fs-5">BAIL<span class="text-danger">MANAGER</span></span>
        <span class="ms-3 small opacity-75">— <?= $nomEntreprise ?></span>
    </div>
    <div class="d-flex align-items-center gap-3">
        <span class="small"><i class="fa fa-user-circle me-1"></i><?= htmlspecialchars($locataire['nom']) ?></span>
        <a href="../php/locataire_logout.php" class="btn btn-sm btn-outline-light">
            <i class="fa fa-sign-out-alt me-1"></i>Déconnexion
        </a>
    </div>
</div>

<div class="container py-4" style="max-width:960px">

    <!-- Titre -->
    <h4 class="fw-bold mb-4" style="color: var(--marine);">
        <i class="fa fa-home me-2"></i>Mon espace locataire
    </h4>

    <!-- Contrats -->
    <h6 class="text-uppercase text-muted fw-bold mb-3 small"><i class="fa fa-file-contract me-2"></i>Mes contrats</h6>
    <?php if (empty($contrats)): ?>
    <div class="alert alert-info">Aucun contrat trouvé.</div>
    <?php else: ?>
    <?php foreach ($contrats as $c):
        $actif = $c['statut_contrat'] === 'actif';
        $jours = (int)$c['jours_restants'];
        $urgence = $actif && $jours <= 7;
    ?>
    <div class="card border-0 shadow-sm mb-3 card-contrat <?= !$actif ? 'termine' : '' ?>">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="fw-bold fs-6"><?= htmlspecialchars($c['maison']) ?></div>
                    <div class="text-muted small"><?= htmlspecialchars($c['adresse']) ?> — <?= htmlspecialchars($c['type_maison']) ?></div>
                    <div class="small mt-1"><i class="fa fa-user-tie me-1 text-muted"></i>Bailleur : <?= htmlspecialchars($c['bailleur']) ?></div>
                </div>
                <div class="col-md-3 mt-2 mt-md-0">
                    <div class="small text-muted">Loyer mensuel</div>
                    <div class="fw-bold text-success fs-5"><?= number_format($c['loyer_mensuel'], 0, ',', ' ') ?> <small>FCFA</small></div>
                    <div class="small text-muted">Depuis <?= date('d/m/Y', strtotime($c['date_debut'])) ?></div>
                </div>
                <div class="col-md-3 mt-2 mt-md-0 text-md-end">
                    <?php if ($actif): ?>
                        <div class="badge bg-success mb-1">Actif</div>
                        <div class="small text-muted">Prochaine échéance</div>
                        <div class="fw-bold <?= $urgence ? 'text-danger' : '' ?>">
                            <?= date('d/m/Y', strtotime($c['date_prochain_loyer'])) ?>
                        </div>
                        <?php if ($jours < 0): ?>
                        <span class="badge bg-danger">En retard de <?= abs($jours) ?> jour(s)</span>
                        <?php elseif ($jours <= 7): ?>
                        <span class="badge bg-warning text-dark">Dans <?= $jours ?> jour(s)</span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="badge bg-secondary">Terminé</span>
                        <?php if ($c['date_fin']): ?>
                        <div class="small text-muted">Fin : <?= date('d/m/Y', strtotime($c['date_fin'])) ?></div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <!-- Historique paiements -->
    <h6 class="text-uppercase text-muted fw-bold mb-3 mt-4 small"><i class="fa fa-receipt me-2"></i>Historique des paiements</h6>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="small">Référence</th>
                            <th class="small">Bien</th>
                            <th class="small">Période</th>
                            <th class="small">Montant</th>
                            <th class="small">Date</th>
                            <th class="small">Mode</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($paiements)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-3">Aucun paiement enregistré.</td></tr>
                        <?php else: ?>
                        <?php foreach ($paiements as $p): ?>
                        <tr>
                            <td class="small fw-bold text-muted"><?= htmlspecialchars($p['reference_recu']) ?></td>
                            <td class="small"><?= htmlspecialchars($p['maison']) ?></td>
                            <td class="small"><?= htmlspecialchars($p['periode_concernee']) ?></td>
                            <td class="small fw-bold text-success"><?= number_format($p['montant_recu'], 0, ',', ' ') ?> FCFA</td>
                            <td class="small"><?= date('d/m/Y', strtotime($p['date_encaissement'])) ?></td>
                            <td class="small text-muted"><?= htmlspecialchars($p['mode_paiement']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Charges -->
    <?php if (!empty($charges)): ?>
    <h6 class="text-uppercase text-muted fw-bold mb-3 small"><i class="fa fa-bolt me-2"></i>Mes charges</h6>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr><th class="small">Date</th><th class="small">Bien</th><th class="small">Type</th><th class="small">Montant</th><th class="small">Description</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($charges as $ch): ?>
                        <tr>
                            <td class="small"><?= date('d/m/Y', strtotime($ch['date_charge'])) ?></td>
                            <td class="small"><?= htmlspecialchars($ch['maison']) ?></td>
                            <td><span class="badge bg-light text-dark border small"><?= htmlspecialchars($ch['type_charge']) ?></span></td>
                            <td class="small fw-bold text-danger"><?= number_format($ch['montant'], 0, ',', ' ') ?> FCFA</td>
                            <td class="small text-muted"><?= htmlspecialchars($ch['description'] ?: '—') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="text-center text-muted small pb-4">
        <?= $nomEntreprise ?> — <?= htmlspecialchars($settings['contact_telephone'] ?? '') ?>
        <?php if (!empty($settings['contact_email'])): ?> — <?= htmlspecialchars($settings['contact_email']) ?><?php endif; ?>
    </div>
</div>

<script src="../js/bootstrap.bundle.min.js"></script>
</body>
</html>
