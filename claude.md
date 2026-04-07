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
- **Panel Apoyo Investigación Científica**: 7 módulos: Dashboard (KPIs: proyectos activos, investigadores SNI, publicaciones año, convocatorias), Agenda+Kanban (cve_area=9), Personal, Proyectos (`inv_proyectos` — líder, fondo, periodo), Investigadores (`inv_investigadores` — CVU, Nivel SNI, institución), Publicaciones (`inv_publicaciones` — tipo articulo/libro/capítulo/ponencia, DOI, URL, CSV), Convocatorias (solo lectura `fin_convocatorias`). Paleta: Índigo `#3730a3`. Área: `apoyo_investigacion`, cve_area=9.
- **Panel Formación RRHH**: 7 módulos: Dashboard (KPIs: cursos activos, becas vigentes, acciones igualdad, tareas), Agenda+Kanban (cve_area=10), Personal, Cursos (`rrhh_cursos` — modalidad, horas, cupo/inscritos barra), Becas (`rrhh_becas` — tipos, CSV), Igualdad (`rrhh_igualdad` — folio `IG-YYYY-NNNN`, CSV), Reportes. Paleta: Teal `#0f766e`. Área: `formacion_rrhh`, cve_area=10.
- **Panel Desarrollo Tecnológico y Vinculación**: 7 módulos: Dashboard (KPIs + convenios 30d alert), Agenda+Kanban (cve_area=12), Personal, Proyectos (`dt_proyectos`, tech pills), Convenios (`dt_convenios` folio `CV-YYYY-NNNN`), Transferencias (`dt_transferencias` card-grid), Reportes. Paleta: Violet `#6d28d9`. Área: `desarrollo_tecnologico`, cve_area=12.
- **Panel Asuntos Jurídicos**: 7 módulos: Dashboard (KPIs: expedientes activos, dictámenes pendientes, acuerdos vigentes, tareas + 2 tablas resumen), Agenda+Kanban (cve_area=19), Personal, Expedientes (`aj_expedientes` — folio `EJ-YYYY-NNNN`, tipo admin/laboral/civil/penal, tribunal, CSV), Dictámenes (`aj_dictamenes` — folio `DJ-YYYY-NNNN`, materia, área_solicitante, card-grid, CSV), Acuerdos (`aj_acuerdos` — folio `AR-YYYY-NNNN`, tipo acuerdo/resolución/circular, área_responsable, CSV), Reportes (breakdown por tipo/materia + 3 CSV). Paleta: Crimson `#991b1b`. Área: `juridico`, cve_area=19. DIFERENTE de `juridico_igualdad` (cve_area=17, contratos/normatividad/igualdad).

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
- **Identidad Extendida de Directivos**: La tabla `cat_personal` incluye `rol_jefatura` (jefe_departamento, director_area) y `nombre_jefatura`. Estas columnas proveen un badge y descripciones personalizadas de las subdivisiones debajo del `cve_area` para organigramas u honoríficos. Así mismo, la columna `jefe_directo_id` vincula múltiples empleados a un líder para delegación de tareas y reportes de subordinados.
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
- **Archivos de Base de Datos Encoding Issue (.sql)**: Algunas bases o dumps (como `init.sql` creado en SSMS/PGAdmin Windows) quedan en formato **UTF-16LE**. Los agentes/parsers asumen UTF-8 y lanzan el error "unsupported mime type text/plain; charset=utf-16le". **Solución**: No usar manipulación automática de texto plano directamente. Es más seguro ejecutar sentencias `ALTER TABLE` directas al container en vez de tratar de manipular estáticamente el volcado desde una herramienta a menos de recodificar a UTF-8.
- **Corrupción de Archivos en PowerShell**: El uso nativo de `Set-Content` o `$content -replace` en Windows PowerShell 5.1 a menudo daña la codificación guardando silenciosamente en UTF-16 LE o ANSI, corrompiendo código PHP. **Solución**: Aplicar SIEMPRE la API de .NET nativa `[IO.File]::WriteAllText($file, $content, New-Object System.Text.UTF8Encoding $False)` para guardar estrictamente en UTF-8 sin BOM.

### 🔐 Seguridad y Sesiones
- **Fuga de Datos Global en Calendarios/Kanban (Aislamiento Multi-Tenant)**: La tabla original `eventos` no contaba con filtrado local (`cve_area`), ocasionando que la información de áreas aisladas se cruzara con el panel global o entre departamentos. **Solución**: Añadida la columna `cve_area` y parcheadas **22 instancias** de dependencias de `calendario.php` restringiendo las sentencias SQL de lectura y escritura (`publico = TRUE OR cve_area = ?`), blindando el hermetismo de cada Intranet departamental.
- **Loop de Redirección (Auth)**: Causado por headers HTTP 302 conflictivos. **Solución**: Redirección por Meta-Refresh en HTML.
- **🚫 CSRF void-vs-bool en APIs JSON (`api/perfil.php`)**: La función `validarCsrfPost()` en `helpers.php` es de tipo `:void` y llama `mostrarError()` (que emite HTML). Si una API JSON la llama como `if (!validarCsrfPost())`, PHP no puede negar un valor void, y el HTML de error rompe el `JSON.parse()` cliente. **Solución**: Validar CSRF inline dentro de las APIs JSON comparando `$_POST['csrf_token']` con `$_SESSION['csrf_token']` directamente. Además, las APIs AJAX que leen `$_SESSION` deben llamar `inicializarSesion()` explícitamente al inicio, antes de leer cualquier dato de sesión.
- **🚨 Notificaciones Silenciadas (Type Mismatch) 🚨**: Las APIs de notificación comparaban IDs con prefijo ('A1', 'P2') contra columnas INTEGER (`destinatario_id`), resultando siempre en 0 pendientes. **Solución**: Refactorizar la query para usar IDs numéricos y separar la lógica de destinatario por columnas de rol (`destinatario_id` vs `destinatario_usuario_id`).
- **🔗 Chat 404 en Notificaciones (Navigation Bug)**: Las notificaciones apuntaban a `admin/chat.php`, pero el chat administrativo es un panel flotante en el Dashboard. **Solución**: Redirigir a `admin/dashboard.php?openChat=1` e interceptar el parámetro en `header_admin.php` para disparar `toggleChat()`automáticamente.
- **✨ Especificación de Sistemas Web en Solicitudes ✨**: Para tener más control sobre qué aplicación presentaba incidencias, se integró el registro y captura del campo `sistema_especifico` si el ticket es del tipo Sistemas. Este dato se propaga desde el form `public/nueva_solicitud` a la pre-visualización `public/consulta`, los listados `admin/solicitudes`, la cabecera del `admin/detalle` y hasta la tabla CSV de `admin/export_csv`.
- **📦 Refactorización Chat (Reducción Masiva de Código)**: Al extraer el chat a `chat_widget.php`, se eliminaron >1,200 líneas duplicadas en los headers. **Nota**: Esto es intencional para mejorar la modularidad; cualquier cambio en el chat ahora se hace en un solo archivo y afecta a todo el sistema.
- **✨ Chat V5.3 Platinum Elite ✨**: Rediseño de alto impacto con tipografía "Outfit", sombras profundas y **FIX DE TELEPORT** para modales. Los modales se mueven dinámicamente al final del `body` para garantizar centrado absoluto en el viewport, evitando restricciones de contenedores padres en el header.
- **PHP Tag Missing**: Omitir `<?php` al inicio tras una edición automática. **Solución**: Verificar siempre la etiqueta de apertura en reemplazos de bloque.

### 🖼️ UI/UX y Layout
- **Tarjetas de Usuarios**: Se implementó una separación explícita de personal dado de baja/inactivo en un grid bloqueado al fondo de `personal.php`, enviándolos a un arreglo propio (`$inactivos`) para no entorpecer ni saturar los bloques principales de la plantilla activa.
- **Desfasado de Títulos en Alertas**: Títulos absolutos tapan la imagen. **Solución**: Mover títulos a contenedores externos (footer/header) fuera del frame de la imagen.
- **🚨 Regresión de Layout en Topbar (Div no cerrado) 🚨**: Un `div` sin cerrar en el `globalSearchWrapper` puede causar que los botones de acción (`darkMode`, `chat`, `IA`) se aniden incorrectamente y se apilen verticalmente. **Solución**: Verificar siempre que el contenedor de búsqueda se cierre antes de los botones de acción para mantener el flujo horizontal del `topbar-actions`.
- **Avatares Gigantes**: Para el diseño "Wow" de 300px, usar `margin` correctivo para no romper el grid de 3 columnas del Hero.
- **📅 Calendario — Desbordamiento de Celdas**: El `<div class="calendar-cell">` es un contenedor `display:flex` dentro de un `display:grid`. Sin `overflow:hidden` y `width:100%` en la celda, los hijos flex se expanden al ancho del texto o la columna colapsa. La combinación `width:100% + min-width:0 + overflow:hidden` en `.calendar-cell` fuerza al grid a repartir el espacio correctamente y constriñe el contenido (ellipsis). El fix global está en `assets/css/admin_extra.css`.
- **🚨 Desfase de Texto en Alertas (Flex vs Block) 🚨**: Las alertas de éxito en formularios (`registro.php`) pueden heredar `display: flex` de clases genéricas, lo que provoca que los párrafos de texto se alineen como columnas horizontales ("desfasado"). **Solución**: Forzar `display: block !important` o usar contenedores con ancho completo y `text-align: center` para asegurar que el flujo de lectura sea vertical y uniforme.
- **🚨 Sincronización Fantasma de Notificaciones (Chat no limpia badges) 🚨**: Al abrir el chat, los mensajes se veían, pero las notificaciones en la campana (NotificationBell) seguían marcando mensajes pendientes. **Causa**: El cliente frontend consumía los mensajes por fetch (`=listar`) pero nunca disparaba el trigger inverso (`=marcar_leido`) hacia el servidor, manteniendo el `ultimo_id_leido` en el pasado dentro de la tabla `sb_chat_lectura`. **Solución**: Inyectar una función `marcarLeidoServidor(ultimoId)` cada vez que los mensajes se renderizan con el chat abierto, seguido de un `.fetch()` síncrono al NotificationBell.
- **🚨 Botón Asistente IA inactivo (Código Sobreescrito) 🚨**: Al pulsar el "Asistente IA", la interfaz gráfica marcaba el evento como nulo. **Causa**: Durante el refactoring "Chat V5.1 Platinum" se reemplazó el archivo `chat_widget.php` sin migrar el código adyacente del widget de IA (HTML + JS), eliminando la declaración `toggleAsistenteIA()` y dejando un botón "fantasma" en el menú. **Solución**: Revisión del historial de Git (`git show`), recuperación en un archivo aislado `ia_widget.php` y su posterior inclusión universal en `header_admin.php`. Adicionalmente, el frontend había perdido la inyección del payload `csrf_token`, propiciando bloqueos de seguridad tipo 403 al responder la IA, lo cual fue solventado reintegrando la variable global de sesión en el constructor FormData. **Fase 2 de solución (Backend)**: El código del backend (`agente_ia.php`) también había sido modificado para buscar un supuesto contenedor `ai_ollama` local de AI que no existía. Se rastreó el historial en Git y se retrocedió al manejador asíncrono hacia `Groq Cloud API` utilizando la llave API `.env` oficial.
- **🚨 Error de Referencia Oculta (JS Polling) 🚨**: Bug silenciado al cerrar el modal de chat. `detenerPoll is not defined` vs `stopPoll()`. Provocaba que el polling nunca se detuviera si se declaraban nombres distintos, sobrecargando el servidor en background. **Solución**: Uniformar la nomenclatura a `stopPoll()` en la limpieza de triggers de toggle.
- **✨ Reacciones y Emojis en Chat**: Se restauró la capacidad visual y de backend para colocar emojis en el input y agregar Reacciones debajo de los mensajes (`reaccionar(id, emoji)`), las cuales ya estaban operativas en SQL pero inalcanzables desde el nuevo UI interactivo "Platinum".
- **🗑️ Auto-limpieza Nocturna de Historial (Visual)**: A petición, todos los mensajes en el Chat Grupal quedan ocultos al iniciar el día (medianoche) limitando la carga a `CURRENT_DATE`, agregando una excepción para `cve_area = 1` y administradores/coordinadores o rol "sistemas", a fin de preservar la capacidad técnica de auditoría manteniendo limpios los UI de las otras áreas.
- **🚨 Error de Asistencia SS (Mismatch de Timezone) 🚨**: Los registros de entrada/salida en `ss_asistencia` fallaban o se bloqueaban incorrectamente. **Causa**: La columna era `timestamp without time zone` (ya local), pero las queries usaban `AT TIME ZONE 'America/Mexico_City'`, lo que provocaba un desplazamiento de +6 horas (hacia UTC) al comparar con la fecha de PHP. **Solución**: Eliminar `AT TIME ZONE` de las consultas en `dashboard.php` y `accion.php`, comparando directamente `DATE(fecha_hora) = :hoy`.
- **🚫 Error Fatal de JS en Dashboard SS (`ReferenceError`) 🚫**: Los botones no hacían nada y la consola mostraba `registrarAsistencia is not defined`. **Causa**: Un error de sintaxis en `cargarEvidencias` (ternarios mal cerrados en template literals) y el uso de la función inexistente `escHtml` hacían que todo el bloque `<script>` fallara al cargar. **Solución**: Corregir la sintaxis de los backticks y definir `escHtml` al inicio del bloque JS.
- **Truncado de Calendario**: Flex-items no se limitan solos. **Solución**: Usar `min-width: 0` + `overflow: hidden` en celdas de calendario.
- **🚨 Error de Caracteres Especiales en JS Inline (Píldoras) 🚨**: El uso de `onclick="agVerEvento('<?php echo addslashes($titulo); ?>', ...)"` se rompe cuando el título contiene comillas dobles, ya que `addslashes` escapa para PHP/JS pero no para el atributo HTML que usa comillas dobles para delimitar el JS. **Solución definitiva**: Usar atributos `data-titulo="..."` en el HTML (con `esc()` / `htmlspecialchars`) y leerlos en JS con `element.dataset.titulo`. Esto es inmune a cualquier carácter especial.
- **🚨 Homologación Estética de Píldoras (Post-it Admin) 🚨**: Todas las áreas deben usar el diseño de botones de `admin/calendario.php`. Esto implica:
    - Botones de acción (`.evento-acciones`) ocultos por defecto (`opacity:0`, `max-height:0`).
    - Revelado suave en hover (`.evento-pildora:hover .evento-acciones`).
    - Botones `.btn-evento-accion` con fondo blanco semitransparente (`rgba(255,255,255,.7)`).
    - Clic en la píldora abre el modal de detalle automáticamente.
- **Regla de Permisos Calendarios**: Áreas solo editan `df_eventos_editoriales` donde `cve_area = {suya}`. Los eventos de la tabla `eventos` (publicos=TRUE) son **solo lectura** para todas las áreas. Solo **Dirección General** y **Sistemas** pueden escribir en `eventos`.

---

## 🤖 Subagentes & IA
- **Agente IA Local**: Integración con Ollama (`host.docker.internal:11434`) / Groq Cloud API.
- **Capacidades**: Generación de código, auditoría de seguridad y asistencia en chat administrativo.
- **Workflows Disponibles**:
  - `/system_sync`: Sincronización completa de Docker y Git.
  - `/db_health`: Verificación de salud de PostgreSQL.
- **Skills Reutilizables** (auto-cargar cuando sean relevantes):
  - `.agents/skills/agenda-area-calendar.md` — Módulo Agenda+Kanban de área (modales, cumpleaños, permisos, bugs).
  - `.agents/skills/departamentos-en-desarrollo.md` — Estado de módulos departamentales.
- **Scripts de Mantenimiento** (`_agents/scripts/`):
  - `replicate_agenda.ps1` — Propaga `desarrollo_tecnologico/agenda.php` → 5 áreas restantes.
  - `check_agenda.ps1` — Verifica que todos los agenda.php tengan el fix de modales aplicado.
