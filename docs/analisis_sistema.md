# Análisis del Sistema COMECyT (Intranet)

Este documento detalla el análisis del sistema web basado en el código fuente, la configuración para compartirlo localmente y la arquitectura de la infraestructura en Docker.

## 1. Arquitectura del Sistema

El sistema es una aplicación web empaquetada a través de Docker Compose e incluye tres componentes (servicios) principales:

- **App (PHP 8.1 + Apache)**:
  Contenedor principal donde se ejecuta la lógica del sistema. Se expone en el puerto `8080`.
  Está preconfigurado con las extensiones necesarias (PDO PostgreSQL, cURL, GD, etc.) y la carpeta pública del sistema mapeada dinámicamente. 
  *Novedad:* Incluye ahora el **Módulo de Servicio Social** con su propia gestión Kanban, vista, y sistema de asistencias en paralelo a la lógica de administradores.

- **DB (PostgreSQL 15)**:
  Servidor de base de datos contenedorizado. Se encuentra configurado con persistencia de datos (volumen `comecyt_postgres_data`) y se han inicializado las tablas pertinentes en la base `bd_sisibic` con el usuario `comecyt_user` y la nueva contraseña establecida `123456789`. Se expone localmente en el puerto `5433` mediante todas las interfaces (`0.0.0.0`), permitiendo el acceso si hiciera falta conectarse desde un cliente externo.
  *Novedad:* Incluye esquemas independientes para `ss_usuarios`, `ss_kanban_tareas`, `ss_evidencias` y `ss_asistencia`.

- **pgAdmin**:
  Interfaz gráfica para la administración de la base de datos a través del navegador. Se expone en el puerto `8081`.

## 2. Configuración y Credenciales

Se actualizaron las credenciales con base en tu solicitud para mantener uniformidad en el despliegue local:
- **Base de Datos**: `bd_sisibic`
- **Usuario BD**: `comecyt_user`
- **Contraseña BD**: `123456789`

El archivo de entorno `.env` ahora inyecta automáticamente esta contraseña a la capa de PHP, y el archivo `docker-compose.yml` instruye a PostgreSQL para que asigne esta misma contraseña.

*(Importante: Para que el cambio de contraseña tome efecto de cero, se limpió la persistencia vieja para forzar el script de inicialización de la contraseña del contendor).*

## 3. Acceso y Uso Compartido en Red Local

La configuración de puertos en Docker se actualizó para enlazar explícitamente sobre `0.0.0.0` (todas las interfaces de red) para permitir conexiones de otros dispositivos en tu red LAN.

- **Tu Dirección IP Local Escaneada**: `172.30.0.44`

Para que tus compañeros de oficina puedan usar el sistema de forma local, pídeles que abran su navegador y escriban las siguientes direcciones según la herramienta que necesiten:

- **Para entrar al Sistema Web (App)**: `http://172.30.0.44:8080`
- **Para entrar al Administrador de Base de Datos (pgAdmin)**: `http://172.30.0.44:8081`

### Acceso a pgAdmin (Credenciales por defecto)
- **Usuario:** `admin@comecyt.local`
- **Contraseña:** `Admin2026!`

Una vez en pgAdmin, deberán registrar el servidor colocando `db` como hostname o `172.30.0.44`, el puerto `5432` de comunicación directa interna, usaurio `comecyt_user` y password `123456789`.

## 4. Archivos de Control e Inicialización

- **docker-compose.yml**:
  Contiene las directivas principales para levantar los 3 contenedores, definie redes locales (`comecyt_net`) y configura scripts de inicialización (`01_schema.sql` y `02_migracion.sql`) en `/docker-entrypoint-initdb.d/`.

- **.env**:
  Controla los secrets y configuración del entorno de PHP, credenciales de Base de datos, API KEYS (`GROQ_API_KEY`) y configuración de correo/cron.

## 5. Requisitos de Ejecución y Mantenimiento

- **Persistencia**: Los archivos subidos se guardarán en el volumen `comecyt_uploads_data`, y los datos de Postgres en `comecyt_postgres_data`. Si se destruyen los volúmenes, la base se reseteará a cero.
- **Detener el sistema**: `docker-compose down`
- **Mirar el estado**: `docker-compose ps`
- **Ver logs**: `docker-compose logs -f`

## 6. Optimizaciones de Rendimiento (Producción)

Dado que Docker en Windows (WSL2) sufre de alta latencia al compartir el sistema de archivos del proyecto con el contenedor Linux, el sistema fue optimizado para redes de alta demanda.

- **Zend OPcache**: Se compiló y activó OPcache en PHP 8.1. Esto permite que todo el código fuente del sistema se pre-compile en Bytecode y se almacene en la Memoria RAM. Gracias a esto, la aplicación carga de forma casi instantánea para los usuarios de la red local sin tener que leer el disco duro de tu computadora en cada clic. Adicionalmente, se configuró `opcache.validate_timestamps=0` para que el servidor nunca pierda tiempo comprobando si los archivos cambiaron, mejorando aún más el throughput.
- **Desvinculación de Sistema de Archivos**: Se eliminó el `bind mount` de desarrollo (`.:/var/www/html`) en Windows. Ahora el contenedor ejecuta sus procesos copiados nativamente en Linux, evadiendo la inmensa latencia de red 9P que sufre Docker Desktop al cruzar sistemas operativos.
- **Realpath Cache**: Se aumentó el tamaño de caché de rutas (`realpath_cache_size=4096K`) para minimizar aún más las llamadas al sistema operativo host.
