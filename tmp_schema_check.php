<?php
require_once __DIR__ . '/config/database.php';
$pdo = getConnection();
$stmt = $pdo->query("SELECT schemaname, tablename FROM pg_catalog.pg_tables WHERE tablename = 'cat_personal'");
$res = $stmt->fetch();
print_r($res);
