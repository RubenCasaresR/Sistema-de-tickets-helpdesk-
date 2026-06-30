<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../conexion.php';
requiereRol(['admin']);

$pdo = obtenerConexion();
$error = '';
$success = '';

$editar_id = (int) ($_GET['id'] ?? 0);
$es_nuevo = $editar_id === 0;

$nombre = '';
$email  = '';
$rol    = 'cliente';

if (!$es_nuevo) {
    $stmt = $pdo->prepare('SELECT nombre, email, rol FROM usuarios WHERE id = :id');
    $stmt->execute([':id' => $editar_id]);
    $usuario = $stmt->fetch();
    if (!$usuario) {
        redirect('admin/usuarios.php');
        exit;
    }
    $nombre = $usuario['nombre'];
    $email  = $usuario['email'];
    $rol    = $usuario['rol'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad invalido.';
    } else {
        $nombre = trim($_POST['nombre'] ?? '');
        $email  = trim($_POST['email'] ?? '');
        $rol    = $_POST['rol'] ?? 'cliente';
        $password = $_POST['password'] ?? '';

        if ($nombre === '' || $email === '') {
            $error = 'El nombre y el correo son obligatorios.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Formato de correo invalido.';
        } elseif (!in_array($rol, ['cliente', 'soporte', 'admin'], true)) {
            $error = 'Rol invalido.';
        } elseif ($es_nuevo && $password === '') {
            $error = 'La contrasena es obligatoria para nuevos usuarios.';
        } elseif (!$es_nuevo && $password !== '' && strlen($password) < 8) {
            $error = 'La contrasena debe tener al menos 8 caracteres.';
        } else {
            // Verificar email unico
            $dup = $pdo->prepare('SELECT id FROM usuarios WHERE email = :email' . ($es_nuevo ? '' : ' AND id != :id') . ' LIMIT 1');
            $params = [':email' => $email];
            if (!$es_nuevo) $params[':id'] = $editar_id;
            $dup->execute($params);
            if ($dup->fetch()) {
                $error = 'El correo ya esta registrado.';
            } else {
                try {
                    if ($es_nuevo) {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $ins = $pdo->prepare('INSERT INTO usuarios (nombre, email, password, rol) VALUES (:nombre, :email, :password, :rol)');
                        $ins->execute([':nombre' => $nombre, ':email' => $email, ':password' => $hash, ':rol' => $rol]);
                        $_SESSION['success_message'] = 'Usuario creado correctamente.';
                    } else {
                        if ($password !== '') {
                            $hash = password_hash($password, PASSWORD_DEFAULT);
                            $upd = $pdo->prepare('UPDATE usuarios SET nombre = :nombre, email = :email, password = :password, rol = :rol WHERE id = :id');
                            $upd->execute([':nombre' => $nombre, ':email' => $email, ':password' => $hash, ':rol' => $rol, ':id' => $editar_id]);
                        } else {
                            $upd = $pdo->prepare('UPDATE usuarios SET nombre = :nombre, email = :email, rol = :rol WHERE id = :id');
                            $upd->execute([':nombre' => $nombre, ':email' => $email, ':rol' => $rol, ':id' => $editar_id]);
                        }
                        $_SESSION['success_message'] = 'Usuario actualizado correctamente.';
                    }
                    redirect('admin/usuarios.php');
                    exit;
                } catch (PDOException $e) {
                    error_log('Error al guardar usuario: ' . $e->getMessage());
                    $error = 'Error al guardar. Intente mas tarde.';
                }
            }
        }
    }
}

$csrf_token = generarTokenCSRF();
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="page-header">
    <h1><?= $es_nuevo ? 'Nuevo Usuario' : 'Editar Usuario' ?></h1>
    <p><?= $es_nuevo ? 'Crea una nueva cuenta en el sistema.' : 'Modifica los datos del usuario.' ?></p>
</div>

<div class="card" style="max-width:520px">
    <div class="card-header">
        <h3><?= $es_nuevo ? 'Datos del nuevo usuario' : 'Datos del usuario' ?></h3>
    </div>
    <div class="card-body">
        <?php if ($error !== ''): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" action="">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

            <div class="form-group">
                <label for="nombre">Nombre completo</label>
                <input type="text" id="nombre" name="nombre" class="form-control" required value="<?= htmlspecialchars($nombre) ?>">
            </div>

            <div class="form-group">
                <label for="email">Correo electronico</label>
                <input type="email" id="email" name="email" class="form-control" required value="<?= htmlspecialchars($email) ?>">
            </div>

            <div class="form-group">
                <label for="rol">Rol</label>
                <select id="rol" name="rol" class="form-control">
                    <option value="cliente" <?= $rol === 'cliente' ? 'selected' : '' ?>>Cliente</option>
                    <option value="soporte" <?= $rol === 'soporte' ? 'selected' : '' ?>>Soporte</option>
                    <option value="admin" <?= $rol === 'admin' ? 'selected' : '' ?>>Admin</option>
                </select>
            </div>

            <div class="form-group">
                <label for="password">Contrasena <?= $es_nuevo ? '' : '<span class="text-muted">(dejar vacio para mantener)</span>' ?></label>
                <input type="password" id="password" name="password" class="form-control" placeholder="<?= $es_nuevo ? 'Minimo 8 caracteres' : 'Nueva contrasena (opcional)' ?>" <?= $es_nuevo ? 'required' : '' ?> minlength="8">
            </div>

            <div class="flex gap-4" style="margin-top:24px">
                <button type="submit" class="btn btn-primary"><?= $es_nuevo ? 'Crear Usuario' : 'Guardar Cambios' ?></button>
                <a href="<?= url('admin/usuarios.php') ?>" class="btn btn-outline">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
