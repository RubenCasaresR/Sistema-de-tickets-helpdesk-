<?php
/**
 * Application configuration.
 *
 * Reads from .env file via phpdotenv (vlucas/phpdotenv).
 * Falls back to defaults for local development.
 */

// ── Timezone ──
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
date_default_timezone_set('America/Mexico_City');

// ── Load .env ──
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'helpdesk');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

define('BASE_URL', getenv('BASE_URL') ?: '/helpdesk');
define('SESSION_LIFETIME', (int) (getenv('SESSION_LIFETIME') ?: 28800));

function url(string $path = ''): string
{
    $base = rtrim(BASE_URL, '/');
    if ($path === '') return $base;
    return $base . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}
