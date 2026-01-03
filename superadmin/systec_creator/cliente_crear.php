<?php
declare(strict_types=1);

// 1) Bootstrap SuperAdmin (NO CORE)
require_once __DIR__ . '/_config/config.php';
require_once __DIR__ . '/_config/auth.php';

require_super_admin();

$errors = [];
$okMsg  = '';

function sa_valid_slug(string $slug): bool {
    return (bool)preg_match('/^[a-z0-9_-]+$/', $slug);
}

function sa_valid_username(string $u): bool {
    // permitido: letras/números + _ - . (sin espacios)
    return (bool)preg_match('/^[a-zA-Z0-9_.-]+$/', $u);
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

/**
 * Crear usuario inicial SUPER_ADMIN en la DB del cliente (opcional).
 * - NO rompe la creación del cliente si falla.
 */
function sa_try_create_initial_super_admin(
    string $db_host,
    string $db_name,
    string $db_user,
    string $db_pass,
    string $nombre,
    string $usuario,
    string $password,
    string $email
): array {
    // Normalizar
    $usuario = trim($usuario);
    $email   = strtolower(trim($email));
    $nombre  = trim($nombre);

    try {
        $pdoClient = new PDO(
            "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4",
            $db_user,
            $db_pass,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    } catch (Exception $e) {
        return ['ok' => false, 'msg' => 'No se pudo conectar a la DB del cliente.'];
    }

    try {
        // 1) Verificar tabla usuarios (más compatible que information_schema)
        $st = $pdoClient->prepare("SHOW TABLES LIKE 'usuarios'");
        $st->execute();
        $hasTable = (bool)$st->fetchColumn();

        if (!$hasTable) {
            return ['ok' => false, 'msg' => "La tabla 'usuarios' no existe en la DB del cliente."];
        }

        // 2) Verificar columnas mínimas esperadas
        $cols = $pdoClient->query("SHOW COLUMNS FROM usuarios")->fetchAll();
        $fields = [];
        foreach ($cols as $c) {
            $fields[(string)$c['Field']] = true;
        }

        $required = ['nombre','usuario','email','password_hash','rol','activo','is_super_admin'];
        foreach ($required as $r) {
            if (!isset($fields[$r])) {
                return ['ok' => false, 'msg' => "Tabla usuarios no compatible. Falta columna: {$r}."];
            }
        }

        // 3) Duplicados (usuario o email)
        $st = $pdoClient->prepare("SELECT id FROM usuarios WHERE usuario = :u OR email = :e LIMIT 1");
        $st->execute([':u' => $usuario, ':e' => $email]);
        if ($st->fetch()) {
            return ['ok' => false, 'msg' => 'El usuario o email inicial ya existe en esta DB.'];
        }

        // 4) Insert SUPER_ADMIN
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $st = $pdoClient->prepare("
            INSERT INTO usuarios (nombre, usuario, email, password_hash, rol, activo, is_super_admin)
            VALUES (:n, :u, :e, :ph, 'SUPER_ADMIN', 1, 1)
        ");
        $st->execute([
            ':n'  => $nombre,
            ':u'  => $usuario,
            ':e'  => $email,
            ':ph' => $hash,
        ]);

        return ['ok' => true, 'msg' => "Admin creado ({$usuario} / {$email})."];

    } catch (Exception $e) {
        return ['ok' => false, 'msg' => 'Fallo al crear el usuario inicial.'];
    }
}

// Defaults
$slug             = '';
$nombre_comercial = '';
$db_host          = 'localhost';
$db_name          = '';
$db_user          = '';
$db_pass          = '';
$activo           = 1;
$core_version     = 'v1.2';
$base_url_public  = '';

// Usuario inicial (opcional)
$crear_admin   = 1;
$admin_nombre  = '';
$admin_email   = '';
$admin_usuario = 'admin';
$admin_pass    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $slug             = strtolower(sa_post('slug'));
    $nombre_comercial = sa_post('nombre_comercial');
    $db_host          = sa_post('db_host', 'localhost');
    $db_name          = sa_post('db_name');
    $db_user          = sa_post('db_user');
    $db_pass          = (string)($_POST['db_pass'] ?? '');
    $activo           = isset($_POST['activo']) ? 1 : 0;
    $core_version     = sa_post('core_version', 'v1.2');
    $base_url_public  = sa_post('base_url_public');

    // Usuario inicial (opcional)
    $crear_admin   = isset($_POST['crear_admin']) ? 1 : 0;
    $admin_nombre  = sa_post('admin_nombre');
    $admin_email   = sa_post('admin_email');
    $admin_usuario = sa_post('admin_usuario', 'admin');
    $admin_pass    = (string)($_POST['admin_pass'] ?? '');

    // 👇 Autopreset para evitar confusión (si dejaron "admin" por costumbre)
    if ($crear_admin === 1 && $slug !== '' && trim($admin_usuario) === 'admin') {
        $admin_usuario = $slug . '_admin';
    }
    if ($crear_admin === 1 && trim($admin_email) === '' && trim($admin_usuario) !== '') {
        // Email técnico por defecto (válido). Si luego quieren recovery por email real, se cambia.
        $admin_email = $admin_usuario . '@c2k.cl';
    }

    // Validaciones mínimas
    if ($slug === '' || !sa_valid_slug($slug)) {
        $errors[] = 'Slug inválido. Solo a-z 0-9 _ - (minúsculas, sin espacios).';
    }

    if ($db_host === '' || $db_name === '' || $db_user === '' || $db_pass === '') {
        $errors[] = 'DB host/name/user/pass son obligatorios.';
    }

    // Validación usuario inicial
    if ($crear_admin === 1) {
        if (trim($admin_nombre) === '' || trim($admin_usuario) === '' || trim($admin_pass) === '') {
            $errors[] = 'Usuario inicial: nombre/usuario/contraseña son obligatorios.';
        } elseif (strlen($admin_pass) < 6) {
            $errors[] = 'Usuario inicial: contraseña mínima 6 caracteres.';
        }

        if (!sa_valid_username($admin_usuario)) {
            $errors[] = 'Usuario inicial: usuario inválido (solo letras/números y _ - . ).';
        }

        if (trim($admin_email) === '') {
            $errors[] = 'Usuario inicial: email es obligatorio.';
        } elseif (!filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Usuario inicial: email no es válido (debe contener @).';
        }
    }

    // Validar core_version exista físicamente
    $corePath = SYSTEC_ROOT ? (SYSTEC_ROOT . '/_cores/systec/' . $core_version) : '';
    if (!$corePath || !is_dir($corePath) || !is_file($corePath . '/router.php')) {
        $errors[] = 'core_version no existe en el servidor (' . htmlspecialchars($core_version, ENT_QUOTES, 'UTF-8') . ').';
    }

    // Calcular base_url_public si no viene
    if ($base_url_public === '') {
        $https  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ((string)($_SERVER['SERVER_PORT'] ?? '') === '443');
        $scheme = $https ? 'https://' : 'http://';
        $host   = (string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost');

        $script = str_replace('\\','/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
        $pos = strpos($script, '/superadmin/systec_creator');
        $sysproWeb = ($pos !== false) ? substr($script, 0, $pos) : rtrim(dirname($script), '/');
        $sysproWeb = rtrim($sysproWeb, '/');

        $pubPath = $sysproWeb . '/systec/_clients/' . $slug . '/tec/public/';
        $base_url_public = $scheme . $host . $pubPath;
    }

    // Validar duplicados en BD master y FS
    if (empty($errors)) {
        try {
            $pdo = sa_pdo();

            $st = $pdo->prepare("SELECT id FROM systec_clientes WHERE slug = :s LIMIT 1");
            $st->execute([':s' => $slug]);
            $exists = $st->fetch();

            if ($exists) {
                $errors[] = 'Ese slug ya existe en la BD master.';
            }

        } catch (Exception $e) {
            $errors[] = 'No se pudo consultar BD master (revisa credenciales).';
        }

        $fsClientRoot = SYSTEC_CLIENTS_ROOT ? (SYSTEC_CLIENTS_ROOT . '/' . $slug) : '';
        if ($fsClientRoot && is_dir($fsClientRoot)) {
            $errors[] = 'Ya existe carpeta en _clients/' . $slug;
        }
    }

    // Crear instancia (carpetas + archivos + registro)
    if (empty($errors)) {

        $tecRoot      = SYSTEC_CLIENTS_ROOT . '/' . $slug . '/tec';
        $publicDir    = $tecRoot . '/public';
        $configDir    = $tecRoot . '/config';
        $storageDir   = $tecRoot . '/storage';

        $instancePath = $configDir . '/instance.php';
        $storagePath  = $storageDir;

        $publicIndex  = $publicDir . '/index.php';
        $publicHt     = $publicDir . '/.htaccess';

        // Crear dirs principales
        $ok = true;
        $ok = $ok && sa_mkdir($publicDir);
        $ok = $ok && sa_mkdir($configDir);
        $ok = $ok && sa_mkdir($storageDir);

        // Storage subfolders oficiales
        $ok = $ok && sa_mkdir($storageDir . '/evidencias');
        $ok = $ok && sa_mkdir($storageDir . '/firmas');
        $ok = $ok && sa_mkdir($storageDir . '/branding');
        $ok = $ok && sa_mkdir($storageDir . '/logs');

        if (!$ok) {
            $errors[] = 'No se pudieron crear carpetas (revisa permisos del servidor).';
        } else {

            // Template: public/index.php (puente)
            $indexTpl = <<<PHP
<?php
/**
 * Puente INSTANCIA -> CORE
 * Cliente: {$slug}
 * Versión Core: {$core_version}
 */

define('SYSTEC_CORE_PATH', realpath(__DIR__ . '/../../../../_cores/systec/{$core_version}'));
define('SYSTEC_INSTANCE_PATH', realpath(__DIR__ . '/../config/instance.php'));

if (!SYSTEC_CORE_PATH || !is_dir(SYSTEC_CORE_PATH)) {
    exit('CORE no encontrado');
}
if (!SYSTEC_INSTANCE_PATH || !is_file(SYSTEC_INSTANCE_PATH)) {
    exit('instance.php no encontrado');
}

// APP_URL de ESTA instancia (ruta pública donde vive /public/)
// (Debe definirse ANTES de cargar instance.php para que APP_URL salga correcto en config)
\$base = rtrim(str_replace('\\\\','/', dirname(\$_SERVER['SCRIPT_NAME'] ?? '')), '/');
if (\$base === '/' || \$base === '') \$base = '';
define('SYSTEC_APP_URL', \$base . '/');

// display_errors depende de ENV (dev/prod) definido en instance.php
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

// CORE_URL (ruta web al CORE) para cargar assets directo desde el CORE
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

            // Template: .htaccess
            $htTpl = "Options -Indexes\n\n" .
"RewriteEngine On\n\n" .
"RewriteCond %{REQUEST_FILENAME} -f [OR]\n" .
"RewriteCond %{REQUEST_FILENAME} -d\n" .
"RewriteRule ^ - [L]\n\n" .
"RewriteRule ^ index.php [L]\n";

            // Template: instance.php
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

    // IMPORTANT: el CORE v1.2 usa SYSTEC_VERSION para resolver versión (si no viene cae a v1.1)
    'SYSTEC_VERSION' => '{$core_version}',

    // APP_URL lo toma del puente (SYSTEC_APP_URL).
    // IMPORTANTE: mantenerlo como PATH (sin scheme/host) para compatibilidad v1.1
    'APP_URL' => (defined('SYSTEC_APP_URL') ? SYSTEC_APP_URL : '/syspro/systec/_clients/{$slug}/tec/public/'),

    // URL pública completa (referencia humana / registry)
    'APP_URL_PUBLIC' => '{$base_url_e}',

    'DB_HOST' => '{$db_host_e}',
    'DB_NAME' => '{$db_name_e}',
    'DB_USER' => '{$db_user_e}',
    'DB_PASS' => '{$db_pass_e}',

    'STORAGE_PATH' => (realpath(__DIR__ . '/../storage') ?: (__DIR__ . '/../storage')),
    'LOG_PATH' => (realpath(__DIR__ . '/../storage/logs') ?: (__DIR__ . '/../storage/logs')),
];
PHP;

            // Escribir archivos
            if (!sa_write_file($publicIndex, $indexTpl)) $errors[] = 'No se pudo escribir public/index.php';
            if (!sa_write_file($publicHt, $htTpl)) $errors[] = 'No se pudo escribir public/.htaccess';
            if (!sa_write_file($instancePath, $instanceTpl)) $errors[] = 'No se pudo escribir config/instance.php';

            // Registrar en BD master
            if (empty($errors)) {
                try {
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

                    // Crear usuario inicial SUPER_ADMIN (opcional)
                    $note = '';
                    $type = 'success';

                    if ($crear_admin === 1) {
                        $r = sa_try_create_initial_super_admin(
                            $db_host,
                            $db_name,
                            $db_user,
                            $db_pass,
                            $admin_nombre,
                            $admin_usuario,
                            $admin_pass,
                            $admin_email
                        );

                        if (!$r['ok']) {
                            $type = 'warning';
                            $note = ' | Cliente OK, pero admin NO: ' . $r['msg'];
                        } else {
                            $note = ' | Admin OK: ' . $r['msg'];
                        }
                    }

                    sa_flash_set('clientes', 'Cliente creado: ' . $slug . $note, $type);
                    header('Location: ' . sa_url('/clientes.php'));
                    exit;

                } catch (Exception $e) {
                    $errors[] = 'No se pudo registrar en BD master.';
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

        <form method="post" autocomplete="off">

          <div class="form-row">
            <div class="form-group col-md-4">
              <label>Slug *</label>
              <input type="text" name="slug" class="form-control" value="<?php echo htmlspecialchars($slug, ENT_QUOTES, 'UTF-8'); ?>" required>
              <small class="text-muted">minúsculas, sin espacios</small>
            </div>
            <div class="form-group col-md-8">
              <label>Nombre comercial</label>
              <input type="text" name="nombre_comercial" class="form-control" value="<?php echo htmlspecialchars($nombre_comercial, ENT_QUOTES, 'UTF-8'); ?>">
            </div>
          </div>

          <div class="form-row">
            <div class="form-group col-md-3">
              <label>DB Host *</label>
              <input type="text" name="db_host" class="form-control" value="<?php echo htmlspecialchars($db_host, ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>
            <div class="form-group col-md-3">
              <label>DB Name *</label>
              <input type="text" name="db_name" class="form-control" value="<?php echo htmlspecialchars($db_name, ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>
            <div class="form-group col-md-3">
              <label>DB User *</label>
              <input type="text" name="db_user" class="form-control" value="<?php echo htmlspecialchars($db_user, ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>
            <div class="form-group col-md-3">
              <label>DB Pass *</label>
              <input type="password" name="db_pass" class="form-control" value="<?php echo htmlspecialchars($db_pass, ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group col-md-4">
              <label>Core version</label>
              <select name="core_version" class="form-control">
                <option value="v1.1" <?php echo ($core_version==='v1.1')?'selected':''; ?>>v1.1</option>
                <option value="v1.2" <?php echo ($core_version==='v1.2')?'selected':''; ?>>v1.2</option>
              </select>
              <small class="text-muted">si v1.2 aún no existe en el server, dará error</small>
            </div>
            <div class="form-group col-md-8">
              <label>Base URL pública (opcional)</label>
              <input type="text" name="base_url_public" class="form-control" value="<?php echo htmlspecialchars($base_url_public, ENT_QUOTES, 'UTF-8'); ?>">
              <small class="text-muted">si lo dejas vacío, se calcula automático</small>
            </div>
          </div>

          <div class="form-group">
            <label>
              <input type="checkbox" name="activo" value="1" <?php echo ($activo===1)?'checked':''; ?>>
              Cliente activo
            </label>
          </div>

          <hr>

          <h5 class="mb-2">Usuario inicial (opcional)</h5>
          <div class="form-group">
            <label>
              <input type="checkbox" name="crear_admin" value="1" <?php echo ($crear_admin===1)?'checked':''; ?>>
              Crear usuario inicial <strong>SUPER_ADMIN</strong>
            </label>
            <div class="text-muted small">
              Nota: si luego usarás recuperación por email, pon un correo real.
            </div>
          </div>

          <div class="form-row">
            <div class="form-group col-md-4">
              <label>Nombre *</label>
              <input type="text" name="admin_nombre" class="form-control" value="<?php echo htmlspecialchars($admin_nombre, ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="form-group col-md-4">
              <label>Email *</label>
              <input type="email" name="admin_email" class="form-control" value="<?php echo htmlspecialchars($admin_email, ENT_QUOTES, 'UTF-8'); ?>" placeholder="ej: demo1_admin@c2k.cl">
            </div>
            <div class="form-group col-md-2">
              <label>Usuario *</label>
              <input type="text" name="admin_usuario" class="form-control" value="<?php echo htmlspecialchars($admin_usuario, ENT_QUOTES, 'UTF-8'); ?>" placeholder="ej: demo1_admin">
            </div>
            <div class="form-group col-md-2">
              <label>Contraseña *</label>
              <input type="password" name="admin_pass" class="form-control" value="">
            </div>
          </div>

          <button class="btn btn-primary" type="submit">Crear cliente</button>
          <a class="btn btn-light" href="<?php echo sa_url('/clientes.php'); ?>">Volver</a>

        </form>

      </div>
    </div>

  </div>
</div>

<?php require_once __DIR__ . '/_layout/footer.php'; ?>
