<?php
/**
 * COMECyT Control de Solicitudes
 * Endpoint API para manejo de personal operativo.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../config/auth.php';

inicializarSesion();
verificarSesionAdmin();

$pdo = getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $jefe_id = (int) getParam('jefe_id');
    
    // Obtener todos los empleados activos, pero ordenados por área
    $stmt = $pdo->query("SELECT u.cve_personal as id, u.nombre, u.appat, u.apmat, u.correo_institucional, u.cve_area, a.des_area, u.jefe_directo_id 
                         FROM cat_personal u 
                         LEFT JOIN cat_areas a ON u.cve_area = a.cve_area 
                         WHERE u.activo = true AND u.cve_personal != $jefe_id 
                         ORDER BY a.des_area ASC, u.nombre ASC");
    $lista = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'data' => $lista]);
    exit;
}
