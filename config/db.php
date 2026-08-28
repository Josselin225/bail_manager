<?php

// ─── Version du logiciel (affichée sur la page "À propos") ───────────────────
define('APP_VERSION', '1.0.0');

// ─── Chargeur .env ───────────────────────────────────────────────────────────
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
        [$key, $val] = explode('=', $line, 2);
        $key = trim($key);
        $val = trim($val);
        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $val;
            putenv("$key=$val");
        }
    }
}

// ─── Connexion PDO ────────────────────────────────────────────────────────────
$dbHost = $_ENV['DB_HOST'] ?? 'localhost';
$dbName = $_ENV['DB_NAME'] ?? 'gestion_bail';
$dbUser = $_ENV['DB_USER'] ?? 'root';
$dbPass = $_ENV['DB_PASS'] ?? '';

try {
    $pdo = new PDO(
        "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4",
        $dbUser,
        $dbPass
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur DB : " . $e->getMessage());
    die("Erreur de connexion à la base de données. Veuillez contacter l'administrateur.");
}

// ─── Journal d'audit ──────────────────────────────────────────────────────────
function insertLog(PDO $pdo, string $action, string $details): void {
    $user_id = $_SESSION['user_id'] ?? null;
    $ip      = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $stmt = $pdo->prepare("INSERT INTO logs (utilisateur_id, action, details, ip_adresse) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user_id, $action, $details, $ip]);
}

// ─── Solde bailleur (source de vérité unique : historique compte_courant_bailleur) ──
// bailleurs.solde_du_bailleur est un cache recalculé après chaque écriture dans
// compte_courant_bailleur, pour ne jamais diverger de l'historique réel.
function recalculerSoldeBailleur(PDO $pdo, int $bailleur_id): void {
    $s = $pdo->prepare("SELECT COALESCE(SUM(montant),0) FROM compte_courant_bailleur WHERE bailleur_id = ?");
    $s->execute([$bailleur_id]);
    $solde = $s->fetchColumn();
    $pdo->prepare("UPDATE bailleurs SET solde_du_bailleur = ? WHERE id = ?")->execute([$solde, $bailleur_id]);
}

// ─── Mandat de gestion (bailleur ↔ agence) ────────────────────────────────────
// Un bailleur ne peut se voir enregistrer un bien (maisons) ni un contrat de bail
// tant qu'aucun mandat de gestion actif n'est en cours pour lui. Un mandat est actif
// s'il a le statut 'actif' et que la date du jour est dans sa période de validité
// (date_fin NULL = durée indéterminée).
function bailleurAMandatActif(PDO $pdo, int $bailleur_id): bool {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM mandats_gestion
         WHERE bailleur_id = ? AND statut = 'actif'
           AND date_debut <= CURDATE()
           AND (date_fin IS NULL OR date_fin >= CURDATE())"
    );
    $stmt->execute([$bailleur_id]);
    return (bool)$stmt->fetchColumn();
}

// ─── Upload d'images sécurisé ─────────────────────────────────────────────────
// Retourne l'extension correspondant au type MIME réel du fichier (détecté via
// mime_content_type, pas via le nom fourni par le client), ou null si le type
// n'est pas une image autorisée. Toujours utiliser CETTE extension pour nommer
// le fichier enregistré — ne jamais réutiliser l'extension du nom d'origine,
// qui est arbitraire et pourrait ne pas correspondre au contenu réel.
function mimeToImageExt(string $mime): ?string {
    $map = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];
    return $map[$mime] ?? null;
}

// ─── Pied de page légal des documents imprimés/exportés ──────────────────────
// Construit les lignes (siège+tel, CC/RCCM/email, banque/IBAN/SWIFT, site web)
// à partir d'une ligne `settings`. Utilisé par les pages d'export PDF (jsPDF)
// et, sous forme de contenu CSS @page, par les pages imprimées via Chrome.
function buildFooterLines(array $entreprise): array {
    $lines = [];
    if (!empty($entreprise['adresse_siege']) || !empty($entreprise['contact_telephone'])) {
        $lines[] = trim(
            (!empty($entreprise['adresse_siege']) ? 'Siège social : ' . $entreprise['adresse_siege'] : '') .
            (!empty($entreprise['contact_telephone']) ? ' - Tel : ' . $entreprise['contact_telephone'] : '')
        );
    }
    $ligneCC = array_filter([
        !empty($entreprise['cc_numero']) ? 'CC N° : ' . $entreprise['cc_numero'] : '',
        !empty($entreprise['regime_imposition']) ? 'Régime d\'Imposition : ' . $entreprise['regime_imposition'] : '',
        !empty($entreprise['rccm_numero']) ? 'N° RCCM : ' . $entreprise['rccm_numero'] : '',
        !empty($entreprise['contact_email']) ? 'E-mail : ' . $entreprise['contact_email'] : '',
    ]);
    if ($ligneCC) $lines[] = implode(' - ', $ligneCC);
    $ligneBanque = array_filter([
        !empty($entreprise['compte_bancaire']) ? 'Compte bancaire : ' . $entreprise['compte_bancaire'] : '',
        !empty($entreprise['iban']) ? 'IBAN ' . $entreprise['iban'] : '',
        !empty($entreprise['swift']) ? 'SWIFT: ' . $entreprise['swift'] : '',
    ]);
    if ($ligneBanque) $lines[] = implode(' - ', $ligneBanque);
    if (!empty($entreprise['site_web'])) $lines[] = $entreprise['site_web'];
    return $lines;
}

// ─── Notifications toast (flash messages) ────────────────────────────────────
// type attendu : success | error | warning | info. Consommé et affiché en
// toast par includes/sidebar.php (via js/toast.js) sur la page suivante.
function flash(string $type, string $message): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

// ─── Protection CSRF (HMAC, sans stockage session) ───────────────────────────
function csrf_generate(): string {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $key = $_ENV['CSRF_KEY'] ?? 'bail_manager_csrf_fallback_key';
    return hash_hmac('sha256', session_id(), $key);
}

function csrf_field(): string {
    return '<input type="hidden" name="token" value="' . htmlspecialchars(csrf_generate(), ENT_QUOTES) . '">';
}

function csrf_validate(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $received = $_POST['token'] ?? '';
    $expected = csrf_generate();
    if (empty($received) || !hash_equals($expected, $received)) {
        http_response_code(403);
        die(
            '<div style="font-family:sans-serif;max-width:420px;margin:80px auto;text-align:center;">' .
            '<p style="font-size:15px;color:#333;">Requête rejetée : la session a expiré ou le formulaire était ouvert depuis trop longtemps.<br>Veuillez réessayer.</p>' .
            '<button onclick="history.back()" style="margin-top:10px;padding:10px 24px;background:#000080;color:#fff;border:none;border-radius:4px;font-weight:bold;cursor:pointer;">Retour</button>' .
            '</div>'
        );
    }
}