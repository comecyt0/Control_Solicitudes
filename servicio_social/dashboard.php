<?php
/**
 * COMECyT — Portal Servicio Social
 * Dashboard con Kanban, Asistencia y Compañeros
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/auth.php';

verificarSesionSS();

$pdo  = getConnection();
$ssId = (int) $_SESSION['ss_id'];
$vista = $_GET['vista'] ?? 'kanban';

// ── Datos para kanban ────────────────────────────────────────────
$stmtTareas = $pdo->prepare(
    "SELECT t.id, t.titulo, t.descripcion, t.columna, t.prioridad, t.color,
            t.fecha_limite, t.created_at, t.updated_at,
            u.nombre || ' ' || u.appat AS asignado_nombre,
            a.nombre AS creado_por_nombre,
            (SELECT COUNT(*) FROM ss_evidencias e WHERE e.tarea_id = t.id) AS evidencias_cnt
     FROM ss_kanban_tareas t
     LEFT JOIN ss_usuarios     u ON t.asignado_a = u.id
     LEFT JOIN administradores a ON t.creado_por = a.id
     WHERE t.asignado_a = :uid OR t.asignado_a IS NULL
     ORDER BY t.created_at DESC"
);
$stmtTareas->execute([':uid' => $ssId]);
$tareas = $stmtTareas->fetchAll();

$columnas = ['pendiente' => [], 'en_proceso' => [], 'completada' => []];
foreach ($tareas as $t) {
    $columnas[$t['columna']][] = $t;
}

// ── Datos asistencia ─────────────────────────────────────────────
$stmtAsist = $pdo->prepare(
    "SELECT id, tipo, fecha_hora, ip
     FROM ss_asistencia
     WHERE usuario_id = :uid
     ORDER BY fecha_hora DESC
     LIMIT 60"
);
$stmtAsist->execute([':uid' => $ssId]);
$asistencia = $stmtAsist->fetchAll();

// Determinar si ya registró entrada hoy sin salida
$hoy    = date('Y-m-d');
$stmtUlt = $pdo->prepare(
    "SELECT tipo FROM ss_asistencia
     WHERE usuario_id = :uid AND DATE(fecha_hora) = :hoy
     ORDER BY fecha_hora DESC LIMIT 1"
);
$stmtUlt->execute([':uid' => $ssId, ':hoy' => $hoy]);
$ultimoTipo = $stmtUlt->fetchColumn() ?: null;
$puedeEntrada = ($ultimoTipo === null || $ultimoTipo === 'salida');
$puedeSalida  = ($ultimoTipo === 'entrada');

// ── Compañeros SS ─────────────────────────────────────────────────
$stmtComp = $pdo->prepare(
    "SELECT u.id, u.nombre || ' ' || u.appat AS nombre_completo,
            u.email, u.institucion, u.carrera,
            (SELECT COUNT(*) FROM ss_kanban_tareas t WHERE t.asignado_a = u.id AND t.columna = 'completada') AS completadas,
            (SELECT COUNT(*) FROM ss_kanban_tareas t WHERE t.asignado_a = u.id AND t.columna != 'completada') AS en_curso,
            (SELECT tipo FROM ss_asistencia a WHERE a.usuario_id = u.id AND DATE(a.fecha_hora) = :hoy ORDER BY a.fecha_hora DESC LIMIT 1) AS estado_hoy
     FROM ss_usuarios u
     WHERE u.activo = TRUE AND u.id != :uid
     ORDER BY u.nombre"
);
$stmtComp->execute([':uid' => $ssId, ':hoy' => $hoy]);
$companeros = $stmtComp->fetchAll();

// ── Estadísticas asistencia para gráfica ────────────────────────
$stmtGraf = $pdo->prepare(
    "SELECT TO_CHAR(fecha_hora, 'YYYY-MM-DD') AS dia,
            tipo, COUNT(*) AS total
     FROM ss_asistencia
     WHERE usuario_id = :uid AND fecha_hora >= NOW() - INTERVAL '30 days'
     GROUP BY dia, tipo ORDER BY dia"
);
$stmtGraf->execute([':uid' => $ssId]);
$grafData = $stmtGraf->fetchAll();

// Resumen actividades
$tasksComp  = count($columnas['completada']);
$tasksTotal = count($tareas);

$pageTitle  = 'Portal Servicio Social';
$activeMenu = $vista;

require_once __DIR__ . '/../includes/header_ss.php';
?>

<!-- ═══════════════════════════════════════════════════════════════
     Tabs de navegación
     ═══════════════════════════════════════════════════════════════ -->
<div style="margin-bottom:20px; display:flex; gap:8px; flex-wrap:wrap;">
    <a href="?vista=kanban"
       class="btn <?= $vista==='kanban' ? 'btn-primary' : 'btn-outline' ?>">
        <i class="fa-solid fa-list-check"></i> Mis Tareas
    </a>
    <a href="?vista=asistencia"
       class="btn <?= $vista==='asistencia' ? 'btn-primary' : 'btn-outline' ?>">
        <i class="fa-solid fa-clock"></i> Asistencia
    </a>
    <a href="?vista=companeros"
       class="btn <?= $vista==='companeros' ? 'btn-primary' : 'btn-outline' ?>">
        <i class="fa-solid fa-users"></i> Mis Compañeros
    </a>
</div>

<!-- ═══════════════════════════════════════════════════════════════
     VISTA: KANBAN
     ═══════════════════════════════════════════════════════════════ -->
<?php if ($vista === 'kanban'): ?>

<!-- Stats rápidas -->
<div class="stats-grid" style="margin-bottom:20px;">
    <div class="stat-card stat-total">
        <div class="stat-icon"><i class="fa-solid fa-list-check"></i></div>
        <div class="stat-info">
            <div class="stat-value"><?= $tasksTotal ?></div>
            <div class="stat-label">Total Tareas</div>
        </div>
    </div>
    <div class="stat-card stat-activas">
        <div class="stat-icon"><i class="fa-solid fa-bolt"></i></div>
        <div class="stat-info">
            <div class="stat-value"><?= count($columnas['en_proceso']) ?></div>
            <div class="stat-label">En Proceso</div>
        </div>
    </div>
    <div class="stat-card stat-completadas">
        <div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div>
        <div class="stat-info">
            <div class="stat-value"><?= $tasksComp ?></div>
            <div class="stat-label">Completadas</div>
        </div>
    </div>
    <div class="stat-card stat-urgentes">
        <div class="stat-icon"><i class="fa-solid fa-hourglass-half"></i></div>
        <div class="stat-info">
            <div class="stat-value"><?= count($columnas['pendiente']) ?></div>
            <div class="stat-label">Pendientes</div>
        </div>
    </div>
</div>

<!-- Tablero Kanban -->
<div style="display:flex; gap:16px; overflow-x:auto; padding-bottom:12px;">
    <?php
    $colInfo = [
        'pendiente'  => ['label' => 'Pendiente',   'icon' => 'fa-hourglass-half', 'color' => '#f59e0b'],
        'en_proceso' => ['label' => 'En Proceso',   'icon' => 'fa-bolt',           'color' => '#3b82f6'],
        'completada' => ['label' => 'Completada',   'icon' => 'fa-circle-check',   'color' => '#22c55e'],
    ];
    foreach ($colInfo as $colKey => $colMeta):
        $tareasCol = $columnas[$colKey];
    ?>
    <div class="kanban-col card" style="min-width:280px; flex:1; padding:0;">
        <!-- Header columna -->
        <div style="padding:14px 16px 10px; border-bottom:1px solid var(--border-color);
                    display:flex; align-items:center; gap:8px;">
            <i class="fa-solid <?= $colMeta['icon'] ?>" style="color:<?= $colMeta['color'] ?>;"></i>
            <span style="font-weight:700; font-size:0.9rem;"><?= $colMeta['label'] ?></span>
            <span style="margin-left:auto; background:rgba(0,0,0,0.07); padding:2px 8px;
                         border-radius:12px; font-size:0.75rem; font-weight:600;">
                <?= count($tareasCol) ?>
            </span>
        </div>
        <!-- Tarjetas -->
        <div style="padding:12px; display:flex; flex-direction:column; gap:10px; min-height:160px;">
            <?php if (empty($tareasCol)): ?>
                <div style="text-align:center; padding:24px 0; color:var(--text-muted); font-size:0.82rem;">
                    <i class="fa-solid fa-inbox" style="font-size:1.6rem; margin-bottom:8px; display:block;"></i>
                    Sin tareas
                </div>
            <?php endif; ?>
            <?php foreach ($tareasCol as $t): ?>
            <div class="card" style="padding:12px; border-left:3px solid <?= esc($t['color']) ?>;
                                     margin:0; border-radius:10px;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:8px; margin-bottom:6px;">
                    <span style="font-weight:600; font-size:0.88rem; line-height:1.4;">
                        <?= esc($t['titulo']) ?>
                    </span>
                    <span class="badge badge-<?= $t['prioridad'] === 'alta' ? 'error' : ($t['prioridad'] === 'media' ? 'warning' : 'info') ?>"
                          style="flex-shrink:0; font-size:0.65rem;">
                        <?= ucfirst($t['prioridad']) ?>
                    </span>
                </div>
                <?php if ($t['descripcion']): ?>
                <p style="font-size:0.78rem; color:var(--text-muted); margin:0 0 8px; line-height:1.4;">
                    <?= esc(mb_substr($t['descripcion'], 0, 100)) ?><?= mb_strlen($t['descripcion']) > 100 ? '…' : '' ?>
                </p>
                <?php endif; ?>
                <?php if ($t['fecha_limite']): ?>
                <div style="font-size:0.72rem; color:var(--text-muted); margin-bottom:8px;">
                    <i class="fa-solid fa-calendar-days"></i>
                    Límite: <?= esc($t['fecha_limite']) ?>
                </div>
                <?php endif; ?>

                <!-- Botones acción -->
                <div style="display:flex; gap:6px; flex-wrap:wrap; margin-top:8px;">
                    <?php if ($colKey === 'pendiente'): ?>
                    <button onclick="moverTarea(<?= $t['id'] ?>, 'en_proceso')"
                            class="btn btn-primary btn-sm" style="font-size:0.72rem;">
                        <i class="fa-solid fa-play"></i> Iniciar
                    </button>
                    <?php elseif ($colKey === 'en_proceso'): ?>
                    <button onclick="moverTarea(<?= $t['id'] ?>, 'completada')"
                            class="btn btn-sm" style="background:#22c55e;color:#fff;font-size:0.72rem;">
                        <i class="fa-solid fa-circle-check"></i> Completar
                    </button>
                    <button onclick="moverTarea(<?= $t['id'] ?>, 'pendiente')"
                            class="btn btn-outline btn-sm" style="font-size:0.72rem;">
                        <i class="fa-solid fa-rotate-left"></i> Regresa
                    </button>
                    <?php endif; ?>
                    <button onclick="abrirEvidencias(<?= $t['id'] ?>, '<?= esc(addslashes($t['titulo'])) ?>')"
                            class="btn btn-outline btn-sm" style="font-size:0.72rem;">
                        <i class="fa-solid fa-paperclip"></i>
                        Evidencias <?= $t['evidencias_cnt'] > 0 ? '('.$t['evidencias_cnt'].')' : '' ?>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- ═══════════════════════════════════════════════════════════════
     VISTA: ASISTENCIA
     ═══════════════════════════════════════════════════════════════ -->
<?php elseif ($vista === 'asistencia'): ?>

<!-- Botones de asistencia -->
<div class="card" style="margin-bottom:20px; padding:24px; text-align:center;">
    <h2 style="margin:0 0 8px; font-size:1.1rem;">
        <i class="fa-solid fa-clock"></i> Registro de Asistencia
    </h2>
    <p style="color:var(--text-muted); font-size:0.88rem; margin-bottom:20px;">
        La hora se registra automáticamente al presionar el botón.
    </p>
    <div style="display:flex; gap:16px; justify-content:center; flex-wrap:wrap;">
        <button id="btnEntrada"
                onclick="registrarAsistencia('entrada')"
                class="btn btn-primary"
                style="padding:12px 28px; font-size:1rem; border-radius:12px;
                       <?= !$puedeEntrada ? 'opacity:.4; pointer-events:none;' : '' ?>"
                <?= !$puedeEntrada ? 'disabled' : '' ?>>
            <i class="fa-solid fa-sign-in-alt"></i> Registrar Entrada
        </button>
        <button id="btnSalida"
                onclick="registrarAsistencia('salida')"
                class="btn"
                style="padding:12px 28px; font-size:1rem; border-radius:12px;
                       background:#dc2626; color:#fff;
                       <?= !$puedeSalida ? 'opacity:.4; pointer-events:none;' : '' ?>"
                <?= !$puedeSalida ? 'disabled' : '' ?>>
            <i class="fa-solid fa-sign-out-alt"></i> Registrar Salida
        </button>
    </div>
    <div id="asistMsg" style="margin-top:14px; font-size:0.88rem;"></div>
</div>

<!-- Gráfica de asistencia últimos 30 días -->
<div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px;">
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><i class="fa-solid fa-chart-line"></i> Asistencia — últimos 30 días</h2>
        </div>
        <div style="position:relative; height:220px;">
            <canvas id="asistenciaChart"></canvas>
        </div>
    </div>
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><i class="fa-solid fa-chart-pie"></i> Distribución de Actividades</h2>
        </div>
        <div style="position:relative; height:220px; display:flex; justify-content:center; align-items:center;">
            <canvas id="actividadesChart"></canvas>
        </div>
    </div>
</div>

<!-- Historial de asistencia -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title"><i class="fa-solid fa-clock-rotate-left"></i> Historial de Asistencia</h2>
    </div>
    <?php if (!empty($asistencia)): ?>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Tipo</th>
                    <th>Fecha y Hora</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($asistencia as $r): ?>
                <tr>
                    <td>
                        <span class="badge <?= $r['tipo']==='entrada' ? 'badge-success' : 'badge-error' ?>">
                            <i class="fa-solid <?= $r['tipo']==='entrada' ? 'fa-sign-in-alt' : 'fa-sign-out-alt' ?>"></i>
                            <?= ucfirst($r['tipo']) ?>
                        </span>
                    </td>
                    <td><?= esc($r['fecha_hora']) ?></td>
                    <td class="text-muted fs-sm"><?= esc($r['ip'] ?? '-') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="empty-state">
        <i class="fa-solid fa-clock"></i>
        <h3>Sin registros de asistencia</h3>
        <p>Usa los botones de Entrada/Salida para comenzar.</p>
    </div>
    <?php endif; ?>
</div>

<!-- ═══════════════════════════════════════════════════════════════
     VISTA: COMPAÑEROS
     ═══════════════════════════════════════════════════════════════ -->
<?php elseif ($vista === 'companeros'): ?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title"><i class="fa-solid fa-users"></i> Compañeros de Servicio Social</h2>
    </div>
    <?php if (!empty($companeros)): ?>
    <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(260px,1fr)); gap:16px; padding:16px;">
        <?php foreach ($companeros as $c): ?>
        <div class="card" style="padding:16px; text-align:center; margin:0;">
            <div style="width:56px; height:56px; border-radius:50%; background:linear-gradient(135deg,#1a5276,#2980b9);
                        display:flex; align-items:center; justify-content:center; font-size:1.4rem;
                        color:#fff; font-weight:700; margin:0 auto 10px;">
                <?= mb_strtoupper(mb_substr($c['nombre_completo'], 0, 1)) ?>
            </div>
            <div style="font-weight:700; margin-bottom:4px;"><?= esc($c['nombre_completo']) ?></div>
            <div style="font-size:0.78rem; color:var(--text-muted); margin-bottom:8px;"><?= esc($c['email']) ?></div>
            <?php if ($c['institucion']): ?>
            <div style="font-size:0.75rem; color:var(--text-muted);">
                <i class="fa-solid fa-school"></i> <?= esc($c['institucion']) ?>
            </div>
            <?php endif; ?>
            <div style="display:flex; gap:8px; justify-content:center; margin-top:10px;">
                <span class="badge badge-success" title="Completadas">
                    <i class="fa-solid fa-circle-check"></i> <?= (int)$c['completadas'] ?>
                </span>
                <span class="badge badge-warning" title="En curso">
                    <i class="fa-solid fa-bolt"></i> <?= (int)$c['en_curso'] ?>
                </span>
                <?php if ($c['estado_hoy'] === 'entrada'): ?>
                <span class="badge" style="background:#22c55e;color:#fff;" title="Asistió hoy">
                    <i class="fa-solid fa-circle" style="font-size:.5rem;"></i> Presente
                </span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="card" style="text-align:center; padding:40px 20px;">
        <div class="empty-state" style="margin: 0 auto;">
            <i class="fa-solid fa-users-slash" style="font-size:3rem; color:var(--text-muted); margin-bottom:15px;"></i>
            <h3 style="margin-bottom:10px;">Sin compañeros registrados</h3>
            <p style="color:var(--text-muted);">Cuando más prestadores se registren aparecerán aquí.</p>
        </div>
    </div>
    <?php endif; ?>
<?php endif; ?>

<!-- ═══════════════════════════════════════════════════════════════
     Modal de Evidencias
     ═══════════════════════════════════════════════════════════════ -->
<div id="modalEvidencias" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.55);
     z-index:9999; align-items:center; justify-content:center;">
    <div style="background:var(--bg-card); border-radius:16px; padding:28px; width:100%;
                max-width:540px; max-height:90vh; overflow-y:auto; box-shadow:0 24px 64px rgba(0,0,0,.3);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3 style="margin:0;" id="modalEvidTitulo">Evidencias</h3>
            <button onclick="cerrarModalEvidencias()" class="btn btn-outline btn-icon">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <!-- Lista evidencias existentes -->
        <div id="listaEvidencias" style="margin-bottom:20px;"></div>

        <!-- Subir nueva evidencia -->
        <div style="border-top:1px solid var(--border-color); padding-top:16px;">
            <h4 style="margin:0 0 12px; font-size:0.9rem;">Agregar Evidencia</h4>
            <div class="form-group">
                <label class="form-label">Tipo</label>
                <select id="evTipo" class="form-control">
                    <option value="foto">Foto</option>
                    <option value="documento">Documento</option>
                    <option value="nota">Nota de texto</option>
                </select>
            </div>
            <div class="form-group" id="evArchivoWrap">
                <label class="form-label">Archivo (máx. 10 MB)</label>
                <input type="file" id="evArchivo" class="form-control"
                       accept="image/*,.pdf,.doc,.docx,.xls,.xlsx">
            </div>
            <div class="form-group">
                <label class="form-label">Descripción / Nota</label>
                <textarea id="evDesc" class="form-control" rows="3"
                          placeholder="Describe brevemente la evidencia"></textarea>
            </div>
            <button onclick="subirEvidencia()" class="btn btn-primary" style="width:100%;">
                <i class="fa-solid fa-upload"></i> Subir Evidencia
            </button>
            <div id="evMsg" style="margin-top:8px; font-size:0.83rem;"></div>
        </div>
    </div>
</div>

<?php
// Preparar datos para gráficas
$grafDias    = [];
$grafEntradas = [];
$grafSalidas  = [];
$mapGraf = [];
foreach ($grafData as $g) {
    $mapGraf[$g['dia']][$g['tipo']] = (int) $g['total'];
}
ksort($mapGraf);
foreach ($mapGraf as $dia => $val) {
    $grafDias[]     = $dia;
    $grafEntradas[] = $val['entrada'] ?? 0;
    $grafSalidas[]  = $val['salida']  ?? 0;
}
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const SS_API = '<?= BASE_URL ?>servicio_social/api/accion.php';
const CSRF   = '<?= $_SESSION['csrf_token'] ?? '' ?>';
let tareaActualId = null;

// ── Asistencia ─────────────────────────────────────────────────
function registrarAsistencia(tipo) {
    const btn = document.getElementById(tipo === 'entrada' ? 'btnEntrada' : 'btnSalida');
    btn.disabled = true;
    const fd = new FormData();
    fd.append('csrf_token', CSRF);
    fd.append('accion', 'registrar_asistencia');
    fd.append('tipo', tipo);
    fetch(SS_API, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            const msg = document.getElementById('asistMsg');
            if (d.ok) {
                msg.innerHTML = '<span style="color:#22c55e;"><i class="fa-solid fa-check"></i> ' + d.mensaje + '</span>';
                setTimeout(() => location.reload(), 1200);
            } else {
                msg.innerHTML = '<span style="color:#ef4444;">Error: ' + d.error + '</span>';
                btn.disabled = false;
            }
        }).catch(() => { btn.disabled = false; });
}

// ── Kanban — mover tarea ───────────────────────────────────────
function moverTarea(tareaId, nuevaColumna) {
    const fd = new FormData();
    fd.append('csrf_token', CSRF);
    fd.append('accion', 'mover_tarea');
    fd.append('tarea_id', tareaId);
    fd.append('columna', nuevaColumna);
    fetch(SS_API, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => { if (d.ok) location.reload(); else alert('Error: ' + d.error); })
        .catch(() => alert('Error de conexión'));
}

// ── Modal evidencias ───────────────────────────────────────────
function abrirEvidencias(tareaId, titulo) {
    tareaActualId = tareaId;
    document.getElementById('modalEvidTitulo').textContent = 'Evidencias — ' + titulo;
    document.getElementById('modalEvidencias').style.display = 'flex';
    cargarEvidencias();
}
function cerrarModalEvidencias() {
    document.getElementById('modalEvidencias').style.display = 'none';
    tareaActualId = null;
}
document.getElementById('evTipo').addEventListener('change', function() {
    document.getElementById('evArchivoWrap').style.display = this.value === 'nota' ? 'none' : 'block';
});
function cargarEvidencias() {
    fetch(SS_API + '?accion=listar_evidencias&tarea_id=' + tareaActualId)
        .then(r => r.json())
        .then(d => {
            const cont = document.getElementById('listaEvidencias');
            if (!d.ok || !d.evidencias.length) {
                cont.innerHTML = '<p style="color:var(--text-muted);font-size:.83rem;">Sin evidencias aún.</p>';
                return;
            }
            cont.innerHTML = d.evidencias.map(e => `
                <div style="display:flex;align-items:flex-start;gap:10px;padding:8px 0;
                             border-bottom:1px solid var(--border-color);">
                    <i class="fa-solid ${e.tipo==='foto'?'fa-image':e.tipo==='documento'?'fa-file-pdf':'fa-note-sticky'}"
                       style="color:var(--primary);margin-top:3px;flex-shrink:0;"></i>
                    <div style="flex:1;">
                        <div style="font-size:.83rem;font-weight:600;">${escHtml(e.nombre_original||e.tipo)}</div>
                        <div style="font-size:.75rem;color:var(--text-muted);">${escHtml(e.descripcion||'')}</div>
                        <div style="font-size:.7rem;color:var(--text-muted);">Por ${escHtml(e.usuario_nombre)} · ${escHtml(e.created_at)}</div>
                    </div>
                    ${e.archivo ? `<a href="<?= BASE_URL ?>public/uploads/ss/${escHtml(e.archivo)}"` : ''}
                      class="btn btn-outline btn-icon btn-sm"><i class="fa-solid fa-download"></i></a>` : ''}
                </div>
            `).join('');
        });
}
function subirEvidencia() {
    const fd = new FormData();
    fd.append('csrf_token', CSRF);
    fd.append('accion', 'subir_evidencia');
    fd.append('tarea_id', tareaActualId);
    fd.append('tipo', document.getElementById('evTipo').value);
    fd.append('descripcion', document.getElementById('evDesc').value);
    const arch = document.getElementById('evArchivo').files[0];
    if (arch) fd.append('archivo', arch);
    const msg = document.getElementById('evMsg');
    msg.textContent = 'Subiendo…';
    fetch(SS_API, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.ok) {
                msg.innerHTML = '<span style="color:#22c55e;">Evidencia agregada.</span>';
                document.getElementById('evDesc').value = '';
                document.getElementById('evArchivo').value = '';
                cargarEvidencias();
            } else {
                msg.innerHTML = '<span style="color:#ef4444;">Error: ' + d.error + '</span>';
            }
        }).catch(() => { msg.innerHTML = '<span style="color:#ef4444;">Error de conexión</span>'; });
}
function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// ── Gráficas ────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
    const dias  = <?= json_encode($grafDias) ?>;
    const ent   = <?= json_encode($grafEntradas) ?>;
    const sal   = <?= json_encode($grafSalidas) ?>;

    if (document.getElementById('asistenciaChart')) {
        new Chart(document.getElementById('asistenciaChart'), {
            type: 'bar',
            data: {
                labels: dias,
                datasets: [
                    { label: 'Entradas', data: ent, backgroundColor: 'rgba(34,197,94,.7)' },
                    { label: 'Salidas',  data: sal, backgroundColor: 'rgba(239,68,68,.7)' }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { labels: { color:'#aaa', font:{ size:11 }}}},
                scales: {
                    x: { ticks:{ color:'#aaa', maxRotation:45, font:{size:9} }, grid:{ color:'rgba(255,255,255,.05)' }},
                    y: { ticks:{ color:'#aaa', font:{size:11} }, grid:{ color:'rgba(255,255,255,.05)' }, beginAtZero:true }
                }
            }
        });
    }

    if (document.getElementById('actividadesChart')) {
        new Chart(document.getElementById('actividadesChart'), {
            type: 'doughnut',
            data: {
                labels: ['Pendientes','En Proceso','Completadas'],
                datasets: [{
                    data: [<?= count($columnas['pendiente']) ?>,
                           <?= count($columnas['en_proceso']) ?>,
                           <?= count($columnas['completada']) ?>],
                    backgroundColor: ['#f59e0b','#3b82f6','#22c55e'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { position:'bottom', labels:{ color:'#aaa', font:{size:11} }}}
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
