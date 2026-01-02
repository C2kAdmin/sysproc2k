<?php
declare(strict_types=1);
require_once __DIR__ . '/../_config/config.php';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <title>SuperAdmin · SysTec Creator</title>

  <!-- Bootstrap CDN (simple y limpio, sin depender del CORE) -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">

  <style>
    body{background:#f6f7fb;}
    .sa-shell{display:flex; min-height:100vh;}
    .sa-side{width:260px; background:#fff; border-right:1px solid #eee;}
    .sa-main{flex:1;}
    .sa-brand{padding:16px 18px; border-bottom:1px solid #eee; font-weight:700;}
    .sa-nav a{display:block; padding:10px 18px; color:#333; text-decoration:none;}
    .sa-nav a:hover{background:#f3f4f6;}
    .sa-top{background:#fff; border-bottom:1px solid #eee; padding:12px 18px;}
    .sa-content{padding:18px;}
  </style>
</head>
<body>

<div class="sa-shell">
