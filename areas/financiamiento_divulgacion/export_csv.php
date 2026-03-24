<?php
/**
 * COMECyT Control de Solicitudes
 * Panel de Administracion — Exportar CSV
 *
 * Genera un archivo CSV con todas las solicitudes registradas en la base de datos
 * para analisis externo (Excel, Python, etc).
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/auth.php';

verificarSesionAdmin();

$pdo = getConnection();

// Obtener todas las solicitudes
$stmt = $pdo->query(
    "SELECT 
        id, 
        folio, 
        tipo, 
        solicitante, 
        email_solicitante, 
        area, 
        prioridad, 
        estatus, 
        fecha_creacion, 
        fecha_actualizacion 
     FROM solicitudes 
     ORDER BY fecha_creacion DESC"
);
$solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Definir nombre del archivo con timestamp
$filename = "comecyt_solicitudes_" . date('Ymd_His') . ".csv";

// Configurar cabeceras HTTP para forzar la descarga como CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Abrir el stream de salida
$output = fopen('php://output', 'w');

// Anadir BOM para que Excel lea correctamente los caracteres UTF-8 (acentos, enes)
fputs($output, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));

// Escribir los encabezados de las columnas (Nombres amigables)
fputcsv($output, [
    'ID',
    'Folio',
    'Tipo',
    'Solicitante',
    'Email del Solicitante',
    'Area/Departamento',
    'Prioridad',
    'Estatus',
    'Fecha de Creacion',
    'Ultima Actualizacion'
], ',', '"', "\\");

// Recorrer los registros y escribirlos en el CSV
foreach ($solicitudes as $row) {
    // Transformar etiquetas e informacion para que sean legibles
    $tipo      = getEtiqueta('tipo', $row['tipo']);
    $prioridad = getEtiqueta('prioridad', $row['prioridad']);
    $estatus   = getEtiqueta('estatus', $row['estatus']);
    $creado    = formatearFecha($row['fecha_creacion']);
    $actual    = formatearFecha($row['fecha_actualizacion']);

    fputcsv($output, [
        $row['id'],
        $row['folio'],
        $tipo,
        $row['solicitante'],
        $row['email_solicitante'],
        $row['area'],
        $prioridad,
        $estatus,
        $creado,
        $actual
    ], ',', '"', "\\");
}

// Cerrar el stream
fclose($output);
exit;
