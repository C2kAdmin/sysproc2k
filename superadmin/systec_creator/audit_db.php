<?php
/**
 * audit_db.php (SOLO LECTURA)
 * Reporte profundo de estructura y salud de BD (MariaDB/MySQL).
 *
 * ✅ No modifica datos.
 * ✅ No crea tablas.
 * ✅ No crea procedures/triggers.
 *
 * Salidas:
 * - HTML (por defecto)
 * - JSON: ?format=json
 *
 * IMPORTANTE: BÓRRALO al terminar. No lo dejes público.
 */

ini_set('display_errors', '1');
error_reporting(E_ALL);
date_default_timezone_set('America/Santiago');

/* =========================
   CONEXIÓN (PRE-RELLENA)
   ========================= */
$DB_HOST = 'localhost';
$DB_NAME = 'ckcl_systec_c2k';
$DB_USER = 'ckcl_systec_c2k';
$DB_PASS = '112233Kdoki.';

/* =========================
   (Opcional) Token simple
   Si lo dejas vacío, no exige token.
   Si lo pones, abre: audit_db.php?token=TU_TOKEN
   ========================= */
// $ACCESS_TOKEN = 'pon_un_token_largo_aqui';
$ACCESS_TOKEN = '';

if ($ACCESS_TOKEN !== '') {
  $t = $_GET['token'] ?? '';
  if (!hash_equals($ACCESS_TOKEN, $t)) {
    http_response_code(403);
    die('403 Forbidden');
  }
}

/* =========================
   PDO
   ========================= */
try {
  $pdo = new PDO(
    "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4",
    $DB_USER,
    $DB_PASS,
    [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false,
    ]
  );
  // Fuerza charset de conexión (aunque server/DB esté en latin1)
  $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
} catch (Exception $e) {
  http_response_code(500);
  die("Error de conexión: " . htmlspecialchars($e->getMessage()));
}

/* =========================
   Helpers
   ========================= */
function q(PDO $pdo, string $sql, array $params = []): array {
  $st = $pdo->prepare($sql);
  $st->execute($params);
  return $st->fetchAll();
}

function q1(PDO $pdo, string $sql, array $params = []): array {
  $rows = q($pdo, $sql, $params);
  return $rows[0] ?? [];
}

function scalar(PDO $pdo, string $sql, array $params = []) {
  $st = $pdo->prepare($sql);
  $st->execute($params);
  return $st->fetchColumn();
}

function safe_ident(string $name): string {
  // backticks + basic sanitization
  return '`' . str_replace('`', '``', $name) . '`';
}

function fmt_bytes($bytes): string {
  if (!is_numeric($bytes)) return (string)$bytes;
  $bytes = (float)$bytes;
  $units = ['B','KB','MB','GB','TB'];
  $i = 0;
  while ($bytes >= 1024 && $i < count($units)-1) { $bytes /= 1024; $i++; }
  return round($bytes, 2) . ' ' . $units[$i];
}

function html($s): string {
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/* =========================
   Recolección del reporte
   ========================= */
$report = [];
$report['generated_at'] = date('Y-m-d H:i:s');
$report['db'] = $DB_NAME;

/* Meta server/db */
$report['meta'] = [];
$report['meta']['server'] = q1($pdo, "SELECT @@version AS version, @@version_comment AS comment, @@sql_mode AS sql_mode, @@foreign_key_checks AS fk_checks");
$report['meta']['database'] = [
  'database()' => scalar($pdo, "SELECT DATABASE()"),
  'character_set_server' => scalar($pdo, "SHOW VARIABLES LIKE 'character_set_server'") ?: null,
  'collation_server' => scalar($pdo, "SHOW VARIABLES LIKE 'collation_server'") ?: null,
];

// Variables charset/collation completas (útil para diagnóstico)
$vars = q($pdo, "SHOW VARIABLES WHERE Variable_name LIKE 'character_set_%' OR Variable_name LIKE 'collation_%'");
$report['meta']['variables_charset_collation'] = $vars;

/* Listado de tablas y vistas */
$fullTables = q($pdo, "SHOW FULL TABLES");
$tables = [];
$views = [];
if (!empty($fullTables)) {
  // La primera columna cambia según DB. Tomamos keys dinámicas.
  $keys = array_keys($fullTables[0]);
  $nameKey = $keys[0];         // Tables_in_db
  $typeKey = $keys[1] ?? null; // Table_type
  foreach ($fullTables as $r) {
    $tname = $r[$nameKey];
    $ttype = $typeKey ? $r[$typeKey] : 'BASE TABLE';
    if ($ttype === 'VIEW') $views[] = $tname;
    else $tables[] = $tname;
  }
}
sort($tables);
sort($views);

$report['objects'] = [
  'base_tables' => $tables,
  'views' => $views,
];

/* Tabla status (tamaños/engine/collation) */
$tableStatus = q($pdo, "SHOW TABLE STATUS");
$statusByName = [];
foreach ($tableStatus as $s) {
  if (!empty($s['Name'])) $statusByName[$s['Name']] = $s;
}
$report['tables_summary'] = [];
foreach ($tables as $t) {
  $s = $statusByName[$t] ?? [];
  $report['tables_summary'][] = [
    'name' => $t,
    'engine' => $s['Engine'] ?? null,
    'rows' => $s['Rows'] ?? null,
    'data_length' => $s['Data_length'] ?? null,
    'index_length' => $s['Index_length'] ?? null,
    'total_bytes' => (int)($s['Data_length'] ?? 0) + (int)($s['Index_length'] ?? 0),
    'auto_increment' => $s['Auto_increment'] ?? null,
    'collation' => $s['Collation'] ?? null,
    'create_time' => $s['Create_time'] ?? null,
    'update_time' => $s['Update_time'] ?? null,
    'comment' => $s['Comment'] ?? null,
  ];
}

/* Índices / columnas / create table / FKs por tabla */
$report['tables'] = [];
$fk_orphans = [];
$health = [
  'tables_without_pk' => [],
  'tables_not_innodb' => [],
  'tables_collation_non_utf8mb4' => [],
  'columns_charset_non_utf8mb4' => [],
  'foreign_keys' => [],
  'foreign_key_orphans' => [],
  'notes' => [],
];

foreach ($tables as $t) {
  $table = ['name' => $t];

  // Columns
  $cols = q($pdo, "SHOW FULL COLUMNS FROM " . safe_ident($t));
  $table['columns'] = $cols;

  // Indexes
  $idx = q($pdo, "SHOW INDEX FROM " . safe_ident($t));
  $table['indexes'] = $idx;

  // PK check
  $hasPk = false;
  foreach ($idx as $irow) {
    if (($irow['Key_name'] ?? '') === 'PRIMARY') { $hasPk = true; break; }
  }
  if (!$hasPk) $health['tables_without_pk'][] = $t;

  // Engine/collation check
  $s = $statusByName[$t] ?? [];
  if (!empty($s) && ($s['Engine'] ?? '') !== 'InnoDB') $health['tables_not_innodb'][] = $t;

  $coll = $s['Collation'] ?? '';
  if ($coll && stripos($coll, 'utf8mb4_') !== 0) {
    // No asumimos que esté “mal”, pero lo marcamos.
    $health['tables_collation_non_utf8mb4'][] = ['table'=>$t,'collation'=>$coll];
  }

  // Column charset/collation check
  foreach ($cols as $c) {
    // SHOW FULL COLUMNS trae Collation solo para tipos textuales
    $cColl = $c['Collation'] ?? null;
    if ($cColl && stripos($cColl, 'utf8mb4_') !== 0) {
      $health['columns_charset_non_utf8mb4'][] = [
        'table' => $t,
        'column' => $c['Field'] ?? null,
        'collation' => $cColl,
        'type' => $c['Type'] ?? null,
      ];
    }
  }

  // SHOW CREATE TABLE
  $create = q($pdo, "SHOW CREATE TABLE " . safe_ident($t));
  // En MariaDB puede venir key "Create Table"
  $createSql = '';
  if (!empty($create[0])) {
    $k = array_keys($create[0]);
    foreach ($k as $kk) {
      if (stripos($kk, 'Create Table') !== false) { $createSql = $create[0][$kk]; break; }
    }
  }
  $table['create_table'] = $createSql;

  // Foreign keys (preferimos information_schema, pero si falla igual mostramos create_table)
  $fks = [];
  try {
    $fks = q($pdo, "
      SELECT
        kcu.CONSTRAINT_NAME AS constraint_name,
        kcu.COLUMN_NAME AS column_name,
        kcu.REFERENCED_TABLE_NAME AS ref_table,
        kcu.REFERENCED_COLUMN_NAME AS ref_column
      FROM information_schema.KEY_COLUMN_USAGE kcu
      WHERE kcu.TABLE_SCHEMA = DATABASE()
        AND kcu.TABLE_NAME = :t
        AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
      ORDER BY kcu.CONSTRAINT_NAME, kcu.ORDINAL_POSITION
    ", [':t'=>$t]);

    // Reglas update/delete
    if (!empty($fks)) {
      $rules = q($pdo, "
        SELECT
          rc.CONSTRAINT_NAME AS constraint_name,
          rc.UPDATE_RULE AS update_rule,
          rc.DELETE_RULE AS delete_rule
        FROM information_schema.REFERENTIAL_CONSTRAINTS rc
        WHERE rc.CONSTRAINT_SCHEMA = DATABASE()
      ");
      $rulesBy = [];
      foreach ($rules as $r) $rulesBy[$r['constraint_name']] = $r;

      foreach ($fks as &$fk) {
        $r = $rulesBy[$fk['constraint_name']] ?? null;
        $fk['update_rule'] = $r['update_rule'] ?? null;
        $fk['delete_rule'] = $r['delete_rule'] ?? null;
      }
      unset($fk);
    }
  } catch (Exception $e) {
    $health['notes'][] = "No se pudo leer information_schema para FKs en {$t}. Se usará SHOW CREATE TABLE como referencia.";
    $fks = [];
  }

  $table['foreign_keys'] = $fks;

  // Guardar lista global de FKs
  foreach ($fks as $fk) {
    $health['foreign_keys'][] = array_merge(['table'=>$t], $fk);
  }

  // Orphan check por FK (solo 1 columna por FK)
  // Si FK es compuesta, no la calculamos aquí.
  $fkByConstraint = [];
  foreach ($fks as $fk) $fkByConstraint[$fk['constraint_name']][] = $fk;

  foreach ($fkByConstraint as $cname => $rowsFk) {
    if (count($rowsFk) !== 1) continue;
    $fk = $rowsFk[0];

    $childCol = $fk['column_name'];
    $parentTable = $fk['ref_table'];
    $parentCol = $fk['ref_column'];

    // Ejecutar conteo orphans
    try {
      $sqlOrph = "
        SELECT COUNT(*) AS orphans
        FROM " . safe_ident($t) . " c
        LEFT JOIN " . safe_ident($parentTable) . " p
          ON c." . safe_ident($childCol) . " = p." . safe_ident($parentCol) . "
        WHERE c." . safe_ident($childCol) . " IS NOT NULL
          AND p." . safe_ident($parentCol) . " IS NULL
      ";
      $orphCount = (int) scalar($pdo, $sqlOrph);
      $fk_orphans[] = [
        'relation' => "{$t}.{$childCol} -> {$parentTable}.{$parentCol} ({$cname})",
        'orphans' => $orphCount
      ];
      if ($orphCount > 0) {
        $health['foreign_key_orphans'][] = [
          'relation' => "{$t}.{$childCol} -> {$parentTable}.{$parentCol} ({$cname})",
          'orphans' => $orphCount
        ];
      }
    } catch (Exception $e) {
      $health['notes'][] = "No se pudo calcular huérfanos para FK {$cname} en {$t}: " . $e->getMessage();
    }
  }

  $report['tables'][] = $table;
}

/* Triggers */
try {
  $report['triggers'] = q($pdo, "SHOW TRIGGERS");
} catch (Exception $e) {
  $report['triggers'] = [];
  $health['notes'][] = "SHOW TRIGGERS falló: " . $e->getMessage();
}

/* Procedures & Functions */
$report['routines'] = [
  'procedures' => [],
  'functions' => [],
];
try {
  $report['routines']['procedures'] = q($pdo, "SHOW PROCEDURE STATUS WHERE Db = DATABASE()");
} catch (Exception $e) {
  $health['notes'][] = "SHOW PROCEDURE STATUS falló: " . $e->getMessage();
}
try {
  $report['routines']['functions'] = q($pdo, "SHOW FUNCTION STATUS WHERE Db = DATABASE()");
} catch (Exception $e) {
  $health['notes'][] = "SHOW FUNCTION STATUS falló: " . $e->getMessage();
}

/* Views (definición) */
$report['views_definition'] = [];
foreach ($views as $v) {
  try {
    $cr = q($pdo, "SHOW CREATE VIEW " . safe_ident($v));
    $createViewSql = '';
    if (!empty($cr[0])) {
      foreach (array_keys($cr[0]) as $kk) {
        if (stripos($kk, 'Create View') !== false) { $createViewSql = $cr[0][$kk]; break; }
      }
    }
    $report['views_definition'][] = ['name'=>$v, 'create_view'=>$createViewSql];
  } catch (Exception $e) {
    $report['views_definition'][] = ['name'=>$v, 'create_view'=>null, 'error'=>$e->getMessage()];
  }
}

/* Ordenar resumen de tablas por tamaño */
usort($report['tables_summary'], function($a, $b) {
  return ($b['total_bytes'] ?? 0) <=> ($a['total_bytes'] ?? 0);
});

/* FK orphans global */
usort($fk_orphans, fn($a,$b) => ($b['orphans'] ?? 0) <=> ($a['orphans'] ?? 0));
$report['integrity'] = [
  'fk_orphans' => $fk_orphans
];

/* Health final */
$report['health'] = $health;

/* Recomendaciones (heurísticas) */
$reco = [];
if (!empty($health['tables_without_pk'])) {
  $reco[] = "Hay tablas sin PRIMARY KEY: " . implode(', ', $health['tables_without_pk']) . ". Recomendado definir PK para performance e integridad.";
}
if (!empty($health['tables_not_innodb'])) {
  $reco[] = "Hay tablas que no son InnoDB: " . implode(', ', $health['tables_not_innodb']) . ". Recomendado InnoDB para FKs e integridad.";
}
if (!empty($health['tables_collation_non_utf8mb4']) || !empty($health['columns_charset_non_utf8mb4'])) {
  $reco[] = "Se detectaron collations no-utf8mb4 en tablas/columnas. Si manejas tildes/emojis, considera migrar a utf8mb4 (planificado, con backup).";
}
if (!empty($health['foreign_key_orphans'])) {
  $reco[] = "Hay huérfanos detectados por FK. Antes de limpiar, decidir si borrar o reparar referencias.";
}
$report['recommendations'] = $reco;

/* =========================
   OUTPUT
   ========================= */
$format = strtolower((string)($_GET['format'] ?? 'html'));
if ($format === 'json') {
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
  exit;
}

/* HTML */
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>SysTec DB Audit — <?php echo html($DB_NAME); ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{font-family:Arial, Helvetica, sans-serif; background:#0b0d10; color:#e8eef7; margin:0; padding:16px;}
    h1,h2,h3{margin:10px 0;}
    a{color:#7db4ff;}
    .wrap{max-width:1200px; margin:0 auto;}
    .card{background:#121622; border:1px solid #1f2a44; border-radius:10px; padding:14px; margin:12px 0;}
    .muted{color:#aab6cc;}
    table{width:100%; border-collapse:collapse; margin-top:10px;}
    th,td{border:1px solid #223055; padding:8px; vertical-align:top; font-size:13px;}
    th{background:#171d2d;}
    code,pre{background:#0f1320; border:1px solid #223055; border-radius:8px; padding:10px; display:block; overflow:auto; color:#dfe8ff;}
    .pill{display:inline-block; padding:4px 10px; border-radius:20px; background:#1a2340; border:1px solid #2a3a66; margin-right:8px; font-size:12px;}
    .ok{background:#0f2a19; border-color:#2a6a44;}
    .warn{background:#2a1f0f; border-color:#6a532a;}
    .bad{background:#2a0f14; border-color:#6a2a35;}
    .grid{display:grid; grid-template-columns:repeat(3,1fr); gap:10px;}
    @media(max-width:900px){.grid{grid-template-columns:1fr;}}
    details{background:#0f1320; border:1px solid #223055; border-radius:10px; padding:10px; margin-top:10px;}
    summary{cursor:pointer; font-weight:bold;}
  </style>
</head>
<body>
<div class="wrap">
  <h1>SysTec DB Audit</h1>
  <div class="muted">BD: <b><?php echo html($DB_NAME); ?></b> — Generado: <?php echo html($report['generated_at']); ?></div>

  <div class="card">
    <div class="grid">
      <div>
        <div class="pill ok">Tablas base: <?php echo count($tables); ?></div>
        <div class="pill">Vistas: <?php echo count($views); ?></div>
      </div>
      <div>
        <div class="pill">Version: <?php echo html($report['meta']['server']['version'] ?? ''); ?></div>
        <div class="pill">FK checks: <?php echo html($report['meta']['server']['fk_checks'] ?? ''); ?></div>
      </div>
      <div>
        <a href="?format=json<?php echo ($ACCESS_TOKEN!=='' ? '&token='.urlencode($_GET['token'] ?? '') : ''); ?>">Ver JSON</a>
      </div>
    </div>
  </div>

  <div class="card">
    <h2>1) Meta</h2>
    <pre><?php echo html(json_encode($report['meta'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
  </div>

  <div class="card">
    <h2>2) Resumen de tablas (tamaño/engine/collation)</h2>
    <table>
      <thead>
        <tr>
          <th>Tabla</th><th>Engine</th><th>Rows</th><th>Data</th><th>Index</th><th>Total</th><th>AutoInc</th><th>Collation</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($report['tables_summary'] as $r): ?>
        <tr>
          <td><?php echo html($r['name']); ?></td>
          <td><?php echo html($r['engine']); ?></td>
          <td><?php echo html($r['rows']); ?></td>
          <td><?php echo html(fmt_bytes($r['data_length'])); ?></td>
          <td><?php echo html(fmt_bytes($r['index_length'])); ?></td>
          <td><?php echo html(fmt_bytes($r['total_bytes'])); ?></td>
          <td><?php echo html($r['auto_increment']); ?></td>
          <td><?php echo html($r['collation']); ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="card">
    <h2>3) Integridad (huérfanos por FK)</h2>
    <table>
      <thead><tr><th>Relación</th><th>Orphans</th></tr></thead>
      <tbody>
        <?php foreach (($report['integrity']['fk_orphans'] ?? []) as $o): ?>
          <tr>
            <td><?php echo html($o['relation']); ?></td>
            <td><?php echo html($o['orphans']); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="card">
    <h2>4) Salud del esquema (hallazgos)</h2>

    <?php
      $pkMissing = $health['tables_without_pk'] ?? [];
      $notInno = $health['tables_not_innodb'] ?? [];
      $badT = $health['tables_collation_non_utf8mb4'] ?? [];
      $badC = $health['columns_charset_non_utf8mb4'] ?? [];
      $badO = $health['foreign_key_orphans'] ?? [];
    ?>

    <div class="pill <?php echo empty($pkMissing) ? 'ok' : 'warn'; ?>">
      Sin PK: <?php echo count($pkMissing); ?>
    </div>
    <div class="pill <?php echo empty($notInno) ? 'ok' : 'warn'; ?>">
      No InnoDB: <?php echo count($notInno); ?>
    </div>
    <div class="pill <?php echo (empty($badT) && empty($badC)) ? 'ok' : 'warn'; ?>">
      Charset/Collation no utf8mb4: <?php echo count($badT) + count($badC); ?>
    </div>
    <div class="pill <?php echo empty($badO) ? 'ok' : 'bad'; ?>">
      FKs con huérfanos: <?php echo count($badO); ?>
    </div>

    <details>
      <summary>Detalle hallazgos</summary>
      <pre><?php echo html(json_encode($health, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
    </details>

    <?php if (!empty($report['recommendations'])): ?>
      <h3>Recomendaciones</h3>
      <ul>
        <?php foreach ($report['recommendations'] as $rec): ?>
          <li><?php echo html($rec); ?></li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <div class="muted">Sin recomendaciones críticas detectadas por heurística.</div>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>5) Detalle por tabla</h2>
    <?php foreach ($report['tables'] as $t): ?>
      <details>
        <summary><?php echo html($t['name']); ?></summary>

        <h3>Columnas</h3>
        <table>
          <thead><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th><th>Collation</th><th>Comment</th></tr></thead>
          <tbody>
            <?php foreach ($t['columns'] as $c): ?>
              <tr>
                <td><?php echo html($c['Field'] ?? ''); ?></td>
                <td><?php echo html($c['Type'] ?? ''); ?></td>
                <td><?php echo html($c['Null'] ?? ''); ?></td>
                <td><?php echo html($c['Key'] ?? ''); ?></td>
                <td><?php echo html($c['Default'] ?? ''); ?></td>
                <td><?php echo html($c['Extra'] ?? ''); ?></td>
                <td><?php echo html($c['Collation'] ?? ''); ?></td>
                <td><?php echo html($c['Comment'] ?? ''); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <h3>Índices</h3>
        <table>
          <thead><tr><th>Key_name</th><th>Non_unique</th><th>Seq</th><th>Column_name</th><th>Index_type</th></tr></thead>
          <tbody>
            <?php foreach ($t['indexes'] as $i): ?>
              <tr>
                <td><?php echo html($i['Key_name'] ?? ''); ?></td>
                <td><?php echo html($i['Non_unique'] ?? ''); ?></td>
                <td><?php echo html($i['Seq_in_index'] ?? ''); ?></td>
                <td><?php echo html($i['Column_name'] ?? ''); ?></td>
                <td><?php echo html($i['Index_type'] ?? ''); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <h3>Foreign Keys</h3>
        <?php if (empty($t['foreign_keys'])): ?>
          <div class="muted">No se detectaron FKs vía information_schema (o no existen).</div>
        <?php else: ?>
          <table>
            <thead><tr><th>Constraint</th><th>Column</th><th>Ref Table</th><th>Ref Col</th><th>On Update</th><th>On Delete</th></tr></thead>
            <tbody>
              <?php foreach ($t['foreign_keys'] as $fk): ?>
                <tr>
                  <td><?php echo html($fk['constraint_name'] ?? ''); ?></td>
                  <td><?php echo html($fk['column_name'] ?? ''); ?></td>
                  <td><?php echo html($fk['ref_table'] ?? ''); ?></td>
                  <td><?php echo html($fk['ref_column'] ?? ''); ?></td>
                  <td><?php echo html($fk['update_rule'] ?? ''); ?></td>
                  <td><?php echo html($fk['delete_rule'] ?? ''); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>

        <h3>SHOW CREATE TABLE</h3>
        <pre><?php echo html($t['create_table'] ?? ''); ?></pre>
      </details>
    <?php endforeach; ?>
  </div>

  <div class="card">
    <h2>6) Triggers / Routines / Views</h2>

    <details>
      <summary>Triggers (SHOW TRIGGERS)</summary>
      <pre><?php echo html(json_encode($report['triggers'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
    </details>

    <details>
      <summary>Procedures (SHOW PROCEDURE STATUS)</summary>
      <pre><?php echo html(json_encode($report['routines']['procedures'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
    </details>

    <details>
      <summary>Functions (SHOW FUNCTION STATUS)</summary>
      <pre><?php echo html(json_encode($report['routines']['functions'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
    </details>

    <details>
      <summary>Views (SHOW CREATE VIEW)</summary>
      <pre><?php echo html(json_encode($report['views_definition'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
    </details>
  </div>

  <div class="card">
    <h2>7) Nota para “no ensuciar”</h2>
    <div class="muted">
      Este script te dice con precisión qué existe en la BD (tablas/columnas/índices/FKs).
      Si Tom quiere crear algo, primero compara el JSON/HTML para evitar duplicados.
    </div>
  </div>

</div>
</body>
</html>
