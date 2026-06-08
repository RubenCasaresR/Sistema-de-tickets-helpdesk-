<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/conexion.php';
requiereAutenticacion();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Metodo no permitido.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$file_id = (int) ($input['id'] ?? 0);
$csrf_token = $input['csrf_token'] ?? '';

if (!validarTokenCSRF($csrf_token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Token de seguridad invalido.']);
    exit;
}

if ($file_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ID de archivo invalido.']);
    exit;
}

$pdo = obtenerConexion();

$stmt = $pdo->prepare('
    SELECT a.*, t.creador_id AS ticket_creador_id, t.asignado_id
    FROM archivos_ticket a
    JOIN tickets t ON t.id = a.ticket_id
    WHERE a.id = :id
');
$stmt->execute([':id' => $file_id]);
$archivo = $stmt->fetch();

if (!$archivo) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Archivo no encontrado.']);
    exit;
}

$usuario_id = (int) $_SESSION['usuario_id'];
$rol = $_SESSION['rol'];
$tiene_permiso = false;

if ($rol === 'admin') {
    $tiene_permiso = true;
} elseif (in_array($rol, ['soporte'], true)) {
    $tiene_permiso = true;
} elseif ((int) $archivo['usuario_id'] === $usuario_id) {
    $tiene_permiso = true;
} elseif ((int) $archivo['ticket_creador_id'] === $usuario_id) {
    $tiene_permiso = true;
}

if (!$tiene_permiso) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'No tienes permiso para eliminar este archivo.']);
    exit;
}

try {
    $pdo->beginTransaction();

    $del = $pdo->prepare('DELETE FROM archivos_ticket WHERE id = :id');
    $del->execute([':id' => $file_id]);

    $ticket_id = (int) $archivo['ticket_id'];
    $path = __DIR__ . '/uploads/tickets/' . $ticket_id . '/' . $archivo['nombre_archivo'];
    if (file_exists($path)) {
        @unlink($path);
    }

    $pdo->commit();

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log('Error al eliminar archivo: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno del servidor.']);
}
