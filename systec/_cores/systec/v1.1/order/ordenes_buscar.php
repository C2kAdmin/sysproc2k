<?php
// order/ordenes_buscar.php
require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . url('/login.php'));
    exit;
}

$q = trim($_GET['q'] ?? '');

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

$resultados = [];
$errorBuscar = '';
$dbActual = '';
$totalOrdenes = 0;

// 🔎 Sanity check (solo informativo)
try {
    $dbActual = (string)$pdo->query("SELECT DATABASE()")->fetchColumn();
    $totalOrdenes = (int)$pdo->query("SELECT COUNT(*) FROM ordenes")->fetchColumn();
} catch (Exception $e) {}

if ($q !== '') {
    try {
        $isNum = ctype_digit($q);

        // ⚠️ IMPORTANTE:
        // MySQL + PDO sin emulación NO permite reutilizar el mismo placeholder
        // Por eso usamos :q1, :q2, :q3, etc.
        $sql = "
            SELECT
                id, numero_orden, cliente_nombre, cliente_telefono,
                equipo_marca, equipo_modelo, equipo_imei1, equipo_imei2,
                estado_actual, fecha_ingreso
            FROM ordenes
            WHERE
                " . ($isNum ? "numero_orden = :num OR " : "") . "
                cliente_nombre     LIKE CONCAT('%', CONVERT(:q1 USING latin1), '%')
                OR cliente_telefono LIKE CONCAT('%', CONVERT(:q2 USING latin1), '%')
                OR equipo_marca     LIKE CONCAT('%', CONVERT(:q3 USING latin1), '%')
                OR equipo_modelo    LIKE CONCAT('%', CONVERT(:q4 USING latin1), '%')
                OR equipo_imei1     LIKE CONCAT('%', CONVERT(:q5 USING latin1), '%')
                OR equipo_imei2     LIKE CONCAT('%', CONVERT(:q6 USING latin1), '%')
                OR token_publico    LIKE CONCAT('%', CONVERT(:q7 USING latin1), '%')
            ORDER BY id DESC
            LIMIT 50
        ";

        $stmt = $pdo->prepare($sql);

        $params = [
            ':q1' => $q,
            ':q2' => $q,
            ':q3' => $q,
            ':q4' => $q,
            ':q5' => $q,
            ':q6' => $q,
            ':q7' => $q,
        ];

        if ($isNum) {
            $params[':num'] = (int)$q;
        }

        $stmt->execute($params);
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (Exception $e) {
        $errorBuscar = $e->getMessage();
        error_log('[ordenes_buscar] ' . $errorBuscar);
    }
}
?>

<div class="main-panel">
  <div class="content">
    <div class="container-fluid">

      <h4 class="page-title">Buscar órdenes</h4>

      <form method="get" action="<?php echo url('/order/ordenes_buscar.php'); ?>" class="mb-3">
        <div class="input-group" style="max-width:520px;">
          <input
            type="text"
            class="form-control"
            name="q"
            placeholder="Número / cliente / teléfono / IMEI / token"
            value="<?php echo htmlspecialchars($q); ?>"
          >
          <div class="input-group-append">
            <button class="btn btn-primary" type="submit">Buscar</button>
          </div>
        </div>
      </form>

      <?php if ($dbActual !== ''): ?>
        <p class="text-muted small mb-2">
          DB: <strong><?php echo htmlspecialchars($dbActual); ?></strong>
          · Total órdenes: <strong><?php echo (int)$totalOrdenes; ?></strong>
        </p>
      <?php endif; ?>

      <?php if ($q === ''): ?>
        <div class="alert alert-info">
          Escribe algo para buscar (ej: <strong>3</strong>, <strong>Sonia</strong>, <strong>569...</strong>, <strong>IMEI</strong>).
        </div>
      <?php else: ?>

        <?php if ($errorBuscar !== ''): ?>
          <div class="alert alert-danger">
            Error al buscar (revisa log del servidor).
            <br>
            <small class="text-muted"><?php echo htmlspecialchars($errorBuscar); ?></small>
          </div>
        <?php endif; ?>

        <p class="text-muted">
          Resultados para: <strong><?php echo htmlspecialchars($q); ?></strong>
          · encontrados: <strong><?php echo count($resultados); ?></strong>
        </p>

        <?php if (empty($resultados)): ?>
          <div class="alert alert-warning">No se encontraron órdenes.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-sm table-hover">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Cliente</th>
                  <th>Teléfono</th>
                  <th>Equipo</th>
                  <th>IMEI</th>
                  <th>Estado</th>
                  <th>Ingreso</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
              <?php foreach ($resultados as $r): ?>
                <tr>
                  <td><?php echo (int)$r['numero_orden']; ?></td>
                  <td><?php echo htmlspecialchars($r['cliente_nombre']); ?></td>
                  <td><?php echo htmlspecialchars($r['cliente_telefono']); ?></td>
                  <td><?php echo htmlspecialchars(trim(($r['equipo_marca'] ?? '').' '.($r['equipo_modelo'] ?? ''))); ?></td>
                  <td><?php echo htmlspecialchars(trim(($r['equipo_imei1'] ?? '').' '.($r['equipo_imei2'] ?? ''))); ?></td>
                  <td><?php echo htmlspecialchars($r['estado_actual']); ?></td>
                  <td><?php echo date('d-m-Y H:i', strtotime($r['fecha_ingreso'])); ?></td>
                  <td>
                    <a class="btn btn-primary btn-sm"
                       href="<?php echo url('/order/orden_detalle.php?id='.(int)$r['id']); ?>">
                      Ver
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>

      <?php endif; ?>

    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
