<?php
/**
 * Application configuration.
 *
 * In production, set these values via environment variables.
 * Falls back to defaults for local development.
 */

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'helpdesk');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

define('BASE_URL', getenv('BASE_URL') ?: '/helpdesk');
