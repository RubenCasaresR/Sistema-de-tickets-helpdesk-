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

// Create / Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validarTokenCSRF($csrf_token)) {
        $error = 'Token de seguridad invalido.';
    } elseif ($_POST['accion'] === 'guardar') {
        $nombre = trim($_POST['nombre'] ?? '');
        $id = (int) ($_POST['id'] ?? 0);
        if ($nombre === '') {
            $error = 'El nombre es obligatorio.';
        } elseif ($id > 0) {
            $pdo->prepare('UPDATE categorias SET nombre = :nombre WHERE id = :id')->execute([':nombre' => $nombre, ':id' => $id]);
            $_SESSION['success_message'] = 'Categoria actualizada.';
        } else {
            $pdo->prepare('INSERT INTO categorias (nombre) VALUES (:nombre)')->execute([':nombre' => $nombre]);
            $_SESSION['success_message'] = 'Categoria creada.';
        }
        header('Location: /helpdesk/admin/categorias.php');
        exit;
    } elseif ($_POST['accion'] === 'toggle_activo') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare('UPDATE categorias SET activo = NOT activo WHERE id = :id')->execute([':id' => $id]);
            $_SESSION['success_message'] = 'Estado cambiado.';
        }
        header('Location: /helpdesk/admin/categorias.php');
        exit;
    } elseif ($_POST['accion'] === 'eliminar') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare('DELETE FROM categorias WHERE id = :id')->execute([':id' => $id]);
            $_SESSION['success_message'] = 'Categoria eliminada.';
        }
        header('Location: /helpdesk/admin/categorias.php');
        exit;
    }
}

$categorias = $pdo->query('SELECT * FROM categorias ORDER BY nombre')->fetchAll();
$csrf_token = generarTokenCSRF();
$page_title = 'Categorias';
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="page-header flex-between">
    <div>
        <h1>Categorias</h1>
        <p>Gestiona las categorias de tickets.</p>
    </div>
    <button class="btn btn-primary" onclick="document.getElementById('formCrear').style.display='block'">+ Nueva</button>
</div>

<?php if ($error !== ''): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success !== ''): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<!-- Create form -->
<div id="formCrear" class="card mb-6" style="display:none">
    <div class="card-header"><h3>Nueva Categoria</h3></div>
    <div class="card-body">
        <form method="post" action="">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="accion" value="guardar">
            <div class="flex gap-4" style="align-items:flex-end">
                <div class="form-group" style="flex:1;margin-bottom:0">
                    <input type="text" name="nombre" class="form-control" placeholder="Nombre de la categoria" required>
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
                <tr><th>Nombre</th><th>Activo</th><th>Acciones</th></tr>
            </thead>
            <tbody>
                <?php foreach ($categorias as $cat): ?>
                <tr>
                    <td>
                        <form method="post" action="" style="display:flex;gap:8px;align-items:center">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            <input type="hidden" name="accion" value="guardar">
                            <input type="hidden" name="id" value="<?= (int) $cat['id'] ?>">
                            <input type="text" name="nombre" value="<?= htmlspecialchars($cat['nombre']) ?>" class="form-control" style="width:200px;margin-bottom:0" required>
                            <button type="submit" class="btn btn-sm btn-primary">Renombrar</button>
                        </form>
                    </td>
                    <td><?= $cat['activo'] ? '✅' : '❌' ?></td>
                    <td>
                        <div class="flex gap-2">
                            <form method="post" action="" style="display:inline">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                <input type="hidden" name="accion" value="toggle_activo">
                                <input type="hidden" name="id" value="<?= (int) $cat['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline"><?= $cat['activo'] ? 'Desactivar' : 'Activar' ?></button>
                            </form>
                            <form method="post" action="" style="display:inline" onsubmit="return confirm('¿Eliminar esta categoria?')">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                <input type="hidden" name="accion" value="eliminar">
                                <input type="hidden" name="id" value="<?= (int) $cat['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
