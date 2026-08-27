<?php
/**
 * En-tête d'entreprise pour documents imprimés (contrat, quittances, rapports).
 * Attend une variable $entreprise (ligne de la table `settings`) déjà en scope.
 * Style calqué sur l'en-tête papier officiel de l'agence (logo + activités + ruban).
 */
$lhActivites = array_filter(array_map('trim', explode("\n", $entreprise['activites'] ?? '')));
?>
<style>
    .lh-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 14px; }
    .lh-logo-block { display: flex; align-items: center; gap: 10px; }
    .lh-logo-block img { max-height: 52px; max-width: 130px; object-fit: contain; }
    .lh-activites { text-align: right; font-size: 7.5pt; font-weight: 700; color: #14305c; line-height: 1.5; text-transform: uppercase; }
    .lh-wave { width: 100%; height: 10px; display: block; margin-top: 6px; margin-bottom: 8px; }
</style>
<div class="lh-header">
    <div class="lh-logo-block">
        <?php if (!empty($entreprise['logo_url']) && file_exists(__DIR__ . '/../uploads/' . $entreprise['logo_url'])): ?>
        <img src="../uploads/<?= htmlspecialchars($entreprise['logo_url']) ?>" alt="Logo">
        <?php endif; ?>
    </div>
    <?php if ($lhActivites): ?>
    <div class="lh-activites">
        <?php foreach ($lhActivites as $act): ?>
        <div><?= htmlspecialchars($act) ?></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<svg class="lh-wave" viewBox="0 0 900 30" preserveAspectRatio="none">
    <path d="M0,20 C150,4 300,26 450,16 C600,4 750,24 900,12 L900,30 L0,30 Z" fill="#f0ad00"></path>
    <path d="M0,26 C150,13 300,29 450,22 C600,12 750,27 900,20 L900,30 L0,30 Z" fill="#14305c"></path>
</svg>
