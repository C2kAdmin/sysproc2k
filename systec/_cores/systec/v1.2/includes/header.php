<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth.php';

// ✅ aquí valida sesión + existencia/activo en BD
require_login();

$usuarioNombre = $_SESSION['usuario_nombre'] ?? 'Usuario';
$usuarioRol    = $_SESSION['usuario_rol']    ?? '';

if (!function_exists('header_get_param')) {
    function header_get_param($clave, $default = '')
    {
        global $pdo;
        if (!isset($pdo)) return $default;

        try {
            $stmt = $pdo->prepare("SELECT valor FROM parametros WHERE clave = :clave LIMIT 1");
            $stmt->execute([':clave' => $clave]);
            $fila = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($fila && $fila['valor'] !== '') return $fila['valor'];
        } catch (Exception $e) {}

        return $default;
    }
}

$nombreNegocio = header_get_param('nombre_negocio', 'SysTec');
$logoNegocio   = header_get_param('logo_ruta', '');

// ... (desde aquí sigue tu header exactamente igual)


$rolIcono = 'fa-user';
$rolColor = '#999999';

switch (strtoupper(trim($usuarioRol))) {
    case 'ADMIN':
        $rolIcono = 'fa-user-shield';
        $rolColor = '#d4a017';
        break;
    case 'TECNICO':
        $rolIcono = 'fa-screwdriver-wrench';
        $rolColor = '#9ca3af';
        break;
    case 'RECEPCION':
        $rolIcono = 'fa-user';
        $rolColor = '#cd7f32';
        break;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />
    <meta charset="utf-8">
    <title><?php echo htmlspecialchars($nombreNegocio); ?> · Sistema Técnico</title>
    <meta content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0, shrink-to-fit=no" name="viewport" />

    <!-- PWA -->
    <link rel="manifest" href="<?php echo url('/manifest.php'); ?>">
    <meta name="theme-color" content="#0b1f3a">
    <link rel="icon" type="image/png" href="<?php echo url('/assets/icons/gsc-512.png'); ?>">
    <link rel="apple-touch-icon" href="<?php echo url('/assets/icons/gsc-512.png'); ?>">

    <!-- Assets CORE -->
    <link rel="stylesheet" href="<?php echo core_url('/assets/css/bootstrap.min.css'); ?>">
    <link rel="stylesheet" href="<?php echo core_url('/assets/css/ready.css'); ?>">
    <link rel="stylesheet" href="<?php echo core_url('/assets/css/demo.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        /* Buscador limpio, sin cuadrito raro */
        .nav-search .form-control {
            border-right: 0;
        }
        .nav-search .input-group-text {
            background: #fff;
            border-left: 0;
            cursor: pointer;
        }
        .nav-search .input-group-text:focus,
        .nav-search .form-control:focus {
            box-shadow: none;
        }
    </style>
</head>

<body>
<div class="wrapper">
<div class="main-header">

    <!-- LOGO -->
    <div class="logo-header">
        <a href="<?php echo url('/dashboard.php'); ?>" class="logo d-flex align-items-center">
            <?php if (!empty($logoNegocio)): ?>
                <img src="<?php echo core_url('/' . ltrim($logoNegocio, '/')); ?>"
                     alt="Logo negocio"
                     style="max-height:32px; margin-right:8px;">
            <?php endif; ?>
            <span style="font-weight:700; font-size:15px; color:#1f2937;">
                <?php echo htmlspecialchars($nombreNegocio); ?>
            </span>
        </a>

        <button class="navbar-toggler sidenav-toggler ml-auto" type="button">
            <span class="navbar-toggler-icon"></span>
        </button>
        <button class="topbar-toggler more">
            <i class="la la-ellipsis-v"></i>
        </button>
    </div>

    <!-- NAVBAR -->
    <nav class="navbar navbar-header navbar-expand-lg">
        <div class="container-fluid">

            <!-- 🔍 BUSCADOR GLOBAL FUNCIONAL -->
            <form class="navbar-left navbar-form nav-search mr-md-3"
                  action="<?php echo url('/order/ordenes_buscar.php'); ?>"
                  method="get">
                <div class="input-group">
                    <input
                        type="text"
                        name="q"
                        class="form-control"
                        placeholder="Buscar orden, cliente, IMEI..."
                        value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>"
                    >
                    <div class="input-group-append">
                        <button type="submit" class="input-group-text" title="Buscar">
                            <i class="la la-search search-icon"></i>
                        </button>
                    </div>
                </div>
            </form>

            <!-- USUARIO -->
            <ul class="navbar-nav topbar-nav ml-md-auto align-items-center">
                <li class="nav-item dropdown">
                    <a class="dropdown-toggle profile-pic" data-toggle="dropdown" href="#">
                        <i class="fa-solid <?php echo $rolIcono; ?>"
                           style="font-size:24px; color:<?php echo $rolColor; ?>; margin-right:6px;"></i>
                        <span><?php echo htmlspecialchars($usuarioNombre); ?></span>
                    </a>

                    <ul class="dropdown-menu dropdown-user">
                        <li>
                            <div class="user-box text-center">
                                <i class="fa-solid <?php echo $rolIcono; ?>"
                                   style="font-size:34px; color:<?php echo $rolColor; ?>;"></i>
                                <h4 class="mt-2"><?php echo htmlspecialchars($usuarioNombre); ?></h4>
                                <p class="text-muted"><?php echo htmlspecialchars($usuarioRol); ?></p>
                            </div>
                        </li>

                        <div class="dropdown-divider"></div>

                        <a class="dropdown-item" href="<?php echo url('/config/config_parametros.php'); ?>">
                            <i class="ti-settings"></i> Configuración
                        </a>

                        <div class="dropdown-divider"></div>

                        <a class="dropdown-item" href="<?php echo url('/logout.php'); ?>">
                            <i class="fa fa-power-off"></i> Cerrar sesión
                        </a>
                    </ul>
                </li>
            </ul>

        </div>
    </nav>
</div>
