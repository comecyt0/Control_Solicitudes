# Endpoints API — COMECyT Control de Solicitudes
**Referencia de APIs Internas | v3.1 | Marzo 2026**

Todos los endpoints de administración están bajo `admin/api/` y requieren sesión administrativa activa. Responden en `application/json`.

---

## Autenticación y Seguridad

Todos los endpoints verifican:
1. **Sesión** — `verificarSesionAdmin()` via `config/auth.php`
2. **CSRF** — `validarCsrfPost()` para peticiones POST
3. **JSON** — `header('Content-Type: application/json')`

Si la sesión expira devuelven:
```json
{ "ok": false, "msg": "Sesión expirada" }
```
Con HTTP 401.

---

## Endpoints de Solicitudes

### `POST /admin/api/cambiar_estatus.php`
Cambia el estatus de una solicitud e inserta entrada en `historial_solicitudes`.

**Body (form-data):**
| Campo | Tipo | Descripción |
|-------|------|-------------|
| `solicitud_id` | int | ID de la solicitud |
| `nuevo_estatus` | string | `pendiente` \| `en_proceso` \| `completada` \| `cancelada` |
| `comentario` | string | Comentario del cambio (opcional) |
| `resuelto_por` | string | Nombre del admin que cierra (si completada) |
| `csrf_token` | string | Token CSRF de sesión |

**Respuesta exitosa:**
```json
{ "ok": true, "msg": "Estatus actualizado correctamente" }
```

---

### `GET /admin/export_csv.php`
Exporta solicitudes filtradas en formato CSV descargable.

**Query params:**
| Param | Descripción |
|-------|-------------|
| `tipo` | Filtro por tipo de solicitud |
| `estatus` | Filtro por estatus |
| `desde` | Fecha inicio (YYYY-MM-DD) |
| `hasta` | Fecha fin (YYYY-MM-DD) |
| `area` | Filtro por área |

**Respuesta**: Archivo `.csv` con cabecera `Content-Disposition: attachment`.

---

### `GET /admin/api/exportar_pdf.php?id={solicitud_id}`
Genera un PDF completo de una solicitud individual para descarga o visualización.

**Query params:**
| Param | Descripción |
|-------|-------------|
| `id` | ID de la solicitud |

**Respuesta**: Archivo PDF inline o attachment.

---

## Endpoints de Comunicación

### `POST /admin/api/chat.php`
Envía o recupera mensajes del chat interno entre administradores. Soporta canal general y DMs.

**Acción `enviar`:**
```json
{
  "accion": "enviar",
  "mensaje": "Texto del mensaje",
  "tipo": "texto",
  "destinatario_id": null,
  "csrf_token": "..."
}
```
> `destinatario_id`: null = canal general, número = DM al admin específico

**Acción `obtener`:**
```json
{
  "accion": "obtener",
  "canal": "general",
  "desde_id": 0
}
```

**Respuesta:**
```json
{
  "ok": true,
  "mensajes": [
    {
      "id": 42,
      "admin_nombre": "VICTOR",
      "admin_id": 7,
      "mensaje": "Texto...",
      "fecha": "17/03/2026 14:30"
    }
  ]
}
```

---

### `POST /admin/api/comentarios.php`
Agrega o lista comentarios privados internos de una solicitud.

**Acción `agregar`:**
```json
{
  "accion": "agregar",
  "solicitud_id": 1,
  "comentario": "Revisado, hay que instalar el driver.",
  "privado": true,
  "csrf_token": "..."
}
```

**Acción `listar`:**
```json
{ "accion": "listar", "solicitud_id": 1 }
```

**Respuesta:**
```json
{
  "ok": true,
  "comentarios": [
    { "id": 1, "admin_nombre": "ABRIL", "comentario": "...", "fecha": "..." }
  ]
}
```

---

### `POST /admin/api/notificacion_email.php`
Envía notificación email al solicitante sobre cambio de estatus.

**Body:**
```json
{
  "solicitud_id": 1,
  "estatus_nuevo": "completada",
  "csrf_token": "..."
}
```

**Respuesta:**
```json
{ "ok": true, "msg": "Email enviado correctamente" }
```

Registra el resultado en `log_notificaciones`. Requiere `MAIL_ENABLED=true` en `.env`.

---

## Endpoints de Gestión

### `POST /admin/api/plantillas.php`
CRUD de plantillas de respuesta reutilizables para administradores.

| Acción | Descripción |
|--------|-------------|
| `listar` | Devuelve todas las plantillas disponibles |
| `crear` | Crea nueva plantilla con `titulo` y `contenido` |
| `eliminar` | Elimina plantilla por `id` |

---

### `POST /admin/api/toggle_darkmode.php`
Alterna el modo oscuro del administrador y persiste en BD (`administradores.dark_mode`).

**Body:**
```json
{ "dark_mode": 1, "csrf_token": "..." }
```

---

## Endpoint de IA

### `POST /admin/api/agente_ia.php`
Consulta al asistente IA (Groq API / LLaMA 3) con contexto institucional.

**Body:**
```json
{
  "pregunta": "¿Cómo resolver un bloqueo de sitio en ESYCA?",
  "solicitud_id": 42,
  "csrf_token": "..."
}
```

**Respuesta:**
```json
{
  "ok": true,
  "respuesta": "Para resolver un bloqueo de sitio en ESYCA, los pasos son..."
}
```

Requiere `GROQ_API_KEY` válido en `.env`. Modelo: `llama3-8b-8192`.

---

## Endpoint de Búsqueda

### `GET /admin/api/busqueda_global.php?q={término}`
Búsqueda full-text unificada en tickets, personal y equipos.

**Query params:**
| Param | Descripción |
|-------|-------------|
| `q` | Término (mínimo 2 caracteres) |

**Respuesta:**
```json
{
  "ok": true,
  "resultados": [
    {
      "tipo_res": "solicitud",
      "label": "CMCT-2026-0042 — GABRIELA VELAZCO",
      "sub": "Correos · completada",
      "url": "/admin/detalle.php?id=105",
      "icono": "fa-file-lines"
    },
    {
      "tipo_res": "personal",
      "label": "GABRIELA VELAZCO MIRANDA",
      "sub": "Área: Atención Ciudadana",
      "url": "/admin/personal.php?id=23",
      "icono": "fa-id-card"
    }
  ]
}
```

---

## Endpoint de Servicio Social

### `POST /admin/api/servicio_social.php`
API completa del módulo de Servicio Social.

Acciones disponibles: `lista_alumnos`, `crear_alumno`, `registrar_asistencia`, `listar_asistencias`, `crear_tarea`, `listar_tareas`, `subir_evidencia`.

---

## Endpoints Públicos (Sin Autenticación de Admin)

### `POST /public/index.php`
Alta de nueva solicitud de soporte. Requiere autenticación de usuario del portal.

### `GET /public/consulta.php?folio={CMCT-XXXX-XXXX}`
Consulta pública de estatus de solicitud por número de folio.

### `POST /public/registro.php`
Registro de nuevo usuario en el portal. Verifica contra `cat_personal` y crea entrada en `usuarios`.

---

## Códigos de Respuesta HTTP

| HTTP | Significado |
|------|-------------|
| 200 | OK — Operación exitosa |
| 400 | Bad Request — Datos inválidos o faltantes |
| 401 | Unauthorized — Sesión expirada o no autenticado |
| 403 | Forbidden — Token CSRF inválido |
| 405 | Method Not Allowed — Método HTTP incorrecto |
| 429 | Too Many Requests — Rate limit (5 intentos de login fallidos) |
| 500 | Internal Server Error — Error del servidor |
