<?php
/**
 * COMECyT — Gestión de Contratos (Jurídico Administrativo)
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../config/auth.php';

verificarSesionAdmin();

$pdo     = getConnection();
$adminId = (int) ($_SESSION['admin_id'] ?? 0);
$flash   = '';
$tipoF   = '';

// Filtros
$filtroTipo    = getParam('tipo',    '');
$filtroEstatus = getParam('estatus', '');

// Query
$where = 'WHERE 1=1';
$params = [];
if ($filtroTipo)    { $where .= ' AND tipo = ?';    $params[] = $filtroTipo; }
if ($filtroEstatus) { $where .= ' AND estatus = ?'; $params[] = $filtroEstatus; }

$stmt = $pdo->prepare("SELECT * FROM ja_contratos $where ORDER BY fecha_fin ASC NULLS LAST, created_at DESC");
$stmt->execute($params);
$contratos = $stmt->fetchAll();

// Recalcular estatus vencidos automáticamente
$pdo->query("UPDATE ja_contratos SET estatus = 'vencido' WHERE estatus = 'activo' AND fecha_fin < CURRENT_DATE");

if ($_SERVER['REQUEST_METHOD'] === 'GET' && getParam('flash')) {
    $fc = getParam('flash');
    if ($fc === 'creado')   { $flash = 'Contrato registrado.';   $tipoF = 'success'; }
    if ($fc === 'editado')  { $flash = 'Contrato actualizado.';  $tipoF = 'success'; }
    if ($fc === 'eliminado'){ $flash = 'Contrato eliminado.';    $tipoF = 'success'; }
}

$pageTitle  = 'Gestión de Contratos';
$activeMenu = 'contratos';

require_once __DIR__ . '/../../includes/header_admin.php';
?>

<style>
:root { --ja-primary:#1e3a5f; --ja-accent:#B19A6D; --ja-soft:rgba(30,58,95,.05); }
.badge-activo     { background:#dcfce7;color:#16a34a; }
.badge-vencido    { background:#fee2e2;color:#dc2626; }
.badge-renovacion { background:#fef3c7;color:#d97706; }
.badge-tipo       { background:#eff6ff;color:#2563eb; }
.ja-table { width:100%; border-collapse:collapse; }
.ja-table th { text-align:left;padding:12px 16px;font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#64748b;background:#f8fafc;border-bottom:2px solid #e2e8f0; }
.ja-table td { padding:14px 16px;border-bottom:1px solid #f1f5f9;vertical-align:middle; }
.ja-table tr:last-child td { border-bottom:none; }
.ja-table tr:hover td { background:#f8fafc; }
.badge-pill { padding:3px 10px;border-radius:20px;font-size:.75rem;font-weight:600; }
.action-btn { width:30px;height:30px;border-radius:7px;border:1px solid #e2e8f0;background:transparent;cursor:pointer;font-size:.8rem;display:inline-flex;align-items:center;justify-content:center;transition:all .15s; }
.action-btn:hover { border-color:var(--ja-primary);color:var(--ja-primary);background:var(--ja-soft); }
.action-btn.danger:hover { border-color:#ef4444;color:#ef4444;background:#fef2f2; }
.modal-backdrop{display:none;position:fixed;inset:0;background:rgba(15,23,42,.7);backdrop-filter:blur(8px);z-index:2000;align-items:center;justify-content:center;padding:20px;}
.modal-backdrop.active{display:flex;}
.modal{background:#fff;width:100%;max-width:600px;border-radius:20px;box-shadow:0 25px 50px -12px rgba(0,0,0,.25);overflow:hidden;display:flex;flex-direction:column;max-height:90vh;}
.modal-header{background:linear-gradient(135deg,#1e3a5f,#142845);color:#fff;padding:1.25rem 1.5rem;display:flex;align-items:center;justify-content:space-between;}
.modal-title{margin:0;font-size:1.1rem;font-weight:800;display:flex;align-items:center;gap:10px;}
.modal-close{background:none;border:none;color:rgba(255,255,255,.8);cursor:pointer;font-size:1.4rem;}
.modal-close:hover{color:#fff;}
.modal-body{padding:1.5rem 2rem;overflow-y:auto;}
.modal-footer{padding:1.25rem 2rem;border-top:1px solid #f1f5f9;background:#fafafa;display:flex;gap:10px;justify-content:flex-end;}
.form-label{font-weight:700;color:#475569;font-size:.85rem;margin-bottom:5px;display:block;}
.form-control{border-radius:10px;border:1.5px solid #e2e8f0;background:#f8fafc;padding:9px 13px;font-family:inherit;font-size:.9rem;width:100%;box-sizing:border-box;transition:all .2s;}
.form-control:focus{border-color:#1e3a5f;box-shadow:0 0 0 3px rgba(30,58,95,.1);background:#fff;outline:none;}
.mb-14{margin-bottom:14px;}
</style>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
    <div>
        <h2 style="font-weight:800;color:#0f172a;margin:0;"><i class="fa-solid fa-file-signature" style="color:#1e3a5f;"></i> Gestión de Contratos</h2>
        <p style="color:#64748b;margin:0;">Contratos, convenios y acuerdos del área.</p>
    </div>
    <button class="btn btn-primary" onclick="abrirModalCrear()" style="background:#1e3a5f;border-color:#1e3a5f;">
        <i class="fa-solid fa-plus"></i> Nuevo Contrato
    </button>
</div>

<?php if ($flash): ?>
<div class="alert alert-<?= $tipoF ?> alert-dismissible fade show" role="alert">
    <?= esc($flash) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Filtros -->
<div style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;">
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;">
        <select name="tipo" class="form-control" style="width:160px;" onchange="this.form.submit()">
            <option value="">Todos los tipos</option>
            <?php foreach (['contrato','convenio','acuerdo'] as $t): ?>
            <option value="<?= $t ?>" <?= $filtroTipo===$t?'selected':'' ?>><?= ucfirst($t) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="estatus" class="form-control" style="width:200px;" onchange="this.form.submit()">
            <option value="">Todos los estatus</option>
            <?php foreach (['activo','vencido','en_renovacion'] as $e): ?>
            <option value="<?= $e ?>" <?= $filtroEstatus===$e?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$e)) ?></option>
            <?php endforeach; ?>
        </select>
        <?php if ($filtroTipo || $filtroEstatus): ?>
        <a href="contratos.php" class="btn" style="background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;padding:9px 14px;border-radius:10px;font-size:.88rem;">Limpiar</a>
        <?php endif; ?>
    </form>
</div>

<div style="background:#fff;border-radius:18px;border:1px solid #e2e8f0;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,.04);">
    <?php if (empty($contratos)): ?>
        <div style="padding:70px;text-align:center;color:#94a3b8;">
            <i class="fa-solid fa-file-circle-plus" style="font-size:2.8rem;opacity:.3;margin-bottom:14px;display:block;"></i>
            <p>No hay contratos registrados. ¡Añade el primero!</p>
        </div>
    <?php else: ?>
    <div style="overflow-x:auto;">
    <table class="ja-table">
        <thead><tr>
            <th>#</th><th>Título</th><th>Contraparte</th><th>Tipo</th>
            <th>Vigencia</th><th>Monto</th><th>Estatus</th><th>Acciones</th>
        </tr></thead>
        <tbody>
        <?php foreach ($contratos as $c):
            $diasRestantes = $c['fecha_fin'] ? (int)(new DateTime($c['fecha_fin']))->diff(new DateTime())->format('%r%a') : null;
            $esCls = $c['estatus'] === 'activo' ? 'badge-activo' : ($c['estatus'] === 'vencido' ? 'badge-vencido' : 'badge-renovacion');
        ?>
            <tr>
                <td style="color:#94a3b8;font-size:.78rem;font-weight:600;">#<?= str_pad($c['id'],4,'0',STR_PAD_LEFT) ?></td>
                <td style="font-weight:600;color:#0f172a;max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= esc($c['titulo']) ?></td>
                <td style="color:#475569;"><?= esc($c['contraparte'] ?: '—') ?></td>
                <td><span class="badge-pill badge-tipo"><?= ucfirst(esc($c['tipo'])) ?></span></td>
                <td style="font-size:.82rem;color:#475569;">
                    <?= $c['fecha_inicio'] ? date('d/m/Y', strtotime($c['fecha_inicio'])) : '—' ?>
                    <?php if ($c['fecha_fin']): ?><br><i class="fa-solid fa-arrow-right" style="opacity:.4;font-size:.7rem;"></i> <?= date('d/m/Y', strtotime($c['fecha_fin'])) ?><?php endif; ?>
                </td>
                <td style="font-size:.88rem;"><?= $c['monto'] ? '$'.number_format($c['monto'],2) : '—' ?></td>
                <td><span class="badge-pill <?= $esCls ?>"><?= esc(ucwords(str_replace('_',' ',$c['estatus']))) ?></span></td>
                <td>
                    <?php if (!empty($c['archivo_path'])): ?>
                    <a href="<?= BASE_URL ?>public/uploads/<?= esc($c['archivo_path']) ?>" download class="action-btn" title="Descargar" style="color:#475569;"><i class="fa-solid fa-download"></i></a>
                    <?php endif; ?>
                    <button class="action-btn" title="Editar" onclick="abrirModalEditar(<?= htmlspecialchars(json_encode($c), ENT_QUOTES) ?>)"><i class="fa-solid fa-pencil"></i></button>
                    <button class="action-btn danger" title="Eliminar" onclick="eliminarContrato(<?= $c['id'] ?>,'<?= esc($c['titulo']) ?>')"><i class="fa-solid fa-trash-can"></i></button>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>

<!-- Modal Crear -->
<div class="modal-backdrop" id="modalCrear">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fa-solid fa-plus"></i> Nuevo Contrato/Convenio</h3>
            <button class="modal-close" onclick="cerrarModal('modalCrear')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" action="api/contratos.php" enctype="multipart/form-data">
            <?= csrfField() ?><input type="hidden" name="accion" value="crear">
            <div class="modal-body">
                <div class="mb-14"><label class="form-label">Título *</label><input type="text" name="titulo" class="form-control" required></div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;" class="mb-14">
                    <div><label class="form-label">Tipo</label>
                        <select name="tipo" class="form-control">
                            <option value="contrato">Contrato</option>
                            <option value="convenio">Convenio</option>
                            <option value="acuerdo">Acuerdo</option>
                        </select>
                    </div>
                    <div><label class="form-label">Estatus</label>
                        <select name="estatus" class="form-control">
                            <option value="activo">Activo</option>
                            <option value="en_renovacion">En Renovación</option>
                            <option value="vencido">Vencido</option>
                        </select>
                    </div>
                </div>
                <div class="mb-14"><label class="form-label">Contraparte / Proveedor</label><input type="text" name="contraparte" class="form-control"></div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;" class="mb-14">
                    <div><label class="form-label">Fecha Inicio</label><input type="date" name="fecha_inicio" class="form-control"></div>
                    <div><label class="form-label">Fecha Fin</label><input type="date" name="fecha_fin" class="form-control"></div>
                </div>
                <div class="mb-14"><label class="form-label">Monto (MXN)</label><input type="number" name="monto" class="form-control" step="0.01" min="0"></div>
                <div class="mb-14"><label class="form-label">Adjunto PDF</label><input type="file" name="archivo" accept=".pdf,.doc,.docx" class="form-control"></div>
                <div><label class="form-label">Notas</label><textarea name="notas" class="form-control" rows="3"></textarea></div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="cerrarModal('modalCrear')" class="btn" style="background:#f1f5f9;color:#475569;">Cancelar</button>
                <button type="submit" class="btn btn-primary" style="background:#1e3a5f;border-color:#1e3a5f;"><i class="fa-solid fa-check"></i> Guardar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Editar -->
<div class="modal-backdrop" id="modalEditar">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fa-solid fa-pencil"></i> Editar Contrato</h3>
            <button class="modal-close" onclick="cerrarModal('modalEditar')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" action="api/contratos.php" enctype="multipart/form-data">
            <?= csrfField() ?><input type="hidden" name="accion" value="editar">
            <input type="hidden" name="id" id="ed_id">
            <div class="modal-body">
                <div class="mb-14"><label class="form-label">Título *</label><input type="text" name="titulo" id="ed_titulo" class="form-control" required></div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;" class="mb-14">
                    <div><label class="form-label">Tipo</label>
                        <select name="tipo" id="ed_tipo" class="form-control">
                            <option value="contrato">Contrato</option><option value="convenio">Convenio</option><option value="acuerdo">Acuerdo</option>
                        </select>
                    </div>
                    <div><label class="form-label">Estatus</label>
                        <select name="estatus" id="ed_estatus" class="form-control">
                            <option value="activo">Activo</option><option value="en_renovacion">En Renovación</option><option value="vencido">Vencido</option>
                        </select>
                    </div>
                </div>
                <div class="mb-14"><label class="form-label">Contraparte</label><input type="text" name="contraparte" id="ed_contra" class="form-control"></div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;" class="mb-14">
                    <div><label class="form-label">Fecha Inicio</label><input type="date" name="fecha_inicio" id="ed_fi" class="form-control"></div>
                    <div><label class="form-label">Fecha Fin</label><input type="date" name="fecha_fin" id="ed_ff" class="form-control"></div>
                </div>
                <div class="mb-14"><label class="form-label">Monto</label><input type="number" name="monto" id="ed_monto" class="form-control" step="0.01"></div>
                <div class="mb-14"><label class="form-label">Reemplazar Adjunto (opcional)</label><input type="file" name="archivo" accept=".pdf,.doc,.docx" class="form-control"></div>
                <div><label class="form-label">Notas</label><textarea name="notas" id="ed_notas" class="form-control" rows="3"></textarea></div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="cerrarModal('modalEditar')" class="btn" style="background:#f1f5f9;color:#475569;">Cancelar</button>
                <button type="submit" class="btn btn-primary" style="background:#1e3a5f;border-color:#1e3a5f;"><i class="fa-solid fa-check"></i> Actualizar</button>
            </div>
        </form>
    </div>
</div>

<form method="POST" action="api/contratos.php" id="frmEliminar" style="display:none;">
    <?= csrfField() ?><input type="hidden" name="accion" value="eliminar">
    <input type="hidden" name="id" id="del_id">
</form>

<script>
function cerrarModal(id) { document.getElementById(id).classList.remove('active'); }
function abrirModalCrear() { document.getElementById('modalCrear').classList.add('active'); }
function abrirModalEditar(c) {
    document.getElementById('ed_id').value     = c.id;
    document.getElementById('ed_titulo').value = c.titulo;
    document.getElementById('ed_contra').value = c.contraparte || '';
    document.getElementById('ed_fi').value     = c.fecha_inicio || '';
    document.getElementById('ed_ff').value     = c.fecha_fin || '';
    document.getElementById('ed_monto').value  = c.monto || '';
    document.getElementById('ed_notas').value  = c.notas || '';
    const sel = (id, val) => { const s = document.getElementById(id); [...s.options].forEach(o => o.selected = o.value === val); };
    sel('ed_tipo', c.tipo); sel('ed_estatus', c.estatus);
    document.getElementById('modalEditar').classList.add('active');
}
function eliminarContrato(id, nombre) {
    if (!confirm(`¿Eliminar "${nombre}"? Esta acción no se puede deshacer.`)) return;
    document.getElementById('del_id').value = id;
    document.getElementById('frmEliminar').submit();
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') document.querySelectorAll('.modal-backdrop.active').forEach(m => m.classList.remove('active')); });
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
