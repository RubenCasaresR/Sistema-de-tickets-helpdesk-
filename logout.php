<?php
session_start();
require_once __DIR__ . '/conexion.php';

if (isset($_SESSION['usuario_id'])) {
    try {
        $pdo = obtenerConexion();
        $pdo->prepare('UPDATE usuarios SET session_token = NULL WHERE id = :id')
            ->execute([':id' => $_SESSION['usuario_id']]);
    } catch (PDOException $e) {
        error_log('Error al limpiar session_token: ' . $e->getMessage());
    }
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();
header('Location: ' . url('login.php'));
exit;
