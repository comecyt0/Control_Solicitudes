# Proyecto: COMECyT — Control de Solicitudes

## Visión General
Sistema de gestión de solicitudes y agenda institucional para COMECyT. Permite a usuarios externos solicitar espacios de calendario y a administradores gestionar dichas solicitudes.

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

## Troubleshooting y Quirks del Entorno
- **Botones Invisibles pero Cliqueables (`.reveal-up`)**: Si un elemento animado está situado el fondo del viewport y la propiedad `rootMargin` en Javascript es `-50px`, este jamás cruzará el umbral, por lo tanto retiene `opacity: 0` pero el cursor puede interactuar con él (falso invisible). La solución es establecer un `rootMargin: 0px` para elementos base.
- **Grids Hardcodeados en Formularios**: Formularios con `display: grid; grid-template-columns: 1fr 1fr;` en línea aplastarán la maqueta en pantallas móviles. Usar clases responsivas obligatorias (`.responsive-grid-2`) con breakpoints pre-probados en `<style>` (`max-width: 768px`).
- **Banners Uploads (`api/anuncios.php`)**: Mover archivos temporalmente en Windows PHP subyacente con Docker falla si se usan rutas relativas con `ROOT`. La directiva `@move_uploaded_file` requiere rutas estrictas con `dirname(__DIR__, 2) . '/public/uploads/anuncios/'`.
- **🚨 Alerta de Sincronización de Contenedores Docker (No Bind-Mount) 🚨**: En Windows, la configuración de `docker-compose.yml` para el servicio `app` no realiza un *Bind Mount* directo y dinámico del código en `c:\Intranet`. Por tanto, cualquier archivo programático modificado en el anfitrión requiere que la imagen sea recompilada desde cero. 
  - ❌ **Comportamiento Erróneo:** Ejecutar `docker compose up -d` tras modificar archivos encenderá el contenedor viejo (desde cache).
  - ✅ **La Solución Estricta:** Para aplicar cambios a los scripts PHP/HTML/CSS en esta arquitectura es obligatorio usar explícitamente **`docker compose up -d --build app`**. Considera implementar Hot-Reload uniendo `volumes: - .:/var/www/html` en el entorno futuro.

- **🚨 Loop de Redirección Infinito (ERR_TOO_MANY_REDIRECTS) en Login local/Docker 🚨**:
  - **Problema:** Al iniciar sesión (o al ser Servicios Sociales), el usuario puede quedar atrapado en un ciclo infinito entre `admin/login.php` e `index.php`. 
  - **Causa 1 (Headers):** Si el servidor de PHP emite rápidamente un *Location 302* dentro del puerto mapeado (localhost o red local) cuando una sesión ya está activa, el proxy y la caché de Chrome pueden interpretar que la respuesta de Auth fue rebotada, atascando la petición.
  - **La Solución (Fallback HTML):** En lugar de depender de `header('Location: ...')` post-autenticación en `admin/login.php`, se escupe al cliente `<meta http-equiv="refresh" content="0;url=...">`. Esto "rompe" la conexión estricta de headers HTTP y delega la redirección al motor de renderizado HTML del navegador, forzando la evaluación de las cookies desde cero y logrando un login impecable.
  - **Causa 2 (Reglas Auth Contradictorias):** En `verificarSesionUsuario()` (archivo `auth.php`), si no se permitía el retorno anticipado `return;` a los `ss_id` (Servicio Social), el archivo `index.php` los pateaba de regreso al `login.php`. Pero el `login.php` sabía que ellos SÍ tenían sesión, y los devolvía al `index.php`. Este error lógico de ping-pong también fue parcheado para que ambos compartan el mismo conjunto de validaciones de exclusión.

- **🚫 Aislamiento Estricto de Servicio Social (`ss_id`)**:
  - **Comportamiento:** Los usuarios con el rol Servicio Social (becarios) NO deben acceder al Hub Global Público (`public/index.php`), sino exclusivamente a `servicio_social/dashboard.php`.
  - **Doble barrera:** Se actualizó `admin/login.php` para canalizarlos a su panel al loguearse. Adicionalmente, el `public/index.php` tiene una barrera que expulsa a cualquiera que tenga la sesión `ss_id`, llevándolos a la fuerza a su panel. Esto previene que alteren URLs a mano y vulneren su aislamiento.
