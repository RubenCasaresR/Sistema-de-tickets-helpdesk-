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

// Aprobar usuario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aprobar_id'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    $aprobar_id = (int) $_POST['aprobar_id'];
    if (validarTokenCSRF($csrf_token) && $aprobar_id > 0) {
        $pdo->prepare('UPDATE usuarios SET activo = 1 WHERE id = :id')->execute([':id' => $aprobar_id]);
        $_SESSION['success_message'] = 'Usuario activado correctamente.';
        redirect('admin/usuarios.php');
        exit;
    }
    $error = 'Token de seguridad invalido.';
}

// Eliminar usuario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_id'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    $eliminar_id = (int) $_POST['eliminar_id'];

    if (!validarTokenCSRF($csrf_token)) {
        $error = 'Token de seguridad invalido.';
    } elseif ($eliminar_id === (int) $_SESSION['usuario_id']) {
        $error = 'No puedes eliminarte a ti mismo.';
    } else {
        $check = $pdo->prepare('SELECT COUNT(*) FROM usuarios WHERE rol = \'admin\'');
        $check->execute();
        $totalAdmins = (int) $check->fetchColumn();

        $stmt = $pdo->prepare('SELECT rol FROM usuarios WHERE id = :id');
        $stmt->execute([':id' => $eliminar_id]);
        $target = $stmt->fetch();

        if ($target && $target['rol'] === 'admin' && $totalAdmins <= 1) {
            $error = 'No puedes eliminar al ultimo administrador.';
        } elseif (!$target) {
            $error = 'Usuario no encontrado.';
        } else {
            $del = $pdo->prepare('DELETE FROM usuarios WHERE id = :id');
            $del->execute([':id' => $eliminar_id]);
            $_SESSION['success_message'] = 'Usuario eliminado correctamente.';
            redirect('admin/usuarios.php');
            exit;
        }
    }
}

$stmt = $pdo->query('SELECT id, nombre, email, rol, activo, fecha_creacion FROM usuarios ORDER BY activo ASC, fecha_creacion DESC');
$usuarios = $stmt->fetchAll();
$csrf_token = generarTokenCSRF();
$pendientes = count(array_filter($usuarios, fn($u) => empty($u['activo'])));
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="page-header flex-between">
    <div>
        <h1>Usuarios</h1>
        <p>Gestion de cuentas del sistema.</p>
    </div>
    <a href="<?= url('admin/usuario_editar.php') ?>" class="btn btn-primary">+ Nuevo Usuario</a>
</div>

<?php if ($error !== ''): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($pendientes > 0): ?>
    <div class="alert alert-info">Hay <strong><?= $pendientes ?></strong> usuario(s) pendiente(s) de aprobacion.</div>
<?php endif; ?>
<?php if ($success !== ''): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-body" style="padding:0">
        <table class="table">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Email</th>
                    <th>Rol</th>
                    <th>Estado</th>
                    <th>Registro</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usuarios as $u): ?>
                <tr class="<?= empty($u['activo']) ? 'row-pending' : '' ?>">
                    <td><?= htmlspecialchars($u['nombre']) ?></td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td><span class="badge badge-<?= $u['rol'] === 'admin' ? 'urgente' : ($u['rol'] === 'soporte' ? 'en_progreso' : 'cerrado') ?>"><?= htmlspecialchars($u['rol']) ?></span></td>
                    <td>
                        <?php if (empty($u['activo'])): ?>
                            <span class="badge badge-cerrado">Pendiente</span>
                        <?php else: ?>
                            <span class="badge badge-resuelto">Activo</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-small text-muted"><?= htmlspecialchars(date('d/m/Y', strtotime($u['fecha_creacion']))) ?></td>
                    <td>
                        <div class="flex gap-2">
                            <?php if (empty($u['activo'])): ?>
                            <form method="post" action="" style="display:inline">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                <input type="hidden" name="aprobar_id" value="<?= (int) $u['id'] ?>">
                                <button type="submit" class="btn btn-primary btn-sm">Aprobar</button>
                            </form>
                            <?php endif; ?>
                            <a href="<?= url('admin/usuario_editar.php?id=' . (int) $u['id']) ?>" class="btn btn-outline btn-sm">Editar</a>
                            <?php if ((int) $u['id'] !== (int) $_SESSION['usuario_id']): ?>
                            <form method="post" action="" onsubmit="return confirm('¿Eliminar a <?= htmlspecialchars($u['nombre'], ENT_QUOTES) ?>?')" style="display:inline">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                <input type="hidden" name="eliminar_id" value="<?= (int) $u['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
