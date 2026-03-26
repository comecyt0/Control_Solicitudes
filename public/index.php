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

// El rol Servicio Social es el único que no debería permanecer en el Intranet Hub, lo forzamos a su panel
if (!empty($_SESSION['ss_id'])) {
    header("Location: " . BASE_URL . "servicio_social/dashboard.php");
    exit;
}

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

$hideSidebar = true;
$pageTitle  = 'Intranet';
$extraHead  = '
<style>
/* Estilos para el Dashboard Intranet - Rediseño Profesional */
.intranet-hero {
    background: linear-gradient(135deg, rgba(102, 35, 49, 0.03) 0%, rgba(139, 47, 66, 0.08) 100%);
    border-radius: 20px;
    padding: 40px;
    margin-bottom: 40px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.04);
    border: 1px solid rgba(102, 35, 49, 0.05);
}
.intranet-hero-content h1 {
    font-size: 2.2rem;
    font-weight: 700;
    color: var(--color-primary);
    margin-bottom: 12px;
    line-height: 1.2;
}
.intranet-hero-content p {
    font-size: 1.1rem;
    color: #475569;
    max-width: 550px;
}
.intranet-hero-icon {
    font-size: 5.5rem;
    color: rgba(102, 35, 49, 0.1);
}

@media (max-width: 768px) {
    .intranet-hero {
        flex-direction: column-reverse;
        text-align: center;
        padding: 24px;
        gap: 20px;
    }
    .intranet-hero-content h1 {
        font-size: 1.6rem;
    }
    .intranet-hero-icon {
        font-size: 4rem;
        margin-bottom: 10px;
    }
}

.intranet-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 24px;
    margin-bottom: 40px;
}

.intranet-card {
    background: #ffffff;
    border-radius: 16px;
    padding: 24px;
    text-decoration: none;
    color: inherit;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    border: 1px solid #f1f5f9;
    box-shadow: 0 4px 15px rgba(0,0,0,0.03);
    transition: transform 0.4s cubic-bezier(0.165, 0.84, 0.44, 1), box-shadow 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
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
    transition: transform 0.4s ease;
}

.intranet-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 20px 40px rgba(0,0,0,0.08);
    border-color: #cbd5e1;
}

.intranet-card:hover::before {
    transform: scaleX(1);
}

.intranet-card-icon {
    width: 52px;
    height: 52px;
    border-radius: 14px;
    background: rgba(102, 35, 49, 0.08);
    color: var(--color-primary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.6rem;
    margin-bottom: 20px;
    transition: background 0.4s ease, color 0.4s ease, transform 0.4s ease;
}

.intranet-card:hover .intranet-card-icon {
    background: var(--color-primary);
    color: #fff;
    transform: scale(1.05);
}

.intranet-card-title {
    font-size: 1.15rem;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 8px;
}
.intranet-card-desc {
    font-size: 0.9rem;
    color: #64748b;
    line-height: 1.5;
}

/* Modificadores de color para tarjetas */
.card-solicitud::before { background: #0369a1; }
.card-solicitud .intranet-card-icon { background: rgba(3, 105, 161, 0.1); color: #0369a1; }
.card-solicitud:hover .intranet-card-icon { background: #0369a1; color: #fff; }

.card-historial::before { background: #6b21a8; }
.card-historial .intranet-card-icon { background: rgba(107, 33, 168, 0.1); color: #6b21a8; }
.card-historial:hover .intranet-card-icon { background: #6b21a8; color: #fff; }

.card-equipos::before { background: #0f766e; }
.card-equipos .intranet-card-icon { background: rgba(15, 118, 110, 0.1); color: #0f766e; }
.card-equipos:hover .intranet-card-icon { background: #0f766e; color: #fff; }

.card-calendario::before { background: #b45309; }
.card-calendario .intranet-card-icon { background: rgba(180, 83, 9, 0.1); color: #b45309; }
.card-calendario:hover .intranet-card-icon { background: #b45309; color: #fff; }

.card-difusion::before { background: #e11d48; }
.card-difusion .intranet-card-icon { background: rgba(225, 29, 72, 0.1); color: #e11d48; }
.card-difusion:hover .intranet-card-icon { background: #e11d48; color: #fff; }

.panels-grid {
    display: grid;
    grid-template-columns: 1.7fr 1fr;
    gap: 30px;
}
@media (max-width: 1024px) {
    .panels-grid { grid-template-columns: 1fr; }
}

.anuncio-item {
    background: #ffffff;
    border-radius: 14px;
    border: 1px solid #e2e8f0;
    margin-bottom: 20px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.02);
    overflow: hidden;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}
.anuncio-item:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 24px rgba(0,0,0,0.05);
}
.anuncio-item:last-child { margin-bottom: 0; }
.anuncio-banner {
    width: 100%;
    height: 200px;
    object-fit: cover;
    display: block;
    border-bottom: 1px solid #f1f5f9;
}
.anuncio-body {
    padding: 20px;
}
.anuncio-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; }
.anuncio-titulo { font-weight: 700; color: #1e293b; font-size: 1.1rem; display: flex; align-items: center; gap: 8px; margin-bottom: 0; }
.anuncio-fecha { font-size: 0.8rem; color: #94a3b8; background: #f8fafc; padding: 4px 8px; border-radius: 6px; }
.anuncio-contenido { font-size: 0.95rem; color: #475569; line-height: 1.6; }

/* Panel Sistemas - Rediseño Claro e Institucional */
.sistemas-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    box-shadow: 0 10px 30px rgba(0,0,0,0.03);
}
.sistemas-card .card-header {
    border-bottom: 1px solid #f1f5f9;
    padding-bottom: 16px;
}
.sistemas-card .card-title {
    color: var(--color-primary);
    font-weight: 700;
}
.sistemas-team-list { margin-top: 16px; display: flex; flex-direction: column; gap: 16px; }
.team-member { display: flex; align-items: center; gap: 16px; padding: 12px; background: #f8fafc; border: 1px solid #f1f5f9; border-radius: 12px; transition: background 0.3s ease; }
.team-member:hover { background: #f1f5f9; }
.team-avatar { width: 44px; height: 44px; border-radius: 12px; background: rgba(102, 35, 49, 0.1); display: flex; align-items: center; justify-content: center; font-weight: 700; color: var(--color-primary); font-size: 1.2rem; }
.team-info { display: flex; flex-direction: column; }
.team-name { font-weight: 700; font-size: 0.95rem; color: #1e293b; }
.team-role { font-size: 0.8rem; color: #64748b; }

/* Clases para animación de scroll */
.reveal-up {
    opacity: 0;
    transform: translateY(30px);
    transition: opacity 0.8s cubic-bezier(0.165, 0.84, 0.44, 1), transform 0.8s cubic-bezier(0.165, 0.84, 0.44, 1);
}
.reveal-up.active {
    opacity: 1;
    transform: translateY(0);
}
</style>
';

require_once __DIR__ . '/../includes/header_user.php';
?>

<div class="intranet-hero reveal-up">
    <div class="intranet-hero-content">
        <h1>¡Bienvenido/a a la Intranet, <?= esc($usuarioNombre) ?>!</h1>
        <p>Dashboard central de servicios e información institucional del COMECyT. Gestiona tus requerimientos técnicos, explora galerías multimedia y mantente enterado de los últimos comunicados oficiales.</p>
        
        <div style="margin-top: 24px; display: flex; gap: 12px; flex-wrap: wrap;">
            <?php if (!empty($_SESSION['admin_id']) || !empty($_SESSION['ss_id'])): ?>
            <a href="<?= BASE_URL ?>public/router.php" class="btn btn-primary btn-lg" style="box-shadow: 0 4px 15px rgba(102, 35, 49, 0.25); border-radius: 12px; padding: 14px 28px; font-weight: 700;">
                <i class="fa-solid fa-rocket" style="margin-right: 8px;"></i> Mi Panel Administrativo
            </a>
            <?php endif; ?>
            <a href="<?= BASE_URL ?>public/calendario.php?solicitar=1" class="btn btn-outline btn-lg" style="border-radius: 12px; padding: 14px 28px; font-weight: 700; background: #fff;">
                <i class="fa-solid fa-calendar-plus" style="margin-right: 8px; color: #b45309;"></i> Agendar Espacio
            </a>
        </div>
    </div>
    <div class="intranet-hero-icon">
        <i class="fa-solid fa-shapes"></i>
    </div>
</div>

<?php if (!empty($_SESSION['admin_id'])): ?>
<div class="card reveal-up" style="background: #f8fafc; border: 1px dashed #cbd5e1; margin-bottom: 40px; padding: 20px;">
    <h3 style="margin-bottom: 15px; color: #475569;"><i class="fa-solid fa-vial"></i> Modo Desarrollador: Ver otras Áreas</h3>
    <p style="font-size: 0.9rem; color: #64748b; margin-bottom: 15px;">Selecciona un área del sistema simulando que perteneces a ella y esquivando el cortafuegos. (Exclusivo para pruebas).</p>
    <form action="<?= BASE_URL ?>public/router.php" method="GET" style="display: flex; gap: 15px; max-width: 500px;">
        <select name="demo_area" class="form-control">
            <?php 
            $stmtAreasO = $pdo->query("SELECT cve_area, des_area FROM cat_areas ORDER BY cve_area ASC");
            while ($a = $stmtAreasO->fetch(PDO::FETCH_ASSOC)):
            ?>
            <option value="<?= $a['cve_area'] ?>"><?= esc($a['des_area']) ?></option>
            <?php endwhile; ?>
        </select>
        <button type="submit" class="btn btn-outline" style="white-space: nowrap;"><i class="fa-solid fa-arrow-right-to-bracket"></i> Visitar</button>
    </form>
</div>
<?php endif; ?>

<div class="intranet-grid">
    <a href="<?= BASE_URL ?>public/nueva_solicitud.php" class="intranet-card card-solicitud reveal-up" style="transition-delay: 0.1s;">
        <div class="intranet-card-icon">
            <i class="fa-solid fa-plus-circle"></i>
        </div>
        <div class="intranet-card-title">Registrar Solicitud</div>
        <div class="intranet-card-desc">Crea un nuevo ticket de soporte, mantenimiento, atención o sistema.</div>
    </a>

    <a href="<?= BASE_URL ?>public/historial.php" class="intranet-card card-historial reveal-up" style="transition-delay: 0.2s;">
        <div class="intranet-card-icon">
            <i class="fa-solid fa-clock-rotate-left"></i>
        </div>
        <div class="intranet-card-title">Mi Historial</div>
        <div class="intranet-card-desc">Consulta el estatus y progreso de todas tus solicitudes previas.</div>
    </a>

    <a href="<?= BASE_URL ?>public/equipos_usuario.php" class="intranet-card card-equipos reveal-up" style="transition-delay: 0.3s;">
        <div class="intranet-card-icon">
            <i class="fa-solid fa-laptop"></i>
        </div>
        <div class="intranet-card-title">Mis Equipos</div>
        <div class="intranet-card-desc">Revisa el inventario de equipo de cómputo que tienes asignado actualmente.</div>
    </a>

    <a href="<?= BASE_URL ?>public/calendario.php" class="intranet-card card-calendario reveal-up" style="transition-delay: 0.4s;">
        <div class="intranet-card-icon">
            <i class="fa-solid fa-calendar-days"></i>
        </div>
        <div class="intranet-card-title">Calendario</div>
        <div class="intranet-card-desc">Consulta eventos públicos o solicita agendar espacios institucionales de manera interactiva.</div>
    </a>
    
    <a href="<?= BASE_URL ?>public/galeria_institucional.php" class="intranet-card card-difusion reveal-up" style="transition-delay: 0.5s;">
        <div class="intranet-card-icon">
            <i class="fa-solid fa-photo-film"></i>
        </div>
        <div class="intranet-card-title">Galería Institucional</div>
        <div class="intranet-card-desc">Repositorio oficial de infografías, sketches y material publicitario (Departamento de Difusión).</div>
    </a>
</div>

<div class="panels-grid">
    <!-- Panel de Anuncios -->
    <div class="card reveal-up" style="border:none; box-shadow:none; background:transparent;">
        <div style="display:flex; align-items:center; gap: 12px; margin-bottom: 24px;">
            <i class="fa-solid fa-bullhorn" style="font-size: 1.5rem; color: var(--color-primary);"></i>
            <h2 style="font-size: 1.4rem; font-weight: 700; color: #1e293b; margin: 0;">Anuncios y Comunicados</h2>
        </div>
        <div>
            <?php if (empty($anuncios)): ?>
                <div style="text-align: center; padding: 60px 20px; color: #94a3b8; background: #fff; border-radius: 16px; border: 1px dashed #cbd5e1;">
                    <i class="fa-regular fa-bell-slash" style="font-size: 2.5rem; margin-bottom: 16px; display: block;"></i>
                    <p style="font-size: 1.1rem;">No hay avisos recientes por parte del departamento.</p>
                </div>
            <?php else: ?>
                <?php foreach ($anuncios as $a): 
                    $icon = 'fa-circle-info';
                    $color = '#0ea5e9'; // azul claro default
                    if ($a['tipo'] === 'warning') { $icon = 'fa-triangle-exclamation'; $color = '#f59e0b'; }
                    elseif ($a['tipo'] === 'success') { $icon = 'fa-check-circle'; $color = '#10b981'; }
                    elseif ($a['tipo'] === 'urgent') { $icon = 'fa-bell'; $color = '#ef4444'; }
                ?>
                <div class="anuncio-item">
                    <?php if (!empty($a['banner_url'])): ?>
                        <img src="<?= BASE_URL . 'public/uploads/anuncios/' . esc($a['banner_url']) ?>" alt="Banner Anuncio" class="anuncio-banner">
                    <?php endif; ?>
                    <div class="anuncio-body">
                        <div class="anuncio-header">
                            <h3 class="anuncio-titulo" style="color: <?= $color ?>;">
                                <i class="fa-solid <?= $icon ?>"></i> <?= esc($a['titulo']) ?>
                            </h3>
                            <div class="anuncio-fecha"><i class="fa-regular fa-clock"></i> <?= date('d/m/Y', strtotime($a['fecha_creacion'])) ?></div>
                        </div>
                        <div class="anuncio-contenido">
                            <?= nl2br(esc($a['contenido'])) ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Panel Equipo de Sistemas -->
    <div class="card sistemas-card reveal-up" style="transition-delay: 0.2s;">
        <div class="card-header">
            <h2 class="card-title">
                <i class="fa-solid fa-shield-halved"></i> Equipo de TI y Sistemas
            </h2>
        </div>
        <div class="card-body" style="padding-top: 16px;">
            <p style="font-size: 0.95rem; color: #475569; line-height: 1.6; margin-bottom: 24px;">
                El equipo de Tecnologías de la Información está aquí para garantizar el óptimo funcionamiento de la infraestructura y brindar un soporte integral a toda la comunidad COMECyT.
            </p>
            
            <div class="sistemas-team-list">
                <div class="team-member">
                    <div class="team-avatar"><i class="fa-solid fa-wrench"></i></div>
                    <div class="team-info">
                        <span class="team-name">Soporte Técnico</span>
                        <span class="team-role">Mantenimiento y atención de hardware activo</span>
                    </div>
                </div>
                <div class="team-member">
                    <div class="team-avatar" style="color: #0369a1; background: rgba(3, 105, 161, 0.1);"><i class="fa-solid fa-code"></i></div>
                    <div class="team-info">
                        <span class="team-name">Desarrollo Web</span>
                        <span class="team-role">Creación y mejora de sistemas institucionales</span>
                    </div>
                </div>
                <div class="team-member">
                    <div class="team-avatar" style="color: #6b21a8; background: rgba(107, 33, 168, 0.1);"><i class="fa-solid fa-network-wired"></i></div>
                    <div class="team-info">
                        <span class="team-name">Redes y Servidores</span>
                        <span class="team-role">Estabilidad, conectividad y seguridad de red</span>
                    </div>
                </div>
            </div>

            <div style="margin-top: 32px; padding: 16px; background: rgba(102, 35, 49, 0.05); border: 1px dashed rgba(102, 35, 49, 0.2); border-radius: 12px; text-align: center; font-size: 0.95rem; color: var(--color-primary);">
                <i class="fa-solid fa-headset" style="margin-right: 8px;"></i> Extensión de Ayuda IT: <strong>314 y/o 114</strong>
            </div>
        </div>
    </div>
</div>

<script>
// Animaciones al hacer scroll (Reveal Up)
document.addEventListener("DOMContentLoaded", () => {
    const reveals = document.querySelectorAll(".reveal-up");

    const revealOnScroll = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add("active");
                observer.unobserve(entry.target);
            }
        });
    }, {
        root: null,
        rootMargin: "0px 0px 0px 0px",
        threshold: 0.1
    });

    reveals.forEach(el => revealOnScroll.observe(el));
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
