<?php
/**
 * COMECyT Control de Solicitudes
 * Repositorio Multimedia - Difusión (v2 Premium)
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../config/auth.php';

verificarSesionAdmin();
$pdo = getConnection();

$pageTitle = 'Repositorio Multimedia';
$activeMenu = 'repositorio';

// Obtener elementos existentes
$stmt = $pdo->query("SELECT r.*, a.nombre as d_creador FROM df_multimedia r LEFT JOIN administradores a ON r.creado_por = a.id ORDER BY r.fecha_creacion DESC");
$archivos = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../../includes/header_admin.php';
?>

<style>
/* === Repositorio Multimedia Premium — Difusión === */
:root {
    --dif-primary: #be123c;
    --dif-secondary: #e11d48;
    --dif-light: #fff1f2;
    --dif-dark: #881337;
}

.repo-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 16px;
    margin-bottom: 30px;
    padding: 28px 32px;
    background: linear-gradient(135deg, var(--dif-primary) 0%, var(--dif-secondary) 100%);
    border-radius: 18px;
    color: white;
    box-shadow: 0 16px 40px -8px rgba(190, 18, 60, 0.4);
    position: relative;
    overflow: hidden;
}

.repo-header::after {
    content: '';
    position: absolute;
    top: -60%; right: -5%;
    width: 260px; height: 260px;
    background: radial-gradient(circle, rgba(255,255,255,0.18) 0%, transparent 70%);
    border-radius: 50%;
}

.repo-header-left { position: relative; z-index: 2; }
.repo-header h1 { font-size: 1.9rem; font-weight: 800; margin: 0 0 6px; letter-spacing: -0.4px; }
.repo-header p { margin: 0; opacity: 0.85; font-size: 1rem; }

.btn-repo-add {
    background: white;
    color: var(--dif-secondary);
    border: none;
    border-radius: 50px;
    padding: 12px 26px;
    font-weight: 700;
    font-size: 0.95rem;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
    transition: all 0.3s ease;
    position: relative;
    z-index: 2;
    text-decoration: none;
}
.btn-repo-add:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 28px rgba(0,0,0,0.2);
}

/* Filtro de tabs */
.filter-tabs {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 24px;
}
.tab-btn {
    padding: 8px 20px;
    border-radius: 50px;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    color: #64748b;
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.25s ease;
}
.tab-btn.active, .tab-btn:hover {
    background: var(--dif-secondary);
    color: white;
    border-color: var(--dif-secondary);
}

/* Grid de tarjetas */
.media-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 22px;
    margin-bottom: 40px;
}

.media-card {
    background: #ffffff;
    border-radius: 18px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0,0,0,0.05);
    border: 1px solid #f1f5f9;
    transition: all 0.35s cubic-bezier(0.25, 0.8, 0.25, 1);
    display: flex;
    flex-direction: column;
}
.media-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 20px 40px rgba(190, 18, 60, 0.12);
    border-color: rgba(225, 29, 72, 0.15);
}

.media-card-preview {
    height: 160px;
    background: linear-gradient(135deg, #fff1f2 0%, #ffe4e6 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3.5rem;
    color: var(--dif-secondary);
    opacity: 0.7;
    position: relative;
    overflow: hidden;
}

.media-card-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    opacity: 1;
}

.media-card-type-badge {
    position: absolute;
    top: 12px;
    right: 12px;
    background: rgba(255,255,255,0.9);
    color: var(--dif-dark);
    font-size: 0.75rem;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 20px;
    border: 1px solid rgba(225, 29, 72, 0.2);
    backdrop-filter: blur(6px);
}

.media-card-body {
    padding: 20px;
    flex: 1;
    display: flex;
    flex-direction: column;
}

.media-card-title {
    font-size: 1.05rem;
    font-weight: 700;
    color: #0f172a;
    margin-bottom: 6px;
    line-height: 1.4;
}

.media-card-desc {
    font-size: 0.88rem;
    color: #64748b;
    line-height: 1.5;
    margin-bottom: 14px;
    flex: 1;
}

.media-card-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 14px;
    border-top: 1px solid #f1f5f9;
}

.media-card-date {
    font-size: 0.82rem;
    color: #94a3b8;
    display: flex;
    align-items: center;
    gap: 5px;
}

.media-card-actions { display: flex; gap: 8px; }

.btn-icon {
    width: 36px; height: 36px;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    color: #64748b;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    text-decoration: none;
    font-size: 0.9rem;
    transition: all 0.2s ease;
}
.btn-icon:hover { background: #f1f5f9; }
.btn-icon.btn-delete:hover { background: #fee2e2; color: #dc2626; border-color: #fca5a5; }

/* Estado vacío */
.empty-state {
    grid-column: 1 / -1;
    text-align: center;
    padding: 80px 20px;
    color: #94a3b8;
}
.empty-state i { font-size: 4rem; opacity: 0.4; margin-bottom: 20px; display: block; }
.empty-state p { font-size: 1.1rem; }

/* Modal Premium */
.modal-overlay {
    position: fixed; inset: 0;
    background: rgba(0,0,0,0.5);
    backdrop-filter: blur(6px);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    padding: 20px;
}
.modal-overlay.open { display: flex; animation: fadeInOverlay 0.3s ease; }
@keyframes fadeInOverlay { from { opacity: 0; } to { opacity: 1; } }

.modal-box {
    background: white;
    border-radius: 22px;
    width: 100%;
    max-width: 560px;
    box-shadow: 0 30px 60px rgba(0,0,0,0.25);
    overflow: hidden;
    animation: slideUpModal 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
}
@keyframes slideUpModal { from { transform: translateY(30px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

.modal-box-header {
    padding: 24px 28px;
    background: linear-gradient(135deg, var(--dif-primary) 0%, var(--dif-secondary) 100%);
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.modal-box-header h3 { margin: 0; font-size: 1.3rem; font-weight: 700; }
.modal-close-btn {
    background: rgba(255,255,255,0.2);
    border: none;
    color: white;
    width: 32px; height: 32px;
    border-radius: 50%;
    cursor: pointer;
    font-size: 1rem;
    display: flex; align-items: center; justify-content: center;
    transition: background 0.2s;
}
.modal-close-btn:hover { background: rgba(255,255,255,0.35); }

.modal-box-body { padding: 28px; display: flex; flex-direction: column; gap: 18px; }

.form-group-premium label {
    display: block;
    font-size: 0.88rem;
    font-weight: 600;
    color: #374151;
    margin-bottom: 7px;
}
.form-group-premium input,
.form-group-premium select,
.form-group-premium textarea {
    width: 100%;
    padding: 11px 14px;
    border: 1.5px solid #e2e8f0;
    border-radius: 10px;
    font-size: 0.95rem;
    color: #1e293b;
    background: #f9fafb;
    transition: border-color 0.2s, box-shadow 0.2s;
    box-sizing: border-box;
}
.form-group-premium input:focus,
.form-group-premium select:focus {
    outline: none;
    border-color: var(--dif-secondary);
    box-shadow: 0 0 0 3px rgba(225, 29, 72, 0.1);
}

.upload-zone {
    border: 2px dashed #e2e8f0;
    border-radius: 14px;
    padding: 28px 20px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    background: #f9fafb;
}
.upload-zone:hover { border-color: var(--dif-secondary); background: var(--dif-light); }
.upload-zone i { font-size: 2.2rem; color: var(--dif-secondary); opacity: 0.7; margin-bottom: 10px; }
.upload-zone p { font-size: 0.9rem; color: #94a3b8; margin: 0; }
.upload-zone input[type="file"] { display: none; }

.modal-box-footer {
    padding: 20px 28px;
    background: #f8fafc;
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    border-top: 1px solid #f1f5f9;
}
.btn-cancel {
    padding: 10px 24px; border-radius: 50px;
    border: 1px solid #e2e8f0; background: white;
    color: #64748b; font-weight: 600; cursor: pointer;
    transition: all 0.2s;
}
.btn-cancel:hover { background: #f1f5f9; }
.btn-submit {
    padding: 10px 28px; border-radius: 50px;
    background: var(--dif-secondary); border: none;
    color: white; font-weight: 700; cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex; align-items: center; gap: 8px;
}
.btn-submit:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(225,29,72,0.3); }
.btn-submit:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

/* Reveal Up */
.reveal-up { opacity: 0; transform: translateY(28px); transition: opacity 0.7s ease, transform 0.7s ease; }
.reveal-up.active { opacity: 1; transform: translateY(0); }

@media (max-width: 640px) {
    .repo-header { flex-direction: column; text-align: center; }
    .media-grid { grid-template-columns: 1fr; }
}
</style>

<!-- ══ HEADER REPOSITORIO ══════════════════════════════════ -->
<div class="repo-header reveal-up">
    <div class="repo-header-left">
        <h1><i class="fa-solid fa-photo-film" style="margin-right:12px;"></i>Repositorio Multimedia</h1>
        <p>Biblioteca de activos visuales, sketches y material de difusión del COMECyT.</p>
    </div>
    <button class="btn-repo-add" onclick="document.getElementById('modalSubir').classList.add('open')">
        <i class="fa-solid fa-plus"></i> Cargar Material
    </button>
</div>

<!-- ══ FILTROS ═══════════════════════════════════════════════ -->
<div class="filter-tabs reveal-up" style="transition-delay: 0.1s;">
    <button class="tab-btn active" data-filter="all">Todos</button>
    <button class="tab-btn" data-filter="sketch">Sketch</button>
    <button class="tab-btn" data-filter="logo">Logotipos</button>
    <button class="tab-btn" data-filter="video">Video / Audio</button>
</div>

<!-- ══ GRID DE ARCHIVOS ═══════════════════════════════════════ -->
<div class="media-grid" id="mediaGrid">
    <?php if (!empty($archivos)): ?>
        <?php foreach ($archivos as $a):
            $tipo = strtolower($a['tipo']);
            $icono = match($tipo) {
                'video'       => 'fa-video',
                'logo'        => 'fa-star',
                'sketch'      => 'fa-pen-nib',
                'documento'   => 'fa-file-pdf',
                default       => 'fa-file-image',
            };
            $esImagen = in_array(pathinfo($a['archivo_ruta'], PATHINFO_EXTENSION), ['jpg','jpeg','png','gif','webp']);
        ?>
        <div class="media-card reveal-up" data-tipo="<?= esc($tipo) ?>" style="transition-delay: <?= 0.05 * ($loop_i = isset($loop_i) ? $loop_i+1 : 0) ?>s;">
            <div class="media-card-preview">
                <?php if ($esImagen): ?>
                    <img src="<?= BASE_URL ?>public/uploads/multimedia/<?= esc($a['archivo_ruta']) ?>" alt="<?= esc($a['titulo']) ?>">
                <?php else: ?>
                    <i class="fa-solid <?= $icono ?>"></i>
                <?php endif; ?>
                <span class="media-card-type-badge"><?= strtoupper(esc($a['tipo'])) ?></span>
            </div>
            <div class="media-card-body">
                <div class="media-card-title"><?= esc($a['titulo']) ?></div>
                <div class="media-card-desc"><?= esc($a['descripcion'] ?: 'Sin descripción.') ?></div>
                <div class="media-card-footer">
                    <span class="media-card-date">
                        <i class="fa-regular fa-clock"></i>
                        <?= date('d M Y', strtotime($a['fecha_creacion'])) ?>
                    </span>
                    <div class="media-card-actions">
                        <a href="<?= BASE_URL ?>public/uploads/multimedia/<?= esc($a['archivo_ruta']) ?>"
                           target="_blank" class="btn-icon" title="Ver / Descargar">
                            <i class="fa-solid fa-eye"></i>
                        </a>
                        <button class="btn-icon btn-delete" title="Eliminar" onclick="eliminarArchivo(<?= $a['id'] ?>)">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="empty-state">
            <i class="fa-regular fa-folder-open"></i>
            <p>El repositorio está vacío. <br>¡Sube el primer material de difusión!</p>
        </div>
    <?php endif; ?>
</div>

<!-- ══ MODAL — CARGAR ARCHIVO ════════════════════════════════ -->
<div class="modal-overlay" id="modalSubir">
    <div class="modal-box">
        <div class="modal-box-header">
            <h3><i class="fa-solid fa-cloud-arrow-up" style="margin-right:10px;"></i>Cargar nuevo material</h3>
            <button class="modal-close-btn" onclick="document.getElementById('modalSubir').classList.remove('open')">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form id="formSubir" action="<?= BASE_URL ?>areas/difusion/api/repositorio.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="accion" value="crear">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <div class="modal-box-body">
                <div class="form-group-premium">
                    <label>Título / Nombre Público</label>
                    <input type="text" name="titulo" placeholder="Ej. Banner Semana de la Ciencia 2025" required>
                </div>
                <div class="form-group-premium">
                    <label>Descripción corta</label>
                    <input type="text" name="descripcion" placeholder="Uso, contexto o notas del material...">
                </div>
                <div class="form-group-premium">
                    <label>Formato / Tipo</label>
                    <select name="tipo" required>
                        <option value="sketch">Sketch Institucional (Infografía / PNG)</option>
                        <option value="logo">Logotipo Oficial</option>
                        <option value="video">Clip de Video / Audio</option>
                        <option value="documento">Documento (PDF / Word)</option>
                    </select>
                </div>
                <div class="form-group-premium">
                    <label>Archivo multimedia</label>
                    <div class="upload-zone" onclick="document.getElementById('fileInput').click()">
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                        <p id="uploadLabel">Haz clic para seleccionar un archivo</p>
                        <input id="fileInput" type="file" name="archivo" required
                               onchange="document.getElementById('uploadLabel').textContent = this.files[0]?.name ?? 'Selecciona un archivo'">
                    </div>
                </div>
            </div>
            <div class="modal-box-footer">
                <button type="button" class="btn-cancel" onclick="document.getElementById('modalSubir').classList.remove('open')">Cancelar</button>
                <button type="submit" class="btn-submit" id="btnSubir">
                    <i class="fa-solid fa-cloud-arrow-up"></i> Subir Material
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// — Reveal on Scroll
document.addEventListener("DOMContentLoaded", () => {
    const reveals = document.querySelectorAll(".reveal-up");
    const obs = new IntersectionObserver((entries, ob) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) { entry.target.classList.add("active"); ob.unobserve(entry.target); }
        });
    }, { root: null, rootMargin: "0px", threshold: 0.08 });
    reveals.forEach(el => obs.observe(el));
});

// — Filtros de tabs
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        const filtro = btn.dataset.filter;
        document.querySelectorAll('.media-card').forEach(card => {
            card.style.display = (filtro === 'all' || card.dataset.tipo === filtro) ? 'flex' : 'none';
        });
    });
});

// — Subida de archivo
document.getElementById('formSubir').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSubir');
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Subiendo...';
    btn.disabled = true;
    fetch(this.action, { method: 'POST', body: new FormData(this) })
        .then(r => {
            if (!r.ok) throw new Error('Error de red o servidor (HTTP ' + r.status + ')');
            return r.json();
        })
        .then(res => {
            if (res.ok) location.reload();
            else { 
                alert(res.error || 'Error al subir el archivo.'); 
                btn.innerHTML = '<i class="fa-solid fa-cloud-arrow-up"></i> Subir Material'; 
                btn.disabled = false; 
            }
        })
        .catch(err => {
            console.error(err);
            alert('Error crítico: ' + err.message + '. Intenta de nuevo o contacta a soporte.');
            btn.innerHTML = '<i class="fa-solid fa-cloud-arrow-up"></i> Subir Material'; 
            btn.disabled = false;
        });
});

// — Eliminar archivo
function eliminarArchivo(id) {
    if (!confirm('¿Seguro que deseas eliminar este archivo permanentemente?')) return;
    const fd = new FormData();
    fd.append('accion', 'eliminar');
    fd.append('id', id);
    fd.append('csrf_token', '<?= $_SESSION['csrf_token'] ?>');
    fetch('<?= BASE_URL ?>areas/difusion/api/repositorio.php', { method: 'POST', body: fd })
        .then(r => r.json()).then(res => {
            if (res.ok) location.reload();
            else alert(res.error || 'No se pudo eliminar el archivo.');
        });
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
