<?php
require_once __DIR__ . '/../../config/auth.php';
header('Content-Type: application/json');

session_start();
echo json_encode([
    'admin_id' => $_SESSION['admin_id'] ?? null,
    'user_id' => $_SESSION['user_id'] ?? null,
    'admin_cve_area' => $_SESSION['admin_cve_area'] ?? null,
    'user_cve_area' => $_SESSION['user_cve_area'] ?? null,
    'area_slug' => $_SESSION['area_slug_activa'] ?? null,
]);
