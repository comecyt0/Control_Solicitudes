<?php
/**
 * COMECyT â€” Agenda y Tablero de Tareas (JurÃ­dico Administrativo)
 * Calendario de eventos + Kanban de Ã¡rea, patrÃ³n idÃ©ntico a DifusiÃ³n.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../config/auth.php';

verificarSesionAdmin();

$pdo         = getConnection();
$cveArea     = 12;
$adminId     = (int) ($_SESSION['admin_id'] ?? 0);
$mensajeFlash = '';
$tipoFlash    = '';

// â”€â”€ PRG: Acciones â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_accion'])) {
    validarCsrfPost();
    $accion = $_POST['_accion'];

    if ($accion === 'crear_evento') {
        $titulo = trim(postParam('titulo'));
        $desc   = trim(postParam('descripcion'));
        $fi     = postParam('fecha_inicio');
        $ff     = postParam('fecha_fin');
        $color  = postParam('color', '#6d28d9');
        if ($titulo && $fi && $ff) {
            $checkAdmin = $pdo->prepare("SELECT 1 FROM administradores WHERE id = ?");
            $checkAdmin->execute([$adminId]);
            $idAutor = $checkAdmin->fetch() ? $adminId : null;
            $pdo->prepare("INSERT INTO df_eventos_editoriales (titulo, descripcion, fecha_inicio, fecha_fin, color, creado_por, publico, cve_area) VALUES (?,?,?,?,?,?,FALSE,?)")
                ->execute([$titulo, $desc, $fi, $ff, $color, $idAutor, $cveArea]);
            header('Location: agenda.php?flash=evento_creado'); exit;
        }
    } elseif ($accion === 'editar_evento') {
        $id = (int) postParam('evento_id');
        $titulo = trim(postParam('titulo')); $desc = trim(postParam('descripcion'));
        $fi = postParam('fecha_inicio'); $ff = postParam('fecha_fin');
        $color = postParam('color', '#6d28d9');
        if ($id > 0 && $titulo && $fi && $ff) {
            $pdo->prepare("UPDATE df_eventos_editoriales SET titulo=?,descripcion=?,fecha_inicio=?,fecha_fin=?,color=? WHERE id=? AND cve_area=?")
                ->execute([$titulo, $desc, $fi, $ff, $color, $id, $cveArea]);
            header('Location: agenda.php?flash=evento_editado'); exit;
        }
    } elseif ($accion === 'eliminar_evento') {
        $id = (int) postParam('evento_id');
        if ($id > 0) {
            $pdo->prepare("DELETE FROM df_eventos_editoriales WHERE id=? AND cve_area=?")->execute([$id, $cveArea]);
            header('Location: agenda.php?flash=evento_eliminado'); exit;
        }
    } elseif ($accion === 'crear_tarea') {
        $titulo = trim(postParam('titulo'));
        if ($titulo) {
            $checkAdmin = $pdo->prepare("SELECT 1 FROM administradores WHERE id = ?");
            $checkAdmin->execute([$adminId]);
            $idAutor = $checkAdmin->fetch() ? $adminId : null;
            $asig = !empty($_POST['asignado_a']) ? (int)$_POST['asignado_a'] : null;
            $pdo->prepare("INSERT INTO sb_kanban_tareas (titulo, descripcion, color, estatus, creado_por, asignado_a, cve_area) VALUES (?,?,?,'pendiente',?,?,?)")
                ->execute([$titulo, trim(postParam('descripcion')), postParam('color','#6d28d9'), $idAutor, $asig, $cveArea]);
            header('Location: agenda.php?flash=tarea_creada#kanban'); exit;
        }
    } elseif ($accion === 'editar_tarea') {
        $id = (int) postParam('tarea_id');
        if ($id > 0 && trim(postParam('titulo'))) {
            $asig = !empty($_POST['asignado_a']) ? (int)$_POST['asignado_a'] : null;
            $pdo->prepare("UPDATE sb_kanban_tareas SET titulo=?,descripcion=?,color=?,asignado_a=? WHERE id=?")
                ->execute([trim(postParam('titulo')), trim(postParam('descripcion')), postParam('color','#6d28d9'), $asig, $id]);
            header('Location: agenda.php?flash=tarea_editada#kanban'); exit;
        }
    } elseif ($accion === 'mover_tarea') {
        $id = (int) postParam('tarea_id');
        $e  = postParam('nuevo_estatus');
        if ($id > 0 && in_array($e, ['pendiente','en_proceso','completada'])) {
            $pdo->prepare("UPDATE sb_kanban_tareas SET estatus=? WHERE id=?")->execute([$e, $id]);
            header('Location: agenda.php?flash=tarea_movida#kanban'); exit;
        }
    } elseif ($accion === 'eliminar_tarea') {
        $id = (int) postParam('tarea_id');
        if ($id > 0) { $pdo->prepare("DELETE FROM sb_kanban_tareas WHERE id=?")->execute([$id]); header('Location: agenda.php?flash=tarea_eliminada#kanban'); exit; }
    }
}

$flashCode = getParam('flash');
if ($flashCode === 'evento_creado')     { $mensajeFlash = 'Evento agendado.';            $tipoFlash = 'success'; }
elseif ($flashCode === 'evento_editado')   { $mensajeFlash = 'Evento actualizado.';          $tipoFlash = 'success'; }
elseif ($flashCode === 'evento_eliminado') { $mensajeFlash = 'Evento eliminado.';            $tipoFlash = 'success'; }
elseif ($flashCode === 'tarea_creada')     { $mensajeFlash = 'Tarea aÃ±adida.';               $tipoFlash = 'success'; }
elseif ($flashCode === 'tarea_editada')    { $mensajeFlash = 'Tarea actualizada.';           $tipoFlash = 'success'; }
elseif ($flashCode === 'tarea_movida')     { $mensajeFlash = 'Estatus actualizado.';         $tipoFlash = 'success'; }
elseif ($flashCode === 'tarea_eliminada')  { $mensajeFlash = 'Tarea eliminada.';             $tipoFlash = 'success'; }

// â”€â”€ Calendario â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$hoy  = new DateTime();
$mes  = (int) getParam('mes',  $hoy->format('m'));
$anio = (int) getParam('anio', $hoy->format('Y'));
if ($mes < 1 || $mes > 12) $mes  = (int) $hoy->format('m');
if ($anio < 2000 || $anio > 2100) $anio = (int) $hoy->format('Y');

$dtMes      = DateTime::createFromFormat('Y-m-d', sprintf('%04d-%02d-01', $anio, $mes));
$mesAnt     = (clone $dtMes)->modify('-1 month');
$mesSig     = (clone $dtMes)->modify('+1 month');
$diasEnMes  = (int) $dtMes->format('t');
$inicioSem  = (int) $dtMes->format('N');

$ini = $dtMes->format('Y-m-01 00:00:00');
$fin = $mesSig->format('Y-m-01 00:00:00');

// Eventos del Ã¡rea
$stmt = $pdo->prepare("SELECT * FROM df_eventos_editoriales WHERE cve_area = ? AND fecha_inicio < ? AND fecha_fin > ? ORDER BY fecha_inicio");
$stmt->execute([$cveArea, $fin, $ini]);
$eventosRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Eventos institucionales pÃºblicos
$stmtG = $pdo->prepare("SELECT * FROM eventos WHERE publico = TRUE AND fecha_inicio < ? AND fecha_fin > ?");
$stmtG->execute([$fin, $ini]);
foreach ($stmtG->fetchAll(PDO::FETCH_ASSOC) as $eg) {
    $eg['es_institucional'] = true;
    if (empty($eg['color'])) $eg['color'] = '#64748b';
    $eventosRaw[] = $eg;
}

// Mapear por dÃ­a
$calEvs = [];
foreach ($eventosRaw as $ev) {
    $d = (int)(new DateTime($ev['fecha_inicio']))->format('d');
    $calEvs[$d][] = $ev;
}

// CumpleaÃ±os
$stmtB = $pdo->prepare("SELECT nombre, appat, apmat, fecha_nacimiento, foto_perfil FROM cat_personal WHERE activo = TRUE AND fecha_nacimiento IS NOT NULL AND EXTRACT(MONTH FROM fecha_nacimiento) = :mes");
$stmtB->execute([':mes' => $mes]);
foreach ($stmtB->fetchAll(PDO::FETCH_ASSOC) as $cp) {
    $diaCumple = (int)(new DateTime($cp['fecha_nacimiento']))->format('d');
    $nombreC   = trim($cp['nombre'].' '.$cp['appat'].' '.$cp['apmat']);
    $calEvs[$diaCumple][] = [
        'id' => null, 'titulo' => 'ðŸŽ‚ '.$nombreC, 'descripcion' => 'CumpleaÃ±os',
        'fecha_inicio' => sprintf('%04d-%02d-%02d 00:00:00', $anio, $mes, $diaCumple),
        'fecha_fin'    => sprintf('%04d-%02d-%02d 23:59:59', $anio, $mes, $diaCumple),
        'color' => '#B19A6D', 'publico' => false, 'es_cumple' => true,
        'foto_perfil' => $cp['foto_perfil'] ?? null, 'nombre_cumple' => $nombreC, 'edad' => '',
    ];
}

// Kanban
$listaTareas = ['pendiente' => [], 'en_proceso' => [], 'completada' => []];
$stmtT = $pdo->prepare(
    "SELECT t.*, COALESCE(a.nombre, p.nombre) AS asignado_nombre
     FROM sb_kanban_tareas t
     LEFT JOIN administradores a ON t.asignado_a = a.id
     LEFT JOIN cat_personal p ON t.asignado_a = p.cve_personal
     WHERE t.cve_area = ? ORDER BY t.estatus DESC, t.id DESC"
);
$stmtT->execute([$cveArea]);
foreach ($stmtT->fetchAll(PDO::FETCH_ASSOC) as $t) {
    if (isset($listaTareas[$t['estatus']])) $listaTareas[$t['estatus']][] = $t;
    else $listaTareas['pendiente'][] = $t;
}

// Personal para asignar
$stmtP = $pdo->prepare("SELECT cve_personal AS id, CONCAT(nombre,' ',appat,' ',apmat) AS nombre FROM cat_personal WHERE activo = TRUE AND cve_area = ? ORDER BY nombre");
$stmtP->execute([$cveArea]);
$adminsDisponibles = $stmtP->fetchAll(PDO::FETCH_ASSOC);

$mesesNombres = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];
$pageTitle  = 'Agenda y Tablero de Tareas';
$activeMenu = 'agenda';

$extraHead = '<style>
:root { --color-primary:#6d28d9; --color-primary-dark:#4c1d95; --color-primary-light:#3b82f6; --color-primary-hover:#7c3aed; --color-accent:#B19A6D; }
.calendar-wrapper{background:#fff;border-radius:16px;box-shadow:0 10px 30px -10px rgba(0,0,0,.08);overflow:hidden;margin-top:1.5rem;border:1px solid rgba(0,0,0,.05);}
.calendar-header-nav{display:flex;justify-content:space-between;align-items:center;padding:1.25rem 2rem;background:#fdfdfd;border-bottom:1px solid rgba(0,0,0,.06);}
.calendar-header-nav h3{margin:0;font-size:1.4rem;font-weight:700;color:var(--color-primary);letter-spacing:-.01em;}
.nav-btn-group{display:flex;gap:.5rem;align-items:center;}
.calendar-header-nav .btn-outline{border-radius:8px;padding:.4rem .8rem;font-weight:500;border:1px solid #e2e8f0;color:#475569;background:#fff;transition:all .2s ease;}
.calendar-header-nav .btn-outline:hover{background:#f8fafc;color:var(--color-primary);border-color:#cbd5e1;}
.calendar-grid{display:grid;grid-template-columns:repeat(7,1fr);background:#f8fafc;gap:1px;}
.calendar-day-name{padding:1rem .5rem;text-align:right;font-weight:800;font-size:.75rem;color:#64748b;text-transform:uppercase;letter-spacing:.05em;background:#fff;}
.calendar-cell{min-height:130px;padding:.5rem;background:#fff;cursor:pointer;transition:background .2s;display:flex;flex-direction:column;gap:.4rem;overflow:hidden;min-width:0;width:100%;}
.calendar-cell:hover{background:#fdfdfd;}
.calendar-cell.empty{background:#f8fafc;cursor:default;}
.day-number{font-weight:700;color:#334155;font-size:1rem;display:inline-flex;justify-content:center;align-self:flex-end;width:28px;height:28px;align-items:center;border-radius:50%;transition:all .2s;}
.calendar-cell.today .day-number{color:#fff;background:var(--color-primary);box-shadow:0 2px 8px rgba(30,58,95,.4);}
.evento-pildora{font-size:.75rem;padding:.5rem .6rem;border-radius:2px 2px 12px 2px;color:#1e293b;margin-bottom:.25rem;cursor:pointer;position:relative;box-shadow:2px 2px 4px rgba(0,0,0,.05);transition:all .2s;display:flex;flex-direction:column;gap:.2rem;line-height:1.3;animation:fadeIn .3s;width:100%;box-sizing:border-box;overflow:hidden;}
.evento-pildora:hover{transform:scale(1.02) translateY(-2px) rotate(-1deg);box-shadow:4px 6px 12px rgba(0,0,0,.1);z-index:10;}
.evento-pildora::after{content:"";position:absolute;bottom:0;right:0;border-width:0 0 12px 12px;border-style:solid;border-color:rgba(0,0,0,.06) white;border-radius:0 0 0 2px;}
.evento-titulo{font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:flex;align-items:center;gap:5px;}
.evento-acciones{display:flex;gap:4px;margin-top:0;opacity:0;max-height:0;overflow:hidden;transition:all .2s;}
.evento-pildora:hover .evento-acciones{opacity:1;max-height:30px;margin-top:4px;padding-bottom:2px;}
.btn-evento-accion{flex:1;background:rgba(255,255,255,.7);border:none;border-radius:4px;padding:4px 0;cursor:pointer;font-size:.75rem;color:#475569;display:flex;justify-content:center;align-items:center;transition:all .2s;}
.btn-evento-accion:hover{background:#fff;color:var(--color-primary);}
.nota-dt{background:#ede9fe;border-top:3px solid #6d28d9;}
.nota-institucional{background:#f1f5f9;border-top:3px solid #64748b;}
.nota-dorado{background:#fef08a;border-top:3px solid #ca8a04;}
.cumple-mini-avatar{width:22px;height:22px;border-radius:50%;object-fit:cover;border:1.5px solid #ca8a04;flex-shrink:0;}
.cumple-mini-placeholder{background:#fef08a;display:inline-flex;align-items:center;justify-content:center;font-size:.7rem;color:#ca8a04;width:22px;height:22px;border-radius:50%;}
@keyframes fadeIn{from{opacity:0;transform:translateY(4px)}to{opacity:1;transform:translateY(0)}}
.kanban-board{display:grid;grid-template-columns:repeat(3,1fr);gap:1.5rem;padding:1.5rem 0;}
.kanban-col{background:#f1f5f9;border-radius:12px;display:flex;flex-direction:column;min-height:460px;border:1px solid #e2e8f0;box-shadow:0 4px 6px -1px rgba(0,0,0,.05);}
.kanban-col-header{padding:1.25rem;font-weight:800;font-size:1rem;text-transform:uppercase;letter-spacing:.05em;border-radius:12px 12px 0 0;display:flex;justify-content:space-between;align-items:center;color:white;}
.bg-pendiente{background:#3b82f6;} .bg-en-proceso{background:#f59e0b;} .bg-completada{background:#10b981;}
.kanban-badge{background:rgba(255,255,255,.25);padding:2px 10px;border-radius:20px;font-size:.8rem;}
.kanban-col-body{padding:1rem;flex:1;display:flex;flex-direction:column;gap:1rem;}
.tarea-card{background:#fff;border-radius:10px;box-shadow:0 2px 4px rgba(0,0,0,.04);border:1px solid #e2e8f0;padding:1.25rem;cursor:grab;border-top:5px solid var(--color-primary);transition:all .2s;}
.tarea-card:hover{transform:translateY(-3px);box-shadow:0 12px 20px -5px rgba(0,0,0,.1);}
.tarea-card h4{margin:0 0 8px;font-size:1rem;font-weight:700;color:#1e293b;}
.tarea-card p{margin:0;font-size:.85rem;color:#64748b;line-height:1.5;}
.modal-backdrop{display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(15,23,42,.7);backdrop-filter:blur(8px);z-index:2000;align-items:center;justify-content:center;padding:20px;}
.modal-backdrop.active{display:flex;animation:fadeIn .3s;}
.modal{background:#fff;width:100%;max-width:540px;border-radius:20px;box-shadow:0 25px 50px -12px rgba(0,0,0,.25);overflow:hidden;display:flex;flex-direction:column;}
.modal-header{background:linear-gradient(135deg,var(--color-primary),var(--color-primary-dark));color:#fff;padding:1.25rem 1.5rem;display:flex;align-items:center;justify-content:space-between;}
.modal-title{margin:0;font-size:1.2rem;font-weight:800;display:flex;align-items:center;gap:12px;}
.modal-close{background:none;border:none;color:rgba(255,255,255,.8);cursor:pointer;font-size:1.4rem;transition:color .2s;}
.modal-close:hover{color:#fff;}
.modal-body{padding:1.5rem 2rem;overflow-y:auto;}
.modal-footer{padding:1.25rem 2rem;border-top:1px solid #f1f5f9;background:#fafafa;display:flex;gap:10px;justify-content:flex-end;}
.form-label{font-weight:700;color:#475569;font-size:.9rem;margin-bottom:6px;display:block;}
.form-control{border-radius:10px;border:1px solid #e2e8f0;background:#f8fafc;padding:10px 14px;transition:all .2s;font-family:inherit;font-size:.9rem;width:100%;box-sizing:border-box;}
.form-control:focus{border-color:var(--color-primary);box-shadow:0 0 0 3px rgba(30,58,95,.1);background:#fff;outline:none;}
.mb-16{margin-bottom:16px;}
</style>';

require_once __DIR__ . '/../../includes/header_admin.php';
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
    <div>
        <h2 style="font-weight:800;color:#0f172a;margin:0;"><i class="fa-solid fa-calendar-week" style="color:#6d28d9;"></i> Agenda JurÃ­dico-Administrativa</h2>
        <p style="color:#64748b;margin:0;">GestiÃ³n de eventos del Ã¡rea y tablero de tareas.</p>
    </div>
    <div style="display:flex;gap:10px;">
        <button class="btn btn-primary" onclick="abrirModalCrear()" style="background:#6d28d9;border-color:#6d28d9;">
            <i class="fa-solid fa-plus"></i> Nuevo Evento
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
            <a href="?mes=<?= $mesAnt->format('m') ?>&anio=<?= $mesAnt->format('Y') ?>" class="btn btn-outline"><i class="fa-solid fa-left-long"></i></a>
            <a href="?mes=<?= date('m') ?>&anio=<?= date('Y') ?>" class="btn btn-outline">Hoy</a>
            <a href="?mes=<?= $mesSig->format('m') ?>&anio=<?= $mesSig->format('Y') ?>" class="btn btn-outline"><i class="fa-solid fa-right-long"></i></a>
        </div>
    </div>
    <div class="calendar-grid">
        <?php foreach (['Lun','Mar','MiÃ©','Jue','Vie','SÃ¡b','Dom'] as $d): ?>
            <div class="calendar-day-name"><?= $d ?></div>
        <?php endforeach; ?>
        <?php for ($i = 1; $i < $inicioSem; $i++): ?>
            <div class="calendar-cell empty"></div>
        <?php endfor; ?>
        <?php for ($dia = 1; $dia <= $diasEnMes; $dia++):
            $esHoy = ($dia === (int)date('d') && $mes === (int)date('m') && $anio === (int)date('Y'));
            $fechaIso = sprintf('%04d-%02d-%02d', $anio, $mes, $dia); ?>
            <div class="calendar-cell <?= $esHoy ? 'today' : '' ?>" onclick="abrirModalCrearDesdeCelda('<?= $fechaIso ?>','<?= $fechaIso ?>')">
                <div class="day-number"><?= $dia ?></div>
                <?php foreach ($calEvs[$dia] ?? [] as $ev):
                    $colorNota  = isset($ev['es_cumple']) ? 'nota-dorado' : (isset($ev['es_institucional']) ? 'nota-institucional' : 'nota-dt');
                    $customStyle = (!isset($ev['es_cumple']) && !empty($ev['color'])) ? 'style="border-top-color:'.$ev['color'].';background-color:'.$ev['color'].'1a;"' : ''; ?>
                    <div class="evento-pildora <?= $colorNota ?>" <?= $customStyle ?>>
                        <div class="evento-titulo">
                            <?php if (isset($ev['es_cumple'])): ?>
                                <?php if (!empty($ev['foto_perfil'])): ?><img src="<?= BASE_URL ?>public/uploads/avatares/<?= esc($ev['foto_perfil']) ?>" class="cumple-mini-avatar"><?php else: ?><div class="cumple-mini-avatar cumple-mini-placeholder"><i class="fa-solid fa-cake-candles"></i></div><?php endif; ?>
                            <?php endif; ?>
                            <?= esc($ev['titulo']) ?>
                        </div>
                        <div class="evento-acciones">
                            <?php if (!isset($ev['es_cumple']) && !isset($ev['es_institucional'])): ?>
                                <button type="button" class="btn-evento-accion" title="Editar" onclick="event.stopPropagation();abrirModalEditar(<?= $ev['id'] ?>,'<?= esc($ev['titulo']) ?>','<?= esc($ev['descripcion'] ?? '') ?>','<?= date('Y-m-d\TH:i',strtotime($ev['fecha_inicio'])) ?>','<?= date('Y-m-d\TH:i',strtotime($ev['fecha_fin'])) ?>','<?= $ev['color'] ?>')"><i class="fa-solid fa-pen"></i></button>
                                <button type="button" class="btn-evento-accion" title="Eliminar" onclick="event.stopPropagation();confirEliminarEvento(<?= $ev['id'] ?>)" style="color:#ef4444;"><i class="fa-solid fa-trash-can"></i></button>
                            <?php else: ?>
                                <button type="button" class="btn-evento-accion" title="Ver" onclick="event.stopPropagation();"><i class="fa-solid fa-eye"></i></button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endfor; ?>
    </div>
</div>

<!-- KANBAN -->
<div id="kanban" style="margin-top:50px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
        <h3 style="font-weight:800;color:#1e293b;margin:0;"><i class="fa-solid fa-list-check" style="color:#3b82f6;"></i> Tablero de Tareas del Ãrea</h3>
        <button class="btn btn-primary" onclick="abrirModalCrearTarea()" style="background:#3b82f6;border:none;padding:.5rem 1rem;font-size:.85rem;">
            <i class="fa-solid fa-plus"></i> Nueva Tarea
        </button>
    </div>
    <div class="kanban-board">
        <?php foreach (['pendiente' => 'Pendiente', 'en_proceso' => 'En Proceso', 'completada' => 'Completada'] as $idCol => $lblCol): ?>
            <div class="kanban-col" ondragover="allowDrop(event)" ondrop="drop(event,'<?= $idCol ?>')">
                <div class="kanban-col-header bg-<?= str_replace('_','-',$idCol) ?>">
                    <span><?= strtoupper($lblCol) ?></span>
                    <span class="kanban-badge"><?= count($listaTareas[$idCol]) ?></span>
                </div>
                <div class="kanban-col-body">
                    <?php if (empty($listaTareas[$idCol])): ?>
                        <div style="color:#94a3b8;font-size:.85rem;text-align:center;padding:20px;">Sin tareas</div>
                    <?php endif; ?>
                    <?php foreach ($listaTareas[$idCol] as $t): ?>
                        <div class="tarea-card" draggable="true" ondragstart="drag(event,<?= $t['id'] ?>)" style="border-top-color:<?= $t['color'] ?>;"
                             onclick="abrirModalEditarTarea(<?= $t['id'] ?>,'<?= esc($t['titulo']) ?>','<?= esc($t['descripcion']) ?>','<?= $t['color'] ?>',<?= (int)$t['asignado_a'] ?>)">
                            <h4><?= esc($t['titulo']) ?></h4>
                            <p><?= esc(mb_strimwidth($t['descripcion'] ?? '', 0, 80, '...')) ?></p>
                            <div style="margin-top:10px;font-size:.7rem;color:#94a3b8;display:flex;justify-content:space-between;">
                                <span><i class="fa-solid fa-user-tag"></i> <?= esc($t['asignado_nombre'] ?: 'Sin asignar') ?></span>
                                <i class="fa-solid fa-up-down-left-right" style="opacity:.3;"></i>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- MODALES -->
<!-- Crear Evento -->
<div class="modal-backdrop" id="modalCrearEvento">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fa-solid fa-calendar-plus"></i> Nuevo Evento</h3>
            <button class="modal-close" onclick="cerrarModal('modalCrearEvento')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST">
            <?= csrfField() ?><input type="hidden" name="_accion" value="crear_evento">
            <div class="modal-body">
                <div class="mb-16"><label class="form-label">TÃ­tulo *</label><input type="text" name="titulo" class="form-control" id="c_titulo" required></div>
                <div class="mb-16"><label class="form-label">DescripciÃ³n</label><textarea name="descripcion" class="form-control" rows="3"></textarea></div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;" class="mb-16">
                    <div><label class="form-label">Desde *</label><input type="date" name="fecha_inicio" id="c_fi" class="form-control" required></div>
                    <div><label class="form-label">Hasta *</label><input type="date" name="fecha_fin" id="c_ff" class="form-control" required></div>
                </div>
                <div><label class="form-label">Color</label><input type="color" name="color" value="#6d28d9" class="form-control" style="height:42px;padding:4px;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="cerrarModal('modalCrearEvento')" class="btn" style="background:#f1f5f9;color:#475569;">Cancelar</button>
                <button type="submit" class="btn btn-primary" style="background:#6d28d9;border-color:#6d28d9;"><i class="fa-solid fa-check"></i> Guardar</button>
            </div>
        </form>
    </div>
</div>

<!-- Editar Evento -->
<div class="modal-backdrop" id="modalEditarEvento">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fa-solid fa-pencil"></i> Editar Evento</h3>
            <button class="modal-close" onclick="cerrarModal('modalEditarEvento')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" id="formEditarEvento">
            <?= csrfField() ?><input type="hidden" name="_accion" value="editar_evento">
            <input type="hidden" name="evento_id" id="e_id">
            <div class="modal-body">
                <div class="mb-16"><label class="form-label">TÃ­tulo *</label><input type="text" name="titulo" class="form-control" id="e_titulo" required></div>
                <div class="mb-16"><label class="form-label">DescripciÃ³n</label><textarea name="descripcion" class="form-control" id="e_desc" rows="3"></textarea></div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;" class="mb-16">
                    <div><label class="form-label">Desde *</label><input type="date" name="fecha_inicio" id="e_fi" class="form-control" required></div>
                    <div><label class="form-label">Hasta *</label><input type="date" name="fecha_fin" id="e_ff" class="form-control" required></div>
                </div>
                <div><label class="form-label">Color</label><input type="color" name="color" id="e_color" class="form-control" style="height:42px;padding:4px;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="cerrarModal('modalEditarEvento')" class="btn" style="background:#f1f5f9;color:#475569;">Cancelar</button>
                <button type="submit" class="btn btn-primary" style="background:#6d28d9;border-color:#6d28d9;"><i class="fa-solid fa-check"></i> Actualizar</button>
            </div>
        </form>
    </div>
</div>

<!-- Eliminar Evento (Form oculto) -->
<form method="POST" id="formEliminarEvento" style="display:none;">
    <?= csrfField() ?><input type="hidden" name="_accion" value="eliminar_evento">
    <input type="hidden" name="evento_id" id="del_ev_id">
</form>

<!-- Crear Tarea -->
<div class="modal-backdrop" id="modalCrearTarea">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fa-solid fa-plus"></i> Nueva Tarea</h3>
            <button class="modal-close" onclick="cerrarModal('modalCrearTarea')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST">
            <?= csrfField() ?><input type="hidden" name="_accion" value="crear_tarea">
            <div class="modal-body">
                <div class="mb-16"><label class="form-label">TÃ­tulo *</label><input type="text" name="titulo" class="form-control" required></div>
                <div class="mb-16"><label class="form-label">DescripciÃ³n</label><textarea name="descripcion" class="form-control" rows="3"></textarea></div>
                <div class="mb-16">
                    <label class="form-label">Asignar a</label>
                    <select name="asignado_a" class="form-control">
                        <option value="">Sin asignar</option>
                        <?php foreach ($adminsDisponibles as $a): ?>
                        <option value="<?= $a['id'] ?>"><?= esc($a['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div><label class="form-label">Color</label><input type="color" name="color" value="#6d28d9" class="form-control" style="height:42px;padding:4px;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="cerrarModal('modalCrearTarea')" class="btn" style="background:#f1f5f9;color:#475569;">Cancelar</button>
                <button type="submit" class="btn btn-primary" style="background:#3b82f6;border-color:#3b82f6;"><i class="fa-solid fa-check"></i> Crear Tarea</button>
            </div>
        </form>
    </div>
</div>

<!-- Editar Tarea -->
<div class="modal-backdrop" id="modalEditarTarea">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fa-solid fa-pencil"></i> Editar Tarea</h3>
            <button class="modal-close" onclick="cerrarModal('modalEditarTarea')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" id="formEditarTarea">
            <?= csrfField() ?><input type="hidden" name="_accion" value="editar_tarea">
            <input type="hidden" name="tarea_id" id="et_id">
            <div class="modal-body">
                <div class="mb-16"><label class="form-label">TÃ­tulo *</label><input type="text" name="titulo" class="form-control" id="et_titulo" required></div>
                <div class="mb-16"><label class="form-label">DescripciÃ³n</label><textarea name="descripcion" class="form-control" id="et_desc" rows="3"></textarea></div>
                <div class="mb-16">
                    <label class="form-label">Asignar a</label>
                    <select name="asignado_a" id="et_asig" class="form-control">
                        <option value="">Sin asignar</option>
                        <?php foreach ($adminsDisponibles as $a): ?>
                        <option value="<?= $a['id'] ?>"><?= esc($a['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;">
                    <div style="flex:1"><label class="form-label">Color</label><input type="color" name="color" id="et_color" class="form-control" style="height:42px;padding:4px;"></div>
                    <div>
                        <label class="form-label">Mover a</label>
                        <form method="POST" id="formMoverTarea" style="display:inline;">
                            <?= csrfField() ?><input type="hidden" name="_accion" value="mover_tarea">
                            <input type="hidden" name="tarea_id" id="mv_id">
                            <input type="hidden" name="nuevo_estatus" id="mv_estatus">
                        </form>
                        <div style="display:flex;gap:6px;">
                            <button type="button" onclick="moverTarea('pendiente')" class="btn" style="background:#eff6ff;color:#3b82f6;border:1px solid #bfdbfe;padding:6px 10px;font-size:.78rem;">Pendiente</button>
                            <button type="button" onclick="moverTarea('en_proceso')" class="btn" style="background:#fffbeb;color:#f59e0b;border:1px solid #fde68a;padding:6px 10px;font-size:.78rem;">Proceso</button>
                            <button type="button" onclick="moverTarea('completada')" class="btn" style="background:#f0fdf4;color:#10b981;border:1px solid #a7f3d0;padding:6px 10px;font-size:.78rem;">Completada</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="confirEliminarTarea()" class="btn" style="background:#fef2f2;color:#ef4444;border:1px solid #fca5a5;margin-right:auto;"><i class="fa-solid fa-trash-can"></i></button>
                <button type="button" onclick="cerrarModal('modalEditarTarea')" class="btn" style="background:#f1f5f9;color:#475569;">Cancelar</button>
                <button type="submit" form="formEditarTarea" class="btn btn-primary" style="background:#3b82f6;border-color:#3b82f6;"><i class="fa-solid fa-check"></i> Actualizar</button>
            </div>
        </form>
        <form method="POST" id="formEliminarTarea" style="display:none;">
            <?= csrfField() ?><input type="hidden" name="_accion" value="eliminar_tarea">
            <input type="hidden" name="tarea_id" id="del_t_id">
        </form>
    </div>
</div>

<script>
function cerrarModal(id) { document.getElementById(id).classList.remove('active'); }
function abrirModalCrear() { document.getElementById('modalCrearEvento').classList.add('active'); }
function abrirModalCrearDesdeCelda(fi, ff) {
    document.getElementById('c_fi').value = fi;
    document.getElementById('c_ff').value = ff;
    document.getElementById('modalCrearEvento').classList.add('active');
}
function abrirModalEditar(id, titulo, desc, fi, ff, color) {
    document.getElementById('e_id').value = id;
    document.getElementById('e_titulo').value = titulo;
    document.getElementById('e_desc').value = desc;
    document.getElementById('e_fi').value = fi;
    document.getElementById('e_ff').value = ff;
    document.getElementById('e_color').value = color || '#6d28d9';
    document.getElementById('modalEditarEvento').classList.add('active');
}
function confirEliminarEvento(id) {
    if (!confirm('Â¿Eliminar este evento?')) return;
    document.getElementById('del_ev_id').value = id;
    document.getElementById('formEliminarEvento').submit();
}
function abrirModalCrearTarea() { document.getElementById('modalCrearTarea').classList.add('active'); }
function abrirModalEditarTarea(id, titulo, desc, color, asigId) {
    document.getElementById('et_id').value = id;
    document.getElementById('mv_id').value = id;
    document.getElementById('et_titulo').value = titulo;
    document.getElementById('et_desc').value = desc;
    document.getElementById('et_color').value = color || '#6d28d9';
    const sel = document.getElementById('et_asig');
    [...sel.options].forEach(o => o.selected = (o.value == asigId));
    document.getElementById('modalEditarTarea').classList.add('active');
}
function moverTarea(estatus) {
    document.getElementById('mv_estatus').value = estatus;
    document.getElementById('formMoverTarea').submit();
}
function confirEliminarTarea() {
    if (!confirm('Â¿Eliminar esta tarea?')) return;
    document.getElementById('del_t_id').value = document.getElementById('et_id').value;
    document.getElementById('formEliminarTarea').submit();
}
// Drag & Drop Kanban
function drag(event, id) { event.dataTransfer.setData('tareaId', id); }
function allowDrop(event) { event.preventDefault(); }
function drop(event, estatus) {
    event.preventDefault();
    const id = event.dataTransfer.getData('tareaId');
    const f = document.createElement('form');
    f.method = 'POST'; f.style.display = 'none';
    f.innerHTML = `<?= csrfField() ?><input name="_accion" value="mover_tarea"><input name="tarea_id" value="${id}"><input name="nuevo_estatus" value="${estatus}">`;
    document.body.appendChild(f); f.submit();
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') document.querySelectorAll('.modal-backdrop.active').forEach(m => m.classList.remove('active')); });
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

