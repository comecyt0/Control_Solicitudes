<?php
/**
 * COMECyT — Calendario Editorial (Departamento Administrativo)
 * Contexto Area 18 (Departamento Administrativo).
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../config/auth.php';

verificarSesionAdmin();

$pdo = getConnection();
$mensajeFlash = '';
$tipoFlash = '';
$cveAreaUsuario  = (int) ($_SESSION['admin_cve_area'] ?? $_SESSION['user_cve_area'] ?? 0);
$cveAreaContexto = 18; // Departamento Administrativo
$adminId         = (int) ($_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 0);

// Personal autorizado: Sistemas (1) o Administrativo (18)
$esPersonalAutorizado = ($cveAreaUsuario === 1 || $cveAreaUsuario === $cveAreaContexto);

if ($cveAreaUsuario === 0) redirigir('public/hub.php');

// -------------------------------------------------------
// Helpers de sincronización: eventos públicos ADM → tabla `eventos`
// -------------------------------------------------------
function admBuscarEspejo(PDO $pdo, int $dfId): ?int {
    $stmt = $pdo->prepare("SELECT id FROM eventos WHERE descripcion LIKE ? LIMIT 1");
    $stmt->execute(['%[ADM:' . $dfId . ']%']);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? (int)$row['id'] : null;
}

function admSincronizarPublico(PDO $pdo, int $dfId, string $titulo, string $descripcion, string $fi, string $ff, string $color, bool $requiereSala, string $areaSol, string $persSol, ?int $creadoPor): void {
    $marcador   = '[ADM:' . $dfId . ']';
    $descEspejo = trim($descripcion . ' ' . $marcador);
    $espejoId   = admBuscarEspejo($pdo, $dfId);
    $reqSalaStr = $requiereSala ? 'true' : 'false';
    // ID fallaback (Sistemas/Admin) si el usuario actual no está en la tabla administradores
    $idAutorSync = $creadoPor ?? 4; 

    if ($espejoId) {
        $pdo->prepare(
            "UPDATE eventos SET titulo=?, descripcion=?, fecha_inicio=?, fecha_fin=?, color=?, publico=TRUE, requiere_sala=?, area_solicitante=?, persona_solicitante=?, creado_por=? WHERE id=?"
        )->execute([$titulo, $descEspejo, $fi, $ff, $color, $reqSalaStr, $areaSol, $persSol, $idAutorSync, $espejoId]);
    } else {
        $pdo->prepare(
            "INSERT INTO eventos (titulo, descripcion, fecha_inicio, fecha_fin, color, publico, requiere_sala, area_solicitante, persona_solicitante, creado_por) 
             VALUES (?,?,?,?,?,TRUE,?,?,?,?)"
        )->execute([$titulo, $descEspejo, $fi, $ff, $color, $reqSalaStr, $areaSol, $persSol, $idAutorSync]);
    }
}

function admEliminarEspejo(PDO $pdo, int $dfId): void {
    $espejoId = admBuscarEspejo($pdo, $dfId);
    if ($espejoId) $pdo->prepare("DELETE FROM eventos WHERE id=?")->execute([$espejoId]);
}

// -------------------------------------------------------
// Procesar acciones (POST)
// -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_accion'])) {
    try {
        validarCsrfPost();
        if (!$esPersonalAutorizado) die("Acceso denegado.");

        $accion = $_POST['_accion'];
        
        if ($accion === 'crear_evento') {
            $titulo = trim(postParam('titulo'));
            $descripcion = trim(postParam('descripcion'));
            $fechaInicio = postParam('fecha_inicio');
            $fechaFin = postParam('fecha_fin');
            $color = postParam('color', '#334155'); 
            $publico = isset($_POST['publico']);
            $requiereSala = isset($_POST['requiere_sala']);
            
            if ($titulo && $fechaInicio && $fechaFin) {
                $areaSol = $_SESSION['admin_area_nombre'] ?? 'Departamento Administrativo';
                $persSol = $_SESSION['admin_nombre'] ?? 'Administrador';

                // SEGURIDAD: Validar si el creador existe en administradores
                $checkAdmin = $pdo->prepare("SELECT 1 FROM administradores WHERE id = ?");
                $checkAdmin->execute([$adminId]);
                $idAutorValido = $checkAdmin->fetch() ? $adminId : null;

                $stmt = $pdo->prepare("INSERT INTO df_eventos_editoriales (titulo, descripcion, fecha_inicio, fecha_fin, color, creado_por, publico, cve_area, requiere_sala, area_solicitante, persona_solicitante) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$titulo, $descripcion, $fechaInicio, $fechaFin, $color, $idAutorValido, $publico ? 'true' : 'false', $cveAreaContexto, $requiereSala ? 'true' : 'false', $areaSol, $persSol]);
                $nuevoId = (int) $pdo->lastInsertId();

                if ($publico && $nuevoId > 0) {
                    admSincronizarPublico($pdo, $nuevoId, $titulo, $descripcion, $fechaInicio, $fechaFin, $color, $requiereSala, $areaSol, $persSol, $idAutorValido);
                }
                header('Location: calendario_editorial.php?flash=evento_creado'); exit;
            }
        } elseif ($accion === 'editar_evento') {
            $id = (int) postParam('evento_id');
            $titulo = trim(postParam('titulo'));
            $descripcion = trim(postParam('descripcion'));
            $fechaInicio = postParam('fecha_inicio');
            $fechaFin    = postParam('fecha_fin');
            $color       = postParam('color', '#334155');
            $publico     = isset($_POST['publico']);
            $requiereSala = isset($_POST['requiere_sala']);
            
            if ($id > 0 && $titulo) {
                $stmt = $pdo->prepare("UPDATE df_eventos_editoriales SET titulo = ?, descripcion = ?, fecha_inicio = ?, fecha_fin = ?, color = ?, publico = ?, requiere_sala = ? WHERE id = ? AND cve_area = ?");
                $stmt->execute([$titulo, $descripcion, $fechaInicio, $fechaFin, $color, ($publico ? 'true' : 'false'), ($requiereSala ? 'true' : 'false'), $id, $cveAreaContexto]);

                if ($publico) {
                    $areaSol = $_SESSION['admin_area_nombre'] ?? 'Departamento Administrativo';
                    $persSol = $_SESSION['admin_nombre'] ?? 'Administrador';
                    admSincronizarPublico($pdo, $id, $titulo, $descripcion, $fechaInicio, $fechaFin, $color, $requiereSala, $areaSol, $persSol, $idAutorValido);
                } else {
                    admEliminarEspejo($pdo, $id);
                }
                header('Location: calendario_editorial.php?flash=evento_editado'); exit;
            }
        } elseif ($accion === 'eliminar_evento') {
            $id = (int) postParam('evento_id');
            if ($id > 0) {
                admEliminarEspejo($pdo, $id);
                $stmt = $pdo->prepare("DELETE FROM df_eventos_editoriales WHERE id = ? AND cve_area = ?");
                $stmt->execute([$id, $cveAreaContexto]);
                header('Location: calendario_editorial.php?flash=evento_eliminado'); exit;
            }
        } elseif ($accion === 'mover_tarea') {
            $id = (int) postParam('tarea_id');
            $nuevoEstatus = postParam('nuevo_estatus');
            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE sb_kanban_tareas SET estatus = ? WHERE id = ? AND cve_area = ?");
                $stmt->execute([$nuevoEstatus, $id, $cveAreaContexto]);
                header('Location: calendario_editorial.php?flash=tarea_movida#kanban'); exit;
            }
        } elseif ($accion === 'crear_tarea' || $accion === 'editar_tarea') {
            $titulo = trim(postParam('titulo'));
            $descripcion = trim(postParam('descripcion'));
            $color = postParam('color', '#334155');
            $asignado_a = !empty($_POST['asignado_a']) ? (int)$_POST['asignado_a'] : null;
            if ($titulo) {
                if ($accion === 'crear_tarea') {
                    // Validar adminId para tareas también
                    $checkAdmin = $pdo->prepare("SELECT 1 FROM administradores WHERE id = ?");
                    $checkAdmin->execute([$adminId]);
                    $idAutorValido = $checkAdmin->fetch() ? $adminId : null;

                    $stmt = $pdo->prepare("INSERT INTO sb_kanban_tareas (titulo, descripcion, color, estatus, creado_por, asignado_a, cve_area) VALUES (?, ?, ?, 'pendiente', ?, ?, ?)");
                    $stmt->execute([$titulo, $descripcion, $color, $idAutorValido, $asignado_a, $cveAreaContexto]);
                } else {
                    $id = (int) postParam('tarea_id');
                    $stmt = $pdo->prepare("UPDATE sb_kanban_tareas SET titulo = ?, descripcion = ?, color = ?, asignado_a = ? WHERE id = ? AND cve_area = ?");
                    $stmt->execute([$titulo, $descripcion, $color, $asignado_a, $id, $cveAreaContexto]);
                }
                header('Location: calendario_editorial.php?flash=' . ($accion === 'crear_tarea' ? 'tarea_creada' : 'tarea_editada') . '#kanban'); exit;
            }
        } elseif ($accion === 'eliminar_tarea') {
             $id = (int) postParam('tarea_id');
             if ($id > 0) {
                 $stmt = $pdo->prepare("DELETE FROM sb_kanban_tareas WHERE id = ? AND cve_area = ?");
                 $stmt->execute([$id, $cveAreaContexto]);
                 header('Location: calendario_editorial.php?flash=tarea_eliminada#kanban'); exit;
             }
        }
    } catch (Exception $e) { die("Error: " . $e->getMessage()); }
}

// Lógica de Vista
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

$stmt = $pdo->prepare("SELECT id, titulo, descripcion, fecha_inicio, fecha_fin, color, publico, requiere_sala, area_solicitante, persona_solicitante, FALSE as es_institucional FROM df_eventos_editoriales WHERE cve_area = ? AND fecha_inicio < ? AND fecha_fin > ? ORDER BY fecha_inicio ASC");
$stmt->execute([$cveAreaContexto, $finMesBusqueda, $inicioMesBusqueda]);
$eventosRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 2. Consultar eventos INSTITUCIONALES (Globales/Públicos)
$stmtG = $pdo->prepare("SELECT id, titulo, descripcion, fecha_inicio, fecha_fin, color, publico, requiere_sala, area_solicitante, persona_solicitante, TRUE as es_institucional FROM eventos WHERE publico = TRUE AND fecha_inicio < ? AND fecha_fin > ? ORDER BY fecha_inicio ASC");
$stmtG->execute([$finMesBusqueda, $inicioMesBusqueda]);
$eventosGlobales = $stmtG->fetchAll(PDO::FETCH_ASSOC);

foreach ($eventosGlobales as $eg) {
    // Evitar duplicados de mirrors (si el marcador [ADM:id] está en la descripción)
    if (strpos($eg['descripcion'] ?? '', '[ADM:') !== false) continue;
    $eventosRaw[] = $eg;
}

// 3. Consultar y Añadir CUMPLEAÑOS
if ($mes > 0 && $mes <= 12) {
    $stmtB = $pdo->prepare("SELECT nombre, appat, apmat, fecha_nacimiento, foto_perfil FROM cat_personal WHERE activo = TRUE AND fecha_nacimiento IS NOT NULL AND EXTRACT(MONTH FROM fecha_nacimiento) = ?");
    $stmtB->execute([$mes]);
    $cumpleaneros = $stmtB->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cumpleaneros as $cp) {
        $diaCumple = (int) (new DateTime($cp['fecha_nacimiento']))->format('d');
        $nombreCompleto = trim($cp['nombre'] . ' ' . $cp['appat'] . ' ' . $cp['apmat']);
        $eventosRaw[] = [
            'id' => null, 
            'titulo' => "🎂 " . $nombreCompleto, 
            'descripcion' => 'Cumpleaños institucional',
            'fecha_inicio' => sprintf('%04d-%02d-%02d 00:00:00', $anio, $mes, $diaCumple), 
            'fecha_fin' => sprintf('%04d-%02d-%02d 23:59:59', $anio, $mes, $diaCumple),
            'color' => '#B19A6D', 
            'publico' => false, 
            'es_cumple' => true,
            'es_institucional' => true,
            'nombre_cumple'=> $nombreCompleto,
            'foto_perfil' => $cp['foto_perfil'] ?? null,
            'edad' => ''
        ];
    }
}

$calendarioEventos = [];
foreach ($eventosRaw as $ev) {
    $dIni = new DateTime($ev['fecha_inicio']);
    $diaEv = (int)$dIni->format('d');
    if (!isset($calendarioEventos[$diaEv])) $calendarioEventos[$diaEv] = [];
    $ev['hora_formateada'] = $dIni->format('H:i');
    
    // Casting de booleanos robusto
    $v = $ev['publico'] ?? false;
    $ev['publico'] = ($v === true || $v === 't' || $v === 'true' || $v === 1 || $v === '1');
    $vs = $ev['requiere_sala'] ?? false;
    $ev['requiere_sala'] = ($vs === true || $vs === 't' || $vs === 'true' || $vs === 1 || $vs === '1');
    
    $calendarioEventos[$diaEv][] = $ev;
}

$listaTareas = ['pendiente'=>[],'en_proceso'=>[],'completada'=>[]];
$stmtT = $pdo->prepare("SELECT t.*, COALESCE(a.nombre, p.nombre) AS asignado_nombre FROM sb_kanban_tareas t LEFT JOIN administradores a ON t.asignado_a = a.id LEFT JOIN cat_personal p ON t.asignado_a = p.cve_personal WHERE t.cve_area = ? ORDER BY t.estatus DESC, t.id DESC");
$stmtT->execute([$cveAreaContexto]);
foreach ($stmtT->fetchAll(PDO::FETCH_ASSOC) as $t) { $listaTareas[$t['estatus']][] = $t; }

$stmtAdmins = $pdo->prepare("SELECT p.cve_personal AS id, CONCAT(p.nombre, ' ', p.appat, ' ', p.apmat) AS nombre FROM cat_personal p WHERE p.activo = true AND p.cve_area = ? ORDER BY p.nombre ASC");
$stmtAdmins->execute([$cveAreaContexto]);
$adminsDisponibles = $stmtAdmins->fetchAll(PDO::FETCH_ASSOC);

$mesesNombres = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];
$pageTitle = 'Departamento Administrativo — Agenda';

$extraHead = '
<style>
:root { --color-primary: #334155; --color-primary-dark: #1e293b; }
.calendar-wrapper { background: #ffffff; border-radius: 16px; box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.08); overflow: hidden; margin-top: 1.5rem; border: 1px solid rgba(0,0,0,0.05); }
.calendar-header-nav { display: flex; justify-content: space-between; align-items: center; padding: 1.25rem 2rem; background: #fdfdfd; border-bottom: 1px solid rgba(0,0,0,0.06); }
.calendar-header-nav h3 { margin: 0; font-size: 1.4rem; font-weight: 700; color: var(--color-primary); }
.calendar-grid { display: grid; grid-template-columns: repeat(7, 1fr); background: #f8fafc; gap: 1px; }
.calendar-day-name { padding: 1rem 0.5rem; text-align: right; font-weight: 800; font-size: 0.75rem; color: #64748b; background: #ffffff; text-transform: uppercase; }
.calendar-cell { min-height: 140px; padding: 0.5rem; background: #ffffff; cursor: pointer; display: flex; flex-direction: column; gap: 0.4rem; overflow: hidden; border:none; border-right: 1px solid rgba(0,0,0,0.03); border-bottom: 1px solid rgba(0,0,0,0.03); }
.day-number { font-weight: 700; color: #334155; align-self: flex-end; }
.calendar-cell.today .day-number { color: #fff; background: var(--color-primary); border-radius: 50%; width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center; }
.evento-pildora { font-size: 0.75rem; padding: 0.5rem 0.6rem; border-radius: 2px 2px 12px 2px; color: #1e293b; margin-bottom: 0.25rem; position: relative; box-shadow: 2px 2px 4px rgba(0, 0, 0, 0.05), inset -10px -10px 20px rgba(0,0,0,0.03); border-top: 6px solid var(--color-primary); width:100%; box-sizing:border-box; transition: all 0.2s; display: flex; flex-direction: column; gap: 0.2rem; }
.evento-pildora:hover { transform: scale(1.02); z-index: 10; }
.evento-titulo { font-weight: 700; display: flex; align-items: center; gap: 4px; width: 100%; min-width: 0; }
.evento-titulo span { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex: 1; min-width: 0; }
.evento-acciones { display: flex; gap: 4px; opacity: 0; max-height: 0; overflow: hidden; transition: all 0.2s; }
.evento-pildora:hover .evento-acciones { opacity: 1; max-height: 30px; margin-top: 4px; }
.btn-evento-accion { flex: 1; background: rgba(255,255,255,0.7); border: none; border-radius: 4px; padding: 4px 0; cursor: pointer; font-size: 0.75rem; color: #475569; display:flex; align-items:center; justify-content:center; }
.kanban-board { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; padding: 1.5rem 0; }
.kanban-col { background: #f1f5f9; border-radius: 12px; min-height: 500px; border: 1px solid #e2e8f0; display: flex; flex-direction: column; }
.kanban-col-header { padding: 1.25rem; font-weight: 800; color: white; border-radius: 12px 12px 0 0; display: flex; justify-content: space-between; }
.bg-pendiente { background-color: #3b82f6; } .bg-en-proceso { background-color: #f59e0b; } .bg-completada { background-color: #10b981; }
.tarea-card { background: #fff; border-radius: 10px; padding: 1.25rem; margin: 10px; border: 1px solid #e2e8f0; border-top: 8px solid var(--color-primary); cursor: pointer; transition: transform 0.2s; }
.tarea-card:hover { transform: translateY(-3px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
.modal-backdrop { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(10px); z-index: 2000; align-items: center; justify-content: center; padding: 20px; }
.modal-backdrop.active { display: flex; }
.modal { background: #fff; width: 100%; max-width: 550px; border-radius: 24px; overflow: hidden; border: none; max-height: 90vh; display: flex; flex-direction: column; }
.modal-header { background: linear-gradient(135deg, var(--color-primary), var(--color-primary-dark)); color: white; padding: 1.5rem; display: flex; justify-content: space-between; align-items: center; position: relative; border: none; flex-shrink: 0; }
.modal-header h3 { margin: 0; font-size: 1.25rem; font-weight: 800; }
.modal-close { position: absolute; top: 1.25rem; right: 1.25rem; background: rgba(255,255,255,0.15); border: none; color: white; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; }
.modal-body { padding: 1.5rem; overflow-y: auto; flex: 1; }
.modal-footer { padding: 1.25rem 1.5rem; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; gap: 0.75rem; flex-shrink: 0; }
</style>';

require_once __DIR__ . '/../../includes/header_admin.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
    <div><h2 style="font-weight: 800; color: #0f172a; margin: 0;"><i class="fa-solid fa-calendar-check" style="color:var(--color-primary);"></i> Agenda Administrativa</h2><p style="color: #64748b; margin: 0;">Gestión interna de compromisos y eventos del departamento.</p></div>
    <button class="btn btn-primary" onclick="abrirModalCrear()"><i class="fa-solid fa-plus"></i> Nuevo Evento</button>
</div>

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
                    $cStyle = 'style="border-top-color:'.$ev['color'].'; background-color:'.$ev['color'].'1a;"';
                ?>
                    <div class="evento-pildora" <?= $cStyle ?> onclick="event.stopPropagation()">
                        <div class="evento-titulo">
                            <?php if (!empty($ev['es_cumple'])): ?>
                                <?php if (!empty($ev['foto_perfil'])): ?>
                                    <img src="<?= BASE_URL ?>public/uploads/avatares/<?= esc($ev['foto_perfil']) ?>" style="width:18px; height:18px; border-radius:50%; object-fit:cover; border:1px solid #ca8a04;">
                                <?php else: ?>
                                    <i class="fa-solid fa-cake-candles" style="color:#ca8a04;"></i>
                                <?php endif; ?>
                            <?php endif; ?>
                            <?php if (!empty($ev['requiere_sala'])): ?><i class="fa-solid fa-person-chalkboard" style="color:#dc2626; flex-shrink:0; font-size:1.1em;" title="SALA DE JUNTAS REQUERIDA"></i><?php endif; ?>
                            <?php if ($ev['publico']): ?><i class="fa-solid fa-earth-americas" style="color:#3b82f6; flex-shrink:0;" title="Público"></i><?php endif; ?>
                            <span><?= esc($ev['titulo']) ?></span>
                        </div>
                        <div class="evento-hora"><?= $ev['hora_formateada'] ?></div>
                        <div class="evento-acciones">
                            <?php if (!empty($ev['es_cumple'])): ?>
                                <button type="button" class="btn-evento-accion" onclick="abrirModalCumple('<?=esc(addslashes($ev['nombre_cumple']))?>','','<?= !empty($ev['foto_perfil']) ? BASE_URL . 'public/uploads/avatares/' . esc($ev['foto_perfil']) : '' ?>','','<?=date('d/m', strtotime($ev['fecha_inicio']))?>')"><i class="fa-solid fa-cake-candles"></i></button>
                            <?php else: ?>
                                <button type="button" class="btn-evento-accion" onclick="abrirModalVer('<?=esc(addslashes($ev['titulo']))?>','<?=esc(addslashes($ev['descripcion']))?>','<?=date('d/m/Y H:i',strtotime($ev['fecha_inicio']))?>','<?=date('d/m/Y H:i',strtotime($ev['fecha_fin']))?>','<?= $ev['requiere_sala'] ? 1 : 0 ?>')"><i class="fa-solid fa-eye"></i></button>
                                <?php if (!$ev['es_institucional'] || $cveAreaUsuario === 1): ?>
                                <button type="button" class="btn-evento-accion" onclick="abrirModalEditar(<?=$ev['id']?>,'<?=esc(addslashes($ev['titulo']))?>','<?=esc(addslashes($ev['descripcion']))?>','<?=date('Y-m-d\TH:i',strtotime($ev['fecha_inicio']))?>','<?=date('Y-m-d\TH:i',strtotime($ev['fecha_fin']))?>','<?=$ev['color']?>',<?=($ev['publico']?1:0)?>,<?=($ev['requiere_sala']?1:0)?>)"><i class="fa-solid fa-pen"></i></button>
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
        <h3 style="font-weight: 800; color: #1e293b; margin:0;"><i class="fa-solid fa-list-check" style="color:#3b82f6;"></i> Compromisos Administrativos</h3>
        <button class="btn btn-primary" onclick="abrirModalCrearTarea()" style="background:#3b82f6; border:none;"><i class="fa-solid fa-plus"></i> Nueva Tarea</button>
    </div>
    <div class="kanban-board">
        <?php foreach (['pendiente' => 'Pendiente', 'en_proceso' => 'En Proceso', 'completada' => 'Completada'] as $idCol => $lblCol): ?>
            <div class="kanban-col" ondragover="allowDrop(event)" ondrop="drop(event, '<?= $idCol ?>')">
                <div class="kanban-col-header bg-<?= str_replace('_','-',$idCol) ?>"><span><?= strtoupper($lblCol) ?></span></div>
                <div class="kanban-col-body">
                    <?php foreach ($listaTareas[$idCol] as $t): ?>
                        <div class="tarea-card" draggable="true" ondragstart="drag(event, <?= $t['id'] ?>)" onclick="abrirModalEditarTarea(<?= $t['id'] ?>, '<?= esc(addslashes($t['titulo'])) ?>', '<?= esc(addslashes($t['descripcion'])) ?>', '<?= $t['color'] ?>', <?= (int)$t['asignado_a'] ?>)" style="border-top-color: <?= $t['color'] ?>;">
                            <h4 style="margin:0 0 5px; font-size:1rem;"><?= esc($t['titulo']) ?></h4>
                            <p style="font-size:0.8rem; color:#64748b;"><?= esc(mb_strimwidth($t['descripcion'], 0, 80, '...')) ?></p>
                            <div style="margin-top:10px; font-size:0.7rem; color:#94a3b8; display:flex; align-items:center; gap:5px;">
                                <i class="fa-solid fa-user-tag"></i> <?= esc($t['asignado_nombre'] ?: 'Sin asignar') ?>
                                <span style="margin-left:auto; width:12px; height:12px; border-radius:50%; background:<?= $t['color'] ?>; border:1px solid rgba(0,0,0,0.1);"></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Modales simplificados -->
<div class="modal-backdrop" id="modalCrearEvento">
    <div class="modal">
        <div class="modal-header"><h3>Nuevo Evento</h3><button type="button" onclick="cerrarModal('modalCrearEvento')" class="modal-close"><i class="fa-solid fa-xmark"></i></button></div>
        <form method="POST"><?= csrfField() ?><input type="hidden" name="_accion" value="crear_evento">
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Título</label><input type="text" name="titulo" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Descripción</label><textarea name="descripcion" class="form-control" rows="2"></textarea></div>
                <div class="row mb-3"><div class="col"><label class="form-label">Desde</label><input type="datetime-local" name="fecha_inicio" id="c_fecha_inicio" class="form-control" required></div><div class="col"><label class="form-label">Hasta</label><input type="datetime-local" name="fecha_fin" id="c_fecha_fin" class="form-control" required></div></div>
                <div class="mb-3"><label class="form-label">Color</label><input type="color" name="color" value="#334155" class="form-control" style="height:40px;"></div>
                <input type="hidden" name="publico" value="0">
                <input type="hidden" name="requiere_sala" value="0">
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary w-100">Guardar</button></div>
        </form>
    </div>
</div>

<div class="modal-backdrop" id="modalVerEvento">
    <div class="modal">
        <div class="modal-header"><h3><i class="fa-solid fa-calendar-day"></i> <span id="v_titulo_txt">Detalle de Evento</span></h3><button type="button" onclick="cerrarModal('modalVerEvento')" class="modal-close"><i class="fa-solid fa-xmark"></i></button></div>
        <div class="modal-body">
            <div id="v_badge_sala" style="margin-bottom:1rem; display:none;">
                <span class="badge bg-danger" style="padding:10px 15px; font-size:0.9rem;"><i class="fa-solid fa-person-chalkboard"></i> REQUIERE SALA DE JUNTAS</span>
            </div>
            <div style="margin-bottom: 1.5rem;">
                <p style="margin: 0; font-size: 0.85rem; color: #64748b; font-weight: 700; text-transform:uppercase; letter-spacing:0.5px;">Fecha y Horario</p>
                <p style="margin: 0.3rem 0; font-weight: 600; font-size:1.1rem; color:var(--color-primary);"><i class="fa-regular fa-clock"></i> <span id="v_horario"></span></p>
            </div>
            <div>
                <p style="margin: 0; font-size: 0.85rem; color: #64748b; font-weight: 700; text-transform:uppercase; letter-spacing:0.5px;">Descripción / Detalles</p>
                <div style="margin-top: 0.5rem; padding: 1.25rem; background: #f8fafc; border-radius: 12px; border: 1px solid #e2e8f0; min-height:80px;">
                    <p style="margin: 0; white-space: pre-wrap; color: #334155; line-height: 1.6;" id="v_descripcion"></p>
                </div>
            </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-primary w-100" onclick="cerrarModal('modalVerEvento')">Cerrar</button></div>
    </div>
</div>
<?php require_once __DIR__ . '/../../admin/modales/modal_cumple.php'; ?>

<div class="modal-backdrop" id="modalEditarEvento">
    <div class="modal">
        <div class="modal-header"><h3>Editar</h3><button type="button" onclick="cerrarModal('modalEditarEvento')" class="modal-close"><i class="fa-solid fa-xmark"></i></button></div>
        <form method="POST"><?= csrfField() ?>
            <input type="hidden" name="_accion" value="editar_evento"><input type="hidden" name="evento_id" id="e_id">
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Título</label><input type="text" name="titulo" id="e_titulo" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Descripción</label><textarea name="descripcion" id="e_descripcion" class="form-control" rows="2"></textarea></div>
                <div class="row mb-3"><div class="col"><input type="datetime-local" name="fecha_inicio" id="e_inicio" class="form-control"></div><div class="col"><input type="datetime-local" name="fecha_fin" id="e_fin" class="form-control"></div></div>
                <div class="mb-3"><label class="form-label">Color del Evento</label><input type="color" name="color" id="e_color" class="form-control" style="height:50px; cursor:pointer;"></div>
                <input type="hidden" name="publico" value="0">
                <input type="hidden" name="requiere_sala" value="0">
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary" style="flex:1;">Guardar</button><button type="submit" name="_accion" value="eliminar_evento" class="btn btn-danger">Eliminar</button></div>
        </form>
    </div>
</div>

<div class="modal-backdrop" id="modalCrearTarea">
    <div class="modal">
        <div class="modal-header" style="background:#3b82f6;"><h3>Nueva Tarea</h3><button type="button" onclick="cerrarModal('modalCrearTarea')" class="modal-close"><i class="fa-solid fa-xmark"></i></button></div>
        <form method="POST"><?= csrfField() ?><input type="hidden" name="_accion" value="crear_tarea">
            <div class="modal-body">
                <input type="text" name="titulo" class="form-control mb-3" placeholder="Título" required>
                <textarea name="descripcion" class="form-control mb-3" placeholder="Descripción"></textarea>
                <select name="asignado_a" class="form-control mb-3"><option value="">-- Sin Asignar --</option><?php foreach ($adminsDisponibles as $adm): ?><option value="<?= $adm['id'] ?>"><?= esc($adm['nombre']) ?></option><?php endforeach; ?></select>
                <div class="mb-3"><label class="form-label">Color de Etiqueta</label><input type="color" name="color" value="#334155" class="form-control" style="height:50px; cursor:pointer;"></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary w-100" style="background:#3b82f6;">Crear</button></div>
        </form>
    </div>
</div>

<div class="modal-backdrop" id="modalEditarTarea">
    <div class="modal">
        <div class="modal-header" style="background:#3b82f6;"><h3>Editar Tarea</h3><button type="button" onclick="cerrarModal('modalEditarTarea')" class="modal-close"><i class="fa-solid fa-xmark"></i></button></div>
        <form method="POST"><?= csrfField() ?><input type="hidden" name="_accion" value="editar_tarea"><input type="hidden" name="tarea_id" id="et_id">
            <div class="modal-body">
                <input type="text" name="titulo" id="et_titulo" class="form-control mb-3">
                <textarea name="descripcion" id="et_descripcion" class="form-control mb-3"></textarea>
                <select name="asignado_a" id="et_asignado_a" class="form-control mb-3"><option value="">-- Sin Asignar --</option><?php foreach ($adminsDisponibles as $adm): ?><option value="<?= $adm['id'] ?>"><?= esc($adm['nombre']) ?></option><?php endforeach; ?></select>
                <div class="mb-3"><label class="form-label">Color de Etiqueta</label><input type="color" name="color" id="et_color" class="form-control" style="height:50px; cursor:pointer;"></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary" style="background:#3b82f6;flex:1;">Actualizar</button><button type="submit" name="_accion" value="eliminar_tarea" class="btn btn-danger">Eliminar</button></div>
        </form>
    </div>
</div>

<form id="formAccionTarea" method="POST" style="display:none;"><?= csrfField() ?><input type="hidden" name="_accion" id="t_accion"><input type="hidden" name="tarea_id" id="t_tarea_id"><input type="hidden" name="nuevo_estatus" id="t_nuevo_estatus"></form>

<script>
function abrirModal(id) { document.getElementById(id).classList.add('active'); }
function cerrarModal(id) { document.getElementById(id).classList.remove('active'); }
function abrirModalCrear() { abrirModal('modalCrearEvento'); }
function abrirModalCrearDesdeCelda(f) { document.getElementById('c_fecha_inicio').value = f+'T09:00'; document.getElementById('c_fecha_fin').value = f+'T10:00'; abrirModal('modalCrearEvento'); }
function abrirModalVer(t,d,ini,fin,sala) { 
    document.getElementById('v_titulo_txt').textContent = t; 
    document.getElementById('v_horario').textContent = ini + (fin ? ' - ' + fin : ''); 
    document.getElementById('v_descripcion').textContent = d || 'Sin descripción adicional.'; 
    document.getElementById('v_badge_sala').style.display = (sala == 1) ? 'block' : 'none';
    abrirModal('modalVerEvento'); 
}
function abrirModalCumple(nombre, desc, fotoUrl, edad, fecha) {
    document.getElementById('mc_nombre').textContent = nombre;
    document.getElementById('mc_nombre_grande').textContent = nombre;
    document.getElementById('mc_edad_label').textContent = '¡Felicidades!';
    document.getElementById('mc_fecha').textContent = fecha;
    const imgEl = document.getElementById('mc_foto');
    const phEl = document.getElementById('mc_foto_placeholder');
    if (fotoUrl && fotoUrl.trim() !== '') {
        imgEl.src = fotoUrl; imgEl.style.display = 'block'; phEl.style.display = 'none';
    } else {
        imgEl.style.display = 'none'; phEl.style.display = 'flex';
    }
    abrirModal('modalVerCumple');
}
function abrirModalEditar(id,t,d,i,f,c,p,sala) {
    document.getElementById('e_id').value = id; document.getElementById('e_titulo').value = t; document.getElementById('e_descripcion').value = d; document.getElementById('e_inicio').value = i; document.getElementById('e_fin').value = f; document.getElementById('e_color').value = c;
    abrirModal('modalEditarEvento');
}
function allowDrop(ev) { ev.preventDefault(); }
function drag(ev, id) { ev.dataTransfer.setData("text", id); }
function drop(ev, nuevo) {
    ev.preventDefault(); const id = ev.dataTransfer.getData("text");
    document.getElementById('t_accion').value = 'mover_tarea'; document.getElementById('t_tarea_id').value = id; document.getElementById('t_nuevo_estatus').value = nuevo; document.getElementById('formAccionTarea').submit();
}
function abrirModalCrearTarea() { abrirModal('modalCrearTarea'); }
function abrirModalEditarTarea(id, t, d, c, a) {
    document.getElementById('et_id').value = id; document.getElementById('et_titulo').value = t; document.getElementById('et_descripcion').value = d; document.getElementById('et_color').value = c; document.getElementById('et_asignado_a').value = a || '';
    abrirModal('modalEditarTarea');
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
