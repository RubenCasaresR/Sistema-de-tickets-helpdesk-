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
$email   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad invalido.';
    } else {
        $email = trim($_POST['email'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Ingresa un correo electronico valido.';
        } else {
            try {
                $pdo = obtenerConexion();
                $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = :email LIMIT 1');
                $stmt->execute([':email' => $email]);
                $usuario = $stmt->fetch();

                $token = null;
                $enlace = null;

                if ($usuario) {
                    $token = bin2hex(random_bytes(32));
                    $expira = date('Y-m-d H:i:s', strtotime('+1 hour'));

                    $ins = $pdo->prepare('INSERT INTO password_resets (usuario_id, token, expira_en) VALUES (:usuario_id, :token, :expira_en)');
                    $ins->execute([':usuario_id' => $usuario['id'], ':token' => $token, ':expira_en' => $expira]);

                    if (file_exists(__DIR__ . '/helpers/mailer.php')) {
                        require_once __DIR__ . '/helpers/mailer.php';
                        $enlace = rtrim(MAIL_BASE_URL, '/') . url('restablecer.php?token=' . $token);
                        $asunto = 'Restablecer tu contrasena - HelpDesk';
                        $cuerpo = '
                            <h2>Restablecer Contrasena</h2>
                            <p>Haz clic en el siguiente enlace para restablecer tu contrasena. Este enlace expira en 1 hora.</p>
                            <p><a href="' . $enlace . '" style="display:inline-block;padding:12px 24px;background:#2d3436;color:#fff;text-decoration:none;border-radius:6px">Restablecer Contrasena</a></p>
                            <p>Si no solicitaste este cambio, ignora este correo.</p>
                        ';
                        $enviado = enviarCorreo($email, $asunto, $cuerpo);
                    }
                }

                $smtpDeshabilitado = !defined('MAIL_USER') || MAIL_USER === '' || !defined('MAIL_PASS') || MAIL_PASS === '';
                if ($usuario && !empty($enlace) && ($smtpDeshabilitado || empty($enviado))) {
                    $success = 'Haz clic en el siguiente enlace para restablecer tu contrasena (valido por 1 hora):<br>
                        <a href="' . htmlspecialchars($enlace) . '" style="display:inline-block;margin-top:8px;padding:10px 20px;background:#2d3436;color:#fff;text-decoration:none;border-radius:6px">Restablecer Contrasena</a>';
                } else {
                    $success = 'Si la cuenta existe, recibiras un correo con instrucciones para restablecer tu contrasena.';
                }
                $email = '';
            } catch (PDOException $e) {
                error_log('Error en recuperar contrasena: ' . $e->getMessage());
                $error = 'Error interno del servidor. Intente mas tarde.';
            }
        }
    }
}
$csrf_token = generarTokenCSRF();
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="auth-page">
    <div class="auth-card">
        <h1>Recuperar Contrasena</h1>
        <p class="auth-subtitle">Ingresa tu correo y te enviaremos instrucciones.</p>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success !== ''): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="post" action="">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <div class="form-group">
                <label for="email">Correo electronico</label>
                <input type="email" id="email" name="email" class="form-control" placeholder="tu@correo.com" required value="<?= htmlspecialchars($email) ?>">
            </div>
            <button type="submit" class="btn btn-primary btn-block">Enviar Instrucciones</button>
        </form>

        <div class="auth-links">
            <a href="<?= url('login.php') ?>">Volver al inicio de sesion</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

