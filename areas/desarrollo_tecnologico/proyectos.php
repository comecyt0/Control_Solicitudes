<?php
/**
 * COMECyT — Proyectos Tecnológicos (DT)
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
$stmt=$pdo->prepare("SELECT * FROM dt_proyectos $where ORDER BY fecha_inicio ASC NULLS LAST, created_at DESC");
$stmt->execute($params);$proyectos=$stmt->fetchAll();

$tipos=['plataforma','sistema','app','otro'];
$estatuses=['activo','en_desarrollo','pausado','concluido'];

$fc=getParam('flash');$flash='';$tipoF='';
if($fc==='creado'){$flash='Proyecto registrado.';$tipoF='success';}
elseif($fc==='editado'){$flash='Proyecto actualizado.';$tipoF='success';}
elseif($fc==='eliminado'){$flash='Proyecto eliminado.';$tipoF='success';}

$pageTitle='Proyectos Tecnológicos'; $activeMenu='proyectos';
require_once __DIR__ . '/../../includes/header_admin.php';
?>
<style>
:root{--dt-primary:#6d28d9;--dt-accent:#B19A6D;}
.badge-activo{background:#dcfce7;color:#16a34a;}.badge-en_desarrollo{background:#e0f2fe;color:#0284c7;}.badge-pausado{background:#fef3c7;color:#d97706;}.badge-concluido{background:#f1f5f9;color:#475569;}
.badge-plataforma{background:#ede9fe;color:#6d28d9;}.badge-sistema{background:#e0f2fe;color:#0369a1;}.badge-app{background:#fce7f3;color:#db2777;}.badge-otro{background:#f1f5f9;color:#475569;}
.badge-pill{padding:3px 10px;border-radius:20px;font-size:.74rem;font-weight:600;}
.tech-pill{background:#ede9fe;color:#6d28d9;padding:2px 7px;border-radius:8px;font-size:.72rem;font-weight:600;display:inline-block;margin:1px;}
.dt-table{width:100%;border-collapse:collapse;}.dt-table th{text-align:left;padding:12px 16px;font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#64748b;background:#f8fafc;border-bottom:2px solid #e2e8f0;}
.dt-table td{padding:13px 16px;border-bottom:1px solid #f1f5f9;vertical-align:middle;}.dt-table tr:last-child td{border-bottom:none;}.dt-table tr:hover td{background:#f8fafc;}
.action-btn{width:30px;height:30px;border-radius:7px;border:1px solid #e2e8f0;background:transparent;cursor:pointer;font-size:.8rem;display:inline-flex;align-items:center;justify-content:center;transition:all .15s;}
.action-btn:hover{border-color:var(--dt-primary);color:var(--dt-primary);}.action-btn.danger:hover{border-color:#ef4444;color:#ef4444;background:#fef2f2;}
.modal-backdrop{display:none;position:fixed;inset:0;background:rgba(15,23,42,.7);backdrop-filter:blur(8px);z-index:2000;align-items:center;justify-content:center;padding:20px;}
.modal-backdrop.active{display:flex;}
.modal{background:#fff;width:100%;max-width:620px;border-radius:20px;overflow:hidden;display:flex;flex-direction:column;max-height:90vh;}
.modal-header{background:linear-gradient(135deg,#6d28d9,#4c1d95);color:#fff;padding:1.25rem 1.5rem;display:flex;align-items:center;justify-content:space-between;}
.modal-title{margin:0;font-size:1.1rem;font-weight:800;display:flex;align-items:center;gap:10px;}
.modal-close{background:none;border:none;color:rgba(255,255,255,.8);cursor:pointer;font-size:1.4rem;}.modal-close:hover{color:#fff;}
.modal-body{padding:1.5rem 2rem;overflow-y:auto;}.modal-footer{padding:1.25rem 2rem;border-top:1px solid #f1f5f9;background:#fafafa;display:flex;gap:10px;justify-content:flex-end;}
.form-label{font-weight:700;color:#475569;font-size:.85rem;margin-bottom:5px;display:block;}
.form-control{border-radius:10px;border:1.5px solid #e2e8f0;background:#f8fafc;padding:9px 13px;font-family:inherit;font-size:.9rem;width:100%;box-sizing:border-box;transition:all .2s;}
.form-control:focus{border-color:#6d28d9;outline:none;}.mb-14{margin-bottom:14px;}
</style>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
    <div><h2 style="font-weight:800;color:#0f172a;margin:0;"><i class="fa-solid fa-diagram-project" style="color:#6d28d9;"></i> Proyectos Tecnológicos</h2><p style="color:#64748b;margin:0;">Registro y seguimiento de proyectos tecnológicos del área.</p></div>
    <button class="btn btn-primary" onclick="abrirModalCrear()" style="background:#6d28d9;border-color:#6d28d9;"><i class="fa-solid fa-plus"></i> Nuevo Proyecto</button>
</div>
<?php if($flash): ?><div class="alert alert-<?=$tipoF?> alert-dismissible fade show" role="alert"><?=esc($flash)?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<div style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;">
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;">
        <select name="tipo" class="form-control" style="width:150px;" onchange="this.form.submit()"><option value="">Todos los tipos</option><?php foreach($tipos as $t): ?><option value="<?=$t?>" <?=$filtroTipo===$t?'selected':''?>><?=ucfirst($t)?></option><?php endforeach; ?></select>
        <select name="estatus" class="form-control" style="width:160px;" onchange="this.form.submit()"><option value="">Todos los estatus</option><?php foreach($estatuses as $e): ?><option value="<?=$e?>" <?=$filtroEst===$e?'selected':''?>><?=ucfirst(str_replace('_',' ',$e))?></option><?php endforeach; ?></select>
        <?php if($filtroEst||$filtroTipo): ?><a href="proyectos.php" class="btn" style="background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;padding:9px 14px;border-radius:10px;font-size:.88rem;">Limpiar</a><?php endif; ?>
    </form>
    <span style="margin-left:auto;align-self:center;font-size:.85rem;color:#64748b;"><?=count($proyectos)?> proyectos</span>
</div>

<div style="background:#fff;border-radius:18px;border:1px solid #e2e8f0;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,.04);">
<?php if(empty($proyectos)): ?>
<div style="padding:70px;text-align:center;color:#94a3b8;"><i class="fa-solid fa-diagram-project" style="font-size:2.8rem;opacity:.3;margin-bottom:14px;display:block;"></i><p>No hay proyectos registrados.</p></div>
<?php else: ?>
<div style="overflow-x:auto;">
<table class="dt-table">
    <thead><tr><th>#</th><th>Nombre</th><th>Tipo</th><th>Líder</th><th>Tecnologías</th><th>Periodo</th><th>Estatus</th><th>Acciones</th></tr></thead>
    <tbody>
    <?php foreach($proyectos as $p): $techs=array_filter(array_map('trim',explode(',',$p['tecnologias']??''))); ?>
    <tr>
        <td style="color:#94a3b8;font-size:.78rem;">#<?=str_pad($p['id'],4,'0',STR_PAD_LEFT)?></td>
        <td style="font-weight:600;color:#0f172a;max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?=esc($p['nombre'])?></td>
        <td><span class="badge-pill badge-<?=$p['tipo']?>"><?=ucfirst(esc($p['tipo']))?></span></td>
        <td style="font-size:.82rem;"><?=esc($p['lider']?:'—')?></td>
        <td><?php foreach(array_slice($techs,0,3) as $t): ?><span class="tech-pill"><?=esc($t)?></span><?php endforeach; if(count($techs)>3) echo '<span class="tech-pill">+'.(count($techs)-3).'</span>'; if(empty($techs)) echo '—';?></td>
        <td style="font-size:.78rem;white-space:nowrap;"><?=$p['fecha_inicio']?date('d/m/Y',strtotime($p['fecha_inicio'])):'—'?> – <?=$p['fecha_fin']?date('d/m/Y',strtotime($p['fecha_fin'])):'—'?></td>
        <td><span class="badge-pill badge-<?=$p['estatus']?>"><?=ucfirst(str_replace('_',' ',$p['estatus']))?></span></td>
        <td>
            <button class="action-btn" onclick="abrirModalEditar(<?=htmlspecialchars(json_encode($p),ENT_QUOTES)?>)"><i class="fa-solid fa-pencil"></i></button>
            <button class="action-btn danger" onclick="eliminar(<?=$p['id']?>,'<?=esc($p['nombre'])?>') "><i class="fa-solid fa-trash-can"></i></button>
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
    <div class="modal-header"><h3 class="modal-title"><i class="fa-solid fa-diagram-project"></i> Nuevo Proyecto</h3><button class="modal-close" onclick="cerrarModal('modalCrear')"><i class="fa-solid fa-xmark"></i></button></div>
    <form method="POST" action="api/proyectos.php"><?=csrfField()?><input type="hidden" name="accion" value="crear">
    <div class="modal-body">
        <div class="mb-14"><label class="form-label">Nombre del Proyecto *</label><input type="text" name="nombre" class="form-control" required></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;" class="mb-14">
            <div><label class="form-label">Tipo</label><select name="tipo" class="form-control"><?php foreach($tipos as $t): ?><option value="<?=$t?>"><?=ucfirst($t)?></option><?php endforeach; ?></select></div>
            <div><label class="form-label">Estatus</label><select name="estatus" class="form-control"><?php foreach($estatuses as $e): ?><option value="<?=$e?>"><?=ucfirst(str_replace('_',' ',$e))?></option><?php endforeach; ?></select></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;" class="mb-14">
            <div><label class="form-label">Líder</label><input type="text" name="lider" class="form-control"></div>
            <div><label class="form-label">Tecnologías (separadas por coma)</label><input type="text" name="tecnologias" class="form-control" placeholder="PHP, Vue.js, Docker"></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;" class="mb-14">
            <div><label class="form-label">Inicio</label><input type="date" name="fecha_inicio" class="form-control"></div>
            <div><label class="form-label">Fin (estimado)</label><input type="date" name="fecha_fin" class="form-control"></div>
        </div>
        <div><label class="form-label">Descripción</label><textarea name="descripcion" class="form-control" rows="3"></textarea></div>
    </div>
    <div class="modal-footer"><button type="button" onclick="cerrarModal('modalCrear')" class="btn" style="background:#f1f5f9;color:#475569;">Cancelar</button><button type="submit" class="btn btn-primary" style="background:#6d28d9;border-color:#6d28d9;"><i class="fa-solid fa-check"></i> Guardar</button></div>
    </form>
</div></div>

<!-- Modal Editar -->
<div class="modal-backdrop" id="modalEditar"><div class="modal">
    <div class="modal-header"><h3 class="modal-title"><i class="fa-solid fa-pencil"></i> Editar Proyecto</h3><button class="modal-close" onclick="cerrarModal('modalEditar')"><i class="fa-solid fa-xmark"></i></button></div>
    <form method="POST" action="api/proyectos.php"><?=csrfField()?><input type="hidden" name="accion" value="editar"><input type="hidden" name="id" id="ed_id">
    <div class="modal-body">
        <div class="mb-14"><label class="form-label">Nombre *</label><input type="text" name="nombre" id="ed_nom" class="form-control" required></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;" class="mb-14">
            <div><label class="form-label">Tipo</label><select name="tipo" id="ed_tipo" class="form-control"><?php foreach($tipos as $t): ?><option value="<?=$t?>"><?=ucfirst($t)?></option><?php endforeach; ?></select></div>
            <div><label class="form-label">Estatus</label><select name="estatus" id="ed_est" class="form-control"><?php foreach($estatuses as $e): ?><option value="<?=$e?>"><?=ucfirst(str_replace('_',' ',$e))?></option><?php endforeach; ?></select></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;" class="mb-14">
            <div><label class="form-label">Líder</label><input type="text" name="lider" id="ed_lider" class="form-control"></div>
            <div><label class="form-label">Tecnologías</label><input type="text" name="tecnologias" id="ed_techs" class="form-control"></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;" class="mb-14">
            <div><label class="form-label">Inicio</label><input type="date" name="fecha_inicio" id="ed_fi" class="form-control"></div>
            <div><label class="form-label">Fin</label><input type="date" name="fecha_fin" id="ed_ff" class="form-control"></div>
        </div>
        <div><label class="form-label">Descripción</label><textarea name="descripcion" id="ed_desc" class="form-control" rows="3"></textarea></div>
    </div>
    <div class="modal-footer"><button type="button" onclick="cerrarModal('modalEditar')" class="btn" style="background:#f1f5f9;color:#475569;">Cancelar</button><button type="submit" class="btn btn-primary" style="background:#6d28d9;border-color:#6d28d9;"><i class="fa-solid fa-check"></i> Actualizar</button></div>
    </form>
</div></div>
<form method="POST" action="api/proyectos.php" id="frmEliminar" style="display:none;"><?=csrfField()?><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" id="del_id"></form>
<script>
function cerrarModal(id){document.getElementById(id).classList.remove('active');}
function abrirModalCrear(){document.getElementById('modalCrear').classList.add('active');}
function abrirModalEditar(p){
    document.getElementById('ed_id').value=p.id;document.getElementById('ed_nom').value=p.nombre;
    document.getElementById('ed_lider').value=p.lider||'';document.getElementById('ed_techs').value=p.tecnologias||'';
    document.getElementById('ed_fi').value=p.fecha_inicio||'';document.getElementById('ed_ff').value=p.fecha_fin||'';
    document.getElementById('ed_desc').value=p.descripcion||'';
    const sel=(id,val)=>{const s=document.getElementById(id);[...s.options].forEach(o=>o.selected=o.value===val);};
    sel('ed_tipo',p.tipo);sel('ed_est',p.estatus);document.getElementById('modalEditar').classList.add('active');
}
function eliminar(id,nombre){if(!confirm(`¿Eliminar "${nombre}"?`))return;document.getElementById('del_id').value=id;document.getElementById('frmEliminar').submit();}
document.addEventListener('keydown',e=>{if(e.key==='Escape')document.querySelectorAll('.modal-backdrop.active').forEach(m=>m.classList.remove('active'));});
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
