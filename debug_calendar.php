<?php
require_once __DIR__ . '/config/database.php';
$pdo = getConnection();
$inicio = '2026-04-01 00:00:00';
$fin    = '2026-05-01 00:00:00';

$sql = "
    SELECT id, titulo, publico, TRUE as es_institucional FROM eventos WHERE publico = TRUE AND fecha_inicio < ? AND fecha_fin > ?
    UNION ALL
    SELECT id, titulo, publico, FALSE as es_institucional FROM df_eventos_editoriales WHERE publico = TRUE AND fecha_inicio < ? AND fecha_fin > ?
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$fin, $inicio, $fin, $inicio]);
$eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: text/plain');
echo "Total eventos encontrados: " . count($eventos) . "\n";
foreach ($eventos as $e) {
    echo "ID: " . $e['id'] . " | Título: " . $e['titulo'] . " | Institucional: " . ($e['es_institucional']?'SÍ':'NO') . "\n";
}
