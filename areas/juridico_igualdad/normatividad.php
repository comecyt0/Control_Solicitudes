<?php
/**
 * COMECyT — API de Normatividad (Jurídico Administrativo)
 * Repositorio de documentos normativos con carpetas fijas.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../config/auth.php';

verificarSesionAdmin();

$pdo = getConnection();

// Carpetas fijas de normatividad
$carpetasNorm = [
    'leyes'          => ['label' => 'Leyes Federales y Estatales',      'icon' => 'fa-landmark',      'color' => '#1e3a5f'],
    'reglamentos'    => ['label' => 'Reglamentos Internos',              'icon' => 'fa-scroll',         'color' => '#6d28d9'],
    'lineamientos'   => ['label' => 'Lineamientos y Circulares',         'icon' => 'fa-file-lines',     'color' => '#0891b2'],
    'convenios'      => ['label' => 'Convenios y Acuerdos',              'icon' => 'fa-handshake',      'color' => '#B19A6D'],
    'protocolos'     => ['label' => 'Protocolos de Igualdad',            'icon' => 'fa-venus-mars',     'color' => '#db2777'],
];

$carpetaActiva = getParam('carpeta', 'leyes');
if (!array_key_exists($carpetaActiva, $carpetasNorm)) $carpetaActiva = 'leyes';

// Tabla dinámica ja_normatividad (creamos si no existe)
$pdo->exec("
    CREATE TABLE IF NOT EXISTS ja_normatividad (
        id          SERIAL PRIMARY KEY,
        carpeta     VARCHAR(50) NOT NULL,
        nombre      VARCHAR(255) NOT NULL,
        archivo_path VARCHAR(500),
        descripcion TEXT,
        creado_por  INT,
        created_at  TIMESTAMP DEFAULT NOW()
    )
");

$fc = getParam('flash');
$flash = '';
$tipoF = '';
if ($fc === 'subido')   { $flash = 'Documento subido.';   $tipoF = 'success'; }
if ($fc === 'eliminado'){ $flash = 'Documento eliminado.'; $tipoF = 'success'; }

$stmt = $pdo->prepare("SELECT * FROM ja_normatividad WHERE carpeta = ? ORDER BY created_at DESC");
$stmt->execute([$carpetaActiva]);
$docs = $stmt->fetchAll();

$pageTitle  = 'Normatividad';
$activeMenu = 'normatividad';

require_once __DIR__ . '/../../includes/header_admin.php';

// Handle upload (basic inline for simplicity)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_accion'])) {
    validarCsrfPost();
    // Handled inline here for normatividad to avoid extra API file
    if ($_POST['_accion'] === 'subir' && !empty($_FILES['archivo']['name'])) {
        $nombre = trim(postParam('nombre'));
        $desc   = trim(postParam('descripcion'));
        if ($nombre) {
            $ext = strtolower(pathinfo($_FILES['archivo']['name'], PATHINFO_EXTENSION));
            $allowedExts = ['pdf','doc','docx','xlsx','pptx','txt'];
            if (in_array($ext, $allowedExts)) {
                $uuid = bin2hex(random_bytes(16)) . '.' . $ext;
                $dir  = __DIR__ . '/../../public/uploads/ja_norm/';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                if (move_uploaded_file($_FILES['archivo']['tmp_name'], $dir . $uuid)) {
                    $adminId = $_SESSION['admin_id'] ?? null;
                    $pdo->prepare("INSERT INTO ja_normatividad (carpeta, nombre, archivo_path, descripcion, creado_por) VALUES (?,?,?,?,?)")
                        ->execute([$carpetaActiva, $nombre, $uuid, $desc, $adminId]);
                    header("Location: normatividad.php?carpeta=$carpetaActiva&flash=subido"); exit;
                }
            }
        }
    } elseif ($_POST['_accion'] === 'eliminar') {
        $id = (int) postParam('doc_id');
        if ($id > 0) {
            $doc = $pdo->prepare("SELECT archivo_path FROM ja_normatividad WHERE id = ?");
            $doc->execute([$id]);
            $fila = $doc->fetch();
            if ($fila && !empty($fila['archivo_path'])) {
                @unlink(__DIR__ . '/../../public/uploads/ja_norm/' . $fila['archivo_path']);
            }
            $pdo->prepare("DELETE FROM ja_normatividad WHERE id = ?")->execute([$id]);
            header("Location: normatividad.php?carpeta=$carpetaActiva&flash=eliminado"); exit;
        }
    }
}
?>

<style>
:root { --ja-primary:#1e3a5f; --ja-accent:#B19A6D; --ja-soft:rgba(30,58,95,.05); }
.norm-layout { display:grid; grid-template-columns:260px 1fr; gap:24px; margin-top:24px; align-items:start; }
.norm-sidebar { background:#fff; border-radius:16px; border:1px solid #e2e8f0; overflow:hidden; box-shadow:0 4px 16px rgba(0,0,0,.04); }
.norm-folder { display:flex; align-items:center; gap:12px; padding:14px 18px; cursor:pointer; border-bottom:1px solid #f1f5f9; text-decoration:none; color:#334155; font-weight:600; font-size:.88rem; transition:all .2s; }
.norm-folder:last-child { border-bottom:none; }
.norm-folder:hover, .norm-folder.active { background:var(--ja-soft); color:var(--ja-primary); }
.norm-folder.active { border-left:3px solid var(--ja-primary); }
.norm-folder i { width:20px; text-align:center; }
.norm-main { background:#fff; border-radius:16px; border:1px solid #e2e8f0; overflow:hidden; box-shadow:0 4px 16px rgba(0,0,0,.04); }
.norm-main-header { padding:20px 24px; border-bottom:1px solid #f1f5f9; display:flex; justify-content:space-between; align-items:center; }
.doc-item { display:flex; align-items:center; gap:14px; padding:14px 18px; border-bottom:1px solid #f1f5f9; }
.doc-item:last-child { border-bottom:none; }
.doc-item:hover { background:#f8fafc; }
.doc-icon { width:42px; height:42px; border-radius:10px; background:var(--ja-soft); display:flex; align-items:center; justify-content:center; font-size:1.3rem; color:var(--ja-primary); flex-shrink:0; }
.doc-info { flex:1; min-width:0; }
.doc-nombre { font-weight:600; color:#0f172a; font-size:.9rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.doc-meta { font-size:.78rem; color:#94a3b8; }
.modal-backdrop{display:none;position:fixed;inset:0;background:rgba(15,23,42,.7);backdrop-filter:blur(8px);z-index:2000;align-items:center;justify-content:center;padding:20px;}
.modal-backdrop.active{display:flex;}
.modal{background:#fff;width:100%;max-width:500px;border-radius:20px;overflow:hidden;display:flex;flex-direction:column;}
.modal-header{background:linear-gradient(135deg,#1e3a5f,#142845);color:#fff;padding:1.25rem 1.5rem;display:flex;align-items:center;justify-content:space-between;}
.modal-title{margin:0;font-size:1.1rem;font-weight:800;display:flex;align-items:center;gap:10px;}
.modal-close{background:none;border:none;color:rgba(255,255,255,.8);cursor:pointer;font-size:1.4rem;}
.modal-body{padding:1.5rem 2rem;}
.modal-footer{padding:1.25rem 2rem;border-top:1px solid #f1f5f9;background:#fafafa;display:flex;gap:10px;justify-content:flex-end;}
.form-label{font-weight:700;color:#475569;font-size:.85rem;margin-bottom:5px;display:block;}
.form-control{border-radius:10px;border:1.5px solid #e2e8f0;background:#f8fafc;padding:9px 13px;font-family:inherit;font-size:.9rem;width:100%;box-sizing:border-box;transition:all .2s;}
.form-control:focus{border-color:#1e3a5f;outline:none;}
.mb-14{margin-bottom:14px;}
</style>

<h2 style="font-weight:800;color:#0f172a;margin:0 0 4px;"><i class="fa-solid fa-scale-balanced" style="color:#1e3a5f;"></i> Normatividad</h2>
<p style="color:#64748b;margin:0;">Repositorio de leyes, reglamentos y lineamientos del área.</p>

<?php if ($flash): ?>
<div class="alert alert-<?= $tipoF ?> alert-dismissible fade show mt-3" role="alert">
    <?= esc($flash) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="norm-layout">
    <!-- Sidebar Carpetas -->
    <div class="norm-sidebar">
        <?php foreach ($carpetasNorm as $slug => $info): ?>
        <a href="?carpeta=<?= $slug ?>" class="norm-folder <?= $carpetaActiva === $slug ? 'active' : '' ?>">
            <i class="fa-solid <?= $info['icon'] ?>" style="color:<?= $info['color'] ?>;"></i>
            <?= esc($info['label']) ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Panel Principal -->
    <div class="norm-main">
        <div class="norm-main-header">
            <div>
                <h3 style="margin:0;font-weight:700;color:var(--ja-primary);font-size:1.1rem;">
                    <i class="fa-solid <?= $carpetasNorm[$carpetaActiva]['icon'] ?>"></i>
                    <?= esc($carpetasNorm[$carpetaActiva]['label']) ?>
                </h3>
                <div style="font-size:.78rem;color:#94a3b8;margin-top:2px;"><?= count($docs) ?> documento(s)</div>
            </div>
            <button class="btn btn-primary" onclick="document.getElementById('modalSubir').classList.add('active')" style="background:#1e3a5f;border-color:#1e3a5f;font-size:.85rem;">
                <i class="fa-solid fa-upload"></i> Subir Documento
            </button>
        </div>

        <?php if (empty($docs)): ?>
            <div style="padding:60px;text-align:center;color:#94a3b8;">
                <i class="fa-solid fa-file-circle-question" style="font-size:2.8rem;opacity:.3;margin-bottom:14px;display:block;"></i>
                <p>No hay documentos en esta categoría.</p>
            </div>
        <?php else: ?>
            <?php foreach ($docs as $d):
                $ext = pathinfo($d['archivo_path'] ?? '', PATHINFO_EXTENSION);
                $iconDoc = match($ext) { 'pdf' => 'fa-file-pdf', 'doc','docx' => 'fa-file-word', 'xlsx' => 'fa-file-excel', 'pptx' => 'fa-file-powerpoint', default => 'fa-file' };
            ?>
            <div class="doc-item">
                <div class="doc-icon"><i class="fa-solid <?= $iconDoc ?>"></i></div>
                <div class="doc-info">
                    <div class="doc-nombre"><?= esc($d['nombre']) ?></div>
                    <?php if (!empty($d['descripcion'])): ?>
                    <div class="doc-meta"><?= esc(mb_strimwidth($d['descripcion'],0,80,'...')) ?></div>
                    <?php endif; ?>
                    <div class="doc-meta"><?= date('d/m/Y', strtotime($d['created_at'])) ?></div>
                </div>
                <?php if (!empty($d['archivo_path'])): ?>
                <a href="<?= BASE_URL ?>public/uploads/ja_norm/<?= esc($d['archivo_path']) ?>" download class="btn" title="Descargar" style="background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;padding:7px 12px;border-radius:8px;font-size:.8rem;text-decoration:none;">
                    <i class="fa-solid fa-download"></i>
                </a>
                <?php endif; ?>
                <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar este documento?')">
                    <?= csrfField() ?><input type="hidden" name="_accion" value="eliminar"><input type="hidden" name="doc_id" value="<?= $d['id'] ?>">
                    <input type="hidden" name="carpeta" value="<?= $carpetaActiva ?>">
                    <button type="submit" class="btn" style="background:#fef2f2;color:#ef4444;border:1px solid #fca5a5;padding:7px 12px;border-radius:8px;font-size:.8rem;">
                        <i class="fa-solid fa-trash-can"></i>
                    </button>
                </form>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Subir -->
<div class="modal-backdrop" id="modalSubir">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fa-solid fa-upload"></i> Subir Documento</h3>
            <button class="modal-close" onclick="document.getElementById('modalSubir').classList.remove('active')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <?= csrfField() ?><input type="hidden" name="_accion" value="subir"><input type="hidden" name="carpeta_actual" value="<?= $carpetaActiva ?>">
            <div class="modal-body">
                <div class="mb-14"><label class="form-label">Nombre del Documento *</label><input type="text" name="nombre" class="form-control" required></div>
                <div class="mb-14"><label class="form-label">Descripción</label><textarea name="descripcion" class="form-control" rows="2"></textarea></div>
                <div><label class="form-label">Archivo * (PDF, Word, Excel)</label><input type="file" name="archivo" class="form-control" accept=".pdf,.doc,.docx,.xlsx,.pptx,.txt" required></div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="document.getElementById('modalSubir').classList.remove('active')" class="btn" style="background:#f1f5f9;color:#475569;">Cancelar</button>
                <button type="submit" class="btn btn-primary" style="background:#1e3a5f;border-color:#1e3a5f;"><i class="fa-solid fa-check"></i> Subir</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('keydown', e => { if (e.key === 'Escape') document.querySelectorAll('.modal-backdrop.active').forEach(m => m.classList.remove('active')); });
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
