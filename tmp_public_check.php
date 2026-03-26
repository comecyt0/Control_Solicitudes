<?php
require_once __DIR__ . '/config/database.php';
$pdo = getConnection();
$stmt = $pdo->query("SELECT schemaname, tablename FROM pg_catalog.pg_tables WHERE tablename = 'cat_personal' AND schemaname = 'public'");
$res = $stmt->fetch();
if ($res) {
    echo "cat_personal exists in PUBLIC schema!\n";
} else {
    echo "cat_personal does NOT exist in PUBLIC schema.\n";
}
