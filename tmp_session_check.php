<?php
session_start();
require_once __DIR__ . '/config/database.php';
echo "Session Admin Area: " . ($_SESSION['admin_cve_area'] ?? 'NONE') . "\n";
echo "Session User Area: " . ($_SESSION['user_cve_area'] ?? 'NONE') . "\n";
$pdo = getConnection();
$stmt = $pdo->query("SELECT cve_area, des_area FROM cat_areas WHERE cve_area IN (6, 18)");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
