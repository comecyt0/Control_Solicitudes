<?php
/**
 * COMECyT — Logout Servicio Social
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/auth.php';

cerrarSesionSS();
header('Location: ' . BASE_URL . 'admin/login.php');
exit;
