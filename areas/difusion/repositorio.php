<?php
/**
 * COMECyT Control de Solicitudes
 * Repositorio Multimedia - Difusión
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

<div class="card reveal-up">
    <div class="card-header" style="display:flex; justify-content: space-between; align-items: center;">
        <h2 class="card-title" style="color: #e11d48;"><i class="fa-solid fa-cloud-arrow-up"></i> Gestor del Repositorio Institucional</h2>
        <button class="btn btn-primary" onclick="abrirModal('modalSubir')"><i class="fa-solid fa-plus"></i> Cargar Archivo</button>
    </div>
    
    <div class="intranet-grid" style="margin-top: 20px;">
        <?php if (!empty($archivos)): ?>
            <?php foreach ($archivos as $a): ?>
            <div class="intranet-card" style="border-top: 4px solid #e11d48;">
                <div style="font-weight: 700; color: #1e293b; margin-bottom: 8px;"><?= esc($a['titulo']) ?></div>
                <div style="font-size: 0.85rem; color: #64748b; margin-bottom: 12px;"><?= esc($a['descripcion']) ?></div>
                <div style="display: flex; gap: 10px; margin-bottom: 16px;">
                    <span class="badge" style="background:#f1f5f9; border:1px solid #cbd5e1;"><?= strtoupper(esc($a['tipo'])) ?></span>
                    <span class="badge" style="background:#fff1f2; color:#be123c; border:1px solid #fda4af;"><i class="fa-regular fa-calendar"></i> <?= date('d M', strtotime($a['fecha_creacion'])) ?></span>
                </div>
                <div style="margin-top: auto; display:flex; gap: 8px;">
                    <a href="<?= BASE_URL ?>public/uploads/multimedia/<?= esc($a['archivo_ruta']) ?>" target="_blank" class="btn btn-outline btn-sm"><i class="fa-solid fa-eye"></i></a>
                    <button class="btn btn-outline btn-sm" style="color: #ef4444; border-color: #fca5a5;" onclick="eliminarArchivo(<?= $a['id'] ?>)"><i class="fa-solid fa-trash"></i></button>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="grid-column: 1 / -1; text-align: center; padding: 40px; color:#94a3b8;">
                <i class="fa-solid fa-folder-open fa-3x" style="margin-bottom: 16px;"></i>
                <p>El repositorio está limpio. Sube tu primer archivo multimedia.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal para subir -->
<div class="modal" id="modalSubir">
    <div class="modal-dialog">
        <form id="formSubir" action="<?= BASE_URL ?>areas/difusion/api/repositorio.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="accion" value="crear">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <div class="modal-header"><h3>Subir Nuevo Archivo</h3></div>
            <div class="modal-body form-grid">
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label>Título / Nombre Público</label>
                    <input type="text" name="titulo" class="form-control" required>
                </div>
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label>Descripción corta</label>
                    <input type="text" name="descripcion" class="form-control">
                </div>
                <div class="form-group">
                    <label>Formato / Tipo</label>
                    <select name="tipo" class="form-control" required>
                        <option value="sketch">Sketch Institucional (Infografía/PNG)</option>
                        <option value="logo">Logotipo Oficial</option>
                        <option value="video">Clip de Video / Audio</option>
                    </select>
                </div>
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label>Archivo Multimedia</label>
                    <input type="file" name="archivo" class="form-control" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="cerrarModal('modalSubir')">Cancelar</button>
                <button type="submit" class="btn btn-primary" style="background:#e11d48; border:none;">Subir Archivo</button>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('formSubir').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = this.querySelector('button[type="submit"]');
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Subiendo...';
    btn.disabled = true;
    
    fetch(this.action, { method: 'POST', body: new FormData(this) })
        .then(r => r.json()).then(res => {
            if (res.ok) location.reload();
            else { alert(res.error); btn.innerHTML = 'Subir Archivo'; btn.disabled = false; }
        });
});

function eliminarArchivo(id) {
    if (!confirm('¿Seguro de eliminar este archivo permanentemente?')) return;
    const fd = new FormData();
    fd.append('accion', 'eliminar');
    fd.append('id', id);
    fd.append('csrf_token', '<?= $_SESSION['csrf_token'] ?>');
    
    fetch('<?= BASE_URL ?>areas/difusion/api/repositorio.php', { method: 'POST', body: fd })
        .then(r => r.json()).then(res => {
            if (res.ok) location.reload();
            else alert(res.error);
        });
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
