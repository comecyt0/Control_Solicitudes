---
description: Skill for propagating Meeting Room (Sala de Juntas) UI across multiple calendars.
---
# Sala de Juntas Rollout Skill

This skill documents how to propagate the "Sala de Juntas" reservation UI and logic across multiple departmental calendars in the Intranet system.

## UI Requirements
Each calendar pildora (sticky note) must:
1.  Display the `fa-person-chalkboard` icon if `requiere_sala` is true.
2.  Pass 7 arguments to `abrirModalVer` (tile, desc, ini, fin, requiereSala, area, persona).
3.  Pass 8 arguments to `abrirModalEditar` (id, titulo, desc, ini, fin, color, publico, requiereSala).

## Backend Requirements
1.  SQL INSERT/UPDATE must include the `requiere_sala` column.
2.  The area and requester name should be automatically pulled from the session (`$_SESSION['admin_nombre']`, etc.).

## Rollout Script logic (PowerShell)
To safely update files without mangling, use a line-by-line reading approach:
```powershell
$files = Get-ChildItem -Path "areas" -Filter "calendario.php" -Recurse
foreach ($f in $files) {
    # Read, process line by line, write back
}
```
