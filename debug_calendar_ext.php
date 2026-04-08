<?php
require_once __DIR__ . '/config/database.php';
$pdo = getConnection();
$inicio = '2026-04-01 00:00:00';
$fin    = '2026-05-01 00:00:00';

$sql = "
    SELECT id, titulo, fecha_inicio, fecha_fin, color, publico, FALSE as es_institucional FROM df_eventos_editoriales WHERE publico = TRUE AND fecha_inicio < ? AND fecha_fin > ?
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$fin, $inicio]);
$eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: text/plain');
echo "Eventos Editoriales Públicos encontrados:\n";
foreach ($eventos as $e) {
    echo "ID: {$e['id']} | Titulo: {$e['titulo']} | Inicio: {$e['fecha_inicio']} | Fin: {$e['fecha_fin']} | Color: {$e['color']} | Pub: " . (var_export($e['publico'], true)) . "\n";
}
