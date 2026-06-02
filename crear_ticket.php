<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/conexion.php';
requiereAutenticacion();

$pdo = obtenerConexion();

$error   = '';
$success = '';
$titulo  = '';
$descripcion = '';

$is_staff = in_array($_SESSION['rol'], ['soporte', 'admin'], true);
$staffStmt = $pdo->query("SELECT id, nombre FROM usuarios WHERE rol IN ('soporte', 'admin') ORDER BY nombre");
$personal_staff = $staffStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo       = trim($_POST['titulo'] ?? '');
    $descripcion  = trim($_POST['descripcion'] ?? '');
    $prioridad    = $_POST['prioridad'] ?? 'media';
    $asignado_id  = $is_staff ? (int) ($_POST['asignado_id'] ?? 0) : 0;
    $csrf_token   = $_POST['csrf_token'] ?? '';

    if (!validarTokenCSRF($csrf_token)) {
        $error = 'Token de seguridad inválido. Intente de nuevo.';
    } elseif ($titulo === '' || $descripcion === '') {
        $error = 'El título y la descripción son obligatorios.';
    } elseif (!in_array($prioridad, ['baja', 'media', 'alta', 'urgente'], true)) {
        $error = 'Prioridad inválida.';
    } elseif ($asignado_id > 0) {
        $check = $pdo->prepare('SELECT id FROM usuarios WHERE id = :id AND rol IN (\'soporte\', \'admin\')');
        $check->execute([':id' => $asignado_id]);
        if (!$check->fetch()) {
            $error = 'El usuario asignado no es válido.';
        }
    }

    if ($error === '') {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->query('SELECT MAX(id) AS max_id FROM tickets');
            $row  = $stmt->fetch();
            $nextId = ($row['max_id'] ?? 0) + 1;
            $folio  = 'TCK-' . str_pad((string) $nextId, 5, '0', STR_PAD_LEFT);

            $insert = $pdo->prepare('
                INSERT INTO tickets (folio, titulo, descripcion, prioridad, creador_id, asignado_id)
                VALUES (:folio, :titulo, :descripcion, :prioridad, :creador_id, :asignado_id)
            ');
            $insert->execute([
                ':folio'        => $folio,
                ':titulo'       => $titulo,
                ':descripcion'  => $descripcion,
                ':prioridad'    => $prioridad,
                ':creador_id'   => $_SESSION['usuario_id'],
                ':asignado_id'  => $asignado_id > 0 ? $asignado_id : null,
            ]);

            $pdo->commit();

            $_SESSION['success_message'] = 'Ticket creado exitosamente. Folio: ' . $folio;
            header('Location: /helpdesk/mis_tickets.php');
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log('Error al crear ticket: ' . $e->getMessage());
            $error = 'Error al crear el ticket. Intente más tarde.';
        }
    }
}

$csrf_token = generarTokenCSRF();
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="page-header">
    <h1>Nuevo Ticket</h1>
    <p>Describe tu solicitud para que nuestro equipo pueda ayudarte.</p>
</div>

<div class="card" style="max-width:680px">
    <div class="card-body">
        <?php if ($error !== ''): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" action="">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

            <div class="form-group">
                <label for="titulo">Título</label>
                <input type="text" id="titulo" name="titulo" class="form-control" placeholder="Resume tu solicitud en una línea" required value="<?= htmlspecialchars($titulo) ?>">
            </div>

            <div class="form-group">
                <label for="prioridad">Prioridad</label>
                <select id="prioridad" name="prioridad" class="form-control">
                    <option value="baja">Baja</option>
                    <option value="media" selected>Media</option>
                    <option value="alta">Alta</option>
                    <option value="urgente">Urgente</option>
                </select>
            </div>

            <div class="form-group">
                <label for="descripcion">Descripción</label>
                <textarea id="descripcion" name="descripcion" class="form-control" placeholder="Describe detalladamente el problema o solicitud..." required><?= htmlspecialchars($descripcion) ?></textarea>
            </div>

            <?php if ($is_staff): ?>
            <div class="form-group">
                <label for="asignado_id">Asignar a</label>
                <select id="asignado_id" name="asignado_id" class="form-control">
                    <option value="0">— Sin asignar —</option>
                    <?php foreach ($personal_staff as $staff): ?>
                        <option value="<?= (int) $staff['id'] ?>"><?= htmlspecialchars($staff['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="flex gap-4">
                <button type="submit" class="btn btn-primary">Crear Ticket</button>
                <a href="/helpdesk/mis_tickets.php" class="btn btn-outline">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
