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

// ── Table : mandats de gestion (bailleur ↔ agence) ────────────────────────────
$migrations['mandats_gestion'] = "
    CREATE TABLE IF NOT EXISTS `mandats_gestion` (
        `id`              INT AUTO_INCREMENT PRIMARY KEY,
        `bailleur_id`     INT NOT NULL,
        `date_signature`  DATE NOT NULL,
        `date_debut`      DATE NOT NULL,
        `date_fin`        DATE DEFAULT NULL,
        `taux_commission` DECIMAL(5,2) NOT NULL DEFAULT 10.00,
        `statut`          ENUM('actif','resilie','expire') NOT NULL DEFAULT 'actif',
        `document_signe`  VARCHAR(255) DEFAULT NULL,
        `created_at`      DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at`      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (`bailleur_id`) REFERENCES `bailleurs`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

// ── Table : accès espace locataire ────────────────────────────────────────────
// code_acces stocke un hash password_hash() (60+ car.), pas le code en clair — VARCHAR(255)
// pour héberger le hash. Le MODIFY ci-dessous élargit la colonne sur une base existante
// créée avant ce changement (VARCHAR(20) à l'origine, insuffisant pour un hash).
$migrations['locataire_acces'] = "
    CREATE TABLE IF NOT EXISTS `locataire_acces` (
        `id`           INT AUTO_INCREMENT PRIMARY KEY,
        `locataire_id` INT NOT NULL UNIQUE,
        `code_acces`   VARCHAR(255) NOT NULL,
        `actif`        TINYINT(1) DEFAULT 1,
        `created_at`   DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`locataire_id`) REFERENCES `locataires`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";
$migrations['locataire_acces_code_hash'] = "
    ALTER TABLE `locataire_acces` MODIFY `code_acces` VARCHAR(255) NOT NULL;
";

// ── Table : anti brute-force persistant (login staff, mot de passe oublié, locataire) ──
// Remplace les compteurs $_SESSION (contournables en repartant d'une session neuve à
// chaque tentative) par un stockage en base, clé par contexte+IP.
$migrations['rate_limits'] = "
    CREATE TABLE IF NOT EXISTS `rate_limits` (
        `id`                 INT AUTO_INCREMENT PRIMARY KEY,
        `cle`                VARCHAR(191) NOT NULL,
        `tentatives`         INT NOT NULL DEFAULT 1,
        `derniere_tentative` DATETIME NOT NULL,
        `updated_at`         DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `cle` (`cle`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

// ── Table : documents joints (bailleur/locataire/contrat/maison) ─────────────
$migrations['documents'] = "
    CREATE TABLE IF NOT EXISTS `documents` (
        `id`            INT AUTO_INCREMENT PRIMARY KEY,
        `entity_type`   ENUM('bailleur','locataire','contrat','maison') NOT NULL,
        `entity_id`     INT NOT NULL,
        `nom_original`  VARCHAR(255) NOT NULL,
        `nom_fichier`   VARCHAR(255) NOT NULL,
        `type_mime`     VARCHAR(100) DEFAULT NULL,
        `taille`        INT DEFAULT NULL,
        `uploaded_by`   INT DEFAULT NULL,
        `created_at`    TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        KEY `idx_entity` (`entity_type`, `entity_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

// ── Table : messagerie agence ↔ locataire ─────────────────────────────────────
$migrations['messages_locataires'] = "
    CREATE TABLE IF NOT EXISTS `messages_locataires` (
        `id`           INT AUTO_INCREMENT PRIMARY KEY,
        `locataire_id` INT NOT NULL,
        `expediteur`   ENUM('locataire','agence') NOT NULL,
        `contenu`      TEXT NOT NULL,
        `lu`           TINYINT(1) NOT NULL DEFAULT 0,
        `created_at`   TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        KEY `locataire_id` (`locataire_id`),
        FOREIGN KEY (`locataire_id`) REFERENCES `locataires`(`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

// ── Colonnes légales de l'agence (en-tête/pied de page des documents PDF) ─────
// Pas de IF NOT EXISTS : non supporté par MySQL pour ADD COLUMN (spécifique à MariaDB).
// Si les colonnes existent déjà, l'erreur "Duplicate column" est normale et sans risque
// (visible en rouge dans la liste ci-dessous, comme pour toute migration déjà appliquée).
$migrations['settings_colonnes_legales'] = "
    ALTER TABLE `settings`
        ADD COLUMN `activites`         TEXT         DEFAULT NULL AFTER `contact_telephone`,
        ADD COLUMN `cc_numero`          VARCHAR(50)  DEFAULT NULL AFTER `activites`,
        ADD COLUMN `regime_imposition`  VARCHAR(100) DEFAULT NULL AFTER `cc_numero`,
        ADD COLUMN `rccm_numero`        VARCHAR(100) DEFAULT NULL AFTER `regime_imposition`,
        ADD COLUMN `compte_bancaire`    VARCHAR(150) DEFAULT NULL AFTER `rccm_numero`,
        ADD COLUMN `iban`               VARCHAR(100) DEFAULT NULL AFTER `compte_bancaire`,
        ADD COLUMN `swift`              VARCHAR(30)  DEFAULT NULL AFTER `iban`,
        ADD COLUMN `site_web`           VARCHAR(150) DEFAULT NULL AFTER `swift`,
        MODIFY `contact_telephone` VARCHAR(150) DEFAULT NULL;
";

// ── Mode de paiement Chèque pour les encaissements ─────────────────────────────
$migrations['encaissements_mode_cheque'] = "
    ALTER TABLE `encaissements`
        MODIFY `mode_paiement` ENUM('especes','virement','mobile_money','cheque') DEFAULT 'especes';
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
