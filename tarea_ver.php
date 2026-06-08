<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/tareas_helper.php';
requiereAutenticacion();

$is_staff = in_array($_SESSION['rol'], ['soporte', 'admin'], true);
if (!$is_staff) {
    header('Location: /helpdesk/index.php');
    exit;
}

$pdo = obtenerConexion();
$error = '';
$success = '';

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: /helpdesk/tareas.php');
    exit;
}

$tarea = obtenerTarea($pdo, $id);
if (!$tarea) {
    header('Location: /helpdesk/tareas.php');
    exit;
}

// Flash messages
if (isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $error = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// POST: agregar comentario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'comentar') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    $mensaje = trim($_POST['mensaje'] ?? '');

    if (!validarTokenCSRF($csrf_token)) {
        $error = 'Token de seguridad invalido.';
    } elseif ($mensaje === '') {
        $error = 'El mensaje no puede estar vacio.';
    } else {
        $mensaje = sanitizarDescripcion($mensaje);
        $ins = $pdo->prepare('INSERT INTO tarea_comentarios (tarea_id, usuario_id, mensaje) VALUES (:tarea_id, :usuario_id, :mensaje)');
        $ins->execute([':tarea_id' => $id, ':usuario_id' => $_SESSION['usuario_id'], ':mensaje' => $mensaje]);
        registrarHistorial($pdo, $id, $_SESSION['usuario_id'], 'comentario', 'Agrego un comentario');
        $_SESSION['success_message'] = 'Comentario agregado.';
        header('Location: /helpdesk/tarea_ver.php?id=' . $id);
        exit;
    }
}

// POST: agregar subtarea
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'agregar_subtarea') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    $texto = trim($_POST['texto'] ?? '');

    if (validarTokenCSRF($csrf_token) && $texto !== '') {
        $maxOrd = $pdo->prepare('SELECT COALESCE(MAX(orden), -1) + 1 AS sig FROM tarea_subtareas WHERE tarea_id = :tarea_id');
        $maxOrd->execute([':tarea_id' => $id]);
        $orden = (int) $maxOrd->fetch()['sig'];

        $ins = $pdo->prepare('INSERT INTO tarea_subtareas (tarea_id, texto, orden) VALUES (:tarea_id, :texto, :orden)');
        $ins->execute([':tarea_id' => $id, ':texto' => $texto, ':orden' => $orden]);
    }
    header('Location: /helpdesk/tarea_ver.php?id=' . $id);
    exit;
}

// POST: cambiar estado desde botones rapidos
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'cambiar_estado') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    $nuevo_estado = $_POST['estado'] ?? '';

    if (!validarTokenCSRF($csrf_token)) {
        $_SESSION['error_message'] = 'Token de seguridad invalido. Intenta recargar la pagina.';
    } elseif (!in_array($nuevo_estado, ['pendiente', 'en_progreso', 'en_revision', 'completada', 'cancelada'])) {
        $_SESSION['error_message'] = 'Estado no valido.';
    } else {
        $pdo->prepare('UPDATE tareas SET estado = :estado' . ($nuevo_estado === 'completada' ? ', fecha_completada = NOW()' : '') . ' WHERE id = :id')
            ->execute([':estado' => $nuevo_estado, ':id' => $id]);
        registrarHistorial($pdo, $id, $_SESSION['usuario_id'], 'estado', "Cambio estado a: {$nuevo_estado}");

        // Sincronizar estado del ticket vinculado
        if (!empty($tarea['ticket_id'])) {
            sincronizarEstadoTicketDesdeTarea($pdo, (int) $tarea['ticket_id'], $_SESSION['usuario_id'], $nuevo_estado);
        }

        $_SESSION['success_message'] = 'Estado actualizado.';
    }
    header('Location: /helpdesk/tarea_ver.php?id=' . $id);
    exit;
}

$csrf_token = generarTokenCSRF();

// Load data
$subtareas = obtenerSubtareas($pdo, $id);
$comentarios = obtenerComentariosTarea($pdo, $id);
$etiquetas = obtenerEtiquetasTarea($pdo, $id);
$tiempos = obtenerTiemposTarea($pdo, $id);
$historial = obtenerHistorialTarea($pdo, $id);
$tiempoTotal = calcularTiempoTotalTarea($pdo, $id);

// Verificar timer activo del usuario actual
$timerActivoStmt = $pdo->prepare("SELECT id, fecha_inicio FROM tarea_tiempo WHERE tarea_id = :tarea_id AND usuario_id = :usuario_id AND fecha_fin IS NULL");
$timerActivoStmt->execute([':tarea_id' => $id, ':usuario_id' => $_SESSION['usuario_id']]);
$timerActivo = $timerActivoStmt->fetch();

// Subtarea completadas
$totalSubtareas = count($subtareas);
$completadasSubtareas = count(array_filter($subtareas, function ($s) { return (int) $s['completado'] === 1; }));
$progresoSubtareas = $totalSubtareas > 0 ? round(($completadasSubtareas / $totalSubtareas) * 100) : 0;
$page_title = ($tarea['titulo'] ?? 'Tarea');
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="page-header">
    <a href="/helpdesk/tareas.php" class="btn btn-outline btn-sm mb-4">&larr; Volver a Tareas</a>
    <h1><?= htmlspecialchars($tarea['titulo']) ?></h1>
</div>

<?php if ($success !== ''): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="split-layout">
    <!-- Left column: main content -->
    <div>
        <!-- Description -->
        <div class="card mb-6">
            <div class="card-header">
                <h3>Descripcion</h3>
                <div class="flex gap-2">
                    <?= renderEstadoBadge($tarea['estado']) ?>
                    <span class="badge badge-<?= htmlspecialchars($tarea['prioridad']) ?>">
                        <?= htmlspecialchars(ucfirst($tarea['prioridad'])) ?>
                    </span>
                    <?php foreach ($etiquetas as $et): ?>
                        <span class="task-tag" style="background:<?= htmlspecialchars($et['color']) ?>20;color:<?= htmlspecialchars($et['color']) ?>;border-color:<?= htmlspecialchars($et['color']) ?>40">
                            <?= htmlspecialchars($et['nombre']) ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="card-body">
                <?php if (!empty($tarea['descripcion'])): ?>
                    <div style="font-size:0.9rem;line-height:1.7"><?= $tarea['descripcion'] ?></div>
                <?php else: ?>
                    <p class="text-muted">Sin descripcion.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Subtareas -->
        <div class="card mb-6">
            <div class="card-header">
                <h3>Checklist</h3>
                <span class="text-muted text-small"><?= $completadasSubtareas ?>/<?= $totalSubtareas ?></span>
            </div>
            <div class="card-body">
                <?php if ($totalSubtareas > 0): ?>
                    <div class="subtask-progress-bar mb-4">
                        <div class="subtask-progress-fill" style="width:<?= $progresoSubtareas ?>%"></div>
                    </div>
                <?php endif; ?>

                <div class="subtask-list" id="subtaskList" data-tarea-id="<?= $id ?>">
                    <?php foreach ($subtareas as $sub): ?>
                        <div class="subtask-item" data-id="<?= (int) $sub['id'] ?>">
                            <label class="subtask-label">
                                <input type="checkbox" class="subtask-checkbox" <?= (int) $sub['completado'] ? 'checked' : '' ?>>
                                <span class="<?= (int) $sub['completado'] ? 'completed' : '' ?>"><?= htmlspecialchars($sub['texto']) ?></span>
                            </label>
                            <button type="button" class="subtask-delete" title="Eliminar">&times;</button>
                        </div>
                    <?php endforeach; ?>
                </div>

                <form method="post" action="" class="subtask-add-form mt-4">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <input type="hidden" name="accion" value="agregar_subtarea">
                    <div class="flex gap-2">
                        <input type="text" name="texto" class="form-control" placeholder="Agregar item..." required maxlength="255">
                        <button type="submit" class="btn btn-primary btn-sm">Agregar</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Comments -->
        <div class="card mb-6">
            <div class="card-header">
                <h3>Comentarios (<?= count($comentarios) ?>)</h3>
            </div>
            <div class="card-body">
                <?php if (count($comentarios) === 0): ?>
                    <p class="text-muted">Sin comentarios aun.</p>
                <?php else: ?>
                    <div class="comment-thread">
                        <?php foreach ($comentarios as $com): ?>
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
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="" class="mt-4">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <input type="hidden" name="accion" value="comentar">
                    <div class="form-group mb-2">
                        <textarea name="mensaje" class="form-control" rows="3" placeholder="Escribe un comentario..." required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Comentar</button>
                </form>
            </div>
        </div>

        <!-- Time tracking -->
        <div class="card mb-6">
            <div class="card-header">
                <h3>Registro de Tiempo</h3>
                <span class="text-muted text-small">Total: <?= formatoTiempo($tiempoTotal) ?></span>
            </div>
            <div class="card-body">
                <div class="time-tracker-actions mb-4">
                    <?php if ($timerActivo): ?>
                        <?php $inicio = strtotime($timerActivo['fecha_inicio']); $transcurrido = time() - $inicio; ?>
                        <form method="post" action="/helpdesk/ajax_tarea_tiempo.php" class="timer-form" data-tarea-id="<?= $id ?>">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            <input type="hidden" name="accion" value="detener">
                            <input type="hidden" name="tarea_id" value="<?= $id ?>">
                            <div class="timer-active">
                                <span class="timer-display" data-start="<?= $inicio ?>"><?php
                                    $h = floor($transcurrido / 3600);
                                    $m = floor(($transcurrido % 3600) / 60);
                                    $s = $transcurrido % 60;
                                    echo ($h > 0 ? $h . ':' : '') . str_pad($m, 2, '0', STR_PAD_LEFT) . ':' . str_pad($s, 2, '0', STR_PAD_LEFT);
                                ?></span>
                                <button type="submit" class="btn btn-danger btn-sm">Detener</button>
                            </div>
                        </form>
                    <?php else: ?>
                        <form method="post" action="/helpdesk/ajax_tarea_tiempo.php" class="timer-form" data-tarea-id="<?= $id ?>">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            <input type="hidden" name="accion" value="iniciar">
                            <input type="hidden" name="tarea_id" value="<?= $id ?>">
                            <button type="submit" class="btn btn-primary btn-sm">Iniciar Timer</button>
                        </form>
                    <?php endif; ?>
                </div>

                <?php if (count($tiempos) > 0): ?>
                    <div class="time-entries">
                        <?php foreach ($tiempos as $t): ?>
                            <div class="time-entry">
                                <div class="time-entry-meta">
                                    <strong><?= htmlspecialchars($t['nombre']) ?></strong>
                                    <span class="text-muted text-small"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($t['fecha_inicio']))) ?></span>
                                </div>
                                <div class="time-entry-duration">
                                    <?php if ($t['fecha_fin']): ?>
                                        <?= formatoTiempo(strtotime($t['fecha_fin']) - strtotime($t['fecha_inicio'])) ?>
                                    <?php else: ?>
                                        <em class="text-muted">En curso...</em>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($t['descripcion'])): ?>
                                    <div class="text-muted text-small"><?= htmlspecialchars($t['descripcion']) ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- History -->
        <div class="card mb-6">
            <div class="card-header">
                <h3>Historial de Actividad</h3>
            </div>
            <div class="card-body">
                <?php if (count($historial) === 0): ?>
                    <p class="text-muted">Sin actividad registrada.</p>
                <?php else: ?>
                    <div class="history-list">
                        <?php foreach ($historial as $h): ?>
                            <div class="history-item">
                                <span class="history-dot"></span>
                                <div class="history-content">
                                    <strong><?= htmlspecialchars($h['nombre']) ?></strong>
                                    <span><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $h['accion']))) ?></span>
                                    <?php if (!empty($h['detalle'])): ?>
                                        <span class="text-muted">— <?= htmlspecialchars($h['detalle']) ?></span>
                                    <?php endif; ?>
                                    <span class="history-date"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($h['fecha_creacion']))) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right sidebar -->
    <div>
        <div class="side-panel">
            <h3>Acciones</h3>
            <form method="post" action="">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" name="accion" value="cambiar_estado">
                <div class="form-group">
                    <select name="estado" class="form-control">
                        <option value="pendiente" <?= $tarea['estado'] === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                        <option value="en_progreso" <?= $tarea['estado'] === 'en_progreso' ? 'selected' : '' ?>>En Progreso</option>
                        <option value="en_revision" <?= $tarea['estado'] === 'en_revision' ? 'selected' : '' ?>>En Revision</option>
                        <option value="completada" <?= $tarea['estado'] === 'completada' ? 'selected' : '' ?>>Completada</option>
                        <option value="cancelada" <?= $tarea['estado'] === 'cancelada' ? 'selected' : '' ?>>Cancelada</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary btn-block btn-sm">Cambiar Estado</button>
            </form>
            <div class="side-panel-actions">
                <a href="/helpdesk/tarea_form.php?id=<?= $id ?>" class="btn btn-outline btn-block btn-sm">Editar Tarea</a>
                <form method="post" action="/helpdesk/tareas.php" onsubmit="return confirm('¿Eliminar esta tarea?')">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <input type="hidden" name="accion" value="eliminar_tarea">
                    <input type="hidden" name="tarea_id" value="<?= $id ?>">
                    <button type="submit" class="btn btn-danger btn-block btn-sm mt-2">Eliminar</button>
                </form>
            </div>
        </div>

        <div class="side-panel mt-6">
            <h3>Informacion</h3>
            <div class="info-row">
                <span class="label">Creador</span>
                <span class="value"><?= htmlspecialchars($tarea['creador_nombre']) ?></span>
            </div>
            <div class="info-row">
                <span class="label">Asignado</span>
                <span class="value"><?= htmlspecialchars($tarea['asignado_nombre'] ?? 'Sin asignar') ?></span>
            </div>
            <div class="info-row">
                <span class="label">Creado</span>
                <span class="value text-small"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($tarea['fecha_creacion']))) ?></span>
            </div>
            <?php if ($tarea['fecha_limite']): ?>
            <div class="info-row">
                <span class="label">Fecha limite</span>
                <span class="value text-small <?= strtotime($tarea['fecha_limite']) < time() && $tarea['estado'] !== 'completada' ? 'text-danger' : '' ?>">
                    <?= htmlspecialchars(date('d/m/Y', strtotime($tarea['fecha_limite']))) ?>
                </span>
            </div>
            <?php endif; ?>
            <?php if ($tarea['fecha_completada']): ?>
            <div class="info-row">
                <span class="label">Completada</span>
                <span class="value text-small"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($tarea['fecha_completada']))) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($tarea['ticket_folio']): ?>
            <div class="info-row">
                <span class="label">Ticket</span>
                <span class="value">
                    <a href="/helpdesk/ver_ticket.php?id=<?= (int) $tarea['ticket_id'] ?>" title="<?= htmlspecialchars($tarea['ticket_folio']) ?>"><?= htmlspecialchars(mb_substr($tarea['ticket_titulo'] ?? $tarea['ticket_folio'], 0, 60)) ?></a>
                </span>
            </div>
            <?php endif; ?>
        </div>

        <!-- Progress card -->
        <div class="side-panel mt-6">
            <h3>Progreso</h3>
            <div class="info-row">
                <span class="label">Checklist</span>
                <span class="value"><?= $completadasSubtareas ?>/<?= $totalSubtareas ?></span>
            </div>
            <div class="subtask-progress-bar mt-2" style="height:6px">
                <div class="subtask-progress-fill" style="width:<?= $progresoSubtareas ?>%"></div>
            </div>
            <div class="info-row mt-4">
                <span class="label">Tiempo total</span>
                <span class="value"><?= formatoTiempo($tiempoTotal) ?></span>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

