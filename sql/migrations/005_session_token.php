<?php
$pdo = $GLOBALS['pdo'] ?? null;
if (!$pdo) {
    require_once __DIR__ . '/../../conexion.php';
    $pdo = obtenerConexion();
}

try {
    $check = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'session_token'");
    if (!$check->fetch()) {
        $pdo->exec("ALTER TABLE usuarios ADD COLUMN session_token VARCHAR(64) DEFAULT NULL AFTER password");
        echo "  ADDED session_token column\n";
    } else {
        echo "  session_token column already exists\n";
    }
} catch (PDOException $e) {
    echo "  SKIP (table not available yet: " . $e->getMessage() . ")\n";
}