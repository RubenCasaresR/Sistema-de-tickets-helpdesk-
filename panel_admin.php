<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/conexion.php';
requiereRol(['soporte', 'admin']);

$pdo = obtenerConexion();

// Stats
$stats = [];
$statsQuery = $pdo->query("
    SELECT
        COUNT(*) AS total,
        SUM(estado = 'abierto') AS abiertos,
        SUM(estado = 'en_progreso') AS en_progreso,
        SUM(estado = 'resuelto') AS resueltos,
        SUM(prioridad = 'urgente') AS urgentes
    FROM tickets
");
$stats = $statsQuery->fetch();

$success = '';
if (isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

$filtro_estado = $_GET['estado'] ?? '';
$filtro_prioridad = $_GET['prioridad'] ?? '';

$sql = '
    SELECT t.id, t.folio, t.titulo, t.estado, t.prioridad, t.fecha_creacion,
           c.nombre AS creador_nombre,
           a.nombre AS asignado_nombre
    FROM tickets t
    JOIN usuarios c ON c.id = t.creador_id
    LEFT JOIN usuarios a ON a.id = t.asignado_id
';
$conditions = [];
$params = [];

if ($filtro_estado !== '' && in_array($filtro_estado, ['abierto', 'en_progreso', 'resuelto', 'cerrado'], true)) {
    $conditions[] = 't.estado = :estado';
    $params[':estado'] = $filtro_estado;
}

if ($filtro_prioridad !== '' && in_array($filtro_prioridad, ['baja', 'media', 'alta', 'urgente'], true)) {
    $conditions[] = 't.prioridad = :prioridad';
    $params[':prioridad'] = $filtro_prioridad;
}

if (count($conditions) > 0) {
    $sql .= ' WHERE ' . implode(' AND ', $conditions);
}

$sql .= ' ORDER BY FIELD(t.prioridad, \'urgente\', \'alta\', \'media\', \'baja\'),
                  t.fecha_creacion DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll();
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="page-header">
    <h1>Dashboard</h1>
    <p>Panel de administración de tickets.</p>
</div>

<?php if ($success !== ''): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card stat-open">
        <div class="stat-number"><?= (int) ($stats['abiertos'] ?? 0) ?></div>
        <div class="stat-label">Abiertos</div>
    </div>
    <div class="stat-card stat-progress">
        <div class="stat-number"><?= (int) ($stats['en_progreso'] ?? 0) ?></div>
        <div class="stat-label">En Progreso</div>
    </div>
    <div class="stat-card stat-resolved">
        <div class="stat-number"><?= (int) ($stats['resueltos'] ?? 0) ?></div>
        <div class="stat-label">Resueltos</div>
    </div>
    <div class="stat-card stat-urgent">
        <div class="stat-number"><?= (int) ($stats['urgentes'] ?? 0) ?></div>
        <div class="stat-label">Urgentes</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?= (int) ($stats['total'] ?? 0) ?></div>
        <div class="stat-label">Total</div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-6">
    <div class="card-body">
        <form method="get" action="" class="flex flex-between gap-4" style="flex-wrap:wrap">
            <div class="flex gap-4" style="flex-wrap:wrap">
                <div class="form-group" style="margin-bottom:0;min-width:160px">
                    <select name="estado" class="form-control">
                        <option value="">Todos los estados</option>
                        <option value="abierto" <?= $filtro_estado === 'abierto' ? 'selected' : '' ?>>Abierto</option>
                        <option value="en_progreso" <?= $filtro_estado === 'en_progreso' ? 'selected' : '' ?>>En Progreso</option>
                        <option value="resuelto" <?= $filtro_estado === 'resuelto' ? 'selected' : '' ?>>Resuelto</option>
                        <option value="cerrado" <?= $filtro_estado === 'cerrado' ? 'selected' : '' ?>>Cerrado</option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:0;min-width:160px">
                    <select name="prioridad" class="form-control">
                        <option value="">Todas las prioridades</option>
                        <option value="baja" <?= $filtro_prioridad === 'baja' ? 'selected' : '' ?>>Baja</option>
                        <option value="media" <?= $filtro_prioridad === 'media' ? 'selected' : '' ?>>Media</option>
                        <option value="alta" <?= $filtro_prioridad === 'alta' ? 'selected' : '' ?>>Alta</option>
                        <option value="urgente" <?= $filtro_prioridad === 'urgente' ? 'selected' : '' ?>>Urgente</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-outline btn-sm">Filtrar</button>
                <?php if ($filtro_estado !== '' || $filtro_prioridad !== ''): ?>
                    <a href="/helpdesk/panel_admin.php" class="btn btn-outline btn-sm">Limpiar</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Tickets Table -->
<div class="card">
    <div class="card-body" style="padding:0">
        <?php if (count($tickets) === 0): ?>
            <div class="text-center" style="padding:48px 24px">
                <p class="text-muted">No se encontraron tickets con los filtros seleccionados.</p>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Folio</th>
                            <th>Título</th>
                            <th>Creador</th>
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
                                <td><?= htmlspecialchars($ticket['creador_nombre']) ?></td>
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
