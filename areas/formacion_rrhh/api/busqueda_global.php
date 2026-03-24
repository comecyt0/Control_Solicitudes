<?php
/**
 * COMECyT Control de Solicitudes
 * API: Búsqueda Global
 *
 * GET ?q=texto → JSON con resultados agrupados de solicitudes, personal y equipos
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../config/auth.php';

verificarSesionAdmin();
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$q = trim($_GET['q'] ?? '');

if (mb_strlen($q) < 2) {
    echo json_encode(['ok' => true, 'resultados' => [], 'total' => 0]);
    exit;
}

$pdo  = getConnection();
$like = '%' . $q . '%';

// ── Solicitudes ──────────────────────────────────────────────────────
$stmtSol = $pdo->prepare(
    "SELECT id, folio, tipo, solicitante, area, estatus, fecha_creacion
     FROM solicitudes
     WHERE folio LIKE ? OR solicitante LIKE ? OR area LIKE ? OR descripcion LIKE ?
     ORDER BY fecha_creacion DESC
     LIMIT 8"
);
$stmtSol->execute([$like, $like, $like, $like]);
$solicitudes = $stmtSol->fetchAll();

// Formatear solicitudes para respuesta JSON
$solFormateadas = array_map(function ($s) {
    return [
        'id'       => (int) $s['id'],
        'folio'    => $s['folio'],
        'tipo'     => $s['tipo'],
        'label'    => $s['folio'] . ' — ' . $s['solicitante'],
        'sub'      => $s['area'] . ' · ' . ETIQUETAS_ESTATUS[$s['estatus']] ?? $s['estatus'],
        'estatus'  => $s['estatus'],
        'url'      => BASE_URL . 'admin/detalle.php?id=' . $s['id'],
        'tipo_res' => 'solicitud',
        'icono'    => 'fa-file-lines',
    ];
}, $solicitudes);

// ── Personal ─────────────────────────────────────────────────────────
$stmtPers = $pdo->prepare(
    "SELECT cve_personal, nombre, appat, apmat, COALESCE(correo_institucional, correo_personal) AS email, cve_area
     FROM cat_personal
     WHERE nombre LIKE ? OR appat LIKE ? OR apmat LIKE ?
        OR CONCAT(nombre, ' ', appat) LIKE ? OR correo_institucional LIKE ?
     LIMIT 5"
);
$stmtPers->execute([$like, $like, $like, $like, $like]);
$personal = $stmtPers->fetchAll();

$persFormateado = array_map(function ($p) {
    $nombre = trim($p['nombre'] . ' ' . $p['appat'] . ' ' . ($p['apmat'] ?? ''));
    return [
        'id'       => $p['cve_personal'],
        'label'    => $nombre,
        'sub'      => $p['email'] ?? 'Sin correo',
        'url'      => BASE_URL . 'admin/personal.php',
        'tipo_res' => 'personal',
        'icono'    => 'fa-id-card',
    ];
}, $personal);

// ── Equipos ───────────────────────────────────────────────────────────
$stmtEq = null;
$equipos = [];
try {
    $stmtEq = $pdo->prepare(
        "SELECT cve_bienes, marca, modelo, num_serie, num_inventario
         FROM sb_bienes
         WHERE marca LIKE ? OR modelo LIKE ? OR num_serie LIKE ? OR num_inventario LIKE ?
         LIMIT 5"
    );
    $stmtEq->execute([$like, $like, $like, $like]);
    $equiposRaw = $stmtEq->fetchAll();
    $equipos = array_map(function ($e) {
        return [
            'id'       => $e['cve_bienes'],
            'label'    => trim($e['marca'] . ' ' . $e['modelo']),
            'sub'      => 'Serie: ' . ($e['num_serie'] ?: 'N/D') . ' · Inv: ' . ($e['num_inventario'] ?: 'N/D'),
            'url'      => BASE_URL . 'admin/equipos.php',
            'tipo_res' => 'equipo',
            'icono'    => 'fa-laptop',
        ];
    }, $equiposRaw);
} catch (Throwable $e) {
    // Tabla puede no existir en este entorno — ignorar
}

$resultados = array_merge($solFormateadas, $persFormateado, $equipos);

echo json_encode([
    'ok'         => true,
    'resultados' => $resultados,
    'total'      => count($resultados),
    'grupos'     => [
        'solicitudes' => count($solFormateadas),
        'personal'    => count($persFormateado),
        'equipos'     => count($equipos),
    ],
]);
