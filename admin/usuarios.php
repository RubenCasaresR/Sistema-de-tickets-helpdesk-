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
            header('Location: /helpdesk/admin/usuarios.php');
            exit;
        }
    }
}

$stmt = $pdo->query('SELECT id, nombre, email, rol, fecha_creacion FROM usuarios ORDER BY fecha_creacion DESC');
$usuarios = $stmt->fetchAll();
$csrf_token = generarTokenCSRF();
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="page-header flex-between">
    <div>
        <h1>Usuarios</h1>
        <p>Gestion de cuentas del sistema.</p>
    </div>
    <a href="/helpdesk/admin/usuario_editar.php" class="btn btn-primary">+ Nuevo Usuario</a>
</div>

<?php if ($error !== ''): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
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
                    <th>Registro</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usuarios as $u): ?>
                <tr>
                    <td><?= htmlspecialchars($u['nombre']) ?></td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td><span class="badge badge-<?= $u['rol'] === 'admin' ? 'urgente' : ($u['rol'] === 'soporte' ? 'en_progreso' : 'cerrado') ?>"><?= htmlspecialchars($u['rol']) ?></span></td>
                    <td class="text-small text-muted"><?= htmlspecialchars(date('d/m/Y', strtotime($u['fecha_creacion']))) ?></td>
                    <td>
                        <div class="flex gap-2">
                            <a href="/helpdesk/admin/usuario_editar.php?id=<?= (int) $u['id'] ?>" class="btn btn-outline btn-sm">Editar</a>
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
