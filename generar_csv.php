<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/conexion.php';
requiereRol(['soporte', 'admin']);

$pdo = obtenerConexion();

$filtro_tipo    = $_GET['tipo'] ?? 'todos';
$filtro_desde   = $_GET['desde'] ?? '';
$filtro_hasta   = $_GET['hasta'] ?? '';
$filtro_asignado = $_GET['asignado_id'] ?? '';

$conditions = [];
$params = [];
buildReportFilters($conditions, $params);

$sql = '
    SELECT t.folio, t.titulo, t.estado, t.prioridad,
           c.nombre AS creador_nombre,
           a.nombre AS asignado_nombre,
           t.fecha_creacion, t.fecha_cierre
    FROM tickets t
    JOIN usuarios c ON c.id = t.creador_id
    LEFT JOIN usuarios a ON a.id = t.asignado_id
';
if (count($conditions) > 0) {
    $sql .= ' WHERE ' . implode(' AND ', $conditions);
}
$sql .= ' ORDER BY t.fecha_creacion DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll();

if (count($tickets) > 5000) {
    $tickets = array_slice($tickets, 0, 5000);
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="reporte_tickets_' . date('Y-m-d') . '.csv"');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8
fputcsv($output, ['Folio', 'Titulo', 'Estado', 'Prioridad', 'Creador', 'Asignado', 'Fecha Creacion', 'Fecha Cierre']);

foreach ($tickets as $t) {
    fputcsv($output, [
        $t['folio'],
        $t['titulo'],
        $t['estado'],
        $t['prioridad'],
        $t['creador_nombre'],
        $t['asignado_nombre'] ?? '',
        $t['fecha_creacion'],
        $t['fecha_cierre'] ?? '',
    ]);
}

fclose($output);
