<?php
/**
 * COMECyT — Unidad de Igualdad y Género (Jurídico Administrativo)
 * Seguimiento de casos con folio, tipo y estatus — sin datos identificatorios.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../config/auth.php';

verificarSesionAdmin();

$pdo     = getConnection();
$adminId = (int) ($_SESSION['admin_id'] ?? 0);
$flash   = '';
$tipoF   = '';

$filtroEst  = getParam('estatus', '');
$filtroTipo = getParam('tipo', '');

$where  = 'WHERE 1=1';
$params = [];
if ($filtroEst)  { $where .= ' AND estatus = ?'; $params[] = $filtroEst; }
if ($filtroTipo) { $where .= ' AND tipo = ?';    $params[] = $filtroTipo; }

$stmt = $pdo->prepare("SELECT * FROM ja_casos_igualdad $where ORDER BY created_at DESC");
$stmt->execute($params);
$casos = $stmt->fetchAll();

$fc = getParam('flash');
if ($fc === 'creado')    { $flash = 'Caso registrado.';   $tipoF = 'success'; }
if ($fc === 'editado')   { $flash = 'Caso actualizado.';  $tipoF = 'success'; }
if ($fc === 'eliminado') { $flash = 'Caso eliminado.';    $tipoF = 'success'; }

$tipos = ['hostigamiento','discriminacion','violencia_laboral','acoso','otro'];
$estatuses = ['recibido','en_seguimiento','resuelto','cerrado'];

$pageTitle  = 'Igualdad y Género';
$activeMenu = 'igualdad';

require_once __DIR__ . '/../../includes/header_admin.php';
?>

<style>
:root { --ja-primary:#1e3a5f; --ja-accent:#B19A6D; --ja-soft:rgba(30,58,95,.05); }
.badge-recibido      { background:#dcfce7;color:#16a34a; }
.badge-en_seguimiento{ background:#fef3c7;color:#d97706; }
.badge-resuelto      { background:#eff6ff;color:#2563eb; }
.badge-cerrado       { background:#f1f5f9;color:#64748b; }
.badge-pill { padding:3px 10px;border-radius:20px;font-size:.75rem;font-weight:600; }
.modal-backdrop{display:none;position:fixed;inset:0;background:rgba(15,23,42,.7);backdrop-filter:blur(8px);z-index:2000;align-items:center;justify-content:center;padding:20px;}
.modal-backdrop.active{display:flex;}
.modal{background:#fff;width:100%;max-width:540px;border-radius:20px;box-shadow:0 25px 50px -12px rgba(0,0,0,.25);overflow:hidden;display:flex;flex-direction:column;max-height:90vh;}
.modal-header{background:linear-gradient(135deg,#db2777,#9d174d);color:#fff;padding:1.25rem 1.5rem;display:flex;align-items:center;justify-content:space-between;}
.modal-title{margin:0;font-size:1.1rem;font-weight:800;display:flex;align-items:center;gap:10px;}
.modal-close{background:none;border:none;color:rgba(255,255,255,.8);cursor:pointer;font-size:1.4rem;}
.modal-close:hover{color:#fff;}
.modal-body{padding:1.5rem 2rem;overflow-y:auto;}
.modal-footer{padding:1.25rem 2rem;border-top:1px solid #f1f5f9;background:#fafafa;display:flex;gap:10px;justify-content:flex-end;}
.form-label{font-weight:700;color:#475569;font-size:.85rem;margin-bottom:5px;display:block;}
.form-control{border-radius:10px;border:1.5px solid #e2e8f0;background:#f8fafc;padding:9px 13px;font-family:inherit;font-size:.9rem;width:100%;box-sizing:border-box;transition:all .2s;}
.form-control:focus{border-color:#db2777;box-shadow:0 0 0 3px rgba(219,39,119,.1);background:#fff;outline:none;}
.mb-14{margin-bottom:14px;}
.ja-table { width:100%; border-collapse:collapse; }
.ja-table th { text-align:left;padding:12px 16px;font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#64748b;background:#f8fafc;border-bottom:2px solid #e2e8f0; }
.ja-table td { padding:14px 16px;border-bottom:1px solid #f1f5f9;vertical-align:middle; }
.ja-table tr:last-child td { border-bottom:none; }
.ja-table tr:hover td { background:#f8fafc; }
.action-btn { width:30px;height:30px;border-radius:7px;border:1px solid #e2e8f0;background:transparent;cursor:pointer;font-size:.8rem;display:inline-flex;align-items:center;justify-content:center;transition:all .15s; }
.action-btn:hover { border-color:#db2777;color:#db2777;background:#fdf2f8; }
.action-btn.danger:hover { border-color:#ef4444;color:#ef4444;background:#fef2f2; }
</style>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
    <div>
        <h2 style="font-weight:800;color:#0f172a;margin:0;"><i class="fa-solid fa-venus-mars" style="color:#db2777;"></i> Unidad de Igualdad y Género</h2>
        <p style="color:#64748b;margin:0;">Seguimiento confidencial de casos del protocolo de igualdad.</p>
    </div>
    <button class="btn" onclick="abrirModalCrear()" style="background:#db2777;color:#fff;border:none;padding:.6rem 1.2rem;border-radius:10px;font-weight:600;cursor:pointer;">
        <i class="fa-solid fa-plus"></i> Registrar Caso
    </button>
</div>

<?php if ($flash): ?>
<div class="alert alert-<?= $tipoF ?> alert-dismissible fade show" role="alert">
    <?= esc($flash) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;">
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;">
        <select name="tipo" class="form-control" style="width:200px;" onchange="this.form.submit()">
            <option value="">Todos los tipos</option>
            <?php foreach ($tipos as $t): ?>
            <option value="<?= $t ?>" <?= $filtroTipo===$t?'selected':'' ?>><?= ucwords(str_replace('_',' ',$t)) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="estatus" class="form-control" style="width:200px;" onchange="this.form.submit()">
            <option value="">Todos los estatus</option>
            <?php foreach ($estatuses as $e): ?>
            <option value="<?= $e ?>" <?= $filtroEst===$e?'selected':'' ?>><?= ucwords(str_replace('_',' ',$e)) ?></option>
            <?php endforeach; ?>
        </select>
        <?php if ($filtroEst || $filtroTipo): ?>
        <a href="igualdad.php" class="btn" style="background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;padding:9px 14px;border-radius:10px;font-size:.88rem;">Limpiar</a>
        <?php endif; ?>
    </form>
</div>

<div style="background:#fff;border-radius:18px;border:1px solid #e2e8f0;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,.04);">
    <?php if (empty($casos)): ?>
        <div style="padding:70px;text-align:center;color:#94a3b8;">
            <i class="fa-solid fa-circle-check" style="font-size:2.8rem;opacity:.3;margin-bottom:14px;display:block;color:#16a34a;"></i>
            <p>No hay casos registrados.</p>
        </div>
    <?php else: ?>
    <div style="overflow-x:auto;">
    <table class="ja-table">
        <thead><tr><th>Folio</th><th>Tipo</th><th>Recepción</th><th>Cierre</th><th>Estatus</th><th>Acciones</th></tr></thead>
        <tbody>
        <?php foreach ($casos as $c): $cls = 'badge-'.$c['estatus']; ?>
            <tr>
                <td style="font-family:monospace;font-weight:700;color:#db2777;"><?= esc($c['folio']) ?></td>
                <td><?= esc(ucwords(str_replace('_',' ',$c['tipo']))) ?></td>
                <td style="font-size:.83rem;color:#475569;"><?= $c['fecha_recepcion'] ? date('d/m/Y', strtotime($c['fecha_recepcion'])) : '—' ?></td>
                <td style="font-size:.83rem;color:#475569;"><?= $c['fecha_cierre'] ? date('d/m/Y', strtotime($c['fecha_cierre'])) : '—' ?></td>
                <td><span class="badge-pill <?= $cls ?>"><?= ucwords(str_replace('_',' ',$c['estatus'])) ?></span></td>
                <td>
                    <button class="action-btn" onclick="abrirModalEditar(<?= htmlspecialchars(json_encode($c),ENT_QUOTES) ?>)"><i class="fa-solid fa-pencil"></i></button>
                    <button class="action-btn danger" onclick="eliminarCaso(<?= $c['id'] ?>,'<?= esc($c['folio']) ?>')"><i class="fa-solid fa-trash-can"></i></button>
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
            <h3 class="modal-title"><i class="fa-solid fa-shield-halved"></i> Registrar Caso</h3>
            <button class="modal-close" onclick="cerrarModal('modalCrear')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" action="api/igualdad.php">
            <?= csrfField() ?><input type="hidden" name="accion" value="crear">
            <div class="modal-body">
                <div class="mb-14"><label class="form-label">Tipo de Caso *</label>
                    <select name="tipo" class="form-control" required>
                        <?php foreach ($tipos as $t): ?><option value="<?= $t ?>"><?= ucwords(str_replace('_',' ',$t)) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-14"><label class="form-label">Fecha de Recepción</label><input type="date" name="fecha_recepcion" class="form-control" value="<?= date('Y-m-d') ?>"></div>
                <div><label class="form-label">Notas (anónimas, sin datos identificatorios)</label><textarea name="notas" class="form-control" rows="4"></textarea></div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="cerrarModal('modalCrear')" class="btn" style="background:#f1f5f9;color:#475569;">Cancelar</button>
                <button type="submit" class="btn" style="background:#db2777;color:#fff;border:none;"><i class="fa-solid fa-check"></i> Registrar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Editar -->
<div class="modal-backdrop" id="modalEditar">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fa-solid fa-pencil"></i> Actualizar Caso <span id="ed_folio_label" style="font-size:.85rem;opacity:.8;"></span></h3>
            <button class="modal-close" onclick="cerrarModal('modalEditar')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" action="api/igualdad.php">
            <?= csrfField() ?><input type="hidden" name="accion" value="editar">
            <input type="hidden" name="id" id="ed_id">
            <div class="modal-body">
                <div class="mb-14"><label class="form-label">Tipo</label>
                    <select name="tipo" id="ed_tipo" class="form-control">
                        <?php foreach ($tipos as $t): ?><option value="<?= $t ?>"><?= ucwords(str_replace('_',' ',$t)) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-14"><label class="form-label">Estatus</label>
                    <select name="estatus" id="ed_estatus" class="form-control">
                        <?php foreach ($estatuses as $e): ?><option value="<?= $e ?>"><?= ucwords(str_replace('_',' ',$e)) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-14"><label class="form-label">Fecha Cierre (si aplica)</label><input type="date" name="fecha_cierre" id="ed_cierre" class="form-control"></div>
                <div><label class="form-label">Notas</label><textarea name="notas" id="ed_notas" class="form-control" rows="4"></textarea></div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="cerrarModal('modalEditar')" class="btn" style="background:#f1f5f9;color:#475569;">Cancelar</button>
                <button type="submit" class="btn" style="background:#db2777;color:#fff;border:none;"><i class="fa-solid fa-check"></i> Actualizar</button>
            </div>
        </form>
    </div>
</div>

<form method="POST" action="api/igualdad.php" id="frmEliminar" style="display:none;">
    <?= csrfField() ?><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" id="del_id">
</form>

<script>
function cerrarModal(id) { document.getElementById(id).classList.remove('active'); }
function abrirModalCrear() { document.getElementById('modalCrear').classList.add('active'); }
function abrirModalEditar(c) {
    document.getElementById('ed_id').value = c.id;
    document.getElementById('ed_folio_label').textContent = '— ' + c.folio;
    document.getElementById('ed_cierre').value = c.fecha_cierre || '';
    document.getElementById('ed_notas').value  = c.notas || '';
    const sel = (id, val) => { const s = document.getElementById(id); [...s.options].forEach(o => o.selected = o.value === val); };
    sel('ed_tipo', c.tipo); sel('ed_estatus', c.estatus);
    document.getElementById('modalEditar').classList.add('active');
}
function eliminarCaso(id, folio) {
    if (!confirm(`¿Eliminar el caso ${folio}? Esta acción no se puede deshacer.`)) return;
    document.getElementById('del_id').value = id;
    document.getElementById('frmEliminar').submit();
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') document.querySelectorAll('.modal-backdrop.active').forEach(m => m.classList.remove('active')); });
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
