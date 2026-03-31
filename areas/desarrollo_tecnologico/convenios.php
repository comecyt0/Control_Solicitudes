<?php
/**
 * COMECyT — Convenios y Vinculación (DT) — folio CV-YYYY-NNNN
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
$stmt=$pdo->prepare("SELECT * FROM dt_convenios $where ORDER BY fecha_fin ASC NULLS LAST, created_at DESC");
$stmt->execute($params);$convenios=$stmt->fetchAll();

$tipos=['colaboracion','donacion','servicio','otro'];
$estatuses=['vigente','vencido','rescindido'];

$fc=getParam('flash');$flash='';$tipoF='';
if($fc==='creado'){$flash='Convenio registrado.';$tipoF='success';}
elseif($fc==='editado'){$flash='Convenio actualizado.';$tipoF='success';}
elseif($fc==='eliminado'){$flash='Convenio eliminado.';$tipoF='success';}

$pageTitle='Convenios y Vinculación'; $activeMenu='convenios';
require_once __DIR__ . '/../../includes/header_admin.php';
?>
<style>
:root{--dt-primary:#6d28d9;--dt-accent:#B19A6D;}
.badge-vigente{background:#dcfce7;color:#16a34a;}.badge-vencido{background:#fee2e2;color:#dc2626;}.badge-rescindido{background:#f1f5f9;color:#475569;}
.badge-colaboracion{background:#ede9fe;color:#6d28d9;}.badge-donacion{background:#fce7f3;color:#db2777;}.badge-servicio{background:#e0f2fe;color:#0284c7;}.badge-otro{background:#f1f5f9;color:#475569;}
.badge-pill{padding:3px 10px;border-radius:20px;font-size:.74rem;font-weight:600;}
.folio-tag{font-family:monospace;background:#f1f5f9;color:#475569;padding:3px 9px;border-radius:8px;font-size:.78rem;font-weight:600;}
.dt-table{width:100%;border-collapse:collapse;}.dt-table th{text-align:left;padding:12px 16px;font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#64748b;background:#f8fafc;border-bottom:2px solid #e2e8f0;}
.dt-table td{padding:13px 16px;border-bottom:1px solid #f1f5f9;vertical-align:middle;}.dt-table tr:last-child td{border-bottom:none;}.dt-table tr:hover td{background:#f8fafc;}
.action-btn{width:30px;height:30px;border-radius:7px;border:1px solid #e2e8f0;background:transparent;cursor:pointer;font-size:.8rem;display:inline-flex;align-items:center;justify-content:center;transition:all .15s;}
.action-btn:hover{border-color:var(--dt-primary);color:var(--dt-primary);}.action-btn.danger:hover{border-color:#ef4444;color:#ef4444;background:#fef2f2;}
.modal-backdrop{display:none;position:fixed;inset:0;background:rgba(15,23,42,.7);backdrop-filter:blur(8px);z-index:2000;align-items:center;justify-content:center;padding:20px;}
.modal-backdrop.active{display:flex;}
.modal{background:#fff;width:100%;max-width:600px;border-radius:20px;overflow:hidden;display:flex;flex-direction:column;max-height:90vh;}
.modal-header{background:linear-gradient(135deg,#6d28d9,#4c1d95);color:#fff;padding:1.25rem 1.5rem;display:flex;align-items:center;justify-content:space-between;}
.modal-title{margin:0;font-size:1.1rem;font-weight:800;display:flex;align-items:center;gap:10px;}
.modal-close{background:none;border:none;color:rgba(255,255,255,.8);cursor:pointer;font-size:1.4rem;}.modal-close:hover{color:#fff;}
.modal-body{padding:1.5rem 2rem;overflow-y:auto;}.modal-footer{padding:1.25rem 2rem;border-top:1px solid #f1f5f9;background:#fafafa;display:flex;gap:10px;justify-content:flex-end;}
.form-label{font-weight:700;color:#475569;font-size:.85rem;margin-bottom:5px;display:block;}
.form-control{border-radius:10px;border:1.5px solid #e2e8f0;background:#f8fafc;padding:9px 13px;font-family:inherit;font-size:.9rem;width:100%;box-sizing:border-box;transition:all .2s;}
.form-control:focus{border-color:#6d28d9;outline:none;}.mb-14{margin-bottom:14px;}
</style>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
    <div><h2 style="font-weight:800;color:#0f172a;margin:0;"><i class="fa-solid fa-handshake" style="color:#6d28d9;"></i> Convenios y Vinculación</h2><p style="color:#64748b;margin:0;">Registro de convenios de colaboración y vinculación institucional.</p></div>
    <div style="display:flex;gap:8px;">
        <a href="api/convenios.php?accion=exportar_csv&csrf_token=<?=$_SESSION['csrf_token']?>" class="btn" style="background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;font-size:.88rem;"><i class="fa-solid fa-file-csv"></i> CSV</a>
        <button class="btn btn-primary" onclick="abrirModalCrear()" style="background:#6d28d9;border-color:#6d28d9;"><i class="fa-solid fa-plus"></i> Nuevo Convenio</button>
    </div>
</div>
<?php if($flash): ?><div class="alert alert-<?=$tipoF?> alert-dismissible fade show" role="alert"><?=esc($flash)?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<div style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;">
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;">
        <select name="tipo" class="form-control" style="width:155px;" onchange="this.form.submit()"><option value="">Todos los tipos</option><?php foreach($tipos as $t): ?><option value="<?=$t?>" <?=$filtroTipo===$t?'selected':''?>><?=ucfirst($t)?></option><?php endforeach; ?></select>
        <select name="estatus" class="form-control" style="width:150px;" onchange="this.form.submit()"><option value="">Todos estatus</option><?php foreach($estatuses as $e): ?><option value="<?=$e?>" <?=$filtroEst===$e?'selected':''?>><?=ucfirst($e)?></option><?php endforeach; ?></select>
        <?php if($filtroEst||$filtroTipo): ?><a href="convenios.php" class="btn" style="background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;padding:9px 14px;border-radius:10px;font-size:.88rem;">Limpiar</a><?php endif; ?>
    </form>
    <span style="margin-left:auto;align-self:center;font-size:.85rem;color:#64748b;"><?=count($convenios)?> convenios</span>
</div>

<div style="background:#fff;border-radius:18px;border:1px solid #e2e8f0;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,.04);">
<?php if(empty($convenios)): ?>
<div style="padding:70px;text-align:center;color:#94a3b8;"><i class="fa-solid fa-handshake" style="font-size:2.8rem;opacity:.3;margin-bottom:14px;display:block;"></i><p>No hay convenios registrados.</p></div>
<?php else: ?>
<div style="overflow-x:auto;">
<table class="dt-table">
    <thead><tr><th>Folio</th><th>Institución</th><th>Tipo</th><th>Responsable</th><th>Vigencia</th><th>Estatus</th><th>Acciones</th></tr></thead>
    <tbody>
    <?php foreach($convenios as $c):
        $hoy=new DateTime(); $alerta='';
        if($c['fecha_fin'] && $c['estatus']==='vigente'){$fin=new DateTime($c['fecha_fin']);$dias=$hoy->diff($fin)->days;if($dias<=30)$alerta='color:#dc2626;font-weight:700;';}
    ?>
    <tr>
        <td><span class="folio-tag"><?=esc($c['folio']??'—')?></span></td>
        <td style="font-weight:600;color:#0f172a;max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?=esc($c['institucion'])?></td>
        <td><span class="badge-pill badge-<?=$c['tipo']?>"><?=ucfirst(esc($c['tipo']))?></span></td>
        <td style="font-size:.82rem;"><?=esc($c['responsable']?:'—')?></td>
        <td style="font-size:.78rem;white-space:nowrap;<?=$alerta?>"><?=$c['fecha_inicio']?date('d/m/Y',strtotime($c['fecha_inicio'])):'—'?> – <?=$c['fecha_fin']?date('d/m/Y',strtotime($c['fecha_fin'])):'—'?></td>
        <td><span class="badge-pill badge-<?=$c['estatus']?>"><?=ucfirst(esc($c['estatus']))?></span></td>
        <td>
            <button class="action-btn" onclick="abrirModalEditar(<?=htmlspecialchars(json_encode($c),ENT_QUOTES)?>)"><i class="fa-solid fa-pencil"></i></button>
            <button class="action-btn danger" onclick="eliminar(<?=$c['id']?>,'<?=esc($c['folio'])?>') "><i class="fa-solid fa-trash-can"></i></button>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
</div>

<!-- Modal Crear -->
<div class="modal-backdrop" id="modalCrear"><div class="modal">
    <div class="modal-header"><h3 class="modal-title"><i class="fa-solid fa-handshake"></i> Nuevo Convenio</h3><button class="modal-close" onclick="cerrarModal('modalCrear')"><i class="fa-solid fa-xmark"></i></button></div>
    <form method="POST" action="api/convenios.php"><?=csrfField()?><input type="hidden" name="accion" value="crear">
    <div class="modal-body">
        <div class="mb-14"><label class="form-label">Institución / Organización *</label><input type="text" name="institucion" class="form-control" required></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;" class="mb-14">
            <div><label class="form-label">Tipo</label><select name="tipo" class="form-control"><?php foreach($tipos as $t): ?><option value="<?=$t?>"><?=ucfirst($t)?></option><?php endforeach; ?></select></div>
            <div><label class="form-label">Estatus</label><select name="estatus" class="form-control"><?php foreach($estatuses as $e): ?><option value="<?=$e?>"><?=ucfirst($e)?></option><?php endforeach; ?></select></div>
        </div>
        <div class="mb-14"><label class="form-label">Objeto del Convenio</label><textarea name="objeto" class="form-control" rows="2"></textarea></div>
        <div class="mb-14"><label class="form-label">Responsable</label><input type="text" name="responsable" class="form-control"></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;" class="mb-14">
            <div><label class="form-label">Inicio</label><input type="date" name="fecha_inicio" class="form-control"></div>
            <div><label class="form-label">Fin / Vigencia</label><input type="date" name="fecha_fin" class="form-control"></div>
        </div>
        <div><label class="form-label">Observaciones</label><textarea name="observaciones" class="form-control" rows="2"></textarea></div>
    </div>
    <div class="modal-footer"><button type="button" onclick="cerrarModal('modalCrear')" class="btn" style="background:#f1f5f9;color:#475569;">Cancelar</button><button type="submit" class="btn btn-primary" style="background:#6d28d9;border-color:#6d28d9;"><i class="fa-solid fa-check"></i> Guardar</button></div>
    </form>
</div></div>

<!-- Modal Editar -->
<div class="modal-backdrop" id="modalEditar"><div class="modal">
    <div class="modal-header"><h3 class="modal-title"><i class="fa-solid fa-pencil"></i> Editar Convenio</h3><button class="modal-close" onclick="cerrarModal('modalEditar')"><i class="fa-solid fa-xmark"></i></button></div>
    <form method="POST" action="api/convenios.php"><?=csrfField()?><input type="hidden" name="accion" value="editar"><input type="hidden" name="id" id="ed_id">
    <div class="modal-body">
        <div class="mb-14" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:8px 12px;font-size:.82rem;color:#64748b;">Folio: <strong id="ed_folio_lbl" style="color:#0f172a;font-family:monospace;"></strong></div>
        <div class="mb-14"><label class="form-label">Institución *</label><input type="text" name="institucion" id="ed_inst" class="form-control" required></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;" class="mb-14">
            <div><label class="form-label">Tipo</label><select name="tipo" id="ed_tipo" class="form-control"><?php foreach($tipos as $t): ?><option value="<?=$t?>"><?=ucfirst($t)?></option><?php endforeach; ?></select></div>
            <div><label class="form-label">Estatus</label><select name="estatus" id="ed_est" class="form-control"><?php foreach($estatuses as $e): ?><option value="<?=$e?>"><?=ucfirst($e)?></option><?php endforeach; ?></select></div>
        </div>
        <div class="mb-14"><label class="form-label">Objeto</label><textarea name="objeto" id="ed_obj" class="form-control" rows="2"></textarea></div>
        <div class="mb-14"><label class="form-label">Responsable</label><input type="text" name="responsable" id="ed_resp" class="form-control"></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;" class="mb-14">
            <div><label class="form-label">Inicio</label><input type="date" name="fecha_inicio" id="ed_fi" class="form-control"></div>
            <div><label class="form-label">Fin</label><input type="date" name="fecha_fin" id="ed_ff" class="form-control"></div>
        </div>
        <div><label class="form-label">Observaciones</label><textarea name="observaciones" id="ed_obs" class="form-control" rows="2"></textarea></div>
    </div>
    <div class="modal-footer"><button type="button" onclick="cerrarModal('modalEditar')" class="btn" style="background:#f1f5f9;color:#475569;">Cancelar</button><button type="submit" class="btn btn-primary" style="background:#6d28d9;border-color:#6d28d9;"><i class="fa-solid fa-check"></i> Actualizar</button></div>
    </form>
</div></div>
<form method="POST" action="api/convenios.php" id="frmEliminar" style="display:none;"><?=csrfField()?><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" id="del_id"></form>

<script>
function cerrarModal(id){document.getElementById(id).classList.remove('active');}
function abrirModalCrear(){document.getElementById('modalCrear').classList.add('active');}
function abrirModalEditar(c){
    document.getElementById('ed_id').value=c.id;document.getElementById('ed_folio_lbl').textContent=c.folio||'—';
    document.getElementById('ed_inst').value=c.institucion||'';document.getElementById('ed_obj').value=c.objeto||'';
    document.getElementById('ed_resp').value=c.responsable||'';document.getElementById('ed_fi').value=c.fecha_inicio||'';
    document.getElementById('ed_ff').value=c.fecha_fin||'';document.getElementById('ed_obs').value=c.observaciones||'';
    const sel=(id,val)=>{const s=document.getElementById(id);[...s.options].forEach(o=>o.selected=o.value===val);};
    sel('ed_tipo',c.tipo);sel('ed_est',c.estatus);document.getElementById('modalEditar').classList.add('active');
}
function eliminar(id,folio){if(!confirm(`¿Eliminar el convenio "${folio}"?`))return;document.getElementById('del_id').value=id;document.getElementById('frmEliminar').submit();}
document.addEventListener('keydown',e=>{if(e.key==='Escape')document.querySelectorAll('.modal-backdrop.active').forEach(m=>m.classList.remove('active'));});
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
