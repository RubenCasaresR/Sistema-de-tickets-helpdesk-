<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/conexion.php';
requiereAutenticacion();

$pdo = obtenerConexion();

$error   = '';
$success = '';
$titulo  = '';
$descripcion = '';

$is_staff = in_array($_SESSION['rol'], ['soporte', 'admin'], true);
$staffStmt = $pdo->query("SELECT id, nombre FROM usuarios WHERE rol IN ('soporte', 'admin') ORDER BY nombre");
$personal_staff = $staffStmt->fetchAll();

$categoriasStmt = $pdo->query("SELECT id, nombre FROM categorias WHERE activo = 1 ORDER BY nombre");
$categorias = $categoriasStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo       = trim($_POST['titulo'] ?? '');
    $descripcion  = trim($_POST['descripcion'] ?? '');
    $prioridad    = $_POST['prioridad'] ?? 'media';
    $asignado_id  = $is_staff ? (int) ($_POST['asignado_id'] ?? 0) : 0;
    $categoria_id = (int) ($_POST['categoria_id'] ?? 0);
    $csrf_token   = $_POST['csrf_token'] ?? '';

    if (!validarTokenCSRF($csrf_token)) {
        $error = 'Token de seguridad invalido. Intente de nuevo.';
    } elseif ($titulo === '' || trim(strip_tags($descripcion)) === '') {
        $error = 'El titulo y la descripcion son obligatorios.';
    } elseif (!in_array($prioridad, ['baja', 'media', 'alta', 'urgente'], true)) {
        $error = 'Prioridad invalida.';
    } elseif ($asignado_id > 0) {
        $check = $pdo->prepare('SELECT id FROM usuarios WHERE id = :id AND rol IN (\'soporte\', \'admin\')');
        $check->execute([':id' => $asignado_id]);
        if (!$check->fetch()) {
            $error = 'El usuario asignado no es valido.';
        }
    }

    if ($error === '') {
        $descripcion = sanitizarDescripcion($descripcion, '<h1><h2><h3>');

        try {
            $pdo->beginTransaction();

            // Insert with temporary unique folio first
            $tempFolio = 'TCK-' . bin2hex(random_bytes(4));
            $insert = $pdo->prepare('
                INSERT INTO tickets (folio, titulo, descripcion, prioridad, creador_id, asignado_id, categoria_id)
                VALUES (:folio, :titulo, :descripcion, :prioridad, :creador_id, :asignado_id, :categoria_id)
            ');
            $insert->execute([
                ':folio'        => $tempFolio,
                ':titulo'       => $titulo,
                ':descripcion'  => $descripcion,
                ':prioridad'    => $prioridad,
                ':creador_id'   => $_SESSION['usuario_id'],
                ':asignado_id'  => $asignado_id > 0 ? $asignado_id : null,
                ':categoria_id' => $categoria_id > 0 ? $categoria_id : null,
            ]);

            $new_ticket_id = (int) $pdo->lastInsertId();
            $folio  = 'TCK-' . str_pad((string) $new_ticket_id, 5, '0', STR_PAD_LEFT);

            $updFolio = $pdo->prepare('UPDATE tickets SET folio = :folio WHERE id = :id');
            $updFolio->execute([':folio' => $folio, ':id' => $new_ticket_id]);

            $pdo->commit();
            registrarHistorialTicket($pdo, $new_ticket_id, $_SESSION['usuario_id'], 'creacion', "Ticket {$folio} creado");

            // Notificar por correo al staff
            $ticketData = [
                'id'         => $new_ticket_id,
                'folio'      => $folio,
                'titulo'     => $titulo,
                'descripcion'=> $descripcion,
                'prioridad'  => $prioridad,
            ];
            if (file_exists(__DIR__ . '/helpers/mailer.php')) {
                require_once __DIR__ . '/helpers/mailer.php';
                notificarStaffNuevoTicket($pdo, $ticketData);
            }

            $_SESSION['success_message'] = 'Ticket creado exitosamente. Folio: ' . $folio;
            redirect('mis_tickets.php');
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log('Error al crear ticket: ' . $e->getMessage());
            $error = 'Error al crear el ticket. Intente mas tarde.';
        }
    }
}

$csrf_token = generarTokenCSRF();
$page_title = 'Nuevo Ticket';
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="page-header">
    <h1>Nuevo Ticket</h1>
    <p>Crea una solicitud de soporte para que nuestro equipo pueda ayudarte.</p>
</div>

<div class="card" style="max-width:860px">
    <div class="card-header">
        <h3>Formulario de Solicitud</h3>
        <span class="text-muted text-small">Completa los campos obligatorios</span>
    </div>
    <div class="card-body">
        <?php if ($error !== ''): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" action="" id="formNuevoTicket">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

            <!-- Section: Informacion Basica -->
            <div class="form-section-title">
                <span class="section-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="8"/></svg>
                </span>
Informacion Basica
            </div>

            <div class="form-group">
                <label for="titulo">Titulo</label>
                <input type="text" id="titulo" name="titulo" class="form-control" placeholder="Resume tu solicitud en una linea" required value="<?= htmlspecialchars($titulo) ?>">
            </div>

            <div class="form-group">
                <label>Prioridad</label>
                <div class="priority-picker" id="priorityPicker">
                    <label class="priority-option baja">
                        <input type="radio" name="prioridad" value="baja">
                        <span class="priority-icon">📉</span>
                        <span class="priority-label">Baja</span>
                        <span class="priority-badge-dot" style="background:var(--badge-baja-bg)"></span>
                        <span class="priority-check">✓</span>
                    </label>
                    <label class="priority-option media selected">
                        <input type="radio" name="prioridad" value="media" checked>
                        <span class="priority-icon">📊</span>
                        <span class="priority-label">Media</span>
                        <span class="priority-badge-dot" style="background:var(--badge-media-bg)"></span>
                        <span class="priority-check">✓</span>
                    </label>
                    <label class="priority-option alta">
                        <input type="radio" name="prioridad" value="alta">
                        <span class="priority-icon">📈</span>
                        <span class="priority-label">Alta</span>
                        <span class="priority-badge-dot" style="background:var(--badge-alta-bg)"></span>
                        <span class="priority-check">✓</span>
                    </label>
                    <label class="priority-option urgente">
                        <input type="radio" name="prioridad" value="urgente">
                        <span class="priority-icon">🚨</span>
                        <span class="priority-label">Urgente</span>
                        <span class="priority-badge-dot" style="background:var(--badge-urgente-bg)"></span>
                        <span class="priority-check">✓</span>
                    </label>
                </div>
            </div>

            <div class="form-group">
                <label for="categoria_id">Categoria</label>
                <select id="categoria_id" name="categoria_id" class="form-control">
                    <option value="">— Sin categoria —</option>
                    <?php foreach ($categorias as $cat): ?>
                        <option value="<?= (int) $cat['id'] ?>"><?= htmlspecialchars($cat['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Section: Descripcion -->
            <div class="form-section-title">
                <span class="section-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                </span>
Descripcion
            </div>

            <div class="quill-wrapper">
                <div class="quill-editor" data-target="descripcion" data-placeholder="Describe detalladamente el problema o solicitud..." data-content="<?= htmlspecialchars($descripcion) ?>"></div>
                <input type="hidden" name="descripcion" value="">
                <span class="form-hint">Incluye pasos para reproducir el problema si es posible.</span>
            </div>

            <?php if ($is_staff): ?>
            <!-- Section: Asignacion -->
            <div class="form-section-title">
                <span class="section-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                </span>
Asignacion
            </div>

            <div class="form-group">
                <label for="asignado_id">Asignar a</label>
                <select id="asignado_id" name="asignado_id" class="form-control">
                    <option value="0">— Sin asignar —</option>
                    <?php foreach ($personal_staff as $staff): ?>
                        <option value="<?= (int) $staff['id'] ?>"><?= htmlspecialchars($staff['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
                <span class="form-hint">Puedes dejar sin asignar para que otro miembro del equipo lo tome.</span>
            </div>
            <?php endif; ?>

            <!-- Actions -->
            <div class="flex gap-4" style="margin-top:28px;padding-top:24px;border-top:1px solid var(--color-border)">
                <button type="submit" class="btn btn-primary" style="padding:10px 28px">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px;vertical-align:middle"><path d="M20 6L9 17l-5-5"/></svg>
                    Crear Ticket
                </button>
                <a href="<?= url('mis_tickets.php') ?>" class="btn btn-outline">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px;vertical-align:middle"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    Cancelar
                </a>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var picker = document.getElementById('priorityPicker');
    if (picker) {
        var options = picker.querySelectorAll('.priority-option');
        options.forEach(function(opt) {
            opt.addEventListener('click', function() {
                options.forEach(function(o) { o.classList.remove('selected'); });
                this.classList.add('selected');
                var radio = this.querySelector('input[type="radio"]');
                if (radio) radio.checked = true;
            });
        });
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>


