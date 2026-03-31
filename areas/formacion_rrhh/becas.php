<?php
/**
 * COMECyT — Becas y Apoyos RRHH
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
$stmt=$pdo->prepare("SELECT * FROM rrhh_becas $where ORDER BY fecha_fin ASC NULLS LAST, created_at DESC");
$stmt->execute($params);$becas=$stmt->fetchAll();

$tipos=['posgrado','idioma','certificacion','otro'];
$estatuses=['activa','concluida','cancelada'];

$fc=getParam('flash');$flash='';$tipoF='';
if($fc==='creado'){$flash='Beca registrada.';$tipoF='success';}
elseif($fc==='editado'){$flash='Beca actualizada.';$tipoF='success';}
elseif($fc==='eliminado'){$flash='Beca eliminada.';$tipoF='success';}

$pageTitle='Becas y Apoyos RRHH'; $activeMenu='becas';
require_once __DIR__ . '/../../includes/header_admin.php';
?>
<style>
:root{--rrhh-primary:#0f766e;--rrhh-accent:#B19A6D;}
.badge-activa{background:#dcfce7;color:#16a34a;}.badge-concluida{background:#f1f5f9;color:#475569;}.badge-cancelada{background:#fee2e2;color:#dc2626;}
.badge-posgrado{background:#ede9fe;color:#6d28d9;}.badge-idioma{background:#e0f2fe;color:#0284c7;}.badge-certificacion{background:#fef3c7;color:#d97706;}.badge-otro{background:#f1f5f9;color:#475569;}
.badge-pill{padding:3px 10px;border-radius:20px;font-size:.74rem;font-weight:600;}
.becas-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(290px,1fr));gap:18px;}
.beca-card{background:#fff;border-radius:16px;border:1px solid #e2e8f0;padding:22px;box-shadow:0 4px 12px rgba(0,0,0,.04);transition:all .25s;border-top:4px solid var(--rrhh-primary);}
.beca-card:hover{transform:translateY(-4px);box-shadow:0 12px 28px rgba(15,118,110,.1);border-top-color:var(--rrhh-accent);}
.beca-nombre{font-size:1rem;font-weight:700;color:#0f172a;margin-bottom:6px;}
.beca-tipo{margin-bottom:12px;}
.beca-meta-item{display:flex;align-items:center;gap:7px;font-size:.8rem;color:#64748b;margin-top:5px;}
.beca-meta-item i{color:var(--rrhh-accent);width:13px;}
.beca-actions{display:flex;gap:6px;margin-top:14px;padding-top:12px;border-top:1px solid #f1f5f9;}
.action-btn{width:30px;height:30px;border-radius:7px;border:1px solid #e2e8f0;background:transparent;cursor:pointer;font-size:.8rem;display:inline-flex;align-items:center;justify-content:center;transition:all .15s;}
.action-btn:hover{border-color:var(--rrhh-primary);color:var(--rrhh-primary);}.action-btn.danger:hover{border-color:#ef4444;color:#ef4444;background:#fef2f2;}
.modal-backdrop{display:none;position:fixed;inset:0;background:rgba(15,23,42,.7);backdrop-filter:blur(8px);z-index:2000;align-items:center;justify-content:center;padding:20px;}
.modal-backdrop.active{display:flex;}
.modal{background:#fff;width:100%;max-width:580px;border-radius:20px;overflow:hidden;display:flex;flex-direction:column;max-height:90vh;}
.modal-header{background:linear-gradient(135deg,#0f766e,#134e4a);color:#fff;padding:1.25rem 1.5rem;display:flex;align-items:center;justify-content:space-between;}
.modal-title{margin:0;font-size:1.1rem;font-weight:800;display:flex;align-items:center;gap:10px;}
.modal-close{background:none;border:none;color:rgba(255,255,255,.8);cursor:pointer;font-size:1.4rem;}.modal-close:hover{color:#fff;}
.modal-body{padding:1.5rem 2rem;overflow-y:auto;}.modal-footer{padding:1.25rem 2rem;border-top:1px solid #f1f5f9;background:#fafafa;display:flex;gap:10px;justify-content:flex-end;}
.form-label{font-weight:700;color:#475569;font-size:.85rem;margin-bottom:5px;display:block;}
.form-control{border-radius:10px;border:1.5px solid #e2e8f0;background:#f8fafc;padding:9px 13px;font-family:inherit;font-size:.9rem;width:100%;box-sizing:border-box;transition:all .2s;}
.form-control:focus{border-color:#0f766e;outline:none;}.mb-14{margin-bottom:14px;}
</style>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
    <div><h2 style="font-weight:800;color:#0f172a;margin:0;"><i class="fa-solid fa-award" style="color:#0f766e;"></i> Becas y Apoyos RRHH</h2><p style="color:#64748b;margin:0;">Seguimiento de apoyos educativos al personal del área.</p></div>
    <div style="display:flex;gap:8px;">
        <a href="api/becas.php?accion=exportar_csv&csrf_token=<?=$_SESSION['csrf_token']?>" class="btn" style="background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;font-size:.88rem;"><i class="fa-solid fa-file-csv"></i> CSV</a>
        <button class="btn btn-primary" onclick="abrirModalCrear()" style="background:#0f766e;border-color:#0f766e;"><i class="fa-solid fa-plus"></i> Nueva Beca</button>
    </div>
</div>
<?php if($flash): ?><div class="alert alert-<?=$tipoF?> alert-dismissible fade show" role="alert"><?=esc($flash)?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<div style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;">
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;">
        <select name="tipo" class="form-control" style="width:160px;" onchange="this.form.submit()"><option value="">Todos los tipos</option><?php foreach($tipos as $t): ?><option value="<?=$t?>" <?=$filtroTipo===$t?'selected':''?>><?=ucfirst($t)?></option><?php endforeach; ?></select>
        <select name="estatus" class="form-control" style="width:150px;" onchange="this.form.submit()"><option value="">Todos los estatus</option><?php foreach($estatuses as $e): ?><option value="<?=$e?>" <?=$filtroEst===$e?'selected':''?>><?=ucfirst($e)?></option><?php endforeach; ?></select>
        <?php if($filtroTipo||$filtroEst): ?><a href="becas.php" class="btn" style="background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;padding:9px 14px;border-radius:10px;font-size:.88rem;">Limpiar</a><?php endif; ?>
    </form>
    <span style="margin-left:auto;align-self:center;font-size:.85rem;color:#64748b;"><?=count($becas)?> apoyos</span>
</div>

<?php if(empty($becas)): ?>
<div style="background:#fff;border-radius:18px;padding:70px;text-align:center;color:#94a3b8;border:1px solid #e2e8f0;">
    <i class="fa-solid fa-award" style="font-size:2.8rem;opacity:.3;margin-bottom:14px;display:block;"></i><p>No hay becas registradas.</p></div>
<?php else: ?>
<div class="becas-grid">
<?php foreach($becas as $b): $cls='badge-'.$b['estatus'];$clsTipo='badge-'.$b['tipo']; ?>
<div class="beca-card">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px;">
        <span class="badge-pill <?=$clsTipo?>"><?=ucfirst(esc($b['tipo']))?></span>
        <span class="badge-pill <?=$cls?>"><?=ucfirst(esc($b['estatus']))?></span>
    </div>
    <div class="beca-nombre"><?=esc($b['beneficiario'])?></div>
    <?php if($b['monto']): ?><div style="font-size:.85rem;font-weight:700;color:var(--rrhh-primary);">$<?=number_format($b['monto'],0)?> MXN</div><?php endif; ?>
    <div class="beca-meta-item"><i class="fa-solid fa-building-columns"></i><?=esc($b['institucion']?:'Sin institución')?></div>
    <?php if($b['fecha_inicio']||$b['fecha_fin']): ?><div class="beca-meta-item"><i class="fa-solid fa-calendar-days"></i><?=$b['fecha_inicio']?date('d/m/Y',strtotime($b['fecha_inicio'])):'—'?> – <?=$b['fecha_fin']?date('d/m/Y',strtotime($b['fecha_fin'])):'—'?></div><?php endif; ?>
    <?php if($b['notas']): ?><div class="beca-meta-item" style="white-space:normal;"><i class="fa-solid fa-note-sticky"></i><?=esc(mb_strimwidth($b['notas'],0,70,'...'))?></div><?php endif; ?>
    <div class="beca-actions">
        <button class="action-btn" style="flex:1;" onclick="abrirModalEditar(<?=htmlspecialchars(json_encode($b),ENT_QUOTES)?>)"><i class="fa-solid fa-pencil"></i> Editar</button>
        <button class="action-btn danger" onclick="eliminar(<?=$b['id']?>,'<?=esc($b['beneficiario'])?>')"><i class="fa-solid fa-trash-can"></i></button>
    </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Modal Crear -->
<div class="modal-backdrop" id="modalCrear"><div class="modal">
    <div class="modal-header"><h3 class="modal-title"><i class="fa-solid fa-award"></i> Nueva Beca / Apoyo</h3><button class="modal-close" onclick="cerrarModal('modalCrear')"><i class="fa-solid fa-xmark"></i></button></div>
    <form method="POST" action="api/becas.php"><?=csrfField()?><input type="hidden" name="accion" value="crear">
    <div class="modal-body">
        <div class="mb-14"><label class="form-label">Beneficiario *</label><input type="text" name="beneficiario" class="form-control" required></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;" class="mb-14">
            <div><label class="form-label">Tipo</label><select name="tipo" class="form-control"><?php foreach($tipos as $t): ?><option value="<?=$t?>"><?=ucfirst($t)?></option><?php endforeach; ?></select></div>
            <div><label class="form-label">Estatus</label><select name="estatus" class="form-control"><?php foreach($estatuses as $e): ?><option value="<?=$e?>"><?=ucfirst($e)?></option><?php endforeach; ?></select></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;" class="mb-14">
            <div><label class="form-label">Institución</label><input type="text" name="institucion" class="form-control"></div>
            <div><label class="form-label">Monto (MXN)</label><input type="number" name="monto" class="form-control" step="1"></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;" class="mb-14">
            <div><label class="form-label">Inicio</label><input type="date" name="fecha_inicio" class="form-control"></div>
            <div><label class="form-label">Fin</label><input type="date" name="fecha_fin" class="form-control"></div>
        </div>
        <div><label class="form-label">Notas</label><textarea name="notas" class="form-control" rows="2"></textarea></div>
    </div>
    <div class="modal-footer"><button type="button" onclick="cerrarModal('modalCrear')" class="btn" style="background:#f1f5f9;color:#475569;">Cancelar</button><button type="submit" class="btn btn-primary" style="background:#0f766e;border-color:#0f766e;"><i class="fa-solid fa-check"></i> Guardar</button></div>
    </form>
</div></div>

<!-- Modal Editar -->
<div class="modal-backdrop" id="modalEditar"><div class="modal">
    <div class="modal-header"><h3 class="modal-title"><i class="fa-solid fa-pencil"></i> Editar Beca</h3><button class="modal-close" onclick="cerrarModal('modalEditar')"><i class="fa-solid fa-xmark"></i></button></div>
    <form method="POST" action="api/becas.php"><?=csrfField()?><input type="hidden" name="accion" value="editar"><input type="hidden" name="id" id="ed_id">
    <div class="modal-body">
        <div class="mb-14"><label class="form-label">Beneficiario *</label><input type="text" name="beneficiario" id="ed_ben" class="form-control" required></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;" class="mb-14">
            <div><label class="form-label">Tipo</label><select name="tipo" id="ed_tipo" class="form-control"><?php foreach($tipos as $t): ?><option value="<?=$t?>"><?=ucfirst($t)?></option><?php endforeach; ?></select></div>
            <div><label class="form-label">Estatus</label><select name="estatus" id="ed_est" class="form-control"><?php foreach($estatuses as $e): ?><option value="<?=$e?>"><?=ucfirst($e)?></option><?php endforeach; ?></select></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;" class="mb-14">
            <div><label class="form-label">Institución</label><input type="text" name="institucion" id="ed_inst" class="form-control"></div>
            <div><label class="form-label">Monto</label><input type="number" name="monto" id="ed_monto" class="form-control" step="1"></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;" class="mb-14">
            <div><label class="form-label">Inicio</label><input type="date" name="fecha_inicio" id="ed_fi" class="form-control"></div>
            <div><label class="form-label">Fin</label><input type="date" name="fecha_fin" id="ed_ff" class="form-control"></div>
        </div>
        <div><label class="form-label">Notas</label><textarea name="notas" id="ed_notas" class="form-control" rows="2"></textarea></div>
    </div>
    <div class="modal-footer"><button type="button" onclick="cerrarModal('modalEditar')" class="btn" style="background:#f1f5f9;color:#475569;">Cancelar</button><button type="submit" class="btn btn-primary" style="background:#0f766e;border-color:#0f766e;"><i class="fa-solid fa-check"></i> Actualizar</button></div>
    </form>
</div></div>
<form method="POST" action="api/becas.php" id="frmEliminar" style="display:none;"><?=csrfField()?><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" id="del_id"></form>
<script>
function cerrarModal(id){document.getElementById(id).classList.remove('active');}
function abrirModalCrear(){document.getElementById('modalCrear').classList.add('active');}
function abrirModalEditar(b){
    document.getElementById('ed_id').value=b.id;document.getElementById('ed_ben').value=b.beneficiario;
    document.getElementById('ed_inst').value=b.institucion||'';document.getElementById('ed_monto').value=b.monto||'';
    document.getElementById('ed_fi').value=b.fecha_inicio||'';document.getElementById('ed_ff').value=b.fecha_fin||'';
    document.getElementById('ed_notas').value=b.notas||'';
    const sel=(id,val)=>{const s=document.getElementById(id);[...s.options].forEach(o=>o.selected=o.value===val);};
    sel('ed_tipo',b.tipo);sel('ed_est',b.estatus);document.getElementById('modalEditar').classList.add('active');
}
function eliminar(id,nombre){if(!confirm(`¿Eliminar la beca de "${nombre}"?`))return;document.getElementById('del_id').value=id;document.getElementById('frmEliminar').submit();}
document.addEventListener('keydown',e=>{if(e.key==='Escape')document.querySelectorAll('.modal-backdrop.active').forEach(m=>m.classList.remove('active'));});
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
