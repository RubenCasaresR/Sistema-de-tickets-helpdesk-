<?php
session_start();
require_once __DIR__ . '/conexion.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ' . url('login.php'));
    exit;
}

if ($_SESSION['rol'] === 'cliente') {
    header('Location: ' . url('mis_tickets.php'));
} else {
    header('Location: ' . url('panel_admin.php'));
}
exit;
