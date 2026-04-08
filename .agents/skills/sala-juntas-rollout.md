---
name: Sala de Juntas Rollout
description: Instructions for extending the "Sala de Juntas" feature to departmental modules.
---

# Sala de Juntas Rollout Skill

This skill provides a systematic approach to updating departmental calendar modules to support room reservations.

## ⚙️ Backend: `api/calendario_solicitudes.php`

Update the `gestionar` action (case `aceptado`) to copy the new columns:

```php
// Existing: SELECT titulo, descripcion, fecha_inicio, fecha_fin, color FROM sb_calendario_solicitudes WHERE id = ?
// Updated:
$stmtSel = $pdo->prepare("SELECT titulo, descripcion, fecha_inicio, fecha_fin, color, requiere_sala, area_solicitante, persona_solicitante FROM sb_calendario_solicitudes WHERE id = ?");

// Existing: INSERT INTO eventos (titulo, descripcion, fecha_inicio, fecha_fin, color, creado_por, publico) VALUES (?, ?, ?, ?, ?, ?, TRUE)
// Updated:
$ins = $pdo->prepare("INSERT INTO eventos (titulo, descripcion, fecha_inicio, fecha_fin, color, creado_por, publico, requiere_sala, area_solicitante, persona_solicitante) 
                      VALUES (?, ?, ?, ?, ?, ?, TRUE, ?, ?, ?)");
```

## 📅 Frontend: `calendario.php`

1. **Grid Icon:** Add the room icon in the event pill loop.
```php
<?php if (!empty($ev['requiere_sala'])): ?>
    <i class="fa-solid fa-person-chalkboard" style="font-size:0.75rem; flex-shrink: 0; color: #ca8a04;" title="Sala de Juntas Reservada"></i>
<?php endif; ?>
```

2. **Detail Modal:** Update `abrirModalVer` call to pass new parameters and update the JS function to display them.

3. **Solicitudes List:** Update the list render to show a "SALA" badge and the solicitor area.

## 🎨 Aesthetics & Responsiveness

Apply the `glass-modal` class and ensure `flexbox` is used for date ranges instead of fixed grids.
