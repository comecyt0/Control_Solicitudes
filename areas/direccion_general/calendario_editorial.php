<?php
/**
 * COMECyT — Gestión de Dirección General (Kanban + Agenda)
 * Filtra tareas y eventos internos del área 4.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../config/auth.php';

verificarSesionAdmin();

$pdo = getConnection();
$cveArea = 4; // Dirección General
$adminId = $_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 0;

// Acciones de Procesamiento (Tareas/Eventos)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_accion'])) {
    validarCsrfPost();
    $accion = $_POST['_accion'];

    // --- TAREAS KANBAN ---
    if ($accion === 'crear_tarea') {
        $titulo = trim(postParam('titulo'));
        $desc   = trim(postParam('descripcion'));
        $color  = postParam('color', '#662331');
        $asig   = postParam('asignado_a');
        if ($titulo) {
            $stmt = $pdo->prepare("INSERT INTO df_tareas (titulo, descripcion, color, estatus, creado_por, asignado_a, cve_area) VALUES (?, ?, ?, 'pendiente', ?, ?, ?)");
            $stmt->execute([$titulo, $desc, $color, $adminId, $asig ?: null, $cveArea]);
            header('Location: calendario_editorial.php?success=tarea_creada'); exit;
        }
    } elseif ($accion === 'mover_tarea') {
        $tid = (int) postParam('tarea_id');
        $est = postParam('nuevo_estatus');
        if ($tid > 0) {
            $stmt = $pdo->prepare("UPDATE df_tareas SET estatus = ? WHERE id = ? AND cve_area = ?");
            $stmt->execute([$est, $tid, $cveArea]);
            header('Location: calendario_editorial.php'); exit;
        }
    } elseif ($accion === 'editar_tarea') {
        $tid = (int) postParam('tarea_id');
        $titulo = trim(postParam('titulo'));
        $desc = trim(postParam('descripcion'));
        $asig = postParam('asignado_a');
        $color = postParam('color');
        if ($tid > 0 && $titulo) {
            $stmt = $pdo->prepare("UPDATE df_tareas SET titulo = ?, descripcion = ?, asignado_a = ?, color = ? WHERE id = ? AND cve_area = ?");
            $stmt->execute([$titulo, $desc, $asig ?: null, $color, $tid, $cveArea]);
            header('Location: calendario_editorial.php?success=tarea_editada'); exit;
        }
    } elseif ($accion === 'eliminar_tarea') {
        $tid = (int) postParam('tarea_id');
        if ($tid > 0) {
            $stmt = $pdo->prepare("DELETE FROM df_tareas WHERE id = ? AND cve_area = ?");
            $stmt->execute([$tid, $cveArea]);
            header('Location: calendario_editorial.php?success=tarea_eliminada'); exit;
        }
    }

    // --- EVENTOS INTERNOS ---
    if ($accion === 'crear_evento') {
        $titulo = trim(postParam('titulo'));
        $inicio = postParam('fecha_inicio');
        $fin    = postParam('fecha_fin');
        if ($titulo && $inicio) {
            $stmt = $pdo->prepare("INSERT INTO df_eventos_editoriales (titulo, descripcion, fecha_inicio, fecha_fin, color, creado_por, publico, cve_area) VALUES (?, ?, ?, ?, ?, ?, FALSE, ?)");
            $stmt->execute([$titulo, postParam('descripcion'), $inicio, $fin ?: $inicio, postParam('color', '#662331'), $adminId, $cveArea]);
            header('Location: calendario_editorial.php?success=evento_creado'); exit;
        }
    }
}

// Cargar Datos
$admins = $pdo->prepare("SELECT p.cve_personal as id, p.nombre FROM cat_personal p WHERE p.cve_area = ? AND p.activo = true ORDER BY p.nombre");
$admins->execute([$cveArea]);
$adminsDisponibles = $admins->fetchAll();

// Tareas Kanban por columna
$columnas = ['pendiente', 'en_proceso', 'completada'];
$tareas = [];
foreach ($columnas as $col) {
    $stmt = $pdo->prepare("SELECT t.*, p.nombre as asignado_nombre FROM df_tareas t LEFT JOIN cat_personal p ON t.asignado_a = p.cve_personal WHERE t.cve_area = ? AND t.estatus = ? ORDER BY t.id DESC");
    $stmt->execute([$cveArea, $col]);
    $tareas[$col] = $stmt->fetchAll();
}

$pageTitle  = 'Gestión de Dirección General';
$activeMenu = 'calendario';

require_once __DIR__ . '/../../includes/header_admin.php';
?>

<style>
.kanban-board { display: flex; gap: 20px; overflow-x: auto; padding-bottom: 20px; }
.kanban-col { flex: 1; min-width: 300px; background: #f1f5f9; border-radius: 12px; padding: 15px; display: flex; flex-direction: column; gap: 12px; }
.kanban-col-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px; }
.kanban-col-title { font-weight: 700; color: #475569; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em; }
.kanban-card { background: white; border-radius: 10px; padding: 16px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); cursor: grab; border-left: 4px solid #662331; }
.kanban-card:active { cursor: grabbing; opacity: 0.8; }
.card-task-title { font-weight: 600; color: #1e293b; font-size: 0.9rem; margin-bottom: 8px; }
.card-task-meta { font-size: 0.75rem; color: #64748b; display: flex; align-items: center; gap: 8px; }
</style>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
    <h2 style="color: var(--dg-primary); font-weight: 800;"><i class="fa-solid fa-list-check"></i> Tablero de Compromisos</h2>
    <button class="btn btn-primary" onclick="abrirModal('modalCrearTarea')"><i class="fa-solid fa-plus"></i> Nueva Tarea</button>
</div>

<div class="kanban-board">
    <?php foreach ($columnas as $col): ?>
    <div class="kanban-col" ondragover="allowDrop(event)" ondrop="drop(event, '<?= $col ?>')">
        <div class="kanban-col-header">
            <span class="kanban-col-title"><?= str_replace('_', ' ', strtoupper($col)) ?></span>
            <span style="background: #cbd5e1; color: #475569; font-size: 0.7rem; padding: 2px 8px; border-radius: 10px; font-weight: 700;"><?= count($tareas[$col]) ?></span>
        </div>
        
        <?php foreach ($tareas[$col] as $t): ?>
        <div class="kanban-card" draggable="true" ondragstart="drag(event, <?= $t['id'] ?>)" 
             onclick="abrirModalEditarTarea(<?= $t['id'] ?>, '<?= esc($t['titulo']) ?>', '<?= esc($t['descripcion']) ?>', '<?= $t['color'] ?>', '<?= $t['asignado_a'] ?>')"
             style="border-left-color: <?= $t['color'] ?>;">
            <div class="card-task-title"><?= esc($t['titulo']) ?></div>
            <div class="card-task-meta">
                <i class="fa-solid fa-user-tag"></i> 
                <span><?= $t['asignado_nombre'] ? esc($t['asignado_nombre']) : 'Sin asignar' ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
</div>

<hr style="margin: 40px 0; border: 0; border-top: 1px solid #e2e8f0;">

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
    <h2 style="color: var(--dg-primary); font-weight: 800;"><i class="fa-solid fa-calendar-day"></i> Agenda Direccional</h2>
    <div style="display: flex; gap: 10px;">
        <button class="btn btn-outline" onclick="abrirModal('modalCrearEvento')"><i class="fa-solid fa-plus"></i> Agregar Evento</button>
    </div>
</div>

<div class="card" style="padding: 0; overflow: hidden;">
    <iframe src="../../public/calendario.php?area_embed=4" style="width: 100%; height: 700px; border: none;"></iframe>
</div>

<!-- Modales -->
<div class="modal-backdrop" id="modalCrearTarea">
    <div class="modal">
        <div class="modal-header"><h3 class="modal-title">Nueva Tarea</h3><button type="button" class="modal-close" onclick="cerrarModal('modalCrearTarea')">&times;</button></div>
        <form method="POST">
            <?= csrfField() ?><input type="hidden" name="_accion" value="crear_tarea">
            <div class="modal-body">
                <div class="form-group mb-16"><label class="form-label">Título</label><input type="text" name="titulo" class="form-control" required></div>
                <div class="form-group mb-16"><label class="form-label">Descripción</label><textarea name="descripcion" class="form-control" rows="3"></textarea></div>
                <div class="form-group mb-16">
                    <label class="form-label">Asignado a</label>
                    <select name="asignado_a" class="form-control">
                        <option value="">-- Sin Asignar --</option>
                        <?php foreach ($adminsDisponibles as $adm): ?>
                            <option value="<?= $adm['id'] ?>"><?= esc($adm['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label class="form-label">Color</label><input type="color" name="color" value="#662331" class="form-control"></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary">Crear Tarea</button></div>
        </form>
    </div>
</div>

<div class="modal-backdrop" id="modalEditarTarea">
    <div class="modal">
        <div class="modal-header"><h3 class="modal-title">Editar Tarea</h3><button type="button" class="modal-close" onclick="cerrarModal('modalEditarTarea')">&times;</button></div>
        <form method="POST">
            <?= csrfField() ?><input type="hidden" name="_accion" value="editar_tarea"><input type="hidden" name="tarea_id" id="et_id">
            <div class="modal-body">
                <div class="form-group mb-16"><label class="form-label">Título</label><input type="text" name="titulo" id="et_titulo" class="form-control" required></div>
                <div class="form-group mb-16"><label class="form-label">Descripción</label><textarea name="descripcion" id="et_descripcion" class="form-control" rows="3"></textarea></div>
                <div class="form-group mb-16">
                    <label class="form-label">Asignado a</label>
                    <select name="asignado_a" id="et_asignado_a" class="form-control">
                        <option value="">-- Sin Asignar --</option>
                        <?php foreach ($adminsDisponibles as $adm): ?>
                            <option value="<?= $adm['id'] ?>"><?= esc($adm['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label class="form-label">Color</label><input type="color" name="color" id="et_color" class="form-control"></div>
            </div>
            <div class="modal-footer d-flex justify-content-between">
                <button type="button" class="btn btn-danger" onclick="eliminarTarea()">Eliminar</button>
                <button type="submit" class="btn btn-primary">Actualizar</button>
            </div>
        </form>
    </div>
</div>

<form id="formHidden" method="POST" style="display:none;">
    <?= csrfField() ?><input type="hidden" name="_accion" id="h_accion"><input type="hidden" name="tarea_id" id="h_id"><input type="hidden" name="nuevo_estatus" id="h_est">
</form>

<script>
function abrirModal(id) { document.getElementById(id).classList.add('open'); }
function cerrarModal(id) { document.getElementById(id).classList.remove('open'); }
function allowDrop(ev) { ev.preventDefault(); }
function drag(ev, id) { ev.dataTransfer.setData("text", id); }
function drop(ev, nuevo) {
    ev.preventDefault();
    const id = ev.dataTransfer.getData("text");
    document.getElementById('h_accion').value = 'mover_tarea';
    document.getElementById('h_id').value = id;
    document.getElementById('h_est').value = nuevo;
    document.getElementById('formHidden').submit();
}
function abrirModalEditarTarea(id, t, d, c, a) {
    document.getElementById('et_id').value = id;
    document.getElementById('et_titulo').value = t;
    document.getElementById('et_descripcion').value = d;
    document.getElementById('et_color').value = c;
    document.getElementById('et_asignado_a').value = a || '';
    abrirModal('modalEditarTarea');
}
function eliminarTarea() {
    if (confirm('¿Seguro que deseas eliminar esta tarea?')) {
        document.getElementById('h_accion').value = 'eliminar_tarea';
        document.getElementById('h_id').value = document.getElementById('et_id').value;
        document.getElementById('formHidden').submit();
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
