<?php
require_once __DIR__ . '/config/database.php';
$pdo = getConnection();

echo "--- Personal Area 6 ---\n";
$stmt = $pdo->prepare("SELECT id, nombre, correo_institucional, correo_personal FROM cat_personal WHERE cve_area = 6");
$stmt->execute();
$personal = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($personal);

echo "\n--- Administradores ---\n";
$stmt = $pdo->prepare("SELECT id, nombre, email FROM administradores");
$stmt->execute();
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($admins);

echo "\n--- Result (Inner Join) ---\n";
$stmtAdmins = $pdo->prepare("SELECT a.id, a.nombre, a.email, p.correo_institucional, p.correo_personal
         FROM administradores a 
         INNER JOIN cat_personal p ON (LOWER(p.correo_institucional) = LOWER(a.email) OR LOWER(p.correo_personal) = LOWER(a.email))
         WHERE a.activo = true AND p.cve_area = 6
         ORDER BY a.nombre ASC");
$stmtAdmins->execute();
$res = $stmtAdmins->fetchAll(PDO::FETCH_ASSOC);
print_r($res);
