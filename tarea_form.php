<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/tareas_helper.php';
requiereAutenticacion();

$is_staff = in_array($_SESSION['rol'], ['soporte', 'admin'], true);
if (!$is_staff) {
    redirect('index.php');
    exit;
}

$pdo = obtenerConexion();
$error = '';
$success = '';
$editar = false;
$tarea = [
    'id' => 0,
    'titulo' => '',
    'descripcion' => '',
    'prioridad' => 'media',
    'estado' => 'pendiente',
    'asignado_id' => '',
    'ticket_id' => '',
    'fecha_limite' => '',
];
$etiquetasSeleccionadas = [];

$id = (int) ($_GET['id'] ?? 0);
if ($id > 0) {
    $tareaDB = obtenerTarea($pdo, $id);
    if (!$tareaDB) {
        redirect('tareas.php');
        exit;
    }
    $tarea = $tareaDB;
    $editar = true;
    $etiquetasSel = obtenerEtiquetasTarea($pdo, $id);
    $etiquetasSeleccionadas = array_map(function ($e) { return (int) $e['id']; }, $etiquetasSel);
}

$personal = obtenerPersonalStaff($pdo);
$todasEtiquetas = obtenerTodasEtiquetas($pdo);

// Buscar tickets para el select (opcional)
$ticketsStmt = $pdo->query("SELECT id, folio, titulo FROM tickets WHERE estado NOT IN ('cerrado','resuelto') ORDER BY fecha_creacion DESC LIMIT 50");
$tickets = $ticketsStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $prioridad = $_POST['prioridad'] ?? 'media';
    $estado = $_POST['estado'] ?? 'pendiente';
    $asignado_id = (int) ($_POST['asignado_id'] ?? 0);
    $ticket_id = (int) ($_POST['ticket_id'] ?? 0);
    $fecha_limite = trim($_POST['fecha_limite'] ?? '');
    $etiquetas_post = $_POST['etiquetas'] ?? [];

    if (!validarTokenCSRF($csrf_token)) {
        $error = 'Token de seguridad invalido.';
    } elseif ($titulo === '') {
        $error = 'El titulo es obligatorio.';
    } elseif (!in_array($prioridad, ['baja', 'media', 'alta', 'urgente'])) {
        $error = 'Prioridad invalida.';
    } elseif (!in_array($estado, ['pendiente', 'en_progreso', 'en_revision', 'completada', 'cancelada'])) {
        $error = 'Estado invalido.';
    } else {
        try {
            $pdo->beginTransaction();

            $descripcion = sanitizarDescripcion($descripcion);

            if ($editar) {
                $stmt = $pdo->prepare('
                    UPDATE tareas SET titulo = :titulo, descripcion = :descripcion, prioridad = :prioridad,
                        estado = :estado, asignado_id = :asignado_id, ticket_id = :ticket_id, fecha_limite = :fecha_limite
                    WHERE id = :id
                ');
                $stmt->execute([
                    ':titulo' => $titulo,
                    ':descripcion' => $descripcion,
                    ':prioridad' => $prioridad,
                    ':estado' => $estado,
                    ':asignado_id' => $asignado_id ?: null,
                    ':ticket_id' => $ticket_id ?: null,
                    ':fecha_limite' => $fecha_limite ?: null,
                    ':id' => $id,
                ]);
                $tarea_id = $id;

                registrarHistorial($pdo, $tarea_id, $_SESSION['usuario_id'], 'actualizada', 'Tarea actualizada');

                if ($estado === 'completada' && empty($tarea['fecha_completada'])) {
                    $pdo->prepare('UPDATE tareas SET fecha_completada = NOW() WHERE id = :id AND estado = :estado')
                        ->execute([':id' => $tarea_id, ':estado' => 'completada']);
                    registrarHistorial($pdo, $tarea_id, $_SESSION['usuario_id'], 'completada', 'Tarea marcada como completada');
                }
            } else {
                $stmt = $pdo->prepare('
                    INSERT INTO tareas (titulo, descripcion, prioridad, estado, creador_id, asignado_id, ticket_id, fecha_limite)
                    VALUES (:titulo, :descripcion, :prioridad, :estado, :creador_id, :asignado_id, :ticket_id, :fecha_limite)
                ');
                $stmt->execute([
                    ':titulo' => $titulo,
                    ':descripcion' => $descripcion,
                    ':prioridad' => $prioridad,
                    ':estado' => $estado,
                    ':creador_id' => $_SESSION['usuario_id'],
                    ':asignado_id' => $asignado_id ?: null,
                    ':ticket_id' => $ticket_id ?: null,
                    ':fecha_limite' => $fecha_limite ?: null,
                ]);
                $tarea_id = (int) $pdo->lastInsertId();

                registrarHistorial($pdo, $tarea_id, $_SESSION['usuario_id'], 'creada', "Tarea \"{$titulo}\" creada");
            }

            // Actualizar etiquetas
            $pdo->prepare('DELETE FROM tarea_etiquetas WHERE tarea_id = :tarea_id')->execute([':tarea_id' => $tarea_id]);
            $insTag = $pdo->prepare('INSERT INTO tarea_etiquetas (tarea_id, etiqueta_id) VALUES (:tarea_id, :etiqueta_id)');
            foreach ($etiquetas_post as $etid) {
                $etid = (int) $etid;
                if ($etid > 0) {
                    $insTag->execute([':tarea_id' => $tarea_id, ':etiqueta_id' => $etid]);
                }
            }

            $pdo->commit();

            // Sincronizar estado del ticket vinculado
            if ($ticket_id > 0) {
                sincronizarEstadoTicketDesdeTarea($pdo, $ticket_id, $_SESSION['usuario_id'], $estado);
            }

            $_SESSION['success_message'] = $editar ? 'Tarea actualizada correctamente.' : 'Tarea creada correctamente.';
            redirect('tarea_ver.php?id=' . $tarea_id);
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log('Error tarea_form: ' . $e->getMessage());
            $error = 'Error al guardar la tarea. Intente de nuevo.';
        }
    }
}

$csrf_token = generarTokenCSRF();
$page_title = isset($tarea) ? 'Editar Tarea' : 'Nueva Tarea';
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="page-header">
    <a href="<?= url('tareas.php') ?>" class="btn btn-outline btn-sm mb-4">&larr; Volver a Tareas</a>
    <h1><?= $editar ? 'Editar Tarea' : 'Nueva Tarea' ?></h1>
</div>

<?php if ($error !== ''): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card" style="max-width:800px">
    <div class="card-header">
        <h3><?= $editar ? 'Editar Tarea' : 'Crear Tarea' ?></h3>
    </div>
    <div class="card-body">
        <form method="post" action="">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

            <div class="form-group">
                <label for="titulo">Titulo *</label>
                <input type="text" id="titulo" name="titulo" class="form-control" required
                       value="<?= htmlspecialchars($tarea['titulo']) ?>"
                       placeholder="Ej: Actualizar servidor de base de datos">
            </div>

            <div class="form-group">
                <label>Descripcion</label>
                <div class="quill-wrapper">
                    <div class="quill-editor" data-target="descripcion"
                         data-placeholder="Describe la tarea en detalle..."
                         data-content="<?= htmlspecialchars($tarea['descripcion'] ?? '') ?>"></div>
                    <input type="hidden" name="descripcion" value="">
                </div>
            </div>

            <div class="flex gap-4" style="flex-wrap:wrap">
                <div class="form-group" style="flex:1;min-width:180px">
                    <label for="prioridad">Prioridad</label>
                    <select id="prioridad" name="prioridad" class="form-control">
                        <option value="baja" <?= $tarea['prioridad'] === 'baja' ? 'selected' : '' ?>>Baja</option>
                        <option value="media" <?= $tarea['prioridad'] === 'media' ? 'selected' : '' ?>>Media</option>
                        <option value="alta" <?= $tarea['prioridad'] === 'alta' ? 'selected' : '' ?>>Alta</option>
                        <option value="urgente" <?= $tarea['prioridad'] === 'urgente' ? 'selected' : '' ?>>Urgente</option>
                    </select>
                </div>

                <div class="form-group" style="flex:1;min-width:180px">
                    <label for="estado">Estado</label>
                    <select id="estado" name="estado" class="form-control">
                        <option value="pendiente" <?= $tarea['estado'] === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                        <option value="en_progreso" <?= $tarea['estado'] === 'en_progreso' ? 'selected' : '' ?>>En Progreso</option>
                        <option value="en_revision" <?= $tarea['estado'] === 'en_revision' ? 'selected' : '' ?>>En Revision</option>
                        <option value="completada" <?= $tarea['estado'] === 'completada' ? 'selected' : '' ?>>Completada</option>
                        <option value="cancelada" <?= $tarea['estado'] === 'cancelada' ? 'selected' : '' ?>>Cancelada</option>
                    </select>
                </div>
            </div>

            <div class="flex gap-4" style="flex-wrap:wrap">
                <div class="form-group" style="flex:1;min-width:180px">
                    <label for="asignado_id">Asignado a</label>
                    <select id="asignado_id" name="asignado_id" class="form-control">
                        <option value="0">— Sin asignar —</option>
                        <?php foreach ($personal as $p): ?>
                            <option value="<?= (int) $p['id'] ?>" <?= (int) $tarea['asignado_id'] === (int) $p['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="flex:1;min-width:180px">
                    <label for="ticket_id">Ticket vinculado (opcional)</label>
                    <select id="ticket_id" name="ticket_id" class="form-control">
                        <option value="0">— Ninguno —</option>
                        <?php foreach ($tickets as $tk): ?>
                            <option value="<?= (int) $tk['id'] ?>" <?= (int) ($tarea['ticket_id'] ?? 0) === (int) $tk['id'] ? 'selected' : '' ?>>
                                [<?= htmlspecialchars($tk['folio']) ?>] <?= htmlspecialchars(mb_substr($tk['titulo'], 0, 60)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="flex gap-4" style="flex-wrap:wrap">
                <div class="form-group" style="flex:1;min-width:180px">
                    <label for="fecha_limite">Fecha limite</label>
                    <input type="date" id="fecha_limite" name="fecha_limite" class="form-control"
                           value="<?= htmlspecialchars($tarea['fecha_limite'] ?? '') ?>">
                </div>

                <div class="form-group" style="flex:1;min-width:180px">
                    <label>Etiquetas</label>
                    <div class="task-tags-grid">
                        <?php foreach ($todasEtiquetas as $et): ?>
                            <label class="task-tag-option" style="--tag-color:<?= htmlspecialchars($et['color']) ?>">
                                <input type="checkbox" name="etiquetas[]" value="<?= (int) $et['id'] ?>"
                                    <?= in_array((int) $et['id'], $etiquetasSeleccionadas) ? 'checked' : '' ?>>
                                <span class="tag-dot" style="background:<?= htmlspecialchars($et['color']) ?>"></span>
                                <?= htmlspecialchars($et['nombre']) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="flex gap-4" style="margin-top:24px">
                <button type="submit" class="btn btn-primary">
                    <?= $editar ? 'Guardar Cambios' : 'Crear Tarea' ?>
                </button>
                <a href="<?= url('tareas.php') ?>" class="btn btn-outline">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

