<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/conexion.php';
requiereAutenticacion();

header('Content-Type: application/json');

$csrf_token = $_POST['csrf_token'] ?? '';
if (!validarTokenCSRF($csrf_token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Token de seguridad invalido.']);
    exit;
}

$ticket_id = (int) ($_POST['ticket_id'] ?? 0);
if ($ticket_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ID de ticket invalido.']);
    exit;
}

// Verificar que el ticket existe
$pdo = obtenerConexion();
$stmt = $pdo->prepare('SELECT id, creador_id FROM tickets WHERE id = :id');
$stmt->execute([':id' => $ticket_id]);
$ticket = $stmt->fetch();

if (!$ticket) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Ticket no encontrado.']);
    exit;
}

// Cliente solo sube a sus propios tickets
if ($_SESSION['rol'] === 'cliente' && (int) $ticket['creador_id'] !== $_SESSION['usuario_id']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'No tienes permiso para subir archivos a este ticket.']);
    exit;
}

if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
    $error_msg = 'Error al subir el archivo.';
    if (isset($_FILES['archivo'])) {
        switch ($_FILES['archivo']['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $error_msg = 'El archivo excede el tamano maximo permitido.';
                break;
            case UPLOAD_ERR_PARTIAL:
                $error_msg = 'La subida del archivo se interrumpio.';
                break;
            case UPLOAD_ERR_NO_FILE:
                $error_msg = 'No se selecciono ningun archivo.';
                break;
        }
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $error_msg]);
    exit;
}

$archivo = $_FILES['archivo'];

// Validar tipo por contenido real (no confiar en $_FILES['type'])
$allowed_mime = [
    'image/jpeg', 'image/png', 'image/gif', 'image/webp',
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/zip', 'application/x-rar-compressed',
    'text/plain'
];

$allowed_ext = ['jpg','jpeg','png','gif','webp','pdf','doc','docx','xls','xlsx','zip','rar','txt'];

$ext = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $allowed_ext)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Extension de archivo no permitida.']);
    exit;
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$detected_mime = finfo_file($finfo, $archivo['tmp_name']);
finfo_close($finfo);

if (!in_array($detected_mime, $allowed_mime)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Tipo de archivo no permitido (detectado: ' . $detected_mime . ').']);
    exit;
}

// Force correct extension per MIME
$mime_to_ext = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/gif' => 'gif',
    'image/webp' => 'webp',
    'application/pdf' => 'pdf',
    'application/msword' => 'doc',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
    'application/vnd.ms-excel' => 'xls',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
    'application/zip' => 'zip',
    'application/x-rar-compressed' => 'rar',
    'text/plain' => 'txt',
];
$forced_ext = $mime_to_ext[$detected_mime] ?? $ext;

// Validar tamano (10 MB)
$max_size = 10 * 1024 * 1024;
if ($archivo['size'] > $max_size) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'El archivo excede el limite de 10 MB.']);
    exit;
}

// Crear directorio de uploads
$upload_dir = __DIR__ . '/uploads/tickets/' . $ticket_id;
if (!is_dir($upload_dir)) {
    if (!mkdir($upload_dir, 0755, true) && !is_dir($upload_dir)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Error al crear el directorio de uploads.']);
        exit;
    }
}

// Generar nombre unico (usando extension forzada por MIME)
$nombre_archivo = bin2hex(random_bytes(16)) . '.' . $forced_ext;
$ruta_destino = $upload_dir . '/' . $nombre_archivo;

if (!move_uploaded_file($archivo['tmp_name'], $ruta_destino)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error al guardar el archivo.']);
    exit;
}

// Guardar en DB
try {
    $ins = $pdo->prepare('
        INSERT INTO archivos_ticket (ticket_id, usuario_id, nombre_original, nombre_archivo, tipo, tamano)
        VALUES (:ticket_id, :usuario_id, :nombre_original, :nombre_archivo, :tipo, :tamano)
    ');
    $ins->execute([
        ':ticket_id'       => $ticket_id,
        ':usuario_id'      => $_SESSION['usuario_id'],
        ':nombre_original' => $archivo['name'],
        ':nombre_archivo'  => $nombre_archivo,
        ':tipo'            => $detected_mime,
        ':tamano'          => $archivo['size'],
    ]);

    $file_id = (int) $pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'archivo' => [
            'id'              => $file_id,
            'nombre_original' => $archivo['name'],
            'nombre_archivo'  => $nombre_archivo,
            'tipo'            => $detected_mime,
            'tamano'          => $archivo['size'],
            'url'             => '/helpdesk/descargar_archivo.php?id=' . $file_id,
        ]
    ]);
} catch (PDOException $e) {
    error_log('Error al registrar archivo en DB: ' . $e->getMessage());
    // Delete the file if DB insert fails
    @unlink($ruta_destino);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno del servidor.']);
}
