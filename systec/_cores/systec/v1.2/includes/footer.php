<?php
// includes/footer.php

// ✅ JS siempre desde el CORE (porque assets viven ahí)
$ASSETS = function_exists('core_url') ? 'core_url' : 'url';

// Helper seguro para llamar la función elegida
$asset = function($path) use ($ASSETS) {
    return $ASSETS($path);
};

// ✅ Asegurar sesión (para el modal)
if (session_status() === PHP_SESSION_NONE) session_start();

// --- Device ID por navegador (para "solo una vez por dispositivo") ---
// OJO: aquí ya hay HTML enviado (footer), así que NO usamos setcookie() (puede fallar).
// En su lugar: si no existe cookie, generamos uno y lo seteamos con JS.
$needSetDeviceCookie = false;

$deviceId = trim((string)($_COOKIE['systec_device_id'] ?? ''));
if ($deviceId === '' || strlen($deviceId) < 12) {
  try {
    $deviceId = bin2hex(random_bytes(16));
  } catch (Exception $e) {
    $deviceId = uniqid('dev_', true);
  }
  $needSetDeviceCookie = true;
}

// ✅ Buscar aviso pendiente (si hay usuario logueado)
$noticeToShow = null;

if (isset($_SESSION['usuario_id'])) {
  try {
    $uid = (int)$_SESSION['usuario_id'];

    $stmt = $pdo->prepare("
SELECT n.id, n.titulo, n.contenido
      FROM notices n
      WHERE n.id = (
        SELECT nn.id
        FROM notices nn
        WHERE nn.activo = 1
          AND (nn.starts_at IS NULL OR nn.starts_at <= NOW())
        ORDER BY nn.prioridad DESC, nn.id DESC
        LIMIT 1
      )
      AND NOT EXISTS (
        SELECT 1
        FROM user_notice_reads r
        WHERE r.notice_id = n.id
          AND r.user_id = :uid
          AND r.device_id = :did
      )
      LIMIT 1
    ");
$stmt->execute([':uid' => $uid, ':did' => $deviceId]);
    $noticeToShow = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $noticeToShow = null;
  }
}

// ✅ IMPORTANTE: endpoint debe ser de la instancia (router) para que funcione en todos los clientes
$noticeEndpoint = url('/notices/mark_read.php');
?>

<style>
/* Footer base */
.footer .footer-inner{
  display:flex;
  align-items:center;
  justify-content:flex-end;
  gap:10px;
}

/* Desktop: una línea */
.footer .foot-desktop{
  display:block;
  white-space:nowrap;
}

/* Mobile: 3 líneas */
.footer .foot-mobile{
  display:none;
  line-height:1.25;
}

.footer .foot-mobile .line1{ font-weight:700; }
.footer .foot-mobile .line2{ font-weight:600; }
.footer .foot-mobile .line3 a{
  font-weight:600;
  color:#333;
  text-decoration:none;
}
.footer .foot-mobile .line3 a:hover{ color:#000; }

/* Toggle responsive + centrado móvil */
@media (max-width: 767.98px){
  .footer .footer-inner{
    justify-content:center;
    flex-direction:column;
    align-items:center;
    text-align:center;
    gap:6px;
  }
  .footer .foot-desktop{ display:none; }
  .footer .foot-mobile{ display:block; width:100%; }
}
</style>

        <footer class="footer">
            <div class="container-fluid footer-inner">

                <!-- ✅ PC: una sola línea -->
                <div class="copyright ml-auto foot-desktop">
                    SysTec <?php echo date('Y'); ?> &copy; ·
                    <a href="https://wa.me/56910129553"
                       target="_blank"
                       style="color:#333; font-weight:600; text-decoration:none;"
                       onmouseover="this.style.color='#000';"
                       onmouseout="this.style.color='#333';">
                        Diseño &amp; Programación: Mikel DNG | C2K Studio
                    </a>
                </div>

                <!-- ✅ MÓVIL: 3 líneas -->
                <div class="copyright foot-mobile">
                    <div class="line1">SysTec <?php echo date('Y'); ?> &copy;</div>
                    <div class="line2">Diseño &amp; Programación</div>
                    <div class="line3">
                        <a href="https://wa.me/56910129553" target="_blank">
                            Mikel DNG | C2K Studio
                        </a>
                    </div>
                </div>

            </div>
        </footer>
    </div> <!-- /.main-panel -->
</div> <!-- /.wrapper -->

<!-- ✅ JS desde el CORE -->
<script src="<?php echo $asset('/assets/js/core/jquery.3.2.1.min.js'); ?>"></script>
<script src="<?php echo $asset('/assets/js/plugin/jquery-ui-1.12.1.custom/jquery-ui.min.js'); ?>"></script>
<script src="<?php echo $asset('/assets/js/core/popper.min.js'); ?>"></script>
<script src="<?php echo $asset('/assets/js/core/bootstrap.min.js'); ?>"></script>

<script src="<?php echo $asset('/assets/js/plugin/chartist/chartist.min.js'); ?>"></script>
<script src="<?php echo $asset('/assets/js/plugin/chartist/plugin/chartist-plugin-tooltip.min.js'); ?>"></script>
<script src="<?php echo $asset('/assets/js/plugin/bootstrap-notify/bootstrap-notify.min.js'); ?>"></script>
<script src="<?php echo $asset('/assets/js/plugin/bootstrap-toggle/bootstrap-toggle.min.js'); ?>"></script>
<script src="<?php echo $asset('/assets/js/plugin/jquery-mapael/jquery.mapael.min.js'); ?>"></script>
<script src="<?php echo $asset('/assets/js/plugin/jquery-mapael/maps/world_countries.min.js'); ?>"></script>
<script src="<?php echo $asset('/assets/js/plugin/chart-circle/circles.min.js'); ?>"></script>
<script src="<?php echo $asset('/assets/js/plugin/jquery-scrollbar/jquery.scrollbar.min.js'); ?>"></script>

<script src="<?php echo $asset('/assets/js/ready.min.js'); ?>"></script>
<!-- <script src="<?php echo $asset('/assets/js/demo.js'); ?>"></script> -->

<?php if ($needSetDeviceCookie): ?>
<script>
(function(){
  try{
    var isHttps = (location && location.protocol === 'https:');
    var maxAge = 60*60*24*365*2; // 2 años
    var cookie = 'systec_device_id=<?php echo htmlspecialchars($deviceId, ENT_QUOTES, 'UTF-8'); ?>; path=/; max-age=' + maxAge + '; samesite=lax';
    if (isHttps) cookie += '; secure';
    document.cookie = cookie;
  }catch(e){}
})();
</script>
<?php endif; ?>

<?php if (!empty($noticeToShow)): ?>
<div class="modal fade" id="modalNotice" tabindex="-1" role="dialog" aria-labelledby="modalNoticeLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">

      <div class="modal-header">
        <h6 class="modal-title" id="modalNoticeLabel">
          <?php echo htmlspecialchars((string)$noticeToShow['titulo']); ?>
        </h6>
      </div>

      <div class="modal-body">
        <div style="white-space:pre-wrap; line-height:1.35;">
          <?php echo htmlspecialchars((string)$noticeToShow['contenido']); ?>
        </div>

        <hr class="my-3">

        <div class="custom-control custom-checkbox">
          <input type="checkbox" class="custom-control-input" id="notice_read_chk">
          <label class="custom-control-label" for="notice_read_chk">
            Leído (no volver a mostrar en este dispositivo)
          </label>
        </div>

        <div id="notice_err" class="alert alert-danger mt-3 mb-0" style="display:none;"></div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-primary" id="btn_notice_ok" disabled>
          Entendido
        </button>
      </div>

    </div>
  </div>
</div>

<script>
(function(){
  $(function(){
    $('#modalNotice').modal({backdrop:'static', keyboard:false});
  });

  var chk = document.getElementById('notice_read_chk');
  var btn = document.getElementById('btn_notice_ok');
  var err = document.getElementById('notice_err');

  function showErr(txt){
    if(!err) return;
    err.textContent = txt || 'Error';
    err.style.display = 'block';
  }

  if (chk && btn){
    chk.addEventListener('change', function(){
      btn.disabled = !chk.checked;
    });

    btn.addEventListener('click', function(){
      if (!chk.checked) return;      var fd = new FormData();
      fd.append('notice_id', '<?php echo (int)$noticeToShow['id']; ?>');
      fd.append('device_id', '<?php echo htmlspecialchars($deviceId, ENT_QUOTES, 'UTF-8'); ?>');
fetch('<?php echo htmlspecialchars($noticeEndpoint, ENT_QUOTES, 'UTF-8'); ?>', {
        method: 'POST',
        body: fd,
        credentials: 'same-origin'
      })
      .then(function(r){ return r.json().catch(function(){ return null; }); })
      .then(function(data){
        if (!data || !data.ok) {
          showErr((data && data.error) ? data.error : 'No se pudo marcar como leído');
          return;
        }
        $('#modalNotice').modal('hide');
      })
      .catch(function(){ showErr('No se pudo marcar como leído'); });
    });
  }
})();
</script>
<?php endif; ?>

</body>
</html>
