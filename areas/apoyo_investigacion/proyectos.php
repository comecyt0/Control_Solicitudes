<?php
/**
 * COMECyT — Proyectos de Investigación (Apoyo Investigación)
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../config/auth.php';
verificarSesionAdmin();
$pdo = getConnection();

$filtroEst = getParam('estatus','');
$where='WHERE 1=1';$params=[];
if($filtroEst){$where.=' AND estatus=?';$params[]=$filtroEst;}
$stmt=$pdo->prepare("SELECT * FROM inv_proyectos $where ORDER BY estatus,fecha_fin ASC NULLS LAST, created_at DESC");
$stmt->execute($params);$proyectos=$stmt->fetchAll();

$fc=getParam('flash');$flash='';$tipoF='';
if($fc==='creado'){$flash='Proyecto registrado.';$tipoF='success';}
elseif($fc==='editado'){$flash='Proyecto actualizado.';$tipoF='success';}
elseif($fc==='eliminado'){$flash='Proyecto eliminado.';$tipoF='success';}

$estatuses=['activo','finalizado','suspendido'];
$pageTitle='Proyectos de Investigación';$activeMenu='proyectos';
require_once __DIR__ . '/../../includes/header_admin.php';
?>
<style>
:root{--inv-primary:#3730a3;--inv-accent:#B19A6D;}
.badge-activo{background:#dcfce7;color:#16a34a;}.badge-finalizado{background:#e0f2fe;color:#0284c7;}.badge-suspendido{background:#fee2e2;color:#dc2626;}
.badge-pill{padding:3px 10px;border-radius:20px;font-size:.75rem;font-weight:600;}
.ja-table{width:100%;border-collapse:collapse;}.ja-table th{text-align:left;padding:12px 16px;font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#64748b;background:#f8fafc;border-bottom:2px solid #e2e8f0;}
.ja-table td{padding:13px 16px;border-bottom:1px solid #f1f5f9;vertical-align:middle;}.ja-table tr:last-child td{border-bottom:none;}.ja-table tr:hover td{background:#f8fafc;}
.action-btn{width:30px;height:30px;border-radius:7px;border:1px solid #e2e8f0;background:transparent;cursor:pointer;font-size:.8rem;display:inline-flex;align-items:center;justify-content:center;transition:all .15s;}
.action-btn:hover{border-color:var(--inv-primary);color:var(--inv-primary);}.action-btn.danger:hover{border-color:#ef4444;color:#ef4444;background:#fef2f2;}
.modal-backdrop{display:none;position:fixed;inset:0;background:rgba(15,23,42,.7);backdrop-filter:blur(8px);z-index:2000;align-items:center;justify-content:center;padding:20px;}
.modal-backdrop.active{display:flex;}
.modal{background:#fff;width:100%;max-width:600px;border-radius:20px;overflow:hidden;display:flex;flex-direction:column;max-height:90vh;}
.modal-header{background:linear-gradient(135deg,#3730a3,#1e1b4b);color:#fff;padding:1.25rem 1.5rem;display:flex;align-items:center;justify-content:space-between;}
.modal-title{margin:0;font-size:1.1rem;font-weight:800;display:flex;align-items:center;gap:10px;}
.modal-close{background:none;border:none;color:rgba(255,255,255,.8);cursor:pointer;font-size:1.4rem;}.modal-close:hover{color:#fff;}
.modal-body{padding:1.5rem 2rem;overflow-y:auto;}.modal-footer{padding:1.25rem 2rem;border-top:1px solid #f1f5f9;background:#fafafa;display:flex;gap:10px;justify-content:flex-end;}
.form-label{font-weight:700;color:#475569;font-size:.85rem;margin-bottom:5px;display:block;}
.form-control{border-radius:10px;border:1.5px solid #e2e8f0;background:#f8fafc;padding:9px 13px;font-family:inherit;font-size:.9rem;width:100%;box-sizing:border-box;transition:all .2s;}
.form-control:focus{border-color:#3730a3;outline:none;}.mb-14{margin-bottom:14px;}
</style>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
    <div><h2 style="font-weight:800;color:#0f172a;margin:0;"><i class="fa-solid fa-diagram-project" style="color:#3730a3;"></i> Proyectos de Investigación</h2><p style="color:#64748b;margin:0;">Seguimiento de proyectos activos del área.</p></div>
    <button class="btn btn-primary" onclick="abrirModalCrear()" style="background:#3730a3;border-color:#3730a3;"><i class="fa-solid fa-plus"></i> Nuevo Proyecto</button>
</div>
<?php if($flash): ?><div class="alert alert-<?=$tipoF?> alert-dismissible fade show" role="alert"><?=esc($flash)?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<div style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;">
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;">
        <select name="estatus" class="form-control" style="width:180px;" onchange="this.form.submit()">
            <option value="">Todos los estatus</option>
            <?php foreach($estatuses as $e): ?><option value="<?=$e?>" <?=$filtroEst===$e?'selected':''?>><?=ucfirst($e)?></option><?php endforeach; ?>
        </select>
        <?php if($filtroEst): ?><a href="proyectos.php" class="btn" style="background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;padding:9px 14px;border-radius:10px;font-size:.88rem;">Limpiar</a><?php endif; ?>
    </form>
</div>

<div style="background:#fff;border-radius:18px;border:1px solid #e2e8f0;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,.04);">
<?php if(empty($proyectos)): ?>
    <div style="padding:70px;text-align:center;color:#94a3b8;"><i class="fa-solid fa-diagram-project" style="font-size:2.8rem;opacity:.3;margin-bottom:14px;display:block;"></i><p>No hay proyectos registrados.</p></div>
<?php else: ?>
<div style="overflow-x:auto;">
<table class="ja-table">
    <thead><tr><th>#</th><th>Nombre del Proyecto</th><th>Líder</th><th>Fondo</th><th>Monto</th><th>Periodo</th><th>Estatus</th><th>Acciones</th></tr></thead>
    <tbody>
    <?php foreach($proyectos as $p): $cls='badge-'.$p['estatus']; ?>
    <tr>
        <td style="color:#94a3b8;font-size:.78rem;font-weight:600;">#<?=str_pad($p['id'],4,'0',STR_PAD_LEFT)?></td>
        <td style="font-weight:600;color:#0f172a;max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?=esc($p['nombre'])?></td>
        <td style="font-size:.82rem;"><?=esc($p['lider']?:'—')?></td>
        <td style="font-size:.82rem;"><?=esc($p['fondo']?:'—')?></td>
        <td><?=$p['monto']?'$'.number_format($p['monto'],0):'—'?></td>
        <td style="font-size:.78rem;white-space:nowrap;"><?=$p['fecha_inicio']?date('d/m/Y',strtotime($p['fecha_inicio'])):'—'?> — <?=$p['fecha_fin']?date('d/m/Y',strtotime($p['fecha_fin'])):'—'?></td>
        <td><span class="badge-pill <?=$cls?>"><?=ucfirst(esc($p['estatus']))?></span></td>
        <td>
            <button class="action-btn" onclick="abrirModalEditar(<?=htmlspecialchars(json_encode($p),ENT_QUOTES)?>)"><i class="fa-solid fa-pencil"></i></button>
            <button class="action-btn danger" onclick="eliminar(<?=$p['id']?>,'<?=esc($p['nombre'])?>')"><i class="fa-solid fa-trash-can"></i></button>
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
            <div><label class="form-label">Líder / Responsable</label><input type="text" name="lider" class="form-control"></div>
            <div><label class="form-label">Estatus</label><select name="estatus" class="form-control"><?php foreach($estatuses as $e): ?><option value="<?=$e?>"><?=ucfirst($e)?></option><?php endforeach; ?></select></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;" class="mb-14">
            <div><label class="form-label">Fondo / Programa</label><input type="text" name="fondo" class="form-control"></div>
            <div><label class="form-label">Monto (MXN)</label><input type="number" name="monto" class="form-control" step="1"></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;" class="mb-14">
            <div><label class="form-label">Inicio</label><input type="date" name="fecha_inicio" class="form-control"></div>
            <div><label class="form-label">Fin</label><input type="date" name="fecha_fin" class="form-control"></div>
        </div>
        <div><label class="form-label">Descripción</label><textarea name="descripcion" class="form-control" rows="3"></textarea></div>
    </div>
    <div class="modal-footer"><button type="button" onclick="cerrarModal('modalCrear')" class="btn" style="background:#f1f5f9;color:#475569;">Cancelar</button><button type="submit" class="btn btn-primary" style="background:#3730a3;border-color:#3730a3;"><i class="fa-solid fa-check"></i> Guardar</button></div>
    </form>
</div></div>

<!-- Modal Editar -->
<div class="modal-backdrop" id="modalEditar"><div class="modal">
    <div class="modal-header"><h3 class="modal-title"><i class="fa-solid fa-pencil"></i> Editar Proyecto</h3><button class="modal-close" onclick="cerrarModal('modalEditar')"><i class="fa-solid fa-xmark"></i></button></div>
    <form method="POST" action="api/proyectos.php"><?=csrfField()?><input type="hidden" name="accion" value="editar"><input type="hidden" name="id" id="ed_id">
    <div class="modal-body">
        <div class="mb-14"><label class="form-label">Nombre *</label><input type="text" name="nombre" id="ed_nom" class="form-control" required></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;" class="mb-14">
            <div><label class="form-label">Líder</label><input type="text" name="lider" id="ed_lider" class="form-control"></div>
            <div><label class="form-label">Estatus</label><select name="estatus" id="ed_est" class="form-control"><?php foreach($estatuses as $e): ?><option value="<?=$e?>"><?=ucfirst($e)?></option><?php endforeach; ?></select></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;" class="mb-14">
            <div><label class="form-label">Fondo</label><input type="text" name="fondo" id="ed_fondo" class="form-control"></div>
            <div><label class="form-label">Monto</label><input type="number" name="monto" id="ed_monto" class="form-control" step="1"></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;" class="mb-14">
            <div><label class="form-label">Inicio</label><input type="date" name="fecha_inicio" id="ed_fi" class="form-control"></div>
            <div><label class="form-label">Fin</label><input type="date" name="fecha_fin" id="ed_ff" class="form-control"></div>
        </div>
        <div><label class="form-label">Descripción</label><textarea name="descripcion" id="ed_desc" class="form-control" rows="3"></textarea></div>
    </div>
    <div class="modal-footer"><button type="button" onclick="cerrarModal('modalEditar')" class="btn" style="background:#f1f5f9;color:#475569;">Cancelar</button><button type="submit" class="btn btn-primary" style="background:#3730a3;border-color:#3730a3;"><i class="fa-solid fa-check"></i> Actualizar</button></div>
    </form>
</div></div>
<form method="POST" action="api/proyectos.php" id="frmEliminar" style="display:none;"><?=csrfField()?><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" id="del_id"></form>

<script>
function cerrarModal(id){document.getElementById(id).classList.remove('active');}
function abrirModalCrear(){document.getElementById('modalCrear').classList.add('active');}
function abrirModalEditar(p){
    document.getElementById('ed_id').value=p.id;document.getElementById('ed_nom').value=p.nombre;
    document.getElementById('ed_lider').value=p.lider||'';document.getElementById('ed_fondo').value=p.fondo||'';
    document.getElementById('ed_monto').value=p.monto||'';document.getElementById('ed_fi').value=p.fecha_inicio||'';
    document.getElementById('ed_ff').value=p.fecha_fin||'';document.getElementById('ed_desc').value=p.descripcion||'';
    const sel=(id,val)=>{const s=document.getElementById(id);[...s.options].forEach(o=>o.selected=o.value===val);};
    sel('ed_est',p.estatus);document.getElementById('modalEditar').classList.add('active');
}
function eliminar(id,nombre){if(!confirm(`¿Eliminar el proyecto "${nombre}"?`))return;document.getElementById('del_id').value=id;document.getElementById('frmEliminar').submit();}
document.addEventListener('keydown',e=>{if(e.key==='Escape')document.querySelectorAll('.modal-backdrop.active').forEach(m=>m.classList.remove('active'));});
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
