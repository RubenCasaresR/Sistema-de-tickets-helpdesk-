<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/conexion.php';
requiereRol(['soporte', 'admin']);

header('Content-Type: application/json');

$pdo = obtenerConexion();

$stats = $pdo->query("
    SELECT
        COUNT(*) AS total,
        SUM(estado = 'abierto') AS abiertos,
        SUM(estado = 'en_progreso') AS en_progreso,
        SUM(prioridad = 'urgente') AS urgentes
    FROM tickets
")->fetch();

$chartEstado = $pdo->query("
    SELECT estado, COUNT(*) AS total FROM tickets GROUP BY estado
")->fetchAll();

$estadoData = [];
foreach ($chartEstado as $row) {
    $estadoData[] = ['label' => ucfirst(str_replace('_', ' ', $row['estado'])), 'value' => (int) $row['total']];
}

$chartPrioridad = $pdo->query("
    SELECT prioridad, COUNT(*) AS total FROM tickets GROUP BY prioridad
")->fetchAll();

$prioridadData = [];
$prioridadColorMap = [
    'baja' => '#6ee7b7',
    'media' => '#fcd34d',
    'alta' => '#fdba74',
    'urgente' => '#dc2626',
];
foreach ($chartPrioridad as $row) {
    $prioridadData[] = [
        'label' => ucfirst($row['prioridad']),
        'value' => (int) $row['total'],
        'color' => $prioridadColorMap[$row['prioridad']] ?? '#ccc',
    ];
}

echo json_encode([
    'stats' => [
        'abiertos'    => (int) ($stats['abiertos'] ?? 0),
        'en_progreso' => (int) ($stats['en_progreso'] ?? 0),
        'urgentes'    => (int) ($stats['urgentes'] ?? 0),
        'total'       => (int) ($stats['total'] ?? 0),
    ],
    'estado'    => $estadoData,
    'prioridad' => $prioridadData,
]);
