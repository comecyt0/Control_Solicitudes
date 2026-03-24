<?php
/**
 * COMECyT Control de Solicitudes
 * Panel de Administración — Dashboard (Departamento de Difusión)
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../config/auth.php';

verificarSesionAdmin();

$pdo = getConnection();

// Estadísticas de Difusión
$stmtM = $pdo->query("SELECT tipo, COUNT(*) as total FROM df_multimedia GROUP BY tipo");
$porTipo = $stmtM->fetchAll(PDO::FETCH_ASSOC) ?: [];
$totalArchivos = array_sum(array_column($porTipo, 'total'));

$stmtR = $pdo->query("SELECT id, titulo, tipo, fecha_creacion FROM df_multimedia ORDER BY fecha_creacion DESC LIMIT 5");
$recientes = $stmtR->fetchAll(PDO::FETCH_ASSOC);

$pageTitle  = 'Dashboard de Difusión';
$activeMenu = 'dashboard';

require_once __DIR__ . '/../../includes/header_admin.php';
?>

<div class="intranet-hero reveal-up" style="background: linear-gradient(135deg, rgba(225, 29, 72, 0.05) 0%, rgba(190, 18, 60, 0.1) 100%);">
    <div class="intranet-hero-content">
        <h1 style="color: #be123c;">Panel Editorial y de Difusión</h1>
        <p>Estás en el corazón del Departamento de Difusión. Desde aquí puedes gestionar la biblioteca de medios oficiales, publicar banners y programar el calendario editorial del COMECyT.</p>
    </div>
    <div class="intranet-hero-icon" style="color: rgba(225, 29, 72, 0.15);">
        <i class="fa-solid fa-camera-retro"></i>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card" style="border-top: 4px solid #e11d48;">
        <div class="stat-icon" style="color: #e11d48; background: rgba(225, 29, 72, 0.1);"><i class="fa-solid fa-photo-film"></i></div>
        <div class="stat-info">
            <div class="stat-value"><?= $totalArchivos ?></div>
            <div class="stat-label">Archivos Subidos</div>
        </div>
    </div>
    <div class="stat-card" style="border-top: 4px solid #14b8a6;">
        <div class="stat-icon" style="color: #14b8a6; background: rgba(20, 184, 166, 0.1);"><i class="fa-solid fa-bullhorn"></i></div>
        <div class="stat-info">
            <div class="stat-value">Gestión</div>
            <div class="stat-label">Banners Activos</div>
        </div>
    </div>
</div>

<div class="card reveal-up">
    <div class="card-header">
        <h2 class="card-title"><i class="fa-solid fa-cloud-arrow-up"></i> Últimos Archivos Agregados al Repositorio</h2>
        <a href="<?= BASE_URL ?>areas/difusion/repositorio.php" class="btn btn-outline btn-sm">Ir al Repositorio Digital</a>
    </div>
    <div class="table-wrapper">
        <?php if (!empty($recientes)): ?>
        <table>
            <thead><tr><th>ID</th><th>Título</th><th>Tipo</th><th>Fecha</th></tr></thead>
            <tbody>
                <?php foreach ($recientes as $r): ?>
                <tr>
                    <td>#<?= $r['id'] ?></td>
                    <td style="font-weight: 600;"><?= esc($r['titulo']) ?></td>
                    <td><span class="badge" style="background:#f1f5f9; color:#475569; border: 1px solid #cbd5e1;"><?= esc($r['tipo']) ?></span></td>
                    <td><?= formatearFecha($r['fecha_creacion']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p style="padding: 20px; color: #64748b; text-align: center;">El repositorio multimedia está vacío.</p>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
