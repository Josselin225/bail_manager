<?php
session_start();
require_once('../config/db.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../pages/dashboard.php?msg=access_denied');
    exit();
}

$host       = $_ENV['DB_HOST']         ?? 'localhost';
$user       = $_ENV['DB_USER']         ?? 'root';
$pass       = $_ENV['DB_PASS']         ?? '';
$dbname     = $_ENV['DB_NAME']         ?? 'gestion_bail';
$mysqldump  = $_ENV['MYSQLDUMP_PATH']  ?? 'mysqldump';

$date     = date("Y-m-d_H-i-s");
$filename = $dbname . "_backup_" . $date . ".sql";
$path     = __DIR__ . "/../backups/";

if (!is_dir($path)) {
    mkdir($path, 0755, true);
}

$fullPath = $path . $filename;

// Construire la commande avec échappement correct des arguments
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
    insertLog($pdo, "Sauvegarde DB", "Fichier : $filename");
    header("Location: ../pages/dashboard.php?backup=success&file=" . urlencode($filename));
} else {
    error_log("Erreur mysqldump : " . implode("\n", $output));
    header("Location: ../pages/dashboard.php?backup=error");
}
exit();
