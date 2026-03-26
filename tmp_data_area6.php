<?php
require_once __DIR__ . '/config/database.php';
$pdo = getConnection();

echo "--- Personal Area 6 ---\n";
// Usando cve_personal en lugar de id
$stmt = $pdo->prepare("SELECT cve_personal, nombre, correo_institucional, correo_personal FROM cat_personal WHERE cve_area = 6");
$stmt->execute();
$personal = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($personal);
