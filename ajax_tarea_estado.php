<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/tareas_helper.php';
requiereAutenticacion();

$is_staff = in_array($_SESSION['rol'], ['soporte', 'admin'], true);
if (!$is_staff) {
    http_response_code(403);
    exit;
}

$pdo = obtenerConexion();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$tarea_id = (int) ($input['tarea_id'] ?? 0);
$estado = $input['estado'] ?? '';
$csrf_token = $input['csrf_token'] ?? '';

if (!validarTokenCSRF($csrf_token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Token invalido']);
    exit;
}

if ($tarea_id <= 0 || !in_array($estado, ['pendiente', 'en_progreso', 'en_revision', 'completada', 'cancelada'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Datos invalidos']);
    exit;
}

try {
    $stmt = $pdo->prepare('UPDATE tareas SET estado = :estado' . ($estado === 'completada' ? ', fecha_completada = NOW()' : '') . ' WHERE id = :id');
    $stmt->execute([':estado' => $estado, ':id' => $tarea_id]);
    registrarHistorial($pdo, $tarea_id, $_SESSION['usuario_id'], 'estado', "Cambio estado a: {$estado}");

    // Sincronizar estado del ticket vinculado
    $tareaStmt = $pdo->prepare('SELECT ticket_id FROM tareas WHERE id = :id');
    $tareaStmt->execute([':id' => $tarea_id]);
    $tarea = $tareaStmt->fetch();
    if ($tarea && !empty($tarea['ticket_id'])) {
        sincronizarEstadoTicketDesdeTarea($pdo, (int) $tarea['ticket_id'], $_SESSION['usuario_id'], $estado);
    }

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    error_log('Error ajax_tarea_estado: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error al actualizar']);
}
