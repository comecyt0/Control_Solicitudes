<?php
require_once 'config/database.php';
$pdo = getConnection();

echo "--- ESTATUS ---\n";
$stmt = $pdo->query("SELECT pg_get_constraintdef(oid) FROM pg_constraint WHERE conname = 'solicitudes_estatus_check'");
echo $stmt->fetchColumn() . "\n\n";

echo "--- ADMINISTRADORES ---\n";
$stmt = $pdo->query("SELECT id, nombre FROM administradores");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['id'] . "|" . $row['nombre'] . "\n";
}

echo "\n--- AREAS ---\n";
$stmt = $pdo->query("SELECT cve_area, des_area FROM cat_areas");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['cve_area'] . "|" . $row['des_area'] . "\n";
}

echo "\n--- USUARIOS MUESTRA ---\n";
$stmt = $pdo->query("SELECT id, nombre FROM usuarios LIMIT 10");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['id'] . "|" . $row['nombre'] . "\n";
}
