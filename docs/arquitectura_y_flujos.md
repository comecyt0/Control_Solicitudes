# Arquitectura y Flujos del Sistema COMECyT
**v3.0 — Marzo 2026 | Guía para Desarrolladores**

---

## 1. Patrón Arquitectónico (PRG + API AJAX Híbrido)

El sistema usa **Server-Side Rendering (SSR)** nativo PHP sin frameworks pesados, garantizando máximo rendimiento y despliegue sencillo en cualquier servidor institucional.

### Flujo principal: PRG (Post-Redirect-Get)
```
Navegador → POST form → PHP valida (CSRF + PDO) → INSERT BD → Flash msg → Location redirect → GET página
```
- **CSRF**: Todo POST valida `validarCsrfPost()` (`config/auth.php`)
- **PDO**: Todas las queries usan `$pdo->prepare()` / `execute()`, sin concatenación directa
- **Flash**: Mensajes de éxito/error en `$_SESSION` para mostrar tras el redirect

### Flujo secundario: AJAX / fetch()
Los módulos interactivos (chat, comentarios, búsqueda global, IA, dark mode) usan `fetch()` hacia `admin/api/*.php`. Cada endpoint PHP devuelve JSON y verifica sesión + CSRF.

### JavaScript: Minimal (Vanilla)
`assets/js/app.js` se usa **exclusivamente para UI**:
- Apertura/cierre de modales (`backdrop-filter: blur`)
- Drag-and-drop de archivos con `DataTransfer API`
- Alertas auto-cerrables
- Inicialización de kalendar, kanban

---

## 2. Mapa Relacional de la Base de Datos

La BD `bd_sisibic` está dividida en **5 módulos lógicos**:

### A. Módulo Core — Tickets de Soporte
| Tabla | Rol |
|-------|-----|
| `solicitudes` | Tabla maestra. Almacena folio, tipo, prioridad, descripción, archivos en JSON |
| `historial_solicitudes` | Bitácora inmutable de cambios de estatus. Solo INSERT, nunca UPDATE |
| `comentarios_solicitudes` | Notas internas privadas por ticket (mejoras v2) |
| `plantillas_respuesta` | Textos de respuesta reutilizables para administradores |
| `log_notificaciones` | Registro de emails enviados a solicitantes |

### B. Módulo ERP — Personal e Identidad
| Tabla | Rol |
|-------|-----|
| `cat_personal` | Catálogo maestro de empleados (fuente de verdad) |
| `usuarios` | Cuentas del portal público (mapeadas a `cat_personal`) |
| `solicitudes_actualizacion_personal` | Solicitudes de corrección de datos del empleado, aprobadas por admin |
| `cat_areas` | Catálogo de áreas organizacionales |
| `estatus` | Catálogos de estatus de personal |

### C. Módulo Inventario Hardware
| Tabla | Rol |
|-------|-----|
| `sb_bienes` | Equipos de cómputo corporativos |
| `sb_bienes_altum` | Equipos con número de inventario ALTUM |
| `sb_bienes_impresoras` | Inventario de impresoras |
| `sb_bienes_red` | Equipos de red (switches, APs, firewalls) |
| `sb_bienes_baja` | Equipos dados de baja |
| `cat_tipobien` | Tipos de bien (laptop, desktop, monitor...) |
| `cat_bienes_gral` / `cat_bienes_red` | Catálogos de clasificación |

### D. Módulo Sistemas Internos
| Tabla | Rol |
|-------|-----|
| `det_sistemainterno` | Inventario de sistemas con datos de servidor/BD |
| `det_sistemasgem` | Sistemas externos del GEM con credenciales |
| `det_doc_sistemas` | Documentación por fases de sistemas |
| `cat_fases_sistemas` | Catálogo de fases (análisis, diseño, desarrollo...) |

### E. Módulo Colaboración
| Tabla | Rol |
|-------|-----|
| `sb_chat_mensajes` | Chat interno entre administradores |
| `sb_kanban_tareas` | Tablero kanban de tareas internas |
| `eventos` | Calendario de eventos institucionales |
| `sb_audit_session` | Registro de sesiones activas |
| `sb_correos_oficial` | Correos institucionales por área |

---

## 3. Flujo Completo de una Solicitud

```
1. LOGIN USUARIO
   public/index.php → valida email contra cat_personal
   → precarga nombre, área, extensión

2. LLENADO DEL FORMULARIO
   app.js maneja: drag-and-drop de archivos, chips visuales,
   cambio dinámico de subtipo (sistemas/mantenimiento/etc.)

3. ENVÍO (POST)
   public/index.php → validarCsrfPost() → sanitiza inputs
   → sube archivos a /public/uploads/solicitudes/{hash}.ext
   → json_encode($archivos) → INSERT solicitudes
   → generarFolio() → INSERT historial ('pendiente')
   → notificacion_email.php (opcional)
   → redirect con flash "Folio: CMCT-2026-XXXX"

4. ADMINISTRADOR VE TICKET
   admin/solicitudes.php → lista con filtros/búsqueda
   → helpers.php :: getBadgeClase() colorea estatus

5. GESTIÓN DEL TICKET
   admin/detalle.php → cambiar_estatus.php (AJAX)
   → INSERT historial_solicitudes
   → INSERT comentarios_solicitudes
   → notificacion_email.php → INSERT log_notificaciones

6. CIERRE
   Estatus → 'completada' | 'cancelada'
   → resuelto_por = nombre del admin
   → Solicitante consulta en public/consulta.php via folio
```

---

## 4. Entorno y Configuración (.env)

```env
# Base de datos
DB_HOST=db          # 'db' en Docker, '127.0.0.1' en MAMP
DB_PORT=3306        # 3306 en Docker, 8889 en MAMP
DB_NAME=bd_sisibic
DB_USER=root
DB_PASS=root

# Sistema
FOLIO_PREFIX=CMCT
APP_KEY=...         # 32 bytes para firma de sesión

# IA
GROQ_API_KEY=...    # Groq API (LLaMA 3)

# Email
MAIL_ENABLED=true
MAIL_FROM=noreply@comecyt.gob.mx

# Seguridad
APP_2FA_ENABLED=false
```

---

## 5. Estilos y Branding

Sistema de diseño con **CSS Custom Properties** en `assets/css/main.css`:

```css
:root {
    --color-primary: #662331;  /* Tinto institucional */
    --color-accent:  #B19A6D;  /* Dorado institucional */
    --bg-base:       #F3F4F6;
    --bg-card:       #FFFFFF;
}
```

- **Logotipo**: Reemplazar `assets/MARCA.png` para cambiar branding (sin tocar HTML)
- **Dark mode**: `assets/css/darkmode.css` activado via `admin/api/toggle_darkmode.php`
- **Iconos**: FontAwesome 6.5 (clases `fa-solid fa-*`)

---

## 6. Agregar una Nueva Funcionalidad

Pipeline estándar para nuevos módulos:

```
1. BD: CREATE TABLE nueva_tabla (InnoDB + FK + índices)
2. Backend: admin/nueva_tabla.php con require header/footer
3. Auth: require 'config/auth.php'; verificarSesionAdmin();
4. API: admin/api/nueva_tabla.php si necesita AJAX
5. Menú: añadir <li> en header_admin.php > sidebar
6. helpers.php: agregar badges/iconos si aplica
7. Cachébusting: incrementar ?v=X.X en script/link tags
```

---

## 7. Configuración Docker (v3.0)

El sistema corre completamente en Docker para portabilidad total:

```
app        → PHP 8.1 + Apache → http://localhost:8080
db         → MySQL 8.0        → localhost:3307
phpmyadmin → phpMyAdmin       → http://localhost:8081
```

Ver [README-DOCKER.md](../README-DOCKER.md) para instrucciones completas.
