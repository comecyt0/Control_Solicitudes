<?php
require_once __DIR__ . '/config/database.php';
$pdo = getConnection();

echo "--- Personal (All) ---\n";
$stmt = $pdo->query("SELECT cve_personal, nombre, correo_institucional, cve_area FROM cat_personal LIMIT 20");
$res = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($res);

echo "\n--- Areas --- \n";
$stmt = $pdo->query("SELECT cve_area, nombre FROM cat_areas");
$res = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($res);
