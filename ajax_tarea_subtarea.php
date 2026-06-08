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

$csrf_token = $_POST['csrf_token'] ?? '';
if (!validarTokenCSRF($csrf_token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Token invalido']);
    exit;
}

$accion = $_POST['accion'] ?? '';
$subtarea_id = (int) ($_POST['subtarea_id'] ?? 0);

try {
    if ($accion === 'toggle' && $subtarea_id > 0) {
        $stmt = $pdo->prepare('UPDATE tarea_subtareas SET completado = NOT completado WHERE id = :id');
        $stmt->execute([':id' => $subtarea_id]);

        // Get current state
        $chk = $pdo->prepare('SELECT completado, tarea_id FROM tarea_subtareas WHERE id = :id');
        $chk->execute([':id' => $subtarea_id]);
        $sub = $chk->fetch();

        // Count progress
        $cnt = $pdo->prepare('SELECT COUNT(*) AS total, SUM(completado) AS completadas FROM tarea_subtareas WHERE tarea_id = :tarea_id');
        $cnt->execute([':tarea_id' => $sub['tarea_id']]);
        $prog = $cnt->fetch();
        $porcentaje = $prog['total'] > 0 ? round(($prog['completadas'] / $prog['total']) * 100) : 0;

        echo json_encode([
            'success' => true,
            'completado' => (int) $sub['completado'],
            'total' => (int) $prog['total'],
            'completadas' => (int) $prog['completadas'],
            'porcentaje' => $porcentaje,
        ]);
    } elseif ($accion === 'eliminar' && $subtarea_id > 0) {
        $pdo->prepare('DELETE FROM tarea_subtareas WHERE id = :id')->execute([':id' => $subtarea_id]);
        echo json_encode(['success' => true]);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Accion invalida']);
    }
} catch (PDOException $e) {
    error_log('Error ajax_tarea_subtarea: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error']);
}
