<?php
/**
 * COMECyT Control de Solicitudes
 * Galería Institucional (Vista Pública del Repositorio de Difusión)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/auth.php';

verificarSesionUsuario();

$pdo = getConnection();

// Obtener elementos filtrados
$filtroTipo = $_GET['tipo'] ?? '';
$where = "";
if ($filtroTipo) {
    if ($filtroTipo === 'multimedia') {
        $where = "WHERE tipo = 'video'";
    } elseif ($filtroTipo === 'imagen') {
        $where = "WHERE tipo IN ('sketch', 'logo', 'banner')";
    }
}
$stmt = $pdo->query("SELECT * FROM df_multimedia $where ORDER BY fecha_creacion DESC");
$archivos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Galería Institucional COMECyT';
$activeMenu = 'galeria';
$hideSidebar = false;

$extraHead = '<style>
.galeria-hero {
    background: linear-gradient(to right, rgba(225, 29, 72, 0.05), rgba(190, 18, 60, 0.1));
    border-radius: 20px;
    padding: 3rem 2rem;
    text-align: center;
    border: 1px solid rgba(225, 29, 72, 0.1);
    margin-bottom: 2rem;
}
.galeria-hero h1 { color: #9f1239; font-weight: 800; font-size: 2.2rem; margin-bottom: 1rem; }
.filtros-galeria { display: flex; justify-content: center; gap: 1rem; margin-bottom: 2rem; }
.btn-filtro { border-radius: 50px; padding: 0.5rem 1.5rem; font-weight: 600; text-decoration: none; border: 1px solid transparent; transition: all 0.3s; }
.btn-filtro.active { background: #e11d48; color: white; box-shadow: 0 4px 15px rgba(225, 29, 72, 0.3); }
.btn-filtro:not(.active) { background: #f8fafc; color: #475569; border-color: #e2e8f0; }
.btn-filtro:hover:not(.active) { background: #f1f5f9; border-color: #cbd5e1; }

.masonry-grid {
    column-count: 3;
    column-gap: 1.5rem;
}
@media (max-width: 1024px) { .masonry-grid { column-count: 2; } }
@media (max-width: 640px) { .masonry-grid { column-count: 1; } }

.masonry-item {
    break-inside: avoid;
    margin-bottom: 1.5rem;
    background: #fff;
    border-radius: 16px;
    border: 1px solid #f1f5f9;
    box-shadow: 0 4px 6px rgba(0,0,0,0.02);
    overflow: hidden;
    transition: transform 0.3s, box-shadow 0.3s;
}
.masonry-item:hover { transform: translateY(-5px); box-shadow: 0 12px 24px rgba(0,0,0,0.06); }
.masonry-img { width: 100%; display: block; object-fit: cover; }
.masonry-body { padding: 1.2rem; }
.masonry-title { font-weight: 700; color: #1e293b; margin-bottom: 0.5rem; }
.masonry-desc { font-size: 0.85rem; color: #64748b; line-height: 1.5; margin-bottom: 1rem; }
.masonry-meta { display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #f1f5f9; padding-top: 1rem; }
.badge-tipo { background: #fff1f2; color: #e11d48; font-size: 0.7rem; padding: 0.2rem 0.6rem; border-radius: 4px; font-weight: 700; }
</style>';

require_once __DIR__ . '/../includes/header_user.php';
?>

<div class="galeria-hero reveal-up">
    <h1>Galería de Imagen Institucional</h1>
    <p style="color: #475569; font-size:1.1rem; max-width:600px; margin:0 auto;">Explora y descarga material oficial, manuales de identidad, logotipos e infografías aprobadas por el Departamento de Difusión.</p>
</div>

<div class="filtros-galeria reveal-up" style="transition-delay:0.1s;">
    <a href="?tipo=" class="btn-filtro <?= !$filtroTipo ? 'active' : '' ?>">Todo</a>
    <a href="?tipo=imagen" class="btn-filtro <?= $filtroTipo === 'imagen' ? 'active' : '' ?>">Imágenes y Diseños</a>
    <a href="?tipo=multimedia" class="btn-filtro <?= $filtroTipo === 'multimedia' ? 'active' : '' ?>">Videos / Multimedia</a>
</div>

<?php if (!empty($archivos)): ?>
<div class="masonry-grid reveal-up" style="transition-delay:0.2s;">
    <?php foreach ($archivos as $file): 
        $ruta = BASE_URL . 'public/uploads/multimedia/' . esc($file['archivo_ruta']);
        $ext = strtolower(pathinfo($file['archivo_ruta'], PATHINFO_EXTENSION));
        $esImg = in_array($ext, ['jpg','jpeg','png','webp','gif']);
    ?>
    <div class="masonry-item">
        <?php if ($esImg): ?>
        <img src="<?= $ruta ?>" class="masonry-img" alt="Imagen Institucional">
        <?php elseif ($ext === 'mp4'): ?>
        <video controls class="masonry-img" style="max-height:250px;"><source src="<?= $ruta ?>" type="video/mp4"></video>
        <?php else: ?>
        <div style="background:#f8fafc; height:150px; display:flex; align-items:center; justify-content:center; color:#94a3b8; font-size:3rem;"><i class="fa-solid fa-file-lines"></i></div>
        <?php endif; ?>
        
        <div class="masonry-body">
            <div class="masonry-title"><?= esc($file['titulo']) ?></div>
            <div class="masonry-desc"><?= esc($file['descripcion']) ?></div>
            <div class="masonry-meta">
                <span class="badge-tipo"><?= strtoupper($file['tipo']) ?></span>
                <a href="<?= $ruta ?>" download class="btn btn-outline btn-sm" style="color:#0ea5e9; border-color:#bae6fd;"><i class="fa-solid fa-download"></i> Bajar</a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div style="text-align:center; padding:4rem 0; color:#cbd5e1;" class="reveal-up">
    <i class="fa-regular fa-images fa-4x" style="margin-bottom:1rem;"></i>
    <h3>No hay material disponible</h3>
    <p>El departamento de difusión aún no ha publicado contenido.</p>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
