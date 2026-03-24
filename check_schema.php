<?php
require_once __DIR__ . '/config/database.php';
$pdo = getConnection();

echo "--- Administradores ---\n";
$stmt = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'administradores'");
while ($row = $stmt->fetch()) { echo $row['column_name'] . "\n"; }

echo "\n--- Personal ---\n";
$stmt = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'cat_personal'");
while ($row = $stmt->fetch()) { echo $row['column_name'] . "\n"; }
