<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/conexion.php';
requiereAutenticacion();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit;
}

$pdo = obtenerConexion();

$stmt = $pdo->prepare('
    SELECT a.*, t.creador_id AS ticket_creador_id, t.asignado_id,
           c.usuario_id AS comentario_usuario_id
    FROM archivos_ticket a
    JOIN tickets t ON t.id = a.ticket_id
    LEFT JOIN comentarios_ticket c ON c.id = a.comentario_id
    WHERE a.id = :id
');
$stmt->execute([':id' => $id]);
$archivo = $stmt->fetch();

if (!$archivo) {
    http_response_code(404);
    exit;
}

$ticket_id = (int) $archivo['ticket_id'];

// --- Permission check ---
$usuario_id = (int) $_SESSION['usuario_id'];
$rol = $_SESSION['rol'];

$tiene_acceso = false;

if ($rol === 'admin') {
    $tiene_acceso = true;
} elseif ((int) $archivo['ticket_creador_id'] === $usuario_id) {
    $tiene_acceso = true;
} elseif ((int) $archivo['asignado_id'] === $usuario_id) {
    $tiene_acceso = true;
} elseif ((int) $archivo['comentario_usuario_id'] === $usuario_id) {
    $tiene_acceso = true;
}

if (!$tiene_acceso) {
    http_response_code(403);
    echo 'Acceso denegado.';
    exit;
}

// --- Serve file ---
$path = __DIR__ . '/uploads/tickets/' . $ticket_id . '/' . $archivo['nombre_archivo'];
if (!file_exists($path)) {
    http_response_code(404);
    echo 'Archivo no encontrado.';
    exit;
}

$filename = $archivo['nombre_original'];
$mime = $archivo['tipo'];

header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . rawurlencode($filename) . '"');
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');

readfile($path);
