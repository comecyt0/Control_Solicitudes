# Skill: Sincronización de Eventos (Espejo de Tablas)

## Contexto
En arquitecturas multi-área donde cada departamento tiene su propio calendario (`df_eventos_editoriales`), surge la necesidad de propagar ciertos eventos a un calendario institucional global (`eventos`) que es consumido por todas las áreas en modo solo lectura.

## Técnica: Marcadores de Referencia (Fingerprinting)
Para evitar alterar el esquema de la base de datos con columnas de IDs cruzados, se utiliza un "Marcador" en el campo de texto de descripción (o notas) del registro espejo.

### Formato del Marcador
```
[AREA-DE:ID]
Ejemplo: [DG:42]
```

## Implementación en PHP

### 1. Funciones Helper
```php
function buscarEspejo(PDO $pdo, int $originalId, string $prefijo): ?int {
    $stmt = $pdo->prepare("SELECT id FROM eventos WHERE descripcion LIKE ? LIMIT 1");
    $stmt->execute(['%[' . $prefijo . ':' . $originalId . ']%']);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? (int)$row['id'] : null;
}

function sincronizarEspejo(PDO $pdo, int $originalId, string $titulo, string $desc, string $fi, string $ff, string $color, string $prefijo): void {
    $marcador = '[' . $prefijo . ':' . $originalId . ']';
    $descConMarcador = trim($desc . ' ' . $marcador);
    $espejoId = buscarEspejo($pdo, $originalId, $prefijo);
    
    if ($espejoId) {
        // UPDATE
        $pdo->prepare("UPDATE eventos SET titulo=?, descripcion=?, fecha_inicio=?, fecha_fin=?, color=?, publico=TRUE WHERE id=?")
            ->execute([$titulo, $descConMarcador, $fi, $ff, $color, $espejoId]);
    } else {
        // INSERT
        $pdo->prepare("INSERT INTO eventos (titulo, descripcion, fecha_inicio, fecha_fin, color, publico) VALUES (?,?,?,?,?,TRUE)")
            ->execute([$titulo, $descConMarcador, $fi, $ff, $color]);
    }
}

function eliminarEspejo(PDO $pdo, int $originalId, string $prefijo): void {
    $espejoId = buscarEspejo($pdo, $originalId, $prefijo);
    if ($espejoId) {
        $pdo->prepare("DELETE FROM eventos WHERE id=?")->execute([$espejoId]);
    }
}
```

### 2. Hooks en el Controlador (PRG)
Se deben invocar estas funciones en los bloques de acción POST:
- **Crear**: Llamar `sincronizarEspejo` tras obtener el `lastInsertId()`.
- **Editar**: Llamar `sincronizarEspejo` (si se mantiene público) o `eliminarEspejo` (si se vuelve privado).
- **Eliminar**: Llamar `eliminarEspejo` ANTES de borrar el registro original.

## Ventajas
1. **Transparencia**: Los calendarios de área no necesitan saber de dónde viene el evento, solo consultan la tabla institucional.
2. **Integridad**: Evita datos huérfanos en la tabla global.
3. **No-Invasivo**: No requiere migraciones de BD (`ALTER TABLE`).
