<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: /helpdesk/login.php');
    exit;
}

if ($_SESSION['rol'] === 'cliente') {
    header('Location: /helpdesk/mis_tickets.php');
} else {
    header('Location: /helpdesk/panel_admin.php');
}
exit;
