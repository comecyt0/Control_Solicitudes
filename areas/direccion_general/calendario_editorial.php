<?php
/**
 * COMECyT — Calendario Editorial (Dirección General) v10.9
 * Contexto Area 2 (Dirección General).
 * Diseño Sticky Notes (Post-it), Sin Alertas de Navegador.
 * Restaurado Modal de Cumpleaños y Corregido Icono de Publicidad.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../config/auth.php';

verificarSesionAdmin();

$pdo = getConnection();
$mensajeFlash = '';
$tipoFlash = '';
$cveAreaUsuario  = (int) ($_SESSION['admin_cve_area'] ?? $_SESSION['user_cve_area'] ?? 0);
$cveAreaContexto = 2; // Dirección General
$adminId         = (int) ($_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 0);

// Definición de personal autorizado para este módulo (Sistemas, DG)
$esPersonalAutorizado = ($cveAreaUsuario === 1 || $cveAreaUsuario === $cveAreaContexto || $cveAreaUsuario === 4);

if ($cveAreaUsuario === 0) redirigir('public/hub.php');

// -------------------------------------------------------
// Helpers de sincronización: eventos públicos DG → tabla `eventos`
// Cuando un evento de df_eventos_editoriales se marca como público,
// se crea/actualiza un espejo en `eventos` (tabla institucional global)
// que todas las agendas de área consumen con WHERE publico=TRUE.
// El marcador [DG:{df_id}] en la descripción del espejo permite
// localizar y actualizar/eliminar el espejo en operaciones futuras.
// -------------------------------------------------------
function dgBuscarEspejo(PDO $pdo, int $dfId): ?int {
    $stmt = $pdo->prepare(
        "SELECT id FROM eventos WHERE descripcion LIKE ? LIMIT 1"
    );
    $stmt->execute(['%[DG:' . $dfId . ']%']);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? (int)$row['id'] : null;
}

function dgSincronizarPublico(PDO $pdo, int $dfId, string $titulo, string $descripcion, string $fi, string $ff, string $color): void {
    $marcador   = '[DG:' . $dfId . ']';
    $descEspejo = trim($descripcion . ' ' . $marcador);
    $espejoId   = dgBuscarEspejo($pdo, $dfId);
    if ($espejoId) {
        // Actualizar espejo existente
        $pdo->prepare(
            "UPDATE eventos SET titulo=?, descripcion=?, fecha_inicio=?, fecha_fin=?, color=?, publico=TRUE WHERE id=?"
        )->execute([$titulo, $descEspejo, $fi, $ff, $color, $espejoId]);
    } else {
        // Crear espejo nuevo
        $pdo->prepare(
            "INSERT INTO eventos (titulo, descripcion, fecha_inicio, fecha_fin, color, publico) VALUES (?,?,?,?,?,TRUE)"
        )->execute([$titulo, $descEspejo, $fi, $ff, $color]);
    }
}

function dgEliminarEspejo(PDO $pdo, int $dfId): void {
    $espejoId = dgBuscarEspejo($pdo, $dfId);
    if ($espejoId) {
        $pdo->prepare("DELETE FROM eventos WHERE id=?")->execute([$espejoId]);
    }
}

// -------------------------------------------------------
// Procesar acciones de Calendario (PRG)
// -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_accion'])) {
    try {
        validarCsrfPost();
        
        if (!$esPersonalAutorizado) {
            die("Acceso denegado: No tiene permisos de administración en el módulo de Dirección General.");
        }

        $accion = $_POST['_accion'];
        
        if ($accion === 'crear_evento') {
            $titulo = trim(postParam('titulo'));
            $descripcion = trim(postParam('descripcion'));
            $fechaInicio = postParam('fecha_inicio');
            $fechaFin = postParam('fecha_fin');
            $color = postParam('color', '#662331'); 
            $publico = isset($_POST['publico']);
            
            if ($titulo && $fechaInicio && $fechaFin) {
                $checkAdmin = $pdo->prepare("SELECT 1 FROM administradores WHERE id = ?");
                $checkAdmin->execute([$adminId]);
                $idAutor = $checkAdmin->fetch() ? $adminId : null;

                $stmt = $pdo->prepare("INSERT INTO df_eventos_editoriales (titulo, descripcion, fecha_inicio, fecha_fin, color, creado_por, publico, cve_area) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$titulo, $descripcion, $fechaInicio, $fechaFin, $color, $idAutor, $publico ? 'true' : 'false', $cveAreaContexto]);
                $nuevoId = (int) $pdo->lastInsertId();

                // Sincronizar con tabla `eventos` si es público
                if ($publico && $nuevoId > 0) {
                    dgSincronizarPublico($pdo, $nuevoId, $titulo, $descripcion, $fechaInicio, $fechaFin, $color);
                }

                header('Location: calendario_editorial.php?flash=evento_creado');
                exit;
            }
        } elseif ($accion === 'editar_evento') {
            $id = (int) postParam('evento_id');
            $esInstitucionalManual = (int) postParam('es_institucional');
            $titulo = trim(postParam('titulo'));
            $descripcion = trim(postParam('descripcion'));
            $fechaInicio = postParam('fecha_inicio');
            $fechaFin    = postParam('fecha_fin');
            $color       = postParam('color', '#662331');
            $publico     = isset($_POST['publico']);
            
            if ($id > 0 && $titulo && $fechaInicio && $fechaFin) {
                if ($esInstitucionalManual === 0) {
                    $stmt = $pdo->prepare("UPDATE df_eventos_editoriales SET titulo = ?, descripcion = ?, fecha_inicio = ?, fecha_fin = ?, color = ?, publico = ? WHERE id = ? AND cve_area = ?");
                    $stmt->execute([$titulo, $descripcion, $fechaInicio, $fechaFin, $color, ($publico ? 'true' : 'false'), $id, $cveAreaContexto]);

                    // Sincronizar con tabla `eventos` según el estado público
                    if ($publico) {
                        dgSincronizarPublico($pdo, $id, $titulo, $descripcion, $fechaInicio, $fechaFin, $color);
                    } else {
                        dgEliminarEspejo($pdo, $id); // Si se desmarca, quitar de calendarios de área
                    }
                } else {
                    $stmt = $pdo->prepare("UPDATE eventos SET titulo = ?, descripcion = ?, fecha_inicio = ?, fecha_fin = ?, color = ?, publico = ? WHERE id = ?");
                    $stmt->execute([$titulo, $descripcion, $fechaInicio, $fechaFin, $color, ($publico ? 'true' : 'false'), $id]);
                }
                header('Location: calendario_editorial.php?flash=evento_editado');
                exit;
            }
        } elseif ($accion === 'eliminar_evento') {
            $id = (int) postParam('evento_id');
            $esInstitucionalManual = (int) postParam('es_institucional');
            if ($id > 0) {
                if ($esInstitucionalManual === 0) {
                    // Eliminar espejo en `eventos` ANTES de borrar el evento de DG
                    dgEliminarEspejo($pdo, $id);
                    $stmt = $pdo->prepare("DELETE FROM df_eventos_editoriales WHERE id = ? AND cve_area = ?");
                    $stmt->execute([$id, $cveAreaContexto]);
                } else {
                    $stmt = $pdo->prepare("DELETE FROM eventos WHERE id = ?");
                    $stmt->execute([$id]);
                }
                header('Location: calendario_editorial.php?flash=evento_eliminado');
                exit;
            }
        } elseif ($accion === 'mover_tarea') {
            $id = (int) postParam('tarea_id');
            $nuevoEstatus = postParam('nuevo_estatus');
            if ($id > 0 && in_array($nuevoEstatus, ['pendiente', 'en_proceso', 'completada'])) {
                $stmt = $pdo->prepare("UPDATE sb_kanban_tareas SET estatus = ? WHERE id = ? AND cve_area = ?");
                $stmt->execute([$nuevoEstatus, $id, $cveAreaContexto]);
                header('Location: calendario_editorial.php?flash=tarea_movida#kanban');
                exit;
            }
        } elseif ($accion === 'eliminar_tarea') {
             $id = (int) postParam('tarea_id');
             if ($id > 0) {
                 $stmt = $pdo->prepare("DELETE FROM sb_kanban_tareas WHERE id = ? AND cve_area = ?");
                 $stmt->execute([$id, $cveAreaContexto]);
                 header('Location: calendario_editorial.php?flash=tarea_eliminada#kanban');
                 exit;
             }
        } elseif ($accion === 'crear_tarea' || $accion === 'editar_tarea') {
            $titulo = trim(postParam('titulo'));
            $descripcion = trim(postParam('descripcion'));
            $color = postParam('color', '#662331');
            $asignado_a = !empty($_POST['asignado_a']) ? (int)$_POST['asignado_a'] : null;
            if ($titulo) {
                if ($accion === 'crear_tarea') {
                    $stmt = $pdo->prepare("INSERT INTO sb_kanban_tareas (titulo, descripcion, color, estatus, creado_por, asignado_a, cve_area) VALUES (?, ?, ?, 'pendiente', ?, ?, ?)");
                    $stmt->execute([$titulo, $descripcion, $color, $adminId, $asignado_a, $cveAreaContexto]);
                } else {
                    $id = (int) postParam('tarea_id');
                    $stmt = $pdo->prepare("UPDATE sb_kanban_tareas SET titulo = ?, descripcion = ?, color = ?, asignado_a = ? WHERE id = ? AND cve_area = ?");
                    $stmt->execute([$titulo, $descripcion, $color, $asignado_a, $id, $cveAreaContexto]);
                }
                header('Location: calendario_editorial.php?flash=' . ($accion === 'crear_tarea' ? 'tarea_creada' : 'tarea_editada') . '#kanban');
                exit;
            }
        }
    } catch (Exception $e) {
        die("Error en la operación: " . $e->getMessage());
    }
}

// Mensajes Flash
$flashCode = getParam('flash');
$mensajes = ['evento_creado'=>'Evento agendado.','evento_editado'=>'Evento actualizado.','evento_eliminado'=>'Evento eliminado.','tarea_creada'=>'Compromiso añadido.','tarea_editada'=>'Tarea actualizada.','tarea_movida'=>'Estatus actualizado.','tarea_eliminada'=>'Tarea eliminada.'];
if (isset($mensajes[$flashCode])) { $mensajeFlash = $mensajes[$flashCode]; $tipoFlash = "success"; }

// Lógica de Calendario
$hoy = new DateTime();
$mes = (int) getParam('mes', $hoy->format('m'));
$anio = (int) getParam('anio', $hoy->format('Y'));
$dtMes = DateTime::createFromFormat('Y-m-d', sprintf('%04d-%02d-01', $anio, $mes));
$mesAnterior = (clone $dtMes)->modify('-1 month');
$mesSiguiente = (clone $dtMes)->modify('+1 month');
$diasEnMes = (int)$dtMes->format('t');
$diaSemanaInicio = (int)$dtMes->format('N'); 
$inicioMesBusqueda = $dtMes->format('Y-m-01 00:00:00');
$finMesBusqueda    = $mesSiguiente->format('Y-m-01 00:00:00');

// Consultas
$stmt = $pdo->prepare("SELECT id, titulo, descripcion, fecha_inicio, fecha_fin, color, publico, FALSE as es_institucional FROM df_eventos_editoriales WHERE cve_area = ? AND fecha_inicio < ? AND fecha_fin > ? ORDER BY fecha_inicio ASC");
$stmt->execute([$cveAreaContexto, $finMesBusqueda, $inicioMesBusqueda]);
$eventosRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmtG = $pdo->prepare("SELECT id, titulo, descripcion, fecha_inicio, fecha_fin, color, publico, TRUE as es_institucional FROM eventos WHERE (publico = TRUE) AND fecha_inicio < ? AND fecha_fin > ? ORDER BY fecha_inicio ASC");
$stmtG->execute([$finMesBusqueda, $inicioMesBusqueda]);
foreach ($stmtG->fetchAll(PDO::FETCH_ASSOC) as $eg) {
    if (strpos($eg['titulo'], '(Editorial)') === 0) continue;
    $eventosRaw[] = $eg;
}

$calendarioEventos = [];
foreach ($eventosRaw as $ev) {
    $dIni = new DateTime($ev['fecha_inicio']);
    $diaEv = (int)$dIni->format('d');
    if (!isset($calendarioEventos[$diaEv])) $calendarioEventos[$diaEv] = [];
    $ev['hora_formateada'] = $dIni->format('H:i');
    // Casteo súper-inclusivo para descartar problemas de formato
    $v = $ev['publico'];
    $ev['publico_raw'] = var_export($v, true);
    $ev['publico'] = ($v === true || $v === 't' || $v === 'true' || $v === 1 || $v === '1' || strtolower((string)$v) === 't' || strtolower((string)$v) === 'true');
    $calendarioEventos[$diaEv][] = $ev;
}

// Cumpleaños
if ($mes > 0 && $mes <= 12) {
    $stmtB = $pdo->prepare("SELECT nombre, appat, apmat, fecha_nacimiento, foto_perfil FROM cat_personal WHERE activo = TRUE AND fecha_nacimiento IS NOT NULL AND EXTRACT(MONTH FROM fecha_nacimiento) = ?");
    $stmtB->execute([$mes]);
    foreach ($stmtB->fetchAll(PDO::FETCH_ASSOC) as $cp) {
        $diaCumple = (int) (new DateTime($cp['fecha_nacimiento']))->format('d');
        $nombreCompleto = trim($cp['nombre'] . ' ' . $cp['appat'] . ' ' . $cp['apmat']);
        $fotoUrl = !empty($cp['foto_perfil']) ? BASE_URL . 'public/uploads/avatares/' . $cp['foto_perfil'] : '';
        $calendarioEventos[$diaCumple][] = ['id'=>null,'titulo'=>"🎂 ".$nombreCompleto,'descripcion'=>'Cumpleaños institucional','fecha_inicio'=>sprintf('%04d-%02d-%02d 00:00:00',$anio,$mes,$diaCumple),'fecha_fin'=>sprintf('%04d-%02d-%02d 23:59:59',$anio,$mes,$diaCumple),'color'=>'#B19A6D','publico'=>false,'es_cumple'=>true,'foto_perfil'=>$fotoUrl,'nombre_cumple'=>$nombreCompleto,'edad'=>'','hora_formateada'=>'Todo el día'];
    }
}

// Kanban y Personal
$listaTareas = ['pendiente'=>[],'en_proceso'=>[],'completada'=>[]];
$stmtT = $pdo->prepare("SELECT t.*, COALESCE(a.nombre, p.nombre) AS asignado_nombre FROM sb_kanban_tareas t LEFT JOIN administradores a ON t.asignado_a = a.id LEFT JOIN cat_personal p ON t.asignado_a = p.cve_personal WHERE t.cve_area = ? ORDER BY t.estatus DESC, t.id DESC");
$stmtT->execute([$cveAreaContexto]);
foreach ($stmtT->fetchAll(PDO::FETCH_ASSOC) as $t) { $listaTareas[$t['estatus']][] = $t; }

$stmtAdmins = $pdo->prepare("SELECT p.cve_personal AS id, CONCAT(p.nombre, ' ', p.appat, ' ', p.apmat) AS nombre FROM cat_personal p WHERE p.activo = true AND p.cve_area = ? ORDER BY p.nombre ASC");
$stmtAdmins->execute([$cveAreaContexto]);
$adminsDisponibles = $stmtAdmins->fetchAll(PDO::FETCH_ASSOC);

$mesesNombres = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];
$pageTitle = 'Dirección General — Agenda';

$extraHead = '
<style>
:root { --color-primary: #662331; --color-primary-dark: #4d1a25; }
.calendar-wrapper { background: #ffffff; border-radius: 16px; box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.08); overflow: hidden; margin-top: 1.5rem; border: 1px solid rgba(0,0,0,0.05); }
.calendar-header-nav { display: flex; justify-content: space-between; align-items: center; padding: 1.25rem 2rem; background: #fdfdfd; border-bottom: 1px solid rgba(0,0,0,0.06); }
.calendar-header-nav h3 { margin: 0; font-size: 1.4rem; font-weight: 700; color: var(--color-primary); }
.calendar-grid { display: grid; grid-template-columns: repeat(7, 1fr); background: #f8fafc; gap: 1px; }
.calendar-day-name { padding: 1rem 0.5rem; text-align: right; font-weight: 800; font-size: 0.75rem; color: #64748b; background: #ffffff; text-transform: uppercase; }
.calendar-cell { min-height: 140px; padding: 0.5rem; background: #ffffff; cursor: pointer; display: flex; flex-direction: column; gap: 0.4rem; overflow: hidden; border:none; border-right: 1px solid rgba(0,0,0,0.03); border-bottom: 1px solid rgba(0,0,0,0.03); }
.day-number { font-weight: 700; color: #334155; align-self: flex-end; }
.calendar-cell.today .day-number { color: #fff; background: var(--color-primary); border-radius: 50%; width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center; }

.evento-pildora { font-size: 0.75rem; padding: 0.5rem 0.6rem; border-radius: 2px 2px 12px 2px; color: #1e293b; margin-bottom: 0.25rem; position: relative; box-shadow: 2px 2px 4px rgba(0, 0, 0, 0.05), inset -10px -10px 20px rgba(0,0,0,0.03); border-top: 3px solid var(--color-primary); width:100%; box-sizing:border-box; transition: all 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275); display: flex; flex-direction: column; gap: 0.2rem; }
.evento-pildora:hover { transform: scale(1.02) translateY(-2px) rotate(-1deg); box-shadow: 4px 6px 12px rgba(0, 0, 0, 0.1); z-index: 10; }
.evento-pildora::after { content: ""; position: absolute; bottom: 0; right: 0; border-width: 0 0 10px 10px; border-style: solid; border-color: rgba(0,0,0,0.06) white; border-radius: 0 0 0 2px; }
.evento-titulo { font-weight: 700; display: flex; align-items: center; gap: 4px; width: 100%; min-width: 0; }
.evento-titulo span { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex: 1; min-width: 0; }
.evento-pildora .fa-earth-americas { flex-shrink: 0; }
.evento-hora { font-size: 0.65rem; opacity: 0.75; font-weight: 500; display: flex; align-items: center; gap: 3px; }
.evento-acciones { display: flex; gap: 4px; opacity: 0; max-height: 0; overflow: hidden; transition: all 0.2s; }
.evento-pildora:hover .evento-acciones { opacity: 1; max-height: 30px; margin-top: 4px; }
.btn-evento-accion { flex: 1; background: rgba(255,255,255,0.7); border: none; border-radius: 4px; padding: 4px 0; cursor: pointer; font-size: 0.75rem; color: #475569; display:flex; align-items:center; justify-content:center; }
.btn-evento-accion:hover { background:#fff; color:var(--color-primary); }

.kanban-board { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; padding: 1.5rem 0; }
.kanban-col { background: #f1f5f9; border-radius: 12px; min-height: 500px; border: 1px solid #e2e8f0; display: flex; flex-direction: column; }
.kanban-col-header { padding: 1.25rem; font-weight: 800; color: white; border-radius: 12px 12px 0 0; display: flex; justify-content: space-between; }
.bg-pendiente { background-color: #3b82f6; } .bg-en-proceso { background-color: #f59e0b; } .bg-completada { background-color: #10b981; }
.tarea-card { background: #fff; border-radius: 10px; padding: 1.25rem; margin: 10px; border: 1px solid #e2e8f0; border-top: 5px solid var(--color-primary); cursor: grab; }

.modal-backdrop { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(10px); z-index: 2000; align-items: center; justify-content: center; padding: 20px; }
.modal-backdrop.active { display: flex; }
.modal { background: #fff; width: 100%; max-width: 550px; border-radius: 24px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25), 0 0 0 1px rgba(0,0,0,0.05); overflow: hidden; animation: modalPop 0.3s cubic-bezier(0.34, 1.56, 0.64, 1); position: relative; border: none; }
@keyframes modalPop { from { transform: scale(0.95); opacity:0; } to { transform: scale(1); opacity:1; } }
.modal-header { background: linear-gradient(135deg, var(--color-primary), var(--color-primary-dark)); color: white; padding: 1.5rem; display: flex; justify-content: space-between; align-items: center; position: relative; border: none; }
.modal-header h3 { margin: 0; font-size: 1.25rem; font-weight: 800; letter-spacing: -0.02em; }
.modal-close { position: absolute; top: 1.25rem; right: 1.25rem; background: rgba(255,255,255,0.15); border: none; color: white; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; backdrop-filter: blur(4px); }
.modal-close:hover { background: rgba(255,255,255,0.25); transform: rotate(90deg); }
.modal-body { padding: 1.5rem; }
.modal-footer { padding: 1.25rem 1.5rem; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; gap: 0.75rem; }
.nota-dorado { background: #fef08a !important; border-top-color: #ca8a04 !important; }
.cumple-mini-avatar { width: 22px; height: 22px; border-radius: 50%; object-fit: cover; border: 1.5px solid #ca8a04; }
.cumple-mini-placeholder { background: #fef08a; width: 22px; height: 22px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 0.65rem; color: #ca8a04; border: 1.5px solid #ca8a04; }
.modal .form-label { font-weight: 700; color: #334155; font-size: 0.85rem; margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.025em; }
.modal .form-control { border-radius: 12px; border: 1px solid #e2e8f0; padding: 0.75rem; font-size: 0.95rem; transition: all 0.2s; background: #f8fafc; }
.modal .form-control:focus { background: #fff; border-color: var(--color-primary); box-shadow: 0 0 0 4px rgba(102, 35, 49, 0.1); outline: none; }
</style>';

require_once __DIR__ . '/../../includes/header_admin.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
    <div><h2 style="font-weight: 800; color: #0f172a; margin: 0;"><i class="fa-solid fa-calendar-check" style="color:var(--color-primary);"></i> Agenda de Dirección</h2><p style="color: #64748b; margin: 0;">Gestión de la agenda institucional y compromisos de área.</p></div>
    <button class="btn btn-primary" onclick="abrirModalCrear()"><i class="fa-solid fa-plus"></i> Nuevo Evento</button>
</div>

<?php if ($mensajeFlash): ?><div class="alert alert-<?= $tipoFlash ?> alert-dismissible fade show"><?= esc($mensajeFlash) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<div class="calendar-wrapper">
    <div class="calendar-header-nav">
        <h3><?= $mesesNombres[$mes] ?> <?= $anio ?></h3>
        <div class="nav-btn-group">
            <a href="?mes=<?= $mesAnterior->format('m') ?>&anio=<?= $mesAnterior->format('Y') ?>" class="btn btn-outline"><i class="fa-solid fa-chevron-left"></i></a>
            <a href="?mes=<?= date('m') ?>&anio=<?= date('Y') ?>" class="btn btn-outline">Hoy</a>
            <a href="?mes=<?= $mesSiguiente->format('m') ?>&anio=<?= $mesSiguiente->format('Y') ?>" class="btn btn-outline"><i class="fa-solid fa-chevron-right"></i></a>
        </div>
    </div>
    <div class="calendar-grid">
        <?php foreach (['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'] as $d): ?><div class="calendar-day-name"><?= $d ?></div><?php endforeach; ?>
        <?php for ($i = 1; $i < $diaSemanaInicio; $i++): ?><div class="calendar-cell empty"></div><?php endfor; ?>
        <?php for ($dia = 1; $dia <= $diasEnMes; $dia++): 
            $esHoy = ($dia===(int)date('d')&&$mes===(int)date('m')&&$anio===(int)date('Y'));
            $fIso = sprintf('%04d-%02d-%02d', $anio, $mes, $dia);
        ?>
            <div class="calendar-cell <?= $esHoy ? 'today' : '' ?>" onclick="abrirModalCrearDesdeCelda('<?= $fIso ?>')">
                <div class="day-number"><?= $dia ?></div>
                <?php foreach ($calendarioEventos[$dia] ?? [] as $ev): 
                    $esCumple = isset($ev['es_cumple']);
                    $colorNota = $esCumple ? 'nota-dorado' : '';
                    $cStyle = (!$esCumple&&!empty($ev['color'])) ? 'style="border-top-color:'.$ev['color'].'; background-color:'.$ev['color'].'1a;"' : '';
                ?>
                    <div class="evento-pildora <?= $colorNota ?>" <?= $cStyle ?> onclick="event.stopPropagation()" data-pub-debug="<?= esc($ev['publico_raw']??'') ?>">
                        <div class="evento-titulo">
                            <?php if ($esCumple): ?>
                                <?php if (!empty($ev['foto_perfil'])): ?><img src="<?= esc($ev['foto_perfil']) ?>" class="cumple-mini-avatar"><?php else: ?><span class="cumple-mini-placeholder"><i class="fa-solid fa-user"></i></span><?php endif; ?>
                            <?php endif; ?>
                            <?php if (!$esCumple && $ev['publico']): ?>
                                <i class="fa-solid fa-earth-americas" style="color:#3b82f6; flex-shrink:0;" title="Visible al Público"></i>
                            <?php endif; ?>
                            <span><?= esc($ev['titulo']) ?></span>
                        </div>
                        <div class="evento-hora"><i class="fa-regular fa-clock"></i> <?= $ev['hora_formateada'] ?></div>
                        <div class="evento-acciones">
                            <?php if($esCumple): ?>
                                <button type="button" class="btn-evento-accion" onclick="abrirModalCumple('<?=esc(addslashes($ev['nombre_cumple']))?>','','<?=$ev['foto_perfil']?>','<?=$ev['edad']?>','<?=date('d/m', strtotime($ev['fecha_inicio']))?>')"><i class="fa-solid fa-cake-candles"></i></button>
                            <?php else: ?>
                                <button type="button" class="btn-evento-accion" onclick="abrirModalVer('<?=esc(addslashes($ev['titulo']))?>','<?=esc(addslashes($ev['descripcion']))?>','<?=date('d/m/Y H:i',strtotime($ev['fecha_inicio'])) . ' a ' . date('d/m/Y H:i',strtotime($ev['fecha_fin']))?>')"><i class="fa-solid fa-eye"></i></button>
                                <?php if($esPersonalAutorizado): ?>
                                <button type="button" class="btn-evento-accion" onclick="abrirModalEditar(<?=$ev['id']?>,'<?=esc(addslashes($ev['titulo']))?>','<?=esc(addslashes($ev['descripcion']))?>','<?=date('Y-m-d\TH:i',strtotime($ev['fecha_inicio']))?>','<?=date('Y-m-d\TH:i',strtotime($ev['fecha_fin']))?>','<?=$ev['color']?>',<?=($ev['publico']?1:0)?>,<?=($ev['es_institucional']?1:0)?>)"><i class="fa-solid fa-pen"></i></button>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endfor; ?>
    </div>
</div>

<div id="kanban" style="margin-top: 50px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h3 style="font-weight: 800; color: #1e293b; margin:0;"><i class="fa-solid fa-list-check" style="color:#3b82f6;"></i> Compromisos Dirección</h3>
        <button class="btn btn-primary" onclick="abrirModalCrearTarea()" style="background:#3b82f6; border:none;"><i class="fa-solid fa-plus"></i> Nueva Tarea</button>
    </div>
    <div class="kanban-board">
        <?php foreach (['pendiente' => 'Pendiente', 'en_proceso' => 'En Proceso', 'completada' => 'Completada'] as $idCol => $lblCol): ?>
            <div class="kanban-col" ondragover="allowDrop(event)" ondrop="drop(event, '<?= $idCol ?>')">
                <div class="kanban-col-header bg-<?= str_replace('_','-',$idCol) ?>"><span><?= strtoupper($lblCol) ?></span><span class="kanban-badge"><?= count($listaTareas[$idCol]) ?></span></div>
                <div class="kanban-col-body">
                    <?php foreach ($listaTareas[$idCol] as $t): ?>
                        <div class="tarea-card" draggable="true" ondragstart="drag(event, <?= $t['id'] ?>)" style="border-top-color: <?= $t['color'] ?>;" onclick="abrirModalEditarTarea(<?= $t['id'] ?>, '<?= esc(addslashes($t['titulo'])) ?>', '<?= esc(addslashes($t['descripcion'])) ?>', '<?= $t['color'] ?>', <?= (int)$t['asignado_a'] ?>)">
                            <h4 style="margin:0 0 5px; font-size:1rem;"><?= esc($t['titulo']) ?></h4><p style="font-size:0.8rem; color:#64748b;"><?= esc(mb_strimwidth($t['descripcion'], 0, 80, '...')) ?></p>
                            <div style="margin-top:10px; font-size:0.7rem; color:#94a3b8; display:flex; justify-content:space-between; align-items:center;"><span><i class="fa-solid fa-user-tag"></i> <?= esc($t['asignado_nombre'] ?: 'Sin asignar') ?></span><i class="fa-solid fa-grip-lines" style="opacity:0.3;"></i></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Modales -->
<div class="modal-backdrop" id="modalCrearEvento">
    <div class="modal">
        <div class="modal-header">
            <h3>Agendar Evento</h3>
            <button type="button" onclick="cerrarModal('modalCrearEvento')" class="modal-close"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST"><?= csrfField() ?>
            <input type="hidden" name="_accion" value="crear_evento">
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Título</label><input type="text" name="titulo" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Descripción</label><textarea name="descripcion" class="form-control" rows="2"></textarea></div>
                <div class="row mb-3">
                    <div class="col"><label class="form-label">Desde</label><input type="datetime-local" name="fecha_inicio" id="c_fecha_inicio" class="form-control" required></div>
                    <div class="col"><label class="form-label">Hasta</label><input type="datetime-local" name="fecha_fin" id="c_fecha_fin" class="form-control" required></div>
                </div>
                <div class="mb-3"><label class="form-label">Color</label><input type="color" name="color" value="#662331" class="form-control" style="height:40px;"></div>
                <div class="form-check"><input type="checkbox" name="publico" value="1" id="c_pub" class="form-check-input"><label for="c_pub">Hacer público</label></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary w-100">Guardar Evento</button></div>
        </form>
    </div>
</div>

<div class="modal-backdrop" id="modalVerEvento">
    <div class="modal">
        <div class="modal-header">
            <h3>Detalle del Evento</h3>
            <button type="button" onclick="cerrarModal('modalVerEvento')" class="modal-close"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <p style="margin-bottom: 0.5rem; color: #64748b; font-size: 0.85rem; font-weight: 700; text-transform: uppercase;">Horario</p>
            <p id="v_horario" style="font-weight: 600; color: #1e293b; margin-bottom: 1.5rem;"></p>
            <p style="margin-bottom: 0.5rem; color: #64748b; font-size: 0.85rem; font-weight: 700; text-transform: uppercase;">Descripción</p>
            <div id="v_descripcion" style="color: #475569; line-height: 1.6;"></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline w-100" onclick="cerrarModal('modalVerEvento')">Cerrar</button>
        </div>
    </div>
</div>

<div class="modal-backdrop" id="modalEditarEvento">
    <div class="modal">
        <div class="modal-header">
            <h3>Editar Evento</h3>
            <button type="button" onclick="cerrarModal('modalEditarEvento')" class="modal-close"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" id="formEditarEvento"><?= csrfField() ?>
            <input type="hidden" name="_accion" value="editar_evento" id="e_accion">
            <input type="hidden" name="evento_id" id="e_id">
            <input type="hidden" name="es_institucional" id="e_es_inst">
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Título</label><input type="text" name="titulo" id="e_titulo" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Descripción</label><textarea name="descripcion" id="e_descripcion" class="form-control" rows="2"></textarea></div>
                <div class="row mb-3">
                    <div class="col"><label class="form-label">Desde</label><input type="datetime-local" name="fecha_inicio" id="e_inicio" class="form-control" required></div>
                    <div class="col"><label class="form-label">Hasta</label><input type="datetime-local" name="fecha_fin" id="e_fin" class="form-control" required></div>
                </div>
                <div class="mb-3"><label class="form-label">Color</label><input type="color" name="color" id="e_color" class="form-control" style="height:40px;"></div>
                <div class="form-check"><input type="checkbox" name="publico" value="1" id="e_pub" class="form-check-input"><label for="e_pub">Hacer público</label></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" onclick="confirmarEliminar()">Eliminar</button>
                <button type="submit" class="btn btn-primary" style="flex:1;">Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>

<!-- Modales Kanban -->
<div class="modal-backdrop" id="modalCrearTarea">
    <div class="modal">
        <div class="modal-header" style="background:#3b82f6;">
            <h3>Nueva Tarea</h3>
            <button type="button" onclick="cerrarModal('modalCrearTarea')" class="modal-close"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST"><?= csrfField() ?>
            <input type="hidden" name="_accion" value="crear_tarea">
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Título</label><input type="text" name="titulo" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Descripción</label><textarea name="descripcion" class="form-control" rows="3"></textarea></div>
                <div class="mb-3"><label class="form-label">Asignado a</label>
                    <select name="asignado_a" class="form-control">
                        <option value="">-- Sin Asignar --</option>
                        <?php foreach ($adminsDisponibles as $adm): ?>
                            <option value="<?= $adm['id'] ?>"><?= esc($adm['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3"><label class="form-label">Color</label><input type="color" name="color" value="#3b82f6" class="form-control" style="height:40px;"></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary w-100" style="background:#3b82f6; border:none;">Crear Tarea</button></div>
        </form>
    </div>
</div>

<div class="modal-backdrop" id="modalEditarTarea">
    <div class="modal">
        <div class="modal-header" style="background:#3b82f6;">
            <h3>Editar Tarea</h3>
            <button type="button" onclick="cerrarModal('modalEditarTarea')" class="modal-close"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST"><?= csrfField() ?>
            <input type="hidden" name="_accion" value="editar_tarea">
            <input type="hidden" name="tarea_id" id="et_id">
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Título</label><input type="text" name="titulo" id="et_titulo" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Descripción</label><textarea name="descripcion" id="et_descripcion" class="form-control" rows="3"></textarea></div>
                <div class="mb-3"><label class="form-label">Asignado a</label>
                    <select name="asignado_a" id="et_asignado_a" class="form-control">
                        <option value="">-- Sin Asignar --</option>
                        <?php foreach ($adminsDisponibles as $adm): ?>
                            <option value="<?= $adm['id'] ?>"><?= esc($adm['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3"><label class="form-label">Color</label><input type="color" name="color" id="et_color" class="form-control" style="height:40px;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" onclick="eliminarTareaDesdeModal()">Eliminar</button>
                <button type="submit" class="btn btn-primary" style="background:#3b82f6; border:none; flex:1;">Actualizar</button>
            </div>
        </form>
    </div>
</div>

<form id="formAccionTarea" method="POST" style="display:none;"><?= csrfField() ?><input type="hidden" name="_accion" id="t_accion"><input type="hidden" name="tarea_id" id="t_tarea_id"><input type="hidden" name="nuevo_estatus" id="t_nuevo_estatus"></form>

<div class="modal-backdrop" id="modalConfirmar" style="z-index: 3000;">
    <div class="modal" style="max-width:380px;">
        <div class="modal-body text-center p-5">
            <div style="width: 80px; height: 80px; background: #fee2e2; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem;">
                <i class="fa-solid fa-trash-can text-danger" style="font-size:2.5rem;"></i>
            </div>
            <h3 style="font-weight: 800; color: #1e293b; margin-bottom: 0.5rem;">¿Estás seguro?</h3>
            <p style="color: #64748b; line-height: 1.5;">Esta acción eliminará el registro de forma permanente. No podrás deshacer este cambio.</p>
            <div class="d-flex gap-2 justify-content-center mt-4">
                <button class="btn btn-outline" style="flex:1; border-radius: 12px;" onclick="cerrarModal('modalConfirmar')">Cancelar</button>
                <button class="btn btn-danger" id="btnConfirmarAccion" style="flex:1; border-radius: 12px; font-weight: 700;">Sí, Eliminar</button>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../admin/modales/modal_cumple.php'; ?>

<script>
function abrirModal(id) { document.getElementById(id).classList.add('active'); }
function cerrarModal(id) { document.getElementById(id).classList.remove('active'); }
function abrirModalCrear() { abrirModal('modalCrearEvento'); }
function abrirModalCrearDesdeCelda(f) { document.getElementById('c_fecha_inicio').value = f+'T09:00'; document.getElementById('c_fecha_fin').value = f+'T10:00'; abrirModal('modalCrearEvento'); }
function abrirModalVer(t,d,h) { document.getElementById('v_horario').textContent = h; document.getElementById('v_descripcion').textContent = d||'Sin descripción.'; abrirModal('modalVerEvento'); }
function abrirModalEditar(id,t,d,i,f,c,p,inst) {
    document.getElementById('e_id').value = id; document.getElementById('e_titulo').value = t; document.getElementById('e_descripcion').value = d; document.getElementById('e_inicio').value = i; document.getElementById('e_fin').value = f; document.getElementById('e_color').value = c; document.getElementById('e_pub').checked=(p===1||p===true); document.getElementById('e_es_inst').value = inst;
    abrirModal('modalEditarEvento');
}
function abrirModalCumple(nombre, desc, fotoUrl, edad, fecha) {
    document.getElementById('mc_nombre').textContent = nombre;
    document.getElementById('mc_nombre_grande').textContent = nombre;
    document.getElementById('mc_fecha').textContent = fecha;
    const imgEl = document.getElementById('mc_foto');
    const phEl = document.getElementById('mc_foto_placeholder');
    if (fotoUrl && fotoUrl.trim() !== '') { imgEl.src = fotoUrl; imgEl.style.display = 'block'; phEl.style.display = 'none'; } 
    else { imgEl.style.display = 'none'; phEl.style.display = 'flex'; }
    abrirModal('modalVerCumple');
}
function confirmarEliminar() {
    document.getElementById('btnConfirmarAccion').onclick = function() {
        document.getElementById('e_accion').value = 'eliminar_evento';
        document.getElementById('formEditarEvento').submit();
    };
    abrirModal('modalConfirmar');
}
function allowDrop(ev) { ev.preventDefault(); }
function drag(ev, id) { ev.dataTransfer.setData("text", id); }
function drop(ev, nuevo) {
    ev.preventDefault();
    const id = ev.dataTransfer.getData("text");
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
    document.getElementById('et_color').value = c;
    document.getElementById('et_asignado_a').value = a || '';
    abrirModal('modalEditarTarea');
}
function eliminarTareaDesdeModal() {
    document.getElementById('btnConfirmarAccion').onclick = function() {
        document.getElementById('t_accion').value = 'eliminar_tarea';
        document.getElementById('t_tarea_id').value = document.getElementById('et_id').value;
        document.getElementById('formAccionTarea').submit();
    };
    abrirModal('modalConfirmar');
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
