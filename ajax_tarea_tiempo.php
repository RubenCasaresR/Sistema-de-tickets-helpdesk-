<?php
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
$csrf_token = $_POST['csrf_token'] ?? '';

if (!validarTokenCSRF($csrf_token)) {
    $_SESSION['error_message'] = 'Token invalido.';
    redirect('tarea_ver.php?id=' . ((int) ($_POST['tarea_id'] ?? 0)));
    exit;
}

$tarea_id = (int) ($_POST['tarea_id'] ?? 0);
$accion = $_POST['accion'] ?? '';
$usuario_id = $_SESSION['usuario_id'];

if ($tarea_id <= 0) {
    redirect('tareas.php');
    exit;
}

try {
    if ($accion === 'iniciar') {
        try {
            $pdo->beginTransaction();
            $chk = $pdo->prepare('SELECT id FROM tarea_tiempo WHERE usuario_id = :uid AND fecha_fin IS NULL FOR UPDATE');
            $chk->execute([':uid' => $usuario_id]);
            if ($chk->fetch()) {
                $_SESSION['error_message'] = 'Ya tienes un timer activo. Detenlo antes de iniciar otro.';
            } else {
                $ins = $pdo->prepare('INSERT INTO tarea_tiempo (tarea_id, usuario_id, fecha_inicio) VALUES (:tarea_id, :usuario_id, NOW())');
                $ins->execute([':tarea_id' => $tarea_id, ':usuario_id' => $usuario_id]);
                registrarHistorial($pdo, $tarea_id, $usuario_id, 'tiempo', 'Inicio timer');
                $_SESSION['success_message'] = 'Timer iniciado.';
            }
            $pdo->commit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log('Error al iniciar timer: ' . $e->getMessage());
            $_SESSION['error_message'] = 'Error con el timer.';
        }
    } elseif ($accion === 'detener') {
        $upd = $pdo->prepare("UPDATE tarea_tiempo SET fecha_fin = NOW() WHERE tarea_id = :tarea_id AND usuario_id = :usuario_id AND fecha_fin IS NULL ORDER BY id DESC LIMIT 1");
        $upd->execute([':tarea_id' => $tarea_id, ':usuario_id' => $usuario_id]);
        if ($upd->rowCount() > 0) {
            registrarHistorial($pdo, $tarea_id, $usuario_id, 'tiempo', 'Detuvo timer');
            $_SESSION['success_message'] = 'Timer detenido.';
        }
    }
} catch (PDOException $e) {
    error_log('Error ajax_tarea_tiempo: ' . $e->getMessage());
    $_SESSION['error_message'] = 'Error con el timer.';
}

redirect('tarea_ver.php?id=' . $tarea_id);
exit;
