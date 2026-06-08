<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/conexion.php';
requiereAutenticacion();

$pdo = obtenerConexion();
$usuario_id = (int) $_SESSION['usuario_id'];

$error   = '';
$success = '';

$stmt = $pdo->prepare('SELECT nombre, email FROM usuarios WHERE id = :id');
$stmt->execute([':id' => $usuario_id]);
$usuario = $stmt->fetch();

if (!$usuario) {
    redirect('logout.php');
    exit;
}

$nombre = $usuario['nombre'];
$email  = $usuario['email'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad invalido.';
    } else {
        $nombre_nuevo   = trim($_POST['nombre'] ?? '');
        $email_nuevo    = trim($_POST['email'] ?? '');
        $pass_actual    = $_POST['password_actual'] ?? '';
        $pass_nueva     = $_POST['password_nueva'] ?? '';
        $pass_confirmar = $_POST['password_confirmar'] ?? '';

        if ($nombre_nuevo === '' || $email_nuevo === '') {
            $error = 'El nombre y el correo son obligatorios.';
        } elseif (!filter_var($email_nuevo, FILTER_VALIDATE_EMAIL)) {
            $error = 'El formato del correo electronico no es valido.';
        } elseif ($pass_actual === '') {
            $error = 'Debes ingresar tu contrasena actual para guardar cambios.';
        } else {
            $check = $pdo->prepare('SELECT password FROM usuarios WHERE id = :id');
            $check->execute([':id' => $usuario_id]);
            $row = $check->fetch();

            if (!password_verify($pass_actual, $row['password'])) {
                $error = 'La contrasena actual no es correcta.';
            } else {
                $dup = $pdo->prepare('SELECT id FROM usuarios WHERE email = :email AND id != :id LIMIT 1');
                $dup->execute([':email' => $email_nuevo, ':id' => $usuario_id]);
                if ($dup->fetch()) {
                    $error = 'El correo electronico ya esta registrado por otro usuario.';
                } elseif ($pass_nueva !== '' && strlen($pass_nueva) < 6) {
                    $error = 'La nueva contrasena debe tener al menos 6 caracteres.';
                } elseif ($pass_nueva !== '' && $pass_nueva !== $pass_confirmar) {
                    $error = 'Las contrasenas nuevas no coinciden.';
                } else {
                    try {
                        if ($pass_nueva !== '') {
                            $hash = password_hash($pass_nueva, PASSWORD_DEFAULT);
                            $upd = $pdo->prepare('UPDATE usuarios SET nombre = :nombre, email = :email, password = :password WHERE id = :id');
                            $upd->execute([':nombre' => $nombre_nuevo, ':email' => $email_nuevo, ':password' => $hash, ':id' => $usuario_id]);
                        } else {
                            $upd = $pdo->prepare('UPDATE usuarios SET nombre = :nombre, email = :email WHERE id = :id');
                            $upd->execute([':nombre' => $nombre_nuevo, ':email' => $email_nuevo, ':id' => $usuario_id]);
                        }
                        $_SESSION['nombre'] = $nombre_nuevo;
                        $success = 'Perfil actualizado correctamente.';
                        $nombre  = $nombre_nuevo;
                        $email   = $email_nuevo;
                    } catch (PDOException $e) {
                        error_log('Error al actualizar perfil: ' . $e->getMessage());
                        $error = 'Error al guardar los cambios. Intente mas tarde.';
                    }
                }
            }
        }
    }
}
$csrf_token = generarTokenCSRF();
$page_title = 'Mi Perfil';
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="page-header">
    <h1>Mi Perfil</h1>
    <p>Administra tu informacion personal y contrasena.</p>
</div>

<div class="card" style="max-width:560px">
    <div class="card-header">
        <h3>Datos de la Cuenta</h3>
    </div>
    <div class="card-body">
        <?php if ($error !== ''): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success !== ''): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
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

            <hr style="border:none;border-top:1px solid var(--color-border);margin:24px 0">

            <p class="text-muted text-small mb-4">Ingresa tu contrasena actual para guardar cambios. Si deseas cambiarla, completa los campos siguientes.</p>

            <div class="form-group">
                <label for="password_actual">Contrasena actual</label>
                <input type="password" id="password_actual" name="password_actual" class="form-control" required placeholder="Requerida para guardar cambios">
            </div>

            <div class="form-group">
                <label for="password_nueva">Nueva contrasena <span class="text-muted">(opcional)</span></label>
                <input type="password" id="password_nueva" name="password_nueva" class="form-control" placeholder="Minimo 6 caracteres" minlength="6">
            </div>

            <div class="form-group">
                <label for="password_confirmar">Confirmar nueva contrasena</label>
                <input type="password" id="password_confirmar" name="password_confirmar" class="form-control" placeholder="Repite la nueva contrasena" minlength="6">
            </div>

            <div class="flex gap-4" style="margin-top:24px">
                <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                <a href="javascript:history.back()" class="btn btn-outline">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
