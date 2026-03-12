# Endpoints API — COMECyT Control de Solicitudes
**Referencia de Endpoints AJAX Internos | v3.0 Marzo 2026**

Todos los endpoints están bajo `admin/api/` y requieren sesión administrativa activa. Responden en JSON.

---

## Autenticación
Todos los endpoints verifican:
1. `verificarSesionAdmin()` via `config/auth.php`
2. Token CSRF en peticiones POST via `validarCsrfPost()`

Si la sesión expira devuelven `{"ok": false, "msg": "Sesión expirada"}` con HTTP 401.

---

## Endpoints disponibles

### `POST /admin/api/cambiar_estatus.php`
Cambia el estatus de una solicitud e inserta en `historial_solicitudes`.

**Body (form-data):**
| Campo | Tipo | Descripción |
|-------|------|-------------|
| `solicitud_id` | int | ID de la solicitud |
| `nuevo_estatus` | string | `pendiente` \| `en_proceso` \| `completada` \| `cancelada` |
| `comentario` | string | Comentario del cambio (opcional) |
| `csrf_token` | string | Token CSRF de sesión |

**Respuesta exitosa:**
```json
{ "ok": true, "msg": "Estatus actualizado correctamente" }
```

---

### `GET /admin/api/busqueda_global.php?q={término}`
Búsqueda full-text en solicitudes, personal y equipos.

**Query params:**
| Param | Descripción |
|-------|-------------|
| `q` | Término de búsqueda (mínimo 3 caracteres) |

**Respuesta:**
```json
{
  "solicitudes": [{ "id": 1, "folio": "CMCT-2026-0001", "solicitante": "...", "estatus": "..." }],
  "personal":    [{ "cve_personal": 1, "nombre": "...", "appat": "..." }],
  "equipos":     [{ "cve_bienes": 1, "marca": "...", "modelo": "..." }]
}
```

---

### `POST /admin/api/chat.php`
Envía o recupera mensajes del chat interno entre administradores.

**Acción `enviar`:**
```json
{ "accion": "enviar", "mensaje": "Texto...", "tipo": "texto", "csrf_token": "..." }
```

**Acción `obtener`:**
```json
{ "accion": "obtener", "desde": "2026-03-06 10:00:00" }
```

**Respuesta:**
```json
{ "ok": true, "mensajes": [{ "id": 1, "admin_nombre": "...", "mensaje": "...", "fecha": "..." }] }
```

---

### `POST /admin/api/comentarios.php`
Agrega o lista comentarios internos privados de una solicitud.

**Acción `agregar`:**
```json
{ "accion": "agregar", "solicitud_id": 1, "comentario": "...", "privado": true, "csrf_token": "..." }
```

**Acción `listar`:**
```json
{ "accion": "listar", "solicitud_id": 1 }
```

---

### `POST /admin/api/plantillas.php`
CRUD de plantillas de respuesta reutilizables.

| Acción | Descripción |
|--------|-------------|
| `listar` | Devuelve todas las plantillas |
| `crear` | Crea nueva plantilla |
| `eliminar` | Elimina plantilla por ID |

---

### `POST /admin/api/notificacion_email.php`
Envía notificación email al solicitante sobre cambio de estatus.

**Body:**
```json
{ "solicitud_id": 1, "estatus_nuevo": "completada", "csrf_token": "..." }
```

Registra el resultado en `log_notificaciones`.

---

### `POST /admin/api/agente_ia.php`
Consulta al agente de IA (Groq/LLaMA 3) con contexto de la solicitud.

**Body:**
```json
{ "solicitud_id": 1, "pregunta": "¿Qué pasos seguir para resolver esta solicitud?", "csrf_token": "..." }
```

**Respuesta:**
```json
{ "ok": true, "respuesta": "Texto de la IA..." }
```

Requiere `GROQ_API_KEY` válido en `.env`.

---

### `POST /admin/api/toggle_darkmode.php`
Alterna el modo oscuro del administrador y persiste en BD.

**Body:**
```json
{ "dark_mode": true, "csrf_token": "..." }
```

---

### `GET /admin/export_csv.php`
Exporta solicitudes filtradas en formato CSV.

**Query params:**
| Param | Descripción |
|-------|-------------|
| `tipo` | Filtro por tipo de solicitud |
| `estatus` | Filtro por estatus |
| `desde` | Fecha inicio (YYYY-MM-DD) |
| `hasta` | Fecha fin (YYYY-MM-DD) |

---

### `GET /admin/api/exportar_pdf.php?id={solicitud_id}`
Genera PDF de una solicitud individual para descarga.

---

## Endpoints Públicos (sin autenticación)

### `POST /public/index.php`
Alta de nueva solicitud de soporte.

### `GET /public/consulta.php?folio={CMCT-XXXX-XXXX}`
Consulta pública de estatus por folio.

### `POST /public/registro.php`
Registro de nuevo usuario en el portal. Si el email ya existe en `cat_personal`, genera una `solicitudes_actualizacion_personal`.

---

## Códigos de respuesta

| HTTP | Significado |
|------|-------------|
| 200 | OK |
| 400 | Datos inválidos o faltantes |
| 401 | Sesión expirada o no autenticado |
| 403 | Token CSRF inválido |
| 429 | Rate limit — demasiados intentos de login |
| 500 | Error interno del servidor |
