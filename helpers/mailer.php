<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

define('MAIL_HOST', getenv('MAIL_HOST') ?: 'smtp.gmail.com');
define('MAIL_PORT', (int) (getenv('MAIL_PORT') ?: 587));
define('MAIL_USER', getenv('MAIL_USER') ?: '');
define('MAIL_PASS', getenv('MAIL_PASS') ?: '');
define('MAIL_FROM', getenv('MAIL_FROM') ?: 'noreply@helpdesk.local');
define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: 'HelpDesk System');
define('MAIL_ENCRYPTION', getenv('MAIL_ENCRYPTION') ?: 'tls');
define('MAIL_BASE_URL', getenv('MAIL_BASE_URL') ?: 'http://localhost');

function enviarCorreo(string $destinatario, string $asunto, string $cuerpoHTML): bool
{
    if (MAIL_USER !== '' && MAIL_PASS !== '') {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = MAIL_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = MAIL_USER;
            $mail->Password   = MAIL_PASS;
            $mail->SMTPSecure = MAIL_ENCRYPTION;
            $mail->Port       = MAIL_PORT;
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
            $mail->addAddress($destinatario);
            $mail->isHTML(true);
            $mail->Subject = $asunto;
            $mail->Body    = $cuerpoHTML;
            $mail->AltBody = strip_tags($cuerpoHTML);

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log('[Mailer] Error SMTP a ' . $destinatario . ': ' . $mail->ErrorInfo);
            // Fall through to fallback methods
        }
    }

    $altBody = strip_tags($cuerpoHTML);
    $logLine = date('Y-m-d H:i:s') . " | TO: $destinatario | SUBJ: $asunto\n$altBody\n------\n";

    if (@mail($destinatario, $asunto, $altBody)) {
        return true;
    }

    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0775, true);
    }
    $written = @file_put_contents($logDir . '/mail.log', $logLine, FILE_APPEND | LOCK_EX);
    if ($written !== false) {
        error_log('[Mailer] Correo guardado en logs/mail.log para ' . $destinatario);
        return true;
    }

    error_log('[Mailer] No se pudo entregar correo a ' . $destinatario);
    return false;
}

function notificarStaffNuevoTicket(PDO $pdo, array $ticket): void
{
    $stmt = $pdo->query("SELECT email, nombre FROM usuarios WHERE rol IN ('soporte', 'admin')");
    $staff = $stmt->fetchAll();

    $asunto = '[Nuevo Ticket] ' . $ticket['folio'] . ' — ' . $ticket['titulo'];
    $cuerpo = '
        <h2>Nuevo Ticket Creado</h2>
        <p><strong>Folio:</strong> ' . htmlspecialchars($ticket['folio']) . '</p>
        <p><strong>Titulo:</strong> ' . htmlspecialchars($ticket['titulo']) . '</p>
        <p><strong>Prioridad:</strong> ' . htmlspecialchars($ticket['prioridad']) . '</p>
        <p><strong>Descripcion:</strong></p>
        <blockquote>' . $ticket['descripcion'] . '</blockquote>
        <p><a href="' . rtrim(MAIL_BASE_URL, '/') . url('ver_ticket.php?id=' . (int) $ticket['id']) . '">Ver ticket</a></p>
    ';

    foreach ($staff as $persona) {
        enviarCorreo($persona['email'], $asunto, $cuerpo);
    }
}

function notificarComentario(PDO $pdo, int $ticket_id, string $folio, string $autor_nombre, string $mensaje, string $destinatario_email): void
{
    $asunto = '[Nuevo comentario] ' . $folio;
    $cuerpo = '
        <h2>Nuevo comentario en ' . htmlspecialchars($folio) . '</h2>
        <p><strong>' . htmlspecialchars($autor_nombre) . '</strong> ha escrito:</p>
        <blockquote>' . $mensaje . '</blockquote>
        <p><a href="' . rtrim(MAIL_BASE_URL, '/') . url('ver_ticket.php?id=' . (int) $ticket_id) . '">Ver ticket</a></p>
    ';
    enviarCorreo($destinatario_email, $asunto, $cuerpo);
}

function notificarCambioEstado(string $folio, string $nuevo_estado, string $destinatario_email, int $ticket_id): void
{
    $asunto = '[Estado actualizado] ' . $folio . ' → ' . ucfirst(str_replace('_', ' ', $nuevo_estado));
    $cuerpo = '
        <h2>Estado de ticket actualizado</h2>
        <p>El ticket <strong>' . htmlspecialchars($folio) . '</strong> ha cambiado a <strong>' . htmlspecialchars(ucfirst(str_replace('_', ' ', $nuevo_estado))) . '</strong>.</p>
        <p><a href="' . rtrim(MAIL_BASE_URL, '/') . url('ver_ticket.php?id=' . (int) $ticket_id) . '">Ver ticket</a></p>
    ';
    enviarCorreo($destinatario_email, $asunto, $cuerpo);
}

function notificarAsignacion(string $folio, string $asignado_nombre, string $destinatario_email, int $ticket_id): void
{
    $asunto = '[Asignado] ' . $folio . ' — Ahora eres responsable';
    $cuerpo = '
        <h2>Ticket Asignado</h2>
        <p>El ticket <strong>' . htmlspecialchars($folio) . '</strong> te ha sido asignado a <strong>' . htmlspecialchars($asignado_nombre) . '</strong>.</p>
        <p><a href="' . rtrim(MAIL_BASE_URL, '/') . url('ver_ticket.php?id=' . (int) $ticket_id) . '">Ver ticket</a></p>
    ';
    enviarCorreo($destinatario_email, $asunto, $cuerpo);
}
