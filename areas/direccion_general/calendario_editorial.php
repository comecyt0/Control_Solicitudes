<?php
/**
 * COMECyT — Calendario Editorial (Dirección General) v10.4
 * Diseño y funcionalidad sincronizados con Difusión pero con permisos elevados.
 * Filtra eventos de df_eventos_editoriales y tareas de sb_kanban_tareas por cve_area = 4.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../config/auth.php';

verificarSesionAdmin();

$pdo = getConnection();
$mensajeFlash = '';
$tipoFlash = '';
$cveAreaUsuario  = (int) ($_SESSION['admin_cve_area'] ?? $_SESSION['user_cve_area'] ?? 0);
$cveAreaContexto = 4; // Dirección General
$adminId         = (int) ($_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 0);

// DEBUG (Temporal): Activo para diagnosticar fallos en producción
// file_put_contents(__DIR__ . '/../../debug_dg.txt', "[".date('Y-m-d H:i:s')."] User: $adminId | Area: $cveAreaUsuario | POST: " . json_encode($_POST) . "\n", FILE_APPEND);

if ($cveAreaUsuario === 0) redirigir('public/hub.php');

// -------------------------------------------------------
// Procesar acciones de Calendario (PRG)
// -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_accion'])) {
    try {
        validarCsrfPost();
        $accion = $_POST['_accion'];
        
        if ($accion === 'crear_evento') {
            $titulo = trim(postParam('titulo'));
            $descripcion = trim(postParam('descripcion'));
            $fechaInicio = postParam('fecha_inicio');
            $fechaFin = postParam('fecha_fin');
            $color = postParam('color', '#662331'); 
            $publico = isset($_POST['publico']) ? true : false;
            
            if ($titulo && $fechaInicio && $fechaFin) {
                // Verificar si es administrador o personal para el ID de autor
                $checkAdmin = $pdo->prepare("SELECT 1 FROM administradores WHERE id = ?");
                $checkAdmin->execute([$adminId]);
                $idAutor = $checkAdmin->fetch() ? $adminId : null;

                $stmt = $pdo->prepare("INSERT INTO df_eventos_editoriales (titulo, descripcion, fecha_inicio, fecha_fin, color, creado_por, publico, cve_area) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$titulo, $descripcion, $fechaInicio, $fechaFin, $color, $idAutor, $publico ? 1 : 0, $cveAreaContexto]);
                
                header('Location: calendario_editorial.php?flash=evento_creado');
                exit;
            }
        } elseif ($accion === 'editar_evento') {
            $id = (int) postParam('evento_id');
            $esInstitucionalManual = (int) postParam('es_institucional');
            
            // PERMISOS DG: Pueden editar institucionales (Área 4) o Sistemas (Área 1)
            if ($esInstitucionalManual === 1 && $cveAreaUsuario !== 1 && $cveAreaUsuario !== 4) {
                 die("Acceso denegado: Solo Dirección y Sistemas gestionan la agenda global.");
            }

            $titulo = trim(postParam('titulo'));
            $descripcion = trim(postParam('descripcion'));
            $fechaInicio = postParam('fecha_inicio');
            $fechaFin    = postParam('fecha_fin');
            $color       = postParam('color', '#662331');
            $publico     = isset($_POST['publico']) ? true : false;
            
            if ($id > 0 && $titulo && $fechaInicio && $fechaFin) {
                if ($esInstitucionalManual === 0) {
                    $stmt = $pdo->prepare("UPDATE df_eventos_editoriales SET titulo = ?, descripcion = ?, fecha_inicio = ?, fecha_fin = ?, color = ?, publico = ? WHERE id = ? AND cve_area = ?");
                    $stmt->execute([$titulo, $descripcion, $fechaInicio, $fechaFin, $color, ($publico ? 1 : 0), $id, $cveAreaContexto]);
                } else {
                    // Es institucional (Global)
                    $stmt = $pdo->prepare("UPDATE eventos SET titulo = ?, descripcion = ?, fecha_inicio = ?, fecha_fin = ?, color = ?, publico = ? WHERE id = ?");
                    $stmt->execute([$titulo, $descripcion, $fechaInicio, $fechaFin, $color, ($publico ? 1 : 0), $id]);
                }
                header('Location: calendario_editorial.php?flash=evento_editado');
                exit;
            }
        } elseif ($accion === 'eliminar_evento') {
            $id = (int) postParam('evento_id');
            $esInstitucionalManual = (int) postParam('es_institucional');
            
            if ($id > 0) {
                if ($esInstitucionalManual === 1 && $cveAreaUsuario !== 1 && $cveAreaUsuario !== 4) {
                    die("Acceso denegado.");
                }

                if ($esInstitucionalManual === 0) {
                    $stmt = $pdo->prepare("DELETE FROM df_eventos_editoriales WHERE id = ? AND cve_area = ?");
                    $stmt->execute([$id, $cveAreaContexto]);
                } else {
                    $stmt = $pdo->prepare("DELETE FROM eventos WHERE id = ?");
                    $stmt->execute([$id]);
                }
                header('Location: calendario_editorial.php?flash=evento_eliminado');
                exit;
            }
        } elseif ($accion === 'crear_tarea') {
            $titulo = trim(postParam('titulo'));
            $descripcion = trim(postParam('descripcion'));
            $color = postParam('color', '#662331');
            $asignado_a = !empty($_POST['asignado_a']) ? (int)$_POST['asignado_a'] : null;
            
            if ($titulo) {
                $stmt = $pdo->prepare("INSERT INTO sb_kanban_tareas (titulo, descripcion, color, estatus, creado_por, asignado_a, cve_area) VALUES (?, ?, ?, 'pendiente', ?, ?, ?)");
                $stmt->execute([$titulo, $descripcion, $color, $adminId, $asignado_a, $cveAreaContexto]);
                header('Location: calendario_editorial.php?flash=tarea_creada#kanban');
                exit;
            }
        } elseif ($accion === 'editar_tarea') {
            $id = (int) postParam('tarea_id');
            $titulo = trim(postParam('titulo'));
            $descripcion = trim(postParam('descripcion'));
            $color = postParam('color', '#662331');
            $asignado_a = !empty($_POST['asignado_a']) ? (int)$_POST['asignado_a'] : null;
            
            if ($id > 0 && $titulo) {
                $stmt = $pdo->prepare("UPDATE sb_kanban_tareas SET titulo = ?, descripcion = ?, color = ?, asignado_a = ? WHERE id = ? AND cve_area = ?");
                $stmt->execute([$titulo, $descripcion, $color, $asignado_a, $id, $cveAreaContexto]);
                header('Location: calendario_editorial.php?flash=tarea_editada#kanban');
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
        }
    } catch (Exception $e) {
        die("Error en la operación: " . $e->getMessage());
    }
}

// Leer flash redirect
$flashCode = getParam('flash');
if ($flashCode === 'evento_creado') { $mensajeFlash = "Evento interno agendado."; $tipoFlash = "success"; }
elseif ($flashCode === 'evento_editado') { $mensajeFlash = "Evento actualizado."; $tipoFlash = "success"; }
elseif ($flashCode === 'evento_eliminado') { $mensajeFlash = "Evento eliminado."; $tipoFlash = "success"; }
elseif ($flashCode === 'tarea_creada') { $mensajeFlash = "Compromiso añadido."; $tipoFlash = "success"; }
elseif ($flashCode === 'tarea_editada') { $mensajeFlash = "Tarea actualizada."; $tipoFlash = "success"; }
elseif ($flashCode === 'tarea_movida') { $mensajeFlash = "Estatus actualizado."; $tipoFlash = "success"; }
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

// 1. Consultar eventos de DIRECCIÓN (Propios del área)
$stmt = $pdo->prepare("SELECT id, titulo, descripcion, fecha_inicio, fecha_fin, color, publico FROM df_eventos_editoriales WHERE cve_area = ? AND fecha_inicio < ? AND fecha_fin > ? ORDER BY fecha_inicio ASC");
$stmt->execute([$cveAreaContexto, $finMesBusqueda, $inicioMesBusqueda]);
$eventosRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 2. Consultar eventos INSTITUCIONALES
$stmtG = $pdo->prepare("SELECT id, titulo, descripcion, fecha_inicio, fecha_fin, color, publico, TRUE as es_institucional FROM eventos WHERE (publico = TRUE) AND fecha_inicio < ? AND fecha_fin > ? ORDER BY fecha_inicio ASC");
$stmtG->execute([$finMesBusqueda, $inicioMesBusqueda]);
$eventosGlobales = $stmtG->fetchAll(PDO::FETCH_ASSOC);

foreach ($eventosGlobales as $eg) {
    if (strpos($eg['titulo'], '(Editorial)') === 0) continue;
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

// 5. Consultar tareas KANBAN filtradas por ÁREA 4
$listaTareas = ['pendiente' => [], 'en_proceso' => [], 'completada' => []];
$stmtT = $pdo->prepare("SELECT t.*, COALESCE(a.nombre, p.nombre) AS asignado_nombre 
         FROM sb_kanban_tareas t 
         LEFT JOIN administradores a ON t.asignado_a = a.id 
         LEFT JOIN cat_personal p ON t.asignado_a = p.cve_personal 
         WHERE t.cve_area = ? 
         ORDER BY t.estatus DESC, t.id DESC");
$stmtT->execute([$cveAreaContexto]);
$tareas = $stmtT->fetchAll(PDO::FETCH_ASSOC);
foreach ($tareas as $t) {
    if (isset($listaTareas[$t['estatus']])) $listaTareas[$t['estatus']][] = $t;
    else $listaTareas['pendiente'][] = $t;
}

// 6. Personal para asignación
$stmtAdmins = $pdo->prepare("SELECT p.cve_personal AS id, 
                CONCAT(p.nombre, ' ', p.appat, ' ', p.apmat) AS nombre 
         FROM cat_personal p
         WHERE p.activo = true AND p.cve_area = ? 
         ORDER BY p.nombre ASC");
$stmtAdmins->execute([$cveAreaContexto]);
$adminsDisponibles = $stmtAdmins->fetchAll(PDO::FETCH_ASSOC);

$mesesNombres = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];
$pageTitle = 'Dirección General — Agenda';
$activeMenu = 'calendario';

$extraHead = '
<style>
:root {
    --color-primary: #662331;
    --color-primary-dark: #4d1a25;
    --color-primary-light: #8a3a4a;
    --color-primary-hover: #7a2c3b;
    --color-accent: #B19A6D;
}

.calendar-wrapper { background: #ffffff; border-radius: 16px; box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.08); overflow: hidden; margin-top: 1.5rem; border: 1px solid rgba(0,0,0,0.05); }
.calendar-header-nav { display: flex; justify-content: space-between; align-items: center; padding: 1.25rem 2rem; background: #fdfdfd; border-bottom: 1px solid rgba(0,0,0,0.06); }
.calendar-header-nav h3 { margin: 0; font-size: 1.4rem; font-weight: 700; color: var(--color-primary); }
.nav-btn-group { display: flex; gap: 0.5rem; }
.calendar-grid { display: grid; grid-template-columns: repeat(7, 1fr); background: #f8fafc; gap: 1px; }
.calendar-day-name { padding: 1rem 0.5rem; text-align: right; font-weight: 800; font-size: 0.75rem; color: #64748b; background: #ffffff; text-transform: uppercase; }
.calendar-cell { min-height: 140px; padding: 0.5rem; background: #ffffff; cursor: pointer; display: flex; flex-direction: column; gap: 0.4rem; overflow: hidden; }
.calendar-cell.today .day-number { color: #fff; background: var(--color-primary); border-radius: 50%; width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center; }
.day-number { font-weight: 700; color: #334155; align-self: flex-end; }

.evento-pildora { font-size: 0.75rem; padding: 0.5rem 0.6rem; border-radius: 2px 2px 12px 2px; color: #1e293b; margin-bottom: 0.25rem; position: relative; box-shadow: 2px 2px 4px rgba(0, 0, 0, 0.05); border-top: 3px solid var(--color-primary); width:100%; box-sizing:border-box;}
.evento-pildora:hover { transform: scale(1.02); z-index: 10; cursor: move; }
.evento-titulo { font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: flex; align-items: center; gap: 5px; }
.evento-acciones { display: flex; gap: 4px; opacity: 0; max-height: 0; overflow: hidden; transition: all 0.2s; }
.evento-pildora:hover .evento-acciones { opacity: 1; max-height: 30px; margin-top: 4px; }
.btn-evento-accion { flex: 1; background: rgba(255,255,255,0.7); border: none; border-radius: 4px; padding: 4px 0; cursor: pointer; font-size: 0.75rem; color: #475569; }

.kanban-board { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; padding: 1.5rem 0; }
.kanban-col { background: #f1f5f9; border-radius: 12px; min-height: 500px; display: flex; flex-direction: column; border: 1px solid #e2e8f0; }
.kanban-col-header { padding: 1.25rem; font-weight: 800; color: white; border-radius: 12px 12px 0 0; display: flex; justify-content: space-between; }
.bg-pendiente { background-color: #3b82f6; }
.bg-en-proceso { background-color: #f59e0b; }
.bg-completada { background-color: #10b981; }
.tarea-card { background: #fff; border-radius: 10px; padding: 1.25rem; margin: 10px; border: 1px solid #e2e8f0; border-top: 5px solid var(--color-primary); cursor: grab; }

.modal-backdrop { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(8px); z-index: 2000; align-items: center; justify-content: center; }
.modal-backdrop.active { display: flex; }
.modal { background: #fff; width: 100%; max-width: 550px; border-radius: 20px; box-shadow: 0 25px 50px rgba(0,0,0,0.2); overflow: hidden; }
.modal-header { background: linear-gradient(135deg, var(--color-primary), var(--color-primary-dark)); color: white; padding: 1.25rem 1.5rem; display: flex; justify-content: space-between; align-items: center; }
.modal-body { padding: 1.5rem 2rem; }
.modal-footer { padding: 1.25rem 2rem; background: #f8fafc; border-top: 1px solid #f1f5f9; display: flex; justify-content: flex-end; gap: 10px; }
.form-control { border-radius: 8px; border: 1px solid #e2e8f0; padding: 10px 12px; width: 100%; }
.form-label { font-weight: 700; color: #475569; font-size: 0.85rem; margin-bottom: 4px; display: block; }

.checkbox-wrapper { display: flex; align-items: center; gap: 10px; margin-top: 10px; padding: 10px; background: #fff1f2; border-radius: 8px; border: 1px solid #fecaca; }
.checkbox-wrapper input { width: 18px; height: 18px; cursor: pointer; }
.checkbox-wrapper label { cursor: pointer; font-weight: 700; color: #be123c; font-size: 0.85rem; }

.nota-dorado { background: #fef08a !important; border-top-color: #ca8a04 !important; }
.cumple-mini-avatar { width: 22px; height: 22px; border-radius: 50%; object-fit: cover; border: 1.5px solid #ca8a04; }
</style>';

require_once __DIR__ . '/../../includes/header_admin.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
    <div>
        <h2 style="font-weight: 800; color: #0f172a; margin: 0;"><i class="fa-solid fa-calendar-check" style="color:var(--color-primary);"></i> Agenda de Dirección</h2>
        <p style="color: #64748b; margin: 0;">Gestión de la agenda institucional y compromisos de área.</p>
    </div>
    <div style="display: flex; gap: 10px;">
        <button class="btn btn-primary" onclick="abrirModalCrear()"><i class="fa-solid fa-plus"></i> Nuevo Evento</button>
    </div>
</div>

<?php if ($mensajeFlash): ?><div class="alert alert-<?= $tipoFlash ?> alert-dismissible fade show" role="alert"><?= esc($mensajeFlash) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

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
            $esHoy = ($dia === (int)date('d') && $mes === (int)date('m') && $anio === (int)date('Y'));
            $fechaIso = sprintf('%04d-%02d-%02d', $anio, $mes, $dia);
        ?>
            <div class="calendar-cell <?= $esHoy ? 'today' : '' ?>" onclick="abrirModalCrearDesdeCelda('<?= $fechaIso ?>', '<?= $fechaIso ?>')">
                <div class="day-number"><?= $dia ?></div>
                <?php foreach ($calendarioEventos[$dia] ?? [] as $ev): 
                    $colorNota = isset($ev['es_cumple']) ? 'nota-dorado' : '';
                    $customStyle = (!isset($ev['es_cumple']) && !empty($ev['color'])) ? 'style="border-top-color: '.$ev['color'].'; background-color: '.$ev['color'].'1a;"' : '';
                ?>
                    <div class="evento-pildora <?= $colorNota ?>" <?= $customStyle ?>>
                        <div class="evento-titulo">
                            <?php if(isset($ev['es_cumple'])): ?>
                                <?php if(!empty($ev['foto_perfil'])): ?><img src="<?= BASE_URL ?>public/uploads/avatares/<?= esc($ev['foto_perfil']) ?>" class="cumple-mini-avatar">
                                <?php else: ?><div class="cumple-mini-avatar" style="background:#fef08a; display:flex; align-items:center; justify-content:center;"><i class="fa-solid fa-cake-candles" style="color:#ca8a04; font-size:0.7rem;"></i></div><?php endif; ?>
                            <?php endif; ?>
                             <?= esc($ev['titulo']) ?>
                             <?php if(isset($ev['publico']) && $ev['publico']): ?><i class="fa-solid fa-earth-americas" style="font-size:0.65rem; color:var(--color-primary); margin-left:5px;" title="Público"></i><?php endif; ?>
                        </div>
                        <div class="evento-acciones">
                            <?php if(isset($ev['es_cumple'])): ?>
                                <button type="button" class="btn-evento-accion" onclick="event.stopPropagation(); abrirModalCumple('<?=esc($ev['nombre_cumple'])?>', '', '<?=$ev['foto_perfil'] ? BASE_URL.'public/uploads/avatares/'.esc($ev['foto_perfil']) : ''?>', '<?=$ev['edad']?>', '<?=date('d M', strtotime($ev['fecha_inicio']))?>')"><i class="fa-solid fa-cake-candles"></i></button>
                            <?php else: ?>
                                <button type="button" class="btn-evento-accion" onclick="event.stopPropagation(); abrirModalVer('<?=esc($ev['titulo'])?>', '<?=esc($ev['descripcion'])?>', '<?=date('d/m/Y H:i', strtotime($ev['fecha_inicio']))?>', '<?=date('d/m/Y H:i', strtotime($ev['fecha_fin']))?>')"><i class="fa-solid fa-eye"></i></button>
                                <?php if($cveAreaUsuario === 1 || $cveAreaUsuario === 4): ?>
                                <button type="button" class="btn-evento-accion" onclick="event.stopPropagation(); abrirModalEditar(<?=$ev['id']?>, '<?=esc($ev['titulo'])?>', '<?=esc($ev['descripcion'])?>', '<?=date('Y-m-d\TH:i', strtotime($ev['fecha_inicio']))?>', '<?=date('Y-m-d\TH:i', strtotime($ev['fecha_fin']))?>', '<?=($ev['color'])?>', <?= ($ev['publico']?1:0) ?>, <?= (isset($ev['es_institucional']) && $ev['es_institucional']) ? '1' : '0' ?>)"><i class="fa-solid fa-pen"></i></button>
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
                        <div class="tarea-card" draggable="true" ondragstart="drag(event, <?= $t['id'] ?>)" onclick="abrirModalEditarTarea(<?= $t['id'] ?>, '<?= esc($t['titulo']) ?>', '<?= esc($t['descripcion']) ?>', '<?= $t['color'] ?>', <?= (int)$t['asignado_a'] ?>)">
                            <h4 style="margin:0 0 5px; font-size:1rem;"><?= esc($t['titulo']) ?></h4><p style="font-size:0.8rem; color:#64748b;"><?= esc(mb_strimwidth($t['descripcion'], 0, 80, '...')) ?></p>
                            <div style="margin-top:10px; font-size:0.7rem; color:#94a3b8; display:flex; justify-content:space-between; align-items:center;"><span><i class="fa-solid fa-user-tag"></i> <?= esc($t['asignado_nombre'] ?: 'Sin asignar') ?></span><i class="fa-solid fa-grip-lines" style="opacity:0.3;"></i></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Modales Agenda -->
<div class="modal-backdrop" id="modalCrearEvento">
    <div class="modal"><div class="modal-header"><h3>Agendar Evento</h3><button type="button" onclick="cerrarModal('modalCrearEvento')" class="btn-close btn-close-white"></button></div>
        <form method="POST"><?= csrfField() ?><input type="hidden" name="_accion" value="crear_evento">
            <div class="modal-body">
                <div class="form-group mb-3"><label class="form-label">Título</label><input type="text" name="titulo" class="form-control" required></div>
                <div class="form-group mb-3"><label class="form-label">Descripción</label><textarea name="descripcion" class="form-control" rows="3"></textarea></div>
                <div class="row mb-3"><div class="col"><label class="form-label">Desde</label><input type="datetime-local" name="fecha_inicio" id="c_fecha_inicio" class="form-control" required></div><div class="col"><label class="form-label">Hasta</label><input type="datetime-local" name="fecha_fin" id="c_fecha_fin" class="form-control" required></div></div>
                <div class="form-group mb-3"><label class="form-label">Color</label><input type="color" name="color" value="#662331" class="form-control" style="height:40px;"></div>
                <div class="checkbox-wrapper"><input type="checkbox" name="publico" id="c_publico" value="1"><label for="c_publico">Mostrar en Calendario Público (Global)</label></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary">Guardar Evento</button></div>
        </form>
    </div>
</div>

<div class="modal-backdrop" id="modalVerEvento">
    <div class="modal"><div class="modal-header"><h3>Detalle de Evento</h3><button type="button" onclick="cerrarModal('modalVerEvento')" class="btn-close btn-close-white"></button></div>
        <div class="modal-body"><p><strong>Horario:</strong> <span id="v_horario"></span></p><hr><div id="v_descripcion" style="white-space:pre-wrap;"></div></div>
    </div>
</div>

<div class="modal-backdrop" id="modalEditarEvento">
    <div class="modal"><div class="modal-header"><h3>Editar Evento</h3><button type="button" onclick="cerrarModal('modalEditarEvento')" class="btn-close btn-close-white"></button></div>
        <form method="POST" id="formEditarEvento"><?= csrfField() ?><input type="hidden" name="_accion" value="editar_evento" id="e_accion"><input type="hidden" name="evento_id" id="e_evento_id"><input type="hidden" name="es_institucional" id="e_es_institucional">
            <div class="modal-body">
                <div class="form-group mb-3"><label class="form-label">Título</label><input type="text" name="titulo" id="e_titulo" class="form-control" required></div>
                <div class="form-group mb-3"><label class="form-label">Descripción</label><textarea name="descripcion" id="e_descripcion" class="form-control" rows="3"></textarea></div>
                <div class="row mb-3"><div class="col"><label class="form-label">Desde</label><input type="datetime-local" name="fecha_inicio" id="e_fecha_inicio" class="form-control" required></div><div class="col"><label class="form-label">Hasta</label><input type="datetime-local" name="fecha_fin" id="e_fecha_fin" class="form-control" required></div></div>
                <div class="form-group mb-3"><label class="form-label">Color</label><input type="color" name="color" id="e_color" class="form-control" style="height:40px;"></div>
                <div class="checkbox-wrapper"><input type="checkbox" name="publico" id="e_publico" value="1"><label for="e_publico">Mostrar en Calendario Público (Global)</label></div>
            </div>
            <div class="modal-footer d-flex justify-content-between"><button type="button" class="btn btn-danger" onclick="eliminarEvento()">Eliminar</button><button type="submit" class="btn btn-primary">Actualizar</button></div>
        </form>
    </div>
</div>

<!-- Modales Kanban -->
<div class="modal-backdrop" id="modalCrearTarea">
    <div class="modal"><div class="modal-header" style="background:#3b82f6;"><h3>Nueva Tarea</h3><button type="button" onclick="cerrarModal('modalCrearTarea')" class="btn-close btn-close-white"></button></div>
        <form method="POST"><?= csrfField() ?><input type="hidden" name="_accion" value="crear_tarea">
            <div class="modal-body">
                <div class="form-group mb-3"><label class="form-label">Título</label><input type="text" name="titulo" class="form-control" required></div>
                <div class="form-group mb-3"><label class="form-label">Descripción</label><textarea name="descripcion" class="form-control" rows="3"></textarea></div>
                <div class="form-group mb-3"><label class="form-label">Asignado a</label><select name="asignado_a" class="form-control"><option value="">-- Sin Asignar --</option><?php foreach ($adminsDisponibles as $adm): ?><option value="<?= $adm['id'] ?>"><?= esc($adm['nombre']) ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label class="form-label">Color</label><input type="color" name="color" value="#3b82f6" class="form-control" style="height:40px;"></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary" style="background:#3b82f6; border:none;">Crear Tarea</button></div>
        </form>
    </div>
</div>

<div class="modal-backdrop" id="modalEditarTarea">
    <div class="modal"><div class="modal-header" style="background:#3b82f6;"><h3>Editar Tarea</h3><button type="button" onclick="cerrarModal('modalEditarTarea')" class="btn-close btn-close-white"></button></div>
        <form method="POST"><?= csrfField() ?><input type="hidden" name="_accion" value="editar_tarea"><input type="hidden" name="tarea_id" id="et_id">
            <div class="modal-body">
                <div class="form-group mb-3"><label class="form-label">Título</label><input type="text" name="titulo" id="et_titulo" class="form-control" required></div>
                <div class="form-group mb-3"><label class="form-label">Descripción</label><textarea name="descripcion" id="et_descripcion" class="form-control" rows="3"></textarea></div>
                <div class="form-group mb-3"><label class="form-label">Asignado a</label><select name="asignado_a" id="et_asignado_a" class="form-control"><option value="">-- Sin Asignar --</option><?php foreach ($adminsDisponibles as $adm): ?><option value="<?= $adm['id'] ?>"><?= esc($adm['nombre']) ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label class="form-label">Color</label><input type="color" name="color" id="et_color" class="form-control" style="height:40px;"></div>
            </div>
            <div class="modal-footer d-flex justify-content-between"><button type="button" class="btn btn-danger" onclick="eliminarTareaDesdeModal()">Eliminar</button><button type="submit" class="btn btn-primary" style="background:#3b82f6; border:none;">Actualizar</button></div>
        </form>
    </div>
</div>

<form id="formAccionTarea" method="POST" style="display:none;"><?= csrfField() ?><input type="hidden" name="_accion" id="t_accion"><input type="hidden" name="tarea_id" id="t_tarea_id"><input type="hidden" name="nuevo_estatus" id="t_nuevo_estatus"></form>

<?php require_once __DIR__ . '/../../admin/modales/modal_cumple.php'; ?>

<script>
function abrirModal(id) { document.getElementById(id).classList.add('active'); }
function cerrarModal(id) { document.getElementById(id).classList.remove('active'); }
function abrirModalCrear() { abrirModal('modalCrearEvento'); }
function abrirModalCrearDesdeCelda(ini, fin) {
    if (ini.length === 10) ini += "T09:00";
    if (fin.length === 10) fin += "T10:00";
    document.getElementById('c_fecha_inicio').value = ini;
    document.getElementById('c_fecha_fin').value = fin;
    abrirModal('modalCrearEvento');
}
function abrirModalVer(titulo, desc, ini, fin) {
    document.getElementById('v_horario').textContent = ini + ' - ' + fin;
    document.getElementById('v_descripcion').textContent = desc || 'Sin detalles.';
    abrirModal('modalVerEvento');
}
function abrirModalCumple(nombre, desc, fotoUrl, edad, fecha) {
    document.getElementById('mc_nombre').textContent = nombre;
    document.getElementById('mc_fecha').textContent = fecha;
    const img = document.getElementById('mc_foto');
    const ph = document.getElementById('mc_foto_placeholder');
    if (fotoUrl) { img.src = fotoUrl; img.style.display = 'block'; ph.style.display = 'none'; } else { img.style.display = 'none'; ph.style.display = 'flex'; }
    abrirModal('modalVerCumple');
}
function abrirModalEditar(id, t, d, ini, fin, c, p, esInst) {
    document.getElementById('e_evento_id').value = id;
    document.getElementById('e_es_institucional').value = esInst;
    document.getElementById('e_titulo').value = t;
    document.getElementById('e_descripcion').value = d;
    document.getElementById('e_fecha_inicio').value = ini;
    document.getElementById('e_fecha_fin').value = fin;
    document.getElementById('e_color').value = c;
    document.getElementById('e_publico').checked = (p === 1);
    abrirModal('modalEditarEvento');
}
function eliminarEvento() {
    if(confirm('¿Deseas eliminar este evento?')) {
        document.getElementById('e_accion').value = 'eliminar_evento';
        document.getElementById('formEditarEvento').submit();
    }
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
    if(confirm('¿Seguro que deseas eliminar esta tarea?')) {
        document.getElementById('t_accion').value = 'eliminar_tarea';
        document.getElementById('t_tarea_id').value = document.getElementById('et_id').value;
        document.getElementById('formAccionTarea').submit();
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
