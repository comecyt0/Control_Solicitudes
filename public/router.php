<?php
/**
 * COMECyT Control de Solicitudes
 * Router de Áreas Mágicas — v4.0
 * 
 * Evalúa a qué área pertenece el usuario y lo redirige a la carpeta correcta
 * de la arquitectura multidepartamental.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/auth.php';

// Debe estar logueado para usar el router
inicializarSesion();

if (empty($_SESSION['admin_id']) && empty($_SESSION['ss_id']) && empty($_SESSION['user_id'])) {
    redirigir('admin/login.php');
}

// Asegurar que si es Admin, tenga el flag de director actualizado (Fase 3)
if (!empty($_SESSION['admin_id'])) {
    verificarSesionAdmin();
}

// Fase 3: Los directores van al Hub de Jurisdicción por defecto, a menos que sea modo demo
if (($_SESSION['is_director'] ?? false) && !isset($_GET['demo_area'])) {
    header('Location: ' . BASE_URL . 'admin/jurisdiccion.php');
    exit;
}

$pdo = getConnection();
$cve_area = 0;
$rol = null;

// Determinar rol y obtener el cve_area de base de datos
if (!empty($_SESSION['admin_id'])) {
    $rol = 'admin';
    // Buscar cve_area a través de email en cat_personal
    $email = $_SESSION['admin_email'] ?? '';
    $stmt = $pdo->prepare("SELECT cve_area FROM cat_personal WHERE correo_institucional = ? OR correo_personal = ? LIMIT 1");
    $stmt->execute([$email, $email]);
    $cve_area = (int)$stmt->fetchColumn();
    // Si no tiene cve_area, forzamos sistemas (1)
    if (!$cve_area) $cve_area = 1;

} elseif (!empty($_SESSION['ss_id'])) {
    $rol = 'servicio_social';
    // Buscar la cve_area asociada al becario (por nombre de institucion mapeado, o por cve_area si se añade)
    $stmt = $pdo->prepare("SELECT COALESCE(cve_area, 1) FROM ss_usuarios WHERE id = ? LIMIT 1");
    $stmt->execute([$_SESSION['ss_id']]);
    $cve_area = (int)$stmt->fetchColumn();

} elseif (!empty($_SESSION['user_id'])) {
    $rol = 'usuario';
    $cve_area = (int)($_SESSION['user_cve_area'] ?? 1);
}

// MODO DEMO / HUB (v5.5): Los directores (admin o user) pueden navegar entre áreas usando demo_area
if (isset($_GET['demo_area']) && (($_SESSION['is_director'] ?? false) || !empty($_SESSION['admin_id']))) {
    if (is_numeric($_GET['demo_area'])) {
        $cve_area = (int)$_GET['demo_area'];
    }
}

// Convertir Clave de Área a Slug de Carpeta (Mapa Estricto según Fase 2)
$mapaAreas = [
    1  => 'admin', // 1: Unidad de Sistemas = carpeta "admin"
    2  => 'areas/direccion_general',
    3  => 'areas/secretaria_particular',
    4  => 'areas/organo_interno',
    5  => 'areas/quejas',
    6  => 'areas/responsabilidades',
    7  => 'areas/planeacion_evaluacion',
    8  => 'areas/investigacion_cientifica',
    9  => 'areas/apoyo_investigacion',
    10 => 'areas/formacion_rrhh',
    11 => 'areas/desarrollo_vinculacion',
    12 => 'areas/desarrollo_tecnologico',
    13 => 'areas/vinculacion',
    14 => 'areas/financiamiento_divulgacion',
    15 => 'areas/financiamiento',
    16 => 'areas/difusion',
    17 => 'areas/juridico_igualdad',
    18 => 'areas/administrativo',
    19 => 'areas/juridico',
    20 => 'areas/archivo'
];

$destino = $mapaAreas[$cve_area] ?? 'admin'; // Fallback a sistemas

// Guardamos en sesión el slug o área validada para chequeo en headers clónicos
$_SESSION['area_slug_activa'] = $destino === 'admin' ? 'sistemas' : explode('/', $destino)[1];
$_SESSION['cve_area_activa'] = $cve_area;

// Si el usuario es Personal Regular (rol 'usuario'), también le definimos el nombre del área para el UI
if ($rol === 'usuario') {
    $stmtN = $pdo->prepare("SELECT des_area FROM cat_areas WHERE cve_area = ? LIMIT 1");
    $stmtN->execute([$cve_area]);
    $_SESSION['user_area'] = (string)$stmtN->fetchColumn();
}

if ($rol === 'servicio_social' && $destino === 'admin') {
    // Si la carpeta es "admin", el servicio social tiene su propio panel "servicio_social/dashboard.php"
    redirigir('servicio_social/dashboard.php');
}

// Para el resto: redirigir a dashboard.php dentro de la carpeta mapeada
header("Location: ../" . $destino . "/dashboard.php");
exit;
