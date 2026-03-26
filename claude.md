# Proyecto: COMECyT — Control de Solicitudes

## Visión General
Sistema de gestión de solicitudes y agenda institucional para COMECyT. Permite a usuarios externos solicitar espacios de calendario y a administradores gestionar dichas solicitudes.
- **Chat de Solicitudes (Retroalimentación)**: Sistema de comunicación bidireccional entre ciudadanos (público) y personal (admin) dentro de cada solicitud, con soporte para carga de documentos.

## Credenciales de Acceso Actuales
- **Administrador principal**: `desarrollo.comecyt@edomex.gob.mx` / `F3rn4nd0`
- **Las credenciales de demo documentadas en README.md ya no son válidas en producción.**


## Arquitectura
- **Backend**: PHP 8.1 (Vanilla)
- **Base de Datos**: PostgreSQL 15 (Tablas: `solicitudes`, `sb_chat_mensajes`, `solicitud_respuestas`)
- **Servidor**: Apache (Dockerizado)
- **Frontend**: HTML5, Vanilla CSS, JS (ES6+)
- **Nuevas APIs**: `public/api/comentarios_solicitud.php` (Seguimiento externo con folio).
- **Infraestructura**: Docker + Docker Compose

## Estructura de Carpetas
- `/admin`: Panel de administración (Unidad de Sistemas).
- `/areas`: Contenedores físicos independientes para los 19 departamentos (Intranets Privadas).
- `/public`: Hub Global, vistas públicas, Mi Perfil, y recursos de enrutamiento web (`router.php`).
- `/config`: Configuración de base de datos y autenticación centralizada.
- `/includes`: Componentes reutilizables (header, footer, helpers).
- `/assets`: Recursos estáticos (CSS, JS, imágenes).
- `/docker`: Archivos de configuración de Apache y PHP.

## Comandos Clave
- `docker compose up -d`: Inicia los servicios.
- `docker compose build app`: Reconstruye la imagen de la aplicación.
- `docker compose down`: Detiene y elimina contenedores.
- `docker exec -it comecyt_db psql -U comecyt_user -d bd_sisibic`: Acceso a la base de datos.

## Guías de Estilo
- **CSS**: Uso de variables CSS para colores. Diseños limpios con bordes redondeados y sombras suaves (estilo premium).
- **PHP**: PDO para base de datos. Manejo de sesiones centralizado en `auth.php`.
- **JS**: Fetch API para comunicación asíncrona. Notificaciones integradas vía `COMECyTUI` (incluyendo Toasts).

- **Buzón de Solicitudes (v2)**: Sistema de notificaciones integrado con eliminación automática de registros al archivar (mantiene BD limpia).
- **Intranet Dashboard Público**: Se integró un panel central `.intranet-grid` en `public/index.php` con grid de accesos rápidos animados y full-width (se oculta el sidebar seteando `$hideSidebar = true`). Ajustes finos: Ext. "314 y/o 114" y botón Topbar Cerrar Sesión.
- **Formularios y Calendario (Vistas Públicas)**: 
  - `public/nueva_solicitud.php`: Incorpora tarjetas con sombra suave premium, `box-shadow` y opacidad sin recuadros grises, además de minimización del logo institucional (`max-width: 260px`).
  - `public/calendario.php`: Restablece la visualización correcta del `sidebar` garantizando navegabilidad y añade cabecero `.page-header-calendario` altamente resposivno con mejor tipografía e iconografía destacada.
- **Animaciones UI**: Se incorpora IntersectionObserver para reveals de items al scrollear (`.reveal-up`) en el Dashboard, Nueva Solicitud y Calendario.
- **Gestor de Anuncios y Banners**: CRUD disponible en `admin/anuncios.php`. Se superó limitación de directorios en PHP usando `dirname(__DIR__, 2)` para procesar descargas de banners transparentes.

- **Arquitectura Multidepartamento (Fase 3)**: El monolito de `/admin` fue clonado a 19 directorios dentro de `/areas/` (Ej. `areas/juridico/`). El login dirige exclusivamente a `public/index.php` (Hub Global) desde donde el router dinámico `public/router.php` calcula y dirige al departamento exacto. Existe un Firewall en `header_admin.php` que compara la URL con el slug del área autorizado en sesión para prevenir inyecciones cruzadas.
  - *Nota Técnica*: En la "Fase 4", se reemplazará progresivamente el tablero "clónico" de TI para cada una de las 19 áreas con *Módulos Específicos* (ej. Repositorios legales para Jurídico, Galerías Multimedia para Difusión).
- **Perfiles Extensibles (Fase 1)**: Glassmorphism responsivo en `public/perfil.php` permitiendo la carga de fotográfias con aprobación pendiente (guardadas en buffer JSON).
- **Cumpleaños Dinámicos (Fase 2)**: Calendarios iterando en `$pdo` bajo sentencias especiales `UNION` SQL para mezclar las agendas con cumpleaños públicos en color dorado de los usuarios actuales y becarios de servicio.

- **Diseño Premium Departamento Difusión (Fase 4)**: Rediseño UI/UX completo de `areas/difusion/dashboard.php` y `repositorio.php`.
  - **Hero con gradiente full (`--dif-primary` → `--dif-secondary`)**: Sustituyendo el div genérico por un blob coloreado con `box-shadow` coloreado, icono decorativo que anima al hover (NO giro ni pulso, solo `scale + rotate` suave).
  - **Tarjetas Glassmorphism (`stat-card-premium`)**: Borde izquierdo acento de 4px, fondo `rgba(255,255,255,0.85)` + `backdrop-filter: blur`, hover con `translateY(-5px)` y sombra roja.
  - **Tabla premium (`table-premium`)**: Cabeceras uppercase con `letter-spacing`, filas con hover `background: #f8fcff`, badges de tipo como píldoras redondeadas.
  - **Repositorio Grid Masonry (`media-grid`)**: Grid responsivo de tarjetas con preview de imagen real si el archivo es imagen, placeholder de icono si es otro tipo. Filtros de tab para `sketch / logo / video`.
  - **Modal Premium animado**: Overlay con `backdrop-filter: blur(6px)`, modal con `slideUpModal` animation, upload zone interactiva con click-to-select y nombre de archivo dinámico.
  - **Regla Anti-Animaciones**: En este proyecto no se usan animaciones de `360deg` ni keyframes de `pulse`. Solo `translateY`, `scale`, `rotate` pequeños y `opacity` para revelar.

## Contexto Importante
- **BASE_URL**: Crucial para la conectividad entre módulos. Se detecta dinámicamente en `helpers.php`.
- **Sesiones**: Los administradores se validan mediante `verificarSesionAdmin()`.
- **Estatus de Solicitudes**: `pendiente`, `aceptado`, `rechazado`.
- **Skill de Sincronización**: `/system_sync` (en `.agents/workflows/system_sync.md`) facilita la actualización del entorno Docker y Git.
- **Redirección por Área al Login**: `admin/login.php` consulta `cat_personal` para conocer el `cve_area` del admin. Si `cve_area > 1` (no es Sistemas), lo envía al `router.php` que despeja su slug y lo lleva a `/areas/{slug}/dashboard.php`. Si es Sistemas, va al Hub Global (`public/index.php`). **Nunca modificar esta lógica sin también actualizar `router.php`.**
- **Chat filtrado por Área**: `areas/{area}/api/chat.php` comprueba `$_SESSION['area_slug_activa']`. Si no es `sistemas`, hace `INNER JOIN cat_personal` para devolver solo los admins del mismo `cve_area`. Asegura que cada área solo hable con sus compañeros.
- **Calendario Editorial Difusión**: `areas/difusion/calendario_editorial.php` usa la tabla `df_eventos_editoriales` (independiente de `eventos`). Los eventos marcados como `publico=TRUE` se insertan también en la tabla global `eventos`, apareciendo en el Calendario Público. Necesita que la tabla exista en PostgreSQL (ya creada con migr. manual 2026-03-24).
- **Gestión de Área en `admin/personal.php`**: El modal de edición de personal incluye ahora un `<select>` con las 20 áreas de `cat_areas`. Al guardar, se actualiza `cve_area` en `cat_personal`, lo que controla a qué panel se redirige el usuario al hacer login (vía `router.php`). Es el único punto de administración del área de un empleado.
- **Fix Truncado de Títulos en Calendarios**: Se añadió en `assets/css/admin_extra.css` la regla `min-width: 0` a `.evento-pildora` (y `.ev-pill`). **Causa raíz**: un flex-child no limita su ancho por defecto, por lo que `text-overflow: ellipsis` no tiene efecto aunque esté declarado. `min-width: 0` en el contenedor flex fuerza el límite. Esta regla aplica a los 18+ calendarios de áreas y el calendario global de Sistemas, ya que todos cargan `admin_extra.css` via `header_admin.php`.


## Troubleshooting y Quirks del Entorno
- **Botones Invisibles pero Cliqueables (`.reveal-up`)**: Si un elemento animado está situado el fondo del viewport y la propiedad `rootMargin` en Javascript es `-50px`, este jamás cruzará el umbral, por lo tanto retiene `opacity: 0` pero el cursor puede interactuar con él (falso invisible). La solución es establecer un `rootMargin: 0px` para elementos base.
- **Grids Hardcodeados en Formularios**: Formularios con `display: grid; grid-template-columns: 1fr 1fr;` en línea aplastarán la maqueta en pantallas móviles. Usar clases responsivas obligatorias (`.responsive-grid-2`) con breakpoints pre-probados en `<style>` (`max-width: 768px`).
- **Banners Uploads (`api/anuncios.php`)**: Mover archivos temporalmente en Windows PHP subyacente con Docker falla si se usan rutas relativas con `ROOT`. La directiva `@move_uploaded_file` requiere rutas estrictas con `dirname(__DIR__, 2) . '/public/uploads/anuncios/'`.
- **🚨 Alerta de Sincronización de Contenedores Docker (No Bind-Mount) 🚨**: En Windows, la configuración de `docker-compose.yml` para el servicio `app` no realiza un *Bind Mount* directo y dinámico del código en `c:\Intranet`. Por tanto, cualquier archivo programmático modificado en el anfitrión requiere que la imagen sea recompilada desde cero. 
  - ❌ **Comportamiento Erróneo:** Ejecutar `docker compose up -d` tras modificar archivos encenderá el contenedor viejo (desde cache).
  - ✅ **La Solución Estricta:** Para aplicar cambios a los scripts PHP/HTML/CSS en esta arquitectura es obligatorio usar explícitamente **`docker compose up -d --build app`**. Considera implementar Hot-Reload uniendo `volumes: - .:/var/www/html` en el entorno futuro.

- **🚫 CSRF void-vs-bool en APIs JSON (`api/perfil.php`)**: La función `validarCsrfPost()` en `helpers.php` es de tipo `:void` y llama `mostrarError()` (que emite HTML). Si una API JSON la llama como `if (!validarCsrfPost())`, PHP no puede negar un valor void, y el HTML de error rompe el `JSON.parse()` cliente. **Solución**: Validar CSRF inline dentro de las APIs JSON comparando `$_POST['csrf_token']` con `$_SESSION['csrf_token']` directamente. Además, las APIs AJAX que leen `$_SESSION` deben llamar `inicializarSesion()` explícitamente al inicio, antes de leer cualquier dato de sesión.
- **📅 Calendario — Desbordamiento de Celdas**: El `<div class="calendar-cell">` es un contenedor `display:flex` dentro de un `display:grid`. Sin `overflow:hidden` y `width:100%` en la celda, los hijos flex se expanden al ancho del texto o la columna colapsa. La combinación `width:100% + min-width:0 + overflow:hidden` en `.calendar-cell` fuerza al grid a repartir el espacio correctamente y constriñe el contenido (ellipsis). El fix global está en `assets/css/admin_extra.css`.
- **🎂 Cumpleaños — Foto de Perfil en Calendario (Fase 3, 2026-03-24)**:
  - **Bug Fix**: El emoji 🎂 se mostraba como texto literal `\u{1F382}` porque en PHP las comillas simples NO interpolan escapes Unicode. Corregido a dobles comillas: `"\u{1F382}"`. Regla: **siempre dobles comillas para strings con escapes Unicode en PHP**.
  - **Feature**: La query SQL de cumpleaños ahora incluye `foto_perfil` de `cat_personal`.
  - **Feature**: Las notas doradas de cumpleaños en el calendario muestran un mini-avatar circular (foto real si existe, ícono placeholder si no).
  - **Feature**: Al hacer clic en el ojo de una nota de cumpleaños, se abre `modalVerCumple` (en lugar del genérico `modalVerEvento`), con: foto circular con borde dorado, nombre completo, edad calculada dinámicamente, fecha de cumpleaños y mensaje de felicitación.
  - **Campos añadidos al array de evento**: `foto_perfil`, `nombre_cumple`, `edad` (calculada como `$anio - $anioNacimiento`).
  - **Alcance**: Aplicado en `admin/calendario.php`, los 19 calendarios de área en `areas/*/calendario.php` y el calendario público en `public/calendario.php`.

- **🎂 Cumpleaños en Calendario — Filtrar solo por Día y Mes**: La query de cumpleaños usa `EXTRACT(MONTH FROM fecha_nacimiento) = :mes` y luego extrae el día con `format('d')`. Esto ignora el año del registro, mostrando correctamente los cumpleaños de personas con nacimiento en años anteriores. Aplicado en `admin/calendario.php` y en los 19 calendarios de área via script PowerShell batch.

- **🚨 Loop de Redirección Infinito (ERR_TOO_MANY_REDIRECTS) en Login local/Docker 🚨**:
  - **Problema:** Al iniciar sesión (o al ser Servicios Sociales), el usuario puede quedar atrapado en un ciclo infinito entre `admin/login.php` e `index.php`. 
  - **Causa 1 (Headers):** Si el servidor de PHP emite rápidamente un *Location 302* dentro del puerto mapeado (localhost o red local) cuando una sesión ya está activa, el proxy y la caché de Chrome pueden interpretar que la respuesta de Auth fue rebotada, atascando la petición.
  - **La Solución (Fallback HTML):** En lugar de depender de `header('Location: ...')` post-autenticación en `admin/login.php`, se escupe al cliente `<meta http-equiv="refresh" content="0;url=...">`. Esto "rompe" la conexión estricta de headers HTTP y delega la redirección al motor de renderizado HTML del navegador, forzando la evaluación de las cookies desde cero y logrando un login impecable.
  - **Causa 2 (Reglas Auth Contradictorias):** En `verificarSesionUsuario()` (archivo `auth.php`), si no se permitía el retorno anticipado `return;` a los `ss_id` (Servicio Social), el archivo `index.php` los pateaba de regreso al `login.php`. Pero el `login.php` sabía que ellos SÍ tenían sesión, y los devolvía al `index.php`. Este error lógico de ping-pong también fue parcheado para que ambos compartan el mismo conjunto de validaciones de exclusión.

- **🚫 Aislamiento Estricto de Servicio Social (`ss_id`)**:
  - **Comportamiento:** Los usuarios con el rol Servicio Social (becarios) NO deben acceder al Hub Global Público (`public/index.php`), sino exclusivamente a `servicio_social/dashboard.php`.
  - **Doble barrera:** Se actualizó `admin/login.php` para canalizarlos a su panel al loguearse. Adicionalmente, el `public/index.php` tiene una barrera que expulsa a cualquiera que tenga la sesión `ss_id`, llevándolos a la fuerza a su panel. Esto previene que alteren URLs a mano y vulneren su aislamiento.

- **📂 Constante `ROOT` missing**: Muchos scripts API (perfil, multimedia) usaban la constante `ROOT` para subir archivos, pero esta no estaba definida globalmente. Se definió centralmente en `config/database.php` como `dirname(__DIR__)` para asegurar que las rutas absolutas de servidor funcionen siempre en Docker y Windows.
- **🔗 Fetch AJAX con URLs Relativas**: En `public/perfil.php`, el `fetch("api/perfil.php")` fallaba (404) si el usuario accedía desde una URL profunda como `/areas/difusion/dashboard.php`. **Solución**: Se cambió el `action` del formulario a una URL absoluta usando `<?= BASE_URL ?>public/api/perfil.php`, garantizando que el endpoint sea alcanzable desde cualquier contexto de navegación.

- **💬 Chat de Administradores — Estabilidad y Responsividad**:
  - **Mensajes "Stuck" (Bug de Orden)**: El API de chat (`admin/api/chat.php`) en su carga inicial (`desde=0`) obtenía los 100 mensajes más antiguos de la historia. **Solución**: Se implementó una subconsulta que obtiene los ÚLTIMOS 50 mensajes y los ordena ascendentemente, asegurando que el chat inicie en el presente.
  - **📱 Vista Móvil**: El panel de chat tenía un ancho fijo de 580px que desbordaba pantallas pequeñas, ocultando el sidebar y el botón de cerrar.
  - **Solución Mobile-First**: Se implementó `@media (max-width: 650px)` en `header_admin.php`, haciendo el panel `width: 100%`, moviendo el sidebar a vista completa y añadiendo un botón de retroceso (`chat-mobile-back`) para navegar entre la lista de administradores y los mensajes.

- **🚀 Unificación de Acceso por Área (Multi-tenant)**:
    - **Problema**: El personal (no admin) con área asignada no era redirigido correctamente y no tenía acceso visual ni técnico a los dashboards de su área desde el hub público.
    - **Acceso Híbrido**: `verificarSesionAdmin()` en `config/auth.php` ahora permite el acceso si existe `admin_id` O `user_id`, permitiendo que el personal legítimo consulte las herramientas de su departamento.
    - **Router Inteligente**: Se habilitó `public/router.php` para el rol `usuario`. Ahora, cualquier usuario con `cve_area > 1` es canalizado a la carpeta de su área (ej. `areas/difusion/dashboard.php`) automáticamente al loguearse.
    - **Interfaz Adaptativa**: El header público ahora muestra dinámicamente el botón "Mi Área" o "Panel de Admin" basándose en el `cve_area` de la sesión, facilitando el salto a la gestión departamental.

- **🖌️ Rediseño Sidebar Footer (Fase 4, 2026-03-24)**: 
  - **Problema**: El footer del sidebar era demasiado transparente y los botones carecían de distinción profesional.
  - **Mejora**: Se aplicó un fondo sólido `#1a1c23` (Charcoal Premium) con un sutil `box-shadow` superior. Los botones de Perfil y Cerrar Sesión ahora tienen fondos individuales `#2d2f39`, bordes definidos y estados hover con `translateY(-1px)` y sombras profundas, mejorando la ergonomía visual.

- **Resolución de Colisión de IDs y Limpieza (2026-03-25)**:
    - **Solución Técnica**: Se identificó que eventos institucionales y editoriales podían compartir el mismo ID numérico en tablas diferentes. Se implementó un campo oculto `es_institucional` en el formulario de edición para que el servidor distinga inequívocamente el origen del dato y aplique las restricciones de seguridad correspondientes.
    - **Refactorización de Código**: Se realizó una limpieza profunda de `calendario_editorial.php`, eliminando bloques de lógica duplicados que generaban estados inconsistentes.
    - **Seguridad Reforzada**: Ahora es imposible "engañar" al servidor para editar un evento institucional mediante la manipulación de IDs si no se cuenta con los privilegios de Sistemas (cve_area == 1).

- **Sistema de Gestión de Evidencias (Fase 4, 2026-03-24)**:
    - **Staff/Sistemas**: En `admin/detalle.php`, se habilitó la carga de evidencias (fotos, PDF, documentos) con descripciones obligatorias capturadas mediante `COMECyTUI.prompt`.
    - **Cierre de Ciclo**: Al completar una solicitud, el modal de confirmación permite adjuntar el archivo de resolución final.
    - **Transparencia**: Los solicitantes pueden ver y descargar estas evidencias en tiempo real desde la consulta pública (`public/consulta.php`).

- **Corrección de Diseño en Detalle**:
    - **Homologación de Calendarios (Fase 4, 2026-03-25)**: 
    - **Sincronización Premium**: Se extrajeron los estilos visuales de `admin/calendario.php` y se aplicaron a `areas/difusion/calendario_editorial.php`. 
    - **Post-its 3D**: Implementación de tarjetas de eventos con pliegue de página, sombreado realista y efectos de hover tridimensionales uniformes en todo el sistema.
    - **Navegación Unificada**: La barra de navegación de meses y los botones de acción ("Hoy", "Anterior", "Siguiente") ahora comparten el mismo diseño de bordes redondeados y tipografía institucional.
    - **Kanban Pro**: El tablero de tareas de Difusión fue rediseñado para igualar la calidad del administrador global, incluyendo badges dinámicos y estados de hover en las tarjetas de tareas.
    - **Aislamiento de Datos**: Se mantiene la lógica de filtrado por área (`cve_area`) para asegurar que cada departamento solo acceda a su información privada, mientras visualiza los eventos institucionales públicos en el mismo entorno de alta fidelidad.

- **Protección de Áreas en Desarrollo (Fase 4.1, 2026-03-25)**:
    - **Banner de Placeholder**: Se implementó una vista premium de "Área en Desarrollo" en todos los `dashboard.php` de la carpeta `areas/` (excepto Difusión). Esto evita que usuarios de módulos no terminados vean el dashboard administrativo global por error.
    - **Cortafuegos de Ruteo (v4)**: Se extendió el `header_admin.php` con un firewall que detecta si el área del usuario está marcada como "en desarrollo". Si es así, bloquea el acceso a cualquier archivo operativo (solicitudes, reportes, etc.) y redirige automáticamente al Dashboard.
    - **Sidebar Inteligente**: El menú lateral ahora oculta dinámicamente secciones de gestión (Personal, Equipos, Reportes) para áreas en construcción, reduciendo la confusión y el riesgo de acceso a datos sensibles.
    - **Control Centralizado**: La lista de áreas funcionales se gestiona desde `$areas_funcionales` en el header, permitiendo habilitar módulos uno a uno conforme se completan.
    - **Nota Técnica (BOM)**: Todos los archivos PHP deben guardarse estrictamente en **UTF-8 sin BOM**. El uso de BOM causa errores de "Headers already sent" al iniciar sesiones o realizar redirecciones.

- **🚨 Fix Chat No Carga + Aislamiento por Área (v6.1, 2026-03-26)**:
    - **Síntoma 1 — Chat no carga para usuarios de Personal (no-admin)**: `areas/difusion/api/chat.php` solo permitía `admin_id` en sesión. El personal (`user_id`) recibía 401. Solución: cambiar la guardia de auth a `empty($_SESSION['admin_id']) && empty($_SESSION['user_id'])`.
    - **Síntoma 2 — Canal grupal mostraba mensajes de otras áreas**: `listar` en `areas/difusion/api/chat.php` no filtraba por `cve_area`. Solo filt­raba `destinatario_id IS NULL`. Solución: agregar `JOIN` con `cat_personal` y filtrar `p.cve_area = :cve_area`.
    - **Síntoma 3 — Lista de contactos DM mostraba admins de otras áreas**: `admins` en el chat de Difusión usó una subquery compleja por email que podía fallar y tenía fallback a todos los admins del sistema. Solución: reemplazar por la misma query robusta de `admin/api/chat.php`: `SELECT FROM cat_personal WHERE cve_area = ?`.
    - **Síntoma 4 — Las burbujas de mensajes nunca se mostraban como `propio`**: `renderBurbuja()` comparaba `parseInt(m.admin_id) === ADMIN_ID`, pero `m.admin_id` y `ADMIN_ID` son strings con prefijo (`'A5'`, `'P12'`). `parseInt('A5')` devuelve `NaN` — siempre `false`. Solución: `String(m.admin_id) === String(ADMIN_ID)`.
    - **Síntoma 5 — Usuario propio aparecía en lista DM**: `cargarAdmins()` comparaba `aid !== ADMIN_ID` donde `aid = parseInt(a.id)` (NaN para 'A5'). Solución: `String(a.id) !== String(ADMIN_ID)` y usar `a.id` directamente en el listener.
    - **Síntoma 6 — `enviar` usaba `validarCsrfPost()`**: Es `:void` y emite HTML, rompiendo JSON. Solución: validación CSRF inline (misma regla global de APIs JSON).
    - **Síntoma 7 — `enviar` no insertaba `usuario_id`**: Solo inser­taba `admin_id`, dejando `NULL` para mensajes de Personal. Solución: `INSERT ... (admin_id, usuario_id, destinatario_id, destinatario_usuario_id, ...)`.
    - **Archivos modificados**: `areas/difusion/api/chat.php`, `includes/header_admin.php`.
    - **Regla permanente**: Comparar IDs de chat **siempre como string** (`String(a.id) === String(ADMIN_ID)`). Los prefijos A/P hacen que `parseInt` devuelva `NaN`. NUNCA usar `parseInt()` sobre IDs con prefijo alfabético.
    - **Reconstruir Docker**: `docker compose up -d --build app`

- **🚨 Fix Banner de Anuncios No Visible (v5.3, 2026-03-25)**:
    - **Síntoma**: Al crear o editar un anuncio con imagen en `areas/difusion/anuncios.php`, el registro se guardaba correctamente en BD pero la imagen nunca aparecía. El campo `banner_url` quedaba `NULL`.
    - **Causa Raíz**: `areas/difusion/api/anuncios.php` usaba `dirname(__DIR__, 2)` para calcular la ruta de destino del upload. Desde `areas/difusion/api/`, subir 2 niveles con `dirname` llega a `C:\Intranet\areas\` — **NO a la raíz del proyecto**. El directorio `areas/public/uploads/anuncios/` nunca existe, `move_uploaded_file()` retorna `false`, la función retorna `null` silenciosamente (estaba suprimido con `@`), y el registro se guarda sin imagen.
    - **Regla de Cálculo de niveles**: `dirname(__DIR__, N)` desde `areas/{area}/api/` requiere N=3 para llegar a la raíz. Desde `admin/api/` requiere N=2. **Solución definitiva**: usar siempre la constante `ROOT` (definida en `config/database.php`) — es absolutamente independiente de la profundidad del script.
    - **Corrección aplicada**: Reemplazado `dirname(__DIR__, 2)` por `ROOT` en `areas/difusion/api/anuncios.php` y homologado en `admin/api/anuncios.php`. Se eliminó el `@` de supresión de errores y se agregaron `error_log()` explícitos para detectar futuros fallos de escritura.
    - **Regla permanente**: **NUNCA usar `dirname(__DIR__, N)` para rutas de upload**. Siempre usar `ROOT . '/public/uploads/...'`. El operador `@` nunca debe suprimir `move_uploaded_file()` — oculta errores críticos.
    - **Reconstruir Docker**: `docker compose up -d --build app`

- **🚨 Fix CSRF en Repositorio Multimedia Difusión (v5.2, 2026-03-25)**:
    - **Síntoma**: Al intentar subir un archivo en `repositorio.php`, el navegador mostraba un `alert()` con "Error de seguridad CSRF." y el upload no completaba.
    - **Causa Raíz**: `api/repositorio.php` usaba `if (!validarCsrfPost())` — error documentado en §Troubleshooting. La función es `:void`, llama a `mostrarError()` que emite HTML, rompiendo `JSON.parse()` en el `fetch()`. Además, `inicializarSesion()` no se llamaba antes de leer `$_SESSION['csrf_token']`, dejando el token vacío.
    - **Solución**: Validación CSRF inline directa (comparar `$_POST['csrf_token']` con `$_SESSION['csrf_token']`) + llamada explícita a `inicializarSesion()` al inicio de la API.
    - **Regla**: **NUNCA llamar `validarCsrfPost()` en APIs que retornan JSON.** Usar siempre validación inline. Ver ejemplo en `areas/difusion/api/repositorio.php`.
    - **Reconstruir Docker**: `docker compose up -d --build app`

- **Phase 5: Polarización de Difusión (v5.0, 2026-03-25)**:
    - **Unificación de Sesiones**: Se modificó `auth.php` y `header_admin.php` para que el sistema reconozca tanto a `admin_id` como a `user_id` de forma intercambiable en módulos compartidos (Chat, IA, Kanban).
    - **Aislamiento Departamental**: El motor de chat (`admin/api/chat.php`) y la asignación de tareas ahora filtran estrictamente por el `cve_area` del usuario logueado, asegurando que el personal de Difusión solo interactúe con sus compañeros de área.
    - **Corrección de Regresiones**: Se resolvieron los errores de "Headers already sent" eliminando advertencias de claves indefinidas en `sidebar_footer.php` y el header.
    - **Seguridad Multimedia**: Se corrigió la validación CSRF y la detección de autoría en el Repositorio Multimedia y la Gestión de Anuncios.

- **Fase 5.1: Depuración de Calendario Difusión (v5.1, 2026-03-25)**:
    - **Solución FK Violation**: Se cambió el fallback de `admin_id` de `0` a `null` para evitar errores de clave foránea en la base de datos al insertar registros desde cuentas de Personal (No-Admins).
    - **Simplificación de UI**: Se eliminó la opción "Visible al público" del Calendario Editorial para mantenerlo como una herramienta de planeación interna pura.

- **Fase 6: Chat Híbrido Universal (v6.0, 2026-03-25)**:
    - **Identidad Extendida**: Migración de `sb_chat_mensajes` para incluir `usuario_id` (vinculado a `cat_personal`). Esto permite que el personal sin cuenta de administrador participe plenamente en el chat.
    - **Prefijos de ID**: Implementación de sistema de prefijos en API y JS (`A` para Admins, `P` para Personal) para manejar destinatarios de forma unificada.
    - **Visibilidad Total**: El sidebar del chat en Difusión ahora muestra a todos los miembros del área registrados en `cat_personal`.
    - **Avatares Determinísticos**: Actualización de la lógica de color JS para generar avatares consistentes basados en strings alfanuméricos.

## Integración de IA Local (Ollama) — Marzo 2026
- **Tecnología**: [Ollama](https://ollama.com/) corriendo en Docker (`ai_ollama`) con [Open WebUI](https://openwebui.com/) (`ai_webui`) en el puerto `3001`.
- **Modelo**: `qwen2.5-coder:1.5b` (seleccionado por eficiencia en GPU de 2GB).
- **Conectividad App → IA**: 
  - **URL Crítica**: En entornos Docker de Windows, `localhost` falla para comunicación entre contenedores. Se debe usar siempre **`http://host.docker.internal:11434`** en los archivos `config/ai_config.php` y `admin/api/agente_ia.php`.
- **Botón en Sidebar**: Se integró en `includes/header_admin.php` para fácil acceso al asistente.
- **Agente de Edición (Aider)**: Script `iniciar_agente.ps1` disponible en raíz para edición de código por voz/chat vía terminal.

## Historial de Errores y Soluciones Críticas (Log 2026-03-25)
- **Error `Foreign key violation (creado_por)=(28)`**:
  - **Causa**: El usuario logueado tenía ID 28 de `cat_personal` pero no existía en `administradores`. La tabla `df_eventos_editoriales` exigía un ID de admin.
  - **Solución**: Se añadió validación en `areas/difusion/calendario_editorial.php` para verificar la existencia del ID en `administradores`. Si no existe, se inserta `NULL` evitando el `Fatal error`.
- **Error `No se pudo conectar con Ollama Local`**:
  - **Causa**: Uso de `localhost` dentro del contenedor `app` (Docker).
  - **Solución**: Cambio a `host.docker.internal`.
- **Rebuild Obligatorio**: Se refuerza la regla de que **CUALQUIER cambio en PHP/HTML exige `docker compose up -d --build app`** debido a que no existe Bind-Mount dinámico en el stack actual bajo Windows.
- **Error `Botón Eliminar Anuncios no responde` (2026-03-26)**:
  - **Causa**: Argumento de callback mal posicionado en `COMECyTUI.confirm` (se pasaba el título como 2º parámetro).
  - **Solución**: Corregido en 20 archivos para usar: `COMECyTUI.confirm(msg, callback, null, { titulo: "..." })`.

- **Error `Botón de Logout no funciona en Áreas` (2026-03-26)**:
  - **Causa**: El botón apuntaba a `public/logout.php`, archivo inexistente.
  - **Solución**: Se consolidó la lógica en `includes/sidebar_footer.php` para que todos los usuarios (Personal y Admin) usen el controlador unificado `admin/logout.php`.

- **Error `Chat se queda en "Cargando mensajes..."` (2026-03-26)**:
  - **Causa**: Error de sintaxis SQL en todos los archivos `api/chat.php`. Se intentaba usar la columna inexistente `id_personal` en lugar de `cve_personal` para las uniones con `cat_personal`.
  - **Solución**: Reemplazo global de `id_personal` por `cve_personal` en 21 archivos de la API.
  - **Aislamiento**: Se verificó que el filtrado por `cve_area` funciona correctamente una vez que la consulta es válida, garantizando que los mensajes solo sean visibles para miembros del mismo departamento.

- **🚨 Unificación de Áreas Difusión (v7.9, 2026-03-26)**:
    - **Problema**: El equipo de Difusión está repartido en IDs de área inconsistentes (`16` para personal, `6` para tareas heredadas). Esto causaba que el selector de personal apareciera vacío.
    - **Solución**: Se implementó una lógica de "Áreas Unificadas" ([6, 16, 18]) en todos los filtros de `calendario_editorial.php`. Se corrigió el `INSERT` de eventos editoriales para que asigne correctamente el `cve_area`.
- **🚨 Pulido Calendario Editorial (v7.8, 2026-03-26)**:
    - **Privacidad**: Eliminación del checkbox "Mostrar en calendario público" (HTML/JS) y forzado de `publico=FALSE` en el backend para procesos editoriales internos.
    - **Colores**: Se añadieron selectores de color en los modales de creación (Eventos y Kanban). Se corrigió la persistencia de color en los modales de edición (JS `abrirModal`).
    - **Cumpleaños**: Corrección en la construcción de la URL de avatar para que, si no hay foto, el modal de detalle muestre correctamente el icono de pastel (`fa-cake-candles`) en lugar de un enlace roto.
- **🚨 Sync UI Calendario Editorial (v7.7, 2026-03-26)**:
    - **Problema**: La lista de personal para asignar tareas estaba vacía porque se filtraba solo por "Administradores" de un área específica (ID 6), pero el equipo real está en `cat_personal` bajo IDs mixtos (6 y 18).
    - **Solución**: Se cambió la fuente de la lista de asignación a `cat_personal` filtrando por áreas 6 y 18 simultáneamente. Se implementó un `COALESCE` en el JOIN de tareas para mostrar nombres tanto de la tabla de administradores como de personal.
- **🚨 Sync UI Calendario Editorial (v7.6, 2026-03-26)**:
    - **Problema**: El botón "Editar" seguía sin funcionar debido a que el JavaScript buscaba los IDs `btnEliminarEvento` y `btnActualizarEvento`, los cuales faltaban en el HTML del modal.
    - **Solución**: Restauración de los IDs de los botones en el footer del modal editar. Se verificó la consistencia entre el DOM y las funciones JS.
- **Error `Lista de DMs vacía / Chat sin branding por área` (2026-03-26)**:
  - **Causa**: La consulta de miembros usaba la columna `habilitado` (inexistente) en lugar de `activo`. Además, el título "Equipo TI" estaba fijo en el HTML.
  - **Solución**: Corrección masiva de `habilitado` → `activo` en 21 archivos. Se implementó la variable `$chat_area_label` en `header_admin.php` para inyectar dinámicamente el nombre del área en el encabezado y subtextos del chat.

- **Error `Mensajes no se guardan en áreas departamentales` (IMPORTANTE) (2026-03-26)**:
  - **Causa 1 (Esquema)**: La tabla `sb_chat_mensajes` tenía un *Foreign Key* (`usuario_id`) que apuntaba a la columna inexistente `id_personal` en `cat_personal`, causando fallos silenciosos en el `INSERT` para usuarios no-administradores.
  - **Causa 2 (Ruteo - "Difusión Trap")**: La lógica de inicio de sesión no mapeaba correctamente los IDs de área a Slugs, forzando a todos los usuarios departamentales al dashboard de Difusión (área 6) mientras su chat seguía filtrado por su área real (ej: 16). Esto hacía que el chat pareciera vacío.
  - **Solución**: Se eliminaron las restricciones obsoletas y se recrearon apuntando a `cve_personal`. Se implementó un `slugMap` en `auth.php` para garantizar que cada usuario llegue a su dashboard correcto con el contexto de chat adecuado.

- **Distribución en Detalle (Grid Alignment)**: Los componentes de seguimiento (Chat, Notas) deben insertarse *dentro* del contenedor `.detail-layout` y utilizar `grid-column: 1 / -1` para asegurar que respeten los márgenes del sidebar y mantengan una visualización profesional de ancho completo.
- **Fix Técnico (Estructura)**: Se corrigió una corrupción de etiquetas HTML (`</form>`, `</div>` duplicados) y la pérdida del `endif` de la lógica de estatus que causaba errores de sintaxis al final del archivo.
