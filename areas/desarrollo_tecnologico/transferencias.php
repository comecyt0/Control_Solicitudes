<?php
/**
 * COMECyT — Transferencia Tecnológica (DT)
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../config/auth.php';
verificarSesionAdmin();
$pdo = getConnection();

$filtroEst  = getParam('estatus','');
$filtroTipo = getParam('tipo','');
$where='WHERE 1=1';$params=[];
if($filtroEst){$where.=' AND estatus=?';$params[]=$filtroEst;}
if($filtroTipo){$where.=' AND tipo=?';$params[]=$filtroTipo;}
$stmt=$pdo->prepare("SELECT * FROM dt_transferencias $where ORDER BY fecha DESC NULLS LAST, created_at DESC");
$stmt->execute($params);$transferencias=$stmt->fetchAll();

$tipos=['prototipo','patente','licencia','spinoff'];
$estatuses=['en_proceso','concluido','cancelado'];

$fc=getParam('flash');$flash='';$tipoF='';
if($fc==='creado'){$flash='Transferencia registrada.';$tipoF='success';}
elseif($fc==='editado'){$flash='Transferencia actualizada.';$tipoF='success';}
elseif($fc==='eliminado'){$flash='Transferencia eliminada.';$tipoF='success';}

$pageTitle='Transferencia Tecnológica'; $activeMenu='transferencias';
require_once __DIR__ . '/../../includes/header_admin.php';
?>
<style>
:root{--dt-primary:#6d28d9;--dt-accent:#B19A6D;}
.badge-en_proceso{background:#e0f2fe;color:#0284c7;}.badge-concluido{background:#dcfce7;color:#16a34a;}.badge-cancelado{background:#fee2e2;color:#dc2626;}
.badge-prototipo{background:#ede9fe;color:#6d28d9;}.badge-patente{background:#fce7f3;color:#db2777;}.badge-licencia{background:#fef3c7;color:#d97706;}.badge-spinoff{background:#e0f2fe;color:#0369a1;}
.badge-pill{padding:3px 10px;border-radius:20px;font-size:.74rem;font-weight:600;}
.trans-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:18px;}
.trans-card{background:#fff;border-radius:16px;border:1px solid #e2e8f0;padding:22px;box-shadow:0 4px 12px rgba(0,0,0,.04);transition:all .25s;border-top:4px solid var(--dt-primary);}
.trans-card:hover{transform:translateY(-4px);box-shadow:0 12px 28px rgba(109,40,217,.1);border-top-color:var(--dt-accent);}
.trans-nombre{font-size:1rem;font-weight:700;color:#0f172a;margin-bottom:8px;}
.trans-meta{display:flex;align-items:center;gap:7px;font-size:.8rem;color:#64748b;margin-top:5px;}
.trans-meta i{color:var(--dt-accent);width:13px;}
.trans-actions{display:flex;gap:6px;margin-top:14px;padding-top:12px;border-top:1px solid #f1f5f9;}
.action-btn{width:30px;height:30px;border-radius:7px;border:1px solid #e2e8f0;background:transparent;cursor:pointer;font-size:.8rem;display:inline-flex;align-items:center;justify-content:center;transition:all .15s;}
.action-btn:hover{border-color:var(--dt-primary);color:var(--dt-primary);}.action-btn.danger:hover{border-color:#ef4444;color:#ef4444;background:#fef2f2;}
.modal-backdrop{display:none;position:fixed;inset:0;background:rgba(15,23,42,.7);backdrop-filter:blur(8px);z-index:2000;align-items:center;justify-content:center;padding:20px;}
.modal-backdrop.active{display:flex;}
.modal{background:#fff;width:100%;max-width:580px;border-radius:20px;overflow:hidden;display:flex;flex-direction:column;max-height:90vh;}
.modal-header{background:linear-gradient(135deg,#6d28d9,#4c1d95);color:#fff;padding:1.25rem 1.5rem;display:flex;align-items:center;justify-content:space-between;}
.modal-title{margin:0;font-size:1.1rem;font-weight:800;display:flex;align-items:center;gap:10px;}
.modal-close{background:none;border:none;color:rgba(255,255,255,.8);cursor:pointer;font-size:1.4rem;}.modal-close:hover{color:#fff;}
.modal-body{padding:1.5rem 2rem;overflow-y:auto;}.modal-footer{padding:1.25rem 2rem;border-top:1px solid #f1f5f9;background:#fafafa;display:flex;gap:10px;justify-content:flex-end;}
.form-label{font-weight:700;color:#475569;font-size:.85rem;margin-bottom:5px;display:block;}
.form-control{border-radius:10px;border:1.5px solid #e2e8f0;background:#f8fafc;padding:9px 13px;font-family:inherit;font-size:.9rem;width:100%;box-sizing:border-box;transition:all .2s;}
.form-control:focus{border-color:#6d28d9;outline:none;}.mb-14{margin-bottom:14px;}
</style>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
    <div><h2 style="font-weight:800;color:#0f172a;margin:0;"><i class="fa-solid fa-flask" style="color:#6d28d9;"></i> Transferencia Tecnológica</h2><p style="color:#64748b;margin:0;">Patentes, prototipos, licencias y spin-offs del área.</p></div>
    <div style="display:flex;gap:8px;">
        <a href="api/transferencias.php?accion=exportar_csv&csrf_token=<?=$_SESSION['csrf_token']?>" class="btn" style="background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;font-size:.88rem;"><i class="fa-solid fa-file-csv"></i> CSV</a>
        <button class="btn btn-primary" onclick="abrirModalCrear()" style="background:#6d28d9;border-color:#6d28d9;"><i class="fa-solid fa-plus"></i> Nueva Transferencia</button>
    </div>
</div>
<?php if($flash): ?><div class="alert alert-<?=$tipoF?> alert-dismissible fade show" role="alert"><?=esc($flash)?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<div style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;">
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;">
        <select name="tipo" class="form-control" style="width:150px;" onchange="this.form.submit()"><option value="">Todos los tipos</option><?php foreach($tipos as $t): ?><option value="<?=$t?>" <?=$filtroTipo===$t?'selected':''?>><?=ucfirst($t)?></option><?php endforeach; ?></select>
        <select name="estatus" class="form-control" style="width:155px;" onchange="this.form.submit()"><option value="">Todos estatus</option><?php foreach($estatuses as $e): ?><option value="<?=$e?>" <?=$filtroEst===$e?'selected':''?>><?=ucfirst(str_replace('_',' ',$e))?></option><?php endforeach; ?></select>
        <?php if($filtroEst||$filtroTipo): ?><a href="transferencias.php" class="btn" style="background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;padding:9px 14px;border-radius:10px;font-size:.88rem;">Limpiar</a><?php endif; ?>
    </form>
    <span style="margin-left:auto;align-self:center;font-size:.85rem;color:#64748b;"><?=count($transferencias)?> registros</span>
</div>

<?php if(empty($transferencias)): ?>
<div style="background:#fff;border-radius:18px;padding:70px;text-align:center;color:#94a3b8;border:1px solid #e2e8f0;"><i class="fa-solid fa-flask" style="font-size:2.8rem;opacity:.3;margin-bottom:14px;display:block;"></i><p>No hay transferencias registradas.</p></div>
<?php else: ?>
<div class="trans-grid">
<?php foreach($transferencias as $t): $cls='badge-'.$t['estatus'];$clsTipo='badge-'.$t['tipo']; ?>
<div class="trans-card">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px;">
        <span class="badge-pill <?=$clsTipo?>"><?=ucfirst(esc($t['tipo']))?></span>
        <span class="badge-pill <?=$cls?>"><?=ucfirst(str_replace('_',' ',$t['estatus']))?></span>
    </div>
    <div class="trans-nombre"><?=esc($t['nombre'])?></div>
    <?php if($t['responsable']): ?><div class="trans-meta"><i class="fa-solid fa-user-tie"></i><?=esc($t['responsable'])?></div><?php endif; ?>
    <?php if($t['institucion']): ?><div class="trans-meta"><i class="fa-solid fa-building"></i><?=esc($t['institucion'])?></div><?php endif; ?>
    <?php if($t['fecha']): ?><div class="trans-meta"><i class="fa-solid fa-calendar-days"></i><?=date('d/m/Y',strtotime($t['fecha']))?></div><?php endif; ?>
    <?php if($t['descripcion']): ?><div class="trans-meta" style="white-space:normal;margin-top:8px;"><i class="fa-solid fa-note-sticky"></i><?=esc(mb_strimwidth($t['descripcion'],0,70,'...'))?></div><?php endif; ?>
    <div class="trans-actions">
        <button class="action-btn" style="flex:1;" onclick="abrirModalEditar(<?=htmlspecialchars(json_encode($t),ENT_QUOTES)?>)"><i class="fa-solid fa-pencil"></i> Editar</button>
        <button class="action-btn danger" onclick="eliminar(<?=$t['id']?>,'<?=esc($t['nombre'])?>') "><i class="fa-solid fa-trash-can"></i></button>
    </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Modal Crear -->
<div class="modal-backdrop" id="modalCrear"><div class="modal">
    <div class="modal-header"><h3 class="modal-title"><i class="fa-solid fa-flask"></i> Nueva Transferencia</h3><button class="modal-close" onclick="cerrarModal('modalCrear')"><i class="fa-solid fa-xmark"></i></button></div>
    <form method="POST" action="api/transferencias.php"><?=csrfField()?><input type="hidden" name="accion" value="crear">
    <div class="modal-body">
        <div class="mb-14"><label class="form-label">Nombre / Descripción *</label><input type="text" name="nombre" class="form-control" required></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;" class="mb-14">
            <div><label class="form-label">Tipo</label><select name="tipo" class="form-control"><?php foreach($tipos as $t): ?><option value="<?=$t?>"><?=ucfirst($t)?></option><?php endforeach; ?></select></div>
            <div><label class="form-label">Estatus</label><select name="estatus" class="form-control"><?php foreach($estatuses as $e): ?><option value="<?=$e?>"><?=ucfirst(str_replace('_',' ',$e))?></option><?php endforeach; ?></select></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;" class="mb-14">
            <div><label class="form-label">Responsable</label><input type="text" name="responsable" class="form-control"></div>
            <div><label class="form-label">Institución</label><input type="text" name="institucion" class="form-control"></div>
        </div>
        <div class="mb-14"><label class="form-label">Fecha</label><input type="date" name="fecha" class="form-control"></div>
        <div><label class="form-label">Descripción adicional</label><textarea name="descripcion" class="form-control" rows="3"></textarea></div>
    </div>
    <div class="modal-footer"><button type="button" onclick="cerrarModal('modalCrear')" class="btn" style="background:#f1f5f9;color:#475569;">Cancelar</button><button type="submit" class="btn btn-primary" style="background:#6d28d9;border-color:#6d28d9;"><i class="fa-solid fa-check"></i> Guardar</button></div>
    </form>
</div></div>

<!-- Modal Editar -->
<div class="modal-backdrop" id="modalEditar"><div class="modal">
    <div class="modal-header"><h3 class="modal-title"><i class="fa-solid fa-pencil"></i> Editar Transferencia</h3><button class="modal-close" onclick="cerrarModal('modalEditar')"><i class="fa-solid fa-xmark"></i></button></div>
    <form method="POST" action="api/transferencias.php"><?=csrfField()?><input type="hidden" name="accion" value="editar"><input type="hidden" name="id" id="ed_id">
    <div class="modal-body">
        <div class="mb-14"><label class="form-label">Nombre *</label><input type="text" name="nombre" id="ed_nom" class="form-control" required></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;" class="mb-14">
            <div><label class="form-label">Tipo</label><select name="tipo" id="ed_tipo" class="form-control"><?php foreach($tipos as $t): ?><option value="<?=$t?>"><?=ucfirst($t)?></option><?php endforeach; ?></select></div>
            <div><label class="form-label">Estatus</label><select name="estatus" id="ed_est" class="form-control"><?php foreach($estatuses as $e): ?><option value="<?=$e?>"><?=ucfirst(str_replace('_',' ',$e))?></option><?php endforeach; ?></select></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;" class="mb-14">
            <div><label class="form-label">Responsable</label><input type="text" name="responsable" id="ed_resp" class="form-control"></div>
            <div><label class="form-label">Institución</label><input type="text" name="institucion" id="ed_inst" class="form-control"></div>
        </div>
        <div class="mb-14"><label class="form-label">Fecha</label><input type="date" name="fecha" id="ed_fecha" class="form-control"></div>
        <div><label class="form-label">Descripción</label><textarea name="descripcion" id="ed_desc" class="form-control" rows="3"></textarea></div>
    </div>
    <div class="modal-footer"><button type="button" onclick="cerrarModal('modalEditar')" class="btn" style="background:#f1f5f9;color:#475569;">Cancelar</button><button type="submit" class="btn btn-primary" style="background:#6d28d9;border-color:#6d28d9;"><i class="fa-solid fa-check"></i> Actualizar</button></div>
    </form>
</div></div>
<form method="POST" action="api/transferencias.php" id="frmEliminar" style="display:none;"><?=csrfField()?><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" id="del_id"></form>
<script>
function cerrarModal(id){document.getElementById(id).classList.remove('active');}
function abrirModalCrear(){document.getElementById('modalCrear').classList.add('active');}
function abrirModalEditar(t){
    document.getElementById('ed_id').value=t.id;document.getElementById('ed_nom').value=t.nombre;
    document.getElementById('ed_resp').value=t.responsable||'';document.getElementById('ed_inst').value=t.institucion||'';
    document.getElementById('ed_fecha').value=t.fecha||'';document.getElementById('ed_desc').value=t.descripcion||'';
    const sel=(id,val)=>{const s=document.getElementById(id);[...s.options].forEach(o=>o.selected=o.value===val);};
    sel('ed_tipo',t.tipo);sel('ed_est',t.estatus);document.getElementById('modalEditar').classList.add('active');
}
function eliminar(id,nombre){if(!confirm(`¿Eliminar "${nombre}"?`))return;document.getElementById('del_id').value=id;document.getElementById('frmEliminar').submit();}
document.addEventListener('keydown',e=>{if(e.key==='Escape')document.querySelectorAll('.modal-backdrop.active').forEach(m=>m.classList.remove('active'));});
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
