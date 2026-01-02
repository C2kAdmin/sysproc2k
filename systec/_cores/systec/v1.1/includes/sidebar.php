<?php
// includes/sidebar.php

// Base URL segura para links internos (multi-cliente)
$APP = function_exists('url') ? rtrim(url(''), '/') : '';

// Tomamos nombre y rol desde la sesión
$usuarioNombre = $_SESSION['usuario_nombre'] ?? 'Usuario';
$usuarioRol    = strtoupper(trim($_SESSION['usuario_rol'] ?? ''));

// ✅ SuperAdmin REAL (si auth.php ya está cargado, genial; si no, fallback a sesión)
$esSuperAdmin = function_exists('is_super_admin')
    ? is_super_admin()
    : ($usuarioRol === 'SUPER_ADMIN');

// ✅ Flags de UI por rol
$esAdmin       = ($usuarioRol === 'ADMIN' || $esSuperAdmin);
$esRecepcion   = ($usuarioRol === 'RECEPCION');
$esTecnico     = ($usuarioRol === 'TECNICO');

// Ícono y color según rol
$rolIcono = 'fa-user';
$rolColor = '#999999';

switch ($usuarioRol) {
    case 'SUPER_ADMIN':
        $rolIcono = 'fa-user-shield';
        $rolColor = '#111827';
        break;

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

<style>
/* ✅ FIX: evitar que el icono se recorte dentro del círculo del sidebar */
.sidebar .user .photo{
    width: 64px !important;
    height: 64px !important;
    border-radius: 50% !important;
    overflow: visible !important;
    background: transparent !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
}
.sidebar .user .photo i{
    font-size: 40px !important;
    line-height: 1 !important;
}

/* ✅ Separador pro */
.sidebar-sep{
    margin: 12px 12px 6px;
    font-size: 11px;
    letter-spacing: .08em;
    color: #9ca3af;
    text-transform: uppercase;
    border-top: 1px solid rgba(0,0,0,.06);
    padding-top: 10px;
}
</style>

<div class="sidebar">
    <div class="scrollbar-inner sidebar-wrapper">
        <div class="user">
            <div class="photo">
                <i class="fa-solid <?php echo $rolIcono; ?>" style="color: <?php echo $rolColor; ?>;"></i>
            </div>

            <div class="info">
                <a data-toggle="collapse" href="#userMenu" aria-expanded="true">
                    <span>
                        <?php echo htmlspecialchars($usuarioNombre); ?>
                        <span class="user-level"><?php echo htmlspecialchars($usuarioRol); ?></span>
                        <span class="caret"></span>
                    </span>
                </a>
                <div class="clearfix"></div>

                <div class="collapse in" id="userMenu" aria-expanded="true">
                    <ul class="nav">
                        <li><a href="#"><span class="link-collapse">Mi perfil</span></a></li>
                    </ul>
                </div>
            </div>
        </div>

        <ul class="nav">
            <!-- Siempre visibles -->
            <li class="nav-item">
                <a href="<?php echo $APP; ?>/dashboard.php">
                    <i class="la la-dashboard"></i><p>Inicio</p>
                </a>
            </li>

            <li class="nav-item">
                <a href="<?php echo $APP; ?>/order/ordenes_dia.php">
                    <i class="la la-calendar-check-o"></i><p>Órdenes del día</p>
                </a>
            </li>

            <li class="nav-item">
                <a href="<?php echo $APP; ?>/order/ordenes.php">
                    <i class="la la-list-alt"></i><p>Todas las órdenes</p>
                </a>
            </li>

            <!-- Recepción + Admin (incluye SUPER_ADMIN) -->
            <?php if ($esAdmin || $esRecepcion): ?>
                <li class="nav-item">
                    <a href="<?php echo $APP; ?>/order/orden_nueva.php">
                        <i class="la la-plus-square"></i><p>Nueva orden</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="<?php echo $APP; ?>/order/firmas_pendientes.php">
                        <i class="la la-pencil-square"></i><p>Firmas pendientes</p>
                    </a>
                </li>
            <?php endif; ?>

            <!-- Admin (incluye SUPER_ADMIN) -->
            <?php if ($esAdmin): ?>
                <li class="nav-item">
                    <a href="<?php echo $APP; ?>/users/usuarios.php">
                        <i class="la la-user"></i><p>Usuarios</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="<?php echo $APP; ?>/config/config_parametros.php">
                        <i class="la la-cog"></i><p>Parámetros</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="<?php echo $APP; ?>/config/config_mensajes.php">
                        <i class="la la-whatsapp"></i><p>Mensajes WhatsApp</p>
                    </a>
                </li>
            <?php endif; ?>

            <!-- SOLO SUPER_ADMIN -->
            <?php if ($esSuperAdmin): ?>
                <div class="sidebar-sep">Super Admin</div>

                <li class="nav-item">
                    <a href="<?php echo $APP; ?>/reset_demo.php">
                        <i class="la la-trash"></i><p>Reset Demo</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="<?php echo $APP; ?>/order/superadmin_pos_print.php">
                        <i class="la la-print"></i><p>POS Print (ASUS)</p>
                    </a>
                </li>
            <?php endif; ?>

            <!-- Logout -->
            <li class="nav-item">
                <a href="<?php echo $APP; ?>/logout.php">
                    <i class="fa fa-power-off"></i><p>Cerrar sesión</p>
                </a>
            </li>
        </ul>
    </div>
</div>
