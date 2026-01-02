<?php
// config/config_mensajes.php

require_once __DIR__ . '/../config/auth.php';

// ✅ SOLO ADMIN (SUPER_ADMIN también entra)
require_role(['ADMIN']);

// -------------------------------
// Semillas por defecto (1ª vez)
// -------------------------------
$mensajes_defecto = [
    [
        'slug'     => 'ingreso',
        'titulo'   => 'Ingreso de equipo',
        'contenido'=> "Hola {NOMBRE}, tu equipo {EQUIPO} ha sido recibido con la orden N° {NUMERO_ORDEN}. Pronto será revisado.",
    ],
    [
        'slug'     => 'diagnostico',
        'titulo'   => 'Diagnóstico listo',
        'contenido'=> "Hola {NOMBRE}, ya tenemos el diagnóstico de tu equipo {EQUIPO}. Detalle: {DIAGNOSTICO}. Costo total: {COSTO_TOTAL}. ¿Autorizas la reparación?",
    ],
    [
        'slug'     => 'en_reparacion',
        'titulo'   => 'Equipo en reparación',
        'contenido'=> "Hola {NOMBRE}, tu equipo {EQUIPO} se encuentra en reparación. Te avisaremos cuando esté listo para entregar.",
    ],
    [
        'slug'     => 'espera_repuestos',
        'titulo'   => 'Esperando repuestos',
        'contenido'=> "Hola {NOMBRE}, tu equipo {EQUIPO} está en espera de repuestos. Apenas lleguen te avisaremos para continuar.",
    ],
    [
        'slug'     => 'listo_entrega',
        'titulo'   => 'Equipo listo para entregar',
        'contenido'=> "Hola {NOMBRE}, tu equipo {EQUIPO} está listo para retirar. Orden N° {NUMERO_ORDEN}. Horario: {HORARIO_TALLER}.",
    ],
];

// Insertar seeds solo si la tabla está vacía
$stmt  = $pdo->query("SELECT COUNT(*) FROM mensajes_whatsapp");
$total = (int)$stmt->fetchColumn();

if ($total === 0) {
    $ins = $pdo->prepare("
        INSERT INTO mensajes_whatsapp (slug, titulo, contenido, activo)
        VALUES (:slug, :titulo, :contenido, 1)
    ");
    foreach ($mensajes_defecto as $m) {
        $ins->execute([
            ':slug'      => $m['slug'],
            ':titulo'    => $m['titulo'],
            ':contenido' => $m['contenido'],
        ]);
    }
}

$mensaje_ok    = '';
$mensaje_error = '';

// -------------------------------
// Guardar cambios
// -------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['mensajes'])
    && is_array($_POST['mensajes'])
) {
    try {
        $upd = $pdo->prepare("
            UPDATE mensajes_whatsapp
            SET titulo = :titulo,
                contenido = :contenido,
                activo = :activo
            WHERE id = :id
        ");

        foreach ($_POST['mensajes'] as $id => $data) {
            $titulo    = trim($data['titulo'] ?? '');
            $contenido = trim($data['contenido'] ?? '');
            $activo    = isset($data['activo']) ? 1 : 0;

            // Si falta algo crítico, no tocamos esa fila
            if ($titulo === '' || $contenido === '') {
                continue;
            }

            $upd->execute([
                ':id'        => (int)$id,
                ':titulo'    => $titulo,
                ':contenido' => $contenido,
                ':activo'    => $activo,
            ]);
        }

        $mensaje_ok = 'Mensajes guardados correctamente.';
    } catch (Exception $e) {
        $mensaje_error = 'Error al guardar los mensajes. Intente nuevamente.';
        // debug opcional:
        // $mensaje_error = $e->getMessage();
    }
}

// -------------------------------
// Volver a cargar mensajes
// -------------------------------
$stmt = $pdo->query("
    SELECT id, slug, titulo, contenido, activo, actualizado_en
    FROM mensajes_whatsapp
    ORDER BY id ASC
");
$lista_mensajes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// UI
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-panel">
    <div class="content">
        <div class="container-fluid">

            <h4 class="page-title">Mensajes de WhatsApp</h4>

            <?php if ($mensaje_ok): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($mensaje_ok); ?>
                </div>
            <?php endif; ?>

            <?php if ($mensaje_error): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($mensaje_error); ?>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-10">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Plantillas por estado de la orden</h5>
                            <p class="card-category">
                                Variables disponibles:
                                <code>{NOMBRE}</code>,
                                <code>{EQUIPO}</code>,
                                <code>{NUMERO_ORDEN}</code>,
                                <code>{DIAGNOSTICO}</code>,
                                <code>{COSTO_TOTAL}</code>,
                                <code>{HORARIO_TALLER}</code>.
                            </p>
                        </div>

                        <div class="card-body">
                            <form method="post" action="<?php echo htmlspecialchars(url('/config/config_mensajes.php')); ?>">

                                <?php foreach ($lista_mensajes as $m): ?>
                                    <div class="border rounded p-3 mb-3">

                                        <div class="form-row">
                                            <div class="form-group col-md-6">
                                                <label>Título</label>
                                                <input type="text"
                                                       class="form-control"
                                                       name="mensajes[<?php echo (int)$m['id']; ?>][titulo]"
                                                       value="<?php echo htmlspecialchars($m['titulo']); ?>">
                                            </div>

                                            <div class="form-group col-md-3">
                                                <label>Clave interna</label>
                                                <input type="text"
                                                       class="form-control"
                                                       value="<?php echo htmlspecialchars($m['slug']); ?>"
                                                       readonly>
                                            </div>

                                            <div class="form-group col-md-3 d-flex align-items-center">
                                                <div class="form-check mt-3">
                                                    <input type="checkbox"
                                                           class="form-check-input"
                                                           id="activo_<?php echo (int)$m['id']; ?>"
                                                           name="mensajes[<?php echo (int)$m['id']; ?>][activo]"
                                                           <?php echo ((int)$m['activo'] === 1) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label"
                                                           for="activo_<?php echo (int)$m['id']; ?>">
                                                        Mensaje activo
                                                    </label>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="form-group mb-0">
                                            <label>Contenido del mensaje</label>
                                            <textarea class="form-control"
                                                      rows="3"
                                                      name="mensajes[<?php echo (int)$m['id']; ?>][contenido]"><?php
                                                echo htmlspecialchars($m['contenido']);
                                            ?></textarea>
                                        </div>

                                        <small class="text-muted">
                                            Última actualización:
                                            <?php echo htmlspecialchars((string)$m['actualizado_en']); ?>
                                        </small>

                                    </div>
                                <?php endforeach; ?>

                                <?php if (empty($lista_mensajes)): ?>
                                    <p class="text-muted">No hay mensajes configurados.</p>
                                <?php else: ?>
                                    <button type="submit" class="btn btn-primary">
                                        Guardar todos los mensajes
                                    </button>
                                <?php endif; ?>

                            </form>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
