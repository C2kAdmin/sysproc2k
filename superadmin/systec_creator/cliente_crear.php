<?php
declare(strict_types=1);

// superadmin/systec_creator/cliente_crear.php

require_once __DIR__ . '/_config/config.php';
require_once __DIR__ . '/_config/auth.php';

require_super_admin();

$errors = [];
$generatedSql = '';

// ===============================
// Seeds fijos (para TODOS los clientes)
// ===============================
// Tu usuario personal (oculto, siempre)
define('SYSTEC_SEED_SUPERADMIN_NAME', 'Mikel SuperAdmin');
define('SYSTEC_SEED_SUPERADMIN_USER', 'superadmin');
define('SYSTEC_SEED_SUPERADMIN_EMAIL', 'mikeldng@c2k.cl');
define('SYSTEC_SEED_SUPERADMIN_PASS', '112233Kdoki.');

// Usuario del cliente (siempre)
define('SYSTEC_SEED_CLIENTADMIN_NAME_PREFIX', 'Admin ');
define('SYSTEC_SEED_CLIENTADMIN_USER', 'admin');
define('SYSTEC_SEED_CLIENTADMIN_PASS', '112233');

function sa_valid_slug(string $slug): bool {
    return (bool)preg_match('/^[a-z0-9_-]+$/', $slug);
}
function sa_valid_dbname(string $db): bool {
    return (bool)preg_match('/^[a-zA-Z0-9_]+$/', $db);
}
function sa_sql_ident(string $ident): string {
    $ident = str_replace('`', '', $ident);
    return '`' . $ident . '`';
}
function sa_sql_quote(string $s): string {
    $s = str_replace("\\", "\\\\", $s);
    $s = str_replace("'", "\\'", $s);
    return "'" . $s . "'";
}
function sa_write_file(string $path, string $content): bool {
    $dir = dirname($path);
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true)) return false;
    }
    return file_put_contents($path, $content) !== false;
}
function sa_mkdir(string $path): bool {
    if (is_dir($path)) return true;
    return mkdir($path, 0755, true);
}
function sa_client_pdo(string $db_host, string $db_name, string $db_user, string $db_pass): PDO {
    $dsn = "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4";
    return new PDO($dsn, $db_user, $db_pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
}

/* =========================
   LOG INSTALL (FULL PRO)
   ========================= */
function sa_install_log_append(?string $logPath, string $line): void {
    if (!$logPath) return;
    $dir = dirname($logPath);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);

    $ts = date('Y-m-d H:i:s');
    @file_put_contents($logPath, "[{$ts}] {$line}\n", FILE_APPEND);
}

function sa_table_exists(PDO $pdo, string $table): bool {
    $st = $pdo->prepare("SHOW TABLES LIKE :t");
    $st->execute([':t' => $table]);
    return (bool)$st->fetchColumn();
}
function sa_column_exists(PDO $pdo, string $table, string $col): bool {
    $st = $pdo->query("SHOW COLUMNS FROM `{$table}`");
    $cols = $st->fetchAll();
    foreach ($cols as $c) {
        if (isset($c['Field']) && (string)$c['Field'] === $col) return true;
    }
    return false;
}
function sa_index_exists(PDO $pdo, string $table, string $keyName): bool {
    $st = $pdo->prepare("SHOW INDEX FROM `{$table}` WHERE Key_name = :k");
    $st->execute([':k' => $keyName]);
    return (bool)$st->fetch();
}

function sa_ensure_usuarios_table(PDO $pdoClient): void {
    $pdoClient->exec("
        CREATE TABLE IF NOT EXISTS `usuarios` (
          `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `nombre` VARCHAR(180) NOT NULL,
          `usuario` VARCHAR(60) NOT NULL,
          `email` VARCHAR(180) NOT NULL,
          `password_hash` VARCHAR(255) NOT NULL,
          `rol` VARCHAR(30) NOT NULL DEFAULT 'RECEPCION',
          `activo` TINYINT(1) NOT NULL DEFAULT 1,
          `is_super_admin` TINYINT(1) NOT NULL DEFAULT 0,
          `must_change_password` TINYINT(1) NOT NULL DEFAULT 0,
          `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` DATETIME NULL DEFAULT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uq_usuarios_usuario` (`usuario`),
          UNIQUE KEY `uq_usuarios_email` (`email`)
        ) ENGINE=InnoDB
          DEFAULT CHARSET=utf8mb4
          COLLATE=utf8mb4_unicode_ci;
    ");

    // parche por si existía una tabla vieja sin must_change_password
    if (sa_column_exists($pdoClient, 'usuarios', 'must_change_password') === false) {
        $pdoClient->exec("ALTER TABLE `usuarios` ADD COLUMN `must_change_password` TINYINT(1) NOT NULL DEFAULT 0");
    }
}

function sa_seed_usuario(PDO $pdoClient, string $nombre, string $usuario, string $email, string $pass, string $rol, int $is_super_admin, int $must_change_password = 0): void {
    $email = strtolower(trim($email));
    $usuario = trim($usuario);
    $nombre = trim($nombre);

    $st = $pdoClient->prepare("SELECT id FROM usuarios WHERE usuario = :u OR email = :e LIMIT 1");
    $st->execute([':u' => $usuario, ':e' => $email]);
    if ($st->fetch()) return;

    $hash = password_hash($pass, PASSWORD_DEFAULT);

    // si tabla no tiene must_change_password (caso ultra viejo), lo ignoramos
    $hasMust = sa_column_exists($pdoClient, 'usuarios', 'must_change_password');

    if ($hasMust) {
        $st = $pdoClient->prepare("
            INSERT INTO usuarios (nombre, usuario, email, password_hash, rol, activo, is_super_admin, must_change_password)
            VALUES (:n, :u, :e, :ph, :rol, 1, :isa, :mcp)
        ");
        $st->execute([
            ':n'   => $nombre,
            ':u'   => $usuario,
            ':e'   => $email,
            ':ph'  => $hash,
            ':rol' => $rol,
            ':isa' => $is_super_admin,
            ':mcp' => $must_change_password,
        ]);
    } else {
        $st = $pdoClient->prepare("
            INSERT INTO usuarios (nombre, usuario, email, password_hash, rol, activo, is_super_admin)
            VALUES (:n, :u, :e, :ph, :rol, 1, :isa)
        ");
        $st->execute([
            ':n'   => $nombre,
            ':u'   => $usuario,
            ':e'   => $email,
            ':ph'  => $hash,
            ':rol' => $rol,
            ':isa' => $is_super_admin,
        ]);
    }
}

function sa_starts_with(string $haystack, string $needle): bool {
    return substr($haystack, 0, strlen($needle)) === $needle;
}

/**
 * Split SQL en statements (evita cortar mal por ';' dentro de strings).
 */
function sa_sql_split_statements(string $sql): array {
    $sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql);

    $out = [];
    $buf = '';
    $len = strlen($sql);

    $inSingle = false;
    $inDouble = false;
    $inBacktick = false;
    $escape = false;

    for ($i = 0; $i < $len; $i++) {
        $ch = $sql[$i];

        if ($escape) {
            $buf .= $ch;
            $escape = false;
            continue;
        }

        if ($ch === "\\") {
            $buf .= $ch;
            $escape = true;
            continue;
        }

        if (!$inDouble && !$inBacktick && $ch === "'") {
            $inSingle = !$inSingle;
            $buf .= $ch;
            continue;
        }
        if (!$inSingle && !$inBacktick && $ch === '"') {
            $inDouble = !$inDouble;
            $buf .= $ch;
            continue;
        }
        if (!$inSingle && !$inDouble && $ch === '`') {
            $inBacktick = !$inBacktick;
            $buf .= $ch;
            continue;
        }

        if (!$inSingle && !$inDouble && !$inBacktick && $ch === ';') {
            $stmt = trim($buf);
            if ($stmt !== '') $out[] = $stmt;
            $buf = '';
            continue;
        }

        $buf .= $ch;
    }

    $tail = trim($buf);
    if ($tail !== '') $out[] = $tail;

    return $out;
}

function sa_schema_extract_version(string $raw): string {
    if (preg_match('/SCHEMA_VERSION:\s*([A-Za-z0-9._-]+)/', $raw, $m)) {
        return (string)$m[1];
    }
    return 'unknown';
}

function sa_ensure_schema_migrations(PDO $pdoClient): void {
    $pdoClient->exec("
        CREATE TABLE IF NOT EXISTS `schema_migrations` (
          `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `schema_version` VARCHAR(60) NOT NULL,
          `schema_hash` CHAR(64) NOT NULL,
          `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `applied_by` VARCHAR(60) NULL DEFAULT NULL,
          `notes` VARCHAR(255) NULL DEFAULT NULL,
          PRIMARY KEY (`id`),
          KEY `idx_schema_version` (`schema_version`),
          KEY `idx_schema_hash` (`schema_hash`)
        ) ENGINE=InnoDB
          DEFAULT CHARSET=utf8mb4
          COLLATE utf8mb4_unicode_ci;
    ");
}

function sa_schema_record(PDO $pdoClient, string $version, string $hash, string $by, string $notes): void {
    sa_ensure_schema_migrations($pdoClient);
    $st = $pdoClient->prepare("
        INSERT INTO schema_migrations (schema_version, schema_hash, applied_by, notes)
        VALUES (:v, :h, :b, :n)
    ");
    $st->execute([':v'=>$version, ':h'=>$hash, ':b'=>$by, ':n'=>$notes]);
}

/**
 * Aplica schema desde archivo (si existe).
 * Filtra statements problemáticos: CREATE DATABASE / ALTER DATABASE / USE.
 * FULL PRO: log + conteo + error exacto.
 */
function sa_install_schema_from_file(PDO $pdoClient, string $schemaFile, ?string $logPath = null): array {
    if (!is_file($schemaFile)) {
        return ['ok' => false, 'msg' => 'schema.sql no encontrado.'];
    }

    $raw = (string)file_get_contents($schemaFile);
    $hash = hash('sha256', $raw);
    $version = sa_schema_extract_version($raw);

    sa_install_log_append($logPath, "Schema file: {$schemaFile}");
    sa_install_log_append($logPath, "Schema version: {$version}");
    sa_install_log_append($logPath, "Schema hash: {$hash}");

    // remover comentarios (mantener header para version ya extraída)
    $body = preg_replace('/^\s*--.*$/m', '', $raw);
    $body = preg_replace('/\/\*.*?\*\//s', '', $body);

    $stmts = sa_sql_split_statements($body);

    $total = count($stmts);
    $applied = 0;

    $t0 = microtime(true);

    foreach ($stmts as $i => $s) {
        $t = ltrim($s);
        $u = strtoupper(substr($t, 0, 20));

        if (sa_starts_with($u, 'CREATE DATABASE') || sa_starts_with($u, 'ALTER DATABASE') || sa_starts_with($u, 'USE ')) {
            sa_install_log_append($logPath, "SKIP stmt#" . ($i+1) . " (db-level)");
            continue;
        }

        try {
            $pdoClient->exec($s);
            $applied++;
            sa_install_log_append($logPath, "OK stmt#" . ($i+1));
        } catch (Exception $e) {
            $excerpt = substr(preg_replace('/\s+/', ' ', $s), 0, 220);
            sa_install_log_append($logPath, "FAIL stmt#" . ($i+1) . " => " . $e->getMessage());
            sa_install_log_append($logPath, "STMT excerpt: {$excerpt}");

            $ms = (int)round((microtime(true) - $t0) * 1000);

            return [
                'ok' => false,
                'msg' => "Fallo aplicando schema (stmt " . ($i+1) . "/{$total}).",
                'version' => $version,
                'hash' => $hash,
                'total' => $total,
                'applied' => $applied,
                'ms' => $ms,
                'failed_stmt' => ($i+1),
                'failed_excerpt' => $excerpt,
            ];
        }
    }

    $ms = (int)round((microtime(true) - $t0) * 1000);
    sa_install_log_append($logPath, "DONE. Applied={$applied}/{$total} in {$ms}ms");

    return [
        'ok' => true,
        'msg' => "Schema aplicado desde archivo ({$applied}/{$total} statements, {$ms}ms).",
        'version' => $version,
        'hash' => $hash,
        'total' => $total,
        'applied' => $applied,
        'ms' => $ms,
    ];
}

/**
 * Parches mínimos obligatorios (anti “Unknown column”):
 * - ordenes.estado_actual
 * - ordenes.fecha_ingreso
 * - índices base
 */
function sa_schema_patch_required(PDO $pdoClient, ?string $logPath = null): array {
    $notes = [];

    if (!sa_table_exists($pdoClient, 'ordenes')) {
        // si no existe, la creamos mínima
        $pdoClient->exec("
            CREATE TABLE IF NOT EXISTS `ordenes` (
              `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `estado_actual` VARCHAR(30) NOT NULL DEFAULT 'INGRESADA',
              `fecha_ingreso` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` DATETIME NULL DEFAULT NULL,
              PRIMARY KEY (`id`),
              KEY `idx_ordenes_estado_actual` (`estado_actual`),
              KEY `idx_ordenes_fecha_ingreso` (`fecha_ingreso`)
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE utf8mb4_unicode_ci;
        ");
        $notes[] = "ordenes creada (fallback mínimo)";
        sa_install_log_append($logPath, "PATCH: created table ordenes (fallback)");
    } else {
        if (!sa_column_exists($pdoClient, 'ordenes', 'estado_actual')) {
            $pdoClient->exec("ALTER TABLE `ordenes` ADD COLUMN `estado_actual` VARCHAR(30) NOT NULL DEFAULT 'INGRESADA'");
            $notes[] = "ordenes.estado_actual agregado";
            sa_install_log_append($logPath, "PATCH: add ordenes.estado_actual");
        }
        if (!sa_column_exists($pdoClient, 'ordenes', 'fecha_ingreso')) {
            $pdoClient->exec("ALTER TABLE `ordenes` ADD COLUMN `fecha_ingreso` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");
            $notes[] = "ordenes.fecha_ingreso agregado";
            sa_install_log_append($logPath, "PATCH: add ordenes.fecha_ingreso");
        }
        if (!sa_index_exists($pdoClient, 'ordenes', 'idx_ordenes_estado_actual')) {
            $pdoClient->exec("CREATE INDEX `idx_ordenes_estado_actual` ON `ordenes` (`estado_actual`)");
            $notes[] = "idx_ordenes_estado_actual creado";
            sa_install_log_append($logPath, "PATCH: create index idx_ordenes_estado_actual");
        }
        if (!sa_index_exists($pdoClient, 'ordenes', 'idx_ordenes_fecha_ingreso')) {
            $pdoClient->exec("CREATE INDEX `idx_ordenes_fecha_ingreso` ON `ordenes` (`fecha_ingreso`)");
            $notes[] = "idx_ordenes_fecha_ingreso creado";
            sa_install_log_append($logPath, "PATCH: create index idx_ordenes_fecha_ingreso");
        }
    }

    // usuarios must_change_password
    sa_ensure_usuarios_table($pdoClient);

    return [
        'ok' => true,
        'msg' => empty($notes) ? 'Parches OK (sin cambios).' : ('Parches aplicados: ' . implode(' | ', $notes)),
    ];
}

/**
 * Instala schema automático:
 * - Si existe CORE v1.2/_db/schema.sql => lo aplica
 * - Si no existe => crea mínimo (via patch_required + ensure_usuarios)
 * - FULL PRO: registra schema_migrations + log
 */
function sa_install_schema_auto(PDO $pdoClient, string $corePath, ?string $logPath = null): array {
    $corePath = rtrim($corePath, '/');

    $candidates = [
        $corePath . '/_db/schema.sql',
        $corePath . '/schema.sql',
    ];

    foreach ($candidates as $f) {
        if (is_file($f)) {
            $r = sa_install_schema_from_file($pdoClient, $f, $logPath);
            if (!empty($r['ok'])) {
                // registro migración
                try {
                    sa_schema_record($pdoClient, (string)$r['version'], (string)$r['hash'], SYSTEC_SEED_SUPERADMIN_USER, 'auto-install from file');
                } catch (Exception $e) {
                    sa_install_log_append($logPath, "WARN schema_migrations: " . $e->getMessage());
                }
                return $r;
            }
            // si falla el archivo, hacemos fallback pero dejamos registro en log
            sa_install_log_append($logPath, "WARN: schema file failed. Fallback to patches.");
            break;
        }
    }

    // fallback mínimo
    sa_ensure_usuarios_table($pdoClient);
    $patch = sa_schema_patch_required($pdoClient, $logPath);

    // registrar “fallback”
    try {
        sa_schema_record($pdoClient, 'fallback-min', hash('sha256', 'fallback-min'), SYSTEC_SEED_SUPERADMIN_USER, 'fallback minimal schema');
    } catch (Exception $e) {
        sa_install_log_append($logPath, "WARN schema_migrations: " . $e->getMessage());
    }

    return ['ok' => true, 'msg' => 'Schema fallback aplicado (mínimo) + ' . $patch['msg'], 'version'=>'fallback-min', 'hash'=>hash('sha256','fallback-min')];
}

function sa_build_install_sql(string $slug, string $db_name, string $admin_email, string $admin_nombre): string {
    $dbIdent = sa_sql_ident($db_name);

    $super_name = SYSTEC_SEED_SUPERADMIN_NAME;
    $super_user = SYSTEC_SEED_SUPERADMIN_USER;
    $super_mail = SYSTEC_SEED_SUPERADMIN_EMAIL;
    $super_pass = SYSTEC_SEED_SUPERADMIN_PASS;

    $client_user = SYSTEC_SEED_CLIENTADMIN_USER;
    $client_pass = SYSTEC_SEED_CLIENTADMIN_PASS;

    if (trim($admin_email) === '') {
        $slugMail = str_replace('_', '-', $slug);
        $admin_email = 'admin@' . $slugMail . '.c2k.cl';
    }
    if (trim($admin_nombre) === '') {
        $admin_nombre = SYSTEC_SEED_CLIENTADMIN_NAME_PREFIX . strtoupper($slug);
    }

    $sql = [];
    $sql[] = "-- =========================================================";
    $sql[] = "-- SysTec v1.2 — SQL Instalación (generado por SysTec Creator)";
    $sql[] = "-- DB objetivo: {$db_name}";
    $sql[] = "-- =========================================================";
    $sql[] = "";
    $sql[] = "-- (Opcional) CREATE DATABASE: en cPanel a veces está bloqueado.";
    $sql[] = "CREATE DATABASE IF NOT EXISTS {$dbIdent} DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;";
    $sql[] = "ALTER DATABASE {$dbIdent} DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;";
    $sql[] = "";
    $sql[] = "USE {$dbIdent};";
    $sql[] = "";

    $sql[] = "-- usuarios (mínimo)";
    $sql[] = "CREATE TABLE IF NOT EXISTS `usuarios` (";
    $sql[] = "  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,";
    $sql[] = "  `nombre` VARCHAR(180) NOT NULL,";
    $sql[] = "  `usuario` VARCHAR(60) NOT NULL,";
    $sql[] = "  `email` VARCHAR(180) NOT NULL,";
    $sql[] = "  `password_hash` VARCHAR(255) NOT NULL,";
    $sql[] = "  `rol` VARCHAR(30) NOT NULL DEFAULT 'RECEPCION',";
    $sql[] = "  `activo` TINYINT(1) NOT NULL DEFAULT 1,";
    $sql[] = "  `is_super_admin` TINYINT(1) NOT NULL DEFAULT 0,";
    $sql[] = "  `must_change_password` TINYINT(1) NOT NULL DEFAULT 0,";
    $sql[] = "  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,";
    $sql[] = "  `updated_at` DATETIME NULL DEFAULT NULL,";
    $sql[] = "  PRIMARY KEY (`id`),";
    $sql[] = "  UNIQUE KEY `uq_usuarios_usuario` (`usuario`),";
    $sql[] = "  UNIQUE KEY `uq_usuarios_email` (`email`)";
    $sql[] = ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $sql[] = "";

    $sql[] = "-- ordenes (mínimo para dashboard v1.2)";
    $sql[] = "CREATE TABLE IF NOT EXISTS `ordenes` (";
    $sql[] = "  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,";
    $sql[] = "  `estado_actual` VARCHAR(30) NOT NULL DEFAULT 'INGRESADA',";
    $sql[] = "  `fecha_ingreso` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,";
    $sql[] = "  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,";
    $sql[] = "  `updated_at` DATETIME NULL DEFAULT NULL,";
    $sql[] = "  PRIMARY KEY (`id`),";
    $sql[] = "  KEY `idx_ordenes_estado_actual` (`estado_actual`),";
    $sql[] = "  KEY `idx_ordenes_fecha_ingreso` (`fecha_ingreso`)";
    $sql[] = ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $sql[] = "";

    // Seeds
    $hashSuper = password_hash($super_pass, PASSWORD_DEFAULT);
    $hashAdmin = password_hash($client_pass, PASSWORD_DEFAULT);

    $sn  = sa_sql_quote($super_name);
    $su  = sa_sql_quote($super_user);
    $se  = sa_sql_quote(strtolower($super_mail));
    $sph = sa_sql_quote($hashSuper);

    $an  = sa_sql_quote($admin_nombre);
    $au  = sa_sql_quote($client_user);
    $ae  = sa_sql_quote(strtolower($admin_email));
    $aph = sa_sql_quote($hashAdmin);

    $sql[] = "-- Seed SUPERADMIN (interno)";
    $sql[] = "INSERT INTO `usuarios` (`nombre`,`usuario`,`email`,`password_hash`,`rol`,`activo`,`is_super_admin`,`must_change_password`)";
    $sql[] = "SELECT {$sn}, {$su}, {$se}, {$sph}, 'SUPER_ADMIN', 1, 1, 0";
    $sql[] = "WHERE NOT EXISTS (SELECT 1 FROM `usuarios` WHERE `usuario` = {$su} OR `email` = {$se});";
    $sql[] = "";

    $sql[] = "-- Seed ADMIN (cliente) / pass provisoria: 112233";
    $sql[] = "INSERT INTO `usuarios` (`nombre`,`usuario`,`email`,`password_hash`,`rol`,`activo`,`is_super_admin`,`must_change_password`)";
    $sql[] = "SELECT {$an}, {$au}, {$ae}, {$aph}, 'ADMIN', 1, 0, 1";
    $sql[] = "WHERE NOT EXISTS (SELECT 1 FROM `usuarios` WHERE `usuario` = {$au} OR `email` = {$ae});";
    $sql[] = "";

    return implode("\n", $sql);
}

// Defaults
$slug             = '';
$nombre_comercial = '';
$db_host          = 'localhost'; // fijo
$db_name          = '';
$db_user          = '';
$db_pass          = '';
$activo           = 1;
$core_version     = 'v1.2';
$base_url_public  = ''; // oculto, siempre auto

// ADMIN cliente (visible, simple)
$admin_nombre  = '';
$admin_email   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $mode = (string)($_POST['_mode'] ?? 'create'); // create | sql

    $slug             = strtolower(sa_post('slug'));
    $nombre_comercial = sa_post('nombre_comercial');
    $db_host          = 'localhost'; // fijo aunque venga algo raro
    $db_name          = sa_post('db_name');
    $db_user          = sa_post('db_user');
    $db_pass          = (string)($_POST['db_pass'] ?? '');
    $activo           = isset($_POST['activo']) ? 1 : 0;
    $core_version     = sa_post('core_version', 'v1.2');

    $admin_nombre = sa_post('admin_nombre');
    $admin_email  = sa_post('admin_email');

    if ($slug === '' || !sa_valid_slug($slug)) {
        $errors[] = 'Identificador inválido. Usa solo a-z 0-9 _ - (minúsculas, sin espacios).';
    }

    if ($db_name === '' || $db_user === '' || $db_pass === '') {
        $errors[] = 'DB name/user/pass son obligatorios.';
    }

    if ($db_name !== '' && !sa_valid_dbname($db_name)) {
        $errors[] = 'DB Name inválido. Usa solo letras/números/underscore (ej: ckcl_systec_cliente2).';
    }

    // Autopreset admin cliente
    if (trim($admin_nombre) === '' && $slug !== '') {
        $admin_nombre = SYSTEC_SEED_CLIENTADMIN_NAME_PREFIX . strtoupper($slug);
    }
    if (trim($admin_email) === '' && $slug !== '') {
        $slugMail = str_replace('_', '-', $slug);
        $admin_email = 'admin@' . $slugMail . '.c2k.cl';
    }

    if (!filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email del ADMIN del cliente no es válido (debe contener @).';
    }

    // base_url_public SIEMPRE automático (campo oculto)
    $https  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ((string)($_SERVER['SERVER_PORT'] ?? '') === '443');
    $scheme = $https ? 'https://' : 'http://';
    $host   = (string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost');

    $script = str_replace('\\','/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $pos = strpos($script, '/superadmin/systec_creator');
    $sysproWeb = ($pos !== false) ? substr($script, 0, $pos) : rtrim(dirname($script), '/');
    $sysproWeb = rtrim($sysproWeb, '/');

    $pubPath = $sysproWeb . '/systec/_clients/' . $slug . '/tec/public/';
    $base_url_public = $scheme . $host . $pubPath;

    // MODO SQL: generar script instalador (no ejecuta nada)
    if ($mode === 'sql' && empty($errors)) {
        $generatedSql = sa_build_install_sql($slug, $db_name, $admin_email, $admin_nombre);
    }

    if ($mode === 'create') {

        // Validar core_version exista físicamente
        $corePath = SYSTEC_ROOT ? (SYSTEC_ROOT . '/_cores/systec/' . $core_version) : '';
        if (!$corePath || !is_dir($corePath) || !is_file($corePath . '/router.php')) {
            $errors[] = 'core_version no existe en el servidor (' . htmlspecialchars($core_version, ENT_QUOTES, 'UTF-8') . ').';
        }

        // Validar duplicados en BD master y FS
        if (empty($errors)) {
            try {
                $pdo = sa_pdo();

                $st = $pdo->prepare("SELECT id FROM systec_clientes WHERE slug = :s LIMIT 1");
                $st->execute([':s' => $slug]);
                if ($st->fetch()) {
                    $errors[] = 'Ese cliente ya existe en la BD master.';
                }
            } catch (Exception $e) {
                $errors[] = 'No se pudo consultar BD master (revisa credenciales del Creator).';
            }

            $fsClientRoot = SYSTEC_CLIENTS_ROOT ? (SYSTEC_CLIENTS_ROOT . '/' . $slug) : '';
            if ($fsClientRoot && is_dir($fsClientRoot)) {
                $errors[] = 'Ya existe carpeta en _clients/' . $slug;
            }
        }

        // Crear instancia (carpetas + archivos + schema + seeds + registro)
        if (empty($errors)) {

            $tecRoot      = SYSTEC_CLIENTS_ROOT . '/' . $slug . '/tec';
            $publicDir    = $tecRoot . '/public';
            $configDir    = $tecRoot . '/config';
            $storageDir   = $tecRoot . '/storage';

            $instancePath = $configDir . '/instance.php';
            $storagePath  = $storageDir;

            $publicIndex  = $publicDir . '/index.php';
            $publicHt     = $publicDir . '/.htaccess';

            // Log instalación (por cliente)
            $installLogPath = $storageDir . '/logs/install_' . $slug . '_' . date('Ymd_His') . '.log';

            // Crear dirs
            $ok = true;
            $ok = $ok && sa_mkdir($publicDir);
            $ok = $ok && sa_mkdir($configDir);
            $ok = $ok && sa_mkdir($storageDir);

            $ok = $ok && sa_mkdir($storageDir . '/evidencias');
            $ok = $ok && sa_mkdir($storageDir . '/firmas');
            $ok = $ok && sa_mkdir($storageDir . '/branding');
            $ok = $ok && sa_mkdir($storageDir . '/logs');

            if (!$ok) {
                $errors[] = 'No se pudieron crear carpetas (revisa permisos del servidor).';
            } else {

                sa_install_log_append($installLogPath, "=== SysTec Creator install start ===");
                sa_install_log_append($installLogPath, "Slug={$slug} DB={$db_name} Core={$core_version}");
                sa_install_log_append($installLogPath, "BaseURL={$base_url_public}");

                // public/index.php (puente)
                $indexTpl = <<<PHP
<?php
/**
 * Puente INSTANCIA -> CORE
 * Cliente: {$slug}
 * Versión Core: {$core_version}
 */

define('SYSTEC_CORE_PATH', realpath(__DIR__ . '/../../../../_cores/systec/{$core_version}'));
define('SYSTEC_INSTANCE_PATH', realpath(__DIR__ . '/../config/instance.php'));

if (!SYSTEC_CORE_PATH || !is_dir(SYSTEC_CORE_PATH)) exit('CORE no encontrado');
if (!SYSTEC_INSTANCE_PATH || !is_file(SYSTEC_INSTANCE_PATH)) exit('instance.php no encontrado');

// APP_URL de ESTA instancia
\$base = rtrim(str_replace('\\\\','/', dirname(\$_SERVER['SCRIPT_NAME'] ?? '')), '/');
if (\$base === '/' || \$base === '') \$base = '';
define('SYSTEC_APP_URL', \$base . '/');

// display_errors depende de ENV
\$__cfg = require SYSTEC_INSTANCE_PATH;
\$__env = strtolower((string)(\$__cfg['ENV'] ?? 'prod'));

if (\$__env === 'dev') {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL);
}

// CORE_URL para assets
\$docRoot = realpath(\$_SERVER['DOCUMENT_ROOT'] ?? '');
\$coreFs  = realpath(SYSTEC_CORE_PATH);

\$coreRel = '';
if (\$docRoot && \$coreFs && strpos(\$coreFs, \$docRoot) === 0) {
    \$coreRel = str_replace('\\\\','/', substr(\$coreFs, strlen(\$docRoot)));
    \$coreRel = '/' . ltrim(\$coreRel, '/');
}
\$coreRel = rtrim(\$coreRel, '/');
define('SYSTEC_CORE_URL', \$coreRel);

require SYSTEC_CORE_PATH . '/router.php';
PHP;

                // .htaccess
                $htTpl = "Options -Indexes\n\n" .
"RewriteEngine On\n\n" .
"RewriteCond %{REQUEST_FILENAME} -f [OR]\n" .
"RewriteCond %{REQUEST_FILENAME} -d\n" .
"RewriteRule ^ - [L]\n\n" .
"RewriteRule ^ index.php [L]\n";

                // instance.php
                $esc = function(string $v): string {
                    return str_replace(['\\', "'"], ['\\\\', "\\'"], $v);
                };

                $db_host_e  = $esc($db_host);
                $db_name_e  = $esc($db_name);
                $db_user_e  = $esc($db_user);
                $db_pass_e  = $esc($db_pass);
                $base_url_e = $esc($base_url_public);

                $instanceTpl = <<<PHP
<?php
return [
    'ENV' => 'dev',
    'SYSTEC_VERSION' => '{$core_version}',

    // APP_URL lo toma del puente (SYSTEC_APP_URL).
    'APP_URL' => (defined('SYSTEC_APP_URL') ? SYSTEC_APP_URL : '/sysproc2k/systec/_clients/{$slug}/tec/public/'),
    'APP_URL_PUBLIC' => '{$base_url_e}',

    'DB_HOST' => '{$db_host_e}',
    'DB_NAME' => '{$db_name_e}',
    'DB_USER' => '{$db_user_e}',
    'DB_PASS' => '{$db_pass_e}',

    'STORAGE_PATH' => (realpath(__DIR__ . '/../storage') ?: (__DIR__ . '/../storage')),
    'LOG_PATH' => (realpath(__DIR__ . '/../storage/logs') ?: (__DIR__ . '/../storage/logs')),
];
PHP;

                if (!sa_write_file($publicIndex, $indexTpl)) $errors[] = 'No se pudo escribir public/index.php';
                if (!sa_write_file($publicHt, $htTpl)) $errors[] = 'No se pudo escribir public/.htaccess';
                if (!sa_write_file($instancePath, $instanceTpl)) $errors[] = 'No se pudo escribir config/instance.php';

                // Schema + Seeds + registrar en BD master
                if (empty($errors)) {
                    try {
                        $note = '';
                        $type = 'success';

                        // 1) Conectar DB cliente
                        $pdoClient = sa_client_pdo($db_host, $db_name, $db_user, $db_pass);
                        sa_install_log_append($installLogPath, "DB connect OK");

                        // 2) Instalar schema (archivo si existe, si no fallback)
                        $schemaResult = sa_install_schema_auto($pdoClient, $corePath, $installLogPath);

                        // 3) Parches obligatorios (anti “Unknown column”)
                        $patchResult = sa_schema_patch_required($pdoClient, $installLogPath);

                        // 4) Seed usuarios
                        sa_ensure_usuarios_table($pdoClient);

                        // superadmin (tuyo) NO debe forzar cambio clave
                        sa_seed_usuario(
                            $pdoClient,
                            SYSTEC_SEED_SUPERADMIN_NAME,
                            SYSTEC_SEED_SUPERADMIN_USER,
                            SYSTEC_SEED_SUPERADMIN_EMAIL,
                            SYSTEC_SEED_SUPERADMIN_PASS,
                            'SUPER_ADMIN',
                            1,
                            0
                        );

                        // admin del cliente SÍ debe cambiar clave
                        sa_seed_usuario(
                            $pdoClient,
                            $admin_nombre,
                            SYSTEC_SEED_CLIENTADMIN_USER,
                            $admin_email,
                            SYSTEC_SEED_CLIENTADMIN_PASS,
                            'ADMIN',
                            0,
                            1
                        );

                        sa_install_log_append($installLogPath, "SEEDS OK: superadmin + admin(must_change_password=1)");

                        // 5) Registrar en BD master (al final, solo si todo lo anterior pasó)
                        $pdo = sa_pdo();
                        $ins = $pdo->prepare("INSERT INTO systec_clientes
                            (slug, nombre_comercial, activo, core_version, base_url_public, instance_path, storage_path, db_host, db_name, db_user, db_pass, created_at)
                            VALUES
                            (:slug, :nom, :act, :core, :url, :ipath, :spath, :h, :dn, :du, :dp, NOW())");

                        $ins->execute([
                            ':slug'  => $slug,
                            ':nom'   => $nombre_comercial,
                            ':act'   => $activo,
                            ':core'  => $core_version,
                            ':url'   => $base_url_public,
                            ':ipath' => $instancePath,
                            ':spath' => $storagePath,
                            ':h'     => $db_host,
                            ':dn'    => $db_name,
                            ':du'    => $db_user,
                            ':dp'    => $db_pass,
                        ]);

                        $note = " | {$schemaResult['msg']} | {$patchResult['msg']} | Usuarios OK (admin pass 112233)";
                        sa_install_log_append($installLogPath, "MASTER REGISTER OK");
                        sa_install_log_append($installLogPath, "Install log: {$installLogPath}");
                        sa_install_log_append($installLogPath, "=== Install DONE ===");

                        sa_flash_set('clientes', 'Cliente creado: ' . $slug . $note, $type);
                        header('Location: ' . sa_url('/clientes.php'));
                        exit;

                    } catch (Exception $e) {
                        sa_install_log_append($installLogPath, "FATAL: " . $e->getMessage());
                        $errors[] = 'Fallo instalación (DB/schema/seeds/master). Revisa log en storage/logs del cliente.';
                    }
                }
            }
        }
    }
}

// Layout
require_once __DIR__ . '/_layout/header.php';
require_once __DIR__ . '/_layout/sidebar.php';
?>

<div class="sa-main">
  <div class="sa-top">
    <strong>SysTec Creator</strong>
    <div class="text-muted small">Crear nuevo cliente</div>
  </div>

  <div class="sa-content">

    <h4 class="mb-3">Crear cliente</h4>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-danger">
        <ul class="mb-0">
          <?php foreach ($errors as $e): ?>
            <li><?php echo htmlspecialchars((string)$e, ENT_QUOTES, 'UTF-8'); ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <div class="card">
      <div class="card-body">

        <?php if ($generatedSql !== ''): ?>
          <div class="alert alert-info">
            <strong>SQL generado.</strong> Copia/pega en phpMyAdmin (en la DB correcta).
          </div>

          <div class="form-group">
            <label>SQL de instalación (copiar/pegar)</label>
            <textarea class="form-control" rows="18" readonly><?php echo htmlspecialchars($generatedSql, ENT_QUOTES, 'UTF-8'); ?></textarea>
            <small class="text-muted">
              Tip: si tu hosting bloquea <code>CREATE DATABASE</code>, crea la DB desde cPanel y ejecuta igual el resto.
            </small>
          </div>

          <hr>
        <?php endif; ?>

        <form method="post" autocomplete="off">

          <div class="form-row">
            <div class="form-group col-md-4">
              <label>Nombre / Identificador del cliente *</label>
              <input type="text" name="slug" class="form-control" value="<?php echo htmlspecialchars($slug, ENT_QUOTES, 'UTF-8'); ?>" required placeholder="ej: cliente2">
              <small class="text-muted">minúsculas, sin espacios (esto crea la carpeta y el enlace)</small>
            </div>
            <div class="form-group col-md-8">
              <label>Nombre comercial</label>
              <input type="text" name="nombre_comercial" class="form-control" value="<?php echo htmlspecialchars($nombre_comercial, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Ej: Ferretería Don Pepe">
            </div>
          </div>

          <div class="form-row">
            <div class="form-group col-md-3">
              <label>DB Host *</label>
              <input type="text" class="form-control" value="localhost" disabled>
              <input type="hidden" name="db_host" value="localhost">
            </div>
            <div class="form-group col-md-3">
              <label>DB Name *</label>
              <input type="text" name="db_name" class="form-control" value="<?php echo htmlspecialchars($db_name, ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>
            <div class="form-group col-md-3">
              <label>DB User *</label>
              <input type="text" name="db_user" class="form-control" value="" required autocomplete="new-password">
            </div>
            <div class="form-group col-md-3">
              <label>DB Pass *</label>
              <input type="password" name="db_pass" class="form-control" value="" required autocomplete="new-password">
            </div>
          </div>

          <div class="form-row">
            <div class="form-group col-md-4">
              <label>Versión del sistema</label>
              <select name="core_version" class="form-control">
                <option value="v1.1" <?php echo ($core_version==='v1.1')?'selected':''; ?>>v1.1</option>
                <option value="v1.2" <?php echo ($core_version==='v1.2')?'selected':''; ?>>v1.2</option>
              </select>
              <small class="text-muted">la URL pública se calcula automáticamente</small>
            </div>
          </div>

          <div class="form-group">
            <label>
              <input type="checkbox" name="activo" value="1" <?php echo ($activo===1)?'checked':''; ?>>
              Cliente activo
            </label>
          </div>

          <hr>

          <h5 class="mb-2">Usuario del cliente (ADMIN)</h5>
          <div class="text-muted small mb-2">
            Se crea automáticamente el usuario <code>admin</code> con contraseña provisoria <code>112233</code> (recomendado cambiarla).
          </div>

          <div class="form-row">
            <div class="form-group col-md-6">
              <label>Nombre del admin *</label>
              <input type="text" name="admin_nombre" class="form-control" value="<?php echo htmlspecialchars($admin_nombre, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Ej: Admin CLIENTE2">
            </div>
            <div class="form-group col-md-6">
              <label>Email del admin *</label>
              <input type="email" name="admin_email" class="form-control" value="<?php echo htmlspecialchars($admin_email, ENT_QUOTES, 'UTF-8'); ?>" placeholder="ej: admin@cliente2.c2k.cl">
            </div>
          </div>

          <div class="form-row">
            <div class="form-group col-md-6">
              <label>Usuario</label>
              <input type="text" class="form-control" value="<?php echo htmlspecialchars(SYSTEC_SEED_CLIENTADMIN_USER, ENT_QUOTES, 'UTF-8'); ?>" readonly>
            </div>
            <div class="form-group col-md-6">
              <label>Contraseña provisoria</label>
              <input type="text" class="form-control" value="<?php echo htmlspecialchars(SYSTEC_SEED_CLIENTADMIN_PASS, ENT_QUOTES, 'UTF-8'); ?>" readonly>
            </div>
          </div>

          <button class="btn btn-primary" type="submit" name="_mode" value="create">Crear cliente</button>
          <button class="btn btn-outline-secondary" type="submit" name="_mode" value="sql">Generar SQL</button>
          <a class="btn btn-light" href="<?php echo sa_url('/clientes.php'); ?>">Volver</a>

        </form>

      </div>
    </div>

  </div>
</div>

<?php require_once __DIR__ . '/_layout/footer.php'; ?>
