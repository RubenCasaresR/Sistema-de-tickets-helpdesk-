<?php

if (session_status() === PHP_SESSION_NONE) {
    $cookiePath = defined('BASE_URL') && BASE_URL ? BASE_URL : '/helpdesk';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $cookiePath,
        'domain' => '',
        'secure' => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

function requiereAutenticacion(): void
{
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        redirect('login.php');
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
