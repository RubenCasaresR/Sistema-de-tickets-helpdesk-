<?php
session_start();

// Si ya esta logueado, redirigir segun rol
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    redirigirPorRol($_SESSION['rol'] ?? '');
    exit;
}

require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/conexion.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Todos los campos son obligatorios.';
    } else {
        try {
            $pdo  = obtenerConexion();
            $stmt = $pdo->prepare('SELECT id, nombre, email, password, rol FROM usuarios WHERE email = :email LIMIT 1');
            $stmt->execute([':email' => $email]);
            $usuario = $stmt->fetch();

            if ($usuario && password_verify($password, $usuario['password'])) {
                session_regenerate_id(true);

                $_SESSION['logged_in']  = true;
                $_SESSION['usuario_id'] = (int) $usuario['id'];
                $_SESSION['nombre']     = $usuario['nombre'];
                $_SESSION['email']      = $usuario['email'];
                $_SESSION['rol']        = $usuario['rol'];

                redirigirPorRol($usuario['rol']);
                exit;
            }

            $error = 'Credenciales invalidas. Verifica tu email y contrasena.';
        } catch (PDOException $e) {
            error_log('Error en login: ' . $e->getMessage());
            $error = 'Error interno del servidor. Intente mas tarde.';
        }
    }
}

function redirigirPorRol(string $rol): void
{
    if ($rol === 'cliente') {
        header('Location: /helpdesk/mis_tickets.php');
    } else {
        header('Location: /helpdesk/panel_admin.php');
    }
}
$page_title = 'Iniciar Sesion';
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="auth-page">
    <div class="auth-card">
        <h1>HelpDesk</h1>
        <p class="auth-subtitle">Inicia sesion para continuar</p>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" action="">
            <div class="form-group">
                <label for="email">Correo electronico</label>
                <input type="email" id="email" name="email" class="form-control" placeholder="tu@correo.com" required autocomplete="email">
            </div>
            <div class="form-group">
                <label for="password">Contrasena</label>
                <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn btn-primary btn-block">Iniciar Sesion</button>
        </form>

        <div class="auth-links">
            <a href="/helpdesk/recuperar.php">¿Olvidaste tu contrasena?</a>
        </div>
        <div class="auth-links">
            ¿No tienes cuenta? <a href="/helpdesk/register.php">Registrate aqui</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

