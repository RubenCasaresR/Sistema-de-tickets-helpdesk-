<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function requiereAutenticacion(): void
{
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        header('Location: /helpdesk/login.php');
        exit;
    }
}

function requiereRol(array $roles): void
{
    requiereAutenticacion();

    if (!in_array($_SESSION['rol'] ?? '', $roles, true)) {
        header('Location: /helpdesk/index.php');
        exit;
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
