<?php
/**
 * schema_export_v12.php (SOLO LECTURA)
 * Exporta esquema detallado (tablas/columnas/índices/FKs) de una BD MySQL/MariaDB.
 *
 * Formatos:
 * - ?format=md       (default)  => Markdown (diccionario de datos)
 * - ?format=json               => JSON estructurado
 * - ?format=mermaid            => Mermaid ER diagram
 *
 * Descarga:
 * - &download=1  => fuerza descarga como archivo
 *
 * SEGURIDAD:
 * - Token obligatorio: ?token=TU_TOKEN
 * - (Opcional) restricción por IP
 *
 * IMPORTANTE: borrar el archivo cuando termines.
 */

ini_set('display_errors', '1');
error_reporting(E_ALL);
date_default_timezone_set('America/Santiago');

/* =========================
   CONFIG (EDITA AQUÍ)
   ========================= */
$DB_HOST = 'localhost';
$DB_NAME = 'ckcl_systec_cliente1';
$DB_USER = 'ckcl_systec_cliente1';
$DB_PASS = '112233Kdoki.';

$ACCESS_TOKEN = 'Kdoki_2026_DbAudit_9f3aX_2026'; // puedes reutilizar el mismo token

// $ALLOW_IPS = ['127.0.0.1', '::1'];
$ALLOW_IPS = [];

/* =========================
   SEGURIDAD
   ========================= */
$ACCESS_TOKEN = trim((string)$ACCESS_TOKEN);
if ($ACCESS_TOKEN === '') {
  http_response_code(500);
  die("Config incompleta: \$ACCESS_TOKEN vacío.\nArchivo: " . __FILE__);
}

$token = (string)($_GET['token'] ?? '');
if (!hash_equals($ACCESS_TOKEN, $token)) {
  http_response_code(403);
  die("403 Forbidden (token inválido)\nArchivo: " . __FILE__);
}

if (!empty($ALLOW_IPS)) {
  $ip = $_SERVER['REMOTE_ADDR'] ?? '';
  if (!in_array($ip, $ALLOW_IPS, true)) {
    http_response_code(403);
    die('403 Forbidden (IP no permitida)');
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
  $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
} catch (Exception $e) {
  http_response_code(500);
  die("Error de conexión: " . $e->getMessage());
}

/* =========================
   Helpers
   ========================= */
function q(PDO $pdo, string $sql, array $params = []): array {
  $st = $pdo->prepare($sql);
  $st->execute($params);
  return $st->fetchAll();
}
function scalar(PDO $pdo, string $sql, array $params = []) {
  $st = $pdo->prepare($sql);
  $st->execute($params);
  return $st->fetchColumn();
}
function safe_ident(string $name): string {
  return '`' . str_replace('`', '``', $name) . '`';
}
function md_escape(string $s): string {
  // escape mínimo para tablas markdown
  return str_replace(['|', "\r", "\n"], ['\|', ' ', ' '], $s);
}

/* =========================
   Input
   ========================= */
$format = strtolower((string)($_GET['format'] ?? 'md'));
$download = ((string)($_GET['download'] ?? '') === '1');

/* =========================
   Recolección esquema
   ========================= */
$meta = [
  'generated_at' => date('Y-m-d H:i:s'),
  'database' => $DB_NAME,
  'server_version' => (string)scalar($pdo, "SELECT @@version"),
  'server_comment' => (string)scalar($pdo, "SELECT @@version_comment"),
];

$dbDefaults = q($pdo, "
  SELECT DEFAULT_CHARACTER_SET_NAME AS charset,
         DEFAULT_COLLATION_NAME AS collation
  FROM information_schema.SCHEMATA
  WHERE SCHEMA_NAME = DATABASE()
  LIMIT 1
");
$meta['db_defaults'] = $dbDefaults[0] ?? ['charset'=>null,'collation'=>null];

/* Tablas base (sin views) + status */
$full = q($pdo, "SHOW FULL TABLES");
$tables = [];
$views = [];
if (!empty($full)) {
  $keys = array_keys($full[0]);
  $nameKey = $keys[0];
  $typeKey = $keys[1] ?? null;
  foreach ($full as $r) {
    $name = $r[$nameKey];
    $type = $typeKey ? $r[$typeKey] : 'BASE TABLE';
    if ($type === 'VIEW') $views[] = $name;
    else $tables[] = $name;
  }
}
sort($tables);
sort($views);

$statusRows = q($pdo, "SHOW TABLE STATUS");
$statusBy = [];
foreach ($statusRows as $s) {
  if (!empty($s['Name'])) $statusBy[$s['Name']] = $s;
}

/* Columnas (por tabla) */
$colsAll = q($pdo, "
  SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, DATA_TYPE, IS_NULLABLE,
         COLUMN_DEFAULT, EXTRA, COLUMN_KEY, COLLATION_NAME, CHARACTER_SET_NAME, COLUMN_COMMENT, ORDINAL_POSITION
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
  ORDER BY TABLE_NAME, ORDINAL_POSITION
");

$columnsBy = [];
foreach ($colsAll as $c) {
  $t = $c['TABLE_NAME'];
  $columnsBy[$t][] = $c;
}

/* Índices (por tabla) */
$idxAll = q($pdo, "
  SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME, INDEX_TYPE
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
  ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX
");

$indexesBy = [];
foreach ($idxAll as $i) {
  $t = $i['TABLE_NAME'];
  $indexesBy[$t][] = $i;
}

/* Foreign Keys */
$fks = q($pdo, "
  SELECT
    kcu.TABLE_NAME,
    kcu.CONSTRAINT_NAME,
    kcu.COLUMN_NAME,
    kcu.REFERENCED_TABLE_NAME,
    kcu.REFERENCED_COLUMN_NAME
  FROM information_schema.KEY_COLUMN_USAGE kcu
  WHERE kcu.TABLE_SCHEMA = DATABASE()
    AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
  ORDER BY kcu.TABLE_NAME, kcu.CONSTRAINT_NAME, kcu.ORDINAL_POSITION
");

$rules = q($pdo, "
  SELECT
    rc.CONSTRAINT_NAME,
    rc.UPDATE_RULE,
    rc.DELETE_RULE
  FROM information_schema.REFERENTIAL_CONSTRAINTS rc
  WHERE rc.CONSTRAINT_SCHEMA = DATABASE()
");

$rulesBy = [];
foreach ($rules as $r) $rulesBy[$r['CONSTRAINT_NAME']] = $r;

$fksBy = [];
foreach ($fks as $fk) {
  $rn = $fk['CONSTRAINT_NAME'];
  $fk['UPDATE_RULE'] = $rulesBy[$rn]['UPDATE_RULE'] ?? null;
  $fk['DELETE_RULE'] = $rulesBy[$rn]['DELETE_RULE'] ?? null;
  $fksBy[$fk['TABLE_NAME']][] = $fk;
}

/* Estructura final */
$schema = [
  'meta' => $meta,
  'objects' => [
    'tables' => $tables,
    'views' => $views,
  ],
  'tables' => [],
];

foreach ($tables as $t) {
  $s = $statusBy[$t] ?? [];
  $schema['tables'][$t] = [
    'status' => [
      'engine' => $s['Engine'] ?? null,
      'rows' => $s['Rows'] ?? null,
      'collation' => $s['Collation'] ?? null,
      'auto_increment' => $s['Auto_increment'] ?? null,
    ],
    'columns' => $columnsBy[$t] ?? [],
    'indexes' => $indexesBy[$t] ?? [],
    'foreign_keys' => $fksBy[$t] ?? [],
    'create_table' => null,
  ];

  // SHOW CREATE TABLE (útil para pegar a soporte)
  try {
    $ct = q($pdo, "SHOW CREATE TABLE " . safe_ident($t));
    $createSql = '';
    if (!empty($ct[0])) {
      foreach (array_keys($ct[0]) as $k) {
        if (stripos($k, 'Create Table') !== false) { $createSql = $ct[0][$k]; break; }
      }
    }
    $schema['tables'][$t]['create_table'] = $createSql;
  } catch (Exception $e) {
    $schema['tables'][$t]['create_table'] = null;
  }
}

/* =========================
   OUTPUT: JSON
   ========================= */
if ($format === 'json') {
  if ($download) {
    header('Content-Disposition: attachment; filename="schema_'.$DB_NAME.'.json"');
  }
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
  exit;
}

/* =========================
   OUTPUT: Mermaid ER
   ========================= */
if ($format === 'mermaid') {
  $out = [];
  $out[] = "erDiagram";

  // Definición de tablas (solo columnas y PK aproximada)
  foreach ($tables as $t) {
    $out[] = "  " . $t . " {";
    $cols = $schema['tables'][$t]['columns'] ?? [];

    // detectar PK por COLUMN_KEY = 'PRI'
    foreach ($cols as $c) {
      $col = (string)$c['COLUMN_NAME'];
      $type = (string)$c['DATA_TYPE'];
      $isPk = ((string)($c['COLUMN_KEY'] ?? '') === 'PRI');
      $suffix = $isPk ? " PK" : "";
      $out[] = "    " . $type . " " . $col . $suffix;
    }
    $out[] = "  }";
    $out[] = "";
  }

  // Relaciones
  foreach ($tables as $t) {
    $fks = $schema['tables'][$t]['foreign_keys'] ?? [];
    foreach ($fks as $fk) {
      $parent = $fk['REFERENCED_TABLE_NAME'];
      $child = $fk['TABLE_NAME'];
      if (!$parent || !$child) continue;
      // Cardinalidad genérica: parent ||--o{ child
      $label = $fk['CONSTRAINT_NAME'] ?? 'fk';
      $out[] = "  " . $parent . " ||--o{ " . $child . " : \"" . $label . "\"";
    }
  }

  $text = implode("\n", $out);
  if ($download) {
    header('Content-Disposition: attachment; filename="schema_'.$DB_NAME.'.mmd"');
  }
  header('Content-Type: text/plain; charset=utf-8');
  echo $text;
  exit;
}

/* =========================
   OUTPUT: Markdown (default)
   ========================= */
$out = [];
$out[] = "# Esquema BD — {$DB_NAME}";
$out[] = "";
$out[] = "- Generado: {$meta['generated_at']}";
$out[] = "- Server: {$meta['server_version']} ({$meta['server_comment']})";
$out[] = "- DB defaults: {$meta['db_defaults']['charset']} / {$meta['db_defaults']['collation']}";
$out[] = "";
$out[] = "## Tablas (" . count($tables) . ")";
$out[] = "";
foreach ($tables as $t) {
  $out[] = "- `{$t}`";
}
$out[] = "";
if (!empty($views)) {
  $out[] = "## Vistas (" . count($views) . ")";
  $out[] = "";
  foreach ($views as $v) $out[] = "- `{$v}`";
  $out[] = "";
}

foreach ($tables as $t) {
  $info = $schema['tables'][$t];
  $st = $info['status'];

  $out[] = "---";
  $out[] = "";
  $out[] = "## `{$t}`";
  $out[] = "";
  $out[] = "- Engine: " . ($st['engine'] ?? '');
  $out[] = "- Collation: " . ($st['collation'] ?? '');
  $out[] = "- Rows (aprox): " . ($st['rows'] ?? '');
  $out[] = "- AutoIncrement: " . ($st['auto_increment'] ?? '');
  $out[] = "";

  // Columnas
  $out[] = "### Columnas";
  $out[] = "";
  $out[] = "| # | Columna | Tipo | Null | Key | Default | Extra | Charset | Collation | Comentario |";
  $out[] = "|---:|---|---|---|---|---|---|---|---|---|";
  $cols = $info['columns'] ?? [];
  foreach ($cols as $c) {
    $out[] =
      "|" . (int)$c['ORDINAL_POSITION'] .
      "|`" . md_escape((string)$c['COLUMN_NAME']) . "`" .
      "|" . md_escape((string)$c['COLUMN_TYPE']) .
      "|" . md_escape((string)$c['IS_NULLABLE']) .
      "|" . md_escape((string)($c['COLUMN_KEY'] ?? '')) .
      "|" . md_escape((string)($c['COLUMN_DEFAULT'] ?? '')) .
      "|" . md_escape((string)($c['EXTRA'] ?? '')) .
      "|" . md_escape((string)($c['CHARACTER_SET_NAME'] ?? '')) .
      "|" . md_escape((string)($c['COLLATION_NAME'] ?? '')) .
      "|" . md_escape((string)($c['COLUMN_COMMENT'] ?? '')) .
      "|";
  }
  $out[] = "";

  // Índices
  $out[] = "### Índices";
  $out[] = "";
  $out[] = "| Index | Unique | Tipo | Columnas |";
  $out[] = "|---|---|---|---|";

  $idx = $info['indexes'] ?? [];
  $idxGroup = [];
  foreach ($idx as $i) {
    $k = $i['INDEX_NAME'];
    $idxGroup[$k]['unique'] = ((int)$i['NON_UNIQUE'] === 0) ? 'YES' : 'NO';
    $idxGroup[$k]['type'] = $i['INDEX_TYPE'] ?? '';
    $idxGroup[$k]['cols'][] = $i['COLUMN_NAME'];
  }
  foreach ($idxGroup as $name => $g) {
    $out[] = "|`" . md_escape((string)$name) . "`|{$g['unique']}|" . md_escape((string)($g['type'] ?? '')) . "|`" . md_escape(implode('`, `', $g['cols'] ?? [])) . "`|";
  }
  $out[] = "";

  // FKs
  $out[] = "### Foreign Keys";
  $out[] = "";
  $fksT = $info['foreign_keys'] ?? [];
  if (empty($fksT)) {
    $out[] = "_(sin FKs)_";
  } else {
    $out[] = "| Constraint | Columna | Ref tabla | Ref columna | On Update | On Delete |";
    $out[] = "|---|---|---|---|---|---|";
    foreach ($fksT as $fk) {
      $out[] =
        "|`" . md_escape((string)$fk['CONSTRAINT_NAME']) . "`" .
        "|`" . md_escape((string)$fk['COLUMN_NAME']) . "`" .
        "|`" . md_escape((string)$fk['REFERENCED_TABLE_NAME']) . "`" .
        "|`" . md_escape((string)$fk['REFERENCED_COLUMN_NAME']) . "`" .
        "|" . md_escape((string)($fk['UPDATE_RULE'] ?? '')) .
        "|" . md_escape((string)($fk['DELETE_RULE'] ?? '')) .
        "|";
    }
  }
  $out[] = "";

  // CREATE TABLE
  $out[] = "### SHOW CREATE TABLE";
  $out[] = "";
  $out[] = "```sql";
  $out[] = (string)($info['create_table'] ?? '');
  $out[] = "```";
  $out[] = "";
}

$text = implode("\n", $out);

if ($download) {
  header('Content-Disposition: attachment; filename="schema_'.$DB_NAME.'.md"');
}
header('Content-Type: text/plain; charset=utf-8');
echo $text;
exit;
