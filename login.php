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
$info = '';
if (isset($_GET['expired'])) {
    $info = 'Tu sesion fue cerrada porque otro usuario inicio sesion con tu cuenta.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verificarRateLimit('login', $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 10, 15)) {
        $error = 'Demasiados intentos. Intenta de nuevo en 15 minutos.';
    } else {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($email === '' || $password === '') {
            $error = 'Todos los campos son obligatorios.';
        } else {
            try {
                $pdo  = obtenerConexion();
                // Check if 'activo' column exists (pre-migration compat)
                $hasActivo = false;
                try {
                    $colCheck = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'activo'");
                    $hasActivo = (bool) $colCheck->fetch();
                } catch (PDOException $e) {
                    $hasActivo = false;
                }

                $selectCols = 'id, nombre, email, password, rol' . ($hasActivo ? ', activo' : '');
                $stmt = $pdo->prepare("SELECT {$selectCols} FROM usuarios WHERE email = :email LIMIT 1");
                $stmt->execute([':email' => $email]);
                $usuario = $stmt->fetch();

                if ($usuario && password_verify($password, $usuario['password'])) {
                    if ($hasActivo && empty($usuario['activo'])) {
                        $error = 'Tu cuenta esta pendiente de aprobacion por un administrador.';
                    } else {
                    session_regenerate_id(true);

                    $sessionToken = bin2hex(random_bytes(32));
                    $pdo->prepare('UPDATE usuarios SET session_token = :token WHERE id = :id')
                        ->execute([':token' => $sessionToken, ':id' => $usuario['id']]);

                    $_SESSION['logged_in']     = true;
                    $_SESSION['usuario_id']    = (int) $usuario['id'];
                    $_SESSION['nombre']        = $usuario['nombre'];
                    $_SESSION['email']         = $usuario['email'];
                    $_SESSION['rol']           = $usuario['rol'];
                    $_SESSION['session_token'] = $sessionToken;

                    redirigirPorRol($usuario['rol']);
                        exit;
                    }
                } else {
                    registrarIntentoFallido($email, 'login');
                    $error = 'Credenciales invalidas. Verifica tu email y contrasena.';
                }
            } catch (PDOException $e) {
                error_log('Error en login: ' . $e->getMessage());
                $error = 'Error interno del servidor. Intente mas tarde.';
            }
        }
    }
}

function redirigirPorRol(string $rol): void
{
    if ($rol === 'cliente') {
        header('Location: ' . url('mis_tickets.php'));
    } else {
        header('Location: ' . url('panel_admin.php'));
    }
}
$page_title = 'Iniciar Sesion';
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="auth-page">
    <div class="auth-card">
        <h1>HelpDesk</h1>
        <p class="auth-subtitle">Inicia sesion para continuar</p>

            <?php if ($info !== ''): ?>
                <div class="alert alert-info"><?= htmlspecialchars($info) ?></div>
            <?php endif; ?>
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
            <a href="<?= url('recuperar.php') ?>">¿Olvidaste tu contrasena?</a>
        </div>
        <div class="auth-links">
            ¿No tienes cuenta? <a href="<?= url('register.php') ?>">Registrate aqui</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

