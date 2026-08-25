<?php
/**
 * Sauvegarde automatique de la base de données — à exécuter via une tâche planifiée.
 * Usage CLI : php cron_backup.php
 *
 * Conserve les 30 dernières sauvegardes automatiques et supprime les plus anciennes.
 */
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("Ce script ne peut être exécuté qu'en ligne de commande.\n");
}

require_once __DIR__ . '/../config/db.php';

$host      = $_ENV['DB_HOST']        ?? 'localhost';
$user      = $_ENV['DB_USER']        ?? 'root';
$pass      = $_ENV['DB_PASS']        ?? '';
$dbname    = $_ENV['DB_NAME']        ?? 'gestion_bail';
$mysqldump = $_ENV['MYSQLDUMP_PATH'] ?? 'mysqldump';

$date     = date("Y-m-d_H-i-s");
$filename = $dbname . "_auto_" . $date . ".sql";
$path     = __DIR__ . "/../backups/";

if (!is_dir($path)) {
    mkdir($path, 0755, true);
}

$fullPath = $path . $filename;

$args = [
    escapeshellarg($mysqldump),
    '--user=' . escapeshellarg($user),
    '--host=' . escapeshellarg($host),
    escapeshellarg($dbname),
];
if ($pass !== '') {
    $args[] = '--password=' . escapeshellarg($pass);
}
$command = implode(' ', $args) . ' > ' . escapeshellarg($fullPath) . ' 2>&1';

exec($command, $output, $returnVar);

if ($returnVar === 0) {
    $pdo->prepare("INSERT INTO logs (utilisateur_id, action, details, ip_adresse) VALUES (NULL, ?, ?, ?)")
        ->execute(["Sauvegarde auto DB", "Fichier : $filename", 'CLI']);
    echo "Sauvegarde créée : $filename\n";

    // Conserver seulement les 30 dernières sauvegardes automatiques
    $autoBackups = glob($path . $dbname . "_auto_*.sql");
    if ($autoBackups !== false && count($autoBackups) > 30) {
        usort($autoBackups, fn($a, $b) => filemtime($a) <=> filemtime($b));
        $toDelete = array_slice($autoBackups, 0, count($autoBackups) - 30);
        foreach ($toDelete as $old) {
            unlink($old);
            echo "Ancienne sauvegarde supprimée : " . basename($old) . "\n";
        }
    }
} else {
    error_log("Erreur mysqldump (cron) : " . implode("\n", $output));
    echo "Échec de la sauvegarde automatique.\n";
    exit(1);
}
