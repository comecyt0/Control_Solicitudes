---
description: skill reutilizable para renderizado robusto de avatares en Intranet (Evitando CORS y rutas fallidas).
---

# Skill: Renderizado Seguro de Avatares

Cuando necesites inyectar la visualización del rostro/fotografía de un usuario en tablas u otras vistas, DEBES utilizar siempre este acercamiento (fallback SVG en base64) para evitar ciclos infinitos en el DOM por fallos de red en intranets o bloqueos de `ui-avatars.com`. 

## Boilerplate Universal (PHP)

1. **Consulta Base**: 
   Asegúrate de traer `foto_perfil` en la consulta SQL (mediante `LEFT JOIN cat_personal` o subconsultas con `LIMIT 1`).
2. **HTML Seguro**:
```php
<?php
$defaultAvatar = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0ibm9uZSIgc3Ryb2tlPSIjOTRhM2I4IiBzdHJva2Utd2lkdGg9IjIiIHN0cm9rZS1saW5lY2FwPSJyb3VuZCIgc3Ryb2tlLWxpbmVqb2luPSJyb3VuZCI+PHBhdGggZD0iTTIwIDIxdi0yYTRgNCAwIDAgMC00LTRIOTRhNCA0IDAgMCAwLTQgNHYyIi8+PGNpcmNsZSBjeD0iMTIiIGN5PSI3IiByPSI0Ii8+PC9zdmc+';

// La ruta original del servidor es public/uploads/avatares/
// Si la subida era de reportes/tickets, validar con cuidado el root path.
$fotoUrl = !empty($row['foto_perfil']) ? BASE_URL . 'public/uploads/avatares/' . esc($row['foto_perfil']) : $defaultAvatar;
?>

<!-- Render (Ejemplo en tabla miniaturas 24px) -->
<img src="<?= $fotoUrl ?>" 
     style="width: 24px; height: 24px; border-radius: 50%; object-fit: cover; flex-shrink:0;" 
     onerror="this.onerror=null; this.src='<?= $defaultAvatar ?>'" 
     alt="Avatar">
```

## Por qué esto funciona sin importar nada:
- El uso de `this.onerror=null` previene ciclos infinitos en Javascript que congelarían la página si el origen falla.
- El uso del string Base64 (`'data:image/svg+xml;base64,...'`) incrustado directamente sobre el `HTML` evita cualquier dependencia de archivos en disco, resolviendo la escasez de recursos si no se hace el clone completo o si `assets/img/default-avatar.png` no fue desplegado correctemente en Producción.
