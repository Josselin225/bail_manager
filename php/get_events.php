<?php
/**
 * API JSON — retourne les événements pour FullCalendar.
 * Types : échéances de loyer, visites, résiliations.
 */
session_start();
require_once('../config/db.php');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([]);
    exit();
}

header('Content-Type: application/json');

$events = [];

// ── Échéances de loyer (contrats actifs) ──────────────────────────────────────
$stmtEch = $pdo->query(
    "SELECT c.id, c.date_prochain_loyer, c.loyer_mensuel,
            l.nom AS nom_locataire, m.designation AS nom_maison
     FROM contrats c
     JOIN locataires l ON c.locataire_id = l.id
     JOIN maisons m ON c.maison_id = m.id
     WHERE c.statut_contrat = 'actif'"
);
foreach ($stmtEch->fetchAll() as $row) {
    $due = strtotime($row['date_prochain_loyer']);
    $today = strtotime('today');
    $color = $due < $today ? '#dc3545' : ($due <= strtotime('+7 days') ? '#fd7e14' : '#0d6efd');

    $events[] = [
        'id'         => 'ech_' . $row['id'],
        'title'      => '💰 ' . $row['nom_locataire'] . ' — ' . number_format($row['loyer_mensuel'], 0, ',', ' ') . ' FCFA',
        'start'      => $row['date_prochain_loyer'],
        'color'      => $color,
        'extendedProps' => [
            'type'   => 'echeance',
            'maison' => $row['nom_maison'],
            'url'    => 'encaissements.php',
        ],
    ];
}

// ── Visites / réservations ────────────────────────────────────────────────────
$stmtVis = $pdo->query(
    "SELECT r.id, r.date_visite, r.nom_visiteur, r.tel_visiteur, r.statut,
            m.designation AS nom_maison
     FROM reservations r
     JOIN maisons m ON r.maison_id = m.id
     WHERE r.statut IN ('en_attente', 'confirmee')"
);
foreach ($stmtVis->fetchAll() as $row) {
    $color = $row['statut'] === 'confirmee' ? '#198754' : '#ffc107';
    $events[] = [
        'id'         => 'vis_' . $row['id'],
        'title'      => '🏠 Visite — ' . $row['nom_visiteur'],
        'start'      => $row['date_visite'],
        'color'      => $color,
        'extendedProps' => [
            'type'   => 'visite',
            'maison' => $row['nom_maison'],
            'tel'    => $row['tel_visiteur'],
            'statut' => $row['statut'],
            'url'    => 'liste_reservations.php',
        ],
    ];
}

// ── Fins de contrat (30 prochains jours) ─────────────────────────────────────
$stmtFin = $pdo->query(
    "SELECT c.id, c.date_fin, l.nom AS nom_locataire, m.designation AS nom_maison
     FROM contrats c
     JOIN locataires l ON c.locataire_id = l.id
     JOIN maisons m ON c.maison_id = m.id
     WHERE c.statut_contrat = 'actif'
       AND c.date_fin IS NOT NULL
       AND c.date_fin BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)"
);
foreach ($stmtFin->fetchAll() as $row) {
    $events[] = [
        'id'         => 'fin_' . $row['id'],
        'title'      => '📋 Fin contrat — ' . $row['nom_locataire'],
        'start'      => $row['date_fin'],
        'color'      => '#6f42c1',
        'extendedProps' => [
            'type'   => 'fin_contrat',
            'maison' => $row['nom_maison'],
            'url'    => 'contrats.php',
        ],
    ];
}

echo json_encode($events);
