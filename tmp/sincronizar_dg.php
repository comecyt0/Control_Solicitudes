<?php
require_once __DIR__ . '/../config/database.php';

$pdo = getConnection();

function dgBuscarEspejo(PDO $pdo, int $dfId): ?int {
    $stmt = $pdo->prepare("SELECT id FROM eventos WHERE descripcion LIKE ? LIMIT 1");
    $stmt->execute(['%[DG:' . $dfId . ']%']);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? (int)$row['id'] : null;
}

function dgSincronizarPublico(PDO $pdo, int $dfId, string $titulo, string $descripcion, string $fi, string $ff, string $color): void {
    $marcador   = '[DG:' . $dfId . ']';
    $descEspejo = trim($descripcion . ' ' . $marcador);
    $espejoId   = dgBuscarEspejo($pdo, $dfId);
    if ($espejoId) {
        $pdo->prepare("UPDATE eventos SET titulo=?, descripcion=?, fecha_inicio=?, fecha_fin=?, color=?, publico=TRUE WHERE id=?")
            ->execute([$titulo, $descEspejo, $fi, $ff, $color, $espejoId]);
        echo "Actualizado espejo para DG ID $dfId\n";
    } else {
        $pdo->prepare("INSERT INTO eventos (titulo, descripcion, fecha_inicio, fecha_fin, color, publico) VALUES (?,?,?,?,?,TRUE)")
            ->execute([$titulo, $descEspejo, $fi, $ff, $color]);
        echo "Creado espejo para DG ID $dfId\n";
    }
}

echo "Iniciando sincronización retroactiva de eventos DG...\n";

$stmt = $pdo->prepare("SELECT id, titulo, descripcion, fecha_inicio, fecha_fin, color FROM df_eventos_editoriales WHERE cve_area = 2 AND publico = TRUE");
$stmt->execute();
$eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($eventos as $ev) {
    dgSincronizarPublico($pdo, (int)$ev['id'], $ev['titulo'], (string)$ev['descripcion'], $ev['fecha_inicio'], $ev['fecha_fin'], (string)$ev['color']);
}

echo "Sincronización completada.\n";
