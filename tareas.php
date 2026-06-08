<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/tareas_helper.php';
requiereAutenticacion();

$is_staff = in_array($_SESSION['rol'], ['soporte', 'admin'], true);
if (!$is_staff) {
    redirect('index.php');
    exit;
}

$pdo = obtenerConexion();
$error = '';
$success = '';

if (isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $error = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// POST: eliminar tarea
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'eliminar_tarea') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    $tarea_id = (int) ($_POST['tarea_id'] ?? 0);
    if (validarTokenCSRF($csrf_token) && $tarea_id > 0) {
        $pdo->prepare('DELETE FROM tareas WHERE id = :id')->execute([':id' => $tarea_id]);
        $_SESSION['success_message'] = 'Tarea eliminada.';
    }
    redirect('tareas.php');
    exit;
}

$csrf_token = generarTokenCSRF();

// View mode (cookie-based for persistence)
$vista = $_GET['vista'] ?? ($_COOKIE['tareas_vista'] ?? 'kanban');
if (!in_array($vista, ['kanban', 'tabla', 'calendario'])) $vista = 'kanban';
setcookie('tareas_vista', $vista, time() + 86400 * 30, BASE_URL . '/');

// Filters
$filtros = [];
$filtro_estado = $_GET['estado'] ?? '';
$filtro_prioridad = $_GET['prioridad'] ?? '';
$filtro_asignado = (int) ($_GET['asignado'] ?? 0);
$filtro_etiqueta = (int) ($_GET['etiqueta'] ?? 0);
$filtro_busqueda = trim($_GET['busqueda'] ?? '');

if ($filtro_estado !== '') $filtros['estado'] = $filtro_estado;
if ($filtro_prioridad !== '') $filtros['prioridad'] = $filtro_prioridad;
if ($filtro_asignado > 0) $filtros['asignado_id'] = $filtro_asignado;
if ($filtro_etiqueta > 0) $filtros['etiqueta_id'] = $filtro_etiqueta;
if ($filtro_busqueda !== '') $filtros['busqueda'] = $filtro_busqueda;

// Build query string for view tabs (preserve filters, change view)
$queryParams = $_GET;
unset($queryParams['vista']);
$queryStringView = http_build_query($queryParams);

// Calendar month
$cal_anio = (int) ($_GET['anio'] ?? date('Y'));
$cal_mes = (int) ($_GET['mes'] ?? date('m'));
if ($cal_mes < 1) { $cal_mes = 12; $cal_anio--; }
if ($cal_mes > 12) { $cal_mes = 1; $cal_anio++; }

$stats = obtenerStatsTareas($pdo);
$personal = obtenerPersonalStaff($pdo);
$todasEtiquetas = obtenerTodasEtiquetas($pdo);

// Get tasks grouped by estado for Kanban
$tareasPorEstado = [];
$etiquetasPorTarea = [];
if ($vista === 'kanban') {
    foreach (['pendiente', 'en_progreso', 'en_revision', 'completada'] as $est) {
        $f = $filtros;
        $f['estado'] = $est;
        $tareasPorEstado[$est] = obtenerTareas($pdo, $f);
    }
    $all_ids = [];
    foreach ($tareasPorEstado as $cards) {
        foreach ($cards as $c) $all_ids[] = (int) $c['id'];
    }
    $etiquetasPorTarea = obtenerEtiquetasParaTareas($pdo, $all_ids);
}

// Get flat list for tabla view (with pagination)
$tareasLista = [];
$totalTareas = 0;
$totalPaginas = 1;
$pagina = 1;
if ($vista === 'tabla') {
    $porPagina = 20;
    $pagina = max(1, (int) ($_GET['p'] ?? 1));
    $offset = ($pagina - 1) * $porPagina;

    $totalTareas = contarTareas($pdo, $filtros);
    $totalPaginas = max(1, (int) ceil($totalTareas / $porPagina));

    if ($pagina > $totalPaginas) {
        $pagina = $totalPaginas;
        $offset = ($pagina - 1) * $porPagina;
    }

    $tareasLista = obtenerTareas($pdo, $filtros, $porPagina, $offset);
    $all_ids = array_map(fn($t) => (int) $t['id'], $tareasLista);
    $etiquetasPorTarea = obtenerEtiquetasParaTareas($pdo, $all_ids);
}

// Get calendar data
$tareasCalendario = [];
if ($vista === 'calendario') {
    $tareasCalendario = obtenerTareasMes($pdo, $cal_anio, $cal_mes);
}
$page_title = 'Gestor de Tareas';
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="page-header flex-between">
    <div>
        <h1>Gestor de Tareas</h1>
        <p>Organiza y da seguimiento al trabajo del equipo</p>
    </div>
    <a href="<?= url('tarea_form.php') ?>" class="btn btn-primary">+ Nueva Tarea</a>
</div>

<?php if ($success !== ''): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-number"><?= $stats['total'] ?></div>
        <div class="stat-label">Total</div>
    </div>
    <div class="stat-card stat-open">
        <div class="stat-number"><?= $stats['pendiente'] ?></div>
        <div class="stat-label">Pendientes</div>
    </div>
    <div class="stat-card stat-progress">
        <div class="stat-number"><?= $stats['en_progreso'] ?></div>
        <div class="stat-label">En Progreso</div>
    </div>
    <div class="stat-card stat-resolved">
        <div class="stat-number"><?= $stats['completada_hoy'] ?></div>
        <div class="stat-label">Completadas Hoy</div>
    </div>
    <div class="stat-card <?= $stats['vencidas'] > 0 ? 'stat-urgent' : '' ?>">
        <div class="stat-number"><?= $stats['vencidas'] ?></div>
        <div class="stat-label">Vencidas</div>
    </div>
</div>

<!-- Filters bar -->
<div class="card mb-6">
    <div class="card-body">
        <form method="get" action="" class="flex gap-2" style="flex-wrap:wrap;align-items:flex-end">
            <input type="hidden" name="vista" value="<?= htmlspecialchars($vista) ?>">
            <div style="flex:1;min-width:160px">
                <label class="text-small text-muted">Buscar</label>
                <input type="text" name="busqueda" class="form-control" placeholder="Buscar tarea..."
                       value="<?= htmlspecialchars($filtro_busqueda) ?>">
            </div>
            <div style="min-width:130px">
                <label class="text-small text-muted">Estado</label>
                <select name="estado" class="form-control">
                    <option value="">Todos</option>
                    <option value="pendiente" <?= $filtro_estado === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                    <option value="en_progreso" <?= $filtro_estado === 'en_progreso' ? 'selected' : '' ?>>En Progreso</option>
                    <option value="en_revision" <?= $filtro_estado === 'en_revision' ? 'selected' : '' ?>>En Revision</option>
                    <option value="completada" <?= $filtro_estado === 'completada' ? 'selected' : '' ?>>Completada</option>
                    <option value="cancelada" <?= $filtro_estado === 'cancelada' ? 'selected' : '' ?>>Cancelada</option>
                </select>
            </div>
            <div style="min-width:130px">
                <label class="text-small text-muted">Prioridad</label>
                <select name="prioridad" class="form-control">
                    <option value="">Todas</option>
                    <option value="baja" <?= $filtro_prioridad === 'baja' ? 'selected' : '' ?>>Baja</option>
                    <option value="media" <?= $filtro_prioridad === 'media' ? 'selected' : '' ?>>Media</option>
                    <option value="alta" <?= $filtro_prioridad === 'alta' ? 'selected' : '' ?>>Alta</option>
                    <option value="urgente" <?= $filtro_prioridad === 'urgente' ? 'selected' : '' ?>>Urgente</option>
                </select>
            </div>
            <div style="min-width:150px">
                <label class="text-small text-muted">Asignado</label>
                <select name="asignado" class="form-control">
                    <option value="0">Todos</option>
                    <?php foreach ($personal as $p): ?>
                        <option value="<?= (int) $p['id'] ?>" <?= $filtro_asignado === (int) $p['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="min-width:130px">
                <label class="text-small text-muted">Etiqueta</label>
                <select name="etiqueta" class="form-control">
                    <option value="0">Todas</option>
                    <?php foreach ($todasEtiquetas as $et): ?>
                        <option value="<?= (int) $et['id'] ?>" <?= $filtro_etiqueta === (int) $et['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($et['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="text-small text-muted">&nbsp;</label>
                <button type="submit" class="btn btn-primary btn-sm" style="display:flex">Filtrar</button>
            </div>
            <?php if ($filtro_busqueda || $filtro_estado || $filtro_prioridad || $filtro_asignado || $filtro_etiqueta): ?>
            <div>
                <label class="text-small text-muted">&nbsp;</label>
                <a href="<?= url('tareas.php?vista=' . htmlspecialchars($vista)) ?>" class="btn btn-outline btn-sm">Limpiar</a>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- View Tabs -->
<div class="flex gap-2 mb-6">
    <a href="?vista=kanban<?= $queryStringView ? '&' . $queryStringView : '' ?>"
       class="btn <?= $vista === 'kanban' ? 'btn-primary' : 'btn-outline' ?> btn-sm">
        Kanban
    </a>
    <a href="?vista=tabla<?= $queryStringView ? '&' . $queryStringView : '' ?>"
       class="btn <?= $vista === 'tabla' ? 'btn-primary' : 'btn-outline' ?> btn-sm">
        Tabla
    </a>
    <a href="?vista=calendario<?= $queryStringView ? '&' . $queryStringView : '' ?>"
       class="btn <?= $vista === 'calendario' ? 'btn-primary' : 'btn-outline' ?> btn-sm">
        Calendario
    </a>
</div>

<?php if ($vista === 'kanban'): ?>
<!-- ===================== KANBAN VIEW ===================== -->
<div class="task-kanban-board" id="taskKanbanBoard" data-csrf-token="<?= htmlspecialchars($csrf_token) ?>">
    <?php
    $columnas = [
        'pendiente' => ['label' => 'Pendiente', 'icon' => '📋'],
        'en_progreso' => ['label' => 'En Progreso', 'icon' => '⚙️'],
        'en_revision' => ['label' => 'En Revision', 'icon' => '🔍'],
        'completada' => ['label' => 'Completada', 'icon' => '✅'],
    ];
    foreach ($columnas as $est => $col):
        $cards = $tareasPorEstado[$est] ?? [];
    ?>
    <div class="kanban-column task-column" data-estado="<?= $est ?>">
        <div class="kanban-column-header">
            <span><?= $col['icon'] ?> <?= $col['label'] ?></span>
            <span class="badge badge-cerrado"><?= count($cards) ?></span>
        </div>
        <div class="kanban-column-body task-column-body" data-estado="<?= $est ?>">
            <?php foreach ($cards as $tar): ?>
                <div class="task-card" data-task-id="<?= (int) $tar['id'] ?>">
                    <div class="task-card-header">
                        <div class="task-card-priority badge badge-<?= htmlspecialchars($tar['prioridad']) ?>">
                            <?= htmlspecialchars(ucfirst($tar['prioridad'])) ?>
                        </div>
                        <?php if ($tar['fecha_limite']): ?>
                            <span class="task-card-date <?= strtotime($tar['fecha_limite']) < time() && $tar['estado'] !== 'completada' ? 'overdue' : '' ?>">
                                <?= htmlspecialchars(date('d/m', strtotime($tar['fecha_limite']))) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <a href="<?= url('tarea_ver.php?id=' . (int) $tar['id']) ?>" class="task-card-title">
                        <?= htmlspecialchars($tar['titulo']) ?>
                    </a>
                    <div class="task-card-meta">
                        <span><?= htmlspecialchars($tar['asignado_nombre'] ?? 'Sin asignar') ?></span>
                        <?php if ($tar['ticket_folio']): ?>
                            <span class="text-muted" title="<?= htmlspecialchars($tar['ticket_titulo'] ?? '') ?>">[<?= htmlspecialchars(mb_substr($tar['ticket_titulo'] ?? $tar['ticket_folio'], 0, 40)) ?>]</span>
                        <?php endif; ?>
                    </div>
                    <?php
                    $tags = $etiquetasPorTarea[(int) $tar['id']] ?? [];
                    if (count($tags) > 0):
                    ?>
                    <div class="task-card-tags">
                        <?php foreach (array_slice($tags, 0, 3) as $tag): ?>
                            <span class="task-tag-dot" style="background:<?= htmlspecialchars($tag['color']) ?>" title="<?= htmlspecialchars($tag['nombre']) ?>"></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php elseif ($vista === 'tabla'): ?>
<!-- ===================== TABLE VIEW ===================== -->
<div class="card">
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Titulo</th>
                    <th>Estado</th>
                    <th>Prioridad</th>
                    <th>Asignado</th>
                    <th>Etiquetas</th>
                    <th>Ticket</th>
                    <th>Fecha limite</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($tareasLista) === 0): ?>
                    <tr><td colspan="8" class="text-muted text-center">No se encontraron tareas.</td></tr>
                <?php endif; ?>
                <?php foreach ($tareasLista as $tar): ?>
                    <tr>
                        <td>
                            <a href="<?= url('tarea_ver.php?id=' . (int) $tar['id']) ?>" class="fw-600">
                                <?= htmlspecialchars($tar['titulo']) ?>
                            </a>
                        </td>
                        <td><?= renderEstadoBadge($tar['estado']) ?></td>
                        <td><span class="badge badge-<?= htmlspecialchars($tar['prioridad']) ?>"><?= htmlspecialchars(ucfirst($tar['prioridad'])) ?></span></td>
                        <td><?= htmlspecialchars($tar['asignado_nombre'] ?? '—') ?></td>
                        <td>
                            <?php
                            $tags = $etiquetasPorTarea[(int) $tar['id']] ?? [];
                            foreach ($tags as $tag):
                            ?>
                                <span class="task-tag-sm" style="background:<?= htmlspecialchars($tag['color']) ?>20;color:<?= htmlspecialchars($tag['color']) ?>">
                                    <?= htmlspecialchars($tag['nombre']) ?>
                                </span>
                            <?php endforeach; ?>
                        </td>
                        <td>
                            <?php if ($tar['ticket_folio']): ?>
                                <a href="<?= url('ver_ticket.php?id=' . (int) $tar['ticket_id']) ?>" class="text-small" title="<?= htmlspecialchars($tar['ticket_folio']) ?>"><?= htmlspecialchars(mb_substr($tar['ticket_titulo'] ?? $tar['ticket_folio'], 0, 60)) ?></a>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="<?= !empty($tar['fecha_limite']) && strtotime($tar['fecha_limite']) < time() && $tar['estado'] !== 'completada' ? 'text-danger' : '' ?>">
                            <?= !empty($tar['fecha_limite']) ? htmlspecialchars(date('d/m/Y', strtotime($tar['fecha_limite']))) : '—' ?>
                        </td>
                        <td>
                            <div class="flex gap-2">
                                <a href="<?= url('tarea_ver.php?id=' . (int) $tar['id']) ?>" class="btn btn-outline btn-sm">Ver</a>
                                <a href="<?= url('tarea_form.php?id=' . (int) $tar['id']) ?>" class="btn btn-outline btn-sm">Editar</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($totalPaginas > 1): ?>
    <div class="pagination">
        <?php if ($pagina > 1): ?>
            <a href="?vista=tabla&p=<?= $pagina - 1 ?><?= $queryStringView ? '&' . $queryStringView : '' ?>" class="btn btn-outline btn-sm">&laquo;</a>
        <?php endif; ?>
        <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
            <a href="?vista=tabla&p=<?= $i ?><?= $queryStringView ? '&' . $queryStringView : '' ?>" class="btn btn-sm <?= $i === $pagina ? 'btn-primary' : 'btn-outline' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <?php if ($pagina < $totalPaginas): ?>
            <a href="?vista=tabla&p=<?= $pagina + 1 ?><?= $queryStringView ? '&' . $queryStringView : '' ?>" class="btn btn-outline btn-sm">&raquo;</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php else: ?>
<!-- ===================== CALENDAR VIEW ===================== -->
<?php
$primer_dia = mktime(0, 0, 0, $cal_mes, 1, $cal_anio);
$dias_en_mes = (int) date('t', $primer_dia);
$dia_semana_inicio = (int) date('w', $primer_dia);
$nombre_mes = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'][$cal_mes];

// Map tasks to days
$tareasPorDia = [];
foreach ($tareasCalendario as $tc) {
    $dia = (int) date('j', strtotime($tc['fecha_limite']));
    if (!isset($tareasPorDia[$dia])) $tareasPorDia[$dia] = [];
    $tareasPorDia[$dia][] = $tc;
}
?>
<div class="card">
    <div class="card-header">
        <h3>Calendario — <?= $nombre_mes ?> <?= $cal_anio ?></h3>
        <div class="flex gap-2">
            <a href="?vista=calendario&mes=<?= $cal_mes - 1 ?>&anio=<?= $cal_anio ?>" class="btn btn-outline btn-sm">&larr;</a>
            <a href="?vista=calendario" class="btn btn-outline btn-sm">Hoy</a>
            <a href="?vista=calendario&mes=<?= $cal_mes + 1 ?>&anio=<?= $cal_anio ?>" class="btn btn-outline btn-sm">&rarr;</a>
        </div>
    </div>
    <div class="card-body">
        <div class="calendar-grid">
            <div class="calendar-day-header">Dom</div>
            <div class="calendar-day-header">Lun</div>
            <div class="calendar-day-header">Mar</div>
            <div class="calendar-day-header">Mie</div>
            <div class="calendar-day-header">Jue</div>
            <div class="calendar-day-header">Vie</div>
            <div class="calendar-day-header">Sab</div>
            <?php for ($i = 0; $i < $dia_semana_inicio; $i++): ?>
                <div class="calendar-day empty"></div>
            <?php endfor; ?>
            <?php for ($dia = 1; $dia <= $dias_en_mes; $dia++): ?>
                <?php
                $hoy = ($dia === (int) date('j') && $cal_mes === (int) date('m') && $cal_anio === (int) date('Y'));
                $tareas_dia = $tareasPorDia[$dia] ?? [];
                ?>
                <div class="calendar-day <?= $hoy ? 'today' : '' ?> <?= count($tareas_dia) > 0 ? 'has-tasks' : '' ?>">
                    <span class="calendar-day-num"><?= $dia ?></span>
                    <div class="calendar-day-tasks">
                        <?php foreach (array_slice($tareas_dia, 0, 3) as $tc): ?>
                            <a href="<?= url('tarea_ver.php?id=' . (int) $tc['id']) ?>"
                               class="cal-task-link"
                               style="border-left-color:<?= $tc['prioridad'] === 'urgente' ? '#dc2626' : ($tc['prioridad'] === 'alta' ? '#fab1a0' : '#6366f1') ?>"
                               title="<?= htmlspecialchars($tc['titulo']) ?>">
                                <?= htmlspecialchars(mb_substr($tc['titulo'], 0, 20)) ?>
                            </a>
                        <?php endforeach; ?>
                        <?php if (count($tareas_dia) > 3): ?>
                            <span class="cal-task-more">+<?= count($tareas_dia) - 3 ?> mas</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endfor; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
