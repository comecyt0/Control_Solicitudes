<?php
require_once __DIR__ . '/../config/database.php';
$pdo = getConnection();

echo "--- ADMINISTRADORES ---\n";
$stmt = $pdo->query("SELECT id, nombre, email, rol, cve_area FROM administradores");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo implode('|', $row) . "\n";
}

echo "\n--- PERSONAL PRUEBA ---\n";
$stmtP = $pdo->query("SELECT cve_personal, nombre, correo_institucional, correo_personal, rol_jefatura, nombre_jefatura FROM cat_personal WHERE nombre ILIKE '%PRUEBA%'");
while($row = $stmtP->fetch(PDO::FETCH_ASSOC)) {
    echo implode('|', $row) . "\n";
}
