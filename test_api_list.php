<?php
require_once __DIR__ . '/config/database.php';
$pdo = getConnection();
$sql = "SELECT s.* FROM sb_calendario_solicitudes s WHERE s.estatus = 'pendiente'";
$stmt = $pdo->query($sql);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Recuento simple: " . count($rows) . "\n";

$sql2 = "SELECT s.*, 
        COALESCE(u.nombre, a.nombre, 'Usuario Externo') as solicitante_nombre
        FROM sb_calendario_solicitudes s
        LEFT JOIN cat_personal u ON s.usuario_id = u.cve_personal
        LEFT JOIN administradores a ON s.usuario_id = a.id
        WHERE s.estatus = 'pendiente'
        ORDER BY s.creado_en DESC";
$stmt2 = $pdo->query($sql2);
$rows2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
echo "Recuento con JOIN: " . count($rows2) . "\n";
print_r($rows2[0] ?? 'Sin resultados');
