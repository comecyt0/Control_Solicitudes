<?php
/**
 * COMECyT — Panel Admin: Gestión de Servicio Social
 * Tabs: Usuarios SS | Tablero Kanban SS | Reportes de asistencia
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../config/auth.php';

verificarSesionAdmin();

$pdo   = getConnection();
$tab   = $_GET['tab'] ?? 'usuarios';
$adminId = (int) $_SESSION['admin_id'];

// Procesar Acciones de Perfil SS
$mensajeFlash = '';
$tipoFlash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_accion_perfil'])) {
    validarCsrfPost();
    if ($_POST['_accion_perfil'] === 'aprobar_reemplazo_perfil_ss') {
        $idReq = (int) postParam('cve_personal');
        $stmtS = $pdo->prepare("SELECT cambios_tmp FROM ss_usuarios WHERE id = ?");
        $stmtS->execute([$idReq]);
        $req = $stmtS->fetch();
        if ($req && !empty($req['cambios_tmp'])) {
            $cambios = json_decode($req['cambios_tmp'], true);
            $stmtUpd = $pdo->prepare("UPDATE ss_usuarios SET fecha_nacimiento = ?, foto_perfil = COALESCE(?, foto_perfil), perfil_en_revision = false, cambios_tmp = NULL WHERE id = ?");
            $stmtUpd->execute([$cambios['fecha_nacimiento'], $cambios['foto_perfil'] ?? null, $idReq]);
            $mensajeFlash = "Foto y Cumpleaños del becario aprobados exitosamente.";
            $tipoFlash = "success";
        }
    } elseif ($_POST['_accion_perfil'] === 'rechazar_reemplazo_perfil_ss') {
        $idReq = (int) postParam('cve_personal');
        $stmtUpd = $pdo->prepare("UPDATE ss_usuarios SET perfil_en_revision = false, cambios_tmp = NULL WHERE id = ?");
        $stmtUpd->execute([$idReq]);
        $mensajeFlash = "Se han descartado los cambios estéticos del perfil del becario.";
        $tipoFlash = "success";
    }
}

// ── Datos comunes ─────────────────────────────────────────────
$stmtUsuarios = $pdo->query(
    "SELECT id, nombre || ' ' || appat AS nombre_completo, email,
            activo, institucion, carrera, fecha_inicio, fecha_fin,
            (SELECT COUNT(*) FROM ss_kanban_tareas t WHERE t.asignado_a = id) AS total_tareas,
            (SELECT COUNT(*) FROM ss_kanban_tareas t WHERE t.asignado_a = id AND t.columna='completada') AS completadas
     FROM ss_usuarios ORDER BY nombre"
);
$usuarios = $stmtUsuarios->fetchAll();

// Perfiles SS en revisión
$stmtSRevision = $pdo->query("SELECT id, nombre, appat, email, cambios_tmp FROM ss_usuarios WHERE perfil_en_revision = true");
$ssEnRevision = $stmtSRevision->fetchAll();

$stmtKanban = $pdo->query(
    "SELECT t.id, t.titulo, t.columna, t.prioridad, t.color, t.fecha_limite,
            u.nombre || ' ' || u.appat AS asignado_nombre,
            a.nombre AS creado_por_nombre,
            (SELECT COUNT(*) FROM ss_evidencias e WHERE e.tarea_id = t.id) AS evidencias_cnt
     FROM ss_kanban_tareas t
     LEFT JOIN ss_usuarios u ON t.asignado_a = u.id
     LEFT JOIN administradores a ON t.creado_por = a.id
     ORDER BY t.created_at DESC"
);
$kanbanTareas = $stmtKanban->fetchAll();

// Asistencia últimas 48h para reporte
$stmtAsistR = $pdo->query(
    "SELECT a.id, a.tipo, a.ip,
            TO_CHAR(a.fecha_hora AT TIME ZONE 'America/Mexico_City','DD/MM/YYYY HH24:MI:SS') AS fecha_hora_fmt,
            u.nombre || ' ' || u.appat AS usuario_nombre
     FROM ss_asistencia a
     JOIN ss_usuarios u ON a.usuario_id = u.id
     ORDER BY a.fecha_hora DESC LIMIT 100"
);
$asistencias = $stmtAsistR->fetchAll();

// Áreas para formularios
$stmtAreas = $pdo->query("SELECT cve_area, des_area FROM cat_areas ORDER BY des_area");
$areas = $stmtAreas->fetchAll();

// Columnas Kanban
$kanbanPorCol = ['pendiente'=>[],'en_proceso'=>[],'completada'=>[]];
foreach ($kanbanTareas as $t) $kanbanPorCol[$t['columna']][] = $t;

$pageTitle  = 'Servicio Social';
$activeMenu = 'servicio_social';
require_once __DIR__ . '/../../includes/header_admin.php';
?>

<!-- Tabs -->
<div style="display:flex; gap:8px; margin-bottom:24px; flex-wrap:wrap;">
    <a href="?tab=usuarios"
       class="btn <?= $tab==='usuarios' ? 'btn-primary' : 'btn-outline' ?>">
        <i class="fa-solid fa-user-graduate"></i> Usuarios SS
    </a>
    <a href="?tab=kanban"
       class="btn <?= $tab==='kanban' ? 'btn-primary' : 'btn-outline' ?>">
        <i class="fa-solid fa-list-check"></i> Tablero Kanban
    </a>
    <a href="?tab=reportes"
       class="btn <?= $tab==='reportes' ? 'btn-primary' : 'btn-outline' ?>">
        <i class="fa-solid fa-chart-bar"></i> Reportes
    </a>
    <a href="?tab=asistencia_completa"
       class="btn <?= $tab==='asistencia_completa' ? 'btn-primary' : 'btn-outline' ?>">
        <i class="fa-solid fa-clock-rotate-left"></i> Historial Completo
    </a>
</div>

<?php if ($tab === 'usuarios'): ?>
<!-- ════════════════════════════════════════════════════════════
     TAB: USUARIOS SS
     ════════════════════════════════════════════════════════════ -->

<?php if ($mensajeFlash): ?>
<div class="alert alert-<?= $tipoFlash ?> mb-16">
    <i class="fa-solid <?= $tipoFlash==='success'?'fa-check-circle':'fa-circle-exclamation' ?>"></i> <?= esc($mensajeFlash) ?>
</div>
<?php endif; ?>

<?php if (!empty($ssEnRevision)): ?>
<div class="card" style="border: 2px solid #3B82F6; background-color: #EFF6FF; margin-bottom: 2rem;">
    <div class="card-header" style="border-bottom: 1px solid rgba(59, 130, 246, 0.3);">
        <h2 class="card-title" style="color: #1D4ED8;">
            <i class="fa-solid fa-image-portrait"></i> Autorización de Cambios de Perfil (Becarios)
        </h2>
    </div>
    <div class="table-wrapper">
        <table style="background: transparent;">
            <thead>
                <tr>
                    <th>Becario Identificado</th>
                    <th>Nuevos Parámetros (Fotografía / Cumpleaños)</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ssEnRevision as $ssRev): 
                    $cambiosJSON = json_decode($ssRev['cambios_tmp'], true);
                ?>
                <tr>
                    <td>
                        <div class="fw-600" style="color: #1E40AF;"><?= esc($ssRev['email']) ?></div>
                        <div><i class="fa-solid fa-user-graduate fa-fw text-muted"></i> <?= esc($ssRev['nombre'] . ' ' . $ssRev['appat']) ?></div>
                    </td>
                    <td>
                        <div style="display:flex; gap: 15px; align-items:center;">
                            <?php if (!empty($cambiosJSON['foto_perfil'])): ?>
                                <img src="<?= BASE_URL ?>public/uploads/avatares/<?= esc($cambiosJSON['foto_perfil']) ?>" alt="Avatar" style="width: 50px; height: 50px; object-fit: cover; border-radius: 50%;">
                                <span style="font-size: 0.8rem; color: #3B82F6;">[Nueva Foto]</span>
                            <?php else: ?>
                                <div style="width: 50px; height: 50px; border-radius: 50%; background: #CBD5E1; display:flex; align-items:center; justify-content:center; color:#94A3B8;"><i class="fa-solid fa-image"></i></div>
                            <?php endif; ?>
                            
                            <?php if (!empty($cambiosJSON['fecha_nacimiento'])): ?>
                                <div>
                                    <strong><i class="fa-solid fa-cake-candles" style="color: #B45309;"></i> Cumpleaños propuesto:</strong><br>
                                    <?= date('d/m/Y', strtotime($cambiosJSON['fecha_nacimiento'])) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="td-actions">
                        <!-- Aceptar -->
                        <form method="POST" action="" style="display:inline-block; margin:0; margin-right: 5px;">
                            <?= csrfField() ?>
                            <input type="hidden" name="_accion_perfil" value="aprobar_reemplazo_perfil_ss">
                            <input type="hidden" name="cve_personal" value="<?= $ssRev['id'] ?>">
                            <button type="submit" class="btn btn-primary btn-sm" title="Aprobar Foto y Cumpleaños" data-confirm="¿Aprobar que el becario cambie su foto de perfil y mostrarla en el calendario?">
                                <i class="fa-solid fa-check"></i> Aprobar
                            </button>
                        </form>

                        <!-- Rechazar -->
                        <form method="POST" action="" style="display:inline-block; margin:0;">
                            <?= csrfField() ?>
                            <input type="hidden" name="_accion_perfil" value="rechazar_reemplazo_perfil_ss">
                            <input type="hidden" name="cve_personal" value="<?= $ssRev['id'] ?>">
                            <button type="submit" class="btn btn-outline btn-icon" title="Rechazar Cambios Estéticos" style="color: #DC2626; border-color: transparent;" data-confirm="¿Rechazar los cambios fotográficos de este becario?">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div class="card" style="margin-bottom:20px;">
    <div class="card-header">
        <h2 class="card-title"><i class="fa-solid fa-user-graduate"></i> Usuarios de Servicio Social</h2>
        <button onclick="abrirModalUsuario()" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-plus"></i> Nuevo Usuario SS
        </button>
    </div>
    <?php if (!empty($usuarios)): ?>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Nombre</th><th>Email</th><th>Institución</th>
                    <th>Periodo</th><th>Tareas</th><th>Estado</th><th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usuarios as $u): ?>
                <tr>
                    <td><?= esc($u['nombre_completo']) ?></td>
                    <td class="fs-sm"><?= esc($u['email']) ?></td>
                    <td class="fs-sm"><?= esc($u['institucion'] ?? '-') ?></td>
                    <td class="fs-sm text-muted">
                        <?= $u['fecha_inicio'] ? date('d/m/y', strtotime($u['fecha_inicio'])) : '-' ?>
                        — <?= $u['fecha_fin'] ? date('d/m/y', strtotime($u['fecha_fin'])) : 'Indef.' ?>
                    </td>
                    <td>
                        <span class="badge badge-info"><?= (int)$u['total_tareas'] ?> total</span>
                        <span class="badge badge-success"><?= (int)$u['completadas'] ?> ✓</span>
                    </td>
                    <td>
                        <span class="badge <?= $u['activo'] ? 'badge-success' : 'badge-error' ?>">
                            <?= $u['activo'] ? 'Activo' : 'Inactivo' ?>
                        </span>
                    </td>
                    <td>
                        <button onclick="editarUsuarioSS(<?= $u['id'] ?>)"
                                class="btn btn-outline btn-icon btn-sm" title="Editar">
                            <i class="fa-solid fa-pen"></i>
                        </button>
                        <button onclick="toggleActivoSS(<?= $u['id'] ?>, <?= $u['activo'] ? 'false' : 'true' ?>)"
                                class="btn btn-outline btn-icon btn-sm"
                                title="<?= $u['activo'] ? 'Desactivar' : 'Activar' ?>">
                            <i class="fa-solid <?= $u['activo'] ? 'fa-toggle-on' : 'fa-toggle-off' ?>"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="empty-state">
        <i class="fa-solid fa-graduation-cap"></i>
        <h3>Sin usuarios SS registrados</h3>
        <p>Agrega el primer prestador de servicio social.</p>
    </div>
    <?php endif; ?>
</div>

<?php elseif ($tab === 'kanban'): ?>
<!-- ════════════════════════════════════════════════════════════
     TAB: KANBAN SS (Admin)
     ════════════════════════════════════════════════════════════ -->
<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
    <h2 style="margin:0; font-size:1.1rem;">
        <i class="fa-solid fa-list-check"></i> Tablero Kanban — Servicio Social
    </h2>
    <button onclick="abrirModalTarea()" class="btn btn-primary btn-sm">
        <i class="fa-solid fa-plus"></i> Nueva Tarea SS
    </button>
</div>

<div style="display:flex; gap:16px; overflow-x:auto; padding-bottom:12px;">
    <?php
    $colInfo = [
        'pendiente'  => ['label'=>'Pendiente',  'icon'=>'fa-hourglass-half','color'=>'#f59e0b'],
        'en_proceso' => ['label'=>'En Proceso', 'icon'=>'fa-bolt',          'color'=>'#3b82f6'],
        'completada' => ['label'=>'Completada', 'icon'=>'fa-circle-check',  'color'=>'#22c55e'],
    ];
    foreach ($colInfo as $colKey => $colMeta):
        $tareasCol = $kanbanPorCol[$colKey];
    ?>
    <div class="card" style="min-width:280px; flex:1; padding:0;">
        <div style="padding:14px 16px 10px; border-bottom:1px solid var(--border-color);
                    display:flex; align-items:center; gap:8px;">
            <i class="fa-solid <?= $colMeta['icon'] ?>" style="color:<?= $colMeta['color'] ?>;"></i>
            <span style="font-weight:700; font-size:0.9rem;"><?= $colMeta['label'] ?></span>
            <span style="margin-left:auto; background:rgba(0,0,0,0.07); padding:2px 8px;
                         border-radius:12px; font-size:0.75rem; font-weight:600;">
                <?= count($tareasCol) ?>
            </span>
        </div>
        <div style="padding:12px; display:flex; flex-direction:column; gap:10px; min-height:140px;">
            <?php if (empty($tareasCol)): ?>
            <div style="text-align:center; padding:24px 0; color:var(--text-muted); font-size:0.82rem;">
                <i class="fa-solid fa-inbox" style="font-size:1.4rem; display:block; margin-bottom:8px;"></i>
                Sin tareas
            </div>
            <?php endif; ?>
            <?php foreach ($tareasCol as $t): ?>
            <div class="card" style="padding:12px; border-left:3px solid <?= esc($t['color']) ?>;
                                     margin:0; border-radius:10px;">
                <div style="display:flex; justify-content:space-between; gap:8px; margin-bottom:4px;">
                    <strong style="font-size:0.86rem;"><?= esc($t['titulo']) ?></strong>
                    <span class="badge badge-<?= $t['prioridad']==='alta'?'error':($t['prioridad']==='media'?'warning':'info') ?>"
                          style="flex-shrink:0;font-size:.65rem;"><?= ucfirst($t['prioridad']) ?></span>
                </div>
                <div style="font-size:0.75rem; color:var(--text-muted); margin-bottom:6px;">
                    <i class="fa-solid fa-user"></i>
                    <?= esc($t['asignado_nombre'] ?? 'Todos') ?>
                </div>
                <?php if ($t['fecha_limite']): ?>
                <div style="font-size:.72rem; color:var(--text-muted); margin-bottom:6px;">
                    <i class="fa-solid fa-calendar-days"></i> <?= esc($t['fecha_limite']) ?>
                </div>
                <?php endif; ?>
                <?php if ($t['evidencias_cnt'] > 0): ?>
                <div style="font-size:.72rem; color:var(--text-muted); margin-bottom: 4px;">
                    <i class="fa-solid fa-paperclip"></i> <?= $t['evidencias_cnt'] ?> evidencia(s)
                </div>
                <button onclick="abrirEvidenciasAdmin(<?= $t['id'] ?>, '<?= esc(addslashes($t['titulo'])) ?>')"
                        class="btn btn-outline btn-sm" style="font-size:0.72rem; padding: 4px 8px; margin-bottom: 6px;">
                    <i class="fa-solid fa-eye"></i> Ver Evidencias
                </button>
                <?php endif; ?>
                <div style="display:flex; gap:6px; margin-top:8px;">
                    <button onclick="editarTareaSS(<?= $t['id'] ?>)"
                            class="btn btn-outline btn-icon btn-sm" title="Editar">
                        <i class="fa-solid fa-pen"></i>
                    </button>
                    <button onclick="eliminarTareaSS(<?= $t['id'] ?>)"
                            class="btn btn-outline btn-icon btn-sm" title="Eliminar"
                            style="color:#ef4444;">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php elseif ($tab === 'reportes'): ?>
<!-- ════════════════════════════════════════════════════════════
     TAB: REPORTES SS
     ════════════════════════════════════════════════════════════ -->
<?php
// Estadísticas globales
$stmtStats = $pdo->query(
    "SELECT
        (SELECT COUNT(*) FROM ss_usuarios WHERE activo=TRUE) AS activos,
        (SELECT COUNT(*) FROM ss_kanban_tareas WHERE columna='completada') AS completadas,
        (SELECT COUNT(*) FROM ss_kanban_tareas WHERE columna!='completada') AS pendientes,
        (SELECT COUNT(*) FROM ss_asistencia WHERE DATE(fecha_hora AT TIME ZONE 'America/Mexico_City')=CURRENT_DATE) AS asist_hoy"
);
$stats = $stmtStats->fetch();

// Asistencia por usuario últimas 2 semanas
$stmtAsistUser = $pdo->query(
    "SELECT u.nombre || ' ' || u.appat AS nombre,
            COUNT(CASE WHEN a.tipo='entrada' THEN 1 END) AS entradas,
            COUNT(CASE WHEN a.tipo='salida' THEN 1 END)  AS salidas
     FROM ss_usuarios u
     LEFT JOIN ss_asistencia a ON a.usuario_id = u.id
         AND a.fecha_hora >= NOW() - INTERVAL '14 days'
     WHERE u.activo = TRUE
     GROUP BY u.id, u.nombre, u.appat
     ORDER BY entradas DESC"
);
$asistPorUsuario = $stmtAsistUser->fetchAll();
?>

<div class="stats-grid" style="margin-bottom:20px;">
    <div class="stat-card stat-total">
        <div class="stat-icon"><i class="fa-solid fa-user-graduate"></i></div>
        <div class="stat-info">
            <div class="stat-value"><?= (int)$stats['activos'] ?></div>
            <div class="stat-label">SS Activos</div>
        </div>
    </div>
    <div class="stat-card stat-completadas">
        <div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div>
        <div class="stat-info">
            <div class="stat-value"><?= (int)$stats['completadas'] ?></div>
            <div class="stat-label">Tareas Completadas</div>
        </div>
    </div>
    <div class="stat-card stat-activas">
        <div class="stat-icon"><i class="fa-solid fa-bolt"></i></div>
        <div class="stat-info">
            <div class="stat-value"><?= (int)$stats['pendientes'] ?></div>
            <div class="stat-label">Tareas Pendientes</div>
        </div>
    </div>
    <div class="stat-card stat-urgentes">
        <div class="stat-icon"><i class="fa-solid fa-clock"></i></div>
        <div class="stat-info">
            <div class="stat-value"><?= (int)$stats['asist_hoy'] ?></div>
            <div class="stat-label">Registros Hoy</div>
        </div>
    </div>
</div>

<div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px;">
    <!-- Asistencia por usuario -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><i class="fa-solid fa-chart-bar"></i> Asistencia (últimas 2 semanas)</h2>
        </div>
        <div style="position:relative; height:240px;">
            <canvas id="asistUserChart"></canvas>
        </div>
    </div>
    <!-- Distribución Kanban -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><i class="fa-solid fa-chart-pie"></i> Estado del Kanban</h2>
        </div>
        <div style="position:relative; height:240px; display:flex; justify-content:center; align-items:center;">
            <canvas id="kanbanPieChart"></canvas>
        </div>
    </div>
</div>

<!-- Historial asistencia reciente -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title"><i class="fa-solid fa-clock-rotate-left"></i> Últimos 100 registros de asistencia</h2>
    </div>
    <?php if (!empty($asistencias)): ?>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr><th>Usuario</th><th>Tipo</th><th>Fecha/Hora</th><th>IP</th></tr>
            </thead>
            <tbody>
                <?php foreach ($asistencias as $a): ?>
                <tr>
                    <td><?= esc($a['usuario_nombre']) ?></td>
                    <td>
                        <span class="badge <?= $a['tipo']==='entrada' ? 'badge-success' : 'badge-error' ?>">
                            <?= ucfirst($a['tipo']) ?>
                        </span>
                    </td>
                    <td class="fs-sm"><?= esc($a['fecha_hora_fmt']) ?></td>
                    <td class="fs-sm text-muted"><?= esc($a['ip'] ?? '-') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="empty-state">
        <i class="fa-solid fa-clock"></i>
        <h3>Sin registros de asistencia</h3>
    </div>
    <?php endif; ?>
</div>
<?php elseif ($tab === 'asistencia_completa'): ?>
<!-- ════════════════════════════════════════════════════════════
     TAB: LISTADO COMPLETO DE ASISTENCIAS
     ════════════════════════════════════════════════════════════ -->
<?php
$stmtFull = $pdo->query(
    "SELECT a.id, a.tipo, a.ip,
            TO_CHAR(a.fecha_hora AT TIME ZONE 'America/Mexico_City','DD/MM/YYYY HH24:MI:SS') AS fecha_hora_fmt,
            u.nombre || ' ' || u.appat AS usuario_nombre, u.institucion
     FROM ss_asistencia a
     JOIN ss_usuarios u ON a.usuario_id = u.id
     ORDER BY a.fecha_hora DESC"
);
$allAsist = $stmtFull->fetchAll();
?>
<div class="card">
    <div class="card-header">
        <h2 class="card-title"><i class="fa-solid fa-list-check"></i> Historial Completo de Asistencia</h2>
    </div>
    <?php if (!empty($allAsist)): ?>
    <div class="table-wrapper" style="max-height: 800px; overflow-y: auto;">
        <table>
            <thead style="position: sticky; top: 0; background: var(--bg-card); z-index: 10; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <tr><th>Usuario</th><th>Institución</th><th>Tipo</th><th>Fecha/Hora Exacta</th><th>IP</th></tr>
            </thead>
            <tbody>
                <?php foreach ($allAsist as $a): ?>
                <tr>
                    <td><?= esc($a['usuario_nombre']) ?></td>
                    <td class="fs-sm"><?= esc($a['institucion'] ?? '-') ?></td>
                    <td>
                        <span class="badge <?= $a['tipo']==='entrada' ? 'badge-success' : 'badge-error' ?>">
                            <?= ucfirst($a['tipo']) ?>
                        </span>
                    </td>
                    <td class="fs-sm" style="font-variant-numeric: tabular-nums;"><?= esc($a['fecha_hora_fmt']) ?></td>
                    <td class="fs-sm text-muted"><?= esc($a['ip'] ?? '-') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="empty-state">
        <i class="fa-solid fa-clock"></i>
        <h3>Sin registros de asistencia</h3>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- ════════════════════════════════════════════════════════════
     MODAL: Evidencias (Solo Vista Admin)
     ════════════════════════════════════════════════════════════ -->
<div id="modalAdminEvidencias" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.55);
     z-index:9999; align-items:center; justify-content:center;">
    <div style="background:var(--bg-card); border-radius:16px; padding:28px; width:100%;
                max-width:540px; max-height:90vh; overflow-y:auto; box-shadow:0 24px 64px rgba(0,0,0,.3);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3 style="margin:0;" id="modalAdminEvidTitulo">Evidencias</h3>
            <button onclick="cerrarModalEvidenciasAdmin()" class="btn btn-outline btn-icon">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div id="listaAdminEvidencias" style="margin-bottom:20px;"></div>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════════
     MODAL: Crear/Editar Usuario SS
     ════════════════════════════════════════════════════════════ -->
<div id="modalUsuarioSS" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.55);
     z-index:9999; align-items:center; justify-content:center; overflow-y:auto;">
    <div style="background:var(--bg-card); border-radius:16px; padding:28px; width:100%;
                max-width:520px; margin:20px auto; box-shadow:0 24px 64px rgba(0,0,0,.3);">
        <div style="display:flex; justify-content:space-between; margin-bottom:20px;">
            <h3 style="margin:0;" id="modalUsuTitulo">Nuevo Usuario SS</h3>
            <button onclick="cerrarModalUsuario()" class="btn btn-outline btn-icon"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form id="formUsuarioSS">
            <input type="hidden" id="usuSsId" value="">
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div class="form-group">
                    <label class="form-label">Nombre(s) *</label>
                    <input type="text" id="usuNombre" class="form-control" required maxlength="100">
                </div>
                <div class="form-group">
                    <label class="form-label">Apellido Paterno *</label>
                    <input type="text" id="usuAppat" class="form-control" required maxlength="80">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Apellido Materno</label>
                <input type="text" id="usuApmat" class="form-control" maxlength="80">
            </div>
            <div class="form-group">
                <label class="form-label">Email *</label>
                <input type="email" id="usuEmail" class="form-control" required maxlength="150">
            </div>
            <div class="form-group" id="passGroup">
                <label class="form-label">Contraseña <span id="passReqLabel">*</span></label>
                <input type="password" id="usuPass" class="form-control" minlength="6" placeholder="Mínimo 6 caracteres">
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div class="form-group">
                    <label class="form-label">Institución</label>
                    <input type="text" id="usuInstitucion" class="form-control" maxlength="150">
                </div>
                <div class="form-group">
                    <label class="form-label">Carrera</label>
                    <input type="text" id="usuCarrera" class="form-control" maxlength="150">
                </div>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div class="form-group">
                    <label class="form-label">Inicio de Servicio</label>
                    <input type="date" id="usuFechaInicio" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Fin de Servicio</label>
                    <input type="date" id="usuFechaFin" class="form-control">
                </div>
            </div>
            <div id="usuMsgModal" style="font-size:.83rem; margin-bottom:10px;"></div>
            <button type="button" onclick="guardarUsuarioSS()" class="btn btn-primary" style="width:100%;">
                <i class="fa-solid fa-floppy-disk"></i> Guardar
            </button>
        </form>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════════
     MODAL: Crear/Editar Tarea Kanban SS
     ════════════════════════════════════════════════════════════ -->
<div id="modalTareaSS" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.55);
     z-index:9999; align-items:center; justify-content:center; overflow-y:auto;">
    <div style="background:var(--bg-card); border-radius:16px; padding:28px; width:100%;
                max-width:520px; margin:20px auto; box-shadow:0 24px 64px rgba(0,0,0,.3);">
        <div style="display:flex; justify-content:space-between; margin-bottom:20px;">
            <h3 style="margin:0;" id="modalTareaTitulo">Nueva Tarea SS</h3>
            <button onclick="cerrarModalTarea()" class="btn btn-outline btn-icon"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form id="formTareaSS">
            <input type="hidden" id="tareaId" value="">
            <div class="form-group">
                <label class="form-label">Título *</label>
                <input type="text" id="tareaTitulo" class="form-control" required maxlength="200">
            </div>
            <div class="form-group">
                <label class="form-label">Descripción</label>
                <textarea id="tareaDesc" class="form-control" rows="3"></textarea>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div class="form-group">
                    <label class="form-label">Asignado a</label>
                    <select id="tareaAsignado" class="form-control">
                        <option value="">— Todos —</option>
                        <?php foreach ($usuarios as $u): ?>
                        <option value="<?= $u['id'] ?>"><?= esc($u['nombre_completo']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Prioridad</label>
                    <select id="tareaPrioridad" class="form-control">
                        <option value="baja">Baja</option>
                        <option value="media" selected>Media</option>
                        <option value="alta">Alta</option>
                    </select>
                </div>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div class="form-group">
                    <label class="form-label">Fecha Límite</label>
                    <input type="date" id="tareaFechaLim" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Color</label>
                    <input type="color" id="tareaColor" class="form-control" value="#662331" style="height:38px;">
                </div>
            </div>
            <div id="tareaMsgModal" style="font-size:.83rem; margin-bottom:10px;"></div>
            <button type="button" onclick="guardarTareaSS()" class="btn btn-primary" style="width:100%;">
                <i class="fa-solid fa-floppy-disk"></i> Guardar Tarea
            </button>
        </form>
    </div>
</div>

<?php if ($tab === 'reportes'): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Asistencia por usuario
    const labels = <?= json_encode(array_column($asistPorUsuario, 'nombre')) ?>;
    const ent    = <?= json_encode(array_column($asistPorUsuario, 'entradas')) ?>;
    const sal    = <?= json_encode(array_column($asistPorUsuario, 'salidas')) ?>;
    new Chart(document.getElementById('asistUserChart'), {
        type: 'bar',
        data: {
            labels,
            datasets: [
                { label:'Entradas', data:ent, backgroundColor:'rgba(34,197,94,.75)' },
                { label:'Salidas',  data:sal, backgroundColor:'rgba(239,68,68,.75)' }
            ]
        },
        options: {
            responsive:true, maintainAspectRatio:false,
            plugins:{ legend:{ labels:{ color:'#aaa', font:{size:11} }}},
            scales:{
                x:{ ticks:{ color:'#aaa',font:{size:11},maxRotation:30 }, grid:{ color:'rgba(255,255,255,.05)' }},
                y:{ ticks:{ color:'#aaa',font:{size:11} }, grid:{ color:'rgba(255,255,255,.05)' }, beginAtZero:true }
            }
        }
    });
    // Kanban pie
    new Chart(document.getElementById('kanbanPieChart'), {
        type:'doughnut',
        data:{
            labels:['Pendiente','En Proceso','Completada'],
            datasets:[{
                data:[<?= count($kanbanPorCol['pendiente']) ?>,
                      <?= count($kanbanPorCol['en_proceso']) ?>,
                      <?= count($kanbanPorCol['completada']) ?>],
                backgroundColor:['#f59e0b','#3b82f6','#22c55e'],
                borderWidth:0
            }]
        },
        options:{
            responsive:true,maintainAspectRatio:false,
            plugins:{legend:{position:'bottom',labels:{color:'#aaa',font:{size:11}}}}
        }
    });
});
</script>
<?php endif; ?>

<script>
const SS_ADMIN_API = '<?= BASE_URL ?>admin/api/servicio_social.php';
const CSRF_ADMIN   = '<?= $_SESSION['csrf_token'] ?? '' ?>';

// ── Modal Usuario SS ───────────────────────────────────────────
function abrirModalUsuario(id = null) {
    document.getElementById('usuSsId').value = '';
    document.getElementById('usuNombre').value = '';
    document.getElementById('usuAppat').value = '';
    document.getElementById('usuApmat').value = '';
    document.getElementById('usuEmail').value = '';
    document.getElementById('usuPass').value = '';
    document.getElementById('usuInstitucion').value = '';
    document.getElementById('usuCarrera').value = '';
    document.getElementById('usuFechaInicio').value = '';
    document.getElementById('usuFechaFin').value = '';
    document.getElementById('usuPass').required = !id;
    document.getElementById('passReqLabel').textContent = id ? '(dejar vacío = sin cambio)' : '*';
    document.getElementById('modalUsuTitulo').textContent = id ? 'Editar Usuario SS' : 'Nuevo Usuario SS';
    document.getElementById('modalUsuarioSS').style.display = 'flex';
}
function cerrarModalUsuario() { document.getElementById('modalUsuarioSS').style.display = 'none'; }
function guardarUsuarioSS() {
    const fd = new FormData();
    fd.append('csrf_token', CSRF_ADMIN);
    fd.append('accion', document.getElementById('usuSsId').value ? 'editar_usuario_ss' : 'crear_usuario_ss');
    fd.append('id',           document.getElementById('usuSsId').value);
    fd.append('nombre',       document.getElementById('usuNombre').value.trim());
    fd.append('appat',        document.getElementById('usuAppat').value.trim());
    fd.append('apmat',        document.getElementById('usuApmat').value.trim());
    fd.append('email',        document.getElementById('usuEmail').value.trim());
    fd.append('password',     document.getElementById('usuPass').value);
    fd.append('institucion',  document.getElementById('usuInstitucion').value.trim());
    fd.append('carrera',      document.getElementById('usuCarrera').value.trim());
    fd.append('fecha_inicio', document.getElementById('usuFechaInicio').value);
    fd.append('fecha_fin',    document.getElementById('usuFechaFin').value);
    const msg = document.getElementById('usuMsgModal');
    msg.textContent = 'Guardando…';
    fetch(SS_ADMIN_API, { method:'POST', body:fd })
        .then(r=>r.json())
        .then(d => {
            if (d.ok) location.reload();
            else msg.innerHTML = '<span style="color:#ef4444;">'+d.error+'</span>';
        });
}
function editarUsuarioSS(id) {
    fetch(SS_ADMIN_API + '?accion=ver_usuario_ss&id=' + id)
        .then(r=>r.json())
        .then(d => {
            if (!d.ok) return;
            const u = d.usuario;
            abrirModalUsuario(id);
            document.getElementById('usuSsId').value = u.id;
            document.getElementById('usuNombre').value = u.nombre;
            document.getElementById('usuAppat').value = u.appat;
            document.getElementById('usuApmat').value = u.apmat || '';
            document.getElementById('usuEmail').value = u.email;
            document.getElementById('usuInstitucion').value = u.institucion || '';
            document.getElementById('usuCarrera').value = u.carrera || '';
            document.getElementById('usuFechaInicio').value = u.fecha_inicio || '';
            document.getElementById('usuFechaFin').value = u.fecha_fin || '';
        });
}
function toggleActivoSS(id, nuevoActivo) {
    if (!confirm('¿Deseas cambiar el estado de este usuario?')) return;
    const fd = new FormData();
    fd.append('csrf_token', CSRF_ADMIN);
    fd.append('accion', 'toggle_activo_ss');
    fd.append('id', id);
    fd.append('activo', nuevoActivo);
    fetch(SS_ADMIN_API, { method:'POST', body:fd })
        .then(r=>r.json())
        .then(d => { if (d.ok) location.reload(); else alert(d.error); });
}

// ── Modal Tarea SS ─────────────────────────────────────────────
function abrirModalTarea() {
    document.getElementById('tareaId').value = '';
    document.getElementById('tareaTitulo').value = '';
    document.getElementById('tareaDesc').value = '';
    document.getElementById('tareaAsignado').value = '';
    document.getElementById('tareaPrioridad').value = 'media';
    document.getElementById('tareaFechaLim').value = '';
    document.getElementById('tareaColor').value = '#662331';
    document.getElementById('modalTareaTitulo').textContent = 'Nueva Tarea SS';
    document.getElementById('modalTareaSS').style.display = 'flex';
}
function cerrarModalTarea() { document.getElementById('modalTareaSS').style.display = 'none'; }
function guardarTareaSS() {
    const fd = new FormData();
    fd.append('csrf_token', CSRF_ADMIN);
    const id = document.getElementById('tareaId').value;
    fd.append('accion',      id ? 'editar_tarea_ss' : 'crear_tarea_ss');
    fd.append('id',          id);
    fd.append('titulo',      document.getElementById('tareaTitulo').value.trim());
    fd.append('descripcion', document.getElementById('tareaDesc').value.trim());
    fd.append('asignado_a',  document.getElementById('tareaAsignado').value);
    fd.append('prioridad',   document.getElementById('tareaPrioridad').value);
    fd.append('fecha_limite',document.getElementById('tareaFechaLim').value);
    fd.append('color',       document.getElementById('tareaColor').value);
    const msg = document.getElementById('tareaMsgModal');
    msg.textContent = 'Guardando…';
    fetch(SS_ADMIN_API, { method:'POST', body:fd })
        .then(r=>r.json())
        .then(d => { if (d.ok) location.reload(); else msg.innerHTML='<span style="color:#ef4444;">'+d.error+'</span>'; });
}
function editarTareaSS(id) {
    fetch(SS_ADMIN_API + '?accion=ver_tarea_ss&id=' + id)
        .then(r=>r.json())
        .then(d => {
            if (!d.ok) return;
            const t = d.tarea;
            abrirModalTarea();
            document.getElementById('tareaId').value = t.id;
            document.getElementById('tareaTitulo').value = t.titulo;
            document.getElementById('tareaDesc').value = t.descripcion || '';
            document.getElementById('tareaAsignado').value = t.asignado_a || '';
            document.getElementById('tareaPrioridad').value = t.prioridad;
            document.getElementById('tareaFechaLim').value = t.fecha_limite || '';
            document.getElementById('tareaColor').value = t.color;
            document.getElementById('modalTareaTitulo').textContent = 'Editar Tarea SS';
        });
}
function eliminarTareaSS(id) {
    if (!confirm('¿Eliminar esta tarea permanentemente? Se eliminarán también sus evidencias.')) return;
    const fd = new FormData();
    fd.append('csrf_token', CSRF_ADMIN);
    fd.append('accion', 'eliminar_tarea_ss');
    fd.append('id', id);
    fetch(SS_ADMIN_API, { method:'POST', body:fd })
        .then(r=>r.json())
        .then(d => { if (d.ok) location.reload(); else alert(d.error); });
}

// ── Modal Lista Evidencias ──────────────────────────────────────
function abrirEvidenciasAdmin(tareaId, titulo) {
    document.getElementById('modalAdminEvidTitulo').textContent = 'Evidencias: ' + titulo;
    const lista = document.getElementById('listaAdminEvidencias');
    lista.innerHTML = '<div style="text-align:center; padding:20px;"><i class="fa-solid fa-spinner fa-spin"></i> Cargando...</div>';
    document.getElementById('modalAdminEvidencias').style.display = 'flex';

    fetch(SS_ADMIN_API + '?accion=listar_evidencias&tarea_id=' + tareaId)
        .then(r => r.json())
        .then(d => {
            if (!d.ok) {
                lista.innerHTML = '<div style="color:#ef4444;">Error: ' + d.error + '</div>';
                return;
            }
            if (d.evidencias.length === 0) {
                lista.innerHTML = '<div style="color:var(--text-muted);text-align:center;padding:10px;">No hay evidencias subidas.</div>';
                return;
            }
            let html = '<div style="display:flex; flex-direction:column; gap:8px;">';
            d.evidencias.forEach(ev => {
                let icon = 'fa-file';
                if (ev.tipo === 'foto') icon = 'fa-image';
                else if (ev.tipo === 'documento') icon = 'fa-file-pdf';
                else if (ev.tipo === 'nota') icon = 'fa-align-left';
                
                html += `<div style="border:1px solid var(--border-color); border-radius:8px; padding:12px; display:flex; gap:12px; align-items:flex-start;">
                    <div style="font-size:1.5rem; color:var(--text-muted);"><i class="fa-solid ${icon}"></i></div>
                    <div style="flex:1;">
                        <div style="font-size:0.75rem; color:var(--text-muted); margin-bottom:4px;">${ev.fecha}</div>`;
                if (ev.descripcion) {
                    html += `<div style="font-size:0.85rem; margin-bottom:6px;">${ev.descripcion}</div>`;
                }
                if (ev.archivo_path) {
                    html += `<a href="${BASE_URL_JS}${ev.archivo_path}" class="btn btn-outline btn-sm" style="font-size:0.7rem;">
                                <i class="fa-solid fa-download"></i> Ver Archivo
                             </a>`;
                }
                html += `</div></div>`;
            });
            html += '</div>';
            lista.innerHTML = html;
        })
        .catch(() => { lista.innerHTML = '<div style="color:#ef4444;">Error de red</div>'; });
}

function cerrarModalEvidenciasAdmin() {
    document.getElementById('modalAdminEvidencias').style.display = 'none';
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
