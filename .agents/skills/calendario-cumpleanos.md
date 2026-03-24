---
name: fix-calendario-cumpleanos
description: Skill para implementar o actualizar la funcionalidad de cumpleaños en los calendarios de COMECyT. Úsalo cuando necesites mostrar fotos de cumpleañeros, corregir emojis, añadir modales especiales o propagar cambios a los 19 calendarios de área.
---

# Skill: Gestión de Cumpleaños en Calendarios

## Contexto del Sistema
- **Calendarios afectados**: `admin/calendario.php` + `areas/*/calendario.php` (19 áreas)
- **Tabla de datos**: `cat_personal` (campos: `nombre`, `appat`, `apmat`, `fecha_nacimiento`, `foto_perfil`)
- **Fotos**: guardadas en `public/uploads/avatares/{filename}`, accesibles vía `BASE_URL . 'public/uploads/avatares/' . $foto`

---

## Regla Crítica: Emojis en PHP
```php
// ❌ MAL: comillas simples NO interpolan \u{...}
'titulo' => '\u{1F382} ' . $nombre;  // Se muestra literal

// ✅ BIEN: dobles comillas SÍ interpolan
'titulo' => "\u{1F382} " . $nombre;

// ✅ TAMBIÉN BIEN: bytes UTF-8 directos
'titulo' => "\xF0\x9F\x8E\x82 " . $nombre;
```

---

## Query SQL Correcta para Cumpleaños
```php
$stmtB = $pdo->prepare(
    "SELECT nombre, appat, apmat, fecha_nacimiento, foto_perfil
     FROM cat_personal
     WHERE activo = TRUE
       AND fecha_nacimiento IS NOT NULL
       AND EXTRACT(MONTH FROM fecha_nacimiento) = :mes"
);
$stmtB->execute([':mes' => $mes]);
$cumpleaneros = $stmtB->fetchAll(PDO::FETCH_ASSOC);
```

---

## Array Estándar de Evento Cumpleaños
```php
foreach ($cumpleaneros as $cp) {
    $diaCumple = (int) (new DateTime($cp['fecha_nacimiento']))->format('d');
    $nombreCompleto = trim($cp['nombre'] . ' ' . $cp['appat'] . ' ' . $cp['apmat']);
    // Calcular edad correctamente
    $anioNacimiento = (int)(new DateTime($cp['fecha_nacimiento']))->format('Y');
    $edadAnios = $anio - $anioNacimiento;

    $calendarioEventos[$diaCumple][] = [
        'id'           => null,
        'titulo'       => "\u{1F382} " . $nombreCompleto,
        'descripcion'  => 'Cumpleaños institucional (' . $edadAnios . ' años)',
        'fecha_inicio' => sprintf('%04d-%02d-%02d 00:00:00', $anio, $mes, $diaCumple),
        'fecha_fin'    => sprintf('%04d-%02d-%02d 23:59:59', $anio, $mes, $diaCumple),
        'color'        => '#B19A6D',   // nota-dorado
        'publico'      => false,
        'es_cumple'    => true,
        // Campos extra para el modal de cumpleaños
        'foto_perfil'  => $cp['foto_perfil'] ?? null,
        'nombre_cumple'=> $nombreCompleto,
        'edad'         => $edadAnios,
    ];
}
```

---

## Renderizado en el HTML (dentro del foreach de eventos)
```php
<?php
    $esCumple = !empty($ev['es_cumple']);
    $fotoUrl  = '';
    if ($esCumple && !empty($ev['foto_perfil'])) {
        $fotoUrl = BASE_URL . 'public/uploads/avatares/' . $ev['foto_perfil'];
    }
?>
<div class="evento-pildora <?= $claseNota ?><?= $esCumple ? ' es-cumple' : '' ?>">
  <?php if ($esCumple): ?>
    <!-- Nota de cumpleaños con miniavatar -->
    <div class="evento-titulo cumple-titulo" style="display:flex;align-items:center;gap:6px;">
        <?php if (!empty($fotoUrl)): ?>
            <img src="<?= esc($fotoUrl) ?>" alt="" class="cumple-mini-avatar">
        <?php else: ?>
            <span class="cumple-mini-avatar cumple-mini-placeholder"><i class="fa-solid fa-user"></i></span>
        <?php endif; ?>
        <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">🎂 <?= esc($ev['nombre_cumple']) ?></span>
    </div>
    <div class="evento-acciones">
        <button type="button" class="btn-evento-accion"
                onclick="event.stopPropagation(); abrirModalCumple(
                    '<?= esc(addslashes($ev['nombre_cumple'] ?? '')) ?>',
                    '<?= esc(addslashes($ev['descripcion'] ?? '')) ?>',
                    '<?= esc($fotoUrl) ?>',
                    '<?= $ev['edad'] ?? 0 ?>',
                    '<?= date('d/m', strtotime($ev['fecha_inicio'])) ?>'
                )">
            <i class="fa-solid fa-eye"></i>
        </button>
    </div>
  <?php else: ?>
    <!-- Nota de evento normal -->
    ... (lógica normal)
  <?php endif; ?>
</div>
```

---

## CSS Necesario (incluir en $extraHead)
```css
.cumple-mini-avatar {
    width: 22px; height: 22px;
    border-radius: 50%; object-fit: cover;
    border: 1.5px solid #ca8a04;
    flex-shrink: 0; display: inline-block; vertical-align: middle;
}
.cumple-mini-placeholder {
    background: #fef08a;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 0.7rem; color: #ca8a04;
}
.evento-pildora.es-cumple { border-top-color: #ca8a04; }
```

---

## Modal de Cumpleaños (HTML)
El modal con ID `modalVerCumple` debe incluir:
- Header con degradado dorado (#fef9c3 → #fde68a)
- `<img id="mc_foto">` para la foto
- `<div id="mc_foto_placeholder">` con emoji 🎂 (se muestra cuando no hay foto)
- `<h2 id="mc_nombre_grande">` para el nombre
- `<p id="mc_edad_label">` para la edad
- `<span id="mc_fecha">` para la fecha "dd/mm"

## Función JavaScript
```javascript
function abrirModalCumple(nombre, desc, fotoUrl, edad, fecha) {
    document.getElementById('mc_nombre').textContent = nombre;
    document.getElementById('mc_nombre_grande').textContent = nombre;
    document.getElementById('mc_edad_label').textContent = '¡Celebra sus ' + edad + ' años!';
    document.getElementById('mc_fecha').textContent = fecha;

    const imgEl = document.getElementById('mc_foto');
    const phEl  = document.getElementById('mc_foto_placeholder');

    if (fotoUrl && fotoUrl.trim() !== '') {
        imgEl.src = fotoUrl;
        imgEl.style.display = 'block';
        phEl.style.display  = 'none';
    } else {
        imgEl.style.display = 'none';
        phEl.style.display  = 'flex';
    }
    abrirModal('modalVerCumple');
}
```

---

## Propagación a las 19 Áreas
Si necesitas propagar cambios al bloque de cumpleaños de los 19 calendarios de área,
usa el script `_fix_areas.ps1` o `_fix_areas_cumple.php` como referencia, adaptando
los strings de búsqueda y reemplazo al cambio específico.

**Flujo de trabajo obligatorio para areas/**:
1. Modifica los archivos en el host (`c:\Intranet\areas\*/calendario.php`)
2. Ejecuta `docker compose build app`
3. Ejecuta `docker compose up -d`
