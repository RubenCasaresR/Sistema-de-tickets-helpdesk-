<?php
session_start();

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    if ($_SESSION['rol'] === 'cliente') {
        header('Location: /helpdesk/mis_tickets.php');
    } else {
        header('Location: /helpdesk/panel_admin.php');
    }
    exit;
}

require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/conexion.php';

$error   = '';
$success = '';
$nombre  = '';
$email   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre  = trim($_POST['nombre'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmar = $_POST['confirmar_password'] ?? '';

    if ($nombre === '' || $email === '' || $password === '' || $confirmar === '') {
        $error = 'Todos los campos son obligatorios.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'El formato del correo electrónico no es válido.';
    } elseif (strlen($password) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres.';
    } elseif ($password !== $confirmar) {
        $error = 'Las contraseñas no coinciden.';
    } else {
        try {
            $pdo = obtenerConexion();

            $check = $pdo->prepare('SELECT id FROM usuarios WHERE email = :email LIMIT 1');
            $check->execute([':email' => $email]);

            if ($check->fetch()) {
                $error = 'El correo ya está registrado.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('INSERT INTO usuarios (nombre, email, password, rol) VALUES (:nombre, :email, :password, :rol)');
                $stmt->execute([
                    ':nombre'   => $nombre,
                    ':email'    => $email,
                    ':password' => $hash,
                    ':rol'      => 'cliente',
                ]);

                $success = 'Cuenta creada exitosamente. Ahora puedes iniciar sesión.';
                $nombre  = '';
                $email   = '';
            }
        } catch (PDOException $e) {
            error_log('Error en registro: ' . $e->getMessage());
            $error = 'Error interno del servidor. Intente más tarde.';
        }
    }
}
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="auth-page">
    <div class="auth-card">
        <h1>Crear Cuenta</h1>
        <p class="auth-subtitle">Regístrate como cliente</p>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success !== ''): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="post" action="">
            <div class="form-group">
                <label for="nombre">Nombre completo</label>
                <input type="text" id="nombre" name="nombre" class="form-control" placeholder="Tu nombre" required value="<?= htmlspecialchars($nombre) ?>">
            </div>
            <div class="form-group">
                <label for="email">Correo electrónico</label>
                <input type="email" id="email" name="email" class="form-control" placeholder="tu@correo.com" required value="<?= htmlspecialchars($email) ?>">
            </div>
            <div class="form-group">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" class="form-control" placeholder="Mínimo 6 caracteres" required minlength="6">
            </div>
            <div class="form-group">
                <label for="confirmar_password">Confirmar contraseña</label>
                <input type="password" id="confirmar_password" name="confirmar_password" class="form-control" placeholder="Repite la contraseña" required minlength="6">
            </div>
            <button type="submit" class="btn btn-primary btn-block">Crear Cuenta</button>
        </form>

        <div class="auth-links">
            ¿Ya tienes cuenta? <a href="/helpdesk/login.php">Inicia sesión</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
