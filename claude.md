# Proyecto: COMECyT — Control de Solicitudes

## Visión General
Sistema de gestión de solicitudes y agenda institucional para COMECyT. Permite a usuarios externos solicitar espacios de calendario y a administradores gestionar dichas solicitudes.

## Credenciales de Acceso Actuales
- **Administrador principal**: `desarrollo.comecyt@edomex.gob.mx` / `F3rn4nd0`
- **Las credenciales de demo documentadas en README.md ya no son válidas en producción.**


## Arquitectura
- **Backend**: PHP 8.1 (Vanilla)
- **Base de Datos**: PostgreSQL 15
- **Servidor**: Apache (Dockerizado)
- **Frontend**: HTML5, Vanilla CSS, JS (ES6+)
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
