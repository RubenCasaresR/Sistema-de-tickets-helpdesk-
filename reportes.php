<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/conexion.php';
requiereRol(['soporte', 'admin']);

$pdo = obtenerConexion();

$filtro_tipo      = $_GET['tipo'] ?? 'todos';
$filtro_desde      = $_GET['desde'] ?? '';
$filtro_hasta      = $_GET['hasta'] ?? '';
$filtro_asignado   = $_GET['asignado_id'] ?? '';

$staffStmt = $pdo->query("SELECT id, nombre FROM usuarios WHERE rol IN ('soporte', 'admin') ORDER BY nombre");
$personal_staff = $staffStmt->fetchAll();

$tickets = [];
$comentarios_por_ticket = [];

$generado = $_SERVER['REQUEST_METHOD'] === 'GET' && (isset($_GET['tipo']) || isset($_GET['desde']) || isset($_GET['hasta']) || isset($_GET['asignado_id']));

if ($generado) {
    $conditions = [];
    $params = [];
    buildReportFilters($conditions, $params);

    $sql = '
        SELECT t.*, c.nombre AS creador_nombre, a.nombre AS asignado_nombre
        FROM tickets t
        JOIN usuarios c ON c.id = t.creador_id
        LEFT JOIN usuarios a ON a.id = t.asignado_id
    ';
    if (count($conditions) > 0) {
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
    }
    $sql .= ' ORDER BY t.fecha_creacion DESC';

    // Count total for pagination
    $countSql = 'SELECT COUNT(*) AS cnt FROM (' . $sql . ') AS sub';
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalTickets = (int) $countStmt->fetch()['cnt'];
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $per_page = 20;
    $totalPages = max(1, (int) ceil($totalTickets / $per_page));

    $pagedSql = $sql . ' LIMIT :limit OFFSET :offset';
    $stmt = $pdo->prepare($pagedSql);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', ($page - 1) * $per_page, PDO::PARAM_INT);
    $stmt->execute();
    $tickets = $stmt->fetchAll();

    // Batch obtener comentarios de todos los tickets
    if (count($tickets) > 0) {
        $ids = array_column($tickets, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $comStmt = $pdo->prepare("
            SELECT ct.*, u.nombre, u.rol
            FROM comentarios_ticket ct
            JOIN usuarios u ON u.id = ct.usuario_id
            WHERE ct.ticket_id IN ($placeholders)
            ORDER BY ct.ticket_id, ct.fecha ASC
        ");
        $comStmt->execute($ids);
        $comentarios = $comStmt->fetchAll();

        foreach ($comentarios as $com) {
            $comentarios_por_ticket[$com['ticket_id']][] = $com;
        }
    }
}

$csrf_token = generarTokenCSRF();
$page_title = 'Reportes';
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="page-header flex-between" id="reportHeader">
    <div>
        <h1>Reportes</h1>
        <p>Genera reportes detallados de tickets con el historial de avances.</p>
    </div>
    <?php if ($generado && count($tickets) > 0):
        $pdf_params = $_GET;
        $pdf_url = url('generar_pdf.php?' . http_build_query($pdf_params));
    ?>
        <a href="<?= htmlspecialchars($pdf_url) ?>" class="btn btn-primary" target="_blank">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:4px"><path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 14h12v8H6z"/></svg>
            Descargar PDF
        </a>
        <?php
        $csv_params = $_GET;
        $csv_url = url('generar_csv.php?' . http_build_query($csv_params));
        ?>
        <a href="<?= htmlspecialchars($csv_url) ?>" class="btn btn-outline" style="margin-left:8px">
            CSV
        </a>
    <?php endif; ?>
</div>

<!-- Filters -->
<div class="card mb-6 no-print">
    <div class="card-body">
        <form method="get" action="" class="flex flex-between gap-4" style="flex-wrap:wrap">
            <div class="flex gap-4" style="flex-wrap:wrap">
                <div class="form-group" style="margin-bottom:0;min-width:140px">
                    <select name="tipo" class="form-control">
                        <option value="todos" <?= $filtro_tipo === 'todos' ? 'selected' : '' ?>>Todos los estados</option>
                        <option value="activos" <?= $filtro_tipo === 'activos' ? 'selected' : '' ?>>Activos</option>
                        <option value="cerrados" <?= $filtro_tipo === 'cerrados' ? 'selected' : '' ?>>Cerrados</option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:0;min-width:140px">
                    <input type="date" id="desde" name="desde" class="form-control" value="<?= htmlspecialchars($filtro_desde) ?>" placeholder="Desde">
                </div>
                <div class="form-group" style="margin-bottom:0;min-width:140px">
                    <input type="date" id="hasta" name="hasta" class="form-control" value="<?= htmlspecialchars($filtro_hasta) ?>" placeholder="Hasta">
                </div>
                <div class="form-group" style="margin-bottom:0;min-width:160px">
                    <select name="asignado_id" class="form-control">
                        <option value="">Todos los agentes</option>
                        <?php foreach ($personal_staff as $staff): ?>
                            <option value="<?= (int) $staff['id'] ?>" <?= $filtro_asignado === (string) $staff['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($staff['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary btn-sm">Generar Reporte</button>
                <?php if ($generado): ?>
                    <a href="<?= url('reportes.php') ?>" class="btn btn-outline btn-sm">Limpiar</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?php if (!$generado): ?>
    <div class="text-center p-6">
        <p class="text-muted text-lg">Selecciona los filtros y haz clic en <strong>Generar Reporte</strong> para ver los resultados.</p>
    </div>
<?php elseif (count($tickets) === 0): ?>
    <div class="text-center p-5">
        <p class="text-muted">No se encontraron tickets con los filtros seleccionados.</p>
    </div>
<?php else: ?>
    <!-- Report Header (solo visible en PDF) -->
    <div class="report-print-header">
        <h2>Reporte de Tickets</h2>
        <p>
            Generado el <?= date('d/m/Y H:i') ?> &mdash;
            <?php
            $filtros_aplicados = [];
            $tipos = ['todos' => 'Todos los estados', 'activos' => 'Activos', 'cerrados' => 'Cerrados'];
            $filtros_aplicados[] = $tipos[$filtro_tipo] ?? 'Todos';
            if ($filtro_desde !== '') $filtros_aplicados[] = 'Desde: ' . $filtro_desde;
            if ($filtro_hasta !== '') $filtros_aplicados[] = 'Hasta: ' . $filtro_hasta;
            if ($filtro_asignado !== '' && $filtro_asignado !== '0') {
                foreach ($personal_staff as $s) {
                    if ((string) $s['id'] === $filtro_asignado) {
                        $filtros_aplicados[] = 'Agente: ' . $s['nombre'];
                        break;
                    }
                }
            }
            echo htmlspecialchars(implode(' | ', $filtros_aplicados));
            ?>
        </p>
    </div>

    <div class="report-summary">
        <div class="stat-card">
            <div class="stat-number"><?= count($tickets) ?></div>
            <div class="stat-label">Total Tickets</div>
        </div>
        <?php
        $con_avances = 0;
        foreach ($tickets as $t) {
            if (!empty($comentarios_por_ticket[$t['id']])) $con_avances++;
        }
        ?>
        <div class="stat-card">
            <div class="stat-number"><?= $con_avances ?></div>
            <div class="stat-label">Con Avances</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= count($tickets) - $con_avances ?></div>
            <div class="stat-label">Sin Avances</div>
        </div>
    </div>

    <!-- Tickets -->
    <div class="report-tickets">
        <?php foreach ($tickets as $ticket): ?>
            <div class="card report-ticket-card mb-4">
                <div class="card-header">
                    <div class="flex gap-4" style="align-items:center">
                        <strong style="font-size:0.95rem"><?= htmlspecialchars($ticket['folio']) ?></strong>
                        <span class="badge badge-<?= htmlspecialchars($ticket['estado']) ?>">
                            <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $ticket['estado']))) ?>
                        </span>
                        <span class="badge badge-<?= htmlspecialchars($ticket['prioridad']) ?>">
                            <?= htmlspecialchars(ucfirst($ticket['prioridad'])) ?>
                        </span>
                    </div>
                    <span class="text-muted text-small"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($ticket['fecha_creacion']))) ?></span>
                </div>
                <div class="card-body">
                    <h3 style="margin-bottom:8px"><?= htmlspecialchars($ticket['titulo']) ?></h3>
                    <div class="report-meta">
                        <span><strong>Creador:</strong> <?= htmlspecialchars($ticket['creador_nombre']) ?></span>
                        <span><strong>Asignado:</strong> <?= htmlspecialchars($ticket['asignado_nombre'] ?? '—') ?></span>
                        <?php if ($ticket['fecha_cierre']): ?>
                            <span><strong>Cerrado:</strong> <?= htmlspecialchars(date('d/m/Y H:i', strtotime($ticket['fecha_cierre']))) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="report-description">
                        <?= sanitizarDescripcion($ticket['descripcion']) ?>
                    </div>

                    <!-- Avances / Comentarios -->
                    <div class="report-avances">
                        <h4>Avances registrados</h4>
                        <?php $avances = $comentarios_por_ticket[$ticket['id']] ?? []; ?>
                        <?php if (count($avances) === 0): ?>
                            <p class="text-muted text-small">Sin avances registrados.</p>
                        <?php else: ?>
                            <?php foreach ($avances as $com): ?>
                                <div class="report-avance">
                                    <div class="report-avance-meta">
                                        <strong><?= htmlspecialchars($com['nombre']) ?></strong>
                                        <span class="text-muted text-small"><?= htmlspecialchars($com['rol']) ?></span>
                                        <span class="text-muted text-small"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($com['fecha']))) ?></span>
                                    </div>
                                    <div class="report-avance-text"><?= sanitizarDescripcion($com['mensaje']) ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
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
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
