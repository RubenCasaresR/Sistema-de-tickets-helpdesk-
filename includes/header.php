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
    <title><?= isset($page_title) ? htmlspecialchars($page_title) . ' — ' : '' ?>Helpdesk</title>
    <link rel="icon" type="image/svg+xml" href="<?= url('assets/img/favicon.svg') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
    <?php if (function_exists('generarTokenCSRF')): ?>
    <meta name="csrf-token" content="<?= htmlspecialchars(generarTokenCSRF()) ?>">
    <?php endif; ?>
    <script>window.BASE_URL = '<?= BASE_URL ?>';</script>
</head>
<body>
<?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true): ?>
<header class="site-header" id="siteHeader">
    <div class="header-inner">
        <a href="<?= url('index.php') ?>" class="site-logo">Help<span>Desk</span></a>
        <nav class="main-nav">
            <?php if ($_SESSION['rol'] === 'cliente'): ?>
                <a href="<?= url('mis_tickets.php') ?>" class="<?= basename($_SERVER['PHP_SELF']) === 'mis_tickets.php' ? 'active' : '' ?>">Mis Tickets</a>
            <?php endif; ?>
            <?php if (in_array($_SESSION['rol'], ['soporte', 'admin'], true)): ?>
                <a href="<?= url('panel_admin.php') ?>" class="<?= basename($_SERVER['PHP_SELF']) === 'panel_admin.php' ? 'active' : '' ?>">Dashboard</a>
                <a href="<?= url('tareas.php') ?>" class="<?= basename($_SERVER['PHP_SELF']) === 'tareas.php' || strpos(basename($_SERVER['PHP_SELF']), 'tarea_') === 0 ? 'active' : '' ?>">
                    Tareas
                    <?php
                    $pendientes = 0;
                    try {
                        $cntPdo = obtenerConexion();
                        $cntStmt = $cntPdo->query("SELECT COUNT(*) AS c FROM tareas WHERE estado IN ('pendiente','en_progreso')");
                        $pendientes = (int) $cntStmt->fetch()['c'];
                    } catch (Exception $e) {}
                    if ($pendientes > 0): ?>
                        <span class="nav-badge"><?= $pendientes > 99 ? '99+' : $pendientes ?></span>
                    <?php endif; ?>
                </a>
                <a href="<?= url('reportes.php') ?>" class="<?= basename($_SERVER['PHP_SELF']) === 'reportes.php' ? 'active' : '' ?>">Reportes</a>
            <?php endif; ?>
            <?php if ($_SESSION['rol'] === 'admin'): ?>
                <div class="nav-dropdown" id="adminDropdown">
                    <button class="nav-dropdown-btn" id="adminDropdownBtn">Admin ▾</button>
                    <div class="nav-dropdown-menu" id="adminDropdownMenu">
                        <a href="<?= url('admin/usuarios.php') ?>" class="<?= basename($_SERVER['PHP_SELF']) === 'usuarios.php' || basename($_SERVER['PHP_SELF']) === 'usuario_editar.php' ? 'active' : '' ?>">Usuarios</a>
                        <a href="<?= url('admin/categorias.php') ?>" class="<?= basename($_SERVER['PHP_SELF']) === 'categorias.php' ? 'active' : '' ?>">Categorias</a>
                        <a href="<?= url('admin/etiquetas.php') ?>" class="<?= basename($_SERVER['PHP_SELF']) === 'etiquetas.php' ? 'active' : '' ?>">Etiquetas</a>
                    </div>
                </div>
            <?php endif; ?>
        </nav>
        <div class="header-actions">
            <button class="theme-toggle" id="themeToggle" aria-label="Cambiar tema">
                <svg id="themeIcon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/>
                </svg>
            </button>
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
                                <a href="<?= url('ver_ticket.php?id=' . (int) $notif['id']) ?>" class="notification-link">
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
            <a href="<?= url('perfil.php') ?>" class="user-name"><?= htmlspecialchars($_SESSION['nombre'] ?? '') ?></a>
            <a href="<?= url('logout.php') ?>" class="btn btn-outline btn-sm">Salir</a>
        </div>
    </div>
</header>
<?php endif; ?>
<div class="toast-container" id="toastContainer"></div>
<main class="page-wrapper">
