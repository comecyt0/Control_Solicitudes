<?php
/**
 * COMECyT — Agenda y Tablero de Tareas
 * Área: Desarrollo Tecnológico y Vinculación | cve_area = 12
 * Paleta: Violet #0f766e
 *
 * REGLAS DE PERMISOS:
 *   - Esta área SOLO puede crear/editar/eliminar sus propios eventos (df_eventos_editoriales, cve_area=12).
 *   - Los eventos institucionales de la tabla `eventos` se muestran en SOLO LECTURA.
 *   - Solo Dirección General y Sistemas pueden crear/editar eventos públicos institucionales.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../config/auth.php';

verificarSesionAdmin();

$pdo          = getConnection();
$cveArea      = 10;
$colorPrimary = '#0f766e';
$adminId      = (int) ($_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 0);
$mensajeFlash = '';
$tipoFlash    = '';

// ── PRG: Acciones ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_accion'])) {
    validarCsrfPost();
    $accion = $_POST['_accion'];

    if ($accion === 'crear_evento') {
        $titulo = trim(postParam('titulo'));
        $desc   = trim(postParam('descripcion'));
        $fi     = postParam('fecha_inicio');
        $ff     = postParam('fecha_fin');
        $color  = postParam('color', $colorPrimary);
        if ($titulo && $fi && $ff) {
            $chk = $pdo->prepare("SELECT 1 FROM administradores WHERE id = ?");
            $chk->execute([$adminId]);
            $idAutor = $chk->fetch() ? $adminId : null;
            $pdo->prepare("INSERT INTO df_eventos_editoriales (titulo, descripcion, fecha_inicio, fecha_fin, color, creado_por, publico, cve_area) VALUES (?,?,?,?,?,?,FALSE,?)")
                ->execute([$titulo, $desc, $fi, $ff, $color, $idAutor, $cveArea]);
            header('Location: agenda.php?flash=evento_creado'); exit;
        }
    } elseif ($accion === 'editar_evento') {
        $id     = (int) postParam('evento_id');
        $titulo = trim(postParam('titulo'));
        $desc   = trim(postParam('descripcion'));
        $fi     = postParam('fecha_inicio');
        $ff     = postParam('fecha_fin');
        $color  = postParam('color', $colorPrimary);
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
            $chk = $pdo->prepare("SELECT 1 FROM administradores WHERE id = ?");
            $chk->execute([$adminId]);
            $idAutor = $chk->fetch() ? $adminId : null;
            $asig    = !empty($_POST['asignado_a']) ? (int)$_POST['asignado_a'] : null;
            $pdo->prepare("INSERT INTO sb_kanban_tareas (titulo, descripcion, color, estatus, creado_por, asignado_a, cve_area) VALUES (?,?,?,'pendiente',?,?,?)")
                ->execute([$titulo, trim(postParam('descripcion')), postParam('color', $colorPrimary), $idAutor, $asig, $cveArea]);
            header('Location: agenda.php?flash=tarea_creada#kanban'); exit;
        }
    } elseif ($accion === 'editar_tarea') {
        $id = (int) postParam('tarea_id');
        if ($id > 0 && trim(postParam('titulo'))) {
            $asig = !empty($_POST['asignado_a']) ? (int)$_POST['asignado_a'] : null;
            $pdo->prepare("UPDATE sb_kanban_tareas SET titulo=?,descripcion=?,color=?,asignado_a=? WHERE id=? AND cve_area=?")
                ->execute([trim(postParam('titulo')), trim(postParam('descripcion')), postParam('color', $colorPrimary), $asig, $id, $cveArea]);
            header('Location: agenda.php?flash=tarea_editada#kanban'); exit;
        }
    } elseif ($accion === 'mover_tarea') {
        $id = (int) postParam('tarea_id');
        $e  = postParam('nuevo_estatus');
        if ($id > 0 && in_array($e, ['pendiente','en_proceso','completada'])) {
            $pdo->prepare("UPDATE sb_kanban_tareas SET estatus=? WHERE id=? AND cve_area=?")->execute([$e, $id, $cveArea]);
            header('Location: agenda.php?flash=tarea_movida#kanban'); exit;
        }
    } elseif ($accion === 'eliminar_tarea') {
        $id = (int) postParam('tarea_id');
        if ($id > 0) {
            $pdo->prepare("DELETE FROM sb_kanban_tareas WHERE id=? AND cve_area=?")->execute([$id, $cveArea]);
            header('Location: agenda.php?flash=tarea_eliminada#kanban'); exit;
        }
    }
}

// ── Flash ──────────────────────────────────────────────────────────────────
$flashMap = [
    'evento_creado'    => ['Evento agendado correctamente.', 'success'],
    'evento_editado'   => ['Evento actualizado.',            'success'],
    'evento_eliminado' => ['Evento eliminado.',              'success'],
    'tarea_creada'     => ['Tarea añadida al tablero.',      'success'],
    'tarea_editada'    => ['Tarea actualizada.',             'success'],
    'tarea_movida'     => ['Estatus actualizado.',           'success'],
    'tarea_eliminada'  => ['Tarea eliminada.',               'success'],
];
$flashCode = getParam('flash');
if (isset($flashMap[$flashCode])) [$mensajeFlash, $tipoFlash] = $flashMap[$flashCode];

// ── Calendario ──────────────────────────────────────────────────────────────
$hoy  = new DateTime();
$mes  = (int) getParam('mes',  $hoy->format('m'));
$anio = (int) getParam('anio', $hoy->format('Y'));
if ($mes < 1 || $mes > 12)        $mes  = (int)$hoy->format('m');
if ($anio < 2000 || $anio > 2100) $anio = (int)$hoy->format('Y');

$dtMes     = DateTime::createFromFormat('Y-m-d', sprintf('%04d-%02d-01', $anio, $mes));
$mesAnt    = (clone $dtMes)->modify('-1 month');
$mesSig    = (clone $dtMes)->modify('+1 month');
$diasEnMes = (int)$dtMes->format('t');
$inicioSem = (int)$dtMes->format('N');

$ini = $dtMes->format('Y-m-01 00:00:00');
$fin = $mesSig->format('Y-m-01 00:00:00');

// Eventos propios del área (editables)
$stmt = $pdo->prepare("SELECT *, FALSE AS es_institucional FROM df_eventos_editoriales WHERE cve_area = ? AND fecha_inicio < ? AND fecha_fin > ? ORDER BY fecha_inicio");
$stmt->execute([$cveArea, $fin, $ini]);
$eventosRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Eventos institucionales públicos (solo lectura - tabla `eventos`)
$stmtG = $pdo->prepare("SELECT *, TRUE AS es_institucional FROM eventos WHERE publico = TRUE AND fecha_inicio < ? AND fecha_fin > ? ORDER BY fecha_inicio");
$stmtG->execute([$fin, $ini]);
foreach ($stmtG->fetchAll(PDO::FETCH_ASSOC) as $eg) {
    if (empty($eg['color'])) $eg['color'] = '#64748b';
    $eventosRaw[] = $eg;
}

// Mapear por día
$calEvs = [];
foreach ($eventosRaw as $ev) {
    $d = (int)(new DateTime($ev['fecha_inicio']))->format('d');
    $calEvs[$d][] = $ev;
}

// Cumpleaños del mes (institucionales - toda la organización)
$mesAligned = str_pad($mes, 2, '0', STR_PAD_LEFT);
$stmtB = $pdo->prepare("
    SELECT TRIM(CONCAT(nombre,' ',COALESCE(appat,''),' ',COALESCE(apmat,''))) AS nombre, fecha_nacimiento, foto_perfil
    FROM cat_personal WHERE activo = TRUE AND fecha_nacimiento IS NOT NULL AND TO_CHAR(fecha_nacimiento,'MM') = ?
    UNION
    SELECT TRIM(CONCAT(nombre,' ',COALESCE(appat,''),' ',COALESCE(apmat,''))) AS nombre, fecha_nacimiento, foto_perfil
    FROM ss_usuarios WHERE activo = TRUE AND fecha_nacimiento IS NOT NULL AND TO_CHAR(fecha_nacimiento,'MM') = ?
");
$stmtB->execute([$mesAligned, $mesAligned]);
foreach ($stmtB->fetchAll(PDO::FETCH_ASSOC) as $cp) {
    $diaCumple = (int)(new DateTime($cp['fecha_nacimiento']))->format('d');
    $nombreC   = trim($cp['nombre']);
    $calEvs[$diaCumple][] = [
        'id'            => null,
        'titulo'        => '🎂 '.$nombreC,
        'descripcion'   => 'Cumpleaños institucional',
        'fecha_inicio'  => sprintf('%04d-%02d-%02d 00:00:00', $anio, $mes, $diaCumple),
        'fecha_fin'     => sprintf('%04d-%02d-%02d 23:59:59', $anio, $mes, $diaCumple),
        'color'         => '#B19A6D',
        'es_cumple'     => true,
        'es_institucional' => false,
        'foto_perfil'   => $cp['foto_perfil'] ?? null,
        'nombre_cumple' => $nombreC,
        'fecha_display' => date('d/m', mktime(0,0,0,$mes,$diaCumple,$anio)),
    ];
}

// ── Kanban ──────────────────────────────────────────────────────────────────
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
    $key = isset($listaTareas[$t['estatus']]) ? $t['estatus'] : 'pendiente';
    $listaTareas[$key][] = $t;
}

$stmtP = $pdo->prepare("SELECT cve_personal AS id, TRIM(CONCAT(nombre,' ',COALESCE(appat,''),' ',COALESCE(apmat,''))) AS nombre FROM cat_personal WHERE activo = TRUE AND cve_area = ? ORDER BY nombre");
$stmtP->execute([$cveArea]);
$adminsDisponibles = $stmtP->fetchAll(PDO::FETCH_ASSOC);

$mesesNombres = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];
$pageTitle    = 'Agenda y Tablero de Tareas';
$activeMenu   = 'agenda';

$extraHead = '<style>
:root{--color-primary:#0f766e;--color-primary-dark:#0a4b46;--color-primary-hover:#14b8a6;}
.calendar-wrapper{background:#fff;border-radius:16px;box-shadow:0 10px 30px -10px rgba(0,0,0,.08);overflow:hidden;margin-top:1.5rem;border:1px solid rgba(0,0,0,.05);}
.calendar-header-nav{display:flex;justify-content:space-between;align-items:center;padding:1.25rem 2rem;background:#fdfdfd;border-bottom:1px solid rgba(0,0,0,.06);}
.calendar-header-nav h3{margin:0;font-size:1.4rem;font-weight:700;color:#0f766e;}
.nav-btn-group{display:flex;gap:.5rem;align-items:center;}
.btn-nav{border-radius:8px;padding:.4rem .8rem;font-weight:500;border:1px solid #e2e8f0;color:#475569;background:#fff;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:4px;transition:all .2s;}
.btn-nav:hover{background:#f8fafc;color:#0f766e;}
.calendar-grid{display:grid;grid-template-columns:repeat(7,1fr);background:#f8fafc;gap:1px;}
.calendar-day-name{padding:1rem .5rem;text-align:right;font-weight:600;font-size:.8rem;color:#64748b;text-transform:uppercase;background:#fff;}
.calendar-cell{min-height:140px;padding:.5rem;background:#fff;cursor:pointer;transition:background .2s;display:flex;flex-direction:column;gap:.4rem;overflow:hidden;min-width:0;width:100%;}
.calendar-cell:hover{background:#fdfdfd;}
.calendar-cell.empty{background:#f8fafc;cursor:default;}
.day-number{font-weight:500;color:#334155;font-size:1rem;align-self:flex-end;width:28px;height:28px;display:flex;align-items:center;justify-content:center;border-radius:50%;}
.calendar-cell.today .day-number{color:#fff;background:#0f766e;font-weight:700;}
.evento-pildora{font-size:.75rem;padding:.5rem .6rem;border-radius:2px 2px 12px 2px;color:#1e293b;margin-bottom:.25rem;cursor:pointer;position:relative;box-shadow:2px 2px 4px rgba(0,0,0,.05);transition:all .2s;display:flex;flex-direction:column;gap:.2rem;width:100%;box-sizing:border-box;overflow:hidden;}
.evento-pildora:hover{transform:scale(1.02) translateY(-2px);box-shadow:4px 6px 12px rgba(0,0,0,.1);z-index:10;}
.evento-pildora::after{content:"";position:absolute;bottom:0;right:0;border-width:0 0 12px 12px;border-style:solid;border-color:rgba(0,0,0,.06) white;border-radius:0 0 0 2px;}
.evento-titulo{font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:flex;align-items:center;gap:5px;}
.evento-hora{font-size:.65rem;opacity:.75;font-weight:500;display:flex;align-items:center;gap:3px;}
.evento-acciones{display:flex;gap:4px;margin-top:0;opacity:0;max-height:0;overflow:hidden;transition:all .2s;}
.evento-pildora:hover .evento-acciones{opacity:1;max-height:30px;margin-top:4px;padding-bottom:2px;}
.btn-evento-accion{flex:1;background:rgba(255,255,255,.7);border:none;border-radius:4px;padding:4px 0;cursor:pointer;font-size:.75rem;color:#475569;display:flex;justify-content:center;align-items:center;transition:all .2s;}
.btn-evento-accion:hover{background:#fff;color:#0f766e;}
.nota-rrhh{background:#ede9fe;border-top:3px solid #0f766e;}
.nota-institucional{background:#f1f5f9;border-top:3px solid #64748b;}
.nota-dorado{background:#fef08a;border-top:3px solid #ca8a04;}
.cumple-mini-avatar{width:20px;height:20px;border-radius:50%;object-fit:cover;border:1.5px solid #ca8a04;flex-shrink:0;display:inline-block;vertical-align:middle;}
.cumple-mini-placeholder{background:#fef08a;display:inline-flex;align-items:center;justify-content:center;font-size:.65rem;color:#ca8a04;width:20px;height:20px;border-radius:50%;}
.kanban-board{display:grid;grid-template-columns:repeat(3,1fr);gap:1.5rem;padding:1.5rem 0;}
.kanban-col{background:#f1f5f9;border-radius:12px;display:flex;flex-direction:column;border:1px solid #e2e8f0;box-shadow:0 4px 6px -1px rgba(0,0,0,.05);}
.kanban-col-header{padding:1.25rem;font-weight:800;font-size:1rem;text-transform:uppercase;letter-spacing:.05em;border-radius:12px 12px 0 0;display:flex;justify-content:space-between;align-items:center;color:#fff;}
.bg-pendiente{background:#3b82f6;}.bg-en-proceso{background:#f59e0b;}.bg-completada{background:#10b981;}
.kanban-badge{background:rgba(255,255,255,.25);padding:2px 10px;border-radius:20px;font-size:.8rem;}
.kanban-col-body{padding:1rem;flex:1;display:flex;flex-direction:column;gap:1rem;overflow-y:auto;max-height:520px;}
.kanban-col-body::-webkit-scrollbar{width:5px;}.kanban-col-body::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:10px;}
.tarea-card{background:#fff;border-radius:10px;box-shadow:0 2px 4px rgba(0,0,0,.04);border:1px solid #e2e8f0;padding:1.25rem;cursor:grab;border-top:5px solid #0f766e;transition:all .2s;}
.tarea-card:hover{transform:translateY(-3px);box-shadow:0 12px 20px -5px rgba(0,0,0,.1);}
.tarea-card h4{margin:0 0 8px;font-size:1rem;font-weight:700;color:#1e293b;}
.tarea-card p{margin:0;font-size:.85rem;color:#64748b;line-height:1.5;}
/* MODALES — usando style.display (NO clases CSS) para compatibilidad con main.css */
.agenda-modal-backdrop{display:none;position:fixed;inset:0;background:rgba(15,23,42,.75);backdrop-filter:blur(6px);z-index:9000;align-items:center;justify-content:center;padding:20px;}
.agenda-modal{background:#fff;width:100%;max-width:540px;border-radius:20px;box-shadow:0 25px 50px -12px rgba(0,0,0,.3);overflow:hidden;display:flex;flex-direction:column;position:relative;z-index:9001;}
.agenda-modal-header{color:#fff;padding:1.25rem 1.5rem;display:flex;align-items:center;justify-content:space-between;}
.agenda-modal-title{margin:0;font-size:1.15rem;font-weight:800;display:flex;align-items:center;gap:10px;}
.agenda-modal-close{background:rgba(255,255,255,.2);border:none;color:#fff;cursor:pointer;font-size:1.1rem;width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;transition:background .2s;flex-shrink:0;line-height:1;}
.agenda-modal-close:hover{background:rgba(255,255,255,.35);}
.agenda-modal-body{padding:1.5rem 2rem;overflow-y:auto;max-height:65vh;}
.agenda-modal-footer{padding:1rem 2rem;border-top:1px solid #f1f5f9;background:#fafafa;display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap;}
.ag-form-label{font-weight:700;color:#475569;font-size:.88rem;margin-bottom:6px;display:block;}
.ag-form-control{border-radius:10px;border:1px solid #e2e8f0;background:#f8fafc;padding:10px 14px;transition:all .2s;font-family:inherit;font-size:.9rem;width:100%;box-sizing:border-box;}
.ag-form-control:focus{border-color:#0f766e;box-shadow:0 0 0 3px rgba(109,40,217,.12);background:#fff;outline:none;}
.mb-16{margin-bottom:16px;}
.btn-ag-primary{background:#0f766e!important;border-color:#0f766e!important;color:#fff!important;}
.btn-ag-primary:hover{background:#14b8a6!important;}
@media(max-width:768px){.kanban-board{grid-template-columns:1fr;}.calendar-grid{display:flex;flex-direction:column;}.calendar-cell.empty{display:none;}.calendar-cell{border:1px solid #e2e8f0;border-radius:10px;}}
@keyframes agFadeIn{from{opacity:0;transform:scale(.97) translateY(8px)}to{opacity:1;transform:none}}
</style>';

require_once __DIR__ . '/../../includes/header_admin.php';
?>

<?php if ($mensajeFlash): ?>
<div class="alert alert-<?= esc($tipoFlash) ?>" data-auto-close="4000">
    <i class="fa-solid <?= $tipoFlash === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation' ?>"></i>
    <?= esc($mensajeFlash) ?>
</div>
<?php endif; ?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
    <div>
        <h2 style="font-weight:800;color:#0f172a;margin:0;"><i class="fa-solid fa-calendar-week" style="color:#0f766e;"></i> Agenda y Tablero de Tareas</h2>
        <p style="color:#64748b;margin:4px 0 0;">Eventos del área — Desarrollo Tecnológico y Vinculación.</p>
    </div>
    <button class="btn" onclick="agAbrirModal('modalCrearEvento')" style="background:#0f766e;border-color:#0f766e;color:#fff;">
        <i class="fa-solid fa-plus"></i> Nuevo Evento
    </button>
</div>

<!-- ══ CALENDARIO ══ -->
<div class="calendar-wrapper">
    <div class="calendar-header-nav">
        <h3><?= $mesesNombres[$mes] ?> <?= $anio ?></h3>
        <div class="nav-btn-group">
            <a href="?mes=<?= $mesAnt->format('m') ?>&anio=<?= $mesAnt->format('Y') ?>" class="btn-nav"><i class="fa-solid fa-chevron-left"></i></a>
            <a href="?mes=<?= date('m') ?>&anio=<?= date('Y') ?>" class="btn-nav">Hoy</a>
            <a href="?mes=<?= $mesSig->format('m') ?>&anio=<?= $mesSig->format('Y') ?>" class="btn-nav"><i class="fa-solid fa-chevron-right"></i></a>
        </div>
    </div>
    <div class="calendar-grid">
        <?php foreach (['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo'] as $d): ?>
            <div class="calendar-day-name"><?= $d ?></div>
        <?php endforeach; ?>
        <?php for ($i = 1; $i < $inicioSem; $i++): ?>
            <div class="calendar-cell empty"></div>
        <?php endfor; ?>
        <?php for ($dia = 1; $dia <= $diasEnMes; $dia++):
            $esHoy    = ($dia === (int)date('d') && $mes === (int)date('m') && $anio === (int)date('Y'));
            $fechaIso = sprintf('%04d-%02d-%02d', $anio, $mes, $dia); ?>
            <div class="calendar-cell <?= $esHoy ? 'today' : '' ?>" onclick="agAbrirCrearDesdeCelda('<?= $fechaIso ?>')">
                <span class="day-number"><?= $dia ?></span>
                <?php foreach ($calEvs[$dia] ?? [] as $ev):
                    $esCumple        = !empty($ev['es_cumple']);
                    $esInstitucional = !empty($ev['es_institucional']);
                    $colorEv         = $ev['color'] ?? $colorPrimary;
                    $styleAttr       = 'background-color:'.esc($colorEv).'1a;border-top:3px solid '.esc($colorEv).';;';
                    if ($esCumple) $styleAttr = '';

                    $claseNota = $esCumple ? 'nota-dorado' : ($esInstitucional ? 'nota-institucional' : 'nota-rrhh');
                    if (!$esCumple && !$esInstitucional) $claseNota = '';

                    $fotoUrl      = '';
                    if ($esCumple && !empty($ev['foto_perfil'])) {
                        $fotoUrl = BASE_URL.'public/uploads/avatares/'.$ev['foto_perfil'];
                    }
                    $fechaDisplay = date('d/m/Y', strtotime($ev['fecha_inicio']));
                    $horaDisplay  = date('H:i', strtotime($ev['fecha_inicio']));
                    $pillTipo     = $esCumple ? 'cumple' : ($esInstitucional ? 'institucional' : 'area');
                ?>
                    <div class="evento-pildora <?= $claseNota ?>"
                         style="<?= $styleAttr ?>;cursor:pointer;"
                         onclick="event.stopPropagation();agPillClick(this)"
                         data-tipo="<?= $pillTipo ?>"
                         data-id="<?= (int)($ev['id'] ?? 0) ?>"
                         data-titulo="<?= esc($ev['titulo'] ?? '') ?>"
                         data-desc="<?= esc($ev['descripcion'] ?? '') ?>"
                         data-fi="<?= esc(date('Y-m-d', strtotime($ev['fecha_inicio']))) ?>"
                         data-ff="<?= esc(date('Y-m-d', strtotime($ev['fecha_fin']))) ?>"
                         data-fi-display="<?= esc(date('d/m/Y H:i', strtotime($ev['fecha_inicio']))) ?>"
                         data-ff-display="<?= esc(date('d/m/Y H:i', strtotime($ev['fecha_fin']))) ?>"
                         data-color="<?= esc($colorEv) ?>"
                         data-foto="<?= esc($fotoUrl) ?>"
                         data-nombre="<?= esc($ev['nombre_cumple'] ?? '') ?>"
                         data-fecha-display="<?= esc($ev['fecha_display'] ?? $fechaDisplay) ?>">
                        <div class="evento-titulo">
                            <?php if ($esCumple): ?>
                                <?php if (!empty($fotoUrl)): ?>
                                    <img src="<?= esc($fotoUrl) ?>" alt="" class="cumple-mini-avatar">
                                <?php else: ?>
                                    <span class="cumple-mini-avatar cumple-mini-placeholder"><i class="fa-solid fa-user"></i></span>
                                <?php endif; ?>
                            <?php endif; ?>
                            <?= esc($ev['titulo']) ?>
                        </div>
                        <?php if (!$esCumple): ?>
                        <div class="evento-hora"><i class="fa-regular fa-clock"></i> <?= $horaDisplay ?></div>
                        <?php endif; ?>
                        <?php if ($pillTipo === 'area'): ?>
                        <div style="font-size:.62rem;opacity:.55;margin-top:2px;display:flex;gap:6px;align-items:center;">
                            <i class="fa-solid fa-eye" title="Ver"></i>
                            <i class="fa-solid fa-pen" title="Editar"></i>
                            <i class="fa-solid fa-trash-can" style="color:#ef4444;" title="Eliminar"></i>
                        </div>
                        <?php elseif ($pillTipo === 'institucional'): ?>
                        <div style="font-size:.62rem;opacity:.5;margin-top:2px;"><i class="fa-solid fa-eye"></i> Ver</div>
                        <?php elseif ($pillTipo === 'cumple'): ?>
                        <div style="font-size:.62rem;opacity:.5;margin-top:2px;"><i class="fa-solid fa-cake-candles"></i> Ver</div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endfor; ?>
        <?php $celdasFal = (7 - ((($inicioSem - 1) + $diasEnMes) % 7)) % 7;
        for ($i = 0; $i < $celdasFal; $i++): ?>
            <div class="calendar-cell empty"></div>
        <?php endfor; ?>
    </div>
</div>

<!-- ══ KANBAN ══ -->
<div id="kanban" style="margin-top:48px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:12px;">
        <h3 style="font-weight:800;color:#1e293b;margin:0;"><i class="fa-solid fa-list-check" style="color:#3b82f6;"></i> Tablero de Tareas del Área</h3>
        <button class="btn" onclick="agAbrirModal('modalCrearTarea')" style="background:#3b82f6;border:none;color:#fff;padding:.5rem 1.1rem;">
            <i class="fa-solid fa-plus"></i> Nueva Tarea
        </button>
    </div>
    <div class="kanban-board">
        <?php foreach (['pendiente' => 'Pendientes', 'en_proceso' => 'En Proceso', 'completada' => 'Completadas'] as $idCol => $lblCol): ?>
            <div class="kanban-col" ondragover="event.preventDefault()" ondrop="agDrop(event,'<?= $idCol ?>')">
                <div class="kanban-col-header bg-<?= str_replace('_','-',$idCol) ?>">
                    <span><?= strtoupper($lblCol) ?></span>
                    <span class="kanban-badge"><?= count($listaTareas[$idCol]) ?></span>
                </div>
                <div class="kanban-col-body">
                    <?php if (empty($listaTareas[$idCol])): ?>
                        <div style="color:#94a3b8;font-size:.85rem;text-align:center;padding:30px 20px;border:2px dashed #e2e8f0;border-radius:10px;">Sin tareas</div>
                    <?php endif; ?>
                    <?php foreach ($listaTareas[$idCol] as $t):
                        $cardColor = !empty($t['color']) ? $t['color'] : $colorPrimary;
                    ?>
                        <div class="tarea-card" draggable="true"
                             ondragstart="event.dataTransfer.setData('tareaId',<?= $t['id'] ?>)"
                             style="border-top-color:<?= esc($cardColor) ?>;"
                             onclick="agAbrirEditarTarea(<?= $t['id'] ?>,'<?= esc(addslashes($t['titulo'])) ?>','<?= esc(addslashes($t['descripcion'] ?? '')) ?>','<?= esc($cardColor) ?>',<?= (int)$t['asignado_a'] ?>)">
                            <h4><?= esc($t['titulo']) ?></h4>
                            <?php if (!empty($t['descripcion'])): ?>
                            <p><?= esc(mb_strimwidth($t['descripcion'], 0, 90, '...')) ?></p>
                            <?php endif; ?>
                            <div style="margin-top:10px;font-size:.72rem;color:#94a3b8;display:flex;justify-content:space-between;align-items:center;">
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

<!-- ════════════ MODALES ════════════ -->
<!-- NOTA: Todos los modales usan style.display='flex'/'none' directamente,
     NO clases CSS como .active/.open, para evitar conflictos con main.css -->

<!-- Modal Ver Evento (solo lectura) -->
<div class="agenda-modal-backdrop" id="modalVerEvento" style="display:none;" onclick="if(event.target===this)agCerrarModal('modalVerEvento')">
    <div class="agenda-modal" style="max-width:440px;animation:agFadeIn .25s;">
        <div class="agenda-modal-header" style="background:linear-gradient(135deg,#475569,#334155);">
            <h3 class="agenda-modal-title"><i class="fa-solid fa-eye"></i> <span id="ver_titulo">Detalle</span></h3>
            <button class="agenda-modal-close" onclick="agCerrarModal('modalVerEvento')">&#x2715;</button>
        </div>
        <div class="agenda-modal-body">
            <p style="margin:0 0 6px;font-size:.82rem;color:#64748b;font-weight:600;">Fecha</p>
            <p style="margin:0 0 16px;font-weight:600;" id="ver_fecha"></p>
            <p style="margin:0 0 6px;font-size:.82rem;color:#64748b;font-weight:600;">Descripción</p>
            <div style="padding:12px;background:#f8fafc;border-radius:10px;border:1px solid #e2e8f0;">
                <p style="margin:0;white-space:pre-wrap;color:#334155;line-height:1.6;" id="ver_desc"></p>
            </div>
        </div>
        <div class="agenda-modal-footer">
            <button type="button" onclick="agCerrarModal('modalVerEvento')" class="btn btn-outline">Cerrar</button>
        </div>
    </div>
</div>

<!-- Modal Ver Cumpleaños (diseño idéntico al calendario público) -->
<div class="agenda-modal-backdrop" id="modalVerCumple" style="display:none;" onclick="if(event.target===this)agCerrarModal('modalVerCumple')">
    <div class="agenda-modal" style="max-width:400px;animation:agFadeIn .25s;">
        <div class="agenda-modal-header" style="background:linear-gradient(135deg,#fef9c3,#fde68a);border-bottom:1px solid #fcd34d;">
            <h3 class="agenda-modal-title" style="color:#92400e;">&#x1F382; &#xA1;Feliz Cumpleaños!</h3>
            <button class="agenda-modal-close" onclick="agCerrarModal('modalVerCumple')" style="background:rgba(146,64,14,.15);color:#92400e;">&#x2715;</button>
        </div>
        <div class="agenda-modal-body" style="text-align:center;padding:2rem 1.5rem;">
            <div style="margin-bottom:1.25rem;">
                <img id="mc_foto" src="" alt="" style="width:110px;height:110px;border-radius:50%;object-fit:cover;border:4px solid #fcd34d;box-shadow:0 8px 20px rgba(0,0,0,.1);display:none;margin:0 auto;">
                <div id="mc_placeholder" style="width:110px;height:110px;border-radius:50%;background:#fde68a;display:flex;align-items:center;justify-content:center;margin:0 auto;border:4px solid #fcd34d;font-size:2.5rem;">&#x1F382;</div>
            </div>
            <h2 id="mc_nombre" style="margin:0 0 .25rem;font-size:1.25rem;font-weight:800;color:#1e293b;"></h2>
            <div style="display:inline-block;background:#fef3c7;border:1px solid #fde68a;border-radius:20px;padding:4px 15px;font-size:.85rem;color:#92400e;font-weight:700;margin-top:.75rem;">
                <i class="fa-solid fa-cake-candles"></i> <span id="mc_fecha"></span>
            </div>
        </div>
        <div class="agenda-modal-footer" style="justify-content:center;">
            <button type="button" class="btn btn-primary" onclick="agCerrarModal('modalVerCumple')">&#xA1;Felicidades!</button>
        </div>
    </div>
</div>

<!-- Modal VER + EDITAR Evento del Área (clic en píldora propia) -->
<div class="agenda-modal-backdrop" id="modalVerDetalle" style="display:none;" onclick="if(event.target===this)agCerrarModal('modalVerDetalle')">
    <div class="agenda-modal" style="max-width:540px;animation:agFadeIn .25s;">
        <div class="agenda-modal-header" style="background:linear-gradient(135deg,<?= $colorPrimary ?>,<?= $colorPrimary ?>cc);">
            <h3 class="agenda-modal-title" id="vd_header_titulo"><i class="fa-solid fa-calendar-check"></i> Detalle del Evento</h3>
            <button class="agenda-modal-close" onclick="agCerrarModal('modalVerDetalle')">&#x2715;</button>
        </div>
        <!-- Vista detalle -->
        <div id="vd_vista" class="agenda-modal-body">
            <div style="margin-bottom:14px;">
                <p style="margin:0 0 4px;font-size:.75rem;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.05em;">T&iacute;tulo</p>
                <p id="vd_titulo" style="margin:0;font-size:1.1rem;font-weight:700;color:#1e293b;"></p>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
                <div style="background:#f8fafc;border-radius:10px;padding:12px;border:1px solid #e2e8f0;">
                    <p style="margin:0 0 4px;font-size:.72rem;font-weight:700;color:#94a3b8;text-transform:uppercase;">Desde</p>
                    <p id="vd_fi" style="margin:0;font-weight:600;color:#334155;"></p>
                </div>
                <div style="background:#f8fafc;border-radius:10px;padding:12px;border:1px solid #e2e8f0;">
                    <p style="margin:0 0 4px;font-size:.72rem;font-weight:700;color:#94a3b8;text-transform:uppercase;">Hasta</p>
                    <p id="vd_ff" style="margin:0;font-weight:600;color:#334155;"></p>
                </div>
            </div>
            <div>
                <p style="margin:0 0 4px;font-size:.75rem;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.05em;">Descripci&oacute;n</p>
                <div style="background:#f8fafc;border-radius:10px;padding:14px;border:1px solid #e2e8f0;min-height:60px;">
                    <p id="vd_desc" style="margin:0;white-space:pre-wrap;color:#334155;line-height:1.6;"></p>
                </div>
            </div>
        </div>
        <!-- Formulario editar (oculto por defecto) -->
        <div id="vd_editar" style="display:none;">
            <form method="POST" id="formVerDetalleEditar">
                <?= csrfField() ?><input type="hidden" name="_accion" value="editar_evento">
                <input type="hidden" name="evento_id" id="vd_ev_id">
                <div class="agenda-modal-body">
                    <div class="mb-16"><label class="ag-form-label">T&iacute;tulo *</label><input type="text" name="titulo" class="ag-form-control" id="vd_e_titulo" required></div>
                    <div class="mb-16"><label class="ag-form-label">Descripci&oacute;n</label><textarea name="descripcion" class="ag-form-control" id="vd_e_desc" rows="3"></textarea></div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;" class="mb-16">
                        <div><label class="ag-form-label">Desde *</label><input type="date" name="fecha_inicio" id="vd_e_fi" class="ag-form-control" required></div>
                        <div><label class="ag-form-label">Hasta *</label><input type="date" name="fecha_fin" id="vd_e_ff" class="ag-form-control" required></div>
                    </div>
                    <div><label class="ag-form-label">Color</label><input type="color" name="color" id="vd_e_color" class="ag-form-control" style="height:44px;padding:4px;cursor:pointer;"></div>
                </div>
                <div class="agenda-modal-footer">
                    <button type="button" onclick="agVdModo('ver')" class="btn btn-outline">Cancelar</button>
                    <button type="submit" form="formVerDetalleEditar" class="btn btn-ag-primary"><i class="fa-solid fa-floppy-disk"></i> Guardar cambios</button>
                </div>
            </form>
        </div>
        <!-- Footer del modo Ver -->
        <div class="agenda-modal-footer" id="vd_footer_ver" style="justify-content:space-between;">
            <button type="button" id="vd_btn_eliminar" onclick="agConfirmarEliminarEvento()" class="btn" style="background:#fef2f2;color:#ef4444;border:1px solid #fca5a5;"><i class="fa-solid fa-trash-can"></i> Eliminar</button>
            <div style="display:flex;gap:8px;">
                <button type="button" onclick="agCerrarModal('modalVerDetalle')" class="btn btn-outline">Cerrar</button>
                <button type="button" onclick="agVdModo('editar')" class="btn btn-ag-primary"><i class="fa-solid fa-pen"></i> Editar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Confirmar Eliminar Evento (reemplaza confirm()) -->
<div class="agenda-modal-backdrop" id="modalConfirmarEliminar" style="display:none;" onclick="if(event.target===this)agCerrarModal('modalConfirmarEliminar')">
    <div class="agenda-modal" style="max-width:400px;animation:agFadeIn .25s;">
        <div class="agenda-modal-header" style="background:linear-gradient(135deg,#dc2626,#991b1b);">
            <h3 class="agenda-modal-title"><i class="fa-solid fa-triangle-exclamation"></i> Confirmar eliminaci&oacute;n</h3>
            <button class="agenda-modal-close" onclick="agCerrarModal('modalConfirmarEliminar')">&#x2715;</button>
        </div>
        <div class="agenda-modal-body" style="text-align:center;padding:2rem 1.5rem;">
            <div style="width:70px;height:70px;border-radius:50%;background:#fef2f2;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;">
                <i class="fa-solid fa-trash-can" style="font-size:1.8rem;color:#ef4444;"></i>
            </div>
            <h3 style="margin:0 0 .5rem;font-size:1.1rem;font-weight:700;color:#1e293b;">&#xBF;Eliminar este evento?</h3>
            <p style="margin:0;color:#64748b;font-size:.9rem;">Esta acci&oacute;n es permanente y no se puede deshacer.</p>
        </div>
        <div class="agenda-modal-footer" style="justify-content:center;gap:16px;">
            <button type="button" onclick="agCerrarModal('modalConfirmarEliminar')" class="btn btn-outline">Cancelar</button>
            <button type="button" onclick="agEjecutarEliminarEvento()" class="btn" style="background:#dc2626;border-color:#dc2626;color:#fff;"><i class="fa-solid fa-trash-can"></i> S&iacute;, eliminar</button>
        </div>
    </div>
</div>
<form method="POST" id="formEliminarEvento" style="display:none;"><?= csrfField() ?><input type="hidden" name="_accion" value="eliminar_evento"><input type="hidden" name="evento_id" id="del_ev_id"></form>

<!-- Modal Crear Evento -->
<div class="agenda-modal-backdrop" id="modalCrearEvento" style="display:none;" onclick="if(event.target===this)agCerrarModal('modalCrearEvento')">
    <div class="agenda-modal" style="animation:agFadeIn .25s;">
        <div class="agenda-modal-header" style="background:linear-gradient(135deg,#0f766e,#0a4b46);">
            <h3 class="agenda-modal-title"><i class="fa-solid fa-calendar-plus"></i> Nuevo Evento</h3>
            <button class="agenda-modal-close" onclick="agCerrarModal('modalCrearEvento')">&#x2715;</button>
        </div>
        <form method="POST">
            <?= csrfField() ?><input type="hidden" name="_accion" value="crear_evento">
            <div class="agenda-modal-body">
                <div class="mb-16"><label class="ag-form-label">Título *</label><input type="text" name="titulo" class="ag-form-control" id="c_titulo" required placeholder="Nombre del evento..."></div>
                <div class="mb-16"><label class="ag-form-label">Descripción</label><textarea name="descripcion" class="ag-form-control" rows="3" placeholder="Detalles opcionales..."></textarea></div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;" class="mb-16">
                    <div><label class="ag-form-label">Desde *</label><input type="date" name="fecha_inicio" id="c_fi" class="ag-form-control" required></div>
                    <div><label class="ag-form-label">Hasta *</label><input type="date" name="fecha_fin" id="c_ff" class="ag-form-control" required></div>
                </div>
                <div><label class="ag-form-label">Color del evento</label><input type="color" name="color" value="<?= $colorPrimary ?>" class="ag-form-control" style="height:44px;padding:4px;cursor:pointer;"></div>
            </div>
            <div class="agenda-modal-footer">
                <button type="button" onclick="agCerrarModal('modalCrearEvento')" class="btn btn-outline">Cancelar</button>
                <button type="submit" class="btn btn-ag-primary"><i class="fa-solid fa-check"></i> Guardar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Editar Evento -->
<div class="agenda-modal-backdrop" id="modalEditarEvento" style="display:none;" onclick="if(event.target===this)agCerrarModal('modalEditarEvento')">
    <div class="agenda-modal" style="animation:agFadeIn .25s;">
        <div class="agenda-modal-header" style="background:linear-gradient(135deg,#0f766e,#0a4b46);">
            <h3 class="agenda-modal-title"><i class="fa-solid fa-pencil"></i> Editar Evento</h3>
            <button class="agenda-modal-close" onclick="agCerrarModal('modalEditarEvento')">&#x2715;</button>
        </div>
        <form method="POST" id="formEditarEvento">
            <?= csrfField() ?><input type="hidden" name="_accion" value="editar_evento">
            <input type="hidden" name="evento_id" id="e_id">
            <div class="agenda-modal-body">
                <div class="mb-16"><label class="ag-form-label">Título *</label><input type="text" name="titulo" class="ag-form-control" id="e_titulo" required></div>
                <div class="mb-16"><label class="ag-form-label">Descripción</label><textarea name="descripcion" class="ag-form-control" id="e_desc" rows="3"></textarea></div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;" class="mb-16">
                    <div><label class="ag-form-label">Desde *</label><input type="date" name="fecha_inicio" id="e_fi" class="ag-form-control" required></div>
                    <div><label class="ag-form-label">Hasta *</label><input type="date" name="fecha_fin" id="e_ff" class="ag-form-control" required></div>
                </div>
                <div><label class="ag-form-label">Color</label><input type="color" name="color" id="e_color" class="ag-form-control" style="height:44px;padding:4px;cursor:pointer;"></div>
            </div>
            <div class="agenda-modal-footer" style="justify-content:space-between;">
                <button type="button" onclick="agConfirmarEliminarEvento(document.getElementById('e_id').value)" class="btn" style="background:#fef2f2;color:#ef4444;border:1px solid #fca5a5;"><i class="fa-solid fa-trash-can"></i></button>
                <div style="display:flex;gap:8px;">
                    <button type="button" onclick="agCerrarModal('modalEditarEvento')" class="btn btn-outline">Cancelar</button>
                    <button type="submit" class="btn btn-ag-primary"><i class="fa-solid fa-floppy-disk"></i> Actualizar</button>
                </div>
            </div>
        </form>
    </div>
</div>
<form method="POST" id="formEliminarEvento" style="display:none;"><?= csrfField() ?><input type="hidden" name="_accion" value="eliminar_evento"><input type="hidden" name="evento_id" id="del_ev_id"></form>

<!-- Modal Crear Tarea -->
<div class="agenda-modal-backdrop" id="modalCrearTarea" style="display:none;" onclick="if(event.target===this)agCerrarModal('modalCrearTarea')">
    <div class="agenda-modal" style="animation:agFadeIn .25s;">
        <div class="agenda-modal-header" style="background:linear-gradient(135deg,#3b82f6,#1d4ed8);">
            <h3 class="agenda-modal-title"><i class="fa-solid fa-plus"></i> Nueva Tarea</h3>
            <button class="agenda-modal-close" onclick="agCerrarModal('modalCrearTarea')">&#x2715;</button>
        </div>
        <form method="POST">
            <?= csrfField() ?><input type="hidden" name="_accion" value="crear_tarea">
            <div class="agenda-modal-body">
                <div class="mb-16"><label class="ag-form-label">Título *</label><input type="text" name="titulo" class="ag-form-control" required placeholder="Descripción breve..."></div>
                <div class="mb-16"><label class="ag-form-label">Descripción</label><textarea name="descripcion" class="ag-form-control" rows="3"></textarea></div>
                <div class="mb-16">
                    <label class="ag-form-label">Asignar a</label>
                    <select name="asignado_a" class="ag-form-control">
                        <option value="">Sin asignar</option>
                        <?php foreach ($adminsDisponibles as $a): ?><option value="<?= $a['id'] ?>"><?= esc($a['nombre']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div><label class="ag-form-label">Color de la tarjeta</label><input type="color" name="color" value="<?= $colorPrimary ?>" class="ag-form-control" style="height:44px;padding:4px;cursor:pointer;"></div>
            </div>
            <div class="agenda-modal-footer">
                <button type="button" onclick="agCerrarModal('modalCrearTarea')" class="btn btn-outline">Cancelar</button>
                <button type="submit" class="btn" style="background:#3b82f6;border-color:#3b82f6;color:#fff;"><i class="fa-solid fa-check"></i> Crear Tarea</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Editar Tarea -->
<div class="agenda-modal-backdrop" id="modalEditarTarea" style="display:none;" onclick="if(event.target===this)agCerrarModal('modalEditarTarea')">
    <div class="agenda-modal" style="animation:agFadeIn .25s;">
        <div class="agenda-modal-header" style="background:linear-gradient(135deg,#3b82f6,#1d4ed8);">
            <h3 class="agenda-modal-title"><i class="fa-solid fa-pencil"></i> Editar Tarea</h3>
            <button class="agenda-modal-close" onclick="agCerrarModal('modalEditarTarea')">&#x2715;</button>
        </div>
        <form method="POST" id="formEditarTarea">
            <?= csrfField() ?><input type="hidden" name="_accion" value="editar_tarea">
            <input type="hidden" name="tarea_id" id="et_id">
            <div class="agenda-modal-body">
                <div class="mb-16"><label class="ag-form-label">Título *</label><input type="text" name="titulo" class="ag-form-control" id="et_titulo" required></div>
                <div class="mb-16"><label class="ag-form-label">Descripción</label><textarea name="descripcion" class="ag-form-control" id="et_desc" rows="3"></textarea></div>
                <div class="mb-16">
                    <label class="ag-form-label">Asignar a</label>
                    <select name="asignado_a" id="et_asig" class="ag-form-control">
                        <option value="">Sin asignar</option>
                        <?php foreach ($adminsDisponibles as $a): ?><option value="<?= $a['id'] ?>"><?= esc($a['nombre']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;" class="mb-16">
                    <div style="flex:0 0 120px;"><label class="ag-form-label">Color</label><input type="color" name="color" id="et_color" class="ag-form-control" style="height:44px;padding:4px;cursor:pointer;"></div>
                    <div style="flex:1;">
                        <label class="ag-form-label">Mover a</label>
                        <div style="display:flex;gap:6px;flex-wrap:wrap;">
                            <button type="button" onclick="agMoverTarea('pendiente')" class="btn btn-sm" style="background:#eff6ff;color:#3b82f6;border:1px solid #bfdbfe;">Pendiente</button>
                            <button type="button" onclick="agMoverTarea('en_proceso')" class="btn btn-sm" style="background:#fffbeb;color:#f59e0b;border:1px solid #fde68a;">Proceso</button>
                            <button type="button" onclick="agMoverTarea('completada')" class="btn btn-sm" style="background:#f0fdf4;color:#10b981;border:1px solid #a7f3d0;">Completada</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="agenda-modal-footer" style="justify-content:space-between;">
                <button type="button" onclick="agConfirmarEliminarTarea()" class="btn" style="background:#fef2f2;color:#ef4444;border:1px solid #fca5a5;"><i class="fa-solid fa-trash-can"></i></button>
                <div style="display:flex;gap:8px;">
                    <button type="button" onclick="agCerrarModal('modalEditarTarea')" class="btn btn-outline">Cancelar</button>
                    <button type="submit" form="formEditarTarea" class="btn" style="background:#3b82f6;border-color:#3b82f6;color:#fff;"><i class="fa-solid fa-check"></i> Actualizar</button>
                </div>
            </div>
        </form>
        <form method="POST" id="formMoverTarea" style="display:none;"><?= csrfField() ?><input type="hidden" name="_accion" value="mover_tarea"><input type="hidden" name="tarea_id" id="mv_id"><input type="hidden" name="nuevo_estatus" id="mv_estatus"></form>
        <form method="POST" id="formEliminarTarea" style="display:none;"><?= csrfField() ?><input type="hidden" name="_accion" value="eliminar_tarea"><input type="hidden" name="tarea_id" id="del_t_id"></form>
    </div>
</div>

<script>
/**
 * GESTIÓN DE MODALES — Agenda Áreas
 * IMPORTANTE: Se usa style.display='flex'/'none' directamente.
 * NO se usan clases .active/.open para evitar conflictos con main.css global.
 */
function agAbrirModal(id) {
    const el = document.getElementById(id);
    if (el) el.style.display = 'flex';
}
function agCerrarModal(id) {
    const el = document.getElementById(id);
    if (el) el.style.display = 'none';
}
// Escape cierra todos los modales de la agenda
document.addEventListener('keydown', function(e) {
    if (e.key !== 'Escape') return;
    document.querySelectorAll('.agenda-modal-backdrop').forEach(function(m) {
        m.style.display = 'none';
    });
});

// ── Eventos del Calendario ────────────────────────────────────────────────
function agAbrirCrearDesdeCelda(f) {
    document.getElementById('c_fi').value = f;
    document.getElementById('c_ff').value = f;
    if (document.getElementById('c_titulo')) document.getElementById('c_titulo').value = '';
    agAbrirModal('modalCrearEvento');
}

/**
 * agPillClick — Handler único para todas las píldoras del calendario.
 * Lee datos desde atributos data-* (esc/htmlspecialchars en PHP, seguro para cualquier caracter).
 * NO usa addslashes() ni inline JS arguments.
 */
function agPillClick(el) {
    var tipo = el.dataset.tipo;
    if (tipo === 'area') {
        agVerDetalleEvento(
            parseInt(el.dataset.id),
            el.dataset.titulo,
            el.dataset.desc,
            el.dataset.fi,
            el.dataset.ff,
            el.dataset.color
        );
    } else if (tipo === 'institucional') {
        agVerEvento(
            el.dataset.titulo,
            el.dataset.desc,
            el.dataset.fiDisplay,
            el.dataset.ffDisplay
        );
    } else if (tipo === 'cumple') {
        agVerCumple(
            el.dataset.titulo,
            el.dataset.foto,
            el.dataset.nombre,
            el.dataset.fechaDisplay
        );
    }
}
function agAbrirEditar(id, titulo, desc, fi, ff, color) {
    document.getElementById('e_id').value    = id;
    document.getElementById('e_titulo').value = agDecode(titulo);
    document.getElementById('e_desc').value  = agDecode(desc);
    document.getElementById('e_fi').value    = fi;
    document.getElementById('e_ff').value    = ff;
    document.getElementById('e_color').value = color || '<?= $colorPrimary ?>';
    agAbrirModal('modalEditarEvento');
}

// Ver+Editar evento propio (clic en píldora del área)
// data-id guardado en variable para el eliminar
var _agEvId = null;
function agVerDetalleEvento(id, titulo, desc, fi, ff, color) {
    _agEvId = id;
    // Poblar vista lectura
    document.getElementById('vd_titulo').textContent = agDecode(titulo);
    document.getElementById('vd_fi').textContent     = fi.replace(/-/g,'/');
    document.getElementById('vd_ff').textContent     = ff.replace(/-/g,'/');
    document.getElementById('vd_desc').textContent   = agDecode(desc) || 'Sin descripción.';
    // Poblar formulario editar
    document.getElementById('vd_ev_id').value    = id;
    document.getElementById('vd_e_titulo').value  = agDecode(titulo);
    document.getElementById('vd_e_desc').value   = agDecode(desc);
    document.getElementById('vd_e_fi').value     = fi;
    document.getElementById('vd_e_ff').value     = ff;
    document.getElementById('vd_e_color').value  = color || '<?= $colorPrimary ?>';
    agVdModo('ver');
    agAbrirModal('modalVerDetalle');
}
// Cambiar entre modo Ver y Editar dentro del mismo modal
function agVdModo(modo) {
    var vista    = document.getElementById('vd_vista');
    var editar   = document.getElementById('vd_editar');
    var footer   = document.getElementById('vd_footer_ver');
    var header   = document.getElementById('vd_header_titulo');
    if (modo === 'editar') {
        vista.style.display  = 'none';
        editar.style.display = 'block';
        footer.style.display = 'none';
        header.innerHTML     = '<i class="fa-solid fa-pen-to-square"></i> Editar Evento';
    } else {
        vista.style.display  = 'block';
        editar.style.display = 'none';
        footer.style.display = 'flex';
        header.innerHTML     = '<i class="fa-solid fa-calendar-check"></i> Detalle del Evento';
    }
}
// Abrir modal de confirmación de eliminación (reemplaza confirm())
function agConfirmarEliminarEvento(id) {
    if (id !== undefined) _agEvId = id; // Puede llamarse desde el modal de editar
    agAbrirModal('modalConfirmarEliminar');
}
// Ejecutar la eliminación (llamado desde el botón del modal de confirmación)
function agEjecutarEliminarEvento() {
    document.getElementById('del_ev_id').value = _agEvId;
    document.getElementById('formEliminarEvento').submit();
}
// Ver evento (solo lectura - institucional)
function agVerEvento(titulo, desc, fechaIni, fechaFin) {
    document.getElementById('ver_titulo').textContent = agDecode(titulo);
    document.getElementById('ver_fecha').textContent  = fechaIni + (fechaFin !== fechaIni ? ' \u2014 ' + fechaFin : '');
    document.getElementById('ver_desc').textContent   = agDecode(desc) || 'Sin descripción.';
    agAbrirModal('modalVerEvento');
}
// Ver cumpleaños — modal dorado idéntico al calendario público
function agVerCumple(titulo, fotoUrl, nombre, fecha) {
    document.getElementById('mc_nombre').textContent = agDecode(nombre || titulo);
    document.getElementById('mc_fecha').textContent  = fecha;
    var img = document.getElementById('mc_foto');
    var ph  = document.getElementById('mc_placeholder');
    var url = agDecode(fotoUrl);
    if (url && url !== '') {
        img.src = url; img.style.display = 'block'; ph.style.display = 'none';
    } else {
        img.style.display = 'none'; ph.style.display = 'flex';
    }
    agAbrirModal('modalVerCumple');
}

// ── Kanban ────────────────────────────────────────────────────────────────
function agAbrirEditarTarea(id, titulo, desc, color, asigId) {
    document.getElementById('et_id').value    = id;
    document.getElementById('mv_id').value    = id;
    document.getElementById('del_t_id').value = id;
    document.getElementById('et_titulo').value = agDecode(titulo);
    document.getElementById('et_desc').value  = agDecode(desc);
    document.getElementById('et_color').value = color || '<?= $colorPrimary ?>';
    var sel = document.getElementById('et_asig');
    Array.from(sel.options).forEach(function(o){ o.selected = (o.value == asigId); });
    agAbrirModal('modalEditarTarea');
}
function agMoverTarea(estatus) {
    document.getElementById('mv_estatus').value = estatus;
    document.getElementById('formMoverTarea').submit();
}
function agConfirmarEliminarTarea() {
    if (!confirm('¿Eliminar esta tarea permanentemente?')) return;
    document.getElementById('formEliminarTarea').submit();
}
// Drag & Drop Kanban
function agDrop(event, estatus) {
    event.preventDefault();
    var id = event.dataTransfer.getData('tareaId');
    var f  = document.createElement('form');
    f.method = 'POST'; f.style.display = 'none';
    f.innerHTML = '<?= csrfField() ?><input name="_accion" value="mover_tarea"><input name="tarea_id" value="' + id + '"><input name="nuevo_estatus" value="' + estatus + '">';
    document.body.appendChild(f); f.submit();
}

// ── Utilidades ────────────────────────────────────────────────────────────
function agDecode(str) {
    var t = document.createElement('textarea');
    t.innerHTML = str;
    return t.value;
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
