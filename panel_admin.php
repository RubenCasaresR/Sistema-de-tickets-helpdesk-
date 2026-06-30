<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/conexion.php';
requiereRol(['soporte', 'admin']);

$pdo = obtenerConexion();

// Stats
$stats = [];
try {
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
} catch (PDOException $e) {
    error_log('Error obteniendo stats: ' . $e->getMessage());
    $stats = ['total' => 0, 'abiertos' => 0, 'en_progreso' => 0, 'resueltos' => 0, 'urgentes' => 0];
}

$success = '';
if (isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Pending users notification
$pendingUsers = 0;
try {
    $pendingUsers = (int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE activo = 0 OR activo IS NULL")->fetchColumn();
} catch (PDOException $e) {
    $pendingUsers = 0;
}

$filtro_estado = $_GET['estado'] ?? '';
$filtro_prioridad = $_GET['prioridad'] ?? '';
$filtro_categoria = (int) ($_GET['categoria_id'] ?? 0);
$csrf_token = generarTokenCSRF();

$catStmt = $pdo->query("SELECT id, nombre FROM categorias WHERE activo = 1 ORDER BY nombre");
$categorias = $catStmt->fetchAll();

[$conditions, $params] = buildTicketFilters(['estado', 'prioridad', 'categoria']);

$sql = '
    SELECT t.id, t.folio, t.titulo, t.estado, t.prioridad, t.fecha_creacion, t.categoria_id,
           c.nombre AS creador_nombre,
           a.nombre AS asignado_nombre,
           cat.nombre AS categoria_nombre
    FROM tickets t
    JOIN usuarios c ON c.id = t.creador_id
    LEFT JOIN usuarios a ON a.id = t.asignado_id
    LEFT JOIN categorias cat ON cat.id = t.categoria_id
';
if (count($conditions) > 0) {
    $sql .= ' WHERE ' . implode(' AND ', $conditions);
}

$sql .= ' ORDER BY FIELD(t.prioridad, \'urgente\', \'alta\', \'media\', \'baja\'),
                  t.fecha_creacion DESC';

// Paginate: max 50 per estado for the kanban board
$page = max(1, (int) ($_GET['page'] ?? 1));
$per_page = 50;
$offset = ($page - 1) * $per_page;

// Clone query for count (without LIMIT)
$countSql = 'SELECT COUNT(*) AS cnt FROM (' . $sql . ') AS sub';
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalTickets = (int) $countStmt->fetch()['cnt'];
$totalPages = max(1, (int) ceil($totalTickets / $per_page));

$pagedSql = $sql . ' LIMIT :limit OFFSET :offset';
$stmt = $pdo->prepare($pagedSql);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$tickets = $stmt->fetchAll();

// Group by estado for kanban
$tickets_por_estado = ['abierto' => [], 'en_progreso' => [], 'resuelto' => [], 'cerrado' => []];
foreach ($tickets as $t) {
    $tickets_por_estado[$t['estado']][] = $t;
}

// Chart data: tickets por mes (ultimos 6)
$chartMeses = $pdo->query("
    SELECT DATE_FORMAT(fecha_creacion, '%Y-%m') AS mes, COUNT(*) AS total
    FROM tickets
    WHERE fecha_creacion >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY mes
    ORDER BY mes ASC
")->fetchAll();

$chartMesesLabels = [];
$chartMesesData = [];
foreach ($chartMeses as $row) {
    $chartMesesLabels[] = $row['mes'];
    $chartMesesData[] = (int) $row['total'];
}

// Chart data: tickets por estado
$chartEstado = $pdo->query("
    SELECT estado, COUNT(*) AS total FROM tickets GROUP BY estado
")->fetchAll();
$chartEstadoLabels = [];
$chartEstadoData = [];
$chartEstadoColors = [];
$colorMap = [
    'abierto' => '#f59e0b',
    'en_progreso' => '#3b82f6',
    'resuelto' => '#10b981',
    'cerrado' => '#94a3b8',
];
foreach ($chartEstado as $row) {
    $chartEstadoLabels[] = ucfirst(str_replace('_', ' ', $row['estado']));
    $chartEstadoData[] = (int) $row['total'];
    $chartEstadoColors[] = $colorMap[$row['estado']] ?? '#ccc';
}

// Chart data: tickets por prioridad
$chartPrioridad = $pdo->query("
    SELECT prioridad, COUNT(*) AS total FROM tickets GROUP BY prioridad
")->fetchAll();
$chartPrioridadLabels = [];
$chartPrioridadData = [];
$chartPrioridadColors = [];
$prioridadColorMap = [
    'baja' => '#6ee7b7',
    'media' => '#fcd34d',
    'alta' => '#fdba74',
    'urgente' => '#dc2626',
];
foreach ($chartPrioridad as $row) {
    $chartPrioridadLabels[] = ucfirst($row['prioridad']);
    $chartPrioridadData[] = (int) $row['total'];
    $chartPrioridadColors[] = $prioridadColorMap[$row['prioridad']] ?? '#ccc';
}
$page_title = 'Dashboard';
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="page-header flex-between">
    <div>
        <h1>Dashboard</h1>
        <p>Panel de administracion de tickets.</p>
    </div>
    <a href="<?= url('crear_ticket.php') ?>" class="btn btn-primary">+ Nuevo Ticket</a>
</div>

<?php if ($pendingUsers > 0): ?>
    <div class="alert alert-info">
        <strong><?= $pendingUsers ?></strong> usuario(s) pendiente(s) de aprobacion.
        <a href="<?= url('admin/usuarios.php') ?>" style="margin-left:8px">Revisar &rarr;</a>
    </div>
<?php endif; ?>

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
    <div class="stat-card stat-urgent">
        <div class="stat-number"><?= (int) ($stats['urgentes'] ?? 0) ?></div>
        <div class="stat-label">Urgentes</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?= (int) ($stats['total'] ?? 0) ?></div>
        <div class="stat-label">Total</div>
    </div>
</div>

<!-- Charts (collapsible) -->
<details class="card mb-6">
    <summary class="card-header" style="cursor:pointer;user-select:none"><h3>Estadisticas</h3></summary>
    <div class="card-body">
        <div class="charts-grid">
            <div class="card" style="box-shadow:none;padding:0;margin:0">
                <div class="card-header"><h3>Tickets por Mes</h3></div>
                <div class="card-body">
                    <canvas id="chartMeses" height="200"></canvas>
                </div>
            </div>
            <div class="card" style="box-shadow:none;padding:0;margin:0">
                <div class="card-header"><h3>Por Estado</h3></div>
                <div class="card-body">
                    <canvas id="chartEstado" height="200"></canvas>
                </div>
            </div>
            <div class="card" style="box-shadow:none;padding:0;margin:0">
                <div class="card-header"><h3>Por Prioridad</h3></div>
                <div class="card-body">
                    <canvas id="chartPrioridad" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>
</details>

<script>
var chartData = {
    meses: { labels: <?= json_encode($chartMesesLabels) ?>, data: <?= json_encode($chartMesesData) ?> },
    estado: { labels: <?= json_encode($chartEstadoLabels) ?>, data: <?= json_encode($chartEstadoData) ?>, colors: <?= json_encode($chartEstadoColors) ?> },
    prioridad: { labels: <?= json_encode($chartPrioridadLabels) ?>, data: <?= json_encode($chartPrioridadData) ?>, colors: <?= json_encode($chartPrioridadColors) ?> }
};
</script>

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
                <div class="form-group" style="margin-bottom:0;min-width:160px">
                    <select name="categoria_id" class="form-control">
                        <option value="">Todas las categorias</option>
                        <?php foreach ($categorias as $cat): ?>
                            <option value="<?= (int) $cat['id'] ?>" <?= $filtro_categoria === (int) $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:0;min-width:180px">
                    <input type="text" id="buscadorTickets" class="form-control" placeholder="Buscar tickets..." autocomplete="off">
                </div>
                <button type="submit" class="btn btn-outline btn-sm">Filtrar</button>
                <?php if ($filtro_estado !== '' || $filtro_prioridad !== ''): ?>
                    <a href="<?= url('panel_admin.php') ?>" class="btn btn-outline btn-sm">Limpiar</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Skeleton Loader -->
<div id="kanbanSkeleton" class="kanban-board" style="transition:opacity 0.3s ease">
    <div class="kanban-column skeleton-column">
        <div class="skeleton skeleton-text" style="width:60%;height:18px;margin-bottom:16px"></div>
        <div class="skeleton skeleton-card"></div>
        <div class="skeleton skeleton-card"></div>
    </div>
    <div class="kanban-column skeleton-column">
        <div class="skeleton skeleton-text" style="width:70%;height:18px;margin-bottom:16px"></div>
        <div class="skeleton skeleton-card"></div>
        <div class="skeleton skeleton-card"></div>
        <div class="skeleton skeleton-card"></div>
    </div>
    <div class="kanban-column skeleton-column">
        <div class="skeleton skeleton-text" style="width:50%;height:18px;margin-bottom:16px"></div>
        <div class="skeleton skeleton-card"></div>
    </div>
    <div class="kanban-column skeleton-column">
        <div class="skeleton skeleton-text" style="width:40%;height:18px;margin-bottom:16px"></div>
        <div class="skeleton skeleton-card"></div>
    </div>
</div>

<!-- Kanban Board -->
<?php
$estados_config = [
    'abierto'     => ['label' => 'Abierto',     'badge' => 'badge-abierto'],
    'en_progreso' => ['label' => 'En Progreso', 'badge' => 'badge-en_progreso'],
    'resuelto'    => ['label' => 'Resuelto',    'badge' => 'badge-resuelto'],
    'cerrado'     => ['label' => 'Cerrado',     'badge' => 'badge-cerrado'],
];
?>
<div class="kanban-board" id="kanbanBoard" data-csrf-token="<?= htmlspecialchars($csrf_token) ?>" data-user-rol="<?= htmlspecialchars($_SESSION['rol']) ?>" style="display:none">
    <?php foreach ($estados_config as $estado_key => $cfg): ?>
        <div class="kanban-column" data-estado="<?= $estado_key ?>">
            <div class="kanban-column-header">
                <span><?= $cfg['label'] ?></span>
                <span class="badge <?= $cfg['badge'] ?>"><?= count($tickets_por_estado[$estado_key]) ?></span>
            </div>
            <div class="kanban-column-body sortable-container" data-estado="<?= $estado_key ?>">
                <?php if (count($tickets_por_estado[$estado_key]) === 0): ?>
                    <p class="text-muted text-small" style="text-align:center;padding:24px 0">Sin tickets</p>
                <?php else: ?>
                    <?php foreach ($tickets_por_estado[$estado_key] as $ticket): ?>
                        <div class="kanban-card" data-ticket-id="<?= (int) $ticket['id'] ?>">
                            <div class="kanban-card-folio"><?= htmlspecialchars($ticket['folio']) ?></div>
                            <div class="kanban-card-title">
                                <a href="<?= url('ver_ticket.php?id=' . (int) $ticket['id']) ?>" style="color:inherit;text-decoration:none">
                                    <?= htmlspecialchars($ticket['titulo']) ?>
                                </a>
                            </div>
                            <div class="kanban-card-meta">
                                <span><strong>Creador:</strong> <?= htmlspecialchars($ticket['creador_nombre']) ?></span>
                                <span><strong>Asignado:</strong> <?= htmlspecialchars($ticket['asignado_nombre'] ?? '—') ?></span>
                                <?php if (!empty($ticket['categoria_nombre'])): ?>
                                    <span class="badge badge-cerrado text-small" style="font-size:0.7rem"><?= htmlspecialchars($ticket['categoria_nombre']) ?></span>
                                <?php endif; ?>
                                <span class="flex-between">
                                    <span class="text-small"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($ticket['fecha_creacion']))) ?></span>
                                    <span class="badge badge-<?= htmlspecialchars($ticket['prioridad']) ?>"><?= htmlspecialchars(ucfirst($ticket['prioridad'])) ?></span>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<div class="pagination" style="margin-top:24px;display:flex;justify-content:center;gap:4px">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <?php
        $params_url = $_GET;
        $params_url['page'] = $i;
        $qs = http_build_query($params_url);
        ?>
        <a href="?<?= $qs ?>" class="btn btn-sm <?= $i === $page ? 'btn-primary' : 'btn-outline' ?>"><?= $i ?></a>
    <?php endfor; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

