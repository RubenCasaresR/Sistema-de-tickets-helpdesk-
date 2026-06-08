<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/conexion.php';
requiereRol(['soporte', 'admin']);

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

$ticket_id  = (int) ($input['ticket_id'] ?? 0);
$estado     = $input['estado'] ?? '';
$csrf_token = $input['csrf_token'] ?? '';

if (!validarTokenCSRF($csrf_token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Token de seguridad invalido.']);
    exit;
}

if ($ticket_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ID de ticket invalido.']);
    exit;
}

if (!in_array($estado, ['abierto', 'en_progreso', 'resuelto', 'cerrado'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Estado invalido.']);
    exit;
}

if ($estado === 'cerrado' && $_SESSION['rol'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Solo administradores pueden cerrar tickets.']);
    exit;
}

try {
    $pdo = obtenerConexion();
    $fecha_cierre = $estado === 'cerrado' ? date('Y-m-d H:i:s') : null;

    $upd = $pdo->prepare('UPDATE tickets SET estado = :estado, fecha_cierre = :fecha_cierre WHERE id = :id');
    $upd->execute([
        ':estado'       => $estado,
        ':fecha_cierre' => $fecha_cierre,
        ':id'           => $ticket_id,
    ]);

    registrarHistorialTicket($pdo, $ticket_id, $_SESSION['usuario_id'], 'estado', "Estado cambiado a {$estado}");

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    error_log('Error al actualizar estado via AJAX: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno del servidor.']);
}
