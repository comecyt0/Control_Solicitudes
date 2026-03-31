<?php
/**
 * COMECyT — Adquisiciones (Jurídico Administrativo)
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../config/auth.php';

verificarSesionAdmin();

$pdo     = getConnection();
$flash   = '';
$tipoF   = '';

$filtroEst = getParam('estatus', '');
$where  = $filtroEst ? 'WHERE estatus = ?' : '';
$params = $filtroEst ? [$filtroEst] : [];

$stmt = $pdo->prepare("SELECT * FROM ja_adquisiciones $where ORDER BY created_at DESC");
$stmt->execute($params);
$adqs = $stmt->fetchAll();

$fc = getParam('flash');
if ($fc === 'creado')    { $flash = 'Adquisición registrada.';   $tipoF = 'success'; }
if ($fc === 'editado')   { $flash = 'Adquisición actualizada.';  $tipoF = 'success'; }
if ($fc === 'eliminado') { $flash = 'Adquisición eliminada.';    $tipoF = 'success'; }

$estatuses = ['en_proceso','completado','cancelado'];
$pageTitle  = 'Adquisiciones';
$activeMenu = 'adquisiciones';

require_once __DIR__ . '/../../includes/header_admin.php';
?>

<style>
:root { --ja-primary:#1e3a5f; --ja-accent:#B19A6D; --ja-soft:rgba(30,58,95,.05); }
.badge-en_proceso { background:#fef3c7;color:#d97706; }
.badge-completado { background:#dcfce7;color:#16a34a; }
.badge-cancelado  { background:#fee2e2;color:#dc2626; }
.badge-pill { padding:3px 10px;border-radius:20px;font-size:.75rem;font-weight:600; }
.ja-table { width:100%; border-collapse:collapse; }
.ja-table th { text-align:left;padding:12px 16px;font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#64748b;background:#f8fafc;border-bottom:2px solid #e2e8f0; }
.ja-table td { padding:14px 16px;border-bottom:1px solid #f1f5f9;vertical-align:middle; }
.ja-table tr:last-child td { border-bottom:none; }
.ja-table tr:hover td { background:#f8fafc; }
.action-btn { width:30px;height:30px;border-radius:7px;border:1px solid #e2e8f0;background:transparent;cursor:pointer;font-size:.8rem;display:inline-flex;align-items:center;justify-content:center;transition:all .15s; }
.action-btn:hover { border-color:var(--ja-primary);color:var(--ja-primary);background:var(--ja-soft); }
.action-btn.danger:hover { border-color:#ef4444;color:#ef4444;background:#fef2f2; }
.modal-backdrop{display:none;position:fixed;inset:0;background:rgba(15,23,42,.7);backdrop-filter:blur(8px);z-index:2000;align-items:center;justify-content:center;padding:20px;}
.modal-backdrop.active{display:flex;}
.modal{background:#fff;width:100%;max-width:560px;border-radius:20px;box-shadow:0 25px 50px -12px rgba(0,0,0,.25);overflow:hidden;display:flex;flex-direction:column;max-height:90vh;}
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
        <h2 style="font-weight:800;color:#0f172a;margin:0;"><i class="fa-solid fa-cart-shopping" style="color:#1e3a5f;"></i> Adquisiciones</h2>
        <p style="color:#64748b;margin:0;">Registro de compras, servicios y proveedores.</p>
    </div>
    <div style="display:flex;gap:8px;">
        <a href="api/adquisiciones.php?accion=exportar_csv&estatus=<?= urlencode($filtroEst) ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
           class="btn" style="background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;font-size:.88rem;">
            <i class="fa-solid fa-file-csv"></i> Exportar CSV
        </a>
        <button class="btn btn-primary" onclick="abrirModalCrear()" style="background:#1e3a5f;border-color:#1e3a5f;">
            <i class="fa-solid fa-plus"></i> Nueva Adquisición
        </button>
    </div>
</div>

<?php if ($flash): ?>
<div class="alert alert-<?= $tipoF ?> alert-dismissible fade show" role="alert">
    <?= esc($flash) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div style="display:flex;gap:10px;margin-bottom:20px;">
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;">
        <select name="estatus" class="form-control" style="width:200px;" onchange="this.form.submit()">
            <option value="">Todos los estatus</option>
            <?php foreach ($estatuses as $e): ?>
            <option value="<?= $e ?>" <?= $filtroEst===$e?'selected':'' ?>><?= ucwords(str_replace('_',' ',$e)) ?></option>
            <?php endforeach; ?>
        </select>
        <?php if ($filtroEst): ?><a href="adquisiciones.php" class="btn" style="background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;padding:9px 14px;border-radius:10px;font-size:.88rem;">Limpiar</a><?php endif; ?>
    </form>
</div>

<div style="background:#fff;border-radius:18px;border:1px solid #e2e8f0;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,.04);">
    <?php if (empty($adqs)): ?>
        <div style="padding:70px;text-align:center;color:#94a3b8;">
            <i class="fa-solid fa-box-open" style="font-size:2.8rem;opacity:.3;margin-bottom:14px;display:block;"></i>
            <p>Sin adquisiciones registradas.</p>
        </div>
    <?php else: ?>
    <div style="overflow-x:auto;">
    <table class="ja-table">
        <thead><tr><th>#</th><th>Concepto</th><th>Proveedor</th><th>Monto</th><th>Área Sol.</th><th>Solicitud</th><th>Estatus</th><th>Acciones</th></tr></thead>
        <tbody>
        <?php foreach ($adqs as $a): $cls = 'badge-'.$a['estatus']; ?>
        <tr>
            <td style="color:#94a3b8;font-size:.78rem;font-weight:600;">#<?= str_pad($a['id'],4,'0',STR_PAD_LEFT) ?></td>
            <td style="font-weight:600;color:#0f172a;max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= esc($a['concepto']) ?></td>
            <td><?= esc($a['proveedor'] ?: '—') ?></td>
            <td><?= $a['monto'] ? '$'.number_format($a['monto'],2) : '—' ?></td>
            <td style="font-size:.82rem;"><?= esc($a['area_solicitante'] ?: '—') ?></td>
            <td style="font-size:.82rem;color:#475569;"><?= $a['fecha_solicitud'] ? date('d/m/Y',strtotime($a['fecha_solicitud'])) : '—' ?></td>
            <td><span class="badge-pill <?= $cls ?>"><?= ucwords(str_replace('_',' ',$a['estatus'])) ?></span></td>
            <td>
                <button class="action-btn" onclick="abrirModalEditar(<?= htmlspecialchars(json_encode($a),ENT_QUOTES) ?>)"><i class="fa-solid fa-pencil"></i></button>
                <button class="action-btn danger" onclick="eliminarAdq(<?= $a['id'] ?>,'<?= esc($a['concepto']) ?>')"><i class="fa-solid fa-trash-can"></i></button>
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
            <h3 class="modal-title"><i class="fa-solid fa-cart-plus"></i> Nueva Adquisición</h3>
            <button class="modal-close" onclick="cerrarModal('modalCrear')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" action="api/adquisiciones.php">
            <?= csrfField() ?><input type="hidden" name="accion" value="crear">
            <div class="modal-body">
                <div class="mb-14"><label class="form-label">Concepto *</label><input type="text" name="concepto" class="form-control" required></div>
                <div class="mb-14"><label class="form-label">Proveedor</label><input type="text" name="proveedor" class="form-control"></div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;" class="mb-14">
                    <div><label class="form-label">Monto (MXN)</label><input type="number" name="monto" class="form-control" step="0.01"></div>
                    <div><label class="form-label">Área Solicitante</label><input type="text" name="area_solicitante" class="form-control"></div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;" class="mb-14">
                    <div><label class="form-label">Fecha Solicitud</label><input type="date" name="fecha_solicitud" class="form-control" value="<?= date('Y-m-d') ?>"></div>
                    <div><label class="form-label">Fecha Entrega</label><input type="date" name="fecha_entrega" class="form-control"></div>
                </div>
                <div class="mb-14"><label class="form-label">Estatus</label>
                    <select name="estatus" class="form-control">
                        <?php foreach ($estatuses as $e): ?><option value="<?= $e ?>"><?= ucwords(str_replace('_',' ',$e)) ?></option><?php endforeach; ?>
                    </select>
                </div>
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
            <h3 class="modal-title"><i class="fa-solid fa-pencil"></i> Editar Adquisición</h3>
            <button class="modal-close" onclick="cerrarModal('modalEditar')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" action="api/adquisiciones.php">
            <?= csrfField() ?><input type="hidden" name="accion" value="editar">
            <input type="hidden" name="id" id="ed_id">
            <div class="modal-body">
                <div class="mb-14"><label class="form-label">Concepto *</label><input type="text" name="concepto" id="ed_concepto" class="form-control" required></div>
                <div class="mb-14"><label class="form-label">Proveedor</label><input type="text" name="proveedor" id="ed_prov" class="form-control"></div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;" class="mb-14">
                    <div><label class="form-label">Monto</label><input type="number" name="monto" id="ed_monto" class="form-control" step="0.01"></div>
                    <div><label class="form-label">Área Solicitante</label><input type="text" name="area_solicitante" id="ed_area" class="form-control"></div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;" class="mb-14">
                    <div><label class="form-label">Fecha Solicitud</label><input type="date" name="fecha_solicitud" id="ed_fs" class="form-control"></div>
                    <div><label class="form-label">Fecha Entrega</label><input type="date" name="fecha_entrega" id="ed_fe" class="form-control"></div>
                </div>
                <div class="mb-14"><label class="form-label">Estatus</label>
                    <select name="estatus" id="ed_est" class="form-control">
                        <?php foreach ($estatuses as $e): ?><option value="<?= $e ?>"><?= ucwords(str_replace('_',' ',$e)) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div><label class="form-label">Notas</label><textarea name="notas" id="ed_notas" class="form-control" rows="3"></textarea></div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="cerrarModal('modalEditar')" class="btn" style="background:#f1f5f9;color:#475569;">Cancelar</button>
                <button type="submit" class="btn btn-primary" style="background:#1e3a5f;border-color:#1e3a5f;"><i class="fa-solid fa-check"></i> Actualizar</button>
            </div>
        </form>
    </div>
</div>

<form method="POST" action="api/adquisiciones.php" id="frmEliminar" style="display:none;">
    <?= csrfField() ?><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" id="del_id">
</form>

<script>
function cerrarModal(id) { document.getElementById(id).classList.remove('active'); }
function abrirModalCrear() { document.getElementById('modalCrear').classList.add('active'); }
function abrirModalEditar(a) {
    document.getElementById('ed_id').value      = a.id;
    document.getElementById('ed_concepto').value = a.concepto;
    document.getElementById('ed_prov').value    = a.proveedor || '';
    document.getElementById('ed_monto').value   = a.monto || '';
    document.getElementById('ed_area').value    = a.area_solicitante || '';
    document.getElementById('ed_fs').value      = a.fecha_solicitud || '';
    document.getElementById('ed_fe').value      = a.fecha_entrega || '';
    document.getElementById('ed_notas').value   = a.notas || '';
    const sel = document.getElementById('ed_est');
    [...sel.options].forEach(o => o.selected = o.value === a.estatus);
    document.getElementById('modalEditar').classList.add('active');
}
function eliminarAdq(id, concepto) {
    if (!confirm(`¿Eliminar "${concepto}"?`)) return;
    document.getElementById('del_id').value = id;
    document.getElementById('frmEliminar').submit();
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') document.querySelectorAll('.modal-backdrop.active').forEach(m => m.classList.remove('active')); });
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
