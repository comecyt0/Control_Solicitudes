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

<style>
/* Estilos Premium - Dashboard Difusión */
:root {
    --dif-primary: #be123c;
    --dif-secondary: #e11d48;
    --dif-light: #fff1f2;
    --dif-dark: #881337;
    --glass-bg: rgba(255, 255, 255, 0.85);
    --glass-border: rgba(255, 255, 255, 0.4);
}

.hero-difusion {
    background: linear-gradient(135deg, var(--dif-primary) 0%, var(--dif-secondary) 100%);
    border-radius: 20px;
    padding: 40px;
    margin-bottom: 30px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    color: white;
    box-shadow: 0 20px 40px -10px rgba(190, 18, 60, 0.4);
    position: relative;
    overflow: hidden;
}

.hero-difusion::after {
    content: '';
    position: absolute;
    top: -50%; right: -10%;
    width: 300px; height: 300px;
    background: radial-gradient(circle, rgba(255,255,255,0.2) 0%, transparent 70%);
    border-radius: 50%;
}

.hero-difusion-content {
    position: relative;
    z-index: 2;
}

.hero-difusion h1 {
    font-size: 2.4rem;
    font-weight: 800;
    margin-bottom: 12px;
    letter-spacing: -0.5px;
}

.hero-difusion p {
    font-size: 1.1rem;
    opacity: 0.9;
    max-width: 600px;
    line-height: 1.6;
}

.hero-difusion-icon {
    font-size: 6rem;
    opacity: 0.2;
    z-index: 1;
    transform: rotate(-10deg);
    transition: transform 0.5s ease;
}

.hero-difusion:hover .hero-difusion-icon {
    transform: rotate(0deg) scale(1.1);
}

.stats-grid-premium {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 24px;
    margin-bottom: 30px;
}

.stat-card-premium {
    background: var(--glass-bg);
    backdrop-filter: blur(10px);
    border: 1px solid var(--glass-border);
    border-radius: 16px;
    padding: 24px;
    display: flex;
    align-items: center;
    gap: 20px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.03);
    transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
    position: relative;
    overflow: hidden;
}

.stat-card-premium::before {
    content: '';
    position: absolute;
    top: 0; left: 0; width: 4px; height: 100%;
    background: var(--dif-secondary);
    border-radius: 4px 0 0 4px;
}

.stat-card-premium:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(190, 18, 60, 0.1);
}

.stat-icon-premium {
    width: 60px; height: 60px;
    border-radius: 14px;
    background: var(--dif-light);
    color: var(--dif-secondary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    transition: background 0.3s, color 0.3s;
}

.stat-card-premium:hover .stat-icon-premium {
    background: var(--dif-secondary);
    color: white;
}

.stat-info-premium { flex: 1; }
.stat-value-premium { font-size: 2.2rem; font-weight: 800; color: #1e293b; line-height: 1; }
.stat-label-premium { font-size: 0.95rem; color: #64748b; margin-top: 4px; font-weight: 500; }

.table-card-premium {
    background: #ffffff;
    border-radius: 20px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.04);
    border: 1px solid #f1f5f9;
    overflow: hidden;
}

.table-card-header {
    padding: 24px 30px;
    border-bottom: 1px solid #f1f5f9;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: var(--glass-bg);
}

.table-card-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 10px;
}

.btn-premium {
    background: var(--dif-light);
    color: var(--dif-secondary);
    border: 1px solid rgba(225, 29, 72, 0.2);
    border-radius: 50px;
    padding: 8px 20px;
    font-weight: 600;
    font-size: 0.9rem;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-premium:hover {
    background: var(--dif-secondary);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(225, 29, 72, 0.25);
}

.table-premium { width: 100%; border-collapse: collapse; }
.table-premium th { text-align: left; padding: 16px 30px; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; background: #f8fafc; border-bottom: 2px solid #f1f5f9; }
.table-premium td { padding: 18px 30px; border-bottom: 1px solid #f1f5f9; color: #334155; vertical-align: middle; }
.table-premium tr:last-child td { border-bottom: none; }
.table-premium tr { transition: background 0.2s ease; }
.table-premium tr:hover { background: #f8fcff; }

.badge-tipo {
    padding: 6px 12px;
    border-radius: 50px;
    font-size: 0.8rem;
    font-weight: 600;
    background: var(--dif-light);
    color: var(--dif-dark);
    border: 1px solid rgba(225, 29, 72, 0.1);
}

/* Animations */
.reveal-up {
    opacity: 0;
    transform: translateY(30px);
    transition: opacity 0.8s cubic-bezier(0.2, 0.8, 0.2, 1), transform 0.8s cubic-bezier(0.2, 0.8, 0.2, 1);
}
.reveal-up.active { opacity: 1; transform: translateY(0); }
</style>

<div class="hero-difusion reveal-up">
    <div class="hero-difusion-content">
        <h1>Panel Editorial y de Difusión</h1>
        <p>Gestiona la biblioteca de medios oficiales, revisa los banners y controla la imagen institucional de manera profesional y fluida.</p>
    </div>
    <div class="hero-difusion-icon">
        <i class="fa-solid fa-photo-film"></i>
    </div>
</div>

<div class="stats-grid-premium">
    <div class="stat-card-premium reveal-up" style="transition-delay: 0.1s;">
        <div class="stat-icon-premium"><i class="fa-solid fa-cloud-arrow-up"></i></div>
        <div class="stat-info-premium">
            <div class="stat-value-premium"><?= $totalArchivos ?></div>
            <div class="stat-label-premium">Archivos en Repositorio</div>
        </div>
    </div>
    <div class="stat-card-premium reveal-up" style="transition-delay: 0.2s;">
        <div class="stat-icon-premium" style="color: #0ea5e9; background: #e0f2fe;"><i class="fa-solid fa-bullhorn"></i></div>
        <div class="stat-info-premium">
            <div class="stat-value-premium">0</div>
            <div class="stat-label-premium">Campañas / Banners</div>
        </div>
    </div>
</div>

<div class="table-card-premium reveal-up" style="transition-delay: 0.3s;">
    <div class="table-card-header">
        <h2 class="table-card-title"><i class="fa-solid fa-bolt" style="color: var(--dif-secondary);"></i> Recientes en Repositorio</h2>
        <a href="<?= BASE_URL ?>areas/difusion/repositorio.php" class="btn-premium"><i class="fa-solid fa-folder-open"></i> Abrir Repositorio</a>
    </div>
    <div style="overflow-x: auto;">
        <?php if (!empty($recientes)): ?>
        <table class="table-premium">
            <thead>
                <tr>
                    <th>Identificador</th>
                    <th>Título del Material</th>
                    <th>Formato</th>
                    <th>Fecha de Carga</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recientes as $r): ?>
                <tr>
                    <td style="color: #94a3b8; font-weight: 500;">#<?= str_pad($r['id'], 4, '0', STR_PAD_LEFT) ?></td>
                    <td style="font-weight: 600; color: #0f172a;"><?= esc($r['titulo']) ?></td>
                    <td><span class="badge-tipo"><?= strtoupper(esc($r['tipo'])) ?></span></td>
                    <td><i class="fa-regular fa-calendar" style="color:#cbd5e1; margin-right: 6px;"></i> <?= date('d M Y', strtotime($r['fecha_creacion'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div style="padding: 60px 20px; text-align: center; color: #94a3b8;">
            <i class="fa-regular fa-folder-open fa-3x" style="margin-bottom: 16px; opacity: 0.5;"></i>
            <p style="font-size: 1.1rem;">Aún no se ha cargado ningún material al repositorio.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
    const reveals = document.querySelectorAll(".reveal-up");
    const revealOnScroll = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add("active");
                observer.unobserve(entry.target);
            }
        });
    }, { root: null, rootMargin: "0px", threshold: 0.1 });
    reveals.forEach(el => revealOnScroll.observe(el));
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
