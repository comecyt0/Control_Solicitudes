<?php
/**
 * COMECyT — Calendario Editorial (Departamento de Difusión)
 * Diseño y funcionalidad sincronizados con el Calendario Administrativo Global.
 * Filtra eventos de df_eventos_editoriales y tareas de sb_kanban_tareas por cve_area.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../config/auth.php';

verificarSesionAdmin();

$pdo = getConnection();
$mensajeFlash = '';
$tipoFlash = '';
$cveAreaUsuario  = (int) ($_SESSION['admin_cve_area'] ?? $_SESSION['user_cve_area'] ?? 0);
$cveAreaContexto = 6; // ÁREA FIJA: Departamento de Difusión de Ciencia y Tecnología
$adminId         = (int) ($_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 0);

if ($cveAreaUsuario === 0) redirigir('public/hub.php');

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
        $color = postParam('color', '#e11d48'); // Color por defecto editorial
        
        if ($titulo && $fechaInicio && $fechaFin) {
            // SEGURIDAD: Validar que el adminId exista en la tabla administradores antes de insertar
            // (Evita el error de Foreign Key violation si el usuario logueado es usuario regular pero no admin)
            $checkAdmin = $pdo->prepare("SELECT 1 FROM administradores WHERE id = ?");
            $checkAdmin->execute([$adminId]);
            $idAutor = $checkAdmin->fetch() ? $adminId : null;

            // Insertar en tabla editorial (Siempre publico=false para esta vista editorial interna)
            $stmt = $pdo->prepare("INSERT INTO df_eventos_editoriales (titulo, descripcion, fecha_inicio, fecha_fin, color, creado_por, publico) VALUES (?, ?, ?, ?, ?, ?, FALSE)");
            $stmt->execute([$titulo, $descripcion, $fechaInicio, $fechaFin, $color, $idAutor]);
            
            header('Location: ' . BASE_URL . 'areas/difusion/calendario_editorial.php?flash=evento_creado');
            exit;
        } else {
            $mensajeFlash = "El título y las fechas son obligatorias.";
            $tipoFlash = "error";
        }
    } elseif ($accion === 'editar_evento') {
        $id = (int) postParam('evento_id');
        $esInstitucionalManual = (int) postParam('es_institucional'); // Bandera enviada por el front
        
        // SEGURIDAD: Validar si el usuario intenta editar un institucional sin ser de Sistemas
        if ($esInstitucionalManual === 1 && $cveAreaUsuario !== 1) {
             die("Acceso denegado: Solo Sistemas puede editar eventos institucionales principales.");
        }

        // Adicionalmente, verificar en DB para evitar manipulaciones del POST
        if ($esInstitucionalManual === 0) {
            $stmtCheck = $pdo->prepare("SELECT 1 FROM df_eventos_editoriales WHERE id = ?");
            $stmtCheck->execute([$id]);
            if (!$stmtCheck->fetch()) {
                // Si dijo que era editorial pero no existe, probablemente es un intento de spoofing de ID institucional
                if ($cveAreaUsuario !== 1) die("Error de validación de registro.");
            }
        }

        $titulo = trim(postParam('titulo'));
        $descripcion = trim(postParam('descripcion'));
        $fechaInicio = postParam('fecha_inicio');
        $fechaFin = postParam('fecha_fin');
        $color = postParam('color', '#e11d48');
        
        if ($id > 0 && $titulo && $fechaInicio && $fechaFin) {
            $publico = isset($_POST['publico']) ? 'TRUE' : 'FALSE';
            if ($esInstitucionalManual === 0) {
                $stmt = $pdo->prepare("UPDATE df_eventos_editoriales SET titulo = ?, descripcion = ?, fecha_inicio = ?, fecha_fin = ?, color = ?, publico = $publico WHERE id = ?");
                $stmt->execute([$titulo, $descripcion, $fechaInicio, $fechaFin, $color, $id]);
            } else {
                // Es institucional y es Sistemas, actualizar tabla global
                $stmt = $pdo->prepare("UPDATE eventos SET titulo = ?, descripcion = ?, fecha_inicio = ?, fecha_fin = ?, color = ?, publico = $publico WHERE id = ?");
                $stmt->execute([$titulo, $descripcion, $fechaInicio, $fechaFin, $color, $id]);
            }
            header('Location: ' . BASE_URL . 'areas/difusion/calendario_editorial.php?flash=evento_editado');
            exit;
        }
    } elseif ($accion === 'eliminar_evento') {
        $id = (int) postParam('evento_id');
        $esInstitucionalManual = (int) postParam('es_institucional');
        
        if ($id > 0) {
            // SEGURIDAD: Solo Sistemas borra institucionales
            if ($esInstitucionalManual === 1 && $cveAreaUsuario !== 1) {
                die("Acceso denegado.");
            }

            if ($esInstitucionalManual === 0) {
                $stmt = $pdo->prepare("DELETE FROM df_eventos_editoriales WHERE id = ?");
            } else {
                $stmt = $pdo->prepare("DELETE FROM eventos WHERE id = ?");
            }
            $stmt->execute([$id]);
            header('Location: ' . BASE_URL . 'areas/difusion/calendario_editorial.php?flash=evento_eliminado');
            exit;
        }
    } elseif ($accion === 'crear_tarea') {
        $titulo = trim(postParam('titulo'));
        $descripcion = trim(postParam('descripcion'));
        $color = postParam('color', '#e11d48');
        $asignado_a = !empty($_POST['asignado_a']) ? (int)$_POST['asignado_a'] : null;
        
        if ($titulo) {
            // SEGURIDAD: Validar si el creador es admin para evitar violaciones de integridad si re-activamos FKs
            $checkAdmin = $pdo->prepare("SELECT 1 FROM administradores WHERE id = ?");
            $checkAdmin->execute([$adminId]);
            $idAutor = $checkAdmin->fetch() ? $adminId : null;

            $stmt = $pdo->prepare("INSERT INTO sb_kanban_tareas (titulo, descripcion, color, estatus, creado_por, asignado_a, cve_area) VALUES (?, ?, ?, 'pendiente', ?, ?, ?)");
            $stmt->execute([$titulo, $descripcion, $color, $idAutor, $asignado_a, $cveAreaContexto]);
            header('Location: ' . BASE_URL . 'areas/difusion/calendario_editorial.php?flash=tarea_creada#kanban');
            exit;
        }
    } elseif ($accion === 'editar_tarea') {
        $id = (int) postParam('tarea_id');
        $titulo = trim(postParam('titulo'));
        $descripcion = trim(postParam('descripcion'));
        $color = postParam('color', '#e11d48');
        $asignado_a = !empty($_POST['asignado_a']) ? (int)$_POST['asignado_a'] : null;
        
        if ($id > 0 && $titulo) {
            $stmt = $pdo->prepare("UPDATE sb_kanban_tareas SET titulo = ?, descripcion = ?, color = ?, asignado_a = ? WHERE id = ?");
            $stmt->execute([$titulo, $descripcion, $color, $asignado_a, $id]);
            header('Location: ' . BASE_URL . 'areas/difusion/calendario_editorial.php?flash=tarea_editada#kanban');
            exit;
        }
    } elseif ($accion === 'mover_tarea') {
        $id = (int) postParam('tarea_id');
        $nuevoEstatus = postParam('nuevo_estatus');
        if ($id > 0 && in_array($nuevoEstatus, ['pendiente', 'en_proceso', 'completada'])) {
            $stmt = $pdo->prepare("UPDATE sb_kanban_tareas SET estatus = ? WHERE id = ?");
            $stmt->execute([$nuevoEstatus, $id]);
            header('Location: ' . BASE_URL . 'areas/difusion/calendario_editorial.php?flash=tarea_movida#kanban');
            exit;
        }
    } elseif ($accion === 'eliminar_tarea') {
        $id = (int) postParam('tarea_id');
        if ($id > 0) {
            $stmt = $pdo->prepare("DELETE FROM sb_kanban_tareas WHERE id = ?");
            $stmt->execute([$id]);
            header('Location: ' . BASE_URL . 'areas/difusion/calendario_editorial.php?flash=tarea_eliminada#kanban');
            exit;
        }
    }
}

// Leer flash redirect
$flashCode = getParam('flash');
if ($flashCode === 'evento_creado') { $mensajeFlash = "Evento editorial agendado."; $tipoFlash = "success"; }
elseif ($flashCode === 'evento_editado') { $mensajeFlash = "Evento editorial actualizado."; $tipoFlash = "success"; }
elseif ($flashCode === 'evento_eliminado') { $mensajeFlash = "Evento eliminado."; $tipoFlash = "success"; }
elseif ($flashCode === 'tarea_creada') { $mensajeFlash = "Tarea añadida al Kanban editorial."; $tipoFlash = "success"; }
elseif ($flashCode === 'tarea_editada') { $mensajeFlash = "Tarea actualizada."; $tipoFlash = "success"; }
elseif ($flashCode === 'tarea_movida') { $mensajeFlash = "Estatus de tarea actualizado."; $tipoFlash = "success"; }
elseif ($flashCode === 'tarea_eliminada') { $mensajeFlash = "Tarea eliminada."; $tipoFlash = "success"; }

// -------------------------------------------------------
// Logica de Mes y Fechas
// -------------------------------------------------------
$hoy = new DateTime();
$mes = (int) getParam('mes', $hoy->format('m'));
$anio = (int) getParam('anio', $hoy->format('Y'));
if ($mes < 1 || $mes > 12) { $mes = (int)$hoy->format('m'); }
if ($anio < 2000 || $anio > 2100) { $anio = (int)$hoy->format('Y'); }

$dtMes = DateTime::createFromFormat('Y-m-d', sprintf('%04d-%02d-01', $anio, $mes));
$mesAnterior = (clone $dtMes)->modify('-1 month');
$mesSiguiente = (clone $dtMes)->modify('+1 month');
$diasEnMes = (int)$dtMes->format('t');
$diaSemanaInicio = (int)$dtMes->format('N'); 

$inicioMesBusqueda = $dtMes->format('Y-m-01 00:00:00');
$finMesBusqueda    = $mesSiguiente->format('Y-m-01 00:00:00');

// 1. Consultar eventos EDITORIALES (Propios del área)
$stmt = $pdo->prepare("SELECT * FROM df_eventos_editoriales WHERE fecha_inicio < ? AND fecha_fin > ? ORDER BY fecha_inicio ASC");
$stmt->execute([$finMesBusqueda, $inicioMesBusqueda]);
$eventosRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 2. Consultar eventos INSTITUCIONALES (Globales/Públicos)
$stmtG = $pdo->prepare("SELECT * FROM eventos WHERE publico = TRUE AND fecha_inicio < ? AND fecha_fin > ? ORDER BY fecha_inicio ASC");
$stmtG->execute([$finMesBusqueda, $inicioMesBusqueda]);
$eventosGlobales = $stmtG->fetchAll(PDO::FETCH_ASSOC);

foreach ($eventosGlobales as $eg) {
    // Evitar duplicados si el título indica que es una réplica editorial
    if (strpos($eg['titulo'], '(Editorial)') === 0) continue;
    
    $eg['es_institucional'] = true;
    if (empty($eg['color'])) $eg['color'] = '#64748b'; 
    $eventosRaw[] = $eg;
}

// 3. Mapear a Calendario por Día
$calendarioEventos = [];
foreach ($eventosRaw as $ev) {
    $diaEv = (int) (new DateTime($ev['fecha_inicio']))->format('d');
    if (!isset($calendarioEventos[$diaEv])) $calendarioEventos[$diaEv] = [];
    $calendarioEventos[$diaEv][] = $ev;
}

// 4. Consultar y Añadir CUMPLEAÑOS
if ($mes > 0 && $mes <= 12) {
    $stmtB = $pdo->prepare("SELECT nombre, appat, apmat, fecha_nacimiento, foto_perfil FROM cat_personal WHERE activo = TRUE AND fecha_nacimiento IS NOT NULL AND EXTRACT(MONTH FROM fecha_nacimiento) = :mes");
    $stmtB->execute([':mes' => $mes]);
    $cumpleaneros = $stmtB->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cumpleaneros as $cp) {
        $diaCumple = (int) (new DateTime($cp['fecha_nacimiento']))->format('d');
        $nombreCompleto = trim($cp['nombre'] . ' ' . $cp['appat'] . ' ' . $cp['apmat']);
        $edadAnios = $anio - (int)(new DateTime($cp['fecha_nacimiento']))->format('Y');
        $calendarioEventos[$diaCumple][] = [
            'id' => null, 
            'titulo' => "🎂 " . $nombreCompleto, 
            'descripcion' => 'Cumpleaños institucional (' . $edadAnios . ' años)',
            'fecha_inicio' => sprintf('%04d-%02d-%02d 00:00:00', $anio, $mes, $diaCumple), 
            'fecha_fin' => sprintf('%04d-%02d-%02d 23:59:59', $anio, $mes, $diaCumple),
            'color' => '#B19A6D', 
            'publico' => false, 
            'es_cumple' => true, 
            'foto_perfil' => $cp['foto_perfil'] ?? null, 
            'nombre_cumple'=> $nombreCompleto, 
            'edad' => $edadAnios,
        ];
    }
}

// 5. Consultar tareas KANBAN filtradas por ÁREA FIJA (Difusión)
$listaTareas = ['pendiente' => [], 'en_proceso' => [], 'completada' => []];
$stmtT = $pdo->prepare("SELECT t.*, a.nombre AS asignado_nombre FROM sb_kanban_tareas t LEFT JOIN administradores a ON t.asignado_a = a.id WHERE t.cve_area = ? ORDER BY t.estatus DESC, t.id DESC");
$stmtT->execute([$cveAreaContexto]);
$tareas = $stmtT->fetchAll(PDO::FETCH_ASSOC);
foreach ($tareas as $t) {
    if (isset($listaTareas[$t['estatus']])) $listaTareas[$t['estatus']][] = $t;
    else $listaTareas['pendiente'][] = $t;
}

// 6. Admins para asignación
$stmtAdmins = $pdo->prepare("SELECT a.id, a.nombre 
         FROM administradores a 
         INNER JOIN cat_personal p ON (LOWER(p.correo_institucional) = LOWER(a.email) OR LOWER(p.correo_personal) = LOWER(a.email))
         WHERE a.activo = true AND p.cve_area = ? 
         ORDER BY a.nombre ASC");
$stmtAdmins->execute([$cveAreaContexto]);
$adminsDisponibles = $stmtAdmins->fetchAll(PDO::FETCH_ASSOC);

$mesesNombres = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];
$pageTitle = 'Calendario Editorial';
$activeMenu = 'calendario';

// REUTILIZAR ESTILOS DE ADMIN/CALENDARIO.PHP (Ya los tengo en el output anterior)
// Sobrescribo variables para que el color principal sea el Editorial (Crimson)
$extraHead = '
<style>
/* Variables de Tema Editorial (Crimson) */
:root {
    --color-primary: #e11d48;
    --color-primary-dark: #be123c;
    --color-primary-light: #fb7185;
    --color-primary-hover: #f43f5e;
    --color-accent: #B19A6D;
}

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

/* Botonera de navegacion */
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
    background: #f8fafc;
    gap: 1px;
}

/* Cabecera de dias */
.calendar-day-name {
    padding: 1rem 0.5rem;
    text-align: right;
    font-weight: 800;
    font-size: 0.75rem;
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
    overflow: hidden;
    min-width: 0;
    width: 100%;
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
    font-weight: 700;
    color: #334155;
    font-size: 1rem;
    margin-bottom: 0.25rem;
    display: inline-flex;
    justify-content: center;
    align-self: flex-end;
    width: 28px;
    height: 28px;
    align-items: center;
    border-radius: 50%;
    transition: all 0.2s;
}

.calendar-cell.today .day-number {
    color: #fff;
    background: var(--color-primary);
    box-shadow: 0 2px 8px rgba(225, 29, 72, 0.4);
}

/* STICKY NOTES (Post-its) */
.evento-pildora {
    font-size: 0.75rem;
    padding: 0.5rem 0.6rem;
    border-radius: 2px 2px 12px 2px;
    color: #1e293b;
    margin-bottom: 0.25rem;
    cursor: pointer;
    position: relative;
    box-shadow: 2px 2px 4px rgba(0, 0, 0, 0.05), inset -10px -10px 20px rgba(0,0,0,0.03);
    transition: all 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    line-height: 1.3;
    animation: fadeIn 0.3s ease-in-out;
    width: 100%;
    box-sizing: border-box;
    overflow: hidden;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(4px); }
    to { opacity: 1; transform: translateY(0); }
}

.evento-pildora:hover {
    transform: scale(1.02) translateY(-2px) rotate(-1deg);
    box-shadow: 4px 6px 12px rgba(0, 0, 0, 0.1), inset -10px -10px 20px rgba(0,0,0,0.02);
    z-index: 10;
}

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

.evento-titulo {
    font-weight: 700;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    display: flex;
    align-items: center;
    gap: 6px;
}

.cumple-mini-avatar {
    width: 22px;
    height: 22px;
    border-radius: 50%;
    object-fit: cover;
    border: 1.5px solid #ca8a04;
    flex-shrink: 0;
}

.cumple-mini-placeholder {
    background: #fef08a;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    color: #ca8a04;
    width: 22px; height: 22px; border-radius: 50%;
}

/* Colores Post-it */
.nota-azul       { background: #e0f2fe; border-top: 3px solid #0284c7; }
.nota-verde      { background: #dcfce7; border-top: 3px solid #16a34a; }
.nota-dorado     { background: #fef08a; border-top: 3px solid #ca8a04; }
.nota-rojo       { background: #fee2e2; border-top: 3px solid #dc2626; }
.nota-difusion   { background: #fff1f2; border-top: 3px solid #e11d48; }

/* KANBAN */
.kanban-board {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.5rem;
    padding: 1.5rem 0;
}

.kanban-col {
    background: #f1f5f9;
    border-radius: 12px;
    display: flex;
    flex-direction: column;
    min-height: 500px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
}

.kanban-col-header {
    padding: 1.25rem;
    font-weight: 800;
    font-size: 1rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border-radius: 12px 12px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    color: white;
}

.bg-pendiente { background-color: #3b82f6; }
.bg-en-proceso { background-color: #f59e0b; }
.bg-completada { background-color: #10b981; }

.kanban-badge {
    background: rgba(255,255,255,0.25);
    padding: 2px 10px;
    border-radius: 20px;
    font-size: 0.8rem;
}

.kanban-col-body {
    padding: 1rem;
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.tarea-card {
    background: #ffffff;
    border-radius: 10px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.04);
    border: 1px solid #e2e8f0;
    padding: 1.25rem;
    cursor: grab;
    border-top: 5px solid var(--color-primary); 
    transition: all 0.2s ease;
}

.tarea-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 20px -5px rgba(0,0,0,0.1);
}

.tarea-card h4 {
    margin: 0 0 8px 0;
    font-size: 1rem;
    font-weight: 700;
    color: #1e293b;
}

.tarea-card p {
    margin: 0;
    font-size: 0.85rem;
    color: #64748b;
    line-height: 1.5;
}

/* Estilos Modales */
.modal-backdrop {
    display: none;
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(15, 23, 42, 0.7);
    backdrop-filter: blur(8px);
    z-index: 2000;
    align-items: center;
    justify-content: center;
    padding: 20px;
}
.modal-backdrop.active { display: flex; animation: fadeIn 0.3s ease; }
.modal {
    background: #fff;
    width: 100%;
    max-width: 550px;
    border-radius: 20px;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    overflow: hidden;
    display: flex;
    flex-direction: column;
}
.modal-header {
    background: linear-gradient(135deg, var(--color-primary), var(--color-primary-dark));
    color: #fff;
    padding: 1.25rem 1.5rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.modal-title { margin: 0; font-size: 1.25rem; font-weight: 800; display: flex; align-items: center; gap: 12px; }
.modal-close { background: none; border: none; color: rgba(255,255,255,0.8); cursor: pointer; font-size: 1.5rem; transition: color 0.2s; }
.modal-close:hover { color: #fff; }
.modal-body { padding: 1.5rem 2rem; overflow-y: auto; }
.modal-footer { padding: 1.25rem 2rem; border-top: 1px solid #f1f5f9; background: #fafafa; display: flex; gap: 10px; justify-content: flex-end; }

.form-label { font-weight: 700; color: #475569; font-size: 0.9rem; margin-bottom: 6px; display: block; }
.form-control { border-radius: 10px; border: 1px solid #e2e8f0; background: #f8fafc; padding: 10px 14px; transition: all 0.2s; }
.form-control:focus { border-color: var(--color-primary); box-shadow: 0 0 0 4px rgba(225, 29, 72, 0.1); background: #fff; outline: none; }

</style>';

require_once __DIR__ . '/../../includes/header_admin.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
    <div>
        <h2 style="font-weight: 800; color: #0f172a; margin: 0;"><i class="fa-solid fa-calendar-week" style="color:#e11d48;"></i> Calendario Editorial</h2>
        <p style="color: #64748b; margin: 0;">Gestión de publicaciones, eventos de difusión y tablero de tareas del área.</p>
    </div>
    <div style="display: flex; gap: 10px;">
        <button class="btn btn-primary" onclick="abrirModalCrear()">
            <i class="fa-solid fa-plus"></i> Nuevo Evento Editorial
        </button>
    </div>
</div>

<?php if ($mensajeFlash): ?>
<div class="alert alert-<?= $tipoFlash ?> alert-dismissible fade show" role="alert">
    <?= esc($mensajeFlash) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- CALENDARIO -->
<div class="calendar-wrapper">
    <div class="calendar-header-nav">
        <h3><?= $mesesNombres[$mes] ?> <?= $anio ?></h3>
        <div class="nav-btn-group">
            <a href="?mes=<?= $mesAnterior->format('m') ?>&anio=<?= $mesAnterior->format('Y') ?>" class="btn btn-outline">
                <i class="fa-solid fa-left-long"></i>
            </a>
            <a href="?mes=<?= date('m') ?>&anio=<?= date('Y') ?>" class="btn btn-outline">Hoy</a>
            <a href="?mes=<?= $mesSiguiente->format('m') ?>&anio=<?= $mesSiguiente->format('Y') ?>" class="btn btn-outline">
                <i class="fa-solid fa-right-long"></i>
            </a>
        </div>
    </div>
    <div class="calendar-grid">
        <?php foreach (['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'] as $d): ?>
            <div class="calendar-day-name"><?= $d ?></div>
        <?php endforeach; ?>

        <?php for ($i = 1; $i < $diaSemanaInicio; $i++): ?>
            <div class="calendar-cell empty"></div>
        <?php endfor; ?>

        <?php for ($dia = 1; $dia <= $diasEnMes; $dia++): 
            $esHoy = ($dia === (int)date('d') && $mes === (int)date('m') && $anio === (int)date('Y'));
            $fechaIso = sprintf('%04d-%02d-%02d', $anio, $mes, $dia);
        ?>
            <div class="calendar-cell <?= $esHoy ? 'today' : '' ?>" onclick="abrirModalCrearDesdeCelda('<?= $fechaIso ?>', '<?= $fechaIso ?>')">
                <div class="day-number"><?= $dia ?></div>
                <?php foreach ($calendarioEventos[$dia] ?? [] as $ev): 
                    $colorNota = isset($ev['es_cumple']) ? 'nota-dorado' : 'nota-difusion';
                ?>
                    <div class="evento-pildora <?= $colorNota ?>" 
                         onclick="event.stopPropagation(); 
                         <?php if(isset($ev['es_cumple'])): ?>
                            abrirModalCumple('<?=esc($ev['nombre_cumple'])?>', '<?=esc($ev['descripcion'])?>', '<?= BASE_URL ?>public/uploads/avatares/<?=esc($ev['foto_perfil'])?>', '<?=$ev['edad']?>', '<?=date('d M', strtotime($ev['fecha_inicio']))?>')
                         <?php elseif(isset($ev['es_institucional']) && $ev['es_institucional'] && $cveAreaUsuario !== 1): ?>
                            abrirModalVer('<?=esc($ev['titulo'])?>', '<?=esc($ev['descripcion'])?>', '<?=date('Y-m-d', strtotime($ev['fecha_inicio']))?>', '<?=date('Y-m-d', strtotime($ev['fecha_fin']))?>')
                         <?php else: ?>
                            abrirModalEditar(<?=$ev['id']?>, '<?=esc($ev['titulo'])?>', '<?=esc($ev['descripcion'])?>', '<?=date('Y-m-d', strtotime($ev['fecha_inicio']))?>', '<?=date('Y-m-d', strtotime($ev['fecha_fin']))?>', '<?=($ev['color'])?>', <?=($ev['publico']?1:0)?>, <?= (isset($ev['es_institucional']) && $ev['es_institucional']) ? '1' : '0' ?>)
                         <?php endif; ?>">
                        <div class="evento-titulo">
                            <?php if(isset($ev['es_cumple'])): ?>
                                <?php if(!empty($ev['foto_perfil'])): ?>
                                    <img src="<?= BASE_URL ?>public/uploads/avatares/<?= esc($ev['foto_perfil']) ?>" class="cumple-mini-avatar">
                                <?php else: ?>
                                    <div class="cumple-mini-avatar cumple-mini-placeholder"><i class="fa-solid fa-cake-candles"></i></div>
                                <?php endif; ?>
                            <?php endif; ?>
                            <?= esc($ev['titulo']) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endfor; ?>
    </div>
</div>

<!-- KANBAN -->
<div id="kanban" style="margin-top: 50px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
        <h3 style="font-weight: 800; color: #1e293b; margin:0;"><i class="fa-solid fa-list-check" style="color:#3b82f6;"></i> Tablero de Tareas Editoriales</h3>
        <button class="btn btn-primary" onclick="abrirModalCrearTarea()" style="background:#3b82f6; border:none; padding: 0.5rem 1rem; font-size: 0.85rem;">
            <i class="fa-solid fa-plus"></i> Nueva Tarea
        </button>
    </div>
    <div class="kanban-board">
        <?php foreach (['pendiente' => 'Pendiente', 'en_proceso' => 'En Proceso', 'completada' => 'Completada'] as $idCol => $lblCol): ?>
            <div class="kanban-col" ondragover="allowDrop(event)" ondrop="drop(event, '<?= $idCol ?>')">
                <div class="kanban-col-header bg-<?= str_replace('_','-',$idCol) ?>">
                    <span><?= strtoupper($lblCol) ?></span>
                    <span class="kanban-badge"><?= count($listaTareas[$idCol]) ?></span>
                </div>
                <div class="kanban-col-body">
                    <?php if (empty($listaTareas[$idCol])): ?>
                        <div class="kanban-empty">Sin tareas</div>
                    <?php endif; ?>
                    <?php foreach ($listaTareas[$idCol] as $t): ?>
                        <div class="tarea-card" draggable="true" ondragstart="drag(event, <?= $t['id'] ?>)" 
                             style="border-top-color: <?= $t['color'] ?>;" 
                             onclick="abrirModalEditarTarea(<?= $t['id'] ?>, '<?= esc($t['titulo']) ?>', '<?= esc($t['descripcion']) ?>', '<?= $t['color'] ?>', <?= (int)$t['asignado_a'] ?>)">
                            <h4><?= esc($t['titulo']) ?></h4>
                            <p><?= esc(mb_strimwidth($t['descripcion'], 0, 80, '...')) ?></p>
                            <div style="margin-top:10px; font-size:0.7rem; color:#94a3b8; display:flex; justify-content:space-between;">
                                <span><i class="fa-solid fa-user-tag"></i> <?= esc($t['asignado_nombre'] ?: 'Sin asignar') ?></span>
                                <i class="fa-solid fa-up-down-left-right" style="opacity:0.3;"></i>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- MODALES (COMECyTUI Standard) -->
<!-- Modal Crear Evento -->
<div class="modal-backdrop" id="modalCrearEvento">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fa-solid fa-plus-circle"></i> Agendar Evento Editorial</h3>
            <button type="button" class="modal-close" onclick="cerrarModal('modalCrearEvento')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="_accion" value="crear_evento">
            <div class="modal-body">
                <div class="form-group mb-16">
                    <label class="form-label">Título</label>
                    <input type="text" name="titulo" class="form-control" id="c_titulo" required>
                </div>
                <div class="form-group mb-16">
                    <label class="form-label">Descripción / Canal</label>
                    <textarea name="descripcion" class="form-control" rows="3"></textarea>
                </div>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;" class="mb-16">
                    <div class="form-group">
                        <label class="form-label">Desde</label>
                        <input type="date" name="fecha_inicio" id="c_fecha_inicio" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Hasta</label>
                        <input type="date" name="fecha_fin" id="c_fecha_fin" class="form-control" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="cerrarModal('modalCrearEvento')">Cerrar</button>
                <button type="submit" class="btn btn-primary">Guardar Evento</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Ver/Editar Evento -->
<div class="modal-backdrop" id="modalEditarEvento">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fa-solid fa-calendar-day"></i> Detalle de Evento</h3>
            <button type="button" class="modal-close" onclick="cerrarModal('modalEditarEvento')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" id="formEditarEvento">
            <?= csrfField() ?>
            <input type="hidden" name="_accion" id="e_accion" value="editar_evento">
            <input type="hidden" name="evento_id" id="e_evento_id">
            <input type="hidden" name="es_institucional" id="e_es_institucional">
            <div class="modal-body">
                <div class="form-group mb-16">
                    <label class="form-label">Título</label>
                    <input type="text" name="titulo" id="e_titulo" class="form-control" required>
                </div>
                <div class="form-group mb-16">
                    <label class="form-label">Descripción</label>
                    <textarea name="descripcion" id="e_descripcion" class="form-control" rows="3"></textarea>
                </div>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;" class="mb-16">
                    <div class="form-group">
                        <label class="form-label">Inicio</label>
                        <input type="date" name="fecha_inicio" id="e_fecha_inicio" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Fin</label>
                        <input type="date" name="fecha_fin" id="e_fecha_fin" class="form-control" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-between" id="footerEditarEvento">
                <button type="button" class="btn btn-danger" onclick="eliminarEvento()" id="btnEliminarEvento">Eliminar</button>
                <div>
                    <button type="button" class="btn btn-outline" onclick="cerrarModal('modalEditarEvento')">Cerrar</button>
                    <button type="submit" class="btn btn-primary" id="btnActualizarEvento">Actualizar</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal Nueva Tarea -->
<div class="modal-backdrop" id="modalCrearTarea">
    <div class="modal">
        <div class="modal-header" style="background: #3b82f6;">
            <h3 class="modal-title"><i class="fa-solid fa-plus-circle"></i> Nueva Tarea Editorial</h3>
            <button type="button" class="modal-close" onclick="cerrarModal('modalCrearTarea')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="_accion" value="crear_tarea">
            <div class="modal-body">
                <div class="form-group mb-16">
                    <label class="form-label">Título de la Tarea</label>
                    <input type="text" name="titulo" class="form-control" placeholder="Ej. Revisar diseño de banner" required>
                </div>
                <div class="form-group mb-16">
                    <label class="form-label">Descripción extendida</label>
                    <textarea name="descripcion" class="form-control" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Asignar a compañero de equipo</label>
                    <select name="asignado_a" class="form-control">
                        <option value="">-- Sin Asignar --</option>
                        <?php foreach ($adminsDisponibles as $adm): ?>
                            <option value="<?= $adm['id'] ?>"><?= esc($adm['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="cerrarModal('modalCrearTarea')">Cerrar</button>
                <button type="submit" class="btn btn-primary" style="background:#3b82f6; border-color:#3b82f6;">Crear Tarea</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Editar Tarea -->
<div class="modal-backdrop" id="modalEditarTarea">
    <div class="modal">
        <div class="modal-header" style="background: #3b82f6;">
            <h3 class="modal-title"><i class="fa-solid fa-pen"></i> Editar Tarea Editorial</h3>
            <button type="button" class="modal-close" onclick="cerrarModal('modalEditarTarea')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="_accion" value="editar_tarea">
            <input type="hidden" name="tarea_id" id="et_id">
            <div class="modal-body">
                <div class="form-group mb-16">
                    <label class="form-label">Título</label>
                    <input type="text" name="titulo" id="et_titulo" class="form-control" required>
                </div>
                <div class="form-group mb-16">
                    <label class="form-label">Descripción</label>
                    <textarea name="descripcion" id="et_descripcion" class="form-control" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Asignado a</label>
                    <select name="asignado_a" id="et_asignado_a" class="form-control">
                        <option value="">-- Sin Asignar --</option>
                        <?php foreach ($adminsDisponibles as $adm): ?>
                            <option value="<?= $adm['id'] ?>"><?= esc($adm['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-between">
                <button type="button" class="btn btn-outline" style="color:var(--color-danger); border-color:var(--color-danger);" onclick="eliminarTareaDesdeModal()">
                    <i class="fa-solid fa-trash"></i> Eliminar
                </button>
                <div>
                    <button type="button" class="btn btn-outline" onclick="cerrarModal('modalEditarTarea')">Cerrar</button>
                    <button type="submit" class="btn btn-primary" style="background:#3b82f6; border-color:#3b82f6;">Actualizar Tarea</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Formulario Oculto para Movimiento de Tareas -->
<form id="formAccionTarea" method="POST" style="display:none;">
    <?= csrfField() ?>
    <input type="hidden" name="_accion" id="t_accion">
    <input type="hidden" name="tarea_id" id="t_tarea_id">
    <input type="hidden" name="nuevo_estatus" id="t_nuevo_estatus">
</form>

<!-- Modal Cumpleaños -->
<?php require_once __DIR__ . '/../../admin/modales/modal_cumple.php'; ?>

<script>
function abrirModal(id) {
    document.getElementById(id).classList.add('active');
}
function cerrarModal(id) {
    document.getElementById(id).classList.remove('active');
}

function abrirModalCrear() { abrirModal('modalCrearEvento'); }
function abrirModalCrearDesdeCelda(ini, fin) {
    document.getElementById('c_fecha_inicio').value = ini;
    document.getElementById('c_fecha_fin').value = fin;
    abrirModal('modalCrearEvento');
}
function abrirModalVer(titulo, desc, ini, fin) {
    document.getElementById('e_titulo').value = titulo;
    document.getElementById('e_titulo').readOnly = true;
    document.getElementById('e_descripcion').value = desc;
    document.getElementById('e_descripcion').readOnly = true;
    document.getElementById('e_fecha_inicio').value = ini;
    document.getElementById('e_fecha_inicio').readOnly = true;
    document.getElementById('e_fecha_fin').value = fin;
    document.getElementById('e_fecha_fin').readOnly = true;
    document.getElementById('e_publico').disabled = true;
    document.getElementById('btnEliminarEvento').style.display = 'none';
    document.getElementById('btnActualizarEvento').style.display = 'none';
    abrirModal('modalEditarEvento');
}
function abrirModalCumple(nombre, desc, fotoUrl, edad, fecha) {
    document.getElementById('mc_nombre').textContent = nombre;
    document.getElementById('mc_nombre_grande').textContent = nombre;
    document.getElementById('mc_edad_label').textContent = '¡Celebra sus ' + edad + ' años!';
    document.getElementById('mc_fecha').textContent = fecha;
    const imgEl = document.getElementById('mc_foto');
    const phEl = document.getElementById('mc_foto_placeholder');
    if (fotoUrl && fotoUrl.trim() !== '') {
        imgEl.src = fotoUrl;
        imgEl.style.display = 'block';
        phEl.style.display = 'none';
    } else {
        imgEl.style.display = 'none';
        phEl.style.display = 'flex';
    }
    abrirModal('modalVerCumple');
}
function abrirModalEditar(id, t, d, ini, fin, c, p, esInst) {
    document.getElementById('e_evento_id').value = id;
    document.getElementById('e_es_institucional').value = esInst ? 1 : 0;
    document.getElementById('e_titulo').value = t;
    document.getElementById('e_titulo').readOnly = false;
    document.getElementById('e_descripcion').value = d;
    document.getElementById('e_descripcion').readOnly = false;
    document.getElementById('e_fecha_inicio').value = ini;
    document.getElementById('e_fecha_inicio').readOnly = false;
    document.getElementById('e_fecha_fin').value = fin;
    document.getElementById('e_fecha_fin').readOnly = false;
    document.getElementById('e_publico').checked = (p == 1);
    document.getElementById('e_publico').disabled = false;
    
    document.getElementById('btnEliminarEvento').style.display = 'block';
    document.getElementById('btnActualizarEvento').style.display = 'block';
    
    // Si es Systems pero es Institucional, ocultar checkbox public para evitar confusiones de replicas
    // Pero el usuario pidió que Systems SI pueda editar. 
    abrirModal('modalEditarEvento');
}

function eliminarEvento() {
    COMECyTUI.confirm('¿Deseas borrar permanentemente este evento de la agenda editorial?', () => {
        document.getElementById('e_accion').value = 'eliminar_evento';
        document.getElementById('formEditarEvento').submit();
    }, null, { titulo: 'Eliminar Evento' });
}

// Kanban
function allowDrop(ev) { ev.preventDefault(); }
function drag(ev, id) { ev.dataTransfer.setData("text", id); }
function drop(ev, nuevo) {
    ev.preventDefault();
    const id = ev.dataTransfer.getData("text");
    moverTarea(id, nuevo);
}
function moverTarea(id, nuevo) {
    document.getElementById('t_accion').value = 'mover_tarea';
    document.getElementById('t_tarea_id').value = id;
    document.getElementById('t_nuevo_estatus').value = nuevo;
    document.getElementById('formAccionTarea').submit();
}
function abrirModalCrearTarea() { abrirModal('modalCrearTarea'); }
function abrirModalEditarTarea(id, t, d, c, a) {
    document.getElementById('et_id').value = id;
    document.getElementById('et_titulo').value = t;
    document.getElementById('et_descripcion').value = d;
    document.getElementById('et_asignado_a').value = a || '';
    abrirModal('modalEditarTarea');
}
function eliminarTareaDesdeModal() {
    COMECyTUI.confirm('¿Seguro que deseas eliminar esta tarea del tablero Kanban?', () => {
        document.getElementById('t_accion').value = 'eliminar_tarea';
        document.getElementById('t_tarea_id').value = document.getElementById('et_id').value;
        document.getElementById('formAccionTarea').submit();
    }, null, { titulo: 'Eliminar Tarea' });
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
