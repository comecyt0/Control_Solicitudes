<?php
/**
 * COMECyT Control de Solicitudes
 * Panel de Administracion — Cerrar Sesion
 *
 * Destruye la sesion activa y redirige al login.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/auth.php';

cerrarSesion();
redirigir('admin/login.php');
