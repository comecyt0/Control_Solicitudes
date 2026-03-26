<?php
require_once __DIR__ . '/config/database.php';
$pdo = getConnection();
echo "DB Name: " . DB_NAME . "\n";
echo "DB User: " . DB_USER . "\n";

$stmt = $pdo->query("SELECT tablename FROM pg_catalog.pg_tables WHERE tablename = 'cat_personal'");
$exists = $stmt->fetch();
if ($exists) {
    echo "cat_personal exists!\n";
} else {
    echo "cat_personal DOES NOT exist in this DB.\n";
}

$stmt = $pdo->query("SELECT tablename FROM pg_catalog.pg_tables WHERE tablename LIKE '%personal%'");
$others = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "Other 'personal' tables: " . implode(", ", $others) . "\n";
