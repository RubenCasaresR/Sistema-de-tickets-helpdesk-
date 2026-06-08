<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/tareas_helper.php';
requiereAutenticacion();

$pdo = obtenerConexion();

$ticket_id = (int) ($_GET['id'] ?? 0);
if ($ticket_id <= 0) {
    redirect('index.php');
    exit;
}

// Obtener ticket
$stmt = $pdo->prepare('
    SELECT t.*, c.nombre AS creador_nombre, a.nombre AS asignado_nombre, cat.nombre AS categoria_nombre
    FROM tickets t
    JOIN usuarios c ON c.id = t.creador_id
    LEFT JOIN usuarios a ON a.id = t.asignado_id
    LEFT JOIN categorias cat ON cat.id = t.categoria_id
    WHERE t.id = :id
');
$stmt->execute([':id' => $ticket_id]);
$ticket = $stmt->fetch();

if (!$ticket) {
    redirect('index.php');
    exit;
}

// Cliente solo ve sus propios tickets
if ($_SESSION['rol'] === 'cliente' && (int) $ticket['creador_id'] !== $_SESSION['usuario_id']) {
    redirect('mis_tickets.php');
    exit;
}

$is_staff = in_array($_SESSION['rol'], ['soporte', 'admin'], true);
$is_admin = $_SESSION['rol'] === 'admin';

// ---- Procesar formularios ----
$error = '';

// Solo admin puede cerrar tickets (antes lo podia hacer el cliente)
if (!$is_staff && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'cambiar_estado') {
    $error = 'No tienes permiso para realizar esta accion.';
}

// Cambiar estado (staff)
if ($is_staff && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';

    if (!validarTokenCSRF($csrf_token)) {
        $error = 'Token de seguridad invalido.';
    } elseif ($_POST['accion'] === 'cambiar_estado') {
        $nuevo_estado = $_POST['estado'] ?? '';
        if ($nuevo_estado === 'cerrado' && !$is_admin) {
            $error = 'Solo administradores pueden cerrar tickets.';
        } elseif (in_array($nuevo_estado, ['abierto', 'en_progreso', 'resuelto', 'cerrado'], true)) {
            $fecha_cierre = $nuevo_estado === 'cerrado' ? date('Y-m-d H:i:s') : null;
            $upd = $pdo->prepare('UPDATE tickets SET estado = :estado, fecha_cierre = :fecha_cierre WHERE id = :id');
            $upd->execute([':estado' => $nuevo_estado, ':fecha_cierre' => $fecha_cierre, ':id' => $ticket_id]);

            registrarHistorialTicket($pdo, $ticket_id, $_SESSION['usuario_id'], 'estado', "Estado cambiado a {$nuevo_estado}");

            if (file_exists(__DIR__ . '/helpers/mailer.php')) {
                require_once __DIR__ . '/helpers/mailer.php';
                $destStmt = $pdo->prepare('SELECT email FROM usuarios WHERE id = :id');
                $destStmt->execute([':id' => $ticket['creador_id']]);
                $dest = $destStmt->fetch();
                if ($dest) {
                    notificarCambioEstado($ticket['folio'], $nuevo_estado, $dest['email'], $ticket_id);
                }
            }

            $_SESSION['success_message'] = 'Estado actualizado correctamente.';
            redirect('ver_ticket.php?id=' . $ticket_id);
            exit;
        }
        $error = 'Estado invalido.';
    } elseif ($is_admin && $_POST['accion'] === 'asignar') {
        $asignado_id = (int) ($_POST['asignado_id'] ?? 0);
        if ($asignado_id > 0) {
            $check = $pdo->prepare('SELECT id, nombre, email FROM usuarios WHERE id = :id AND rol IN (\'soporte\', \'admin\')');
            $check->execute([':id' => $asignado_id]);
            $nuevoResponsable = $check->fetch();
            if ($nuevoResponsable) {
                $upd = $pdo->prepare('UPDATE tickets SET asignado_id = :asignado_id WHERE id = :id');
                $upd->execute([':asignado_id' => $asignado_id, ':id' => $ticket_id]);

                registrarHistorialTicket($pdo, $ticket_id, $_SESSION['usuario_id'], 'asignacion', "Asignado a {$nuevoResponsable['nombre']}");

                if (file_exists(__DIR__ . '/helpers/mailer.php')) {
                    require_once __DIR__ . '/helpers/mailer.php';
                    notificarAsignacion($ticket['folio'], $nuevoResponsable['nombre'], $nuevoResponsable['email'], $ticket_id);
                }

                $_SESSION['success_message'] = 'Ticket reasignado correctamente.';
                redirect('ver_ticket.php?id=' . $ticket_id);
                exit;
            }
        } elseif ($asignado_id === 0) {
            $upd = $pdo->prepare('UPDATE tickets SET asignado_id = NULL WHERE id = :id');
            $upd->execute([':id' => $ticket_id]);
            registrarHistorialTicket($pdo, $ticket_id, $_SESSION['usuario_id'], 'asignacion', 'Asignacion eliminada');
            $_SESSION['success_message'] = 'Asignacion eliminada.';
            redirect('ver_ticket.php?id=' . $ticket_id);
            exit;
        }
        $error = 'Usuario de soporte invalido.';
    }
}

// Detectar solicitud AJAX
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// Anadir comentario (solicitudes no-AJAX — las AJAX se manejan abajo)
if (!$is_ajax && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'comentar') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    $mensaje    = trim($_POST['mensaje'] ?? '');

    if (!validarTokenCSRF($csrf_token)) {
        $error = 'Token de seguridad invalido.';
    } elseif (trim(strip_tags($mensaje)) === '') {
        $error = 'El mensaje no puede estar vacio.';
    } else {
        $mensaje = sanitizarDescripcion($mensaje);
        $es_interna = $is_staff ? ((int) ($_POST['es_interna'] ?? 0)) : 0;

        $ins = $pdo->prepare('INSERT INTO comentarios_ticket (ticket_id, usuario_id, mensaje, es_interna) VALUES (:ticket_id, :usuario_id, :mensaje, :es_interna)');
        $ins->execute([
            ':ticket_id'   => $ticket_id,
            ':usuario_id'  => $_SESSION['usuario_id'],
            ':mensaje'     => $mensaje,
            ':es_interna'  => $es_interna,
        ]);

        // Si esta abierto y el staff comenta, pasar a en_progreso automaticamente
        if ($is_staff && $ticket['estado'] === 'abierto') {
            $pdo->prepare('UPDATE tickets SET estado = \'en_progreso\' WHERE id = :id')->execute([':id' => $ticket_id]);
            registrarHistorialTicket($pdo, $ticket_id, $_SESSION['usuario_id'], 'estado', 'Estado cambiado a en_progreso (staff comento)');
        }

        // Notificar por correo
        if (file_exists(__DIR__ . '/helpers/mailer.php')) {
            require_once __DIR__ . '/helpers/mailer.php';
            $autor = $_SESSION['nombre'] ?? 'Usuario';
            if ($is_staff) {
                // Staff comenta → notificar al creador
                $destStmt = $pdo->prepare('SELECT email FROM usuarios WHERE id = :id');
                $destStmt->execute([':id' => $ticket['creador_id']]);
                $dest = $destStmt->fetch();
                if ($dest) {
                    notificarComentario($pdo, $ticket_id, $ticket['folio'], $autor, $mensaje, $dest['email']);
                }
            } else {
                // Cliente comenta → notificar al asignado
                if ($ticket['asignado_id']) {
                    $destStmt = $pdo->prepare('SELECT email FROM usuarios WHERE id = :id');
                    $destStmt->execute([':id' => $ticket['asignado_id']]);
                    $dest = $destStmt->fetch();
                    if ($dest) {
                        notificarComentario($pdo, $ticket_id, $ticket['folio'], $autor, $mensaje, $dest['email']);
                    }
                }
            }
        }

        redirect('ver_ticket.php?id=' . $ticket_id);
        exit;
    }
}

// Obtener comentarios (preparar statement para usar tanto en AJAX como en carga inicial)
$comStmt = $pdo->prepare('
    SELECT c.*, u.nombre, u.rol
    FROM comentarios_ticket c
    JOIN usuarios u ON u.id = c.usuario_id
    WHERE c.ticket_id = :ticket_id' . ($is_staff ? '' : ' AND c.es_interna = 0') . '
    ORDER BY c.fecha ASC
');

// Si es AJAX y se envio un comentario, devolver solo el HTML del contenedor de comentarios
if ($is_ajax && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'comentar') {
    // Re-fetch ticket
    $stmt->execute([':id' => $ticket_id]);
    $ticket = $stmt->fetch();

    // Re-fetch comments
    $comStmt->execute([':ticket_id' => $ticket_id]);
    $comentarios = $comStmt->fetchAll();

    // Generate fresh CSRF token
    $csrf_token = generarTokenCSRF();
    ?>
    <div class="comment-thread">
        <?php if (count($comentarios) === 0): ?>
            <p class="text-muted">No hay avances registrados aun.</p>
        <?php else: ?>
            <?php foreach ($comentarios as $com): ?>
                <div class="comment<?= (int) $com['usuario_id'] === (int) $ticket['asignado_id'] ? ' assigned' : '' ?><?= !empty($com['es_interna']) ? ' is-interna' : '' ?>">
                    <div class="comment-avatar <?= $com['rol'] === 'admin' ? 'admin' : ($com['rol'] === 'soporte' ? 'staff' : '') ?>">
                        <?= htmlspecialchars(strtoupper(substr($com['nombre'], 0, 2))) ?>
                    </div>
                    <div class="comment-body">
                        <div class="comment-meta">
                            <span class="comment-author"><?= htmlspecialchars($com['nombre']) ?></span>
                            <span class="comment-role-badge <?= $com['rol'] !== 'cliente' ? 'staff' : '' ?>">
                                <?= htmlspecialchars($com['rol']) ?>
                            </span>
                            <?php if ((int) $com['usuario_id'] === (int) $ticket['asignado_id']): ?>
                                <span class="comment-role-badge assigned-badge">Responsable</span>
                            <?php endif; ?>
                            <?php if (!empty($com['es_interna'])): ?>
                                <span class="comment-role-badge" style="background:#dc2626;color:#fff">🔒 Interna</span>
                            <?php endif; ?>
                            <span class="comment-date"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($com['fecha']))) ?></span>
                        </div>
                        <div class="comment-text"><?php
                            if (strip_tags($com['mensaje']) !== $com['mensaje']) {
                                echo $com['mensaje'];
                            } else {
                                echo nl2br(htmlspecialchars($com['mensaje']));
                            }
                        ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

            <div class="comment-form">
                <form method="post" action="" id="formComentario">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <input type="hidden" name="accion" value="comentar">
                    <div class="quill-wrapper">
                        <label>Registrar avance o responder</label>
                        <div class="quill-editor" data-target="mensaje" data-placeholder="Describe los avances realizados hoy..."></div>
                        <input type="hidden" name="mensaje" value="">
                    </div>
                    <div class="form-group" style="margin-top:10px">
                        <label class="flex gap-2" style="align-items:center;cursor:pointer;font-weight:400">
                            <input type="checkbox" name="es_interna" value="1">
                            <span>🔒 Nota interna (solo visible para staff)</span>
                        </label>
                    </div>
                    <button type="submit" class="btn btn-primary">Registrar Avance</button>
                </form>
            </div>
    <?php
    exit;
}

// Recargar ticket despues de modificaciones
$stmt->execute([':id' => $ticket_id]);
$ticket = $stmt->fetch();

$comStmt->execute([':ticket_id' => $ticket_id]);
$comentarios = $comStmt->fetchAll();

// Obtener archivos adjuntos
$archivos = [];
try {
    $archStmt = $pdo->prepare('SELECT * FROM archivos_ticket WHERE ticket_id = :ticket_id ORDER BY fecha ASC');
    $archStmt->execute([':ticket_id' => $ticket_id]);
    $archivos = $archStmt->fetchAll();
} catch (PDOException $e) {
    // Table may not exist yet
    $archivos = [];
}

// Historial del ticket
try {
    $historial = obtenerHistorialTicket($pdo, $ticket_id);
} catch (PDOException $e) {
    $historial = [];
}
// Tareas vinculadas al ticket
$tareas_vinculadas = [];
try {
    $tareasStmt = $pdo->prepare('
        SELECT t.*, a.nombre AS asignado_nombre
        FROM tareas t
        LEFT JOIN usuarios a ON a.id = t.asignado_id
        WHERE t.ticket_id = :ticket_id
        ORDER BY t.fecha_creacion DESC
    ');
    $tareasStmt->execute([':ticket_id' => $ticket_id]);
    $tareas_vinculadas = $tareasStmt->fetchAll();
} catch (PDOException $e) {
    $tareas_vinculadas = [];
}

// Personal de soporte para asignacion
$staffStmt = $pdo->query("SELECT id, nombre FROM usuarios WHERE rol IN ('soporte', 'admin') ORDER BY nombre");
$personal_staff = $staffStmt->fetchAll();

$csrf_token = generarTokenCSRF();

// Success message
$success = '';
if (isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

$page_title = 'Ticket ' . ($ticket['folio'] ?? '');
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<?php if ($success !== ''): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="page-header">
    <a href="<?= $is_staff ? url('panel_admin.php') : url('mis_tickets.php') ?>" class="btn btn-outline btn-sm mb-4">&larr; Volver</a>
    <h1>Ticket <?= htmlspecialchars($ticket['folio']) ?></h1>
    <p><?= htmlspecialchars($ticket['titulo']) ?></p>
</div>

<div class="split-layout">
    <!-- Left: Ticket Info + Comments -->
    <div>
        <div class="card mb-6">
            <div class="card-header">
                <h3>Detalle del Ticket</h3>
                <div class="flex gap-2">
                    <span class="badge badge-<?= htmlspecialchars($ticket['estado']) ?>">
                        <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $ticket['estado']))) ?>
                    </span>
                    <span class="badge badge-<?= htmlspecialchars($ticket['prioridad']) ?>">
                        <?= htmlspecialchars(ucfirst($ticket['prioridad'])) ?>
                    </span>
                    <?php if (!empty($ticket['categoria_nombre'])): ?>
                        <span class="badge badge-cerrado"><?= htmlspecialchars($ticket['categoria_nombre']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <div class="mb-4">
                    <h3><?= htmlspecialchars($ticket['titulo']) ?></h3>
                    <p class="text-muted text-small">Creado por <strong><?= htmlspecialchars($ticket['creador_nombre']) ?></strong> el <?= htmlspecialchars(date('d/m/Y H:i', strtotime($ticket['fecha_creacion']))) ?></p>
                </div>
                <div style="font-size:0.9rem;color:var(--color-text-primary);line-height:1.7">
                    <?= $ticket['descripcion'] ?>
                </div>
            </div>
        </div>

        <!-- Tareas Vinculadas -->
        <?php if ($is_staff && count($tareas_vinculadas) > 0): ?>
        <div class="card mt-6">
            <div class="card-header">
                <h3>Tareas Vinculadas (<?= count($tareas_vinculadas) ?>)</h3>
            </div>
            <div class="card-body">
                <div class="linked-tasks-list">
                    <?php foreach ($tareas_vinculadas as $tv): ?>
                        <div class="linked-task-item">
                            <div class="linked-task-info">
                                <a href="<?= url('tarea_ver.php?id=' . (int) $tv['id']) ?>" class="linked-task-title">
                                    <?= htmlspecialchars($tv['titulo']) ?>
                                </a>
                                <span class="text-muted text-small">
                                    <?= htmlspecialchars($tv['asignado_nombre'] ?? 'Sin asignar') ?>
                                </span>
                            </div>
                            <div class="flex gap-2">
                                <?= renderEstadoBadge($tv['estado']) ?>
                                <span class="badge badge-<?= htmlspecialchars($tv['prioridad']) ?>">
                                    <?= htmlspecialchars(ucfirst($tv['prioridad'])) ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- File Upload -->
        <div class="card mt-6">
            <div class="card-header">
                <h3>Archivos Adjuntos</h3>
                <?php if (count($archivos) > 0): ?>
                    <span class="badge badge-cerrado"><?= count($archivos) ?> archivo(s)</span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <div class="drop-zone" data-ticket-id="<?= (int) $ticket_id ?>" data-preview="filePreviewList-<?= (int) $ticket_id ?>">
                    <div class="drop-icon">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    </div>
                    <div class="drop-text">Arrastra archivos aqui o haz clic para seleccionar</div>
                    <div class="drop-hint">PDF, imagenes, documentos — Max. 10 MB por archivo</div>
                    <input type="file" multiple accept="image/*,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/zip,application/x-rar-compressed,text/plain" style="display:none">
                </div>
                <div class="file-preview-list" id="filePreviewList-<?= (int) $ticket_id ?>"></div>

                <?php if (count($archivos) > 0): ?>
                <div class="file-gallery">
                    <div class="file-gallery-title">Archivos subidos</div>
                    <?php foreach ($archivos as $archivo): ?>
                        <?php
                        $url = url('descargar_archivo.php?id=' . (int) $archivo['id']);
                        $es_imagen = strpos($archivo['tipo'], 'image/') === 0;
                        $puede_eliminar = $is_staff || (int) $archivo['usuario_id'] === (int) $_SESSION['usuario_id'] || (int) $ticket['creador_id'] === (int) $_SESSION['usuario_id'];
                        ?>
                        <div class="file-gallery-item-wrapper">
                            <a href="<?= $url ?>" class="file-gallery-item" target="_blank" title="<?= htmlspecialchars($archivo['nombre_original']) ?>">
                                <span class="file-icon-sm"><?= $es_imagen ? '🖼️' : '📎' ?></span>
                                <span><?= htmlspecialchars($archivo['nombre_original']) ?></span>
                                <span class="text-muted text-small">(<?= round($archivo['tamano'] / 1024) ?> KB)</span>
                            </a>
                            <?php if ($puede_eliminar): ?>
                                <button type="button" class="file-delete-btn" data-file-id="<?= (int) $archivo['id'] ?>" data-filename="<?= htmlspecialchars($archivo['nombre_original']) ?>" title="Eliminar archivo">&times;</button>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Comments -->
        <div class="card mt-6">
            <div class="card-header">
                <h3>Bitacora de Avances (<?= count($comentarios) ?>)</h3>
            </div>
            <div class="card-body" id="listaComentarios">
                <?php if (count($comentarios) === 0): ?>
<p class="text-muted">No hay avances registrados aun.</p>
                <?php else: ?>
                    <div class="comment-thread">
                        <?php foreach ($comentarios as $com): ?>
                            <div class="comment<?= (int) $com['usuario_id'] === (int) $ticket['asignado_id'] ? ' assigned' : '' ?><?= !empty($com['es_interna']) ? ' is-interna' : '' ?>">
                                <div class="comment-avatar <?= $com['rol'] === 'admin' ? 'admin' : ($com['rol'] === 'soporte' ? 'staff' : '') ?>">
                                    <?= htmlspecialchars(strtoupper(substr($com['nombre'], 0, 2))) ?>
                                </div>
                                <div class="comment-body">
                                    <div class="comment-meta">
                                        <span class="comment-author"><?= htmlspecialchars($com['nombre']) ?></span>
                                        <span class="comment-role-badge <?= $com['rol'] !== 'cliente' ? 'staff' : '' ?>">
                                            <?= htmlspecialchars($com['rol']) ?>
                                        </span>
                                        <?php if ((int) $com['usuario_id'] === (int) $ticket['asignado_id']): ?>
                                            <span class="comment-role-badge assigned-badge">Responsable</span>
                                        <?php endif; ?>
                                        <span class="comment-date"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($com['fecha']))) ?></span>
                                    </div>
                                    <div class="comment-text"><?php
                                        if (strip_tags($com['mensaje']) !== $com['mensaje']) {
                                            echo $com['mensaje'];
                                        } else {
                                            echo nl2br(htmlspecialchars($com['mensaje']));
                                        }
                                    ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Comment Form -->
                <div class="comment-form">
                    <form method="post" action="" id="formComentario">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        <input type="hidden" name="accion" value="comentar">

                        <div class="quill-wrapper">
                            <label>Registrar avance o responder</label>
                            <div class="quill-editor" data-target="mensaje" data-placeholder="Describe los avances realizados hoy..."></div>
                            <input type="hidden" name="mensaje" value="">
                        </div>
                        <?php if ($is_staff): ?>
                        <div class="form-group" style="margin-top:10px">
                            <label class="flex gap-2" style="align-items:center;cursor:pointer;font-weight:400">
                                <input type="checkbox" name="es_interna" value="1">
                                <span>🔒 Nota interna (solo visible para staff)</span>
                            </label>
                        </div>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary">Registrar Avance</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Historial -->
        <div class="card mt-6">
            <div class="card-header">
                <h3>Historial de Actividad</h3>
            </div>
            <div class="card-body">
                <?php if (count($historial) === 0): ?>
                    <p class="text-muted">No hay actividad registrada.</p>
                <?php else: ?>
                    <div class="historial-list">
                    <?php foreach ($historial as $h): ?>
                        <div class="historial-item">
                            <span class="historial-accion badge">
                                <?= htmlspecialchars(ucfirst($h['accion'])) ?>
                            </span>
                            <span class="historial-detalle"><?= htmlspecialchars($h['detalle'] ?? '') ?></span>
                            <span class="historial-meta text-muted text-small">
                                <?= htmlspecialchars($h['nombre']) ?> — <?= htmlspecialchars(date('d/m/Y H:i', strtotime($h['fecha_creacion']))) ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($is_staff): ?>
    <!-- Right: Staff Panel -->
    <div>
        <div class="side-panel">
            <h3>Informacion</h3>
            <div class="info-row">
                <span class="label">Folio</span>
                <span class="value"><?= htmlspecialchars($ticket['folio']) ?></span>
            </div>
            <div class="info-row">
                <span class="label">Creador</span>
                <span class="value"><?= htmlspecialchars($ticket['creador_nombre']) ?></span>
            </div>
            <div class="info-row">
                <span class="label">Responsable</span>
                <span class="value"><?= htmlspecialchars($ticket['asignado_nombre'] ?? 'Sin asignar') ?></span>
            </div>
            <div class="info-row">
                <span class="label">Creado</span>
                <span class="value text-small"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($ticket['fecha_creacion']))) ?></span>
            </div>
            <?php if ($ticket['fecha_cierre']): ?>
            <div class="info-row">
                <span class="label">Cerrado</span>
                <span class="value text-small"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($ticket['fecha_cierre']))) ?></span>
            </div>
            <?php endif; ?>
        </div>

        <!-- Change Status -->
        <div class="side-panel mt-6">
            <h3>Cambiar Estado</h3>
            <form method="post" action="">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" name="accion" value="cambiar_estado">

                <div class="form-group">
                    <select name="estado" class="form-control">
                        <option value="abierto" <?= $ticket['estado'] === 'abierto' ? 'selected' : '' ?>>Abierto</option>
                        <option value="en_progreso" <?= $ticket['estado'] === 'en_progreso' ? 'selected' : '' ?>>En Progreso</option>
                        <option value="resuelto" <?= $ticket['estado'] === 'resuelto' ? 'selected' : '' ?>>Resuelto</option>
                        <?php if ($is_admin): ?>
                        <option value="cerrado" <?= $ticket['estado'] === 'cerrado' ? 'selected' : '' ?>>Cerrado</option>
                        <?php endif; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary btn-block btn-sm">Actualizar Estado</button>
            </form>
        </div>

        <?php if ($is_admin): ?>
        <!-- Edit -->
        <div class="side-panel mt-6">
            <a href="<?= url('editar_ticket.php?id=' . $ticket_id) ?>" class="btn btn-primary btn-block">Editar Ticket</a>
        </div>

        <!-- Reassign -->
        <div class="side-panel mt-6">
            <h3>Reasignar</h3>
            <form method="post" action="">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" name="accion" value="asignar">

                <div class="form-group">
                    <select name="asignado_id" class="form-control">
                        <option value="0">— Sin asignar —</option>
                        <?php foreach ($personal_staff as $staff): ?>
                            <option value="<?= (int) $staff['id'] ?>" <?= (int) $ticket['asignado_id'] === (int) $staff['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($staff['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary btn-block btn-sm">Reasignar</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>


