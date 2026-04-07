---
name: agenda-area-calendar
description: >
  Skill para crear, depurar y mantener módulos Agenda+Kanban de área.
  Usar cuando se modifiquen archivos areas/*/agenda.php.
  CARGAR AUTOMÁTICAMENTE si el usuario menciona: agenda, calendario, kanban, eventos, cumpleaños, modales.
---

# Skill: Módulo Agenda + Kanban de Área

## Arquitectura del módulo

Cada área tiene su propio `areas/{slug}/agenda.php` que implementa:
1. **Calendario mensual** con eventos propios y eventos institucionales
2. **Tablero Kanban** (Pendiente / En Proceso / Completado)

## Tablas de base de datos

| Tabla | Propósito | Columna clave |
|-------|-----------|---------------|
| `df_eventos_editoriales` | Eventos propios de cada área | `cve_area` |
| `eventos` | Eventos institucionales públicos (solo lectura para áreas) | `publico = TRUE` |
| `sb_kanban_tareas` | Tareas del Kanban por área | `cve_area` |
| `cat_personal` | Personal para cumpleaños y asignaciones | `cve_area`, `fecha_nacimiento` |
| `ss_usuarios` | Usuarios para cumpleaños | `fecha_nacimiento` |

## Áreas del sistema

| Slug del directorio | `cve_area` | Color primario |
|---------------------|------------|----------------|
| `desarrollo_tecnologico` | 12 | `#6d28d9` (Violet) |
| `juridico_igualdad` | 17 | `#1e3a5f` (Navy) |
| `apoyo_investigacion` | 9 | `#3730a3` (Indigo) |
| `financiamiento` | 15 | `#064e3b` (Emerald) |
| `formacion_rrhh` | 10 | `#0f766e` (Teal) |
| `juridico` | 19 | `#991b1b` (Crimson) |

## ⚠️ Reglas de permisos CRÍTICAS

- Las áreas **solo pueden** crear/editar/eliminar eventos de `df_eventos_editoriales` con `cve_area = {suya}`.
- Los eventos de la tabla `events` (institucionales, `publico = TRUE`) se muestran en **SOLO LECTURA** en todos los calendarios de área.
- Solo **Dirección General** y **Sistemas** pueden publicar en la tabla `eventos`.
- El HTML ya implementa esta lógica: `if ($esInstitucional)` → solo botón Ver (sin Editar/Eliminar).

## 🐛 Bugs conocidos y patrones correctos

### BUG CRÍTICO: Modales no abren/cierran (conflicto CSS)

**Causa**: `main.css` define `.modal-backdrop { display:none }` y `.modal-backdrop.open { display:flex }`.
Cualquier código que use `.modal-backdrop.active` causará que los botones de cerrar **no funcionen**.

**SOLUCIÓN OBLIGATORIA**: Usar `style.display` directamente. Nunca clases CSS para mostrar/ocultar modales.

```javascript
// ✅ CORRECTO — siempre así
function agAbrirModal(id)  { document.getElementById(id).style.display = 'flex'; }
function agCerrarModal(id) { document.getElementById(id).style.display = 'none'; }

// ❌ INCORRECTO — causa conflicto con main.css
function abrirModal(id)  { document.getElementById(id).classList.add('active'); }
function cerrarModal(id) { document.getElementById(id).classList.remove('active'); }
```

**CSS del backdrop** — usar prefijo `agenda-` para evitar conflictos:
```css
/* ✅ CORRECTO */
.agenda-modal-backdrop { display: none; position: fixed; inset: 0; ... }

/* ❌ INCORRECTO — choca con main.css .modal-backdrop */
.modal-backdrop { display: none; ... }
.modal-backdrop.active { display: flex; } /* nunca hacer esto */
```

**Cierre al hacer clic fuera** — usar `onclick` inline:
```html
<!-- ✅ CORRECTO -->
<div class="agenda-modal-backdrop" id="modalX" style="display:none;"
     onclick="if(event.target===this)agCerrarModal('modalX')">
```

**Tecla Escape** — siempre incluir:
```javascript
document.addEventListener('keydown', function(e) {
    if (e.key !== 'Escape') return;
    document.querySelectorAll('.agenda-modal-backdrop').forEach(function(m) {
        m.style.display = 'none';
    });
});
```

### Modal de cumpleaños

Debe ser idéntico al de `public/calendario.php` — fondo amarillo-dorado, foto centrada 110px:

```javascript
function agVerCumple(titulo, fotoUrl, nombre, fecha) {
    document.getElementById('mc_nombre').textContent = agDecode(nombre || titulo);
    document.getElementById('mc_fecha').textContent  = fecha;
    var img = document.getElementById('mc_foto');
    var ph  = document.getElementById('mc_placeholder');
    if (fotoUrl) { img.src = fotoUrl; img.style.display = 'block'; ph.style.display = 'none'; }
    else { img.style.display = 'none'; ph.style.display = 'flex'; }
    agAbrirModal('modalVerCumple');
}
```

### Consulta de cumpleaños (fuentes duales)

```sql
SELECT TRIM(CONCAT(nombre,' ',COALESCE(appat,''),' ',COALESCE(apmat,''))) AS nombre,
       fecha_nacimiento, foto_perfil
FROM cat_personal WHERE activo=TRUE AND fecha_nacimiento IS NOT NULL
  AND TO_CHAR(fecha_nacimiento,'MM') = ?
UNION
SELECT TRIM(CONCAT(nombre,' ',COALESCE(appat,''),' ',COALESCE(apmat,''))) AS nombre,
       fecha_nacimiento, foto_perfil
FROM ss_usuarios WHERE activo=TRUE AND fecha_nacimiento IS NOT NULL
  AND TO_CHAR(fecha_nacimiento,'MM') = ?
```

### Consulta de eventos institucionales (fusión con área)

```sql
-- Propios del area (editables)
SELECT *, FALSE AS es_institucional FROM df_eventos_editoriales
WHERE cve_area = ? AND fecha_inicio < ? AND fecha_fin > ?

-- Institucionales (solo lectura)
SELECT *, TRUE AS es_institucional FROM eventos
WHERE publico = TRUE AND fecha_inicio < ? AND fecha_fin > ?
```

### Píldoras del Calendario (Diseño "Post-it" Homologado)

Para igualar el diseño de `admin/calendario.php`, las píldoras usan un efecto de revelado de botones en hover y sombreado profundo:

**CSS ($extraHead):**
```css
.evento-pildora { 
    transition: all .2s cubic-bezier(.175,.885,.32,1.275); 
    box-shadow: 2px 2px 4px rgba(0,0,0,.05), inset -10px -10px 20px rgba(0,0,0,0.03); 
}
.evento-pildora:hover { transform: scale(1.02) translateY(-2px) rotate(-1deg); }
.evento-acciones { opacity: 0; max-height: 0; transition: all .2s ease; }
.evento-pildora:hover .evento-acciones { opacity: 1; max-height: 30px; margin-top: 4px; }
```

**JS Signature:**
```javascript
// ✅ CORRECTO — permite abrir directamente en modo 'ver' o 'editar'
function agPillClick(el, modo) {
    var m = modo || 'ver';
    // ... invoca agVerDetalleEvento(..., m)
}
```

**HTML (Botones):**
```html
<button onclick="event.stopPropagation(); agPillClick(this.closest('.evento-pildora'), 'ver')">👁</button>
<button onclick="event.stopPropagation(); agPillClick(this.closest('.evento-pildora'), 'editar')">✏️</button>
```

### Kanban — filtro estricto por cveArea

```sql
SELECT t.*, COALESCE(a.nombre, p.nombre) AS asignado_nombre
FROM sb_kanban_tareas t
LEFT JOIN administradores a ON t.asignado_a = a.id
LEFT JOIN cat_personal p ON t.asignado_a = p.cve_personal
WHERE t.cve_area = ?   -- SIEMPRE filtrar por área
ORDER BY t.estatus DESC, t.id DESC
```

> **IMPORTANTE**: El UPDATE de tareas también debe incluir `AND cve_area=?` para seguridad:
> `UPDATE sb_kanban_tareas SET titulo=?,descripcion=?,color=? WHERE id=? AND cve_area=?`

## Workflow para agregar/modificar una agenda de área

1. Leer `areas/desarrollo_tecnologico/agenda.php` como **template de referencia**
2. Cambiar solo: `$cveArea`, `$colorPrimary`, nombre del área en HTML, prefijo CSS de clase `.nota-{sufijo}`
3. Replicar con: `powershell -ExecutionPolicy Bypass -File "c:\Intranet\_agents\scripts\replicate_agenda.ps1"`
4. Verificar con: `powershell -ExecutionPolicy Bypass -File "c:\Intranet\_agents\scripts\check_agenda.ps1"`
5. Reconstruir: `docker compose up -d --build app`
6. Commit: `git add . ; git commit -m "feat: ..."`

## Comandos útiles

```powershell
# Verificar encodings y fixes en todos los archivos
powershell -ExecutionPolicy Bypass -File "c:\Intranet\_agents\scripts\check_agenda.ps1"

# Replicar template a los 5 archivos restantes (tras editar desarrollo_tecnologico)
powershell -ExecutionPolicy Bypass -File "c:\Intranet\_agents\scripts\replicate_agenda.ps1"

# Migración de kanban (registros sin área asignada)
docker compose exec db psql -U comecyt_user -d bd_sisibic -c "UPDATE sb_kanban_tareas SET cve_area=1 WHERE cve_area IS NULL;"

# Rebuild Docker
docker compose up -d --build app
```
