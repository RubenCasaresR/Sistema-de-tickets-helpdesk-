<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../conexion.php';
requiereRol(['admin']);

$pdo = obtenerConexion();
$error = '';
$success = '';

if (isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validarTokenCSRF($csrf_token)) {
        $error = 'Token de seguridad invalido.';
    } elseif ($_POST['accion'] === 'guardar') {
        $nombre = trim($_POST['nombre'] ?? '');
        $color = trim($_POST['color'] ?? '#6366f1');
        $id = (int) ($_POST['id'] ?? 0);
        if ($nombre === '') {
            $error = 'El nombre es obligatorio.';
        } elseif ($id > 0) {
            $pdo->prepare('UPDATE etiquetas SET nombre = :nombre, color = :color WHERE id = :id')
                ->execute([':nombre' => $nombre, ':color' => $color, ':id' => $id]);
            $_SESSION['success_message'] = 'Etiqueta actualizada.';
        } else {
            $pdo->prepare('INSERT INTO etiquetas (nombre, color) VALUES (:nombre, :color)')
                ->execute([':nombre' => $nombre, ':color' => $color]);
            $_SESSION['success_message'] = 'Etiqueta creada.';
        }
        header('Location: /helpdesk/admin/etiquetas.php');
        exit;
    } elseif ($_POST['accion'] === 'eliminar') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare('DELETE FROM etiquetas WHERE id = :id')->execute([':id' => $id]);
            $_SESSION['success_message'] = 'Etiqueta eliminada.';
        }
        header('Location: /helpdesk/admin/etiquetas.php');
        exit;
    }
}

$etiquetas = $pdo->query('SELECT * FROM etiquetas ORDER BY nombre')->fetchAll();
$csrf_token = generarTokenCSRF();
$page_title = 'Etiquetas';
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="page-header flex-between">
    <div>
        <h1>Etiquetas</h1>
        <p>Gestiona las etiquetas para tareas.</p>
    </div>
    <button class="btn btn-primary" onclick="document.getElementById('formCrear').style.display='block'">+ Nueva</button>
</div>

<?php if ($error !== ''): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success !== ''): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<div id="formCrear" class="card mb-6" style="display:none">
    <div class="card-header"><h3>Nueva Etiqueta</h3></div>
    <div class="card-body">
        <form method="post" action="">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="accion" value="guardar">
            <div class="flex gap-4" style="align-items:flex-end">
                <div class="form-group" style="margin-bottom:0">
                    <input type="text" name="nombre" class="form-control" placeholder="Nombre" required>
                </div>
                <div class="form-group" style="margin-bottom:0">
                    <input type="color" name="color" class="form-control" value="#6366f1" style="width:60px;padding:4px">
                </div>
                <button type="submit" class="btn btn-primary">Guardar</button>
                <button type="button" class="btn btn-outline" onclick="this.closest('.card').style.display='none'">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-container">
        <table>
            <thead>
                <tr><th>Nombre</th><th>Color</th><th>Acciones</th></tr>
            </thead>
            <tbody>
                <?php foreach ($etiquetas as $et): ?>
                <tr>
                    <td>
                        <form method="post" action="" style="display:flex;gap:8px;align-items:center">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            <input type="hidden" name="accion" value="guardar">
                            <input type="hidden" name="id" value="<?= (int) $et['id'] ?>">
                            <input type="text" name="nombre" value="<?= htmlspecialchars($et['nombre']) ?>" class="form-control" style="width:160px;margin-bottom:0" required>
                            <input type="color" name="color" value="<?= htmlspecialchars($et['color']) ?>" class="form-control" style="width:60px;padding:4px;margin-bottom:0">
                            <button type="submit" class="btn btn-sm btn-primary">Guardar</button>
                        </form>
                    </td>
                    <td><span class="task-tag-sm" style="background:<?= htmlspecialchars($et['color']) ?>20;color:<?= htmlspecialchars($et['color']) ?>"><?= htmlspecialchars($et['color']) ?></span></td>
                    <td>
                        <form method="post" action="" style="display:inline" onsubmit="return confirm('¿Eliminar esta etiqueta?')">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            <input type="hidden" name="accion" value="eliminar">
                            <input type="hidden" name="id" value="<?= (int) $et['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
