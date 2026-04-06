<?php
/**
 * COMECyT Control de Solicitudes
 * Panel de Administracion — Calendario / Agenda Institucional
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../config/auth.php';

verificarSesionAdmin();

$pdo = getConnection();
$mensajeFlash = '';
$tipoFlash = '';

// -------------------------------------------------------
// Procesar acciones de Calendario (PRG)
// -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_accion'])) {
    validarCsrfPost();
    $accion = $_POST['_accion'];
    
    if ($accion === 'crear_evento') {
        $titulo = trim(postParam('titulo'));
        $descripcion = trim(postParam('descripcion'));
        $fechaInicio = postParam('fecha_inicio');
        $fechaFin = postParam('fecha_fin');
        $color = postParam('color', '#3788d8');
        
        if ($titulo && $fechaInicio && $fechaFin) {
            $publico = isset($_POST['publico']) ? 'TRUE' : 'FALSE';
            $stmt = $pdo->prepare("INSERT INTO eventos (titulo, descripcion, fecha_inicio, fecha_fin, color, creado_por, publico, cve_area) VALUES (?, ?, ?, ?, ?, ?, $publico, ?)");
            $stmt->execute([$titulo, $descripcion, $fechaInicio, $fechaFin, $color, $_SESSION['admin_id'], $_SESSION['admin_cve_area'] ?? $_SESSION['user_cve_area'] ?? 0]);
            header('Location: ' . BASE_URL . 'admin/calendario.php?flash=evento_creado');
            exit;
        } else {
            $mensajeFlash = "El título y las fechas son obligatorias.";
            $tipoFlash = "error";
        }
    } elseif ($accion === 'editar_evento') {
        $id = (int) postParam('evento_id');
        $titulo = trim(postParam('titulo'));
        $descripcion = trim(postParam('descripcion'));
        $fechaInicio = postParam('fecha_inicio');
        $fechaFin = postParam('fecha_fin');
        $color = postParam('color', '#3788d8');
        
        if ($id > 0 && $titulo && $fechaInicio && $fechaFin) {
            $publico = isset($_POST['publico']) ? 'TRUE' : 'FALSE';
            $stmt = $pdo->prepare("UPDATE eventos SET titulo = ?, descripcion = ?, fecha_inicio = ?, fecha_fin = ?, color = ?, publico = $publico WHERE id = ?");
            $stmt->execute([$titulo, $descripcion, $fechaInicio, $fechaFin, $color, $id]);
            header('Location: ' . BASE_URL . 'admin/calendario.php?flash=evento_editado');
            exit;
        } else {
            $mensajeFlash = "Faltan datos obligatorios para editar.";
            $tipoFlash = "error";
        }
    } elseif ($accion === 'eliminar_evento') {
        $id = (int) postParam('evento_id');
        if ($id > 0) {
            $stmt = $pdo->prepare("DELETE FROM eventos WHERE id = ?");
            $stmt->execute([$id]);
            header('Location: ' . BASE_URL . 'admin/calendario.php?flash=evento_eliminado');
            exit;
        }
    } elseif ($accion === 'crear_tarea') {
        $titulo = trim(postParam('titulo'));
        $descripcion = trim(postParam('descripcion'));
        $color = postParam('color', '#3788d8');
        $asignado_a = !empty($_POST['asignado_a']) ? (int)$_POST['asignado_a'] : null;
        
        if ($titulo) {
            $stmt = $pdo->prepare("INSERT INTO sb_kanban_tareas (titulo, descripcion, color, estatus, creado_por, asignado_a, cve_area) VALUES (?, ?, ?, 'pendiente', ?, ?, ?)");
            $stmt->execute([$titulo, $descripcion, $color, $_SESSION['admin_id'], $asignado_a, $_SESSION['admin_cve_area'] ?? $_SESSION['user_cve_area'] ?? 0]);
            header('Location: ' . BASE_URL . 'admin/calendario.php?flash=tarea_creada#kanban');
            exit;
        }
    } elseif ($accion === 'editar_tarea') {
        $id = (int) postParam('tarea_id');
        $titulo = trim(postParam('titulo'));
        $descripcion = trim(postParam('descripcion'));
        $color = postParam('color', '#3788d8');
        $asignado_a = !empty($_POST['asignado_a']) ? (int)$_POST['asignado_a'] : null;
        
        if ($id > 0 && $titulo) {
            $stmt = $pdo->prepare("UPDATE sb_kanban_tareas SET titulo = ?, descripcion = ?, color = ?, asignado_a = ? WHERE id = ?");
            $stmt->execute([$titulo, $descripcion, $color, $asignado_a, $id]);
            header('Location: ' . BASE_URL . 'admin/calendario.php?flash=tarea_editada#kanban');
            exit;
        }
    } elseif ($accion === 'mover_tarea') {
        $id = (int) postParam('tarea_id');
        $nuevoEstatus = postParam('nuevo_estatus');
        
        if ($id > 0 && in_array($nuevoEstatus, ['pendiente', 'en_proceso', 'completada'])) {
            $stmt = $pdo->prepare("UPDATE sb_kanban_tareas SET estatus = ? WHERE id = ?");
            $stmt->execute([$nuevoEstatus, $id]);
            header('Location: ' . BASE_URL . 'admin/calendario.php?flash=tarea_movida#kanban');
            exit;
        }
    } elseif ($accion === 'eliminar_tarea') {
        $id = (int) postParam('tarea_id');
        if ($id > 0) {
            $stmt = $pdo->prepare("DELETE FROM sb_kanban_tareas WHERE id = ?");
            $stmt->execute([$id]);
            header('Location: ' . BASE_URL . 'admin/calendario.php?flash=tarea_eliminada#kanban');
            exit;
        }
    }
}

// Leer flash redirect
$flashCode = getParam('flash');
if ($flashCode === 'evento_creado') {
    $mensajeFlash = "Evento guardado exitosamente.";
    $tipoFlash = "success";
} elseif ($flashCode === 'evento_editado') {
    $mensajeFlash = "Evento actualizado correctamente.";
    $tipoFlash = "success";
} elseif ($flashCode === 'evento_eliminado') {
    $mensajeFlash = "Evento eliminado del calendario.";
    $tipoFlash = "success";
} elseif ($flashCode === 'tarea_creada') {
    $mensajeFlash = "Tarea añadida al Kanban.";
    $tipoFlash = "success";
} elseif ($flashCode === 'tarea_editada') {
    $mensajeFlash = "Tarea actualizada exitosamente.";
    $tipoFlash = "success";
} elseif ($flashCode === 'tarea_movida') {
    $mensajeFlash = "Estatus de la tarea actualizado.";
    $tipoFlash = "success";
} elseif ($flashCode === 'tarea_eliminada') {
    $mensajeFlash = "Tarea eliminada definitivamente.";
    $tipoFlash = "success";
}

// -------------------------------------------------------
// Logica de Mes y Fechas (Vanilla PHP sin FullCalendar JS)
// -------------------------------------------------------
$hoy = new DateTime();
$mesStr = getParam('mes', $hoy->format('m'));
$anioStr = getParam('anio', $hoy->format('Y'));

$mes = (int)$mesStr;
$anio = (int)$anioStr;

if ($mes < 1 || $mes > 12) { $mes = (int)$hoy->format('m'); }
if ($anio < 2000 || $anio > 2100) { $anio = (int)$hoy->format('Y'); }

// Navegacion de meses
$dtMes = DateTime::createFromFormat('Y-m-d', sprintf('%04d-%02d-01', $anio, $mes));
$mesAnterior = (clone $dtMes)->modify('-1 month');
$mesSiguiente = (clone $dtMes)->modify('+1 month');

$diasEnMes = (int)$dtMes->format('t');
// 1 (Lunes) a 7 (Domingo)
$diaSemanaInicio = (int)$dtMes->format('N'); 

// -------------------------------------------------------
// Consultar eventos del mes
// Filtramos desde el d1 del mes actual al d1 del siguiente
// -------------------------------------------------------
$inicioMesBusqueda = $dtMes->format('Y-m-01 00:00:00');
$finMesBusqueda    = $mesSiguiente->format('Y-m-01 00:00:00');

$stmt = $pdo->prepare("SELECT * FROM eventos WHERE fecha_inicio < ? AND fecha_fin > ? AND (publico = TRUE OR cve_area = " . ($_SESSION['admin_cve_area'] ?? $_SESSION['user_cve_area'] ?? 0) . ") ORDER BY fecha_inicio ASC");
$stmt->execute([$finMesBusqueda, $inicioMesBusqueda]);
$eventosRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

$listaTareas = ['pendiente' => [], 'en_proceso' => [], 'completada' => []];
$rol = isset($_SESSION['admin_id']) ? 'admin' : '';

// Catalogo de Administradores para asignaciones
$stmtAdmins = $pdo->query("SELECT id, nombre FROM administradores WHERE activo = true ORDER BY nombre ASC");
$adminsDisponibles = $stmtAdmins->fetchAll(PDO::FETCH_ASSOC);

// Tareas con JOIN a administradores
$stmtT = $pdo->query("SELECT t.*, a.nombre AS asignado_nombre FROM sb_kanban_tareas t LEFT JOIN administradores a ON t.asignado_a = a.id WHERE t.cve_area = " . ($_SESSION['admin_cve_area'] ?? $_SESSION['user_cve_area'] ?? 0) . " ORDER BY t.estatus DESC, t.id DESC");
$tareas = $stmtT->fetchAll(PDO::FETCH_ASSOC);
foreach ($tareas as $t) {
    if (isset($listaTareas[$t['estatus']])) {
        $listaTareas[$t['estatus']][] = $t;
    } else {
        $listaTareas['pendiente'][] = $t;
    }
}

// Mapear eventos por dia (pueden cruzar varios dias, simplificaremos indexando por diaInicio)
$calendarioEventos = [];
foreach ($eventosRaw as $ev) {
    $dIni = new DateTime($ev['fecha_inicio']);
    $dia = (int)$dIni->format('d');
    
    if (!isset($calendarioEventos[$dia])) {
        $calendarioEventos[$dia] = [];
    }
    $calendarioEventos[$dia][] = $ev;
}

// Consultar cumpleanos del personal del mes actual
if ($mes > 0 && $mes <= 12) {
    $stmtB = $pdo->prepare(
        "SELECT TRIM(CONCAT(nombre, ' ', COALESCE(appat, ''), ' ', COALESCE(apmat, ''))) AS nombre, fecha_nacimiento, foto_perfil
         FROM cat_personal
         WHERE activo = TRUE
           AND fecha_nacimiento IS NOT NULL
           AND EXTRACT(MONTH FROM fecha_nacimiento) = :mes"
    );
    $stmtB->execute([':mes' => $mes]);
    $cumpleaneros = $stmtB->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cumpleaneros as $cp) {
        $diaCumple = (int) (new DateTime($cp['fecha_nacimiento']))->format('d');
        $nombreCompleto = trim($cp['nombre']);
        $calendarioEventos[$diaCumple][] = [
            'id' => null, 'titulo' => "\xF0\x9F\x8E\x82 " . $nombreCompleto,
            'descripcion' => 'Cumpleanos', 'color' => '#B19A6D',
            'fecha_inicio' => sprintf('%04d-%02d-%02d 00:00:00', $anio, $mes, $diaCumple),
            'fecha_fin'    => sprintf('%04d-%02d-%02d 23:59:59', $anio, $mes, $diaCumple),
            'publico' => false, 'es_cumple' => true,
            'foto_perfil'  => $cp['foto_perfil'] ?? null,
            'nombre_cumple'=> $nombreCompleto,
            'edad' => '',$edadAnios,
        ];
    }
}$mesesNombres = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
    7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];

$pageTitle = 'Calendario / Agenda';
$activeMenu = 'calendario';
$helpPage   = 'calendario';

// -------------------------------------------------------
// Estilos locales de la grid del calendario
// -------------------------------------------------------
$extraHead = '
<style>
/* Contenedor principal estilo App MacOS / iPadOS */
.calendar-wrapper {
    background: #ffffff;
    border-radius: 16px;
    box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.08), 0 4px 6px -4px rgba(0, 0, 0, 0.04);
    overflow: hidden;
    margin-top: 1.5rem;
    border: 1px solid rgba(0,0,0,0.05);
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
}

/* Encabezado del calendario */
.calendar-header-nav {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.25rem 2rem;
    background: #fdfdfd;
    border-bottom: 1px solid rgba(0,0,0,0.06);
}

.calendar-header-nav h3 {
    margin: 0; 
    font-size: 1.4rem; 
    font-weight: 700;
    color: var(--color-primary);
    letter-spacing: -0.01em;
}

/* Botonera de navegacion moderna */
.nav-btn-group {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}
.calendar-header-nav .btn-outline {
    border-radius: 8px;
    padding: 0.4rem 0.8rem;
    font-weight: 500;
    border: 1px solid #e2e8f0;
    color: #475569;
    background: white;
    box-shadow: 0 1px 2px rgba(0,0,0,0.02);
    transition: all 0.2s ease;
}
.calendar-header-nav .btn-outline:hover {
    background: #f8fafc;
    color: var(--color-primary);
    border-color: #cbd5e1;
}

/* Grid del calendario */
.calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    background: #f8fafc; /* Color de fondo de las lineas divisorias */
    gap: 1px; /* Gap de 1px crea los bordes perfectos */
}

/* Cabecera de dias */
.calendar-day-name {
    padding: 1rem 0.5rem;
    text-align: right;
    font-weight: 600;
    font-size: 0.8rem;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    background: #ffffff;
}

/* Celdas individuales */
.calendar-cell {
    min-height: 140px;
    padding: 0.5rem;
    background: #ffffff;
    cursor: pointer;
    transition: background 0.2s ease;
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

.calendar-cell:hover {
    background: #fdfdfd;
    box-shadow: inset 0 0 0 1px rgba(0,0,0,0.02);
}

.calendar-cell.empty {
    background: #f8fafc;
    cursor: default;
}

/* Numeros de dia */
.day-number {
    font-weight: 500;
    color: #334155;
    font-size: 1rem;
    margin-bottom: 0.25rem;
    display: inline-flex;
    justify-content: flex-end;
    align-self: flex-end;
    width: 28px;
    height: 28px;
    align-items: center;
    border-radius: 50%;
}

.calendar-cell.today .day-number {
    color: #fff;
    background: var(--color-primary);
    font-weight: 600;
}

/* 
 * ==========================================
 * ESTILO DE NOTAS ADHERIBLES (STICKY NOTES)
 * ==========================================
 */
.evento-pildora {
    font-size: 0.75rem;
    padding: 0.5rem 0.6rem;
    border-radius: 2px 2px 12px 2px; /* Efecto doblez esquina */
    color: #1e293b;
    margin-bottom: 0.25rem;
    cursor: pointer;
    position: relative;
    box-shadow: 
        2px 2px 4px rgba(0, 0, 0, 0.05),
        inset -10px -10px 20px rgba(0,0,0,0.03); /* Sombra interna para textura */
    transition: all 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275); /* Rebote en hover */
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    line-height: 1.3;
    animation: fadeIn 0.3s ease-in-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(4px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Efecto Hover tridimensional de Post-it */
.evento-pildora:hover {
    transform: scale(1.02) translateY(-2px) rotate(-1deg);
    box-shadow: 
        4px 6px 12px rgba(0, 0, 0, 0.1),
        inset -10px -10px 20px rgba(0,0,0,0.02);
    z-index: 10;
}

/* Evento Acciones Dropdown/Botones */
.evento-acciones {
    display: flex;
    gap: 4px;
    margin-top: 0;
    opacity: 0;
    max-height: 0;
    overflow: hidden;
    transition: all 0.2s ease;
}

.evento-pildora:hover .evento-acciones {
    opacity: 1;
    max-height: 30px;
    margin-top: 4px;
    padding-bottom: 2px;
}

.btn-evento-accion {
    flex: 1;
    background: rgba(255,255,255,0.7);
    border: none;
    border-radius: 4px;
    padding: 4px 0;
    cursor: pointer;
    font-size: 0.75rem;
    color: #475569;
    display: flex;
    justify-content: center;
    align-items: center;
    transition: all 0.2s ease;
}

.btn-evento-accion:hover {
    background: #ffffff;
    color: var(--color-primary);
    box-shadow: 0 1px 2px rgba(0,0,0,0.1);
}

/* Pliegue de pagina nativo usando pseudo-elementos */
.evento-pildora::after {
    content: "";
    position: absolute;
    bottom: 0;
    right: 0;
    border-width: 0 0 12px 12px;
    border-style: solid;
    border-color: rgba(0,0,0,0.06) white;
    box-shadow: -1px -1px 2px rgba(0,0,0,0.05);
    border-radius: 0 0 0 2px;
}

/* Titulo del Post-It */
.evento-pildora .evento-titulo {
    font-weight: 700;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Hora del Post-It */
.evento-pildora .evento-hora {
    font-size: 0.65rem;
    opacity: 0.75;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 3px;
}

/* Tema de colores apastelados para emular papel adherible real */
.nota-azul       { background: #e0f2fe; border-top: 3px solid #0284c7; }
.nota-verde      { background: #dcfce7; border-top: 3px solid #16a34a; }
.nota-dorado     { background: #fef08a; border-top: 3px solid #ca8a04; } /* Clasico post-it amarillo */
.nota-rojo       { background: #fee2e2; border-top: 3px solid #dc2626; }
.nota-gris       { background: #f1f5f9; border-top: 3px solid #64748b; }

/* Estilos extra para los modales */
.color-picker-label {
    cursor: pointer; 
    display: flex; 
    align-items: center; 
    gap: 8px;
    padding: 8px 14px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    transition: all 0.2s ease;
    font-size: 0.85rem;
    font-weight: 500;
    color: #475569;
    background: #ffffff;
}
.color-picker-label:hover { 
    background: #f8fafc; 
    border-color: #cbd5e1;
}
.color-picker-label input[type="radio"] { 
    display: none; 
}
.color-picker-label input[type="radio"]:checked + span.color-dot {
    box-shadow: 0 0 0 2px white, 0 0 0 4px var(--color-primary);
}

.color-dot { 
    width: 16px; 
    height: 16px; 
    border-radius: 4px; 
    display: inline-block; 
}

/* Enhancements for Modal form */
.modal {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}
.modal .form-label {
    font-weight: 600; 
    color: #334155;
}
.modal .form-control {
    border-radius: 8px;
    border: 1px solid #cbd5e1;
    background: #f8fafc;
    transition: all 0.2s ease;
}
.modal .form-control:focus {
    background: #ffffff;
    border-color: var(--color-primary);
    box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.1);
}

/* 
 * ==========================================
 * DISEÑO RESPONSIVO (DASHBOARD MOVIL)
 * ==========================================
 */
.day-header-container {
    display: flex;
    justify-content: flex-end;
}

/* 
 * ==========================================
 * KANBAN BOARD ESTILOS
 * ==========================================
 */
.kanban-board {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.5rem;
    padding: 1rem 0;
    align-items: start;
}

.kanban-col {
    background: #f1f5f9;
    border-radius: 8px;
    display: flex;
    flex-direction: column;
    min-height: 400px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
}

.kanban-col-header {
    padding: 1rem;
    font-weight: 700;
    font-size: 1.05rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border-radius: 8px 8px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.bg-pendiente { background-color: #3b82f6; color: #ffffff; }
.bg-en-proceso { background-color: #f59e0b; color: #ffffff; }
.bg-completada { background-color: #10b981; color: #ffffff; }

.kanban-badge {
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 700;
}
.bg-white { background: rgba(255,255,255,0.2) !important; color: white !important; }

.kanban-col-body {
    padding: 1rem;
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

/* Tarea Card (Drag and Drop) */
.tarea-card {
    background: #ffffff;
    border-radius: 6px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    border: 1px solid #e2e8f0;
    padding: 1rem;
    cursor: grab;
    position: relative;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    border-top: 5px solid transparent; 
    transition: transform 0.2s, box-shadow 0.2s;
}

.tarea-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 12px -3px rgba(0,0,0,0.1);
}

.tarea-card:active {
    cursor: grabbing;
    transform: scale(0.98);
}

.tarea-card.completada .evento-titulo {
    text-decoration: line-through;
    opacity: 0.7;
}

.tarea-card .evento-titulo {
    font-size: 0.95rem;
    font-weight: 600;
    color: #334155;
    margin: 0;
    white-space: normal;
    line-height: 1.4;
}

.tarea-card .tarea-acciones {
    display: flex;
    justify-content: flex-end;
    gap: 0.3rem;
    opacity: 0;
    transition: opacity 0.2s;
}

.tarea-card:hover .tarea-acciones {
    opacity: 1;
}

.tarea-acciones button {
    background: #f1f5f9;
    border: none;
    border-radius: 4px;
    color: #64748b;
    cursor: pointer;
    font-size: 0.8rem;
    padding: 6px 8px;
    transition: all 0.2s ease;
}

.tarea-acciones button:hover {
    background: #e2e8f0;
    color: var(--color-primary);
}
.tarea-acciones button.btn-danger:hover {
    background: #fee2e2;
    color: #ef4444;
}

.kanban-empty {
    text-align: center;
    font-size: 0.85rem;
    margin-top: 1rem;
    padding: 1rem;
    border: 2px dashed #cbd5e1;
    border-radius: 6px;
    color: #94a3b8;
}

.mobile-day-name {
    display: none;
    font-weight: 600;
    color: #64748b;
    font-size: 0.85rem;
    text-transform: uppercase;
}

@media (max-width: 768px) {
    .calendar-header-nav {
        flex-direction: column;
        gap: 1rem;
        align-items: flex-start;
    }
    
    .calendar-day-name {
        display: none; /* Ocultamos la cabecera de la grid en móvil */
    }
    
    .calendar-grid {
        display: flex;
        flex-direction: column;
        gap: 1rem;
        background: transparent;
        padding: 1rem;
    }
    
    .calendar-cell {
        min-height: auto;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.02);
    }
    
    .calendar-cell.empty {
        display: none; /* Ocultamos celdas vacías en móvil */
    }
    
    .day-number {
        align-self: flex-start;
        margin-bottom: 0.5rem;
    }
    
    .mobile-day-name {
        display: inline-block;
        margin-left: 0.5rem;
    }
    
    .day-header-container {
        justify-content: flex-start;
        border-bottom: 1px solid #f1f5f9;
        margin-bottom: 0.5rem;
        padding-bottom: 0.5rem;
    }

    .kanban-board {
        grid-template-columns: 1fr;
    }
}
</style>
';

require_once __DIR__ . '/../../includes/header_admin.php';
?>

<?php if ($mensajeFlash): ?>
<div class="alert alert-<?= esc($tipoFlash) ?>" data-auto-close="4000">
    <i class="fa-solid <?= $tipoFlash === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation' ?>"></i>
    <?= esc($mensajeFlash) ?>
</div>
<?php endif; ?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
    <h2><i class="fa-solid fa-calendar-days text-primary"></i> Calendario</h2>
    <div style="display: flex; gap: 10px;">
        <button class="btn btn-outline" id="btnVerSolicitudes" onclick="abrirModalSolicitudes()" style="position:relative;">
            <i class="fa-solid fa-bell"></i> Solicitudes 
            <span id="badgeSolicitudes" style="display:none; position:absolute; top:-8px; right:-8px; background:var(--color-danger); color:#fff; border-radius:50%; width:20px; height:20px; font-size:0.7rem; align-items:center; justify-content:center; border:2px solid #fff;">0</span>
        </button>
        <button class="btn btn-primary" onclick="abrirModalCrear()">
            <i class="fa-solid fa-plus"></i> Nuevo Evento
        </button>
    </div>
</div>

<div class="calendar-wrapper">
    <div class="calendar-header-nav">
        <h3 style="margin: 0; font-size: 1.4rem; font-weight: 700;">
            <?= $mesesNombres[$mes] ?> de <?= $anio ?>
        </h3>
        <div class="nav-btn-group">
            <a href="?mes=<?= $mesAnterior->format('m') ?>&anio=<?= $mesAnterior->format('Y') ?>" class="btn-outline">
                <i class="fa-solid fa-chevron-left"></i>
            </a>
            <a href="?mes=<?= date('m') ?>&anio=<?= date('Y') ?>" class="btn-outline">
                Hoy
            </a>
            <a href="?mes=<?= $mesSiguiente->format('m') ?>&anio=<?= $mesSiguiente->format('Y') ?>" class="btn-outline">
                <i class="fa-solid fa-chevron-right"></i>
            </a>
        </div>
    </div>

    <div class="calendar-grid">
        <!-- Nombres Días -->
        <div class="calendar-day-name">Lunes</div>
        <div class="calendar-day-name">Martes</div>
        <div class="calendar-day-name">Miércoles</div>
        <div class="calendar-day-name">Jueves</div>
        <div class="calendar-day-name">Viernes</div>
        <div class="calendar-day-name">Sábado</div>
        <div class="calendar-day-name">Domingo</div>

        <?php
        // Celdas vacias al inicio del mes
        for ($i = 1; $i < $diaSemanaInicio; $i++): ?>
            <div class="calendar-cell empty"></div>
        <?php endfor; ?>

        <?php
        $diaActualMes = (int)$hoy->format('d');
        $mesActualHoy = (int)$hoy->format('m');
        $anioActualHoy = (int)$hoy->format('Y');

        // Mapeo de hex a clases de notas
        $mapaColoresNotas = [
            '#3788d8' => 'nota-azul',
            '#16A34A' => 'nota-verde',
            '#B19A6D' => 'nota-dorado',
            '#DC2626' => 'nota-rojo',
            '#6B7280' => 'nota-gris'
        ];

        // Días de la semana para vista móvil
        $nombresDias = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];

        for ($dia = 1; $dia <= $diasEnMes; $dia++): 
            $esHoy = ($dia === $diaActualMes && $mes === $mesActualHoy && $anio === $anioActualHoy);
            
            // Format fecha for clic
            $fechaCellStr = sprintf('%04d-%02d-%02d', $anio, $mes, $dia);
            $fechaCellFormat1 = $fechaCellStr . 'T09:00';
            $fechaCellFormat2 = $fechaCellStr . 'T10:00';
            
            // Determinar día de la semana para la vista móvil
            $tsDay = strtotime($fechaCellStr);
            $dayOfWeekStr = $nombresDias[date('w', $tsDay)];
        ?>
            <div class="calendar-cell <?= $esHoy ? 'today' : '' ?>" onclick="abrirModalCrearDesdeCelda('<?= $fechaCellFormat1 ?>', '<?= $fechaCellFormat2 ?>')">
                <div class="day-header-container">
                    <span class="day-number"><?= $dia ?></span>
                    <span class="mobile-day-name"><?= $dayOfWeekStr ?></span>
                </div>
                
                <?php if (isset($calendarioEventos[$dia])): ?>
                    <?php foreach ($calendarioEventos[$dia] as $ev): ?>
                        <?php 
                            $claseNota = $mapaColoresNotas[$ev['color']] ?? 'nota-azul';
                        ?>
                         <div class="evento-pildora <?= $claseNota ?>" title="<?= esc($ev['titulo']) ?>">
                              <div class="evento-titulo">
                                  <?php if ($ev['publico']): ?>
                                      <i class="fa-solid fa-eye text-primary" title="Visible en Calendario Público"></i>
                                  <?php endif; ?>
                                  <?= esc($ev['titulo']) ?>
                              </div>
                              <div class="evento-hora">
                                  <i class="fa-regular fa-clock"></i> <?= date('H:i', strtotime($ev['fecha_inicio'])) ?>
                              </div>
                             <div class="evento-acciones">
                                 <button type="button" class="btn-evento-accion" title="Ver Nota"
                                         onclick="event.stopPropagation(); abrirModalVer(
                                             '<?= esc(addslashes($ev['titulo'])) ?>', 
                                             '<?= esc(addslashes($ev['descripcion'] ?? '')) ?>', 
                                             '<?= date('d/m/Y H:i', strtotime($ev['fecha_inicio'])) ?>', 
                                             '<?= date('d/m/Y H:i', strtotime($ev['fecha_fin'])) ?>'
                                         )">
                                     <i class="fa-solid fa-eye"></i>
                                 </button>
                                 <button type="button" class="btn-evento-accion" title="Editar Nota"
                                         onclick="event.stopPropagation(); abrirModalEditar(
                                             <?= $ev['id'] ?>, 
                                             '<?= esc(addslashes($ev['titulo'])) ?>', 
                                             '<?= esc(addslashes($ev['descripcion'] ?? '')) ?>', 
                                             '<?= date('Y-m-d\TH:i', strtotime($ev['fecha_inicio'])) ?>', 
                                             '<?= date('Y-m-d\TH:i', strtotime($ev['fecha_fin'])) ?>', 
                                             '<?= esc($ev['color']) ?>',
                                             <?= $ev['publico'] ? 1 : 0 ?>
                                         )">
                                     <i class="fa-solid fa-pen"></i>
                                 </button>
                             </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endfor; ?>

        <?php
        // Celdas vacias al final
        $totalCeldas = ($diaSemanaInicio - 1) + $diasEnMes;
        $celdasFaltantes = (7 - ($totalCeldas % 7)) % 7;
        for ($i = 0; $i < $celdasFaltantes; $i++): ?>
            <div class="calendar-cell empty"></div>
        <?php endfor; ?>
    </div>
</div>

<!-- KANBAN BOARD -->
<div class="card mb-16" id="kanban" style="margin-top: 24px;">
    <div class="card-header d-flex justify-between align-center" style="flex-wrap: wrap; gap: 12px;">
        <h2 class="card-title">
            <i class="fa-solid fa-clipboard-list"></i> Tablero de Tareas Kanban
        </h2>
        <button type="button" class="btn btn-primary btn-sm" onclick="abrirModalCrearTarea()">
            <i class="fa-solid fa-plus"></i> Nueva Tarea
        </button>
    </div>
    
    <div class="kanban-board">
        <!-- Columna Pendiente -->
        <div class="kanban-col" id="col_pendiente" ondrop="drop(event, 'pendiente')" ondragover="allowDrop(event)">
            <div class="kanban-col-header bg-pendiente">
                <span>Pendientes</span>
                <span class="kanban-badge bg-white text-blue"><?= count($listaTareas['pendiente']) ?></span>
            </div>
            <div class="kanban-col-body">
                <?php foreach ($listaTareas['pendiente'] as $t): ?>
                <div class="tarea-card" draggable="true" ondragstart="drag(event, <?= $t['id'] ?>)" style="border-top-color: <?= esc($t['color']) ?>;" onclick="abrirModalVerTarea(<?= $t['id'] ?>, '<?= esc(js_escape($t['titulo'])) ?>', '<?= esc(js_escape($t['descripcion'])) ?>', 'pendiente', '<?= esc(js_escape($t['asignado_nombre'] ?? '')) ?>')">
                    <div class="evento-titulo"><?= esc($t['titulo']) ?></div>
                    <?php if(!empty($t['asignado_nombre'])): ?>
                    <div style="font-size: 0.75rem; color: #64748b; margin-top: -4px;"><i class="fa-regular fa-user"></i> <?= esc($t['asignado_nombre']) ?></div>
                    <?php endif; ?>
                    <div class="tarea-acciones" onclick="event.stopPropagation();">
                        <button type="button" title="Ver" onclick="abrirModalVerTarea(<?= $t['id'] ?>, '<?= esc(js_escape($t['titulo'])) ?>', '<?= esc(js_escape($t['descripcion'])) ?>', 'pendiente', '<?= esc(js_escape($t['asignado_nombre'] ?? '')) ?>')"><i class="fa-solid fa-eye"></i></button>
                        <button type="button" title="Editar" onclick="abrirModalEditarTarea(<?= $t['id'] ?>, '<?= esc(js_escape($t['titulo'])) ?>', '<?= esc(js_escape($t['descripcion'])) ?>', '<?= esc($t['color']) ?>', '<?= $t['asignado_a'] ?>')"><i class="fa-solid fa-pen"></i></button>
                        <button type="button" class="btn-danger" title="Eliminar" onclick="eliminarTarea(<?= $t['id'] ?>)"><i class="fa-solid fa-trash"></i></button>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if(empty($listaTareas['pendiente'])): ?>
                    <div class="text-muted kanban-empty">Arrastra tareas aquí</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Columna En Proceso -->
        <div class="kanban-col" id="col_en_proceso" ondrop="drop(event, 'en_proceso')" ondragover="allowDrop(event)">
            <div class="kanban-col-header bg-en-proceso">
                <span>En Proceso</span>
                <span class="kanban-badge bg-white text-orange"><?= count($listaTareas['en_proceso']) ?></span>
            </div>
            <div class="kanban-col-body">
                <?php foreach ($listaTareas['en_proceso'] as $t): ?>
                <div class="tarea-card" draggable="true" ondragstart="drag(event, <?= $t['id'] ?>)" style="border-top-color: <?= esc($t['color']) ?>;" onclick="abrirModalVerTarea(<?= $t['id'] ?>, '<?= esc(js_escape($t['titulo'])) ?>', '<?= esc(js_escape($t['descripcion'])) ?>', 'en_proceso', '<?= esc(js_escape($t['asignado_nombre'] ?? '')) ?>')">
                    <div class="evento-titulo"><?= esc($t['titulo']) ?></div>
                    <?php if(!empty($t['asignado_nombre'])): ?>
                    <div style="font-size: 0.75rem; color: #64748b; margin-top: -4px;"><i class="fa-regular fa-user"></i> <?= esc($t['asignado_nombre']) ?></div>
                    <?php endif; ?>
                    <div class="tarea-acciones" onclick="event.stopPropagation();">
                        <button type="button" title="Ver" onclick="abrirModalVerTarea(<?= $t['id'] ?>, '<?= esc(js_escape($t['titulo'])) ?>', '<?= esc(js_escape($t['descripcion'])) ?>', 'en_proceso', '<?= esc(js_escape($t['asignado_nombre'] ?? '')) ?>')"><i class="fa-solid fa-eye"></i></button>
                        <button type="button" title="Editar" onclick="abrirModalEditarTarea(<?= $t['id'] ?>, '<?= esc(js_escape($t['titulo'])) ?>', '<?= esc(js_escape($t['descripcion'])) ?>', '<?= esc($t['color']) ?>', '<?= $t['asignado_a'] ?>')"><i class="fa-solid fa-pen"></i></button>
                        <button type="button" class="btn-danger" title="Eliminar" onclick="eliminarTarea(<?= $t['id'] ?>)"><i class="fa-solid fa-trash"></i></button>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if(empty($listaTareas['en_proceso'])): ?>
                    <div class="text-muted kanban-empty">Arrastra tareas aquí</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Columna Completada -->
        <div class="kanban-col" id="col_completada" ondrop="drop(event, 'completada')" ondragover="allowDrop(event)">
            <div class="kanban-col-header bg-completada">
                <span>Completadas</span>
                <span class="kanban-badge bg-white text-green"><?= count($listaTareas['completada']) ?></span>
            </div>
            <div class="kanban-col-body">
                <?php foreach ($listaTareas['completada'] as $t): ?>
                <div class="tarea-card completada" draggable="true" ondragstart="drag(event, <?= $t['id'] ?>)" style="border-top-color: <?= esc($t['color']) ?>;" onclick="abrirModalVerTarea(<?= $t['id'] ?>, '<?= esc(js_escape($t['titulo'])) ?>', '<?= esc(js_escape($t['descripcion'])) ?>', 'completada', '<?= esc(js_escape($t['asignado_nombre'] ?? '')) ?>')">
                    <div class="evento-titulo"><?= esc($t['titulo']) ?></div>
                    <?php if(!empty($t['asignado_nombre'])): ?>
                    <div style="font-size: 0.75rem; color: #64748b; margin-top: -4px;"><i class="fa-regular fa-user"></i> <?= esc($t['asignado_nombre']) ?></div>
                    <?php endif; ?>
                    <div class="tarea-acciones" onclick="event.stopPropagation();">
                        <button type="button" title="Ver" onclick="abrirModalVerTarea(<?= $t['id'] ?>, '<?= esc(js_escape($t['titulo'])) ?>', '<?= esc(js_escape($t['descripcion'])) ?>', 'completada', '<?= esc(js_escape($t['asignado_nombre'] ?? '')) ?>')"><i class="fa-solid fa-eye"></i></button>
                        <button type="button" title="Editar" onclick="abrirModalEditarTarea(<?= $t['id'] ?>, '<?= esc(js_escape($t['titulo'])) ?>', '<?= esc(js_escape($t['descripcion'])) ?>', '<?= esc($t['color']) ?>', '<?= $t['asignado_a'] ?>')"><i class="fa-solid fa-pen"></i></button>
                        <button type="button" class="btn-danger" title="Eliminar" onclick="eliminarTarea(<?= $t['id'] ?>)"><i class="fa-solid fa-trash"></i></button>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if(empty($listaTareas['completada'])): ?>
                    <div class="text-muted kanban-empty">Arrastra tareas aquí</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Formulario Oculto para Mover/Eliminar Tarea -->
<form method="POST" action="" id="formAccionTarea" style="display:none;">
    <?= csrfField() ?>
    <input type="hidden" name="_accion" id="t_accion">
    <input type="hidden" name="tarea_id" id="t_tarea_id">
    <input type="hidden" name="nuevo_estatus" id="t_nuevo_estatus">
</form>

<!-- Modal: Crear Evento -->
<div class="modal-backdrop" id="modalCrearEvento">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fa-solid fa-calendar-plus"></i> Nuevo Evento
            </h3>
            <button type="button" class="modal-close" onclick="cerrarModal('modalCrearEvento')">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form method="POST" action="" autocomplete="off">
            <?= csrfField() ?>
            <input type="hidden" name="_accion" value="crear_evento">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Título del Evento <span class="required">*</span></label>
                    <input type="text" name="titulo" class="form-control" required placeholder="Ej. Reunión Mensual">
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Fecha y Hora Inicio <span class="required">*</span></label>
                        <input type="datetime-local" name="fecha_inicio" id="c_fecha_inicio" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Fecha y Hora Fin <span class="required">*</span></label>
                        <input type="datetime-local" name="fecha_fin" id="c_fecha_fin" class="form-control" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Descripción o Notas (Opcional)</label>
                    <textarea name="descripcion" class="form-control" rows="3" placeholder="Detalles de agenda..."></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Color (Categoría)</label>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <label class="color-picker-label">
                            <input type="radio" name="color" value="#3788d8" checked> <span class="color-dot" style="background:#3788d8;"></span> Reunión
                        </label>
                        <label class="color-picker-label">
                            <input type="radio" name="color" value="#16A34A"> <span class="color-dot" style="background:#16A34A;"></span> Conferencia
                        </label>
                        <label class="color-picker-label">
                            <input type="radio" name="color" value="#B19A6D"> <span class="color-dot" style="background:#B19A6D;"></span> Interno
                        </label>
                        <label class="color-picker-label">
                            <input type="radio" name="color" value="#DC2626"> <span class="color-dot" style="background:#DC2626;"></span> Urgente
                        </label>
                        <label class="color-picker-label">
                            <input type="radio" name="color" value="#6B7280"> <span class="color-dot" style="background:#6B7280;"></span> Nota
                        </label>
                    </div>
                </div>
                <div class="form-group" style="margin-top: 1rem; padding: 12px; background: #e0f2fe; border-radius: 8px; display: flex; align-items: center; gap: 10px;">
                    <input type="checkbox" name="publico" id="c_publico" style="width: 18px; height: 18px; cursor: pointer;">
                    <label for="c_publico" style="margin: 0; cursor: pointer; font-weight: 600; color: #0369a1; font-size: 0.9rem;">
                        <i class="fa-solid fa-earth-americas"></i> Mostrar en Calendario Público
                    </label>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="cerrarModal('modalCrearEvento')">Cancelar</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Guardar Evento</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Ver Evento (Sólo lectura) -->
<div class="modal-backdrop" id="modalVerEvento">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fa-solid fa-calendar-day"></i> <span id="v_titulo">Detalle de Evento</span>
            </h3>
            <button type="button" class="modal-close" onclick="cerrarModal('modalVerEvento')">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="modal-body">
            <div style="margin-bottom: 1rem;">
                <p style="margin: 0; font-size: 0.85rem; color: #64748b; font-weight: 600;">Fecha y Hora</p>
                <p style="margin: 0.2rem 0; font-weight: 500;"><i class="fa-regular fa-clock text-accent"></i> <span id="v_horario"></span></p>
            </div>
            <div>
                <p style="margin: 0; font-size: 0.85rem; color: #64748b; font-weight: 600;">Descripción</p>
                <div style="margin-top: 0.4rem; padding: 1rem; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;">
                    <p style="margin: 0; white-space: pre-wrap; color: #334155; line-height: 1.5;" id="v_descripcion"></p>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-primary" onclick="cerrarModal('modalVerEvento')">Cerrar</button>
        </div>
    </div>
</div>

<!-- Modal: Editar / Eliminar Evento -->
<div class="modal-backdrop" id="modalEditarEvento">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fa-solid fa-pen-to-square"></i> Detalle de Evento
            </h3>
            <button type="button" class="modal-close" onclick="cerrarModal('modalEditarEvento')">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form method="POST" action="" autocomplete="off" id="formEditarEvento">
            <?= csrfField() ?>
            <input type="hidden" name="_accion" value="editar_evento" id="e_accion">
            <input type="hidden" name="evento_id" id="e_evento_id">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Título del Evento <span class="required">*</span></label>
                    <input type="text" name="titulo" id="e_titulo" class="form-control" required>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Fecha y Hora Inicio <span class="required">*</span></label>
                        <input type="datetime-local" name="fecha_inicio" id="e_fecha_inicio" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Fecha y Hora Fin <span class="required">*</span></label>
                        <input type="datetime-local" name="fecha_fin" id="e_fecha_fin" class="form-control" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Descripción o Notas</label>
                    <textarea name="descripcion" id="e_descripcion" class="form-control" rows="3"></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Color</label>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <label class="color-picker-label">
                            <input type="radio" name="color" value="#3788d8"> <span class="color-dot" style="background:#3788d8;"></span> Reunión
                        </label>
                        <label class="color-picker-label">
                            <input type="radio" name="color" value="#16A34A"> <span class="color-dot" style="background:#16A34A;"></span> Conferencia
                        </label>
                        <label class="color-picker-label">
                            <input type="radio" name="color" value="#B19A6D"> <span class="color-dot" style="background:#B19A6D;"></span> Interno
                        </label>
                        <label class="color-picker-label">
                            <input type="radio" name="color" value="#DC2626"> <span class="color-dot" style="background:#DC2626;"></span> Urgente
                        </label>
                        <label class="color-picker-label">
                            <input type="radio" name="color" value="#6B7280"> <span class="color-dot" style="background:#6B7280;"></span> Nota
                        </label>
                    </div>
                </div>
                <div class="form-group" style="margin-top: 1rem; padding: 12px; background: #e0f2fe; border-radius: 8px; display: flex; align-items: center; gap: 10px;">
                    <input type="checkbox" name="publico" id="e_publico" style="width: 18px; height: 18px; cursor: pointer;">
                    <label for="e_publico" style="margin: 0; cursor: pointer; font-weight: 600; color: #0369a1; font-size: 0.9rem;">
                        <i class="fa-solid fa-earth-americas"></i> Mostrar en Calendario Público
                    </label>
                </div>
            </div>
            <div class="modal-footer" style="display: flex; justify-content: space-between;">
                <button type="button" class="btn btn-outline" style="color:var(--color-danger); border-color:var(--color-danger);" onclick="eliminarEvento()">
                    <i class="fa-solid fa-trash"></i> Eliminar
                </button>
                <div>
                    <button type="button" class="btn btn-outline" onclick="cerrarModal('modalEditarEvento')">Cerrar</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Actualizar</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Ver Tarea (Sólo lectura) -->
<div class="modal-backdrop" id="modalVerTarea">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fa-solid fa-clipboard-check text-primary"></i> <span id="vt_titulo">Detalle de Tarea</span>
            </h3>
            <button type="button" class="modal-close" onclick="cerrarModal('modalVerTarea')">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="modal-body">
            <div>
                <p style="margin: 0; font-size: 0.85rem; color: #64748b; font-weight: 600;">Descripción</p>
                <div style="margin-top: 0.4rem; padding: 1rem; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;">
                    <p style="margin: 0; white-space: pre-wrap; color: #334155; line-height: 1.5;" id="vt_descripcion"></p>
                </div>
            </div>
            <div style="margin-top: 1rem;">
                <p style="margin: 0; font-size: 0.85rem; color: #64748b; font-weight: 600;">Estatus Actual</p>
                <p style="margin: 0.2rem 0; font-weight: 500;" id="vt_estatus"></p>
            </div>
            <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #e2e8f0;">
                <p style="margin: 0; font-size: 0.85rem; color: #64748b; font-weight: 600;">Asignado A</p>
                <p style="margin: 0.2rem 0; font-weight: 500; display: flex; align-items: center; gap: 0.5rem;" id="vt_asignado"><i class="fa-regular fa-user text-accent"></i> <span></span></p>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-primary" onclick="cerrarModal('modalVerTarea')">Cerrar</button>
        </div>
    </div>
</div>

<!-- Modal: Crear Tarea -->
<div class="modal-backdrop" id="modalCrearTarea">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fa-solid fa-plus"></i> Nueva Tarea Kanban
            </h3>
            <button type="button" class="modal-close" onclick="cerrarModal('modalCrearTarea')">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form method="POST" action="">
            <?= csrfField() ?>
            <input type="hidden" name="_accion" value="crear_tarea">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Título de la Tarea <span class="required">*</span></label>
                    <input type="text" name="titulo" class="form-control" required placeholder="Ej. Realizar mantenimiento servidor 03">
                </div>
                <div class="form-group">
                    <label class="form-label">Asignar a (Opcional)</label>
                    <select name="asignado_a" class="form-control">
                        <option value="">-- Sin Asignar --</option>
                        <?php foreach ($adminsDisponibles as $adm): ?>
                            <option value="<?= $adm['id'] ?>"><?= esc($adm['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Descripción</label>
                    <textarea name="descripcion" class="form-control" rows="3" placeholder="(Opcional) Proveer detalles adicionales de la tarea"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Color del Post-It</label>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <label class="color-picker-label"><input type="radio" name="color" value="#3788d8" checked> <span class="color-dot" style="background:#3788d8;"></span></label>
                        <label class="color-picker-label"><input type="radio" name="color" value="#16A34A"> <span class="color-dot" style="background:#16A34A;"></span></label>
                        <label class="color-picker-label"><input type="radio" name="color" value="#eab308"> <span class="color-dot" style="background:#eab308;"></span></label>
                        <label class="color-picker-label"><input type="radio" name="color" value="#f97316"> <span class="color-dot" style="background:#f97316;"></span></label>
                        <label class="color-picker-label"><input type="radio" name="color" value="#8b5cf6"> <span class="color-dot" style="background:#8b5cf6;"></span></label>
                        <label class="color-picker-label"><input type="radio" name="color" value="#ec4899"> <span class="color-dot" style="background:#ec4899;"></span></label>
                        <label class="color-picker-label"><input type="radio" name="color" value="#475569"> <span class="color-dot" style="background:#475569;"></span></label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="cerrarModal('modalCrearTarea')">Cancelar</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Guardar Tarea</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Editar Tarea -->
<div class="modal-backdrop" id="modalEditarTarea">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fa-solid fa-pen"></i> Editar Tarea Kanban
            </h3>
            <button type="button" class="modal-close" onclick="cerrarModal('modalEditarTarea')">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form method="POST" action="">
            <?= csrfField() ?>
            <input type="hidden" name="_accion" value="editar_tarea">
            <input type="hidden" name="tarea_id" id="et_id">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Título de la Tarea <span class="required">*</span></label>
                    <input type="text" name="titulo" id="et_titulo" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Asignar a</label>
                    <select name="asignado_a" id="et_asignado_a" class="form-control">
                        <option value="">-- Sin Asignar --</option>
                        <?php foreach ($adminsDisponibles as $adm): ?>
                            <option value="<?= $adm['id'] ?>"><?= esc($adm['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Descripción</label>
                    <textarea name="descripcion" id="et_descripcion" class="form-control" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Color del Post-It</label>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;" id="et_color_radios">
                        <label class="color-picker-label"><input type="radio" name="color" value="#3788d8"> <span class="color-dot" style="background:#3788d8;"></span></label>
                        <label class="color-picker-label"><input type="radio" name="color" value="#16A34A"> <span class="color-dot" style="background:#16A34A;"></span></label>
                        <label class="color-picker-label"><input type="radio" name="color" value="#eab308"> <span class="color-dot" style="background:#eab308;"></span></label>
                        <label class="color-picker-label"><input type="radio" name="color" value="#f97316"> <span class="color-dot" style="background:#f97316;"></span></label>
                        <label class="color-picker-label"><input type="radio" name="color" value="#8b5cf6"> <span class="color-dot" style="background:#8b5cf6;"></span></label>
                        <label class="color-picker-label"><input type="radio" name="color" value="#ec4899"> <span class="color-dot" style="background:#ec4899;"></span></label>
                        <label class="color-picker-label"><input type="radio" name="color" value="#475569"> <span class="color-dot" style="background:#475569;"></span></label>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="display: flex; justify-content: space-between;">
                <button type="button" class="btn btn-outline" style="color:var(--color-danger); border-color:var(--color-danger);" onclick="eliminarTareaDesdeModal()">
                    <i class="fa-solid fa-trash"></i> Eliminar
                </button>
                <div>
                    <button type="button" class="btn btn-outline" onclick="cerrarModal('modalEditarTarea')">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Actualizar</button>
                </div>
            </div>
        </form>
    </div>
</div>
<!-- Modal: Gestionar Solicitudes -->
<div class="modal-backdrop" id="modalSolicitudesCalendario">
    <div class="modal" style="max-width: 700px;">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fa-solid fa-calendar-check text-primary"></i> Solicitudes de Espacio Pendientes
            </h3>
            <button type="button" class="modal-close" onclick="cerrarModal('modalSolicitudesCalendario')">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="modal-body">
            <div id="listaSolicitudesPendientes" style="max-height: 450px; overflow-y: auto;">
                <!-- Cargado via AJAX -->
                <p style="text-align:center; padding:20px; color:#64748b;"><i class="fa-solid fa-spinner fa-spin"></i> Cargando...</p>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="cerrarModal('modalSolicitudesCalendario')">Cerrar</button>
        </div>
    </div>
</div>

<!-- Modal: Detalle/Procesar Solicitud -->
<div class="modal-backdrop" id="modalProcesarSolicitud">
    <div class="modal" style="max-width: 450px;">
        <div class="modal-header">
            <h3 class="modal-title" id="ps_titulo">Procesar Solicitud</h3>
            <button type="button" class="modal-close" onclick="cerrarModal('modalProcesarSolicitud')">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="ps_id">
            <div id="ps_info" style="margin-bottom: 15px; font-size: 0.9rem; line-height: 1.4;">
                <!-- Info detallada -->
            </div>
            <div class="form-group">
                <label class="form-label">Motivo (en caso de rechazo)</label>
                <textarea id="ps_motivo" class="form-control" rows="2" placeholder="Explique por qué no se puede agendar el espacio..."></textarea>
            </div>
        </div>
        <div class="modal-footer" style="display: flex; gap: 10px;">
            <button type="button" class="btn btn-danger" style="flex:1;" onclick="gestionarSolicitud('rechazado')">Rechazar</button>
            <button type="button" class="btn btn-primary" style="flex:1;" onclick="gestionarSolicitud('aceptado')">Aceptar y Agendar</button>
        </div>
    </div>
</div>

<script>
function abrirModalCrear() {
    abrirModal('modalCrearEvento');
}

function abrirModalCrearDesdeCelda(dInicio, dFin) {
    document.getElementById('c_fecha_inicio').value = dInicio;
    document.getElementById('c_fecha_fin').value = dFin;
    abrirModal('modalCrearEvento');
}

function abrirModalVer(titulo, desc, ini, fin) {
    document.getElementById('v_titulo').textContent = titulo;
    document.getElementById('v_horario').textContent = ini + ' - ' + fin;
    document.getElementById('v_descripcion').textContent = desc || 'Sin detalles adicionales registrados.';
    abrirModal('modalVerEvento');
}

function abrirModalEditar(id, titulo, desc, ini, fin, color, publico) {
    document.getElementById('e_evento_id').value = id;
    document.getElementById('e_titulo').value = titulo;
    document.getElementById('e_descripcion').value = desc;
    document.getElementById('e_fecha_inicio').value = ini;
    document.getElementById('e_fecha_fin').value = fin;
    document.getElementById('e_accion').value = 'editar_evento';
    document.getElementById('e_publico').checked = (publico == 1);
    
    let colorRadios = document.querySelectorAll('#formEditarEvento input[name="color"]');
    colorRadios.forEach(radio => {
        if (radio.value === color) {
            radio.checked = true;
        }
    });
    
    abrirModal('modalEditarEvento');
}

function verDetalleEvento(t, d, h1, h2) {
    COMECyTUI.info(`${d}\n\nHorario: ${h1} - ${h2}`, t);
}

function eliminarEvento() {
    mostrarConfirmacionPersonalizada(
        "¿Borrar Evento?", 
        "¿Quieres borrar este evento de tu agenda?", 
        () => {
            setTimeout(() => {
                mostrarConfirmacionPersonalizada(
                    "⚠️ Acción Irreversible", 
                    "¿Estás SEGURO? Esta acción borrará el evento de forma permanente en la base de datos.", 
                    () => {
                        document.getElementById('e_accion').value = 'eliminar_evento';
                        document.getElementById('formEditarEvento').submit();
                    }, 
                    true
                );
            }, 150);
        }, 
        false
    );
}

// ==========================================
// SCRIPTS DE KANBAN
// ==========================================

function allowDrop(ev) {
    ev.preventDefault();
}

function drag(ev, id) {
    ev.dataTransfer.setData("text", id);
}

function drop(ev, nuevoEstatus) {
    ev.preventDefault();
    const id = ev.dataTransfer.getData("text");
    moverTarea(id, nuevoEstatus);
}

function abrirModalCrearTarea() {
    abrirModal('modalCrearTarea');
}

function abrirModalVerTarea(id, titulo, desc, estatus, asignado_nombre) {
    document.getElementById('vt_titulo').textContent = titulo;
    document.getElementById('vt_descripcion').textContent = desc || 'Sin detalles adicionales registrados.';
    let estatusStr = '';
    if (estatus === 'pendiente') estatusStr = 'Pendiente';
    if (estatus === 'en_proceso') estatusStr = 'En Proceso';
    if (estatus === 'completada') estatusStr = 'Completada';
    document.getElementById('vt_estatus').textContent = estatusStr;
    document.querySelector('#vt_asignado span').textContent = asignado_nombre || 'Sin Asignar';
    abrirModal('modalVerTarea');
}

function abrirModalEditarTarea(id, titulo, desc, color, asignado_id) {
    document.getElementById('et_id').value = id;
    document.getElementById('et_titulo').value = titulo;
    document.getElementById('et_descripcion').value = desc;
    document.getElementById('et_asignado_a').value = asignado_id || '';
    
    let colorRadios = document.querySelectorAll('#et_color_radios input[name="color"]');
    colorRadios.forEach(radio => {
        if (radio.value === color) {
            radio.checked = true;
        }
    });
    
    abrirModal('modalEditarTarea');
}

function moverTarea(id, nuevoEstatus) {
    document.getElementById('t_accion').value = 'mover_tarea';
    document.getElementById('t_tarea_id').value = id;
    document.getElementById('t_nuevo_estatus').value = nuevoEstatus;
    document.getElementById('formAccionTarea').submit();
}

function eliminarTarea(id) {
    mostrarConfirmacionPersonalizada("¿Borrar Tarea?", "¿Seguro que deseas eliminar esta tarea del Kanban permanentemente?", () => {
        document.getElementById('t_accion').value = 'eliminar_tarea';
        document.getElementById('t_tarea_id').value = id;
        document.getElementById('formAccionTarea').submit();
    }, true);
}

function eliminarTareaDesdeModal() {
    const id = document.getElementById('et_id').value;
    eliminarTarea(id);
}

// ==========================================
// SCRIPTS DE SOLICITUDES DE CALENDARIO
// ==========================================
let solicitudesCache = [];

function abrirModalSolicitudes() {
    abrirModal('modalSolicitudesCalendario');
    cargarSolicitudes();
}

function cargarSolicitudes() {
    const zona = document.getElementById('listaSolicitudesPendientes');
    fetch('api/calendario_solicitudes.php?accion=listar_pendientes')
        .then(r => {
            if (!r.ok) throw new Error('API Response Error: ' + r.status);
            return r.json();
        })
        .then(d => {
            if (!d.ok) {
                console.error('API Error:', d.error);
                return;
            }
            solicitudesCache = d.solicitudes;
            actualizarBadgeSolicitudes(solicitudesCache.length);
            
            if (solicitudesCache.length === 0) {
                zona.innerHTML = '<p style="text-align:center; padding:40px; color:#94a3b8;">No hay solicitudes pendientes.</p>';
                return;
            }

            let html = '<div style="display:flex; flex-direction:column; gap:12px;">';
            solicitudesCache.forEach(s => {
                html += `
                    <div style="padding:15px; border:1px solid #e2e8f0; border-radius:10px; background:#f8fafc; display:flex; justify-content:space-between; align-items:center;">
                        <div style="flex:1; min-width:0;">
                            <div style="font-weight:700; color:#334155; margin-bottom:2px;">${escapeHtml(s.titulo)}</div>
                            <div style="font-size:0.75rem; color:#64748b;">
                                <i class="fa-solid fa-user"></i> ${escapeHtml(s.solicitante_nombre)} | 
                                <i class="fa-solid fa-calendar"></i> ${formatFechaDisplay(s.fecha_inicio)}
                            </div>
                        </div>
                        <button class="btn btn-sm btn-outline" onclick="abrirDetalleSolicitud(${s.id})">Revisar</button>
                    </div>
                `;
            });
            html += '</div>';
            zona.innerHTML = html;
        })
        .catch(err => {
            console.error('Fetch error:', err);
            zona.innerHTML = '<p style="text-align:center; padding:40px; color:#ef4444;">Error al cargar solicitudes. Verifique su sesión.</p>';
        });
}

function actualizarBadgeSolicitudes(n) {
    const b = document.getElementById('badgeSolicitudes');
    if (n > 0) {
        b.textContent = n;
        b.style.display = 'flex';
    } else {
        b.style.display = 'none';
    }
}

function abrirDetalleSolicitud(id) {
    const s = solicitudesCache.find(x => parseInt(x.id) === id);
    if (!s) return;
    
    document.getElementById('ps_id').value = s.id;
    document.getElementById('ps_titulo').textContent = s.titulo;
    document.getElementById('ps_info').innerHTML = `
        <p><strong>Solicitante:</strong> ${escapeHtml(s.solicitante_nombre)}</p>
        <p><strong>Inicio:</strong> ${formatFechaDisplay(s.fecha_inicio)}</p>
        <p><strong>Fin:</strong> ${formatFechaDisplay(s.fecha_fin)}</p>
        <div style="background:#fff; border:1px solid #e2e8f0; padding:10px; border-radius:6px; margin-top:10px;">
            <strong>Descripción:</strong><br>${escapeHtml(s.descripcion || 'Sin descripción')}
        </div>
    `;
    document.getElementById('ps_motivo').value = '';
    abrirModal('modalProcesarSolicitud');
}

function gestionarSolicitud(estatus) {
    const id = document.getElementById('ps_id').value;
    const motivo = document.getElementById('ps_motivo').value.trim();
    
    if (estatus === 'rechazado' && !motivo) {
        COMECyTUI.alert('Debe indicar un motivo para el rechazo.');
        return;
    }

    const fd = new FormData();
    fd.append('accion', 'gestionar');
    fd.append('csrf_token', '<?= $_SESSION['csrf_token'] ?>');
    fd.append('id', id);
    fd.append('estatus', estatus);
    fd.append('motivo', motivo);

    fetch('api/calendario_solicitudes.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.ok) {
                cerrarModal('modalProcesarSolicitud');
                cargarSolicitudes();
                if (estatus === 'aceptado') {
                    COMECyTUI.info('Solicitud aprobada. El evento ha sido añadido al calendario.', 'Éxito', () => location.reload());
                } else {
                    COMECyTUI.info('Solicitud rechazada.', 'Gestión');
                }
            } else {
                COMECyTUI.alert('Error: ' + d.error);
            }
        });
}

function formatFechaDisplay(s) {
    const d = new Date(s);
    return d.toLocaleString('es-MX', { day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit' });
}

function escapeHtml(s) {
    return String(s || '')
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Inicializar contador al cargar
document.addEventListener('DOMContentLoaded', () => {
    fetch('api/calendario_solicitudes.php?accion=listar_pendientes')
        .then(r => {
            if (!r.ok) throw new Error('API Response Error: ' + r.status);
            return r.json();
        })
        .then(d => {
            if (d.ok) actualizarBadgeSolicitudes(d.solicitudes.length);
        })
        .catch(err => console.error('Initial fetch error:', err));
});

</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

