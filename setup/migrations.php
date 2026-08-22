<?php
/**
 * Migrations BailManager — à exécuter UNE seule fois en tant qu'admin.
 * Crée les nouvelles tables si elles n'existent pas encore.
 */
session_start();
require_once('../config/db.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Accès refusé. Connectez-vous en tant qu'administrateur.");
}

$migrations = [];

// ── Table : révisions de loyer ────────────────────────────────────────────────
$migrations['revisions_loyer'] = "
    CREATE TABLE IF NOT EXISTS `revisions_loyer` (
        `id`            INT AUTO_INCREMENT PRIMARY KEY,
        `contrat_id`    INT NOT NULL,
        `ancien_loyer`  DECIMAL(12,2) NOT NULL,
        `nouveau_loyer` DECIMAL(12,2) NOT NULL,
        `motif`         VARCHAR(255) DEFAULT NULL,
        `effectue_par`  INT DEFAULT NULL,
        `created_at`    DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`contrat_id`) REFERENCES `contrats`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

// ── Table : charges locatives ─────────────────────────────────────────────────
$migrations['charges_locatives'] = "
    CREATE TABLE IF NOT EXISTS `charges_locatives` (
        `id`          INT AUTO_INCREMENT PRIMARY KEY,
        `contrat_id`  INT NOT NULL,
        `type_charge` VARCHAR(100) NOT NULL,
        `montant`     DECIMAL(12,2) NOT NULL,
        `date_charge` DATE NOT NULL,
        `description` TEXT DEFAULT NULL,
        `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`contrat_id`) REFERENCES `contrats`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

// ── Table : accès espace locataire ────────────────────────────────────────────
$migrations['locataire_acces'] = "
    CREATE TABLE IF NOT EXISTS `locataire_acces` (
        `id`           INT AUTO_INCREMENT PRIMARY KEY,
        `locataire_id` INT NOT NULL UNIQUE,
        `code_acces`   VARCHAR(20) NOT NULL,
        `actif`        TINYINT(1) DEFAULT 1,
        `created_at`   DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`locataire_id`) REFERENCES `locataires`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

// ── Exécution ─────────────────────────────────────────────────────────────────
$results = [];
foreach ($migrations as $name => $sql) {
    try {
        $pdo->exec($sql);
        $results[$name] = ['ok' => true, 'msg' => 'Créée / déjà existante'];
    } catch (PDOException $e) {
        $results[$name] = ['ok' => false, 'msg' => $e->getMessage()];
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Migrations — BailManager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light p-5">
<div class="container" style="max-width:640px">
    <h3 class="fw-bold mb-4">Migrations BailManager</h3>
    <div class="card shadow-sm border-0">
        <ul class="list-group list-group-flush">
            <?php foreach ($results as $table => $r): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <code><?= $table ?></code>
                <?php if ($r['ok']): ?>
                    <span class="badge bg-success"><i class="fa fa-check me-1"></i><?= $r['msg'] ?></span>
                <?php else: ?>
                    <span class="badge bg-danger text-wrap" style="max-width:300px"><?= htmlspecialchars($r['msg']) ?></span>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <a href="../pages/dashboard.php" class="btn btn-primary mt-4">Retour au dashboard</a>
</div>
</body>
</html>
