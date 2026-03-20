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
- `/admin`: Panel de administración.
- `/public`: Vistas públicas y API de usuario.
- `/config`: Configuración de base de datos y autenticación.
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
- **Diseño Optimizado**: Reducción de espacios en blanco y corrección de layout flex en el calendario público.
- **Visibilidad Pública Automática**: Al aceptar una solicitud, se crea el evento con `publico = TRUE` por defecto.
- **Kanban Drag-and-Drop Fix**: Se implementaron las funciones HTML5 de corrección de drag-and-drop en `admin/calendario.php` (allowDrop, drag, drop).
- **Intranet Dashboard Público**: Se integró un panel central en `public/index.php` con grid de accesos rápidos animados, un panel informativo del Equipo de Sistemas y un módulo administrable de Anuncios.
- **Gestor de Anuncios**: CRUD disponible en `admin/anuncios.php` para publicar comunicados internos en la Intranet con la tabla `anuncios`.

## Contexto Importante
- **BASE_URL**: Crucial para la conectividad entre módulos. Se detecta dinámicamente en `helpers.php`.
- **Sesiones**: Los administradores se validan mediante `verificarSesionAdmin()`.
- **Estatus de Solicitudes**: `pendiente`, `aceptado`, `rechazado`.
- **Skill de Sincronización**: `/system_sync` (en `.agents/workflows/system_sync.md`) facilita la actualización del entorno Docker y Git.
