<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/conexion.php';
requiereRol(['admin']);

$pdo = obtenerConexion();

$ticket_id = (int) ($_GET['id'] ?? 0);
if ($ticket_id <= 0) {
    redirect('panel_admin.php');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM tickets WHERE id = :id');
$stmt->execute([':id' => $ticket_id]);
$ticket = $stmt->fetch();

if (!$ticket) {
    redirect('panel_admin.php');
    exit;
}

$error   = '';
$titulo  = $ticket['titulo'];
$descripcion = $ticket['descripcion'];
$prioridad   = $ticket['prioridad'];
$categoria_id = (int) ($ticket['categoria_id'] ?? 0);

$catStmt = $pdo->query("SELECT id, nombre FROM categorias WHERE activo = 1 ORDER BY nombre");
$categorias = $catStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo       = trim($_POST['titulo'] ?? '');
    $descripcion  = trim($_POST['descripcion'] ?? '');
    $prioridad    = $_POST['prioridad'] ?? 'media';
    $categoria_id = (int) ($_POST['categoria_id'] ?? 0);
    $csrf_token   = $_POST['csrf_token'] ?? '';

    if (!validarTokenCSRF($csrf_token)) {
        $error = 'Token de seguridad invalido. Intente de nuevo.';
    } elseif ($titulo === '' || trim(strip_tags($descripcion)) === '') {
        $error = 'El titulo y la descripcion son obligatorios.';
    } elseif (!in_array($prioridad, ['baja', 'media', 'alta', 'urgente'], true)) {
        $error = 'Prioridad invalida.';
    } else {
        $descripcion = sanitizarDescripcion($descripcion, '<h1><h2><h3>');
        try {
            $upd = $pdo->prepare('
                UPDATE tickets
                SET titulo = :titulo, descripcion = :descripcion, prioridad = :prioridad, categoria_id = :categoria_id
                WHERE id = :id
            ');
            $upd->execute([
                ':titulo'        => $titulo,
                ':descripcion'   => $descripcion,
                ':prioridad'     => $prioridad,
                ':categoria_id'  => $categoria_id > 0 ? $categoria_id : null,
                ':id'            => $ticket_id,
            ]);

            $_SESSION['success_message'] = 'Ticket actualizado correctamente.';
            redirect('ver_ticket.php?id=' . $ticket_id);
            exit;
        } catch (PDOException $e) {
            error_log('Error al editar ticket: ' . $e->getMessage());
            $error = 'Error al actualizar el ticket. Intente mas tarde.';
        }
    }
}

$csrf_token = generarTokenCSRF();
$page_title = 'Editar ' . ($ticket['folio'] ?? '');
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
                <label for="titulo">Titulo</label>
                <input type="text" id="titulo" name="titulo" class="form-control" placeholder="Resume tu solicitud en una linea" required value="<?= htmlspecialchars($titulo) ?>">
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
                <label for="categoria_id">Categoria</label>
                <select id="categoria_id" name="categoria_id" class="form-control">
                    <option value="">— Sin categoria —</option>
                    <?php foreach ($categorias as $cat): ?>
                        <option value="<?= (int) $cat['id'] ?>" <?= (int) $cat['id'] === $categoria_id ? 'selected' : '' ?>><?= htmlspecialchars($cat['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="quill-wrapper">
                <label>Descripcion</label>
                <div class="quill-editor" data-target="descripcion" data-placeholder="Describe detalladamente el problema o solicitud..." data-content="<?= htmlspecialchars($descripcion) ?>"></div>
                <input type="hidden" name="descripcion" value="">
            </div>

            <div class="flex gap-4">
                <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                <a href="<?= url('ver_ticket.php?id=' . $ticket_id) ?>" class="btn btn-outline">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

