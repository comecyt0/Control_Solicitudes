<?php
require_once __DIR__ . '/../config/database.php';
$pdo = getConnection();
$stmt = $pdo->query("SELECT publico FROM df_eventos_editoriales WHERE publico = TRUE LIMIT 1");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Type: " . gettype($row['publico']) . "\n";
echo "Value: " . var_export($row['publico'], true) . "\n";

$stmt2 = $pdo->query("SELECT publico FROM eventos WHERE publico = TRUE LIMIT 1");
$row2 = $stmt2->fetch(PDO::FETCH_ASSOC);
echo "Type Eventos: " . gettype($row2['publico']) . "\n";
echo "Value Eventos: " . var_export($row2['publico'], true) . "\n";
