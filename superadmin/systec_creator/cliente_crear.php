<?php
declare(strict_types=1);

// 1️⃣ Bootstrap SuperAdmin (NO CORE)
require_once __DIR__ . '/_config/config.php';
require_once __DIR__ . '/_config/auth.php';

require_super_admin();

$errors = [];
$okMsg  = '';

function sa_valid_slug(string $slug): bool {
    return (bool)preg_match('/^[a-z0-9_-]+$/', $slug);
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

// Defaults
$slug            = '';
$nombre_comercial= '';
$db_host         = 'localhost';
$db_name         = '';
$db_user         = '';
$db_pass         = '';
$activo          = 1;
$core_version    = 'v1.2';
$base_url_public = '';

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

    // Validaciones mínimas
    if ($slug === '' || !sa_valid_slug($slug)) {
        $errors[] = 'Slug inválido. Solo a-z 0-9 _ - (minúsculas, sin espacios).';
    }

    if ($db_host === '' || $db_name === '' || $db_user === '' || $db_pass === '') {
        $errors[] = 'DB host/name/user/pass son obligatorios.';
    }

    // Validar core_version exista físicamente
    $corePath = SYSTEC_ROOT ? (SYSTEC_ROOT . '/_cores/systec/' . $core_version) : '';
    if (!$corePath || !is_dir($corePath) || !is_file($corePath . '/router.php')) {
        $errors[] = 'core_version no existe en el servidor (' . htmlspecialchars($core_version, ENT_QUOTES, 'UTF-8') . ').';
    }

    // Calcular base_url_public si no viene
    if ($base_url_public === '') {
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
        $logsPath     = $storageDir . '/logs';

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
    exit('❌ CORE no encontrado');
}
if (!SYSTEC_INSTANCE_PATH || !is_file(SYSTEC_INSTANCE_PATH)) {
    exit('❌ instance.php no encontrado');
}

// ✅ display_errors depende de ENV (dev/prod) definido en instance.php
$__cfg = require SYSTEC_INSTANCE_PATH;
$__env = strtolower((string)($__cfg['ENV'] ?? 'prod'));

if ($__env === 'dev') {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL);
}

// ✅ APP_URL de ESTA instancia (ruta pública donde vive /public/)
$base = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($base === '/' || $base === '') $base = '';
define('SYSTEC_APP_URL', $base . '/');

// ✅ CORE_URL (ruta web al CORE) para cargar assets directo desde el CORE
$docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
$coreFs  = realpath(SYSTEC_CORE_PATH);

$coreRel = '';
if ($docRoot && $coreFs && strpos($coreFs, $docRoot) === 0) {
    $coreRel = str_replace('\\','/', substr($coreFs, strlen($docRoot)));
    $coreRel = '/' . ltrim($coreRel, '/');
}
$coreRel = rtrim($coreRel, '/');
define('SYSTEC_CORE_URL', $coreRel);

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

            $db_host_e   = $esc($db_host);
            $db_name_e   = $esc($db_name);
            $db_user_e   = $esc($db_user);
            $db_pass_e   = $esc($db_pass);
            $base_url_e  = $esc($base_url_public);

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

                    sa_flash_set('clientes', 'Cliente creado: ' . $slug, 'success');
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

          <button class="btn btn-primary" type="submit">Crear cliente</button>
          <a class="btn btn-light" href="<?php echo sa_url('/clientes.php'); ?>">Volver</a>

        </form>

      </div>
    </div>

  </div>
</div>

<?php require_once __DIR__ . '/_layout/footer.php'; ?>
