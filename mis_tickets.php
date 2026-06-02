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

$stmt = $pdo->prepare('
    SELECT t.id, t.folio, t.titulo, t.estado, t.prioridad, t.fecha_creacion,
           u.nombre AS asignado_nombre
    FROM tickets t
    LEFT JOIN usuarios u ON u.id = t.asignado_id
    WHERE t.creador_id = :creador_id
    ORDER BY FIELD(t.prioridad, \'urgente\', \'alta\', \'media\', \'baja\'),
             t.fecha_creacion DESC
');
$stmt->execute([':creador_id' => $_SESSION['usuario_id']]);
$tickets = $stmt->fetchAll();
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="page-header flex-between">
    <div>
        <h1>Mis Tickets</h1>
        <p>Estos son todos los tickets que has creado.</p>
    </div>
    <a href="/helpdesk/crear_ticket.php" class="btn btn-primary">+ Nuevo Ticket</a>
</div>

<?php if ($success !== ''): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-body" style="padding:0">
        <?php if (count($tickets) === 0): ?>
            <div class="text-center" style="padding:48px 24px">
                <p class="text-muted">No has creado ningún ticket todavía.</p>
                <a href="/helpdesk/crear_ticket.php" class="btn btn-primary mt-4">Crear mi primer ticket</a>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Folio</th>
                            <th>Título</th>
                            <th>Estado</th>
                            <th>Prioridad</th>
                            <th>Asignado</th>
                            <th>Fecha</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tickets as $ticket): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($ticket['folio']) ?></strong></td>
                                <td><?= htmlspecialchars($ticket['titulo']) ?></td>
                                <td>
                                    <span class="badge badge-<?= htmlspecialchars($ticket['estado']) ?>">
                                        <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $ticket['estado']))) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="priority-indicator">
                                        <span class="priority-dot <?= htmlspecialchars($ticket['prioridad']) ?>"></span>
                                        <span class="badge badge-<?= htmlspecialchars($ticket['prioridad']) ?>">
                                            <?= htmlspecialchars(ucfirst($ticket['prioridad'])) ?>
                                        </span>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($ticket['asignado_nombre'] ?? '—') ?></td>
                                <td class="text-muted text-small"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($ticket['fecha_creacion']))) ?></td>
                                <td><a href="/helpdesk/ver_ticket.php?id=<?= (int) $ticket['id'] ?>" class="btn btn-outline btn-sm">Ver</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
