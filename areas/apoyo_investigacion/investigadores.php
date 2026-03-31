<?php
/**
 * COMECyT — Investigadores (Apoyo Investigación)
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../config/auth.php';
verificarSesionAdmin();
$pdo = getConnection();

$filtroSni = getParam('sni','');$filtroActivo = getParam('activo','');
$where='WHERE 1=1';$params=[];
if($filtroSni){$where.=' AND nivel_sni=?';$params[]=$filtroSni;}
if($filtroActivo!==''){$where.=' AND activo=?';$params[]=($filtroActivo==='1'?true:false);}
$stmt=$pdo->prepare("SELECT * FROM inv_investigadores $where ORDER BY nombre ASC");
$stmt->execute($params);$investigadores=$stmt->fetchAll();

$nivelesSni=['Candidato','I','II','III','Emérito'];
$fc=getParam('flash');$flash='';$tipoF='';
if($fc==='creado'){$flash='Investigador registrado.';$tipoF='success';}
elseif($fc==='editado'){$flash='Investigador actualizado.';$tipoF='success';}
elseif($fc==='eliminado'){$flash='Investigador eliminado.';$tipoF='success';}

$pageTitle='Investigadores';$activeMenu='investigadores';
require_once __DIR__ . '/../../includes/header_admin.php';
?>
<style>
:root{--inv-primary:#3730a3;--inv-accent:#B19A6D;}
.investigadores-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:18px;}
.inv-card{background:#fff;border-radius:16px;border:1px solid #e2e8f0;padding:22px;box-shadow:0 4px 12px rgba(0,0,0,.04);transition:all .25s;position:relative;}
.inv-card:hover{transform:translateY(-4px);box-shadow:0 12px 28px rgba(55,48,163,.1);border-color:var(--inv-accent);}
.inv-card-header{display:flex;align-items:center;gap:14px;margin-bottom:14px;}
.inv-avatar{width:52px;height:52px;border-radius:50%;background:linear-gradient(135deg,#3730a3,#1e1b4b);display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.4rem;font-weight:700;flex-shrink:0;}
.inv-card-nombre{font-size:1rem;font-weight:700;color:#0f172a;margin-bottom:3px;}
.inv-card-especialidad{font-size:.8rem;color:#64748b;}
.sni-badge{padding:3px 10px;border-radius:12px;font-size:.72rem;font-weight:700;background:#ede9fe;color:#6d28d9;}
.sni-candidato{background:#fef3c7;color:#d97706;}
.inv-meta-item{display:flex;align-items:center;gap:7px;font-size:.8rem;color:#64748b;margin-top:5px;}
.inv-meta-item i{color:var(--inv-accent);width:13px;}
.inv-actions{display:flex;gap:6px;margin-top:14px;padding-top:12px;border-top:1px solid #f1f5f9;}
.action-btn{width:30px;height:30px;border-radius:7px;border:1px solid #e2e8f0;background:transparent;cursor:pointer;font-size:.8rem;display:inline-flex;align-items:center;justify-content:center;transition:all .15s;}
.action-btn:hover{border-color:var(--inv-primary);color:var(--inv-primary);}.action-btn.danger:hover{border-color:#ef4444;color:#ef4444;background:#fef2f2;}
.modal-backdrop{display:none;position:fixed;inset:0;background:rgba(15,23,42,.7);backdrop-filter:blur(8px);z-index:2000;align-items:center;justify-content:center;padding:20px;}
.modal-backdrop.active{display:flex;}
.modal{background:#fff;width:100%;max-width:580px;border-radius:20px;overflow:hidden;display:flex;flex-direction:column;max-height:90vh;}
.modal-header{background:linear-gradient(135deg,#3730a3,#1e1b4b);color:#fff;padding:1.25rem 1.5rem;display:flex;align-items:center;justify-content:space-between;}
.modal-title{margin:0;font-size:1.1rem;font-weight:800;display:flex;align-items:center;gap:10px;}
.modal-close{background:none;border:none;color:rgba(255,255,255,.8);cursor:pointer;font-size:1.4rem;}.modal-close:hover{color:#fff;}
.modal-body{padding:1.5rem 2rem;overflow-y:auto;}.modal-footer{padding:1.25rem 2rem;border-top:1px solid #f1f5f9;background:#fafafa;display:flex;gap:10px;justify-content:flex-end;}
.form-label{font-weight:700;color:#475569;font-size:.85rem;margin-bottom:5px;display:block;}
.form-control{border-radius:10px;border:1.5px solid #e2e8f0;background:#f8fafc;padding:9px 13px;font-family:inherit;font-size:.9rem;width:100%;box-sizing:border-box;transition:all .2s;}
.form-control:focus{border-color:#3730a3;outline:none;}.mb-14{margin-bottom:14px;}
</style>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
    <div><h2 style="font-weight:800;color:#0f172a;margin:0;"><i class="fa-solid fa-user-astronaut" style="color:#3730a3;"></i> Investigadores</h2><p style="color:#64748b;margin:0;">Control del padrón de investigadores y Nivel SNI.</p></div>
    <div style="display:flex;gap:8px;">
        <a href="api/investigadores.php?accion=exportar_csv&csrf_token=<?=$_SESSION['csrf_token']?>" class="btn" style="background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;font-size:.88rem;"><i class="fa-solid fa-file-csv"></i> Exportar CSV</a>
        <button class="btn btn-primary" onclick="abrirModalCrear()" style="background:#3730a3;border-color:#3730a3;"><i class="fa-solid fa-plus"></i> Nuevo Investigador</button>
    </div>
</div>
<?php if($flash): ?><div class="alert alert-<?=$tipoF?> alert-dismissible fade show" role="alert"><?=esc($flash)?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<div style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;">
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;">
        <select name="sni" class="form-control" style="width:160px;" onchange="this.form.submit()"><option value="">Todos los SNI</option><?php foreach($nivelesSni as $n): ?><option value="<?=$n?>" <?=$filtroSni===$n?'selected':''?>>SNI <?=$n?></option><?php endforeach; ?></select>
        <select name="activo" class="form-control" style="width:140px;" onchange="this.form.submit()"><option value="">Todos</option><option value="1" <?=$filtroActivo==='1'?'selected':''?>>Activos</option><option value="0" <?=$filtroActivo==='0'?'selected':''?>>Inactivos</option></select>
        <?php if($filtroSni||$filtroActivo!==''): ?><a href="investigadores.php" class="btn" style="background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;padding:9px 14px;border-radius:10px;font-size:.88rem;">Limpiar</a><?php endif; ?>
    </form>
    <span style="margin-left:auto;align-self:center;font-size:.85rem;color:#64748b;"><?=count($investigadores)?> investigadores</span>
</div>

<?php if(empty($investigadores)): ?>
<div style="background:#fff;border-radius:18px;padding:70px;text-align:center;color:#94a3b8;border:1px solid #e2e8f0;">
    <i class="fa-solid fa-user-astronaut" style="font-size:2.8rem;opacity:.3;margin-bottom:14px;display:block;"></i><p>No hay investigadores registrados.</p></div>
<?php else: ?>
<div class="investigadores-grid">
<?php foreach($investigadores as $inv): $ini=mb_strtoupper(mb_substr($inv['nombre'],0,1,'UTF-8')); ?>
    <div class="inv-card">
        <div class="inv-card-header">
            <div class="inv-avatar"><?=$ini?></div>
            <div>
                <div class="inv-card-nombre"><?=esc($inv['nombre'])?></div>
                <div class="inv-card-especialidad"><?=esc($inv['especialidad']?:'Sin especialidad')?></div>
            </div>
        </div>
        <?php if($inv['nivel_sni']): ?><span class="sni-badge <?=$inv['nivel_sni']==='Candidato'?'sni-candidato':''?>">SNI <?=esc($inv['nivel_sni'])?></span><?php endif; ?>
        <div class="inv-meta-item"><i class="fa-solid fa-building-columns"></i><?=esc($inv['institucion']?:'—')?></div>
        <?php if($inv['cvu']): ?><div class="inv-meta-item"><i class="fa-solid fa-id-badge"></i>CVU: <?=esc($inv['cvu'])?></div><?php endif; ?>
        <?php if($inv['correo']): ?><div class="inv-meta-item"><i class="fa-solid fa-envelope"></i><?=esc($inv['correo'])?></div><?php endif; ?>
        <?php if(!$inv['activo']): ?><div style="margin-top:8px;"><span style="background:#fee2e2;color:#dc2626;padding:2px 8px;border-radius:10px;font-size:.72rem;font-weight:700;">Inactivo</span></div><?php endif; ?>
        <div class="inv-actions">
            <button class="action-btn" style="flex:1;" onclick="abrirModalEditar(<?=htmlspecialchars(json_encode($inv),ENT_QUOTES)?>)"><i class="fa-solid fa-pencil"></i></button>
            <button class="action-btn danger" onclick="eliminar(<?=$inv['id']?>,'<?=esc($inv['nombre'])?>')"><i class="fa-solid fa-trash-can"></i></button>
        </div>
    </div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Modal Crear -->
<div class="modal-backdrop" id="modalCrear"><div class="modal">
    <div class="modal-header"><h3 class="modal-title"><i class="fa-solid fa-user-plus"></i> Nuevo Investigador</h3><button class="modal-close" onclick="cerrarModal('modalCrear')"><i class="fa-solid fa-xmark"></i></button></div>
    <form method="POST" action="api/investigadores.php"><?=csrfField()?><input type="hidden" name="accion" value="crear">
    <div class="modal-body">
        <div class="mb-14"><label class="form-label">Nombre Completo *</label><input type="text" name="nombre" class="form-control" required></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;" class="mb-14">
            <div><label class="form-label">CVU CONACYT</label><input type="text" name="cvu" class="form-control" placeholder="Ej. 12345"></div>
            <div><label class="form-label">Nivel SNI</label><select name="nivel_sni" class="form-control"><option value="">Sin SNI</option><?php foreach($nivelesSni as $n): ?><option value="<?=$n?>"><?=$n?></option><?php endforeach; ?></select></div>
        </div>
        <div class="mb-14"><label class="form-label">Institución</label><input type="text" name="institucion" class="form-control"></div>
        <div class="mb-14"><label class="form-label">Especialidad / Área</label><input type="text" name="especialidad" class="form-control"></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;" class="mb-14">
            <div><label class="form-label">Correo</label><input type="email" name="correo" class="form-control"></div>
            <div><label class="form-label">Estatus</label><select name="activo" class="form-control"><option value="1">Activo</option><option value="0">Inactivo</option></select></div>
        </div>
    </div>
    <div class="modal-footer"><button type="button" onclick="cerrarModal('modalCrear')" class="btn" style="background:#f1f5f9;color:#475569;">Cancelar</button><button type="submit" class="btn btn-primary" style="background:#3730a3;border-color:#3730a3;"><i class="fa-solid fa-check"></i> Guardar</button></div>
    </form>
</div></div>

<!-- Modal Editar -->
<div class="modal-backdrop" id="modalEditar"><div class="modal">
    <div class="modal-header"><h3 class="modal-title"><i class="fa-solid fa-pencil"></i> Editar Investigador</h3><button class="modal-close" onclick="cerrarModal('modalEditar')"><i class="fa-solid fa-xmark"></i></button></div>
    <form method="POST" action="api/investigadores.php"><?=csrfField()?><input type="hidden" name="accion" value="editar"><input type="hidden" name="id" id="ed_id">
    <div class="modal-body">
        <div class="mb-14"><label class="form-label">Nombre *</label><input type="text" name="nombre" id="ed_nom" class="form-control" required></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;" class="mb-14">
            <div><label class="form-label">CVU</label><input type="text" name="cvu" id="ed_cvu" class="form-control"></div>
            <div><label class="form-label">Nivel SNI</label><select name="nivel_sni" id="ed_sni" class="form-control"><option value="">Sin SNI</option><?php foreach($nivelesSni as $n): ?><option value="<?=$n?>"><?=$n?></option><?php endforeach; ?></select></div>
        </div>
        <div class="mb-14"><label class="form-label">Institución</label><input type="text" name="institucion" id="ed_inst" class="form-control"></div>
        <div class="mb-14"><label class="form-label">Especialidad</label><input type="text" name="especialidad" id="ed_esp" class="form-control"></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;" class="mb-14">
            <div><label class="form-label">Correo</label><input type="email" name="correo" id="ed_correo" class="form-control"></div>
            <div><label class="form-label">Estatus</label><select name="activo" id="ed_activo" class="form-control"><option value="1">Activo</option><option value="0">Inactivo</option></select></div>
        </div>
    </div>
    <div class="modal-footer"><button type="button" onclick="cerrarModal('modalEditar')" class="btn" style="background:#f1f5f9;color:#475569;">Cancelar</button><button type="submit" class="btn btn-primary" style="background:#3730a3;border-color:#3730a3;"><i class="fa-solid fa-check"></i> Actualizar</button></div>
    </form>
</div></div>
<form method="POST" action="api/investigadores.php" id="frmEliminar" style="display:none;"><?=csrfField()?><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" id="del_id"></form>

<script>
function cerrarModal(id){document.getElementById(id).classList.remove('active');}
function abrirModalCrear(){document.getElementById('modalCrear').classList.add('active');}
function abrirModalEditar(i){
    document.getElementById('ed_id').value=i.id;document.getElementById('ed_nom').value=i.nombre;
    document.getElementById('ed_cvu').value=i.cvu||'';document.getElementById('ed_inst').value=i.institucion||'';
    document.getElementById('ed_esp').value=i.especialidad||'';document.getElementById('ed_correo').value=i.correo||'';
    const sel=(id,val)=>{const s=document.getElementById(id);[...s.options].forEach(o=>o.selected=o.value==val);};
    sel('ed_sni',i.nivel_sni||'');sel('ed_activo',i.activo?'1':'0');
    document.getElementById('modalEditar').classList.add('active');
}
function eliminar(id,nombre){if(!confirm(`¿Eliminar a "${nombre}"?`))return;document.getElementById('del_id').value=id;document.getElementById('frmEliminar').submit();}
document.addEventListener('keydown',e=>{if(e.key==='Escape')document.querySelectorAll('.modal-backdrop.active').forEach(m=>m.classList.remove('active'));});
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
