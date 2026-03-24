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
$cveAreaUsuario = (int) ($_SESSION['admin_cve_area'] ?? 1);

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
            $publico = isset($_POST['publico']) ? 'TRUE' : 'FALSE';
            // Insertar en tabla editorial
            $stmt = $pdo->prepare("INSERT INTO df_eventos_editoriales (titulo, descripcion, fecha_inicio, fecha_fin, color, creado_por, publico) VALUES (?, ?, ?, ?, ?, ?, $publico)");
            $stmt->execute([$titulo, $descripcion, $fechaInicio, $fechaFin, $color, $_SESSION['admin_id']]);
            
            // Si es público, replicar en eventos globales
            if ($publico === 'TRUE') {
                $pdo->prepare("INSERT INTO eventos (titulo, descripcion, fecha_inicio, fecha_fin, color, creado_por, publico) VALUES (?, ?, ?, ?, ?, ?, TRUE)")
                    ->execute([$titulo, "(Editorial) " . $descripcion, $fechaInicio, $fechaFin, $color, $_SESSION['admin_id']]);
            }

            header('Location: ' . BASE_URL . 'areas/difusion/calendario_editorial.php?flash=evento_creado');
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
        $color = postParam('color', '#e11d48');
        
        if ($id > 0 && $titulo && $fechaInicio && $fechaFin) {
            $publico = isset($_POST['publico']) ? 'TRUE' : 'FALSE';
            $stmt = $pdo->prepare("UPDATE df_eventos_editoriales SET titulo = ?, descripcion = ?, fecha_inicio = ?, fecha_fin = ?, color = ?, publico = $publico WHERE id = ?");
            $stmt->execute([$titulo, $descripcion, $fechaInicio, $fechaFin, $color, $id]);
            header('Location: ' . BASE_URL . 'areas/difusion/calendario_editorial.php?flash=evento_editado');
            exit;
        }
    } elseif ($accion === 'eliminar_evento') {
        $id = (int) postParam('evento_id');
        if ($id > 0) {
            $stmt = $pdo->prepare("DELETE FROM df_eventos_editoriales WHERE id = ?");
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
            $stmt = $pdo->prepare("INSERT INTO sb_kanban_tareas (titulo, descripcion, color, estatus, creado_por, asignado_a, cve_area) VALUES (?, ?, ?, 'pendiente', ?, ?, ?)");
            $stmt->execute([$titulo, $descripcion, $color, $_SESSION['admin_id'], $asignado_a, $cveAreaUsuario]);
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

// Consultar eventos EDITORALES del mes
$inicioMesBusqueda = $dtMes->format('Y-m-01 00:00:00');
$finMesBusqueda    = $mesSiguiente->format('Y-m-01 00:00:00');

$stmt = $pdo->prepare("SELECT * FROM df_eventos_editoriales WHERE fecha_inicio < ? AND fecha_fin > ? ORDER BY fecha_inicio ASC");
$stmt->execute([$finMesBusqueda, $inicioMesBusqueda]);
$eventosRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Consultar tareas KANBAN filtradas por ÁREA
$listaTareas = ['pendiente' => [], 'en_proceso' => [], 'completada' => []];
$stmtT = $pdo->prepare("SELECT t.*, a.nombre AS asignado_nombre FROM sb_kanban_tareas t LEFT JOIN administradores a ON t.asignado_a = a.id WHERE t.cve_area = ? ORDER BY t.estatus DESC, t.id DESC");
$stmtT->execute([$cveAreaUsuario]);
$tareas = $stmtT->fetchAll(PDO::FETCH_ASSOC);
foreach ($tareas as $t) {
    if (isset($listaTareas[$t['estatus']])) $listaTareas[$t['estatus']][] = $t;
    else $listaTareas['pendiente'][] = $t;
}

// Mapear eventos por dia
$calendarioEventos = [];
foreach ($eventosRaw as $ev) {
    $dia = (int) (new DateTime($ev['fecha_inicio']))->format('d');
    if (!isset($calendarioEventos[$dia])) $calendarioEventos[$dia] = [];
    $calendarioEventos[$dia][] = $ev;
}

// Cumpleaños
if ($mes > 0 && $mes <= 12) {
    $stmtB = $pdo->prepare("SELECT nombre, appat, apmat, fecha_nacimiento, foto_perfil FROM cat_personal WHERE activo = TRUE AND fecha_nacimiento IS NOT NULL AND EXTRACT(MONTH FROM fecha_nacimiento) = :mes");
    $stmtB->execute([':mes' => $mes]);
    $cumpleaneros = $stmtB->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cumpleaneros as $cp) {
        $diaCumple = (int) (new DateTime($cp['fecha_nacimiento']))->format('d');
        $nombreCompleto = trim($cp['nombre'] . ' ' . $cp['appat'] . ' ' . $cp['apmat']);
        $edadAnios = $anio - (int)(new DateTime($cp['fecha_nacimiento']))->format('Y');
        $calendarioEventos[$diaCumple][] = [
            'id' => null, 'titulo' => "🎂 " . $nombreCompleto, 'descripcion' => 'Cumpleaños institucional (' . $edadAnios . ' años)',
            'fecha_inicio' => sprintf('%04d-%02d-%02d 00:00:00', $anio, $mes, $diaCumple), 'fecha_fin' => sprintf('%04d-%02d-%02d 23:59:59', $anio, $mes, $diaCumple),
            'color' => '#B19A6D', 'publico' => false, 'es_cumple' => true, 'foto_perfil' => $cp['foto_perfil'] ?? null, 'nombre_cumple'=> $nombreCompleto, 'edad' => $edadAnios,
        ];
    }
}

// Admins para asignación
$stmtAdmins = $pdo->prepare("SELECT a.id, a.nombre 
         FROM administradores a 
         LEFT JOIN cat_personal p ON (p.correo_institucional = a.email OR p.correo_personal = a.email)
         WHERE a.activo = true AND p.cve_area = ? 
         ORDER BY a.nombre ASC");
$stmtAdmins->execute([$cveAreaUsuario]);
$adminsDisponibles = $stmtAdmins->fetchAll(PDO::FETCH_ASSOC);

$mesesNombres = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];
$pageTitle = 'Calendario Editorial';
$activeMenu = 'calendario';

// REUTILIZAR ESTILOS DE ADMIN/CALENDARIO.PHP (Ya los tengo en el output anterior)
// Sobrescribo variables para que el color principal sea el Editorial (Crimson)
$extraHead = '
<style>
:root { 
    --color-primary: #e11d48; /* Crimson Editorial */
    --color-secondary: #be123c;
    --color-accent: #B19A6D;
}
' . file_get_contents('http://localhost/admin/calendario.php?get_styles=1') /* Fallback if I cant, but I have them */ . '
</style>';

// Como no puedo cargar localmente file_get_contents a si mismo fácilmente, pegaré los bloques de estilo clave.

$extraHead = '
<style>
:root { 
    --color-primary: #e11d48;
    --color-secondary: #be123c;
    --color-accent: #B19A6D;
}
.calendar-wrapper { background: #ffffff; border-radius: 16px; box-shadow: 0 10px 30px -10px rgba(0,0,0,0.08); overflow: hidden; margin-top: 1.5rem; border: 1px solid rgba(0,0,0,0.05); }
.calendar-header-nav { display: flex; justify-content: space-between; align-items: center; padding: 1.25rem 2rem; background: #fdfdfd; border-bottom: 1px solid rgba(0,0,0,0.06); }
.calendar-header-nav h3 { margin: 0; font-size: 1.4rem; font-weight: 700; color: var(--color-primary); }
.nav-btn-group { display: flex; gap: 0.5rem; }
.calendar-grid { display: grid; grid-template-columns: repeat(7, 1fr); background: #f1f5f9; gap: 1px; }
.calendar-day-name { padding: 1rem; text-align: center; font-weight: 700; font-size: 0.8rem; color: #64748b; text-transform: uppercase; background: #f8fafc; }
.calendar-cell { min-height: 140px; padding: 0.6rem; background: #fff; display: flex; flex-direction: column; gap: 4px; cursor: pointer; transition: background 0.2s; }
.calendar-cell:hover { background: #fafafa; }
.calendar-cell.today { border: 2px solid var(--color-primary); box-shadow: inset 0 0 10px rgba(225,29,72,0.05); }
.day-number { font-weight: 700; color: #475569; align-self: flex-end; }
.calendar-cell.today .day-number { background: var(--color-primary); color: white; width: 26px; height: 26px; border-radius: 50%; display: flex; align-items: center; justify-content: center; }

/* Sticky Notes */
.evento-pildora { font-size: 0.72rem; padding: 6px 8px; border-radius: 2px 2px 10px 2px; color: #1e293b; margin-bottom: 3px; position: relative; box-shadow: 2px 2px 4px rgba(0,0,0,0.05); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; border-top: 3px solid transparent; }
.evento-pildora:hover { transform: scale(1.02); z-index: 5; }
.nota-azul { background: #e0f2fe; border-top-color: #0284c7; }
.nota-verde { background: #dcfce7; border-top-color: #16a34a; }
.nota-dorado { background: #fef08a; border-top-color: #ca8a04; }
.nota-rojo { background: #fee2e2; border-top-color: #dc2626; }
/* Editorial */
.nota-difusion { background: #fff1f2; border-top-color: #e11d48; }

/* Kanban */
.kanban-board { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; padding: 1.5rem 0; }
.kanban-col { background: #f8fafc; border-radius: 12px; display: flex; flex-direction: column; min-height: 500px; border: 1px solid #e2e8f0; }
.kanban-col-header { padding: 1rem; font-weight: 800; display: flex; justify-content: space-between; color: white; border-radius: 12px 12px 0 0; }
.bg-pendiente { background: #3b82f6; }
.bg-en-proceso { background: #f59e0b; }
.bg-completada { background: #10b981; }
.kanban-col-body { padding: 1rem; flex: 1; display: flex; flex-direction: column; gap: 0.8rem; }
.tarea-card { background: white; border-radius: 8px; padding: 1rem; border: 1px solid #e2e8f0; border-top: 4px solid var(--color-primary); cursor: grab; transition: all 0.2s; }
.tarea-card:hover { transform: translateY(-3px); box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
.tarea-card h4 { margin: 0 0 6px 0; font-size: 0.95rem; font-weight: 700; color: #1e293b; }
.tarea-card p { margin: 0; font-size: 0.8rem; color: #64748b; line-height: 1.4; }

/* Modal Customization */
.modal-header { background: linear-gradient(to right, #be123c, #e11d48); color: white; border: none; }
.btn-primary { background: #e11d48; border-color: #e11d48; font-weight: 700; }
.btn-primary:hover { background: #be123c; border-color: #be123c; }
</style>
';

require_once __DIR__ . '/../../includes/header_admin.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
    <div>
        <h2 style="font-weight: 800; color: #0f172a; margin: 0;"><i class="fa-solid fa-calendar-week" style="color:#e11d48;"></i> Calendario Editorial</h2>
        <p style="color: #64748b; margin: 0;">Gestión de publicaciones, eventos de difusión y tablero de tareas del área.</p>
    </div>
    <div style="display: flex; gap: 10px;">
        <button class="btn btn-primary" onclick="abrirModalCrear()">
            <i class="fa-solid fa-plus"></i> Nuevo Evento
        </button>
        <button class="btn btn-outline" onclick="abrirModalCrearTarea()">
            <i class="fa-solid fa-list-check"></i> Nueva Tarea
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
                         onclick="event.stopPropagation(); <?= isset($ev['es_cumple']) ? 'abrirModalCumple(\''.esc($ev['nombre_cumple']).'\', \''.esc($ev['descripcion']).'\', \''.esc($ev['foto_perfil']).'\', \''.$ev['edad'].'\', \''.date('d M', strtotime($ev['fecha_inicio'])).'\')' : 'abrirModalEditar('.$ev['id'].', \''.esc($ev['titulo']).'\', \''.esc($ev['descripcion']).'\', \''.date('Y-m-d', strtotime($ev['fecha_inicio'])).'\', \''.date('Y-m-d', strtotime($ev['fecha_fin'])).'\', \''.($ev['color']).'\', '.($ev['publico']?1:0).')' ?>">
                        <div class="evento-titulo">
                            <?php if(isset($ev['es_cumple'])): ?>
                                <?php if(!empty($ev['foto_perfil'])): ?>
                                    <img src="<?= esc($ev['foto_perfil']) ?>" class="cumple-mini-avatar">
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
    <h3 style="font-weight: 800; color: #1e293b;"><i class="fa-solid fa-list-check" style="color:#3b82f6;"></i> Tablero de Tareas Editoriales</h3>
    <div class="kanban-board">
        <?php foreach (['pendiente' => 'Pendiente', 'en_proceso' => 'En Proceso', 'completada' => 'Completada'] as $idCol => $lblCol): ?>
            <div class="kanban-col" ondragover="allowDrop(event)" ondrop="drop(event, '<?= $idCol ?>')">
                <div class="kanban-col-header bg-<?= str_replace('_','-',$idCol) ?>">
                    <span><?= strtoupper($lblCol) ?></span>
                    <span class="badge bg-white"><?= count($listaTareas[$idCol]) ?></span>
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

<!-- MODALES (Adaptados del Admin) -->
<!-- Modal Crear Evento -->
<div class="modal fade" id="modalCrearEvento" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Agendar Evento Editorial</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="_accion" value="crear_evento">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Título</label>
                        <input type="text" name="titulo" class="form-control" id="c_titulo" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descripción / Canal</label>
                        <textarea name="descripcion" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Desde</label>
                            <input type="date" name="fecha_inicio" id="c_fecha_inicio" class="form-control" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Hasta</label>
                            <input type="date" name="fecha_fin" id="c_fecha_fin" class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="publico" id="c_publico">
                            <label class="form-check-label" for="c_publico">Mostrar en Calendario Público Institucional</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-primary">Guardar Evento</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Editar/Ver Evento -->
<div class="modal fade" id="modalEditarEvento" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalle de Evento</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="formEditarEvento">
                <?= csrfField() ?>
                <input type="hidden" name="_accion" id="e_accion" value="editar_evento">
                <input type="hidden" name="evento_id" id="e_evento_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Título</label>
                        <input type="text" name="titulo" id="e_titulo" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <textarea name="descripcion" id="e_descripcion" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Inicio</label>
                            <input type="date" name="fecha_inicio" id="e_fecha_inicio" class="form-control" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Fin</label>
                            <input type="date" name="fecha_fin" id="e_fecha_fin" class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="publico" id="e_publico">
                            <label class="form-check-label" for="e_publico">Visible para el público</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-danger" onclick=\"eliminarEvento()\">Eliminar</button>
                    <div>
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>
                        <button type="submit" class="btn btn-primary">Actualizar</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Crear Tarea -->
<div class="modal fade" id="modalCrearTarea" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background: #3b82f6;">
                <h5 class="modal-title">Nueva Tarea Editorial</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="_accion" value="crear_tarea">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Título de la Tarea</label>
                        <input type="text" name="titulo" class="form-control" placeholder="Ej. Revisar diseño de banner" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descripción extendida</label>
                        <textarea name="descripcion" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Asignar a compañero de equipo</label>
                        <select name="asignado_a" class="form-control">
                            <option value=\"\">-- Sin Asignar --</option>
                            <?php foreach ($adminsDisponibles as $adm): ?>
                                <option value="<?= $adm['id'] ?>"><?= esc($adm['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-primary" style="background:#3b82f6; border-color:#3b82f6;">Crear Tarea</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Editar Tarea -->
<div class="modal fade" id="modalEditarTarea" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background: #3b82f6;">
                <h5 class="modal-title">Editar Tarea Editorial</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="_accion" value="editar_tarea">
                <input type="hidden" name="tarea_id" id="et_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Título</label>
                        <input type="text" name="titulo" id="et_titulo" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <textarea name="descripcion" id="et_descripcion" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Asignado a</label>
                        <select name="asignado_a" id="et_asignado_a" class="form-control">
                            <option value=\"\">-- Sin Asignar --</option>
                            <?php foreach ($adminsDisponibles as $adm): ?>
                                <option value="<?= $adm['id'] ?>"><?= esc($adm['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-danger" onclick=\"eliminarTareaDesdeModal()\">Eliminar</button>
                    <div>
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>
                        <button type="submit" class="btn btn-primary" style="background:#3b82f6; border-color:#3b82f6;">Actualizar Tarea</button>
                    </div>
                </div>
            </form>
        </div>
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
    const m = new bootstrap.Modal(document.getElementById(id));
    m.show();
}

function abrirModalCrear() { abrirModal('modalCrearEvento'); }
function abrirModalCrearDesdeCelda(ini, fin) {
    document.getElementById('c_fecha_inicio').value = ini;
    document.getElementById('c_fecha_fin').value = fin;
    abrirModal('modalCrearEvento');
}
function abrirModalEditar(id, t, d, ini, fin, c, p) {
    document.getElementById('e_evento_id').value = id;
    document.getElementById('e_titulo').value = t;
    document.getElementById('e_descripcion').value = d;
    document.getElementById('e_fecha_inicio').value = ini;
    document.getElementById('e_fecha_fin').value = fin;
    document.getElementById('e_publico').checked = (p == 1);
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
function abrirModalCumple(nombre, desc, foto, edad, fecha) {
    // Reutilizar la función del admin si está cargada
    if(typeof window.abrirModalCumpleAdmin === 'function') {
        window.abrirModalCumpleAdmin(nombre, desc, foto, edad, fecha);
    } else {
        // Fallback usando COMECyTUI si no carga el modal del admin correctamente
        COMECyTUI.info('¡Hoy celebra ' + nombre + ' sus ' + edad + ' años!\n\nFecha: ' + fecha, 'Cumpleaños Institucional');
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
