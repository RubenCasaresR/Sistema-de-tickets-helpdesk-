<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/conexion.php';
requiereRol(['soporte', 'admin']);

header('Content-Type: application/json');

$pdo = obtenerConexion();

$chartEstado = $pdo->query("
    SELECT estado, COUNT(*) AS total FROM tickets GROUP BY estado
")->fetchAll();

$estadoData = [];
foreach ($chartEstado as $row) {
    $estadoData[] = ['label' => ucfirst(str_replace('_', ' ', $row['estado'])), 'value' => (int) $row['total']];
}

echo json_encode(['estado' => $estadoData]);
