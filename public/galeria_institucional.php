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
$where = "WHERE visible_publico = true";

if ($filtroTipo) {
    if ($filtroTipo === 'multimedia') {
        $where .= " AND tipo = 'video'";
    } elseif ($filtroTipo === 'imagen') {
        $where .= " AND tipo IN ('sketch', 'logo', 'banner')";
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

/* Thumbnail effect */
.masonry-preview-container {
    height: 220px;
    overflow: hidden;
    position: relative;
    background: #f1f5f9;
    cursor: zoom-in;
}
.masonry-img { 
    width: 100%; 
    height: 100%; 
    display: block; 
    object-fit: cover; 
    transition: transform 0.5s;
    user-select: none;
    pointer-events: none;
}
.masonry-item:hover .masonry-img { transform: scale(1.05); }

.preview-overlay-btn {
    position: absolute;
    inset: 0;
    background: rgba(0,0,0,0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s;
    color: white;
    font-size: 1.1rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
}
.masonry-preview-container:hover .preview-overlay-btn { opacity: 1; }

.masonry-body { padding: 1.2rem; }
.masonry-title { font-weight: 700; color: #1e293b; margin-bottom: 0.5rem; }
.masonry-desc { font-size: 0.85rem; color: #64748b; line-height: 1.5; margin-bottom: 1rem; }
.masonry-meta { display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #f1f5f9; padding-top: 1rem; }
.badge-tipo { background: #fff1f2; color: #e11d48; font-size: 0.7rem; padding: 0.2rem 0.6rem; border-radius: 4px; font-weight: 700; }

/* Modal Preview */
.modal-preview {
    position: fixed; inset: 0;
    background: rgba(0,0,0,0.9);
    backdrop-filter: blur(10px);
    z-index: 9999;
    display: none;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 40px;
}
.modal-preview.active { display: flex; animation: fadeIn 0.3s ease; }

.modal-preview-close {
    position: absolute; top: 20px; right: 20px;
    background: rgba(255,255,255,0.1);
    color: white; border: none; width: 44px; height: 44px;
    border-radius: 50%; cursor: pointer; font-size: 1.5rem;
    display: flex; align-items: center; justify-content: center;
    transition: background 0.2s;
}
.modal-preview-close:hover { background: rgba(255,255,255,0.25); }

.modal-preview-content {
    max-width: 90vw; max-height: 80vh;
    overflow: auto; background: #000;
    border-radius: 12px; box-shadow: 0 20px 50px rgba(0,0,0,0.5);
    position: relative;
}
.modal-preview-content img, .modal-preview-content video { 
    display: block; max-width: 100%; height: auto; 
    margin: 0 auto;
    oncontextmenu: return false;
}

.modal-preview-info {
    margin-top: 20px; text-align: center; color: white; max-width: 600px;
}
.modal-preview-info h3 { font-size: 1.4rem; margin-bottom: 8px; }
.modal-preview-info p { opacity: 0.7; font-size: 0.95rem; line-height: 1.5; }

@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
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
        <div class="masonry-preview-container" onclick="abrirPreview('<?= $ruta ?>', '<?= $file['tipo'] ?>', '<?= esc(addslashes($file['titulo'])) ?>', '<?= esc(addslashes($file['descripcion'])) ?>')">
            <?php if ($esImg): ?>
                <img src="<?= $ruta ?>" class="masonry-img" alt="Vista previa" oncontextmenu="return false;">
            <?php elseif ($ext === 'mp4'): ?>
                <div style="background:#000; width:100%; height:100%; display:flex; align-items:center; justify-content:center; color:#fff; font-size:3rem;"><i class="fa-solid fa-play"></i></div>
            <?php else: ?>
                <div style="background:#f8fafc; width:100%; height:100%; display:flex; align-items:center; justify-content:center; color:#94a3b8; font-size:3rem;"><i class="fa-solid fa-file-lines"></i></div>
            <?php endif; ?>
            <div class="preview-overlay-btn"><i class="fa-solid fa-expand" style="margin-right:8px;"></i> Vista Previa</div>
        </div>
        
        <div class="masonry-body">
            <div class="masonry-title"><?= esc($file['titulo']) ?></div>
            <div class="masonry-desc"><?= esc($file['descripcion']) ?></div>
            <div class="masonry-meta">
                <span class="badge-tipo"><?= strtoupper($file['tipo']) ?></span>
                <?php if ($file['permite_descarga']): ?>
                    <a href="<?= $ruta ?>" download class="btn btn-outline btn-sm" style="color:#0ea5e9; border-color:#bae6fd;"><i class="fa-solid fa-download"></i> Bajar</a>
                <?php else: ?>
                    <span style="font-size: 0.75rem; color: #94a3b8;"><i class="fa-solid fa-lock" style="margin-right:4px;"></i> Solo lectura</span>
                <?php endif; ?>
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

<!-- Modal Preview Universal -->
<div class="modal-preview" id="modalPreview" oncontextmenu="return false;">
    <button class="modal-preview-close" onclick="cerrarPreview()"><i class="fa-solid fa-xmark"></i></button>
    <div class="modal-preview-content" id="previewContent">
        <!-- El contenido se inyecta por JS -->
    </div>
    <div class="modal-preview-info">
        <h3 id="previewTitle"></h3>
        <p id="previewDesc"></p>
    </div>
</div>

<script>
function abrirPreview(url, tipo, titulo, desc) {
    const container = document.getElementById('previewContent');
    const titleEl = document.getElementById('previewTitle');
    const descEl = document.getElementById('previewDesc');
    
    titleEl.textContent = titulo;
    descEl.textContent = desc || 'Sin descripción adicional.';
    container.innerHTML = '';
    
    if (tipo === 'video') {
        container.innerHTML = `<video controls autoplay style="width:100%; max-height:80vh;"><source src="${url}" type="video/mp4"></video>`;
    } else if (tipo === 'documento') {
        container.innerHTML = `<iframe src="${url}" style="width:80vw; height:80vh; border:none; background:white;"></iframe>`;
    } else {
        container.innerHTML = `<img src="${url}" style="width:auto; max-width:100%; max-height:80vh; border-radius:4px;" oncontextmenu="return false;">`;
    }
    
    document.getElementById('modalPreview').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function cerrarPreview() {
    document.getElementById('modalPreview').classList.remove('active');
    document.getElementById('previewContent').innerHTML = ''; // Limpiar para detener videos
    document.body.style.overflow = 'auto';
}

// Bloqueo de click derecho nativo para toda la aplicación si se desea un nivel más alto, 
// o solo para la galería (como está ahora inyectado en el modal y previews).
document.addEventListener('contextmenu', function(e) {
    if (e.target.tagName === 'IMG' || e.target.classList.contains('masonry-preview-container')) {
        e.preventDefault();
    }
}, false);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
