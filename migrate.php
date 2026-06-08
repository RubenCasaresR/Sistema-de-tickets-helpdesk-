<?php
/**
 * Database migration runner.
 * Usage: php migrate.php
 *
 * Creates a _migrations table to track applied migrations,
 * then runs any SQL files in sql/migrations/ that haven't been applied yet.
 */

require_once __DIR__ . '/conexion.php';

$pdo = obtenerConexion();

// Ensure tracking table exists
$pdo->exec("
    CREATE TABLE IF NOT EXISTS _migrations (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        filename VARCHAR(255) NOT NULL UNIQUE,
        applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$applied = $pdo->query("SELECT filename FROM _migrations")->fetchAll(PDO::FETCH_COLUMN);

$migrationsDir = __DIR__ . '/sql/migrations';
$files = glob($migrationsDir . '/*.sql');
sort($files);

$count = 0;

foreach ($files as $file) {
    $basename = basename($file);
    if (in_array($basename, $applied, true)) {
        echo "  SKIP {$basename} (already applied)\n";
        continue;
    }

    echo "  RUN  {$basename}... ";

    $sql = file_get_contents($file);

    // Skip pure-comment files
    if (preg_match('/^\s*(--|#)/m', $sql) && !preg_match('/CREATE|ALTER|INSERT|UPDATE|DELETE/i', $sql)) {
        $pdo->prepare("INSERT INTO _migrations (filename) VALUES (:f)")->execute([':f' => $basename]);
        echo "SKIP (no SQL statements)\n";
        continue;
    }

    try {
        $pdo->exec($sql);
        $pdo->prepare("INSERT INTO _migrations (filename) VALUES (:f)")->execute([':f' => $basename]);
        echo "OK\n";
        $count++;
    } catch (PDOException $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
        exit(1);
    }
}

if ($count === 0) {
    echo "Nothing to migrate.\n";
} else {
    echo "Applied {$count} migration(s).\n";
}
