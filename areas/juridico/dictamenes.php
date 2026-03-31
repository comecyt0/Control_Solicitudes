<?php
/**
 * COMECyT — Dictámenes y Opiniones Jurídicas (AJ) — folio DJ-YYYY-NNNN
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../config/auth.php';
verificarSesionAdmin();
$pdo = getConnection();

$filtroEst    = getParam('estatus','');
$filtroMateria= getParam('materia','');
$where='WHERE 1=1';$params=[];
if($filtroEst){$where.=' AND estatus=?';$params[]=$filtroEst;}
if($filtroMateria){$where.=' AND materia=?';$params[]=$filtroMateria;}
$stmt=$pdo->prepare("SELECT * FROM aj_dictamenes $where ORDER BY fecha_solicitud DESC NULLS LAST, created_at DESC");
$stmt->execute($params);$dictamenes=$stmt->fetchAll();

$materias=['laboral','civil','administrativo','fiscal','otro'];
$estatuses=['pendiente','en_elaboracion','emitido'];

$fc=getParam('flash');$flash='';$tipoF='';
if($fc==='creado'){$flash='Dictamen registrado.';$tipoF='success';}
elseif($fc==='editado'){$flash='Dictamen actualizado.';$tipoF='success';}
elseif($fc==='eliminado'){$flash='Dictamen eliminado.';$tipoF='success';}

$pageTitle='Dictámenes y Opiniones'; $activeMenu='dictamenes';
require_once __DIR__ . '/../../includes/header_admin.php';
?>
<style>
:root{--aj-primary:#991b1b;--aj-accent:#B19A6D;}
.badge-pendiente{background:#fef3c7;color:#d97706;}.badge-en_elaboracion{background:#e0f2fe;color:#0284c7;}.badge-emitido{background:#dcfce7;color:#16a34a;}
.badge-laboral{background:#fce7f3;color:#db2777;}.badge-civil{background:#ede9fe;color:#6d28d9;}.badge-administrativo{background:#fff1f2;color:#991b1b;}.badge-fiscal{background:#fef3c7;color:#d97706;}.badge-otro{background:#f1f5f9;color:#475569;}
.badge-pill{padding:3px 10px;border-radius:20px;font-size:.74rem;font-weight:600;}
.folio-tag{font-family:monospace;background:#fff1f2;color:#991b1b;padding:3px 9px;border-radius:8px;font-size:.78rem;font-weight:600;}
.dict-card{background:#fff;border-radius:16px;border:1px solid #e2e8f0;padding:22px;box-shadow:0 4px 12px rgba(0,0,0,.04);transition:all .25s;border-left:4px solid var(--aj-primary);}
.dict-card:hover{transform:translateY(-3px);box-shadow:0 12px 24px rgba(153,27,27,.08);}
.dict-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:18px;}
.dict-titulo{font-size:.95rem;font-weight:700;color:#0f172a;margin:10px 0 6px;}
.dict-meta{display:flex;align-items:center;gap:7px;font-size:.8rem;color:#64748b;margin-top:4px;}
.dict-meta i{color:var(--aj-accent);width:13px;}
.dict-actions{display:flex;gap:6px;margin-top:14px;padding-top:12px;border-top:1px solid #f1f5f9;}
.action-btn{width:30px;height:30px;border-radius:7px;border:1px solid #e2e8f0;background:transparent;cursor:pointer;font-size:.8rem;display:inline-flex;align-items:center;justify-content:center;transition:all .15s;}
.action-btn:hover{border-color:var(--aj-primary);color:var(--aj-primary);}.action-btn.danger:hover{border-color:#ef4444;color:#ef4444;background:#fef2f2;}
.modal-backdrop{display:none;position:fixed;inset:0;background:rgba(15,23,42,.7);backdrop-filter:blur(8px);z-index:2000;align-items:center;justify-content:center;padding:20px;}
.modal-backdrop.active{display:flex;}
.modal{background:#fff;width:100%;max-width:600px;border-radius:20px;overflow:hidden;display:flex;flex-direction:column;max-height:90vh;}
.modal-header{background:linear-gradient(135deg,#991b1b,#7f1d1d);color:#fff;padding:1.25rem 1.5rem;display:flex;align-items:center;justify-content:space-between;}
.modal-title{margin:0;font-size:1.1rem;font-weight:800;display:flex;align-items:center;gap:10px;}
.modal-close{background:none;border:none;color:rgba(255,255,255,.8);cursor:pointer;font-size:1.4rem;}.modal-close:hover{color:#fff;}
.modal-body{padding:1.5rem 2rem;overflow-y:auto;}.modal-footer{padding:1.25rem 2rem;border-top:1px solid #f1f5f9;background:#fafafa;display:flex;gap:10px;justify-content:flex-end;}
.form-label{font-weight:700;color:#475569;font-size:.85rem;margin-bottom:5px;display:block;}
.form-control{border-radius:10px;border:1.5px solid #e2e8f0;background:#f8fafc;padding:9px 13px;font-family:inherit;font-size:.9rem;width:100%;box-sizing:border-box;transition:all .2s;}
.form-control:focus{border-color:#991b1b;outline:none;}.mb-14{margin-bottom:14px;}
</style>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
    <div><h2 style="font-weight:800;color:#0f172a;margin:0;"><i class="fa-solid fa-scroll" style="color:#991b1b;"></i> Dictámenes y Opiniones Jurídicas</h2><p style="color:#64748b;margin:0;">Consultas y opiniones para otras áreas del COMECyT.</p></div>
    <div style="display:flex;gap:8px;">
        <a href="api/dictamenes.php?accion=exportar_csv&csrf_token=<?=$_SESSION['csrf_token']?>" class="btn" style="background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;font-size:.88rem;"><i class="fa-solid fa-file-csv"></i> CSV</a>
        <button class="btn btn-primary" onclick="abrirModalCrear()" style="background:#991b1b;border-color:#991b1b;"><i class="fa-solid fa-plus"></i> Nuevo Dictamen</button>
    </div>
</div>
<?php if($flash): ?><div class="alert alert-<?=$tipoF?> alert-dismissible fade show" role="alert"><?=esc($flash)?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<div style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;">
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;">
        <select name="materia" class="form-control" style="width:155px;" onchange="this.form.submit()"><option value="">Todas las materias</option><?php foreach($materias as $m): ?><option value="<?=$m?>" <?=$filtroMateria===$m?'selected':''?>><?=ucfirst($m)?></option><?php endforeach; ?></select>
        <select name="estatus" class="form-control" style="width:160px;" onchange="this.form.submit()"><option value="">Todos estatus</option><?php foreach($estatuses as $e): ?><option value="<?=$e?>" <?=$filtroEst===$e?'selected':''?>><?=ucfirst(str_replace('_',' ',$e))?></option><?php endforeach; ?></select>
        <?php if($filtroEst||$filtroMateria): ?><a href="dictamenes.php" class="btn" style="background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;padding:9px 14px;border-radius:10px;font-size:.88rem;">Limpiar</a><?php endif; ?>
    </form>
    <span style="margin-left:auto;align-self:center;font-size:.85rem;color:#64748b;"><?=count($dictamenes)?> dictámenes</span>
</div>

<?php if(empty($dictamenes)): ?>
<div style="background:#fff;border-radius:18px;padding:70px;text-align:center;color:#94a3b8;border:1px solid #e2e8f0;"><i class="fa-solid fa-scroll" style="font-size:2.8rem;opacity:.3;margin-bottom:14px;display:block;"></i><p>No hay dictámenes registrados.</p></div>
<?php else: ?>
<div class="dict-grid">
<?php foreach($dictamenes as $d): ?>
<div class="dict-card">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;">
        <span class="folio-tag"><?=esc($d['folio'])?></span>
        <span class="badge-pill badge-<?=$d['estatus']?>"><?=ucfirst(str_replace('_',' ',$d['estatus']))?></span>
    </div>
    <div class="dict-titulo"><?=esc($d['titulo'])?></div>
    <span class="badge-pill badge-<?=$d['materia']?>" style="margin-bottom:6px;"><?=ucfirst(esc($d['materia']?:'—'))?></span>
    <?php if($d['area_solicitante']): ?><div class="dict-meta"><i class="fa-solid fa-building"></i><?=esc($d['area_solicitante'])?></div><?php endif; ?>
    <?php if($d['fecha_solicitud']): ?><div class="dict-meta"><i class="fa-solid fa-calendar-plus"></i>Solicitud: <?=date('d/m/Y',strtotime($d['fecha_solicitud']))?></div><?php endif; ?>
    <?php if($d['fecha_respuesta']): ?><div class="dict-meta"><i class="fa-solid fa-calendar-check"></i>Respuesta: <?=date('d/m/Y',strtotime($d['fecha_respuesta']))?></div><?php endif; ?>
    <?php if($d['descripcion']): ?><div class="dict-meta" style="white-space:normal;margin-top:8px;"><i class="fa-solid fa-note-sticky"></i><?=esc(mb_strimwidth($d['descripcion'],0,70,'...'))?></div><?php endif; ?>
    <div class="dict-actions">
        <button class="action-btn" style="flex:1;" onclick="abrirModalEditar(<?=htmlspecialchars(json_encode($d),ENT_QUOTES)?>)"><i class="fa-solid fa-pencil"></i> Editar</button>
        <button class="action-btn danger" onclick="eliminar(<?=$d['id']?>,'<?=esc($d['folio'])?>') "><i class="fa-solid fa-trash-can"></i></button>
    </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Modal Crear -->
<div class="modal-backdrop" id="modalCrear"><div class="modal">
    <div class="modal-header"><h3 class="modal-title"><i class="fa-solid fa-scroll"></i> Nuevo Dictamen / Opinión</h3><button class="modal-close" onclick="cerrarModal('modalCrear')"><i class="fa-solid fa-xmark"></i></button></div>
    <form method="POST" action="api/dictamenes.php"><?=csrfField()?><input type="hidden" name="accion" value="crear">
    <div class="modal-body">
        <div class="mb-14"><label class="form-label">Título / Tema *</label><input type="text" name="titulo" class="form-control" required></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;" class="mb-14">
            <div><label class="form-label">Materia</label><select name="materia" class="form-control"><?php foreach($materias as $m): ?><option value="<?=$m?>"><?=ucfirst($m)?></option><?php endforeach; ?></select></div>
            <div><label class="form-label">Estatus</label><select name="estatus" class="form-control"><?php foreach($estatuses as $e): ?><option value="<?=$e?>"><?=ucfirst(str_replace('_',' ',$e))?></option><?php endforeach; ?></select></div>
        </div>
        <div class="mb-14"><label class="form-label">Área Solicitante</label><input type="text" name="area_solicitante" class="form-control"></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;" class="mb-14">
            <div><label class="form-label">Fecha Solicitud</label><input type="date" name="fecha_solicitud" class="form-control"></div>
            <div><label class="form-label">Fecha Respuesta</label><input type="date" name="fecha_respuesta" class="form-control"></div>
        </div>
        <div><label class="form-label">Descripción / Contenido</label><textarea name="descripcion" class="form-control" rows="3"></textarea></div>
    </div>
    <div class="modal-footer"><button type="button" onclick="cerrarModal('modalCrear')" class="btn" style="background:#f1f5f9;color:#475569;">Cancelar</button><button type="submit" class="btn btn-primary" style="background:#991b1b;border-color:#991b1b;"><i class="fa-solid fa-check"></i> Guardar</button></div>
    </form>
</div></div>
<!-- Modal Editar -->
<div class="modal-backdrop" id="modalEditar"><div class="modal">
    <div class="modal-header"><h3 class="modal-title"><i class="fa-solid fa-pencil"></i> Editar Dictamen</h3><button class="modal-close" onclick="cerrarModal('modalEditar')"><i class="fa-solid fa-xmark"></i></button></div>
    <form method="POST" action="api/dictamenes.php"><?=csrfField()?><input type="hidden" name="accion" value="editar"><input type="hidden" name="id" id="ed_id">
    <div class="modal-body">
        <div class="mb-14" style="background:#fff1f2;border:1px solid #fecaca;border-radius:8px;padding:8px 12px;font-size:.82rem;color:#64748b;">Folio: <strong id="ed_folio_lbl" style="color:#991b1b;font-family:monospace;"></strong></div>
        <div class="mb-14"><label class="form-label">Título *</label><input type="text" name="titulo" id="ed_titulo" class="form-control" required></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;" class="mb-14">
            <div><label class="form-label">Materia</label><select name="materia" id="ed_mat" class="form-control"><?php foreach($materias as $m): ?><option value="<?=$m?>"><?=ucfirst($m)?></option><?php endforeach; ?></select></div>
            <div><label class="form-label">Estatus</label><select name="estatus" id="ed_est" class="form-control"><?php foreach($estatuses as $e): ?><option value="<?=$e?>"><?=ucfirst(str_replace('_',' ',$e))?></option><?php endforeach; ?></select></div>
        </div>
        <div class="mb-14"><label class="form-label">Área Solicitante</label><input type="text" name="area_solicitante" id="ed_area" class="form-control"></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;" class="mb-14">
            <div><label class="form-label">Fecha Solicitud</label><input type="date" name="fecha_solicitud" id="ed_fs" class="form-control"></div>
            <div><label class="form-label">Fecha Respuesta</label><input type="date" name="fecha_respuesta" id="ed_fr" class="form-control"></div>
        </div>
        <div><label class="form-label">Descripción</label><textarea name="descripcion" id="ed_desc" class="form-control" rows="3"></textarea></div>
    </div>
    <div class="modal-footer"><button type="button" onclick="cerrarModal('modalEditar')" class="btn" style="background:#f1f5f9;color:#475569;">Cancelar</button><button type="submit" class="btn btn-primary" style="background:#991b1b;border-color:#991b1b;"><i class="fa-solid fa-check"></i> Actualizar</button></div>
    </form>
</div></div>
<form method="POST" action="api/dictamenes.php" id="frmEliminar" style="display:none;"><?=csrfField()?><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" id="del_id"></form>
<script>
function cerrarModal(id){document.getElementById(id).classList.remove('active');}
function abrirModalCrear(){document.getElementById('modalCrear').classList.add('active');}
function abrirModalEditar(d){
    document.getElementById('ed_id').value=d.id;document.getElementById('ed_folio_lbl').textContent=d.folio||'—';
    document.getElementById('ed_titulo').value=d.titulo||'';document.getElementById('ed_area').value=d.area_solicitante||'';
    document.getElementById('ed_fs').value=d.fecha_solicitud||'';document.getElementById('ed_fr').value=d.fecha_respuesta||'';
    document.getElementById('ed_desc').value=d.descripcion||'';
    const sel=(id,val)=>{const s=document.getElementById(id);[...s.options].forEach(o=>o.selected=o.value===val);};
    sel('ed_mat',d.materia);sel('ed_est',d.estatus);document.getElementById('modalEditar').classList.add('active');
}
function eliminar(id,folio){if(!confirm(`¿Eliminar dictamen "${folio}"?`))return;document.getElementById('del_id').value=id;document.getElementById('frmEliminar').submit();}
document.addEventListener('keydown',e=>{if(e.key==='Escape')document.querySelectorAll('.modal-backdrop.active').forEach(m=>m.classList.remove('active'));});
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
