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

if ($filtro_tipo === 'activos') {
    $conditions[] = "t.estado IN ('abierto', 'en_progreso', 'resuelto')";
} elseif ($filtro_tipo === 'cerrados') {
    $conditions[] = "t.estado = 'cerrado'";
}

if ($filtro_desde !== '') {
    $conditions[] = 't.fecha_creacion >= :desde';
    $params[':desde'] = $filtro_desde . ' 00:00:00';
}
if ($filtro_hasta !== '') {
    $conditions[] = 't.fecha_creacion <= :hasta';
    $params[':hasta'] = $filtro_hasta . ' 23:59:59';
}
if ($filtro_asignado !== '' && $filtro_asignado !== '0') {
    $conditions[] = 't.asignado_id = :asignado_id';
    $params[':asignado_id'] = (int) $filtro_asignado;
}

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
