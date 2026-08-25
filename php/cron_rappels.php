<?php
/**
 * Script de rappels automatiques — à exécuter une fois par jour via une tâche planifiée.
 * Usage CLI : php cron_rappels.php
 *
 * - Envoie un email de rappel aux locataires en retard ou à échéance proche (si un email est renseigné).
 * - Envoie une synthèse quotidienne à l'agence si des retards, échéances proches ou fins de contrat existent.
 */
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("Ce script ne peut être exécuté qu'en ligne de commande.\n");
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/mailer.php';

$settings = $pdo->query("SELECT * FROM settings LIMIT 1")->fetch();
if (!$settings || (int)($settings['rappels_actifs'] ?? 1) !== 1) {
    echo "Rappels automatiques désactivés dans les paramètres.\n";
    exit(0);
}

$nomEntreprise = $settings['nom_entreprise'] ?? 'BailManager';
$adminEmail    = $settings['contact_email'] ?? null;

// ── Loyers en retard (contrats actifs) ─────────────────────────────────────────
$stmtRetard = $pdo->query(
    "SELECT c.id, c.loyer_mensuel, c.date_prochain_loyer, DATEDIFF(CURDATE(), c.date_prochain_loyer) AS jours_retard,
            l.nom AS locataire, l.email, m.designation AS maison
     FROM contrats c
     JOIN locataires l ON c.locataire_id = l.id
     JOIN maisons m ON c.maison_id = m.id
     WHERE c.statut_contrat = 'actif' AND c.date_prochain_loyer < CURDATE()
     ORDER BY c.date_prochain_loyer ASC"
);
$retards = $stmtRetard->fetchAll();

// ── Loyers à échéance dans les 3 prochains jours ───────────────────────────────
$stmtProche = $pdo->query(
    "SELECT c.id, c.loyer_mensuel, c.date_prochain_loyer,
            l.nom AS locataire, l.email, m.designation AS maison
     FROM contrats c
     JOIN locataires l ON c.locataire_id = l.id
     JOIN maisons m ON c.maison_id = m.id
     WHERE c.statut_contrat = 'actif'
       AND c.date_prochain_loyer BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)
     ORDER BY c.date_prochain_loyer ASC"
);
$prochains = $stmtProche->fetchAll();

// ── Contrats arrivant à échéance (30 jours) ────────────────────────────────────
$stmtFin = $pdo->query(
    "SELECT c.id, c.date_fin, l.nom AS locataire, m.designation AS maison,
            DATEDIFF(c.date_fin, CURDATE()) AS jours_restants
     FROM contrats c
     JOIN locataires l ON c.locataire_id = l.id
     JOIN maisons m ON c.maison_id = m.id
     WHERE c.statut_contrat = 'actif' AND c.date_fin IS NOT NULL
       AND c.date_fin BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
     ORDER BY c.date_fin ASC"
);
$echeances = $stmtFin->fetchAll();

$fmtMontant = fn($n) => number_format((float)$n, 0, ',', ' ') . ' FCFA';
$nbEmailsEnvoyes = 0;

// ── Emails individuels aux locataires (retard + échéance proche) ──────────────
foreach (array_merge($retards, $prochains) as $c) {
    if (empty($c['email'])) continue;
    $enRetard = isset($c['jours_retard']);
    $sujet = $enRetard ? "Rappel de paiement — loyer en retard" : "Rappel de paiement — échéance proche";
    $corps = $enRetard
        ? "<p>Bonjour {$c['locataire']},</p><p>Votre loyer de <strong>{$fmtMontant($c['loyer_mensuel'])}</strong> pour le bien <strong>{$c['maison']}</strong> est en retard de {$c['jours_retard']} jour(s) (échéance du " . date('d/m/Y', strtotime($c['date_prochain_loyer'])) . ").</p><p>Merci de régulariser votre situation dans les meilleurs délais.</p>"
        : "<p>Bonjour {$c['locataire']},</p><p>Votre loyer de <strong>{$fmtMontant($c['loyer_mensuel'])}</strong> pour le bien <strong>{$c['maison']}</strong> arrive à échéance le " . date('d/m/Y', strtotime($c['date_prochain_loyer'])) . ".</p><p>Merci de bien vouloir procéder au paiement à temps.</p>";
    $corps .= "<p style='color:#888;font-size:12px;margin-top:20px;'>$nomEntreprise</p>";
    if (sendMail($c['email'], $c['locataire'], $sujet, $corps)) {
        $nbEmailsEnvoyes++;
    }
}

// ── Synthèse quotidienne à l'agence ─────────────────────────────────────────────
if ($adminEmail && (count($retards) > 0 || count($prochains) > 0 || count($echeances) > 0)) {
    $html = "<h2>Synthèse quotidienne — $nomEntreprise</h2>";

    if ($retards) {
        $html .= "<h3 style='color:#dc3545;'>Loyers en retard (" . count($retards) . ")</h3><ul>";
        foreach ($retards as $c) {
            $html .= "<li>{$c['locataire']} — {$c['maison']} — " . $fmtMontant($c['loyer_mensuel']) . " — {$c['jours_retard']} jour(s) de retard</li>";
        }
        $html .= "</ul>";
    }
    if ($prochains) {
        $html .= "<h3 style='color:#fd7e14;'>Échéances dans les 3 prochains jours (" . count($prochains) . ")</h3><ul>";
        foreach ($prochains as $c) {
            $html .= "<li>{$c['locataire']} — {$c['maison']} — " . $fmtMontant($c['loyer_mensuel']) . " — le " . date('d/m/Y', strtotime($c['date_prochain_loyer'])) . "</li>";
        }
        $html .= "</ul>";
    }
    if ($echeances) {
        $html .= "<h3 style='color:#6f42c1;'>Contrats arrivant à échéance (" . count($echeances) . ")</h3><ul>";
        foreach ($echeances as $c) {
            $html .= "<li>{$c['locataire']} — {$c['maison']} — fin le " . date('d/m/Y', strtotime($c['date_fin'])) . " (J-{$c['jours_restants']})</li>";
        }
        $html .= "</ul>";
    }
    $html .= "<p style='color:#888;font-size:12px;margin-top:20px;'>Rapport généré automatiquement le " . date('d/m/Y à H:i') . ".</p>";

    sendMail($adminEmail, $nomEntreprise, "Synthèse quotidienne — " . date('d/m/Y'), $html);
}

$pdo->prepare("INSERT INTO logs (utilisateur_id, action, details, ip_adresse) VALUES (NULL, ?, ?, ?)")
    ->execute([
        "Rappels automatiques",
        "Retards : " . count($retards) . " — Échéances proches : " . count($prochains) . " — Fins de contrat : " . count($echeances) . " — Emails envoyés : $nbEmailsEnvoyes",
        'CLI',
    ]);

echo "Terminé. Retards: " . count($retards) . ", Échéances proches: " . count($prochains) . ", Fins de contrat: " . count($echeances) . ", Emails envoyés: $nbEmailsEnvoyes\n";
