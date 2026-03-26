<?php
require_once __DIR__ . '/config/database.php';
$pdo = getConnection();
$stmt = $pdo->query("SELECT tablename FROM pg_catalog.pg_tables WHERE schemaname != 'pg_catalog' AND schemaname != 'information_schema'");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "Tables found: " . implode(", ", $tables) . "\n";
