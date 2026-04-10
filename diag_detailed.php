<?php
require_once __DIR__ . '/config/database.php';
$pdo = getConnection();
echo "--- EVENTOS WITH DG MARKER ---\n";
$stmt = $pdo->query("SELECT id, titulo, cve_area, publico, descripcion FROM eventos WHERE descripcion LIKE '%[DG:%'");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: {$row['id']} | Title: {$row['titulo']} | Area: {$row['cve_area']} | Public: {$row['publico']} | Desc: {$row['descripcion']}\n";
}

echo "\n--- DG EDITORIAL EVENTS (PUBLIC) ---\n";
$stmt = $pdo->query("SELECT id, titulo, publico, cve_area FROM df_eventos_editoriales WHERE cve_area = 2 AND publico = 1");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: {$row['id']} | Title: {$row['titulo']} | Area: {$row['cve_area']} | Public: {$row['publico']}\n";
}
