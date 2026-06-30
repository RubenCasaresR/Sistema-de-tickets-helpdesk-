<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/conexion.php';
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}
requiereRol(['soporte', 'admin']);

$pdo = obtenerConexion();

$filtro_tipo      = $_GET['tipo'] ?? 'todos';
$filtro_desde      = $_GET['desde'] ?? '';
$filtro_hasta      = $_GET['hasta'] ?? '';
$filtro_asignado   = $_GET['asignado_id'] ?? '';

define('MAX_PDF_TICKETS', 500);

$staffStmt = $pdo->query("SELECT id, nombre FROM usuarios WHERE rol IN ('soporte', 'admin') ORDER BY nombre");
$personal_staff = $staffStmt->fetchAll();

$conditions = [];
$params = [];
buildReportFilters($conditions, $params);

$countSql = 'SELECT COUNT(*) FROM tickets t JOIN usuarios c ON c.id = t.creador_id LEFT JOIN usuarios a ON a.id = t.asignado_id';
if (count($conditions) > 0) {
    $countSql .= ' WHERE ' . implode(' AND ', $conditions);
}
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalCount = (int) $countStmt->fetchColumn();
$truncated = $totalCount > MAX_PDF_TICKETS;

$sql = '
    SELECT t.*, c.nombre AS creador_nombre, a.nombre AS asignado_nombre
    FROM tickets t
    JOIN usuarios c ON c.id = t.creador_id
    LEFT JOIN usuarios a ON a.id = t.asignado_id
';
if (count($conditions) > 0) {
    $sql .= ' WHERE ' . implode(' AND ', $conditions);
}
$sql .= ' ORDER BY t.fecha_creacion DESC LIMIT ' . MAX_PDF_TICKETS;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll();

$comentarios_por_ticket = [];
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

$con_avances = 0;
foreach ($tickets as $t) {
    if (!empty($comentarios_por_ticket[$t['id']])) $con_avances++;
}

ob_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
    @page { margin: 18mm 14mm 18mm 14mm; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #1a1a2e; line-height: 1.5; }
    h1 { font-size: 16pt; margin: 0 0 4px 0; color: #1a1a2e; }
    .report-meta { font-size: 8pt; color: #6c757d; margin-bottom: 18px; padding-bottom: 10px; border-bottom: 2px solid #e9ecef; }
    .summary-table { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
    .summary-table td { width: 33.33%; text-align: center; padding: 10px; border: 1px solid #e9ecef; font-size: 10pt; }
    .summary-table .num { font-size: 16pt; font-weight: 700; display: block; }
    .summary-table .lbl { font-size: 7.5pt; color: #6c757d; text-transform: uppercase; letter-spacing: 0.04em; }
    .ticket { border: 1px solid #dee2e6; border-radius: 4px; margin-bottom: 14px; page-break-inside: avoid; }
    .ticket-header { background: #f8f9fa; padding: 8px 12px; border-bottom: 1px solid #dee2e6; font-size: 8pt; }
    .ticket-header .folio { font-weight: 700; font-size: 9.5pt; color: #1a1a2e; }
    .ticket-header .badge { display: inline-block; padding: 2px 8px; border-radius: 8px; font-size: 7pt; font-weight: 600; margin-left: 6px; border: 1px solid #ccc; }
    .ticket-header .fecha { float: right; color: #6c757d; }
    .ticket-body { padding: 10px 12px; }
    .ticket-body h3 { font-size: 10pt; margin: 0 0 6px 0; }
    .info-row { font-size: 8pt; color: #6c757d; margin-bottom: 8px; }
    .info-row strong { color: #1a1a2e; }
    .descripcion { font-size: 8.5pt; background: #f8f9fa; padding: 8px 10px; border-radius: 4px; margin-bottom: 10px; white-space: pre-wrap; }
    .avances-section { border-top: 1px solid #e9ecef; padding-top: 8px; }
    .avances-section h4 { font-size: 7.5pt; text-transform: uppercase; letter-spacing: 0.04em; color: #6c757d; margin: 0 0 8px 0; }
    .avance { padding: 6px 0; border-bottom: 1px solid #f1f3f5; font-size: 8pt; }
    .avance:last-child { border-bottom: none; }
    .avance .meta { font-size: 7.5pt; color: #6c757d; margin-bottom: 2px; }
    .avance .meta strong { color: #1a1a2e; }
    .avance .texto { font-size: 8pt; color: #1a1a2e; }
    .sin-avances { font-size: 8pt; color: #adb5bd; font-style: italic; }
    .page-footer { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; font-size: 7pt; color: #adb5bd; padding: 6px 0; }
</style>
</head>
<body>

<h1>Reporte de Tickets</h1>
<div class="report-meta">
    Generado el <?= date('d/m/Y H:i') ?> &mdash; <?= htmlspecialchars(implode(' | ', $filtros_aplicados)) ?><br>
    Total: <?= $totalCount ?> tickets &mdash; <?= $con_avances ?> con avances &mdash; <?= count($tickets) - $con_avances ?> sin avances
    <?php if ($truncated): ?>
    <br><strong style="color:#dc2626;">Nota:</strong> Mostrando solo los primeros <?= MAX_PDF_TICKETS ?> tickets. <?= $totalCount - MAX_PDF_TICKETS ?> tickets no fueron incluidos. Ajuste los filtros para acotar el reporte.
    <?php endif; ?>
</div>

<table class="summary-table">
    <tr>
        <td><span class="num"><?= count($tickets) ?></span><span class="lbl">Total Tickets</span></td>
        <td><span class="num"><?= $con_avances ?></span><span class="lbl">Con Avances</span></td>
        <td><span class="num"><?= count($tickets) - $con_avances ?></span><span class="lbl">Sin Avances</span></td>
    </tr>
</table>

<?php foreach ($tickets as $ticket): ?>
<div class="ticket">
    <div class="ticket-header">
        <span class="folio"><?= htmlspecialchars($ticket['folio']) ?></span>
        <span class="badge"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $ticket['estado']))) ?></span>
        <span class="badge"><?= htmlspecialchars(ucfirst($ticket['prioridad'])) ?></span>
        <span class="fecha"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($ticket['fecha_creacion']))) ?></span>
    </div>
    <div class="ticket-body">
        <h3><?= htmlspecialchars($ticket['titulo']) ?></h3>
        <div class="info-row">
            <strong>Creador:</strong> <?= htmlspecialchars($ticket['creador_nombre']) ?> &nbsp;|&nbsp;
            <strong>Asignado:</strong> <?= htmlspecialchars($ticket['asignado_nombre'] ?? '—') ?>
            <?php if ($ticket['fecha_cierre']): ?>
                &nbsp;|&nbsp; <strong>Cerrado:</strong> <?= htmlspecialchars(date('d/m/Y H:i', strtotime($ticket['fecha_cierre']))) ?>
            <?php endif; ?>
        </div>
        <div class="descripcion"><?= sanitizarDescripcion($ticket['descripcion']) ?></div>

        <div class="avances-section">
            <h4>Avances registrados</h4>
            <?php $avances = $comentarios_por_ticket[$ticket['id']] ?? []; ?>
            <?php if (count($avances) === 0): ?>
                <div class="sin-avances">Sin avances registrados.</div>
            <?php else: ?>
                <?php foreach ($avances as $com): ?>
                    <div class="avance">
                        <div class="meta">
                            <strong><?= htmlspecialchars($com['nombre']) ?></strong>
                            &nbsp;·&nbsp; <?= htmlspecialchars($com['rol']) ?>
                            &nbsp;·&nbsp; <?= htmlspecialchars(date('d/m/Y H:i', strtotime($com['fecha']))) ?>
                        </div>
                        <div class="texto"><?= sanitizarDescripcion($com['mensaje']) ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endforeach; ?>

<div class="page-footer">HelpDesk System — Reporte generado el <?= date('d/m/Y H:i') ?></div>

</body>
</html>
<?php
$html = ob_get_clean();

$mpdf = new \Mpdf\Mpdf([
    'mode' => 'utf-8',
    'format' => 'Letter',
    'margin_left' => 14,
    'margin_right' => 14,
    'margin_top' => 14,
    'margin_bottom' => 20,
]);

$mpdf->SetTitle('Reporte de Tickets - HelpDesk');
$mpdf->SetAuthor('HelpDesk System');
$mpdf->WriteHTML($html);
$mpdf->Output('reporte_tickets.pdf', 'I');

