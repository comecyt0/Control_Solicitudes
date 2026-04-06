<?php
/**
 * COMECyT Control de Solicitudes
 * Panel de Administracion — Dashboard
 *
 * Muestra estadisticas generales del sistema:
 * totales por estatus, urgentes activos, distribucion por tipo,
 * y las solicitudes mas recientes.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/auth.php';

verificarSesionAdmin();

$pdo = getConnection();

// -------------------------------------------------------
// Consultas de estadisticas
// -------------------------------------------------------

// Conteo por estatus
$stmtEstatus = $pdo->query(
    "SELECT estatus, COUNT(*) AS total
     FROM solicitudes
     GROUP BY estatus"
);
$porEstatus = [];
foreach ($stmtEstatus as $row) {
    $porEstatus[$row['estatus']] = (int) $row['total'];
}
$totalSolicitudes = array_sum($porEstatus);
$totalActivas     = ($porEstatus['pendiente'] ?? 0) + ($porEstatus['en_proceso'] ?? 0);
$totalCompletadas = $porEstatus['completada'] ?? 0;

// Urgentes activas
$stmtUrgentes = $pdo->query(
    "SELECT COUNT(*) AS total
     FROM solicitudes
     WHERE prioridad = 'urgente' AND estatus NOT IN ('completada', 'cancelada')"
);
$urgentesActivas = (int) $stmtUrgentes->fetchColumn();

// Conteo por tipo
$stmtTipo = $pdo->query(
    "SELECT tipo, COUNT(*) AS total
     FROM solicitudes
     GROUP BY tipo
     ORDER BY total DESC"
);
$porTipo = $stmtTipo->fetchAll();

// Solicitudes recientes (ultimas 8)
$stmtRecientes = $pdo->query(
    "SELECT id, folio, tipo, solicitante, area, prioridad, estatus, fecha_creacion, sistema_especifico
     FROM solicitudes
     ORDER BY fecha_creacion DESC
     LIMIT 8"
);
$recientes = $stmtRecientes->fetchAll();

// -------------------------------------------------------
// Preparar datos para grafica de barras
// -------------------------------------------------------
$maxTipo = !empty($porTipo) ? max(array_column($porTipo, 'total')) : 1;

$coloresTipo = [
    'mantenimiento' => '#F59E0B',
    'atencion'      => '#A78BFA',
    'soporte'       => '#22D3EE',
    'administracion'=> '#4ADE80',
];

// -------------------------------------------------------
// Variables para la vista
// -------------------------------------------------------
$pageTitle  = 'Dashboard';
$activeMenu = 'dashboard';
$helpPage   = 'dashboard';

require_once __DIR__ . '/../includes/header_admin.php';
?>

<!-- Tarjetas de estadisticas -->
<div class="stats-grid">
    <a href="<?= BASE_URL ?>admin/solicitudes.php" class="stat-card stat-total">
        <div class="stat-icon"><i class="fa-solid fa-clipboard-list"></i></div>
        <div class="stat-info">
            <div class="stat-value"><?= $totalSolicitudes ?></div>
            <div class="stat-label">Total Solicitudes</div>
        </div>
    </a>
    <a href="<?= BASE_URL ?>admin/solicitudes.php?estatus=pendiente" class="stat-card stat-activas">
        <div class="stat-icon"><i class="fa-solid fa-bolt"></i></div>
        <div class="stat-info">
            <div class="stat-value"><?= $totalActivas ?></div>
            <div class="stat-label">Activas</div>
        </div>
    </a>
    <a href="<?= BASE_URL ?>admin/solicitudes.php?estatus=completada" class="stat-card stat-completadas">
        <div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div>
        <div class="stat-info">
            <div class="stat-value"><?= $totalCompletadas ?></div>
            <div class="stat-label">Completadas</div>
        </div>
    </a>
    <a href="<?= BASE_URL ?>admin/solicitudes.php?prioridad=urgente" class="stat-card stat-urgentes">
        <div class="stat-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <div class="stat-info">
            <div class="stat-value"><?= $urgentesActivas ?></div>
            <div class="stat-label">Urgentes Activas</div>
        </div>
    </a>
</div>

<!-- Graficas y tabla recientes -->
<div style="display:grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px;">

    <!-- Grafica de solicitudes por tipo -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">
                <i class="fa-solid fa-chart-bar"></i>
                Solicitudes por Tipo
            </h2>
        </div>
        <?php if (!empty($porTipo)): ?>
            <?php foreach ($porTipo as $item): ?>
                <?php
                    $pct   = $maxTipo > 0 ? round(($item['total'] / $maxTipo) * 100) : 0;
                    $color = $coloresTipo[$item['tipo']] ?? '#9D9BAA';
                ?>
                <div class="chart-bar-row">
                    <div class="chart-bar-label">
                        <i class="<?= getIconoTipo($item['tipo']) ?>" style="color: <?= $color ?>; width:14px;"></i>
                        <?= esc(getEtiqueta('tipo', $item['tipo'])) ?>
                    </div>
                    <div class="chart-bar-track">
                        <div class="chart-bar-fill" style="width: <?= $pct ?>%; background: <?= $color ?>;"></div>
                    </div>
                    <div class="chart-bar-count"><?= $item['total'] ?></div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="text-muted fs-sm">Sin datos disponibles.</p>
        <?php endif; ?>
    </div>

    <!-- Distribucion por estatus -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">
                <i class="fa-solid fa-chart-pie"></i>
                Distribucion por Estatus
            </h2>
        </div>
        <div style="position: relative; height: 260px; width: 100%; display: flex; justify-content: center; align-items: center; padding: 10px;">
            <canvas id="estatusPieChart"></canvas>
        </div>
    </div>
</div>

<!-- Tabla de solicitudes recientes -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">
            <i class="fa-solid fa-clock-rotate-left"></i>
            Solicitudes Recientes
        </h2>
        <a href="<?= BASE_URL ?>admin/solicitudes.php" class="btn btn-outline btn-sm">
            Ver todas <i class="fa-solid fa-arrow-right"></i>
        </a>
    </div>

    <?php if (!empty($recientes)): ?>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Folio</th>
                    <th>Tipo</th>
                    <th>Solicitante</th>
                    <th>Area</th>
                    <th>Prioridad</th>
                    <th>Estatus</th>
                    <th>Fecha</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recientes as $sol): ?>
                <tr>
                    <td>
                        <a href="<?= BASE_URL ?>admin/detalle.php?id=<?= $sol['id'] ?>"
                           class="folio-link">
                            <?= esc($sol['folio']) ?>
                        </a>
                    </td>
                    <td>
                        <span class="badge <?= getBadgeClase('tipo', $sol['tipo']) ?>">
                            <i class="<?= getIconoTipo($sol['tipo']) ?>"></i>
                            <?= esc(getEtiqueta('tipo', $sol['tipo'])) ?>
                        </span>
                        <?php if ($sol['tipo'] === 'sistemas' && !empty($sol['sistema_especifico'])): ?>
                            <div style="margin-top: 4px; font-size: 0.65rem; color: #4f46e5; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 120px;" title="<?= esc($sol['sistema_especifico']) ?>">
                                <i class="fa-solid fa-layer-group"></i> <?= esc($sol['sistema_especifico']) ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td><?= esc($sol['solicitante']) ?></td>
                    <td><?= esc($sol['area']) ?></td>
                    <td>
                        <span class="badge <?= getBadgeClase('prioridad', $sol['prioridad']) ?>">
                            <?= esc(getEtiqueta('prioridad', $sol['prioridad'])) ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge <?= getBadgeClase('estatus', $sol['estatus']) ?>">
                            <i class="<?= getIconoEstatus($sol['estatus']) ?>"></i>
                            <?= esc(getEtiqueta('estatus', $sol['estatus'])) ?>
                        </span>
                    </td>
                    <td class="text-muted fs-sm"><?= formatearFecha($sol['fecha_creacion']) ?></td>
                    <td>
                        <a href="<?= BASE_URL ?>admin/detalle.php?id=<?= $sol['id'] ?>"
                           class="btn btn-outline btn-icon" title="Ver detalle">
                            <i class="fa-solid fa-eye"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="empty-state">
        <i class="fa-solid fa-inbox"></i>
        <h3>Sin solicitudes registradas</h3>
        <p>Las solicitudes apareceran aqui cuando sean enviadas.</p>
    </div>
    <?php endif; ?>
</div>

<?php
$estatusLabels = [];
$estatusData = [];
$estatusColors = [];
$estatusConfig = [
    'pendiente'  => ['label' => 'Pendiente',  'color' => '#FCD34D'],
    'en_proceso' => ['label' => 'En Proceso',  'color' => '#60A5FA'],
    'completada' => ['label' => 'Completada',  'color' => '#4ADE80'],
    'cancelada'  => ['label' => 'Cancelada',   'color' => '#F87171'],
];
foreach ($estatusConfig as $est => $cfg) {
    $estatusLabels[] = $cfg['label'];
    $estatusData[] = $porEstatus[$est] ?? 0;
    $estatusColors[] = $cfg['color'];
}
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('estatusPieChart').getContext('2d');
    new Chart(ctx, {
        type: 'pie',
        data: {
            labels: <?= json_encode($estatusLabels) ?>,
            datasets: [{
                data: <?= json_encode($estatusData) ?>,
                backgroundColor: <?= json_encode($estatusColors) ?>,
                borderWidth: 0,
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { color: '#bbb' }
                }
            }
        }
    });
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
