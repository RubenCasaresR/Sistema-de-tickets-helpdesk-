<?php
session_start();

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: ' . url('index.php'));
    exit;
}

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/includes/auth_check.php';

$error   = '';
$success = '';
$token   = $_GET['token'] ?? '';

// Validar token
if ($token === '') {
    $error = 'Token invalido.';
} else {
    try {
        $pdo = obtenerConexion();
        $stmt = $pdo->prepare('SELECT id, usuario_id FROM password_resets WHERE token = :token AND usado = 0 AND expira_en > NOW() LIMIT 1');
        $stmt->execute([':token' => $token]);
        $reset = $stmt->fetch();

        if (!$reset) {
            $error = 'El enlace es invalido o ha expirado. Solicita un nuevo restablecimiento.';
        }
    } catch (PDOException $e) {
        error_log('Error al validar token: ' . $e->getMessage());
        $error = 'Error interno del servidor.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $reset) {
    if (!validarTokenCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad invalido.';
    } else {
        $password = $_POST['password'] ?? '';
        $confirmar = $_POST['password_confirmar'] ?? '';

        if (strlen($password) < 6) {
            $error = 'La contrasena debe tener al menos 6 caracteres.';
        } elseif ($password !== $confirmar) {
            $error = 'Las contrasenas no coinciden.';
        } else {
            try {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare('UPDATE usuarios SET password = :password WHERE id = :id')->execute([
                    ':password' => $hash,
                    ':id' => $reset['usuario_id'],
                ]);
                $pdo->prepare('UPDATE password_resets SET usado = 1 WHERE id = :id')->execute([':id' => $reset['id']]);

                $success = 'Contrasena actualizada correctamente. Ahora puedes iniciar sesion.';
                $reset = null;
            } catch (PDOException $e) {
                error_log('Error al restablecer contrasena: ' . $e->getMessage());
                $error = 'Error interno del servidor.';
            }
        }
    }
}
$csrf_token = generarTokenCSRF();
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="auth-page">
    <div class="auth-card">
        <h1>Restablecer Contrasena</h1>
        <p class="auth-subtitle">Ingresa tu nueva contrasena.</p>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success !== ''): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if ($reset): ?>
        <form method="post" action="?token=<?= htmlspecialchars($token) ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <div class="form-group">
                <label for="password">Nueva contrasena</label>
                <input type="password" id="password" name="password" class="form-control" placeholder="Minimo 6 caracteres" required minlength="6">
            </div>
            <div class="form-group">
                <label for="password_confirmar">Confirmar contrasena</label>
                <input type="password" id="password_confirmar" name="password_confirmar" class="form-control" placeholder="Repite la contrasena" required minlength="6">
            </div>
            <button type="submit" class="btn btn-primary btn-block">Guardar Nueva Contrasena</button>
        </form>
        <?php endif; ?>

        <div class="auth-links">
            <a href="<?= url('login.php') ?>">Volver al inicio de sesion</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

