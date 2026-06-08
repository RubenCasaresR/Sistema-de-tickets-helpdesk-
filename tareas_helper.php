<?php
/**
 * Helper functions for the Task Manager module
 */

function obtenerTareas(PDO $pdo, array $filtros = []): array {
    $sql = 'SELECT t.*, c.nombre AS creador_nombre, a.nombre AS asignado_nombre,
                   tk.folio AS ticket_folio, tk.titulo AS ticket_titulo
            FROM tareas t
            JOIN usuarios c ON c.id = t.creador_id
            LEFT JOIN usuarios a ON a.id = t.asignado_id
            LEFT JOIN tickets tk ON tk.id = t.ticket_id
            WHERE 1=1';
    $params = [];

    if (!empty($filtros['estado'])) {
        $sql .= ' AND t.estado = :estado';
        $params[':estado'] = $filtros['estado'];
    }
    if (!empty($filtros['prioridad'])) {
        $sql .= ' AND t.prioridad = :prioridad';
        $params[':prioridad'] = $filtros['prioridad'];
    }
    if (!empty($filtros['asignado_id'])) {
        $sql .= ' AND t.asignado_id = :asignado_id';
        $params[':asignado_id'] = (int) $filtros['asignado_id'];
    }
    if (!empty($filtros['creador_id'])) {
        $sql .= ' AND t.creador_id = :creador_id';
        $params[':creador_id'] = (int) $filtros['creador_id'];
    }
    if (!empty($filtros['busqueda'])) {
        $sql .= ' AND (t.titulo LIKE :busqueda OR t.descripcion LIKE :busqueda2)';
        $params[':busqueda'] = '%' . $filtros['busqueda'] . '%';
        $params[':busqueda2'] = '%' . $filtros['busqueda'] . '%';
    }
    if (!empty($filtros['etiqueta_id'])) {
        $sql .= ' AND t.id IN (SELECT tarea_id FROM tarea_etiquetas WHERE etiqueta_id = :etiqueta_id)';
        $params[':etiqueta_id'] = (int) $filtros['etiqueta_id'];
    }
    if (!empty($filtros['fecha_desde'])) {
        $sql .= ' AND t.fecha_limite >= :fecha_desde';
        $params[':fecha_desde'] = $filtros['fecha_desde'];
    }
    if (!empty($filtros['fecha_hasta'])) {
        $sql .= ' AND t.fecha_limite <= :fecha_hasta';
        $params[':fecha_hasta'] = $filtros['fecha_hasta'];
    }

    $sql .= ' ORDER BY t.fecha_creacion DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function obtenerTarea(PDO $pdo, int $id): ?array {
    $stmt = $pdo->prepare('
        SELECT t.*, c.nombre AS creador_nombre, a.nombre AS asignado_nombre,
               tk.folio AS ticket_folio, tk.titulo AS ticket_titulo
        FROM tareas t
        JOIN usuarios c ON c.id = t.creador_id
        LEFT JOIN usuarios a ON a.id = t.asignado_id
        LEFT JOIN tickets tk ON tk.id = t.ticket_id
        WHERE t.id = :id
    ');
    $stmt->execute([':id' => $id]);
    $tarea = $stmt->fetch();
    return $tarea ?: null;
}

function obtenerSubtareas(PDO $pdo, int $tarea_id): array {
    $stmt = $pdo->prepare('
        SELECT * FROM tarea_subtareas
        WHERE tarea_id = :tarea_id
        ORDER BY orden ASC, id ASC
    ');
    $stmt->execute([':tarea_id' => $tarea_id]);
    return $stmt->fetchAll();
}

function obtenerComentariosTarea(PDO $pdo, int $tarea_id): array {
    $stmt = $pdo->prepare('
        SELECT c.*, u.nombre, u.rol
        FROM tarea_comentarios c
        JOIN usuarios u ON u.id = c.usuario_id
        WHERE c.tarea_id = :tarea_id
        ORDER BY c.fecha_creacion ASC
    ');
    $stmt->execute([':tarea_id' => $tarea_id]);
    return $stmt->fetchAll();
}

function obtenerEtiquetasTarea(PDO $pdo, int $tarea_id): array {
    $stmt = $pdo->prepare('
        SELECT e.* FROM etiquetas e
        JOIN tarea_etiquetas te ON te.etiqueta_id = e.id
        WHERE te.tarea_id = :tarea_id
        ORDER BY e.nombre
    ');
    $stmt->execute([':tarea_id' => $tarea_id]);
    return $stmt->fetchAll();
}

function obtenerEtiquetasParaTareas(PDO $pdo, array $tarea_ids): array {
    if (empty($tarea_ids)) return [];
    $placeholders = [];
    $params = [];
    foreach ($tarea_ids as $i => $id) {
        $key = ":id{$i}";
        $placeholders[] = $key;
        $params[$key] = (int) $id;
    }
    $in = implode(',', $placeholders);
    $stmt = $pdo->prepare("
        SELECT te.tarea_id, e.*
        FROM tarea_etiquetas te
        JOIN etiquetas e ON e.id = te.etiqueta_id
        WHERE te.tarea_id IN ($in)
        ORDER BY e.nombre
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    $result = [];
    foreach ($rows as $row) {
        $tid = (int) $row['tarea_id'];
        if (!isset($result[$tid])) $result[$tid] = [];
        $result[$tid][] = $row;
    }
    return $result;
}

function obtenerTodasEtiquetas(PDO $pdo): array {
    return $pdo->query('SELECT * FROM etiquetas ORDER BY nombre')->fetchAll();
}

function obtenerTiemposTarea(PDO $pdo, int $tarea_id): array {
    $stmt = $pdo->prepare('
        SELECT tt.*, u.nombre
        FROM tarea_tiempo tt
        JOIN usuarios u ON u.id = tt.usuario_id
        WHERE tt.tarea_id = :tarea_id
        ORDER BY tt.fecha_inicio DESC
    ');
    $stmt->execute([':tarea_id' => $tarea_id]);
    return $stmt->fetchAll();
}

function obtenerHistorialTarea(PDO $pdo, int $tarea_id): array {
    $stmt = $pdo->prepare('
        SELECT h.*, u.nombre
        FROM tarea_historial h
        JOIN usuarios u ON u.id = h.usuario_id
        WHERE h.tarea_id = :tarea_id
        ORDER BY h.fecha_creacion DESC
        LIMIT 50
    ');
    $stmt->execute([':tarea_id' => $tarea_id]);
    return $stmt->fetchAll();
}

function registrarHistorial(PDO $pdo, int $tarea_id, int $usuario_id, string $accion, ?string $detalle = null): void {
    $stmt = $pdo->prepare('
        INSERT INTO tarea_historial (tarea_id, usuario_id, accion, detalle)
        VALUES (:tarea_id, :usuario_id, :accion, :detalle)
    ');
    $stmt->execute([
        ':tarea_id' => $tarea_id,
        ':usuario_id' => $usuario_id,
        ':accion' => $accion,
        ':detalle' => $detalle,
    ]);
}

function obtenerPersonalStaff(PDO $pdo): array {
    return $pdo->query("SELECT id, nombre FROM usuarios WHERE rol IN ('soporte', 'admin') ORDER BY nombre")->fetchAll();
}

function calcularTiempoTotalTarea(PDO $pdo, int $tarea_id): int {
    $stmt = $pdo->prepare('
        SELECT COALESCE(SUM(TIMESTAMPDIFF(SECOND, fecha_inicio, fecha_fin)), 0) AS total
        FROM tarea_tiempo
        WHERE tarea_id = :tarea_id AND fecha_fin IS NOT NULL
    ');
    $stmt->execute([':tarea_id' => $tarea_id]);
    return (int) $stmt->fetch()['total'];
}

function formatoTiempo(int $segundos): string {
    $horas = floor($segundos / 3600);
    $minutos = floor(($segundos % 3600) / 60);
    if ($horas > 0) {
        return "{$horas}h {$minutos}m";
    }
    return "{$minutos}m";
}

function obtenerStatsTareas(PDO $pdo): array {
    $stats = [
        'total' => 0,
        'pendiente' => 0,
        'en_progreso' => 0,
        'en_revision' => 0,
        'completada_hoy' => 0,
        'vencidas' => 0,
    ];

    $res = $pdo->query("SELECT estado, COUNT(*) AS c FROM tareas GROUP BY estado");
    while ($row = $res->fetch()) {
        $stats[$row['estado']] = (int) $row['c'];
        $stats['total'] += (int) $row['c'];
    }

    $hoy = $pdo->prepare("SELECT COUNT(*) AS c FROM tareas WHERE estado = 'completada' AND DATE(fecha_completada) = CURDATE()");
    $hoy->execute();
    $stats['completada_hoy'] = (int) $hoy->fetch()['c'];

    $venc = $pdo->prepare("SELECT COUNT(*) AS c FROM tareas WHERE estado NOT IN ('completada','cancelada') AND fecha_limite IS NOT NULL AND fecha_limite < CURDATE()");
    $venc->execute();
    $stats['vencidas'] = (int) $venc->fetch()['c'];

    return $stats;
}

function obtenerTareasMes(PDO $pdo, int $anio, int $mes): array {
    $stmt = $pdo->prepare("
        SELECT t.id, t.titulo, t.fecha_limite, t.estado, t.prioridad, a.nombre AS asignado_nombre
        FROM tareas t
        LEFT JOIN usuarios a ON a.id = t.asignado_id
        WHERE t.fecha_limite IS NOT NULL
          AND YEAR(t.fecha_limite) = :anio
          AND MONTH(t.fecha_limite) = :mes
        ORDER BY t.fecha_limite ASC
    ");
    $stmt->execute([':anio' => $anio, ':mes' => $mes]);
    return $stmt->fetchAll();
}

function sincronizarEstadoTicketDesdeTarea(PDO $pdo, int $ticket_id, int $usuario_id, string $estado_tarea): void {
    $estado_ticket = null;
    switch ($estado_tarea) {
        case 'en_progreso':
            $estado_ticket = 'en_progreso';
            break;
        case 'pendiente':
            $estado_ticket = 'abierto';
            break;
    }
    if ($estado_ticket === null) return;

    $stmt = $pdo->prepare('SELECT estado FROM tickets WHERE id = :id');
    $stmt->execute([':id' => $ticket_id]);
    $ticket = $stmt->fetch();
    if (!$ticket) return;

    $upd = $pdo->prepare('UPDATE tickets SET estado = :estado WHERE id = :id');
    $upd->execute([':estado' => $estado_ticket, ':id' => $ticket_id]);

    if (function_exists('registrarHistorialTicket')) {
        registrarHistorialTicket($pdo, $ticket_id, $usuario_id, 'estado', "Estado sincronizado a {$estado_ticket} desde tarea vinculada");
    }
}

function renderEstadoBadge(string $estado): string {
    $map = [
        'pendiente' => 'badge-abierto',
        'en_progreso' => 'badge-en_progreso',
        'en_revision' => 'badge-media',
        'completada' => 'badge-resuelto',
        'cancelada' => 'badge-cerrado',
    ];
    $label = str_replace('_', ' ', $estado);
    $class = $map[$estado] ?? 'badge-cerrado';
    return '<span class="badge ' . $class . '">' . htmlspecialchars(ucfirst($label)) . '</span>';
}
