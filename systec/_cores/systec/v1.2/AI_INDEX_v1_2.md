# AI_INDEX_v1_2.md — SysProC2K / SysTec (lectura estable para chats GPT)

> Objetivo: abrir archivos **sin depender del árbol de GitHub** (que a veces falla con “Uh oh…”).
> Usen preferentemente **RAW** (texto plano). Menos clicks = menos bloqueo.

---

## 0) Links base (siempre útiles)

- Repo (UI): https://github.com/C2kAdmin/sysproc2k
- Rama v1.2 (UI): https://github.com/C2kAdmin/sysproc2k/tree/v1.2
- Branches: https://github.com/C2kAdmin/sysproc2k/branches

### RAW (patrón para cualquier archivo)
`https://raw.githubusercontent.com/C2kAdmin/sysproc2k/v1.2/<RUTA_DEL_ARCHIVO>`

Ejemplo:
- https://raw.githubusercontent.com/C2kAdmin/sysproc2k/v1.2/README.md

---

## 1) Docs raíz (claves)

- README (RAW)
  https://raw.githubusercontent.com/C2kAdmin/sysproc2k/v1.2/README.md
- Registro v1.2 (RAW)
  https://raw.githubusercontent.com/C2kAdmin/sysproc2k/v1.2/REGISTRO_v1_2.md

---

## 2) CORE SysTec v1.2 (rutas clave del sistema)

### Entradas / navegación
- Login (RAW)
  https://raw.githubusercontent.com/C2kAdmin/sysproc2k/v1.2/systec/_cores/systec/v1.2/login.php
- Router (RAW)
  https://raw.githubusercontent.com/C2kAdmin/sysproc2k/v1.2/systec/_cores/systec/v1.2/router.php
- Dashboard (RAW)
  https://raw.githubusercontent.com/C2kAdmin/sysproc2k/v1.2/systec/_cores/systec/v1.2/dashboard.php
- Logout (RAW)
  https://raw.githubusercontent.com/C2kAdmin/sysproc2k/v1.2/systec/_cores/systec/v1.2/logout.php
- Reset demo (RAW)
  https://raw.githubusercontent.com/C2kAdmin/sysproc2k/v1.2/systec/_cores/systec/v1.2/reset_demo.php

### Config principal (muy importante)
- config.php (RAW)
  https://raw.githubusercontent.com/C2kAdmin/sysproc2k/v1.2/systec/_cores/systec/v1.2/config/config.php
- auth.php (RAW)
  https://raw.githubusercontent.com/C2kAdmin/sysproc2k/v1.2/systec/_cores/systec/v1.2/config/auth.php
- session_config.php (RAW)
  https://raw.githubusercontent.com/C2kAdmin/sysproc2k/v1.2/systec/_cores/systec/v1.2/config/session_config.php
- instance.php (RAW)
  https://raw.githubusercontent.com/C2kAdmin/sysproc2k/v1.2/systec/_cores/systec/v1.2/config/instance.php
- instance.example.php (RAW)
  https://raw.githubusercontent.com/C2kAdmin/sysproc2k/v1.2/systec/_cores/systec/v1.2/config/instance.example.php
- config_publico.php (RAW)
  https://raw.githubusercontent.com/C2kAdmin/sysproc2k/v1.2/systec/_cores/systec/v1.2/config/config_publico.php
- config_parametros.php (RAW)
  https://raw.githubusercontent.com/C2kAdmin/sysproc2k/v1.2/systec/_cores/systec/v1.2/config/config_parametros.php
- config_mensajes.php (RAW)
  https://raw.githubusercontent.com/C2kAdmin/sysproc2k/v1.2/systec/_cores/systec/v1.2/config/config_mensajes.php

### Layout / includes
- header.php (RAW)
  https://raw.githubusercontent.com/C2kAdmin/sysproc2k/v1.2/systec/_cores/systec/v1.2/includes/header.php
- sidebar.php (RAW)
  https://raw.githubusercontent.com/C2kAdmin/sysproc2k/v1.2/systec/_cores/systec/v1.2/includes/sidebar.php
- footer.php (RAW)
  https://raw.githubusercontent.com/C2kAdmin/sysproc2k/v1.2/systec/_cores/systec/v1.2/includes/footer.php

---

## 3) Módulo Órdenes (CORE) — lo que se toca siempre

### Listados / creación / detalle
- Ordenes (RAW)
  https://raw.githubusercontent.com/C2kAdmin/sysproc2k/v1.2/systec/_cores/systec/v1.2/order/ordenes.php
- Orden nueva (RAW)
  https://raw.githubusercontent.com/C2kAdmin/sysproc2k/v1.2/systec/_cores/systec/v1.2/order/orden_nueva.php
- Orden detalle (RAW)
  https://raw.githubusercontent.com/C2kAdmin/sysproc2k/v1.2/systec/_cores/systec/v1.2/order/orden_detalle.php
- Orden editar (RAW)
  https://raw.githubusercontent.com/C2kAdmin/sysproc2k/v1.2/systec/_cores/systec/v1.2/order/orden_editar.php
- Orden diagnóstico (RAW)
  https://raw.githubusercontent.com/C2kAdmin/sysproc2k/v1.2/systec/_cores/systec/v1.2/order/orden_diagnostico.php
- Ordenes buscar (RAW)
  https://raw.githubusercontent.com/C2kAdmin/sysproc2k/v1.2/systec/_cores/systec/v1.2/order/ordenes_buscar.php
- Ordenes día (RAW)
  https://raw.githubusercontent.com/C2kAdmin/sysproc2k/v1.2/systec/_cores/systec/v1.2/order/ordenes_dia.php

### Evidencias / seguimiento / firmas
- Orden evidencias (RAW)
  https://raw.githubusercontent.com/C2kAdmin/sysproc2k/v1.2/systec/_cores/systec/v1.2/order/orden_evidencias.php
- Evidencia ver (RAW)
  https://raw.githubusercontent.com/C2kAdmin/sysproc2k/v1.2/systec/_cores/systec/v1.2/order/evidencia_ver.php
- Evidencia view (RAW)
  https://raw.githubusercontent.com/C2kAdmin/sysproc2k/v1.2/systec/_cores/systec/v1.2/order/evidencia_view.php
- Evidencia pública (RAW)
  https://raw.githubusercontent.com/C2kAdmin/sysproc2k/v1.2/systec/_cores/systec/v1.2/order/evidencia_publica.php
- Evidencia eliminar (RAW)
  https://raw.githubusercontent.com/C2kAdmin/sysproc2k/v1.2/systec/_cores/systec/v1.2/order/evidencia_eliminar.php
- Seguimiento orden (RAW)
  https://raw.githubusercontent.com/C2kAdmin/sysproc2k/v1.2/systec/_cores/systec/v1.2/order/seguimiento_orden.php
- Firmas pendientes (RAW)
  https://raw.githubusercontent.com/C2kAdmin/sysproc2k/v1.2/systec/_cores/systec/v1.2/order/firmas_pendientes.php
- Firma registrar (RAW)
  https://raw.githubusercontent.com/C2kAdmin/sysproc2k/v1.2/systec/_cores/systec/v1.2/order/firma_registrar.php

### Ticket / PDF
- Orden ticket (RAW)
  https://raw.githubusercontent.com/C2kAdmin/sysproc2k/v1.2/systec/_cores/systec/v1.2/order/orden_ticket.php
- Ticket view (RAW)
  https://raw.githubusercontent.com/C2kAdmin/sysproc2k/v1.2/systec/_cores/systec/v1.2/order/ticket_view.php
- Orden PDF (RAW)
  https://raw.githubusercontent.com/C2kAdmin/sysproc2k/v1.2/systec/_cores/systec/v1.2/order/orden_pdf.php
- Orden PDF público (RAW)
  https://raw.githubusercontent.com/C2kAdmin/sysproc2k/v1.2/systec/_cores/systec/v1.2/order/orden_pdf_publico.php
- Entrega PDF (RAW)
  https://raw.githubusercontent.com/C2kAdmin/sysproc2k/v1.2/systec/_cores/systec/v1.2/order/entrega_pdf.php
- Plantilla HTML PDF (RAW)
  https://raw.githubusercontent.com/C2kAdmin/sysproc2k/v1.2/systec/_cores/systec/v1.2/plantillas/orden_pdf_html.php

### POS Print (superadmin helper)
- superadmin_pos_print.php (RAW)
  https://raw.githubusercontent.com/C2kAdmin/sysproc2k/v1.2/systec/_cores/systec/v1.2/order/superadmin_pos_print.php

---

## 4) Módulo Usuarios (CORE)

- usuarios.php (RAW)
  https://raw.githubusercontent.com/C2kAdmin/sysproc2k/v1.2/systec/_cores/systec/v1.2/users/usuarios.php
- usuarios_guardar.php (RAW)
  https://raw.githubusercontent.com/C2kAdmin/sysproc2k/v1.2/systec/_cores/systec/v1.2/users/usuarios_guardar.php
- usuarios_editar.php (RAW)
  https://raw.githubusercontent.com/C2kAdmin/sysproc2k/v1.2/systec/_cores/systec/v1.2/users/usuarios_editar.php
- usuarios_actualizar.php (RAW)
  https://raw.githubusercontent.com/C2kAdmin/sysproc2k/v1.2/systec/_cores/systec/v1.2/users/usuarios_actualizar.php
- usuarios_eliminar.php (RAW)
  https://raw.githubusercontent.com/C2kAdmin/sysproc2k/v1.2/systec/_cores/systec/v1.2/users/usuarios_eliminar.php
- usuarios_activar.php (RAW)
  https://raw.githubusercontent.com/C2kAdmin/sysproc2k/v1.2/systec/_cores/systec/v1.2/users/usuarios_activar.php

---

## 5) Notificaciones (CORE)

- mark_read.php (RAW)
  https://raw.githubusercontent.com/C2kAdmin/sysproc2k/v1.2/systec/_cores/systec/v1.2/notices/mark_read.php

---

## 6) CLIENTE1 — TEC (instancia de ejemplo)

### Public (entrada del cliente)
- index.php (RAW)
  https://raw.githubusercontent.com/C2kAdmin/sysproc2k/v1.2/systec/_clients/cliente1/tec/public/index.php
- manifest.php (RAW)
  https://raw.githubusercontent.com/C2kAdmin/sysproc2k/v1.2/systec/_clients/cliente1/tec/public/manifest.php
- .htaccess (RAW)
  https://raw.githubusercontent.com/C2kAdmin/sysproc2k/v1.2/systec/_clients/cliente1/tec/public/.htaccess

### Config instancia cliente
- instance.php (RAW)
  https://raw.githubusercontent.com/C2kAdmin/sysproc2k/v1.2/systec/_clients/cliente1/tec/config/instance.php
- instance.example.php (RAW)
  https://raw.githubusercontent.com/C2kAdmin/sysproc2k/v1.2/systec/_clients/cliente1/tec/config/instance.example.php

---

## 7) SUPERADMIN — Creator (si necesitan revisar esa parte)

- Login (RAW)
  https://raw.githubusercontent.com/C2kAdmin/sysproc2k/v1.2/superadmin/systec_creator/login.php
- Clientes (RAW)
  https://raw.githubusercontent.com/C2kAdmin/sysproc2k/v1.2/superadmin/systec_creator/clientes.php
- Crear cliente (RAW)
  https://raw.githubusercontent.com/C2kAdmin/sysproc2k/v1.2/superadmin/systec_creator/cliente_crear.php
- Auditor BD (RAW)
  https://raw.githubusercontent.com/C2kAdmin/sysproc2k/v1.2/superadmin/systec_creator/audit_db.php
- Logout (RAW)
  https://raw.githubusercontent.com/C2kAdmin/sysproc2k/v1.2/superadmin/systec_creator/logout.php

### Config / auth / layout
- _config/config.php (RAW)
  https://raw.githubusercontent.com/C2kAdmin/sysproc2k/v1.2/superadmin/systec_creator/_config/config.php
- _config/auth.php (RAW)
  https://raw.githubusercontent.com/C2kAdmin/sysproc2k/v1.2/superadmin/systec_creator/_config/auth.php
- _layout/header.php (RAW)
  https://raw.githubusercontent.com/C2kAdmin/sysproc2k/v1.2/superadmin/systec_creator/_layout/header.php
- _layout/sidebar.php (RAW)
  https://raw.githubusercontent.com/C2kAdmin/sysproc2k/v1.2/superadmin/systec_creator/_layout/sidebar.php
- _layout/footer.php (RAW)
  https://raw.githubusercontent.com/C2kAdmin/sysproc2k/v1.2/superadmin/systec_creator/_layout/footer.php

---

## 8) Si vuelve el “a veces entra / a veces no”
Regla simple para el equipo:
- Evitar navegar carpetas (tree) y usar buscador de GitHub.
- Abrir 2–5 archivos clave por RAW y listo.
- Si falla, cambiar de archivo (o esperar unos minutos) porque suele ser rate-limit intermitente.

Fin. GitHub a veces se pone exquisito, pero con esto lo leemos igual 😄
