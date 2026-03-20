<?php
/**
 * COMECyT Control de Solicitudes
 * Intranet Dashboard (Vista Pública Inicial)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/auth.php';

// Validar que el usuario esté loggeado (Admin o Usuario)
verificarSesionUsuario();

$pdo = getConnection();

// Obtener nombre del usuario para el saludo
$usuarioNombreCompleto = $_SESSION['user_nombre'] ?? $_SESSION['admin_nombre'] ?? 'Usuario';
$usuarioNombre = explode(' ', trim($usuarioNombreCompleto))[0];

// Obtener anuncios activos
$anuncios = [];
try {
    $stmtAnuncios = $pdo->query("SELECT * FROM anuncios WHERE activo = true ORDER BY fecha_creacion DESC LIMIT 5");
    $anuncios = $stmtAnuncios->fetchAll();
} catch (Throwable $e) {
    // Si la tabla no existe o hay error, continua vacio
}

$pageTitle  = 'Intranet COMECyT';
$activeMenu = 'dashboard';
$extraHead  = '
<style>
/* Estilos para el Dashboard Intranet */
.intranet-hero {
    background: linear-gradient(135deg, rgba(102, 35, 49, 0.05) 0%, rgba(139, 47, 66, 0.1) 100%);
    border-radius: 20px;
    padding: 40px;
    margin-bottom: 30px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
    border: 1px solid rgba(102, 35, 49, 0.1);
}
.intranet-hero-content h1 {
    font-size: 2.2rem;
    font-weight: 700;
    color: var(--color-primary);
    margin-bottom: 10px;
    line-height: 1.2;
}
.intranet-hero-content p {
    font-size: 1.1rem;
    color: #64748b;
    max-width: 500px;
}
.intranet-hero-icon {
    font-size: 5rem;
    color: rgba(102, 35, 49, 0.15);
}

.intranet-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 40px;
}

.intranet-card {
    background: #fff;
    border-radius: 16px;
    padding: 24px;
    text-decoration: none;
    color: inherit;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 6px rgba(0,0,0,0.02);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

.intranet-card::before {
    content: "";
    position: absolute;
    top: 0; left: 0; right: 0; height: 4px;
    background: var(--color-primary);
    transform: scaleX(0);
    transform-origin: left;
    transition: transform 0.3s ease;
}

.intranet-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 24px rgba(0,0,0,0.08);
    border-color: #cbd5e1;
}

.intranet-card:hover::before {
    transform: scaleX(1);
}

.intranet-card-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: rgba(102, 35, 49, 0.1);
    color: var(--color-primary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    margin-bottom: 16px;
    transition: background 0.3s ease, color 0.3s ease;
}

.intranet-card:hover .intranet-card-icon {
    background: var(--color-primary);
    color: #fff;
}

.intranet-card-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 8px;
}
.intranet-card-desc {
    font-size: 0.85rem;
    color: #64748b;
    line-height: 1.4;
}

/* Modificadores de color para tarjetas */
.card-solicitud::before { background: #3b82f6; }
.card-solicitud .intranet-card-icon { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
.card-solicitud:hover .intranet-card-icon { background: #3b82f6; color: #fff; }

.card-historial::before { background: #8b5cf6; }
.card-historial .intranet-card-icon { background: rgba(139, 92, 246, 0.1); color: #8b5cf6; }
.card-historial:hover .intranet-card-icon { background: #8b5cf6; color: #fff; }

.card-equipos::before { background: #10b981; }
.card-equipos .intranet-card-icon { background: rgba(16, 185, 129, 0.1); color: #10b981; }
.card-equipos:hover .intranet-card-icon { background: #10b981; color: #fff; }

.card-calendario::before { background: #f59e0b; }
.card-calendario .intranet-card-icon { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
.card-calendario:hover .intranet-card-icon { background: #f59e0b; color: #fff; }

.panels-grid {
    display: grid;
    grid-template-columns: 1.5fr 1fr;
    gap: 24px;
}
@media (max-width: 1024px) {
    .panels-grid { grid-template-columns: 1fr; }
}

.anuncio-item {
    padding: 16px;
    border-radius: 12px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    margin-bottom: 12px;
    transition: background 0.2s;
}
.anuncio-item:hover {
    background: #f1f5f9;
}
.anuncio-item:last-child { margin-bottom: 0; }
.anuncio-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px; }
.anuncio-titulo { font-weight: 600; color: #1e293b; font-size: 1rem; display: flex; align-items: center; gap: 8px; }
.anuncio-fecha { font-size: 0.75rem; color: #94a3b8; }
.anuncio-contenido { font-size: 0.9rem; color: #475569; line-height: 1.5; }

.sistemas-card {
    background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
    color: #fff;
}
.sistemas-card .card-header { border-bottom-color: rgba(255,255,255,0.1); }
.sistemas-card .card-title { color: #fff; }
.sistemas-team-list { margin-top: 16px; display: flex; flex-direction: column; gap: 12px; }
.team-member { display: flex; align-items: center; gap: 12px; padding: 12px; background: rgba(255,255,255,0.05); border-radius: 10px; }
.team-member:hover { background: rgba(255,255,255,0.08); }
.team-avatar { width: 40px; height: 40px; border-radius: 50%; background: var(--color-primary); display: flex; align-items: center; justify-content: center; font-weight: 700; color: #fff; }
.team-info { display: flex; flex-direction: column; }
.team-name { font-weight: 600; font-size: 0.9rem; }
.team-role { font-size: 0.75rem; color: #94a3b8; }
</style>
';

require_once __DIR__ . '/../includes/header_user.php';
?>

<div class="intranet-hero">
    <div class="intranet-hero-content">
        <h1>¡Bienvenido/a, <?= esc($usuarioNombre) ?>!</h1>
        <p>Dashboard central de servicios y requerimientos técnicos COMECyT. Gestiona tus solicitudes, equipos y mantente informado.</p>
    </div>
    <div class="intranet-hero-icon">
        <i class="fa-solid fa-shapes"></i>
    </div>
</div>

<div class="intranet-grid">
    <a href="<?= BASE_URL ?>public/nueva_solicitud.php" class="intranet-card card-solicitud">
        <div class="intranet-card-icon">
            <i class="fa-solid fa-plus-circle"></i>
        </div>
        <div class="intranet-card-title">Registrar Solicitud</div>
        <div class="intranet-card-desc">Crea un nuevo ticket de soporte, mantenimiento, atención o sistema.</div>
    </a>

    <a href="<?= BASE_URL ?>public/historial.php" class="intranet-card card-historial">
        <div class="intranet-card-icon">
            <i class="fa-solid fa-clock-rotate-left"></i>
        </div>
        <div class="intranet-card-title">Mi Historial</div>
        <div class="intranet-card-desc">Consulta el estatus y progreso de todas tus solicitudes previas.</div>
    </a>

    <a href="<?= BASE_URL ?>public/equipos_usuario.php" class="intranet-card card-equipos">
        <div class="intranet-card-icon">
            <i class="fa-solid fa-laptop"></i>
        </div>
        <div class="intranet-card-title">Mis Equipos</div>
        <div class="intranet-card-desc">Revisa el inventario de equipo de cómputo que tienes asignado.</div>
    </a>

    <a href="<?= BASE_URL ?>public/calendario.php" class="intranet-card card-calendario">
        <div class="intranet-card-icon">
            <i class="fa-solid fa-calendar-days"></i>
        </div>
        <div class="intranet-card-title">Calendario</div>
        <div class="intranet-card-desc">Consulta eventos públicos o solicita agendar espacios institucionales.</div>
    </a>
</div>

<div class="panels-grid">
    <!-- Panel de Anuncios -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">
                <i class="fa-solid fa-bullhorn text-primary"></i> Anuncios y Comunicados
            </h2>
        </div>
        <div class="card-body">
            <?php if (empty($anuncios)): ?>
                <div style="text-align: center; padding: 40px 20px; color: #94a3b8;">
                    <i class="fa-regular fa-bell-slash" style="font-size: 2rem; margin-bottom: 12px; display: block;"></i>
                    <p>No hay avisos recientes por parte del departamento.</p>
                </div>
            <?php else: ?>
                <?php foreach ($anuncios as $a): 
                    $icon = 'fa-circle-info';
                    $color = '#3b82f6';
                    if ($a['tipo'] === 'warning') { $icon = 'fa-triangle-exclamation'; $color = '#f59e0b'; }
                    elseif ($a['tipo'] === 'success') { $icon = 'fa-check-circle'; $color = '#10b981'; }
                    elseif ($a['tipo'] === 'urgent') { $icon = 'fa-bell'; $color = '#ef4444'; }
                ?>
                <div class="anuncio-item">
                    <div class="anuncio-header">
                        <div class="anuncio-titulo" style="color: <?= $color ?>;">
                            <i class="fa-solid <?= $icon ?>"></i> <?= esc($a['titulo']) ?>
                        </div>
                        <div class="anuncio-fecha"><?= date('d/m/Y', strtotime($a['fecha_creacion'])) ?></div>
                    </div>
                    <div class="anuncio-contenido">
                        <?= nl2br(esc($a['contenido'])) ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Panel Equipo de Sistemas -->
    <div class="card sistemas-card">
        <div class="card-header">
            <h2 class="card-title">
                <i class="fa-solid fa-shield-halved"></i> Equipo de TI y Sistemas
            </h2>
        </div>
        <div class="card-body" style="padding-top: 10px;">
            <p style="font-size: 0.9rem; color: #cbd5e1; line-height: 1.5; margin-bottom: 20px;">
                El equipo de Tecnologías de la Información está aquí para garantizar el óptimo funcionamiento de la infraestructura y proveer soluciones innovadoras al COMECyT.
            </p>
            
            <div class="sistemas-team-list">
                <div class="team-member">
                    <div class="team-avatar">S</div>
                    <div class="team-info">
                        <span class="team-name">Soporte Técnico</span>
                        <span class="team-role">Mantenimiento y atención de hardware</span>
                    </div>
                </div>
                <div class="team-member">
                    <div class="team-avatar" style="background: #3b82f6;">D</div>
                    <div class="team-info">
                        <span class="team-name">Desarrollo Web</span>
                        <span class="team-role">Creación de sistemas web institucionales</span>
                    </div>
                </div>
                <div class="team-member">
                    <div class="team-avatar" style="background: #8b5cf6;">R</div>
                    <div class="team-info">
                        <span class="team-name">Redes y Servidores</span>
                        <span class="team-role">Estabilidad y seguridad de infraestructura</span>
                    </div>
                </div>
            </div>

            <div style="margin-top: 24px; padding: 12px; background: rgba(0,0,0,0.2); border-radius: 8px; text-align: center; font-size: 0.85rem; color: #94a3b8;">
                <i class="fa-solid fa-headset" style="margin-right: 6px;"></i> Extensión IT: <strong>4100</strong>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
