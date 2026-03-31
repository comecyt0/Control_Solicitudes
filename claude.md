# Proyecto: COMECyT — Intranet Institucional

## 🎯 Visión General
Sistema integral de gestión de solicitudes, agenda institucional, comunicación interna y repositorio multimedia para el COMECyT. Diseñado bajo una arquitectura de "Intranets Departamentales" (19 áreas) orquestadas por un Hub Global de alto impacto visual.

### Objetivos Clave:
- Digitalización de procesos administrativos.
- Comunicación en tiempo real (Chat Híbrido).
- Gestión de evidencias y transparencia institucional.
- Planeación estratégica mediante calendarios editoriales e institucionales.
- **Repositorio de Documentos (DG)**: Gestión de archivos con carpetas anidadas, drag-and-drop, vista previa (imágenes, PDFs). Tablas: `dg_repo_carpetas`, `dg_repo_archivos`. Archivos en `public/uploads/dg_repo/{carpeta_id}/`. API: `areas/direccion_general/api/repositorio.php`.
- **Panel Jurídico Administrativo**: 7 módulos: Dashboard, Agenda+Kanban (`df_eventos_editoriales`/`sb_kanban_tareas` filtrado por `cve_area=17`), Personal (solo lectura `cat_personal`), Contratos (`ja_contratos`), Normatividad (`ja_normatividad` + carpetas fijas), Igualdad (`ja_casos_igualdad` con folio `EQ-YYYY-NNNN`), Adquisiciones (`ja_adquisiciones` + CSV export). Uploads en `public/uploads/ja_contratos/`. Área: `juridico_igualdad`, cve_area=17. Agregado a `$areas_funcionales` en `header_admin.php`.
- **Panel Financiamiento**: 7 módulos: Dashboard (KPIs: convocatorias, becas, presupuesto%, tareas), Agenda+Kanban (cve_area=15), Personal, Convocatorias (`fin_convocatorias`, auto-cierre expiradas), Becas (`fin_becas` vinculadas a convocatoria), Presupuesto (`fin_presupuesto` por partida con barra progreso), Reportes (CSV 3 módulos). Paleta: Verde `#064e3b`. Área: `financiamiento`, cve_area=15. Agregado a `$areas_funcionales`.
- **Panel Apoyo Investigación Científica**: 7 módulos: Dashboard (KPIs: proyectos activos, investigadores SNI, publicaciones año, convocatorias), Agenda+Kanban (cve_area=9), Personal, Proyectos (`inv_proyectos` — líder, fondo, periodo), Investigadores (`inv_investigadores` — CVU, Nivel SNI, institución), Publicaciones (`inv_publicaciones` — tipo articulo/libro/capítulo/ponencia, DOI, URL, CSV), Convocatorias (solo lectura `fin_convocatorias`). Paleta: Índigo `#3730a3`. Área: `apoyo_investigacion`, cve_area=9. Agenda generada adaptando `juridico_igualdad/agenda.php` con PowerShell sed.

---

## 💻 Comandos Clave
Para garantizar la estabilidad del entorno Docker en Windows (sin Bind-Mount dinámico), es obligatorio seguir estos protocolos:

- **Despliegue Completo**: `docker compose up -d --build app` (Obligatorio para aplicar cambios en PHP/CSS/JS).
- **Control de Servicios**: `docker compose up -d`, `docker compose stop`.
- **Base de Datos (PSQL)**: `docker exec -it comecyt_db psql -U comecyt_user -d bd_sisibic`
- **Sincronización Git**: `git add . ; git commit -m "mensaje" ; git push` (Usar `;` en PowerShell).

---

## 🎨 Guías de Estilo & UX
- **Estética Premium**: Uso de **Glassmorphism**, bordes `20px+`, sombras profundas (`0 15px 45px rgba(0,0,0,0.15)`) y desenfoque de fondo.
- **Tipografía**: Familia 'Inter' (300 a 700).
- **Iconografía**: FontAwesome 6 (Sólidos y Regulares).
- **Animaciones**: Máximo respeto a la fluidez. Usar `IntersectionObserver` para reveals (`.reveal-up`). Prohibido: Rotaciones de 360° o pulsos agresivos.
- **Branding**: Títulos de pestaña con sufijo `| COMECyT Intranet`. Favicon institucional obligatorio (`assets/MARCA.png`).

---

## 🧠 Contexto Importante (Arquitectura)
- **Ruteo Centralizado**: `public/router.php` es el director de tráfico. Mapea `cve_area` a slugs de carpetas físicas en `/areas/`.
- **Autenticación Híbrida**: Soporta `admin_id` y `user_id` (Personal). Centralizado en `config/auth.php`.
- **Chat Multi-tenant**: Aislado por `cve_area` y persistido en `sb_chat_lectura` (notificaciones proactivas).
- **Universal Widgets**: Se utiliza una arquitectura de componentes extraíbles (`includes/chat_widget.php`, `includes/help_widget.php`) para mantener las cabeceras (`header_admin`, `header_user`, `header_ss`) delgadas, consistentes y fáciles de mantener.
- **Constantes de Ruta**: Usar siempre `ROOT` para subidas (`move_uploaded_file`) y `BASE_URL` para enlaces front-end.
- **Timezone**: Siempre `America/Mexico_City` tanto en PHP como en PostgreSQL.

---

## 📂 Errores y Soluciones (Memory Log)

### 🚨 Errores de Infraestructura y Docker
- **Cambios no se ven**: En Windows, no hay bind-mount. **Solución**: Reconstruir imagen con `--build app`.
- **Rutas de Upload fallidas**: `dirname(__DIR__, N)` varía según la profundidad del script. **Solución**: Usar la constante global `ROOT`.
- **PowerShell `&&` Error**: El operador `&&` no existe en PS. **Solución**: Usar `;` para separar comandos.

### 🔐 Seguridad y Sesiones
- **Loop de Redirección (Auth)**: Causado por headers HTTP 302 conflictivos. **Solución**: Redirección por Meta-Refresh en HTML.
- **🚫 CSRF void-vs-bool en APIs JSON (`api/perfil.php`)**: La función `validarCsrfPost()` en `helpers.php` es de tipo `:void` y llama `mostrarError()` (que emite HTML). Si una API JSON la llama como `if (!validarCsrfPost())`, PHP no puede negar un valor void, y el HTML de error rompe el `JSON.parse()` cliente. **Solución**: Validar CSRF inline dentro de las APIs JSON comparando `$_POST['csrf_token']` con `$_SESSION['csrf_token']` directamente. Además, las APIs AJAX que leen `$_SESSION` deben llamar `inicializarSesion()` explícitamente al inicio, antes de leer cualquier dato de sesión.
- **🚨 Notificaciones Silenciadas (Type Mismatch) 🚨**: Las APIs de notificación comparaban IDs con prefijo ('A1', 'P2') contra columnas INTEGER (`destinatario_id`), resultando siempre en 0 pendientes. **Solución**: Refactorizar la query para usar IDs numéricos y separar la lógica de destinatario por columnas de rol (`destinatario_id` vs `destinatario_usuario_id`).
- **🔗 Chat 404 en Notificaciones (Navigation Bug)**: Las notificaciones apuntaban a `admin/chat.php`, pero el chat administrativo es un panel flotante en el Dashboard. **Solución**: Redirigir a `admin/dashboard.php?openChat=1` e interceptar el parámetro en `header_admin.php` para disparar `toggleChat()`automáticamente.
- **📦 Refactorización Chat (Reducción Masiva de Código)**: Al extraer el chat a `chat_widget.php`, se eliminaron >1,200 líneas duplicadas en los headers. **Nota**: Esto es intencional para mejorar la modularidad; cualquier cambio en el chat ahora se hace en un solo archivo y afecta a todo el sistema.
- **✨ Chat V5.3 Platinum Elite ✨**: Rediseño de alto impacto con tipografía "Outfit", sombras profundas y **FIX DE TELEPORT** para modales. Los modales se mueven dinámicamente al final del `body` para garantizar centrado absoluto en el viewport, evitando restricciones de contenedores padres en el header.
- **PHP Tag Missing**: Omitir `<?php` al inicio tras una edición automática. **Solución**: Verificar siempre la etiqueta de apertura en reemplazos de bloque.

### 🖼️ UI/UX y Layout
- **Desfasado de Títulos en Alertas**: Títulos absolutos tapan la imagen. **Solución**: Mover títulos a contenedores externos (footer/header) fuera del frame de la imagen.
- **🚨 Regresión de Layout en Topbar (Div no cerrado) 🚨**: Un `div` sin cerrar en el `globalSearchWrapper` puede causar que los botones de acción (`darkMode`, `chat`, `IA`) se aniden incorrectamente y se apilen verticalmente. **Solución**: Verificar siempre que el contenedor de búsqueda se cierre antes de los botones de acción para mantener el flujo horizontal del `topbar-actions`.
- **Avatares Gigantes**: Para el diseño "Wow" de 300px, usar `margin` correctivo para no romper el grid de 3 columnas del Hero.
- **📅 Calendario — Desbordamiento de Celdas**: El `<div class="calendar-cell">` es un contenedor `display:flex` dentro de un `display:grid`. Sin `overflow:hidden` y `width:100%` en la celda, los hijos flex se expanden al ancho del texto o la columna colapsa. La combinación `width:100% + min-width:0 + overflow:hidden` en `.calendar-cell` fuerza al grid a repartir el espacio correctamente y constriñe el contenido (ellipsis). El fix global está en `assets/css/admin_extra.css`.
- **🚨 Error de Asistencia SS (Mismatch de Timezone) 🚨**: Los registros de entrada/salida en `ss_asistencia` fallaban o se bloqueaban incorrectamente. **Causa**: La columna era `timestamp without time zone` (ya local), pero las queries usaban `AT TIME ZONE 'America/Mexico_City'`, lo que provocaba un desplazamiento de +6 horas (hacia UTC) al comparar con la fecha de PHP. **Solución**: Eliminar `AT TIME ZONE` de las consultas en `dashboard.php` y `accion.php`, comparando directamente `DATE(fecha_hora) = :hoy`.
- **🚫 Error Fatal de JS en Dashboard SS (`ReferenceError`) 🚫**: Los botones no hacían nada y la consola mostraba `registrarAsistencia is not defined`. **Causa**: Un error de sintaxis en `cargarEvidencias` (ternarios mal cerrados en template literals) y el uso de la función inexistente `escHtml` hacían que todo el bloque `<script>` fallara al cargar. **Solución**: Corregir la sintaxis de los backticks y definir `escHtml` al inicio del bloque JS.
- **Truncado de Calendario**: Flex-items no se limitan solos. **Solución**: Usar `min-width: 0` + `overflow: hidden` en celdas de calendario.

---

## 🤖 Subagentes & IA
- **Agente IA Local**: Integración con Ollama (`host.docker.internal:11434`).
- **Capacidades**: Generación de código, auditoría de seguridad y asistencia en chat administrativo.
- **Workflows Disponibles**:
  - `/system_sync`: Sincronización completa de Docker y Git.
  - `/branding_check`: (Propuesto) Verificación de cumplimiento de guías de estilo.
