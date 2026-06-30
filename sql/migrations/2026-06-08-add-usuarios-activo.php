<?php
$pdo = $GLOBALS['pdo'] ?? null;
if (!$pdo) {
    require_once __DIR__ . '/../../conexion.php';
    $pdo = obtenerConexion();
}

try {
    $check = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'activo'");
    if (!$check->fetch()) {
        $pdo->exec("ALTER TABLE usuarios ADD COLUMN activo TINYINT(1) NOT NULL DEFAULT 1 AFTER rol");
        $pdo->exec("UPDATE usuarios SET activo = 1 WHERE activo = 0");
        echo "  ADDED activo column\n";
    } else {
        echo "  activo column already exists\n";
    }
} catch (PDOException $e) {
    echo "  SKIP (table not available yet: " . $e->getMessage() . ")\n";
}