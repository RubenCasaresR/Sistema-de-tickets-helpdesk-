<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/includes/config.php';
define('DB_CHARSET', 'utf8mb4');

function obtenerConexion(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_NAME,
            DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log('Error de conexion PDO: ' . $e->getMessage());
            die('Error de conexion a la base de datos. Intente mas tarde.');
        }
    }

    return $pdo;
}

// ── Query helpers ──

function buildTicketFilters(array $allowed): array {
    $filters = [];
    $params = [];
    $estado = $_GET['estado'] ?? '';
    $prioridad = $_GET['prioridad'] ?? '';
    $categoria = (int) ($_GET['categoria_id'] ?? 0);

    if (in_array('estado', $allowed) && $estado !== '' && in_array($estado, ['abierto','en_progreso','resuelto','cerrado'], true)) {
        $filters[] = 't.estado = :estado';
        $params[':estado'] = $estado;
    }
    if (in_array('prioridad', $allowed) && $prioridad !== '' && in_array($prioridad, ['baja','media','alta','urgente'], true)) {
        $filters[] = 't.prioridad = :prioridad';
        $params[':prioridad'] = $prioridad;
    }
    if (in_array('categoria', $allowed) && $categoria > 0) {
        $filters[] = 't.categoria_id = :categoria_id';
        $params[':categoria_id'] = $categoria;
    }
    return [$filters, $params];
}

// ── Ticket historial ──

function obtenerHistorialTicket(PDO $pdo, int $ticket_id): array {
    $stmt = $pdo->prepare('
        SELECT h.*, u.nombre
        FROM ticket_historial h
        JOIN usuarios u ON u.id = h.usuario_id
        WHERE h.ticket_id = :ticket_id
        ORDER BY h.fecha_creacion DESC
        LIMIT 50
    ');
    $stmt->execute([':ticket_id' => $ticket_id]);
    return $stmt->fetchAll();
}

function registrarHistorialTicket(PDO $pdo, int $ticket_id, int $usuario_id, string $accion, ?string $detalle = null): void {
    $stmt = $pdo->prepare('
        INSERT INTO ticket_historial (ticket_id, usuario_id, accion, detalle)
        VALUES (:ticket_id, :usuario_id, :accion, :detalle)
    ');
    $stmt->execute([
        ':ticket_id'  => $ticket_id,
        ':usuario_id' => $usuario_id,
        ':accion'     => $accion,
        ':detalle'    => $detalle,
    ]);
}
// ── Report helpers (DRY) ──

function buildReportFilters(array &$conditions, array &$params): void
{
    $filtro_tipo    = $_GET['tipo'] ?? 'todos';
    $filtro_desde   = $_GET['desde'] ?? '';
    $filtro_hasta   = $_GET['hasta'] ?? '';
    $filtro_asignado = $_GET['asignado_id'] ?? '';

    if ($filtro_tipo === 'activos') {
        $conditions[] = "t.estado IN ('abierto', 'en_progreso', 'resuelto')";
    } elseif ($filtro_tipo === 'cerrados') {
        $conditions[] = "t.estado = 'cerrado'";
    }

    if ($filtro_desde !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filtro_desde)) {
        $conditions[] = 't.fecha_creacion >= :desde';
        $params[':desde'] = $filtro_desde . ' 00:00:00';
    }
    if ($filtro_hasta !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filtro_hasta)) {
        $conditions[] = 't.fecha_creacion <= :hasta';
        $params[':hasta'] = $filtro_hasta . ' 23:59:59';
    }
    if ($filtro_asignado !== '' && $filtro_asignado !== '0') {
        $conditions[] = 't.asignado_id = :asignado_id';
        $params[':asignado_id'] = (int) $filtro_asignado;
    }
}

// ── Rate limiting ──

function verificarRateLimit(string $tipo, string $identificador, int $maxIntentos = 5, int $ventanaMinutos = 15): bool
{
    $pdo = obtenerConexion();
    $limite = date('Y-m-d H:i:s', strtotime("-{$ventanaMinutos} minutes"));
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE tipo = :tipo AND (email = :identificador OR ip_address = :ip) AND attempted_at >= :limite');
    $stmt->execute([
        ':tipo' => $tipo,
        ':identificador' => $identificador,
        ':ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
        ':limite' => $limite,
    ]);
    return (int) $stmt->fetchColumn() < $maxIntentos;
}

function registrarIntentoFallido(string $email = '', string $tipo = 'login'): void
{
    try {
        $pdo = obtenerConexion();
        $ins = $pdo->prepare('INSERT INTO login_attempts (ip_address, email, tipo) VALUES (:ip, :email, :tipo)');
        $ins->execute([
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            ':email' => $email,
            ':tipo' => $tipo,
        ]);
    } catch (PDOException $e) {
        error_log('Error al registrar intento: ' . $e->getMessage());
    }
}

// ── Cleanup expired tokens ──

function limpiarTokensExpirados(): void
{
    try {
        $pdo = obtenerConexion();
        $pdo->exec("DELETE FROM password_resets WHERE expira_en < NOW()");
    } catch (PDOException $e) {
        error_log('Error limpiando tokens: ' . $e->getMessage());
    }
}

// ── HTML sanitizer (HTMLPurifier) ──

function sanitizarDescripcion(string $html, string $extraTags = ''): string {
    static $purifier = null;
    if ($purifier === null) {
        $config = HTMLPurifier_Config::createDefault();
        $allowed = 'p,br,strong,em,u,s,ul,ol,li,blockquote,pre,code,a[href|title],'
                 . 'h1,h2,h3,span[style],sub,sup';
        if ($extraTags !== '') {
            $allowed .= ',' . $extraTags;
        }
        $config->set('HTML.Allowed', $allowed);
        $config->set('HTML.TargetBlank', true);
        $config->set('Attr.AllowedFrameTargets', ['_blank']);
        $config->set('Attr.EnableID', false);
        $config->set('CSS.AllowedProperties', 'text-decoration,color,background-color');
        $cacheDir = __DIR__ . '/cache';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }
        $config->set('Cache.SerializerPath', $cacheDir);
        $purifier = new HTMLPurifier($config);
    }
    return $purifier->purify($html);
}
