<?php
/**
 * COMECyT Control de Solicitudes
 * Panel de Administración — Reportes Avanzados
 *
 * Gráficas de tendencia mensual, top áreas/solicitantes,
 * tiempo promedio de resolución, solicitudes sin atender.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/auth.php';

verificarSesionAdmin();
$pdo = getConnection();

// ── Tendencia mensual últimos 6 meses ─────────────────────────────
$meses = [];
$mesesLabels = [];
$mesesData   = [];
for ($i = 5; $i >= 0; $i--) {
    $ts    = strtotime("-{$i} months");
    $key   = date('Y-m', $ts);
    // Meses abreviados en español (sin strftime — deprecada en PHP 8.1+)
    $mesesEs = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
    $label   = strtoupper($mesesEs[(int)date('n', $ts) - 1] . ' ' . date('y', $ts));
    $meses[$key] = ['label' => $label, 'total' => 0, 'completadas' => 0];
}

$stmtTend = $pdo->query(
    "SELECT TO_CHAR(fecha_creacion, 'YYYY-MM') AS mes,
            COUNT(*) AS total,
            SUM(CASE WHEN estatus = 'completada' THEN 1 ELSE 0 END) AS completadas
     FROM solicitudes
     WHERE fecha_creacion >= NOW() - INTERVAL '6 months'
     GROUP BY mes ORDER BY mes ASC"
);
foreach ($stmtTend as $row) {
    if (isset($meses[$row['mes']])) {
        $meses[$row['mes']]['total']       = (int) $row['total'];
        $meses[$row['mes']]['completadas'] = (int) $row['completadas'];
    }
}
foreach ($meses as $m) {
    $mesesLabels[] = $m['label'];
    $mesesData[] = ['total' => $m['total'], 'completadas' => $m['completadas']];
}

// ── Top 5 áreas con más solicitudes ──────────────────────────────
$stmtAreas = $pdo->query(
    "SELECT area, COUNT(*) AS total,
            SUM(CASE WHEN estatus='completada' THEN 1 ELSE 0 END) AS completadas,
            SUM(CASE WHEN estatus IN ('pendiente','en_proceso') THEN 1 ELSE 0 END) AS activas
     FROM solicitudes
     GROUP BY area ORDER BY total DESC LIMIT 5"
);
$topAreas = $stmtAreas->fetchAll();

// ── Top 5 solicitantes ────────────────────────────────────────────
$stmtSol = $pdo->query(
    "SELECT solicitante, area, COUNT(*) AS total
     FROM solicitudes
     GROUP BY solicitante, area ORDER BY total DESC LIMIT 5"
);
$topSolicitantes = $stmtSol->fetchAll();

// ── Tiempo promedio de resolución (horas) ─────────────────────────
$stmtTiempo = $pdo->query(
    "SELECT ROUND(AVG(EXTRACT(EPOCH FROM (h.fecha_cambio - s.fecha_creacion)) / 3600))::INT AS promedio_horas,
            s.tipo
     FROM solicitudes s
     INNER JOIN historial_solicitudes h ON h.solicitud_id = s.id AND h.estatus_nuevo = 'completada'
     WHERE s.estatus = 'completada'
     GROUP BY s.tipo ORDER BY promedio_horas ASC"
);
$tiemposResolucion = $stmtTiempo->fetchAll();

// ── Solicitudes sin atender > 3 días ─────────────────────────────
$stmtSinAtender = $pdo->query(
    "SELECT id, folio, tipo, solicitante, area, estatus, fecha_creacion,
            DATE_PART('day', NOW() - fecha_creacion)::INT AS dias_espera
     FROM solicitudes
     WHERE estatus IN ('pendiente','en_proceso')
       AND DATE_PART('day', NOW() - fecha_creacion) >= 3
     ORDER BY dias_espera DESC LIMIT 10"
);
$sinAtender = $stmtSinAtender->fetchAll();

// ── Distribución por tipo (para dona) ────────────────────────────
$stmtTipo = $pdo->query(
    "SELECT tipo, COUNT(*) AS total FROM solicitudes GROUP BY tipo ORDER BY total DESC"
);
$porTipo = $stmtTipo->fetchAll();
$maxTipo = !empty($porTipo) ? max(array_column($porTipo, 'total')) : 1;

// ── KPIs rápidos ─────────────────────────────────────────────────
$stmtKpi = $pdo->query(
    "SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN estatus = 'pendiente' THEN 1 ELSE 0 END) AS pendientes,
        SUM(CASE WHEN estatus = 'en_proceso' THEN 1 ELSE 0 END) AS en_proceso,
        SUM(CASE WHEN estatus = 'completada' THEN 1 ELSE 0 END) AS completadas,
        SUM(CASE WHEN estatus = 'cancelada' THEN 1 ELSE 0 END) AS canceladas,
        ROUND(AVG(CASE WHEN estatus = 'completada'
            THEN EXTRACT(EPOCH FROM (fecha_actualizacion - fecha_creacion)) / 3600
        END))::INT AS prom_horas
     FROM solicitudes"
);
$kpi = $stmtKpi->fetch();

$coloresTipo = [
    'mantenimiento' => '#F59E0B',
    'atencion'      => '#A78BFA',
    'soporte'       => '#22D3EE',
    'administracion'=> '#4ADE80',
    'sistemas'      => '#818CF8',
];

$pageTitle  = 'Reportes';
$activeMenu = 'reportes';
$helpPage   = 'reportes';

require_once __DIR__ . '/../includes/header_admin.php';
?>

<!-- KPIs Rápidos -->
<div class="stats-grid" style="grid-template-columns: repeat(5, 1fr); margin-bottom: 24px;">
    <div class="stat-card stat-total">
        <div class="stat-icon"><i class="fa-solid fa-clipboard-list"></i></div>
        <div class="stat-info">
            <div class="stat-value"><?= (int)$kpi['total'] ?></div>
            <div class="stat-label">Total</div>
        </div>
    </div>
    <div class="stat-card" style="border-top: 3px solid var(--color-pendiente);">
        <div class="stat-icon" style="background:rgba(217,119,6,0.12);color:var(--color-pendiente);"><i class="fa-solid fa-clock"></i></div>
        <div class="stat-info">
            <div class="stat-value"><?= (int)$kpi['pendientes'] ?></div>
            <div class="stat-label">Pendientes</div>
        </div>
    </div>
    <div class="stat-card stat-activas">
        <div class="stat-icon"><i class="fa-solid fa-bolt"></i></div>
        <div class="stat-info">
            <div class="stat-value"><?= (int)$kpi['en_proceso'] ?></div>
            <div class="stat-label">En Proceso</div>
        </div>
    </div>
    <div class="stat-card stat-completadas">
        <div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div>
        <div class="stat-info">
            <div class="stat-value"><?= (int)$kpi['completadas'] ?></div>
            <div class="stat-label">Completadas</div>
        </div>
    </div>
    <div class="stat-card" style="border-top: 3px solid var(--color-accent);">
        <div class="stat-icon" style="background:rgba(177,154,109,0.12);color:var(--color-accent);"><i class="fa-solid fa-stopwatch"></i></div>
        <div class="stat-info">
            <div class="stat-value"><?= $kpi['prom_horas'] ? $kpi['prom_horas'] . 'h' : '—' ?></div>
            <div class="stat-label">T. Promedio</div>
        </div>
    </div>
</div>

<!-- Gráfica de tendencia mensual -->
<div style="display:grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 24px;">
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><i class="fa-solid fa-chart-line"></i> Tendencia — Últimos 6 Meses</h2>
        </div>
        <div style="position:relative; height:260px;">
            <canvas id="tendenciaChart"></canvas>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><i class="fa-solid fa-chart-pie"></i> Por Tipo</h2>
        </div>
        <div style="position:relative; height:260px; display:flex; align-items:center; justify-content:center;">
            <canvas id="tipoDonaChart"></canvas>
        </div>
    </div>
</div>

<!-- Top Áreas y Top Solicitantes -->
<div style="display:grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px;">
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><i class="fa-solid fa-building"></i> Top 5 Áreas</h2>
        </div>
        <?php foreach ($topAreas as $a): ?>
        <?php $pct = $maxTipo > 0 ? round(($a['total'] / ($topAreas[0]['total'] ?: 1)) * 100) : 0; ?>
        <div class="chart-bar-row" style="margin-bottom:12px;">
            <div class="chart-bar-label" style="font-size:0.82rem; margin-bottom:4px;">
                <?= esc($a['area']) ?>
                <span class="text-muted fs-sm" style="float:right;"><?= (int)$a['total'] ?> sol</span>
            </div>
            <div class="chart-bar-track" style="height:8px; border-radius:4px; background:var(--bg-muted);">
                <div class="chart-bar-fill" style="width:<?= $pct ?>%; height:100%; border-radius:4px; background:linear-gradient(90deg,#662331,#B19A6D);"></div>
            </div>
            <div style="display:flex; gap:8px; margin-top:3px; font-size:0.7rem; color:var(--text-muted);">
                <span style="color:var(--color-completada);">✓ <?= (int)$a['completadas'] ?></span>
                <span style="color:var(--color-en-proceso);">⚡ <?= (int)$a['activas'] ?></span>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($topAreas)): ?>
        <p class="text-muted fs-sm">Sin datos disponibles.</p>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><i class="fa-solid fa-users"></i> Top 5 Solicitantes</h2>
        </div>
        <div class="table-wrapper">
            <table>
                <thead><tr>
                    <th>Nombre</th>
                    <th>Área</th>
                    <th style="text-align:center;">Solicitudes</th>
                </tr></thead>
                <tbody>
                    <?php foreach ($topSolicitantes as $s): ?>
                    <tr>
                        <td class="fw-600" style="font-size:0.83rem;"><?= esc($s['solicitante']) ?></td>
                        <td class="text-muted" style="font-size:0.78rem;"><?= esc($s['area']) ?></td>
                        <td style="text-align:center;">
                            <span class="badge" style="background:rgba(177,154,109,0.12);color:var(--color-accent);border:1px solid var(--border-accent);"><?= (int)$s['total'] ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($topSolicitantes)): ?>
                    <tr><td colspan="3" class="text-muted" style="text-align:center; padding:20px;">Sin datos</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Tiempo promedio por tipo + Solicitudes sin atender -->
<div style="display:grid; grid-template-columns: 1fr 2fr; gap: 20px; margin-bottom: 24px;">
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><i class="fa-solid fa-stopwatch"></i> T. Resolución Promedio</h2>
        </div>
        <?php if (!empty($tiemposResolucion)): ?>
        <?php foreach ($tiemposResolucion as $t): ?>
        <div style="display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid var(--border-color);">
            <div style="display:flex; align-items:center; gap:8px;">
                <i class="<?= getIconoTipo($t['tipo']) ?>" style="color:<?= $coloresTipo[$t['tipo']] ?? '#999' ?>;"></i>
                <span style="font-size:0.85rem;"><?= esc(getEtiqueta('tipo', $t['tipo'])) ?></span>
            </div>
            <span style="font-weight:700; color:var(--color-accent); font-size:0.9rem;">
                <?= $t['promedio_horas'] ? round($t['promedio_horas']) . 'h' : '—' ?>
            </span>
        </div>
        <?php endforeach; ?>
        <?php else: ?>
        <p class="text-muted fs-sm">No hay solicitudes completadas aún.</p>
        <?php endif; ?>
    </div>

    <div class="card" style="<?= empty($sinAtender) ? '' : 'border-left: 4px solid var(--color-urgente);' ?>">
        <div class="card-header">
            <h2 class="card-title" style="color:<?= empty($sinAtender) ? 'inherit' : 'var(--color-urgente)' ?>;">
                <i class="fa-solid fa-triangle-exclamation"></i>
                Sin Atender &gt;3 Días
                <span class="text-muted fs-sm fw-600" style="margin-left:6px;"><?= count($sinAtender) ?> casos</span>
            </h2>
        </div>
        <?php if (!empty($sinAtender)): ?>
        <div class="table-wrapper">
            <table>
                <thead><tr>
                    <th>Folio</th>
                    <th>Solicitante</th>
                    <th>Área</th>
                    <th>Días</th>
                    <th>Estatus</th>
                    <th></th>
                </tr></thead>
                <tbody>
                    <?php foreach ($sinAtender as $s): ?>
                    <tr>
                        <td><a href="<?= BASE_URL ?>admin/detalle.php?id=<?= $s['id'] ?>" class="folio-link"><?= esc($s['folio']) ?></a></td>
                        <td style="font-size:0.83rem;"><?= esc($s['solicitante']) ?></td>
                        <td class="text-muted" style="font-size:0.78rem;"><?= esc($s['area']) ?></td>
                        <td>
                            <span style="font-weight:700; color:<?= $s['dias_espera'] >= 7 ? 'var(--color-urgente)' : 'var(--color-pendiente)' ?>;">
                                <?= (int)$s['dias_espera'] ?>d
                            </span>
                        </td>
                        <td><span class="badge <?= getBadgeClase('estatus', $s['estatus']) ?>"><?= esc(getEtiqueta('estatus', $s['estatus'])) ?></span></td>
                        <td>
                            <a href="<?= BASE_URL ?>admin/detalle.php?id=<?= $s['id'] ?>" class="btn btn-outline btn-icon btn-sm" title="Ver detalle">
                                <i class="fa-solid fa-eye"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state" style="padding:20px;">
            <i class="fa-solid fa-circle-check" style="color:var(--color-completada);"></i>
            <p>¡Todo al día! No hay solicitudes pendientes de más de 3 días.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Scripts Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const labels    = <?= json_encode($mesesLabels) ?>;
    const dataTot   = <?= json_encode(array_column($mesesData, 'total')) ?>;
    const dataComp  = <?= json_encode(array_column($mesesData, 'completadas')) ?>;

    // Gráfica de línea — tendencia mensual
    const ctxTend = document.getElementById('tendenciaChart').getContext('2d');
    new Chart(ctxTend, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Total',
                    data: dataTot,
                    backgroundColor: 'rgba(102,35,49,0.7)',
                    borderColor: '#662331',
                    borderWidth: 1,
                    borderRadius: 4,
                    order: 2,
                },
                {
                    label: 'Completadas',
                    data: dataComp,
                    type: 'line',
                    borderColor: '#16A34A',
                    backgroundColor: 'rgba(22,163,74,0.08)',
                    borderWidth: 2,
                    pointRadius: 4,
                    tension: 0.4,
                    fill: true,
                    order: 1,
                }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'top', labels: { color: '#6B7280', font: { size: 11 } } } },
            scales: {
                x: { ticks: { color: '#6B7280' }, grid: { color: 'rgba(0,0,0,0.04)' } },
                y: { ticks: { color: '#6B7280', stepSize: 1 }, grid: { color: 'rgba(0,0,0,0.04)' }, beginAtZero: true }
            }
        }
    });

    // Gráfica de dona — por tipo
    const tipoLabels = <?= json_encode(array_map(fn($t) => ETIQUETAS_TIPO[$t['tipo']] ?? $t['tipo'], $porTipo)) ?>;
    const tipoData   = <?= json_encode(array_column($porTipo, 'total')) ?>;
    const tipoColors = <?= json_encode(array_map(fn($t) => $coloresTipo[$t['tipo']] ?? '#9D9BAA', $porTipo)) ?>;

    const ctxDona = document.getElementById('tipoDonaChart').getContext('2d');
    new Chart(ctxDona, {
        type: 'doughnut',
        data: {
            labels: tipoLabels,
            datasets: [{ data: tipoData, backgroundColor: tipoColors, borderWidth: 0, hoverOffset: 6 }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { color: '#6B7280', font: { size: 11 }, padding: 10 } } },
            cutout: '65%'
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
