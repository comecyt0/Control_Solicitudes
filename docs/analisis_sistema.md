# Análisis Técnico del Sistema COMECyT
**Control de Solicitudes Internas | v3.1 | Marzo 2026**

Este documento detalla el análisis técnico completo del sistema: infraestructura, componentes de software, módulos de negocio y flujos de datos.

---

## 1. Arquitectura de Infraestructura (Docker)

El sistema está completamente contenedorizado mediante **Docker Compose** con 3 servicios:

### Servicio: `comecyt_app` (PHP 8.1 + Apache)
- **Imagen base**: Dockerfile personalizado sobre `php:8.1-apache`
- **Puerto expuesto**: `8080 → 80`
- **Extensiones PHP compiladas**: `pdo_pgsql`, `pgsql`, `gd`, `zip`, `curl`, `mbstring`, `opcache`
- **OPcache activo**: Pre-compilación de bytecode PHP en RAM → carga casi instantánea
- **Volume de uploads**: `comecyt_uploads_data:/var/www/html/public/uploads`
- **Código fuente**: Copiado estáticamente durante el build (`COPY . /var/www/html`)
  - ⚠️ Sin bind-mount por rendimiento en WSL2 — **Actualizar Docker al hacer cambios**

### Servicio: `comecyt_db` (PostgreSQL 15)
- **Imagen**: `postgres:15-alpine`
- **Base de datos**: `bd_sisibic`
- **Usuario**: `comecyt_user` | **Contraseña**: en `.env`
- **Puerto**: `5433 → 5432` (puerto de host 5433 → interno 5432)
- **Persistencia**: Volumen `comecyt_postgres_data`
- **Init scripts**: `database/init.sql` al primer inicio

### Servicio: `comecyt_pgadmin` (pgAdmin)
- **Puerto expuesto**: `8081 → 80`
- **Credenciales**: Email y contraseña en `.env`
- **Acceso**: http://localhost:8081

### Red interna
Todos los servicios están en la red `comecyt_net` (bridge). El app se conecta a la BD usando hostname `db` (no localhost).

---

## 2. Stack Tecnológico Completo

| Capa | Tecnología | Versión | Notas |
|---|---|---|---|
| Backend | PHP | 8.1 | MVC manual, sin framework |
| Base de datos | PostgreSQL | 15 | pgsql, driver PDO |
| Servidor web | Apache | 2.4 | Mod rewrite + PHP-FPM |
| Frontend CSS | Vanilla CSS | — | CSS Custom Properties, 5 breakpoints |
| Frontend JS | Vanilla JavaScript | — | Sin jQuery, sin Node.js |
| Iconos | FontAwesome | 6.5.1 | CDN |
| Tipografía | Google Fonts (Inter) | — | CDN |
| IA API | Groq (LLaMA 3) | — | Requiere GROQ_API_KEY |
| Email | PHP mail / SMTP | — | Configurable en .env |
| Contenedores | Docker + Compose | 24+ | 3 servicios |
| Control de versiones | Git | — | branch: main |

---

## 3. Arquitectura PHP (Sin Framework)

El sistema usa **SSR nativo PHP** con patrón **PRG (Post-Redirect-Get)**:

```
Browser → GET página → HTML renderizado por PHP
Browser → POST form → PHP valida → INSERT BD → header redirect → GET
Browser → fetch() → admin/api/*.php → JSON response
```

### Archivos de configuración base

| Archivo | Responsabilidad |
|---|---|
| `config/database.php` | Conexión PDO singleton, `generarFolio()`, constantes de BD |
| `config/auth.php` | Sesiones, CSRF tokens, rate limiting, verificación de roles |
| `includes/helpers.php` | `esc()`, `badgeEstatus()`, `csrfField()`, constantes de tipos y estados |

### Seguridad implementada
- **CSRF**: Tokens en todos los POST, validados por `validarCsrfPost()` en `auth.php`
- **Rate limiting**: 5 intentos → bloqueo 5 minutos por IP
- **PDO**: Todas las queries con `prepare()` y `execute()` — sin SQL injection posible
- **XSS**: Función `esc()` en TODA salida HTML sin excepción
- **RCE**: `.htaccess` en `/uploads/` bloquea ejecución PHP de archivos subidos
- **Secrets**: Variables en `.env`, no en código fuente (validado en `.gitignore`)

---

## 4. Módulos del Sistema

### Módulo 1: Tickets de Soporte (Core)
**Archivos principales**: `public/index.php`, `admin/solicitudes.php`, `admin/detalle.php`

Gestión completa del ciclo de vida de las solicitudes internas:
- Alta por parte del empleado (público o autenticado)
- Asignación y cambio de estatus por administradores
- Bitácora inmutable de cambios (`historial_solicitudes`)
- Comentarios privados internos
- Notificación email automática al solicitante
- Exportación individual en PDF y masiva en CSV

**Tipos de solicitud**:
- `sistemas` — Problemas con sistemas internos COMECyT
- `soporte` — Soporte técnico hardware/software
- `atencion` — Atención ciudadana y correos
- `administracion` — Gestión administrativa

**Ciclo de estatus**:
```
pendiente → en_proceso → completada
           ↓
        cancelada
```

**Generación de folios**: `CMCT-{AÑO}-{NNNN}` (secuencial por año, función `generarFolio()`)

---

### Módulo 2: ERP de Personal
**Archivo principal**: `admin/personal.php`

Catálogo maestro de empleados de COMECyT:
- Tabla `cat_personal`: RFC, CURP, nombre, apellidos, extensión, email, área, categoría
- Portal de registro de usuarios vinculado al catálogo
- Solicitudes de actualización de datos (`solicitudes_actualizacion_personal`)
- Filtros por área, estatus y búsqueda global

---

### Módulo 3: Inventario de Hardware
**Archivo principal**: `admin/equipos.php`

Control de activos tecnológicos:
- `sb_bienes`: Computadoras, laptops, monitores
- `sb_bienes_altum`: Equipos con número Altum (GEM)
- `sb_bienes_impresoras`: Impresoras y multifuncionales
- `sb_bienes_red`: Switches, APs, firewalls, cableado
- `sb_bienes_baja`: Histórico de equipos dados de baja
- Asignación de equipos a empleados y visualización por usuario

---

### Módulo 4: Calendario y Kanban
**Archivo principal**: `admin/calendario.php`

- Calendario de eventos institucionales con vista mensual
- Tablero Kanban de tareas internas del equipo TI
- Creación de tareas desde el chat del equipo
- Eventos y tareas accesibles desde el header_admin (botones rápidos)

---

### Módulo 5: Colaboración Interna
**Integrado en**: `includes/header_admin.php` + `admin/api/chat.php`

- **Chat en tiempo real** entre administradores (polling cada 7s)
- Canal general del equipo TI
- Mensajes directos (DM) entre administradores
- Creación de tareas Kanban y eventos desde el chat
- **Asistente IA** integrado: Groq + LLaMA 3 con contexto del sistema
- Búsqueda global: tickets, personal, equipos desde el topbar

---

### Módulo 6: Correos Institucionales
**Archivo principal**: `admin/correos.php`

Gestión del catálogo de correos oficiales por área:
- Alta, modificación y baja de cuentas
- Visualización por área organizacional

---

### Módulo 7: Reportes y Estadísticas
**Archivo principal**: `admin/reportes.php`

- KPIs en el dashboard: total, activas, completadas, urgentes
- Gráficas CSS de distribución por tipo y área
- Exportación CSV con filtros: tipo, estatus, rango de fechas

---

### Módulo 8: Servicio Social
**Archivos**: `admin/servicio_social.php`, `servicio_social/dashboard.php`, `admin/api/servicio_social.php`

Portal independiente para gestión de becarios de servicio social:
- Registro de alumnos (`ss_alumnos`)
- Control de asistencias diarias (`ss_asistencia`)
- Tablero Kanban para tareas asignadas (`ss_kanban_tareas`)
- Subida de evidencias (`ss_evidencias`)
- Dashboard propio para el becario

---

## 5. Capa de Presentación (Frontend)

### CSS (`assets/css/main.css`)
- **Sistema de diseño** con CSS Custom Properties (variables CSS)
- **Paleta institucional**: Guinda `#662331` + Dorado `#B19A6D` (identidad COMECyT / GEM 2023-2029)
- **Dark Mode**: Clase `.dark-mode` en `<body>`, persistida en BD por administrador
- **Responsive completo**: 5 breakpoints CSS:
  - ≥ 1400px: Pantallas grandes
  - 1024–1399px: Laptops
  - 768–1023px: Tablets (sidebar colapsable)
  - 480–767px: Móviles
  - ≤ 360px: Móviles muy pequeños

### JavaScript (`assets/js/app.js`)
Minimal, solo lógica de UI:
- Sidebar móvil (toggle + overlay + Escape key)
- Apertura/cierre de modales
- Sistema de toasts (notificaciones efímeras)
- Auto-hide de alertas
- Confirmaciones de acción globales

### Iconos: FontAwesome 6.5.1 (CDN CloudFlare)
### Tipografía: Inter (Google Fonts, CDN)

---

## 6. Base de Datos — Diagrama Lógico

```
solicitudes
├── id (PK, serial)
├── folio (unique, CMCT-YYYY-NNNN)
├── tipo (enum: sistemas|soporte|atencion|administracion)
├── solicitante (text)
├── email_solicitante
├── area (text)
├── prioridad (enum: baja|media|alta|urgente)
├── descripcion (text)
├── estatus (enum: pendiente|en_proceso|completada|cancelada)
├── resuelto_por (text, nullable)
├── archivos (JSON array)
├── equipo_id (FK → sb_bienes, nullable)
├── fecha_creacion (timestamp)
└── fecha_actualizacion (timestamp)
    ↓ 1:N
historial_solicitudes (bitácora inmutable)
    ↓ 1:N
comentarios_solicitudes (notas privadas)

cat_personal ←→ usuarios
cat_personal → cat_areas
sb_bienes → solicitudes (equipo_id)
```

---

## 7. Configuración del Entorno (.env)

Variables requeridas en `.env` para producción:

```env
# Base de datos (PostgreSQL)
DB_HOST=db              # 'db' en Docker, '127.0.0.1' sin Docker
DB_PORT=5432
DB_NAME=bd_sisibic
DB_USER=comecyt_user
DB_PASS=<contraseña>

# Sistema
FOLIO_PREFIX=CMCT
BASE_URL=http://TU_IP:8080/
APP_HTTPS=false         # true en producción con SSL

# IA
GROQ_API_KEY=<clave>    # Groq API para asistente IA

# Email
MAIL_ENABLED=true
MAIL_HOST=smtp.comecyt.gob.mx
MAIL_USER=<usuario>
MAIL_PASS=<contraseña>
MAIL_FROM=noreply@comecyt.gob.mx

# pgAdmin
PGADMIN_EMAIL=admin@comecyt.local
PGADMIN_PASS=<contraseña>
```

---

## 8. Datos Históricos Migrados

En Marzo 2026 se realizó la migración de **85 registros históricos** de solicitudes previas al sistema actual.

- **Script**: `database/migration_history.sql`
- **Folios generados**: `CMCT-2025-0001` a `CMCT-2026-0084`
- **Responsables mapeados**: VICTOR, RICARDO, ABRIL, FERNANDO, EDUARDO
- **Nuevos administradores creados**: VICTOR (ID 7), RICARDO (ID 8)
- **Estatus asignado**: `completada` para los registros "Atendido", `en_proceso` para los pendientes
- **Campo área**: Texto libre mapeado al Sistema de origen (DUAL, ESYCA, Licenciatura, etc.)

---

## 9. Acceso en Red Local

Para compartir el sistema en la red interna:

1. Asegurarse de que Docker está activo
2. Obtener la IP del servidor: `ipconfig` (Windows) / `ip addr` (Linux)
3. Abrir puertos `8080` y `8081` en el firewall si es necesario
4. Los usuarios acceden por: `http://IP_SERVIDOR:8080/public/`

> La IP del servidor en la red COMECyT es típicamente `172.30.0.44` o `172.30.0.25`.

---

## 10. Mantenimiento y Actualización del Docker

Cada vez que se modifique código fuente, es **necesario reconstruir la imagen Docker**:

```bash
# Reconstruir y reiniciar con los cambios
docker compose down
docker compose up --build -d

# O solo el contenedor de la app
docker compose up --build -d comecyt_app

# Ver si los cambios cargaron bien
docker compose logs -f comecyt_app
```

> ⚠️ **IMPORTANTE**: Debido a que el código se copia en el build (no hay bind mount), cualquier modificación de PHP, CSS o JS requiere ejecutar `--build` para que los cambios sean visibles.
