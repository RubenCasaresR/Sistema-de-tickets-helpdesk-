<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Tickets - Helpdesk</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/helpdesk/assets/css/style.css">
</head>
<body>
<?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true): ?>
<header class="site-header" id="siteHeader">
    <div class="header-inner">
        <a href="/helpdesk/index.php" class="site-logo">Help<span>Desk</span></a>
        <nav class="main-nav">
            <?php if ($_SESSION['rol'] === 'cliente'): ?>
                <a href="/helpdesk/mis_tickets.php" class="<?= basename($_SERVER['PHP_SELF']) === 'mis_tickets.php' ? 'active' : '' ?>">Mis Tickets</a>
            <?php endif; ?>
            <?php if (in_array($_SESSION['rol'], ['soporte', 'admin'], true)): ?>
                <a href="/helpdesk/panel_admin.php" class="<?= basename($_SERVER['PHP_SELF']) === 'panel_admin.php' ? 'active' : '' ?>">Dashboard</a>
                <a href="/helpdesk/reportes.php" class="<?= basename($_SERVER['PHP_SELF']) === 'reportes.php' ? 'active' : '' ?>">Reportes</a>
            <?php endif; ?>
            <?php if ($_SESSION['rol'] === 'admin'): ?>
                <a href="/helpdesk/crear_ticket.php" class="<?= basename($_SERVER['PHP_SELF']) === 'crear_ticket.php' ? 'active' : '' ?>">Nuevo Ticket</a>
            <?php endif; ?>
        </nav>
        <div class="header-actions">
            <?php if (in_array($_SESSION['rol'], ['soporte', 'admin'], true)): ?>
                <?php
                require_once __DIR__ . '/../conexion.php';
                try {
                    $pdo = obtenerConexion();
                    $stmt = $pdo->query("
                        SELECT COUNT(*) AS total FROM tickets
                        WHERE (prioridad = 'urgente' AND asignado_id IS NULL)
                           OR (estado = 'abierto' AND fecha_creacion <= NOW() - INTERVAL 48 HOUR)
                    ");
                    $alerts = (int) $stmt->fetch()['total'];
                } catch (PDOException $e) {
                    $alerts = 0;
                }
                ?>
                <div class="bell-container">
                    <button class="bell-btn" id="bellBtn" aria-label="Notificaciones">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                    </button>
                    <span class="bell-dot <?= $alerts > 0 ? 'has-alerts' : '' ?>" id="bellDot"></span>
                    <div class="notifications-dropdown" id="notifDropdown">
                        <div class="notifications-header">
                            <span>Notificaciones</span>
                            <span style="font-weight:400;font-size:0.78rem;color:var(--color-text-muted)"><?= $alerts ?> alertas</span>
                        </div>
                        <ul class="notifications-list" id="notifList">
                            <?php if ($alerts > 0): ?>
                                <?php
                                $notifStmt = $pdo->query("
                                    (SELECT 'urgente' AS tipo, id, folio, titulo, fecha_creacion FROM tickets WHERE prioridad = 'urgente' AND asignado_id IS NULL)
                                    UNION ALL
                                    (SELECT 'vencido' AS tipo, id, folio, titulo, fecha_creacion FROM tickets WHERE estado = 'abierto' AND fecha_creacion <= NOW() - INTERVAL 48 HOUR)
                                    ORDER BY fecha_creacion DESC
                                    LIMIT 10
                                ");
                                while ($notif = $notifStmt->fetch()):
                                ?>
                                <a href="/helpdesk/ver_ticket.php?id=<?= (int) $notif['id'] ?>" class="notification-link">
                                    <li class="notification-item">
                                        <div class="notif-text">
                                            <?php if ($notif['tipo'] === 'urgente'): ?>
                                                &#x26A1; Ticket <strong><?= htmlspecialchars($notif['folio']) ?></strong> urgente sin asignar
                                            <?php else: ?>
                                                &#x23F0; Ticket <strong><?= htmlspecialchars($notif['folio']) ?></strong> sin actividad (+48h)
                                            <?php endif; ?>
                                        </div>
                                        <div class="notif-time"><?= htmlspecialchars($notif['titulo']) ?></div>
                                    </li>
                                </a>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <li class="notification-empty">No hay alertas pendientes</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>
            <span class="user-name"><?= htmlspecialchars($_SESSION['nombre'] ?? '') ?></span>
            <a href="/helpdesk/logout.php" class="btn btn-outline btn-sm">Salir</a>
        </div>
    </div>
</header>
<?php endif; ?>
<main class="page-wrapper">
