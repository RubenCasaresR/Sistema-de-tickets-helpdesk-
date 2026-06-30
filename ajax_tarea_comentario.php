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

$tarea_id = (int) ($_POST['tarea_id'] ?? 0);
$mensaje = trim($_POST['mensaje'] ?? '');

if ($tarea_id <= 0 || $mensaje === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Datos invalidos']);
    exit;
}

try {
    $mensaje = sanitizarDescripcion($mensaje);
    if ($mensaje === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'El comentario no puede estar vacio.']);
        exit;
    }
    $ins = $pdo->prepare('INSERT INTO tarea_comentarios (tarea_id, usuario_id, mensaje) VALUES (:tarea_id, :usuario_id, :mensaje)');
    $ins->execute([':tarea_id' => $tarea_id, ':usuario_id' => $_SESSION['usuario_id'], ':mensaje' => $mensaje]);
    registrarHistorial($pdo, $tarea_id, $_SESSION['usuario_id'], 'comentario', 'Agrego un comentario');

    // Return new comment HTML
    $comId = (int) $pdo->lastInsertId();
    $stmt = $pdo->prepare('SELECT c.*, u.nombre, u.rol FROM tarea_comentarios c JOIN usuarios u ON u.id = c.usuario_id WHERE c.id = :id');
    $stmt->execute([':id' => $comId]);
    $com = $stmt->fetch();
    ob_start();
    ?>
    <div class="comment">
        <div class="comment-avatar <?= $com['rol'] !== 'cliente' ? 'staff' : '' ?>">
            <?= htmlspecialchars(strtoupper(substr($com['nombre'], 0, 2))) ?>
        </div>
        <div class="comment-body">
            <div class="comment-meta">
                <span class="comment-author"><?= htmlspecialchars($com['nombre']) ?></span>
                <span class="comment-date"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($com['fecha_creacion']))) ?></span>
            </div>
            <div class="comment-text"><?= nl2br(htmlspecialchars($com['mensaje'])) ?></div>
        </div>
    </div>
    <?php
    echo json_encode(['success' => true, 'html' => ob_get_clean()]);
} catch (PDOException $e) {
    if (ob_get_level()) ob_end_clean();
    error_log('Error ajax_tarea_comentario: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error']);
}

