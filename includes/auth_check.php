<?php

if (session_status() === PHP_SESSION_NONE) {
    $cookiePath = defined('BASE_URL') && BASE_URL ? rtrim(BASE_URL, '/') . '/' : '/helpdesk/';
    $sessionLifetime = defined('SESSION_LIFETIME') ? (int) SESSION_LIFETIME : 28800;
    session_set_cookie_params([
        'lifetime' => $sessionLifetime,
        'path' => $cookiePath,
        'domain' => '',
        'secure' => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
    if (!isset($_SESSION['_created'])) {
        $_SESSION['_created'] = time();
    } elseif (time() - $_SESSION['_created'] > $sessionLifetime) {
        $_SESSION = [];
        session_destroy();
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_start();
        $_SESSION = [];
    }
}

function requiereAutenticacion(): void
{
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        redirect('login.php');
        exit;
    }

    if (isset($_SESSION['session_token']) && function_exists('obtenerConexion')) {
        try {
            $pdo = obtenerConexion();
            $stmt = $pdo->prepare('SELECT session_token FROM usuarios WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $_SESSION['usuario_id']]);
            $row = $stmt->fetch();
            if ($row && $row['session_token'] !== null && $row['session_token'] !== $_SESSION['session_token']) {
                $_SESSION = [];
                session_destroy();
                redirect('login.php?expired=1');
                exit;
            }
        } catch (PDOException $e) {
            error_log('Error verificando session_token: ' . $e->getMessage());
        }
    }
}

function requiereRol(array $roles): void
{
    requiereAutenticacion();

    if (!in_array($_SESSION['rol'] ?? '', $roles, true)) {
        redirect('index.php');
    }
}

function generarTokenCSRF(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validarTokenCSRF(string $token): bool
{
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function regenerarTokenCSRF(): void
{
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
