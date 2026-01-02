<?php
// /pos_print/imprimir_pos.php
// Proxy de impresión silenciosa en ASUS (XAMPP)
// Recibe: ?url=https://tusitio/order/orden_ticket.php?id=14&w=80
// Hace: descarga HTML -> crea wrapper auto-print -> abre Chrome con --kiosk-printing

date_default_timezone_set('America/Santiago');

function out($msg) {
    header('Content-Type: text/plain; charset=UTF-8');
    echo $msg;
    exit;
}

$url = trim((string)($_GET['url'] ?? ''));
if ($url === '') out("ERROR: falta url");
if (!preg_match('#^https?://#i', $url)) out("ERROR: url inválida (debe ser http/https)");

// Carpeta tmp
$TMP_DIR = __DIR__ . DIRECTORY_SEPARATOR . 'tmp';
if (!is_dir($TMP_DIR)) @mkdir($TMP_DIR, 0777, true);
if (!is_dir($TMP_DIR)) out("ERROR: no puedo crear tmp/");

// Nombre archivo tmp
$stamp = date('Ymd_His');
$rand  = bin2hex(random_bytes(3));

// Archivo final que abriremos (WRAPPER)
$tmpFile = $TMP_DIR . DIRECTORY_SEPARATOR . "printwrap_{$stamp}_{$rand}.html";

// Descargar HTML del ticket
$ctx = stream_context_create([
    'http' => [
        'timeout' => 15,
        'user_agent' => 'POS-PRINT/1.1',
    ],
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
    ]
]);

$html = @file_get_contents($url, false, $ctx);
if ($html === false || trim($html) === '') {
    out("ERROR: no pude descargar el ticket\nURL: $url");
}

// ✅ Crear WRAPPER que auto-imprime (sin diálogo si Chrome tiene --kiosk-printing)
$jsHtml = json_encode($html, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$wrapper = "<!doctype html>
<html lang=\"es\">
<head>
  <meta charset=\"utf-8\">
  <meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">
  <title>POS Print</title>
</head>
<body style=\"margin:0;padding:0;\">
  <iframe id=\"f\" style=\"position:absolute;left:-9999px;top:-9999px;width:1px;height:1px;border:0;\"></iframe>
  <script>
    (function(){
      var html = $jsHtml;
      var f = document.getElementById('f');
      f.srcdoc = html;

      f.onload = function(){
        setTimeout(function(){
          try {
            f.contentWindow.focus();
            f.contentWindow.print();
          } catch(e) {}

          // cerrar después de mandar a imprimir
          setTimeout(function(){
            try { window.close(); } catch(e) {}
          }, 700);
        }, 350);
      };
    })();
  </script>
</body>
</html>";

if (@file_put_contents($tmpFile, $wrapper) === false) {
    out("ERROR: no pude guardar wrapper\nFILE: $tmpFile");
}

// Detectar Chrome
$chromeCandidates = [
    'C:\Program Files\Google\Chrome\Application\chrome.exe',
    'C:\Program Files (x86)\Google\Chrome\Application\chrome.exe',
];

$chromeExe = '';
foreach ($chromeCandidates as $c) {
    if (is_file($c)) { $chromeExe = $c; break; }
}
if ($chromeExe === '') {
    out("ERROR: no encuentro Chrome instalado en rutas típicas.\nInstala Chrome o ajusta la ruta en imprimir_pos.php");
}

// Convertir a file:///...
$winPath = str_replace('\\', '/', $tmpFile);
$fileUrl = 'file:///' . ltrim($winPath, '/');

// ✅ Perfil único por impresión (evita que Chrome ignore flags por perfil ocupado)
$profileDir = $TMP_DIR . DIRECTORY_SEPARATOR . 'chrome_profile_' . $stamp . '_' . $rand;
if (!is_dir($profileDir)) @mkdir($profileDir, 0777, true);
$profileDir = str_replace('\\', '/', $profileDir);

// Ejecutar Chrome (minimizado) + kiosk-printing
$cmd = 'start "" /min "'.$chromeExe.'" '
     . '--kiosk-printing '
     . '--new-window '
     . '--no-first-run '
     . '--no-default-browser-check '
     . '--disable-session-crashed-bubble '
     . '--no-remote '
     . '--user-data-dir="'.$profileDir.'" '
     . '"'.$fileUrl.'"';

@pclose(@popen($cmd, 'r'));

out("OK: enviado a imprimir en ASUS.\nTicket: $url\nWrapper: $tmpFile\nCmd: $cmd");