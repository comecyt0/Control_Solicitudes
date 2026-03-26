<?php
require_once __DIR__ . '/config/database.php';
$pdo = getConnection();
$stmt = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'cat_personal' AND table_schema = 'public'");
$cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "Columns: " . implode(", ", $cols) . "\n";
