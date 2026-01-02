<?php
declare(strict_types=1);
require_once __DIR__ . '/../_config/config.php';
?>
<aside class="sa-side">
  <div class="sa-brand">SysTec Creator</div>

  <nav class="sa-nav">
    <a href="<?php echo sa_url('/clientes.php'); ?>">Clientes</a>
    <a href="<?php echo sa_url('/cliente_crear.php'); ?>">Crear cliente</a>
    <hr class="my-2">
    <a href="<?php echo sa_url('/logout.php'); ?>">Cerrar sesión</a>
  </nav>
</aside>
