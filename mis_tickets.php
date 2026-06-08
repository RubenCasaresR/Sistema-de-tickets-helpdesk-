<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/conexion.php';
requiereRol(['cliente']);

$pdo = obtenerConexion();

$success = '';
if (isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

$porPagina = 12;
$pagina = max(1, (int) ($_GET['p'] ?? 1));
$offset = ($pagina - 1) * $porPagina;

$countStmt = $pdo->prepare('SELECT COUNT(*) FROM tickets WHERE creador_id = :creador_id');
$countStmt->execute([':creador_id' => $_SESSION['usuario_id']]);
$totalTickets = (int) $countStmt->fetchColumn();
$totalPaginas = max(1, (int) ceil($totalTickets / $porPagina));

if ($pagina > $totalPaginas) {
    $pagina = $totalPaginas;
    $offset = ($pagina - 1) * $porPagina;
}

$stmt = $pdo->prepare('
    SELECT t.id, t.folio, t.titulo, t.estado, t.prioridad, t.fecha_creacion, t.categoria_id,
           u.nombre AS asignado_nombre,
           cat.nombre AS categoria_nombre
    FROM tickets t
    LEFT JOIN usuarios u ON u.id = t.asignado_id
    LEFT JOIN categorias cat ON cat.id = t.categoria_id
    WHERE t.creador_id = :creador_id
    ORDER BY FIELD(t.prioridad, \'urgente\', \'alta\', \'media\', \'baja\'),
             t.fecha_creacion DESC
    LIMIT :limite OFFSET :offset
');
$stmt->bindValue(':creador_id', $_SESSION['usuario_id'], PDO::PARAM_INT);
$stmt->bindValue(':limite', $porPagina, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$tickets = $stmt->fetchAll();
$page_title = 'Mis Tickets';
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="page-header flex-between">
    <div>
        <h1>Mis Tickets</h1>
        <p>Estos son todos los tickets que has creado.</p>
    </div>
    <a href="<?= url('crear_ticket.php') ?>" class="btn btn-primary">+ Nuevo Ticket</a>
</div>

<?php if ($success !== ''): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<?php if (count($tickets) === 0): ?>
    <div class="card">
        <div class="card-body">
            <div class="text-center" style="padding:48px 24px">
                <p class="text-muted">No has creado ningun ticket todavia.</p>
                <a href="<?= url('crear_ticket.php') ?>" class="btn btn-primary mt-4">Crear mi primer ticket</a>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="ticket-grid">
        <?php foreach ($tickets as $ticket): ?>
            <div class="ticket-grid-card">
                <div class="ticket-card-header">
                    <span class="badge badge-<?= htmlspecialchars($ticket['estado']) ?>">
                        <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $ticket['estado']))) ?>
                    </span>
                    <span class="badge badge-<?= htmlspecialchars($ticket['prioridad']) ?>">
                        <?= htmlspecialchars(ucfirst($ticket['prioridad'])) ?>
                    </span>
                    <?php if (!empty($ticket['categoria_nombre'])): ?>
                        <span class="badge badge-cerrado text-small"><?= htmlspecialchars($ticket['categoria_nombre']) ?></span>
                    <?php endif; ?>
                </div>
                <div class="ticket-card-body">
                    <div class="ticket-card-folio"><?= htmlspecialchars($ticket['folio']) ?></div>
                    <div class="ticket-card-title"><?= htmlspecialchars($ticket['titulo']) ?></div>
                    <div class="ticket-card-meta">
                        <span>Asignado: <?= htmlspecialchars($ticket['asignado_nombre'] ?? '—') ?></span>
                        <span><?= htmlspecialchars(date('d/m/Y H:i', strtotime($ticket['fecha_creacion']))) ?></span>
                    </div>
                </div>
                <div class="ticket-card-footer">
                    <a href="<?= url('ver_ticket.php?id=' . (int) $ticket['id']) ?>" class="btn btn-outline btn-sm">Ver detalle</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($totalPaginas > 1): ?>
    <div class="pagination">
        <?php if ($pagina > 1): ?>
            <a href="?p=<?= $pagina - 1 ?>" class="pagination-btn">&laquo; Anterior</a>
        <?php endif; ?>

        <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
            <a href="?p=<?= $i ?>" class="pagination-btn <?= $i === $pagina ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>

        <?php if ($pagina < $totalPaginas): ?>
            <a href="?p=<?= $pagina + 1 ?>" class="pagination-btn">Siguiente &raquo;</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
