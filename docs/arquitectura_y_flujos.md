# Arquitectura y Flujos del Sistema COMECyT
**v3.1 — Marzo 2026 | Guía para Desarrolladores**

---

## 1. Patrón Arquitectónico

El sistema usa **Server-Side Rendering (SSR) nativo PHP** sin frameworks pesados (no Laravel, no Symfony), garantizando máximo rendimiento y despliegue sencillo en servidores institucionales.

### Flujo principal: PRG (Post-Redirect-Get)
```
Navegador → POST form → PHP valida (CSRF + PDO) → INSERT BD → Flash msg → Location redirect → GET página
```
- **CSRF**: Todo POST valida `validarCsrfPost()` en `config/auth.php`
- **PDO**: Todas las queries usan `$pdo->prepare()` / `execute()`, sin SQL
- **Notificaciones y Chat**:
    - Polling bidireccional: `assets/js/notificaciones.js` (global, cada 8s) y `header_admin.php` (local, cada 6s).
    - El backend (`admin/api/chat.php`) gestiona la lógica de mensajes, reacciones y persistencia de lectura.
    - Sincronización de lectura: Al abrir el chat, el cliente notifica al servidor (`marcar_leido`) para limpiar estados de alerta en toda la sesión del admin.
A diferencia de los usuarios regulares, los administradores cuentan con **Sesiones Permanentes**.
- La sesión no expira por el simple paso del tiempo durante la jornada laboral.
- **Excepción (Regla de Medianoche)**: El sistema realiza un cierre de seguridad a las 12:00 AM. Si el servidor detecta que la hora actual es >= 12:00 AM y el último clic del usuario fue el día anterior (y hace más de 30 minutos), la sesión se invalida. Esto garantiza que el panel no quede abierto indefinidamente en equipos desatendidos durante días.

### Flujo secundario: AJAX / fetch()
Los módulos interactivos (chat, comentarios, búsqueda global, IA, dark mode) usan `fetch()` hacia `admin/api/*.php`. Cada endpoint PHP devuelve JSON y verifica sesión + CSRF.

### JavaScript: Minimal (Vanilla)
`assets/js/app.js` se usa **exclusivamente para UI**:
- Apertura/cierre del sidebar en móvil (toggle + overlay)
- Apertura/cierre de modales
- Sistema de toasts (notificaciones)
- Confirmaciones globales de acción (DELETE, cambios de estatus)

---

## 2. Base de Datos (PostgreSQL 15)

La BD `bd_sisibic` está dividida en **5 módulos lógicos**:

### A. Módulo Core — Tickets de Soporte
| Tabla | Rol |
|-------|-----|
| `solicitudes` | Tabla maestra. Folio, tipo, prioridad, solicitante, área, descripción, estatus, archivos (JSON) |
| `historial_solicitudes` | Bitácora **inmutable** de cambios de estatus (solo INSERT, nunca UPDATE) |
| `comentarios_solicitudes` | Notas internas privadas por ticket |
| `plantillas_respuesta` | Textos de respuesta reutilizables para administradores |
| `log_notificaciones` | Registro de emails enviados a solicitantes |

### B. Módulo ERP — Personal e Identidad
| Tabla | Rol |
|-------|-----|
| `cat_personal` | Catálogo maestro de empleados (fuente de verdad) |
| `usuarios` | Cuentas del portal público mapeadas a `cat_personal` |
| `solicitudes_actualizacion_personal` | Correcciones de datos de empleados aprobadas por admin |
| `cat_areas` | Catálogo de áreas organizacionales (ID → nombre) |
| `administradores` | Cuentas de administradores del sistema (roles, dark_mode) |

### C. Módulo Inventario Hardware
| Tabla | Rol |
|-------|-----|
| `sb_bienes` | Computadoras, laptops, monitores corporativos |
| `sb_bienes_altum` | Equipos con número ALTUM (inventario GEM) |
| `sb_bienes_impresoras` | Impresoras y multifuncionales |
| `sb_bienes_red` | Switches, APs, firewalls, cableado |
| `sb_bienes_baja` | Histórico de equipos dados de baja |
| `cat_tipobien` | Tipos de bien (laptop, desktop, monitor...) |

### D. Módulo Sistemas Internos
| Tabla | Rol |
|-------|-----|
| `det_sistemainterno` | Inventario de sistemas con datos de servidor y BD |
| `det_sistemasgem` | Sistemas externos del GEM con credenciales |
| `det_doc_sistemas` | Documentación técnica por fases |
| `cat_fases_sistemas` | Catálogo de fases (análisis, diseño, desarrollo...) |

### E. Módulo Colaboración
| Tabla | Rol |
|-------|-----|
| `sb_chat_mensajes` | Chat interno + DMs entre administradores |
| `sb_kanban_tareas` | Tablero Kanban del equipo TI |
| `eventos` | Calendario de eventos institucionales |
| `sb_correos_oficial` | Correos oficiales por área |
| `sb_audit_session` | Bitácora de sesiones de administrador |

### F. Módulo Servicio Social
| Tabla | Rol |
|-------|-----|
| `ss_alumnos` | Registro de becarios de servicio social |
| `ss_asistencia` | Control de asistencias diarias |
| `ss_kanban_tareas` | Tareas asignadas al becario |
| `ss_evidencias` | Subida de evidencias de actividades |

---

## 3. Flujo Completo de una Solicitud

```
1. AUTENTICACIÓN (opcional para portal público)
   public/index.php → valida email contra cat_personal
   → Precarga nombre, área, extensión en el formulario

2. LLENADO DEL FORMULARIO
   - Selección de tipo: sistemas | soporte | atencion | administracion
   - Adjuntar archivos: drag-and-drop (DataTransfer API)
   - Selección de equipo (si aplica)

3. ENVÍO (POST a public/index.php)
   → validarCsrfPost()
   → Sanitizar inputs
   → Subir archivos a /public/uploads/solicitudes/{hash}.ext
   → json_encode($archivos)
   → generarFolio() → CMCT-2026-XXXX
   → INSERT solicitudes
   → INSERT historial_solicitudes (estatus: 'pendiente')
   → notificacion_email.php (si MAIL_ENABLED)
   → redirect con flash "Tu folio es CMCT-2026-XXXX"

4. ADMINISTRADOR VE EL TICKET
   admin/solicitudes.php → filtros por tipo, estatus, fecha
   → badgeEstatus() colorea el estatus visualmente

5. GESTIÓN DEL TICKET
   admin/detalle.php
   → Cambiar estatus via cambiar_estatus.php (AJAX)
     → INSERT historial_solicitudes (nueva entrada)
     → UPDATE solicitudes SET estatus, resuelto_por, fecha_actualizacion
   → Agregar comentario via comentarios.php (AJAX)
   → Usar plantilla de respuesta
   → Exportar PDF vía exportar_pdf.php

6. NOTIFICACIÓN
   notificacion_email.php
   → Envía email al solicitante con el nuevo estatus
   → INSERT log_notificaciones

7. CIERRE
   Estatus → 'completada' | 'cancelada'
   resuelto_por = nombre del admin que cerró
   → Solicitante consulta estado en public/consulta.php via folio
```

---

## 4. Flujo de Autenticación Admin

```
1. login.php → POST {email, password}
   → Buscar en administradores WHERE email = ?
   → password_verify($input, $hash_bd)
   → Si fallo: rate_limit++, si ≥ 5 → bloqueo 5 min
   → Si ok: SESSION {admin_id, admin_nombre, admin_rol, csrf_token}
   → Redirect → dashboard.php

2. Cada página admin:
   → require 'config/auth.php'
   → verificarSesionAdmin()
   → Si no hay sesión → redirect login.php

3. Cada POST admin:
   → validarCsrfPost()  // verifica csrf_token en POST == SESSION
   → Si csrf inválido → HTTP 403
```

---

## 5. Estructura CSS — Sistema de Diseño

`assets/css/main.css` define el sistema de diseño completo con **CSS Custom Properties**:

```css
:root {
  /* Paleta institucional COMECyT / GEM 2023-2029 */
  --color-primary:     #662331;    /* Guinda tinto    */
  --color-accent:      #B19A6D;    /* Dorado          */
  --color-primary-dark:#4d1a25;
  --color-primary-light:#8b2f42;

  /* Layout */
  --sidebar-width:     260px;
  --topbar-height:     60px;

  /* Dark mode sobreescribe estas variables con clase .dark-mode */
}
```

### Breakpoints Responsive
| Breakpoint | Target |
|---|---|
| ≥ 1400px | Pantallas grandes — máximo espacio |
| 1024–1399px | Laptops — sidebar 220px |
| ≤ 1100px | Stats grid a 2 columnas |
| ≤ 768px | Tablet — sidebar colapsable, hamburguesa visible |
| ≤ 480px | Móvil — 1 columna, modales bottom sheet |
| ≤ 360px | Móvil mínimo — tipo grid 1 columna |

### Dark Mode
- Activado/desactivado por administrador vía `admin/api/toggle_darkmode.php`
- Persiste en columna `dark_mode` de la tabla `administradores`
- Se carga al inicio desde BD y se aplica como clase `.dark-mode` en `<body>`

---

## 6. Gestión de Archivos Adjuntos

```
public/index.php → $_FILES → validar extensión y tamaño
→ generar hash único del nombre
→ move_uploaded_file() → /public/uploads/solicitudes/{hash}.{ext}
→ json_encode([{nombre, url, tamanio}]) → solicitudes.archivos (JSON)
```

**Seguridad en uploads**:
- `/public/uploads/.htaccess`: `php_flag engine off` — PHP no ejecuta en esta carpeta
- Validación de extensiones: solo PDF, DOC, DOCX, JPG, PNG, XLSX

---

## 7. Integración IA (Groq API)

`admin/api/agente_ia.php`:
1. Recibe `pregunta` + `solicitud_id`
2. Carga contexto de la solicitud desde BD
3. Construye prompt con instrucciones institucionales + contexto
4. Llama a `https://api.groq.com/openai/v1/chat/completions`
5. Modelo: `llama3-8b-8192`
6. Devuelve respuesta al cliente en JSON

Requiere `GROQ_API_KEY` válido en `.env`.

---

## 8. Agregar un Nuevo Módulo

Pipeline estándar para nuevas funcionalidades:

```
1. BD: CREATE TABLE nueva_tabla (+ FK + índices en init.sql)
2. Backend: admin/nueva_tabla.php
   → require ROOT . '/config/auth.php';
   → verificarSesionAdmin();
   → require ROOT . '/includes/header_admin.php';
   → [lógica PHP]
   → require ROOT . '/includes/footer.php';
3. API AJAX (si necesita): admin/api/nueva_tabla.php
   → verificarSesionAdmin()
   → Si POST: validarCsrfPost()
   → header('Content-Type: application/json')
   → echo json_encode(['ok' => true, ...])
4. Menú: añadir <a class="nav-link"> en includes/header_admin.php sidebar
5. helpers.php: agregar badges/iconos si aplica
6. CSS: agregar en assets/css/main.css si necesita estilos propios
7. Rebuild Docker: docker compose up --build -d comecyt_app
8. Commit + Push: git add . && git commit -m "feat: ..."
```

---

## 9. Configuración Docker Completa

```yaml
# docker-compose.yml (resumen)
services:
  comecyt_app:   # PHP 8.1 + Apache
    build: .
    ports: "8080:80"
    volumes: comecyt_uploads_data:/var/www/html/public/uploads
    depends_on: db

  comecyt_db:    # PostgreSQL 15
    image: postgres:15-alpine
    ports: "5433:5432"
    volumes: comecyt_postgres_data:/var/lib/postgresql/data
    environment: POSTGRES_DB, POSTGRES_USER, POSTGRES_PASSWORD

  comecyt_pgadmin:
    image: dpage/pgadmin4
    ports: "8081:80"
```

**Actualizar Docker tras cambios de código**:
```bash
docker compose down
docker compose up --build -d
docker compose logs -f comecyt_app
```
