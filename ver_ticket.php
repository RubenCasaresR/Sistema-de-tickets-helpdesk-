<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/conexion.php';
requiereAutenticacion();

$pdo = obtenerConexion();

$ticket_id = (int) ($_GET['id'] ?? 0);
if ($ticket_id <= 0) {
    header('Location: /helpdesk/index.php');
    exit;
}

// Obtener ticket
$stmt = $pdo->prepare('
    SELECT t.*, c.nombre AS creador_nombre, a.nombre AS asignado_nombre
    FROM tickets t
    JOIN usuarios c ON c.id = t.creador_id
    LEFT JOIN usuarios a ON a.id = t.asignado_id
    WHERE t.id = :id
');
$stmt->execute([':id' => $ticket_id]);
$ticket = $stmt->fetch();

if (!$ticket) {
    header('Location: /helpdesk/index.php');
    exit;
}

// Cliente solo ve sus propios tickets
if ($_SESSION['rol'] === 'cliente' && (int) $ticket['creador_id'] !== $_SESSION['usuario_id']) {
    header('Location: /helpdesk/mis_tickets.php');
    exit;
}

$is_staff = in_array($_SESSION['rol'], ['soporte', 'admin'], true);
$is_admin = $_SESSION['rol'] === 'admin';

// ---- Procesar formularios ----
$error = '';

// Cambiar estado
if ($is_staff && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';

    if (!validarTokenCSRF($csrf_token)) {
        $error = 'Token de seguridad inválido.';
    } elseif ($_POST['accion'] === 'cambiar_estado') {
        $nuevo_estado = $_POST['estado'] ?? '';
        if (in_array($nuevo_estado, ['abierto', 'en_progreso', 'resuelto', 'cerrado'], true)) {
            $fecha_cierre = $nuevo_estado === 'cerrado' ? date('Y-m-d H:i:s') : null;
            $upd = $pdo->prepare('UPDATE tickets SET estado = :estado, fecha_cierre = :fecha_cierre WHERE id = :id');
            $upd->execute([':estado' => $nuevo_estado, ':fecha_cierre' => $fecha_cierre, ':id' => $ticket_id]);
            $_SESSION['success_message'] = 'Estado actualizado correctamente.';
            header('Location: /helpdesk/ver_ticket.php?id=' . $ticket_id);
            exit;
        }
        $error = 'Estado inválido.';
    } elseif ($is_admin && $_POST['accion'] === 'asignar') {
        $asignado_id = (int) ($_POST['asignado_id'] ?? 0);
        if ($asignado_id > 0) {
            $check = $pdo->prepare('SELECT id FROM usuarios WHERE id = :id AND rol IN (\'soporte\', \'admin\')');
            $check->execute([':id' => $asignado_id]);
            if ($check->fetch()) {
                $upd = $pdo->prepare('UPDATE tickets SET asignado_id = :asignado_id WHERE id = :id');
                $upd->execute([':asignado_id' => $asignado_id, ':id' => $ticket_id]);
                $_SESSION['success_message'] = 'Ticket reasignado correctamente.';
                header('Location: /helpdesk/ver_ticket.php?id=' . $ticket_id);
                exit;
            }
        } elseif ($asignado_id === 0) {
            $upd = $pdo->prepare('UPDATE tickets SET asignado_id = NULL WHERE id = :id');
            $upd->execute([':id' => $ticket_id]);
            $_SESSION['success_message'] = 'Asignación eliminada.';
            header('Location: /helpdesk/ver_ticket.php?id=' . $ticket_id);
            exit;
        }
        $error = 'Usuario de soporte inválido.';
    }
}

// Añadir comentario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'comentar') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    $mensaje    = trim($_POST['mensaje'] ?? '');

    if (!validarTokenCSRF($csrf_token)) {
        $error = 'Token de seguridad inválido.';
    } elseif ($mensaje === '') {
        $error = 'El mensaje no puede estar vacío.';
    } else {
        $ins = $pdo->prepare('INSERT INTO comentarios_ticket (ticket_id, usuario_id, mensaje) VALUES (:ticket_id, :usuario_id, :mensaje)');
        $ins->execute([
            ':ticket_id'  => $ticket_id,
            ':usuario_id' => $_SESSION['usuario_id'],
            ':mensaje'    => $mensaje,
        ]);

        // Si está abierto y el staff comenta, pasar a en_progreso automáticamente
        if ($is_staff && $ticket['estado'] === 'abierto') {
            $pdo->prepare('UPDATE tickets SET estado = \'en_progreso\' WHERE id = :id')->execute([':id' => $ticket_id]);
        }

        header('Location: /helpdesk/ver_ticket.php?id=' . $ticket_id);
        exit;
    }
}

// Recargar ticket después de modificaciones
$stmt->execute([':id' => $ticket_id]);
$ticket = $stmt->fetch();

// Obtener comentarios
$comStmt = $pdo->prepare('
    SELECT c.*, u.nombre, u.rol
    FROM comentarios_ticket c
    JOIN usuarios u ON u.id = c.usuario_id
    WHERE c.ticket_id = :ticket_id
    ORDER BY c.fecha ASC
');
$comStmt->execute([':ticket_id' => $ticket_id]);
$comentarios = $comStmt->fetchAll();

// Personal de soporte para asignación
$staffStmt = $pdo->query("SELECT id, nombre FROM usuarios WHERE rol IN ('soporte', 'admin') ORDER BY nombre");
$personal_staff = $staffStmt->fetchAll();

$csrf_token = generarTokenCSRF();

// Success message
$success = '';
if (isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<?php if ($success !== ''): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="page-header">
    <a href="<?= $is_staff ? '/helpdesk/panel_admin.php' : '/helpdesk/mis_tickets.php' ?>" class="btn btn-outline btn-sm mb-4">&larr; Volver</a>
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
                </div>
            </div>
            <div class="card-body">
                <div class="mb-4">
                    <h3><?= htmlspecialchars($ticket['titulo']) ?></h3>
                    <p class="text-muted text-small">Creado por <strong><?= htmlspecialchars($ticket['creador_nombre']) ?></strong> el <?= htmlspecialchars(date('d/m/Y H:i', strtotime($ticket['fecha_creacion']))) ?></p>
                </div>
                <div style="white-space:pre-wrap;font-size:0.9rem;color:var(--color-text-primary);line-height:1.7">
                    <?= htmlspecialchars($ticket['descripcion']) ?>
                </div>
            </div>
        </div>

        <!-- Comments -->
        <div class="card">
            <div class="card-header">
                <h3>Bitácora de Avances (<?= count($comentarios) ?>)</h3>
            </div>
            <div class="card-body">
                <?php if (count($comentarios) === 0): ?>
                    <p class="text-muted">No hay avances registrados aún.</p>
                <?php else: ?>
                    <div class="comment-thread">
                        <?php foreach ($comentarios as $com): ?>
                            <div class="comment<?= (int) $com['usuario_id'] === (int) $ticket['asignado_id'] ? ' assigned' : '' ?>">
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
                                    <div class="comment-text"><?= nl2br(htmlspecialchars($com['mensaje'])) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Comment Form -->
                <div class="comment-form">
                    <form method="post" action="">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        <input type="hidden" name="accion" value="comentar">

                        <div class="form-group">
                            <label for="mensaje">Registrar avance o responder</label>
                            <textarea id="mensaje" name="mensaje" class="form-control" placeholder="Describe los avances realizados hoy..." required style="min-height:80px"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Registrar Avance</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Right: Staff Panel -->
    <?php if ($is_staff): ?>
    <div>
        <div class="side-panel">
            <h3>Información</h3>
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
                        <option value="cerrado" <?= $ticket['estado'] === 'cerrado' ? 'selected' : '' ?>>Cerrado</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary btn-block btn-sm">Actualizar Estado</button>
            </form>
        </div>

        <?php if ($is_admin): ?>
        <!-- Edit -->
        <div class="side-panel mt-6">
            <a href="/helpdesk/editar_ticket.php?id=<?= $ticket_id ?>" class="btn btn-primary btn-block">Editar Ticket</a>
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
