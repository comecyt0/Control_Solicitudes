# COMECyT — Manual Técnico y de Mantenimiento
**v3.0 — Marzo 2026**

---

## 1. Arquitectura del Sistema

Sistema construido en **PHP 8.1 + PostgreSQL 15** bajo el modelo SSR sin frameworks externos. Corre en **Docker** para portabilidad total.

- **Backend**: PHP puro con PDO (patrón singleton en `config/database.php`)
- **Frontend**: HTML5 + Vanilla CSS + JS minimal
- **Estilos**: Sistema de diseño con CSS Custom Properties en `assets/css/main.css`
- **Identidad**: Logotipo institucional `assets/MARCA.png`
- **Interactividad**: `assets/js/app.js` solo para UI (modales, alertas, uploads drag-drop)
- **Infraestructura**: Docker Compose (PHP+Apache app, PostgreSQL 15, pgAdmin)

---

## 2. Estructura de Directorios

```
COMECyT_Solicitudes/
├── admin/          → Panel admin (protegido) + admin/api/ (endpoints AJAX)
├── public/         → Portal público + uploads/
├── config/         → database.php (PDO), auth.php (sesiones/CSRF)
├── includes/       → Fragmentos HTML reutilizables + helpers.php
├── assets/         → css/, js/, imágenes
├── database/       → Scripts SQL (MySQL y PostgreSQL)
├── cron/           → Tareas programadas (recordatorios.php)
├── docs/           → Documentación técnica
├── docker/         → php.ini y apache.conf para Docker
├── backups/        → Backups de BD (excluidos de git)
├── Dockerfile      → Imagen PHP 8.1 + Apache
├── docker-compose.yml → Orquestación de servicios
├── Makefile        → Comandos rápidos
├── .env            → Variables de entorno (NO en git)
└── .env.example    → Plantilla para nuevas instalaciones
```

---

## 3. Configuración de Entorno

| Variable | Local | Docker | Descripción |
|----------|-------|--------|-------------|
| `DB_HOST` | `127.0.0.1` | `db` | Host de PostgreSQL |
| `DB_PORT` | `5432` | `5432` | Puerto de PostgreSQL |
| `DB_NAME` | `bd_sisibic` | `bd_sisibic` | Nombre de la BD |
| `GROQ_API_KEY` | tu clave | tu clave | API de IA |
| `FOLIO_PREFIX` | `CMCT` | `CMCT` | Prefijo de folios |
| `UPLOAD_DIR_SOLICITUDES` | `/var/www/html/public/uploads/solicitudes/` | `/var/www/html/public/uploads/solicitudes/` | Ruta de archivos |
| `APP_HTTPS` | `false` | `true` | Activa cookies seguras en producción |

### Manejo de Errores y Depuración
El sistema utiliza una función centralizada `mostrarError()` en `includes/helpers.php` para evitar fugas de datos técnicos y mantener una UX profesional.

---

## 4. Mantenimiento y Modificaciones Comunes

### 4.1 Agregar un Nuevo Tipo de Solicitud o Estatus

Toda la traducción de enums a elementos visuales está en **`includes/helpers.php`**:

1. Actualizar el `CHECK` en PostgreSQL en la tabla `solicitudes`
2. Agregar al diccionario `ETIQUETAS_ESTATUS` en `helpers.php`
3. Asignar clase CSS en `getBadgeClase()` en `helpers.php`
4. Asignar ícono FontAwesome en `getIconoEstatus()` en `helpers.php`
5. Definir transiciones válidas en `$transiciones` dentro de `admin/detalle.php`

### 4.2 Áreas de Adscripción en el Formulario de Registro

El formulario `public/registro.php` usa una **lista canónica fija** de 15 áreas institucionales (no consulta dinámica a BD):

- Dirección General, Subdirección de TI, Dpto. Soporte Técnico, Dpto. Redes, Dpto. Sistemas
- Recursos Humanos, Administración y Finanzas, Comunicación Social, Jurídico
- Vinculación y Difusión, Control Escolar, Planeación, Atención Ciudadana, Archivo General, Servicios Generales
- **Opción "Otro"**: muestra un campo de texto libre; el sistema busca la coincidencia en `cat_areas` (LOWER/TRIM) y usa `cve_area=1` como fallback.

### 4.2 Modificar Branding (Colores / Logotipo)

- **Logotipo**: Reemplazar `assets/MARCA.png` (mantener el nombre)
- **Colores**: Editar variables en `assets/css/main.css` → sección `:root`:
```css
:root {
    --color-primary: #662331;  /* Tinto institucional */
    --color-accent:  #B19A6D;  /* Dorado institucional */
}
```
El cambio aplica automáticamente al 100% del sistema.

### 4.3 Gestión de Administradores y Servicio Social

Desde `admin/administradores.php`:
- **Crear**: Nombre, email, contraseña inicial (bcrypt automático)
- **Roles disponibles**: `superadmin` | `admin` | `revisor`
- **Desactivar** (soft delete): Alterna `activo = 0` sin borrar historial

Desde `admin/servicio_social.php`:
- **Crear Prestador SS**: Nombre, Apellidos, Área, email, contraseña inicial (bcrypt)
- **Rol**: `servicio_social` (totalmente independiente).
- **Pruebas Locales**: Para el portal de Servicio Social, puedes probar con el usuario creado en base de datos: 
  - Correo: `juanitogamer@gmail.com`
  - Contraseña: `juan123`

### 4.4 Modificar Tiempo de Expiración de Sesión

```php
// config/auth.php
define('SESSION_TIMEOUT', 7200); // segundos (default: 2 horas)
```

### 4.5 Agregar un Nuevo Módulo al Sistema

1. Crear `admin/nuevo_modulo.php` con `require_once '../config/auth.php'; verificarSesionAdmin();`
2. Usar `require_once '../includes/header_admin.php'` al inicio y `footer.php` al final
3. Si requiere AJAX: crear `admin/api/nuevo_modulo.php` devolviendo JSON
4. Registrar enlace en el sidebar de `includes/header_admin.php`
5. Crear tabla con `InnoDB` + `FK` + índices en la BD
6. Incrementar versión en tags CSS/JS: `app.js?v=4.0`

---

## 5. Base de Datos

### 5.1 Tablas Principales

| Tabla | Descripción |
|-------|-------------|
| `solicitudes` | Tickets maestros |
| `historial_solicitudes` | Bitácora de cambios (solo INSERT) |
| `comentarios_solicitudes` | Notas internas privadas |
| `plantillas_respuesta` | Plantillas de respuesta admin |
| `log_notificaciones` | Log de emails enviados |
| `administradores` | Cuentas admin con roles |
| `cat_personal` | Catálogo de empleados institucional |
| `cat_areas` | Áreas institucionales |
| `usuarios` | Cuentas del portal público |
| `sb_bienes` | Inventario de equipos de cómputo |
| `sb_kanban_tareas` | Tablero kanban interno |
| `eventos` | Calendario institucional |
| `sb_chat_mensajes` | Chat entre administradores (grupal + DM) |

### 5.2 Generación de Folios

`generarFolio()` en `config/database.php` genera folios únicos en formato `CMCT-YYYY-NNNN`. Extrae matemáticamente el último folio del año en curso para evitar duplicados en concurrencia.

### 5.3 Archivos Adjuntos

- Se suben a `public/uploads/solicitudes/`
- El nombre original se reemplaza por un hash SHA-256 para seguridad
- Se almacenan como JSON en `solicitudes.archivos_adjuntos`
- La carpeta tiene `.htaccess` que desactiva ejecución PHP (protección RCE)

### 5.4 Script SQL Unificado
| Archivo | Propósito |
|---------|-----------|
| `database/init.sql` | Estructura y datos PostgreSQL unificados (Sistema + Servicio Social). |

### 5.5 Buenas Prácticas Obligatorias de BD

1. **PostgreSQL**: Siempre usar `TO_CHAR()` para formatear fechas — nunca `DATE_FORMAT()` (MySQL only)
2. **Booleanos**: Usar `TRUE`/`FALSE` en valores literales — PostgreSQL no hace cast desde `0`/`1`
3. **FK**: Declarar todos los `FOREIGN KEY` con `ON DELETE CASCADE` cuando aplique
4. **Contraseñas sensibles**: bcrypt en todo momento
5. **Tipos**: `VARCHAR(150)` para textos cortos, no `TEXT` genérico en campos indexados
6. **Sensibles**: Nunca concatenar variables en SQL; siempre `prepare()` / `execute()`

---

## 6. Cron Jobs

```bash
# Recordatorios automáticos de solicitudes pendientes
# Ejecutar cada hora (agregar a crontab):
0 * * * * php /var/www/html/cron/recordatorios.php >> /var/log/comecyt_cron.log 2>&1

# En Docker — agregar a Makefile:
make cron
```

---

## 7. Backups

### Exportar BD (con Docker activo)
```bash
make db-export
# Guarda en: backups/bd_sisibic_YYYYMMDD_HHMMSS.sql
```

### Exportar BD (con MAMP)
```bash
/Applications/MAMP/Library/bin/mysql80/bin/mysqldump \
  -u root -proot bd_sisibic > backups/bd_$(date +%Y%m%d).sql
```

### Importar BD (Docker)
```bash
make db-import FILE=backups/bd_sisibic_20260306.sql
```

---

## 8. Seguridad

| Mecanismo | Implementación |
|-----------|---------------|
| CSRF | `csrfField()` + `validarCsrfPost()` en todo POST |
| Rate Limiting | 5 intentos → bloqueo 5 min (`config/auth.php`) |
| SQL Injection | 100% PDO `prepare()` / `execute()` |
| XSS | `esc()` helper en toda salida HTML |
| RCE | `.htaccess` desactiva `mod_php` en `/uploads/` |
| Secrets | Variables exclusivamente en `.env` |
| Sesiones | Timeout configurable (`SESSION_TIMEOUT`) |
| Contraseñas | bcrypt (`password_hash()` + `password_verify()`) |

Ver [docs/auditoria_seguridad.md](auditoria_seguridad.md) para el reporte completo.

---

## 9. Docker (v3.0)

Para instrucciones de instalación, portabilidad entre computadoras y comandos disponibles, ver [README-DOCKER.md](../README-DOCKER.md).

**Comandos rápidos:**
```bash
make up        # Levantar todos los servicios
make down      # Detener servicios
make logs      # Ver logs en tiempo real
make db-export # Exportar backup de BD
make db-import # Importar backup de BD
make shell     # Abrir shell en el contenedor PHP
```

---

## 10. Módulo Servicio Social

El módulo Servicio Social agrega un rol y portal independiente para prestadores de servicio social.

### 10.1 Flujo de Login

`
admin/login.php POST
  ├─ iniciarSesion()          → admin/dashboard.php
  ├─ iniciarSesionUsuario()   → public/index.php
  └─ iniciarSesionSS()        → servicio_social/dashboard.php   ← NUEVO
`

### 10.2 Permisos del Portal SS

| Acción | SS puede |
|--------|----------|
| Ver tareas (propias y de todos) | ✅ |
| Mover tarea (pendiente → en_proceso → completada) | ✅ |
| Subir evidencias (foto/doc/nota, máx 10 MB) | ✅ |
| Crear / editar / eliminar tareas | ❌ |
| Registrar entrada / salida (timestamp servidor) | ✅ |
| Ver historial de asistencia propio | ✅ |
| Ver tarjetas de compañeros SS | ✅ |

### 10.3 Archivos del módulo

| Archivo | Descripción |
|---------|-------------|
| database/migracion_ss.sql | Migración idempotente (4 tablas) |
| config/auth.php → iniciarSesionSS() | Autenticación SS |
| config/auth.php → erificarSesionSS() | Guard de sesión SS |
| includes/header_ss.php | Layout/sidebar azul exclusivo del portal SS |
| servicio_social/dashboard.php | Portal completo: Kanban + Asistencia + Compañeros |
| servicio_social/api/accion.php | AJAX: mover tarea, subir evidencia, registrar asistencia |
| servicio_social/logout.php | Cierre de sesión SS |
| dmin/servicio_social.php | Admin: CRUD usuarios SS + Kanban SS + Reportes |
| dmin/api/servicio_social.php | AJAX admin: gestión usuarios y tareas SS |

### 10.4 Tablas de BD

| Tabla | Descripción |
|-------|-------------|
| ss_usuarios | Prestadores con email+bcrypt, periodo, institución |
| ss_kanban_tareas | Tablero Kanban SS independiente del admin |
| ss_evidencias | Fotos/documentos/notas adjuntos a tareas SS (ON DELETE CASCADE) |
| ss_asistencia | Registro de entrada/salida con timestamp exacto del servidor + IP |

### 10.5 Uploads SS

Los archivos de evidencia se guardan en public/uploads/ss/ con nombre hash (hex 16B).
MIME validado: imágenes (jpeg/png/gif/webp) y documentos (pdf/doc/docx/xls/xlsx). Máx 10 MB.

