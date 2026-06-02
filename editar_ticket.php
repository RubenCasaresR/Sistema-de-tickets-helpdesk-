<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/conexion.php';
requiereRol(['admin']);

$pdo = obtenerConexion();

$ticket_id = (int) ($_GET['id'] ?? 0);
if ($ticket_id <= 0) {
    header('Location: /helpdesk/panel_admin.php');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM tickets WHERE id = :id');
$stmt->execute([':id' => $ticket_id]);
$ticket = $stmt->fetch();

if (!$ticket) {
    header('Location: /helpdesk/panel_admin.php');
    exit;
}

$error   = '';
$titulo  = $ticket['titulo'];
$descripcion = $ticket['descripcion'];
$prioridad   = $ticket['prioridad'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo       = trim($_POST['titulo'] ?? '');
    $descripcion  = trim($_POST['descripcion'] ?? '');
    $prioridad    = $_POST['prioridad'] ?? 'media';
    $csrf_token   = $_POST['csrf_token'] ?? '';

    if (!validarTokenCSRF($csrf_token)) {
        $error = 'Token de seguridad inválido. Intente de nuevo.';
    } elseif ($titulo === '' || $descripcion === '') {
        $error = 'El título y la descripción son obligatorios.';
    } elseif (!in_array($prioridad, ['baja', 'media', 'alta', 'urgente'], true)) {
        $error = 'Prioridad inválida.';
    } else {
        try {
            $upd = $pdo->prepare('
                UPDATE tickets
                SET titulo = :titulo, descripcion = :descripcion, prioridad = :prioridad
                WHERE id = :id
            ');
            $upd->execute([
                ':titulo'      => $titulo,
                ':descripcion' => $descripcion,
                ':prioridad'   => $prioridad,
                ':id'          => $ticket_id,
            ]);

            $_SESSION['success_message'] = 'Ticket actualizado correctamente.';
            header('Location: /helpdesk/ver_ticket.php?id=' . $ticket_id);
            exit;
        } catch (PDOException $e) {
            error_log('Error al editar ticket: ' . $e->getMessage());
            $error = 'Error al actualizar el ticket. Intente más tarde.';
        }
    }
}

$csrf_token = generarTokenCSRF();
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="page-header">
    <h1>Editar Ticket <?= htmlspecialchars($ticket['folio']) ?></h1>
    <p>Modifica los datos del ticket.</p>
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
                    <option value="baja" <?= $prioridad === 'baja' ? 'selected' : '' ?>>Baja</option>
                    <option value="media" <?= $prioridad === 'media' ? 'selected' : '' ?>>Media</option>
                    <option value="alta" <?= $prioridad === 'alta' ? 'selected' : '' ?>>Alta</option>
                    <option value="urgente" <?= $prioridad === 'urgente' ? 'selected' : '' ?>>Urgente</option>
                </select>
            </div>

            <div class="form-group">
                <label for="descripcion">Descripción</label>
                <textarea id="descripcion" name="descripcion" class="form-control" placeholder="Describe detalladamente el problema o solicitud..." required><?= htmlspecialchars($descripcion) ?></textarea>
            </div>

            <div class="flex gap-4">
                <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                <a href="/helpdesk/ver_ticket.php?id=<?= $ticket_id ?>" class="btn btn-outline">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
