<?php
/**
 * COMECyT — Convocatorias (Financiamiento)
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../config/auth.php';
verificarSesionAdmin();
$pdo = getConnection();

$filtroTipo = getParam('tipo','');$filtroEst = getParam('estatus','');
$where='WHERE 1=1';$params=[];
if($filtroTipo){$where.=' AND tipo=?';$params[]=$filtroTipo;}
if($filtroEst){$where.=' AND estatus=?';$params[]=$filtroEst;}
$stmt=$pdo->prepare("SELECT * FROM fin_convocatorias $where ORDER BY fecha_cierre ASC NULLS LAST, created_at DESC");
$stmt->execute($params);$convocatorias=$stmt->fetchAll();

// Auto-cerrar vencidas
$pdo->query("UPDATE fin_convocatorias SET estatus='cerrada' WHERE estatus='activa' AND fecha_cierre < CURRENT_DATE");

$fc=getParam('flash'); $flash='';$tipoF='';
if($fc==='creado'){$flash='Convocatoria registrada.';$tipoF='success';}
elseif($fc==='editado'){$flash='Convocatoria actualizada.';$tipoF='success';}
elseif($fc==='eliminado'){$flash='Convocatoria eliminada.';$tipoF='success';}

$tipos=['fondo','beca','apoyo','concurso']; $estatuses=['activa','cerrada','en_evaluacion'];
$pageTitle='Convocatorias';$activeMenu='convocatorias';
require_once __DIR__ . '/../../includes/header_admin.php';
?>
<style>
:root{--fin-primary:#064e3b;--fin-accent:#B19A6D;}
.badge-activa{background:#dcfce7;color:#16a34a;} .badge-cerrada{background:#fee2e2;color:#dc2626;} .badge-en_evaluacion{background:#fef3c7;color:#d97706;}
.badge-pill{padding:3px 10px;border-radius:20px;font-size:.75rem;font-weight:600;}
.ja-table{width:100%;border-collapse:collapse;} .ja-table th{text-align:left;padding:12px 16px;font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#64748b;background:#f8fafc;border-bottom:2px solid #e2e8f0;}
.ja-table td{padding:14px 16px;border-bottom:1px solid #f1f5f9;vertical-align:middle;} .ja-table tr:last-child td{border-bottom:none;} .ja-table tr:hover td{background:#f8fafc;}
.action-btn{width:30px;height:30px;border-radius:7px;border:1px solid #e2e8f0;background:transparent;cursor:pointer;font-size:.8rem;display:inline-flex;align-items:center;justify-content:center;transition:all .15s;}
.action-btn:hover{border-color:var(--fin-primary);color:var(--fin-primary);background:rgba(6,78,59,.05);}
.action-btn.danger:hover{border-color:#ef4444;color:#ef4444;background:#fef2f2;}
.modal-backdrop{display:none;position:fixed;inset:0;background:rgba(15,23,42,.7);backdrop-filter:blur(8px);z-index:2000;align-items:center;justify-content:center;padding:20px;}
.modal-backdrop.active{display:flex;}
.modal{background:#fff;width:100%;max-width:600px;border-radius:20px;overflow:hidden;display:flex;flex-direction:column;max-height:90vh;}
.modal-header{background:linear-gradient(135deg,#064e3b,#022c22);color:#fff;padding:1.25rem 1.5rem;display:flex;align-items:center;justify-content:space-between;}
.modal-title{margin:0;font-size:1.1rem;font-weight:800;display:flex;align-items:center;gap:10px;}
.modal-close{background:none;border:none;color:rgba(255,255,255,.8);cursor:pointer;font-size:1.4rem;} .modal-close:hover{color:#fff;}
.modal-body{padding:1.5rem 2rem;overflow-y:auto;} .modal-footer{padding:1.25rem 2rem;border-top:1px solid #f1f5f9;background:#fafafa;display:flex;gap:10px;justify-content:flex-end;}
.form-label{font-weight:700;color:#475569;font-size:.85rem;margin-bottom:5px;display:block;}
.form-control{border-radius:10px;border:1.5px solid #e2e8f0;background:#f8fafc;padding:9px 13px;font-family:inherit;font-size:.9rem;width:100%;box-sizing:border-box;transition:all .2s;}
.form-control:focus{border-color:#064e3b;box-shadow:0 0 0 3px rgba(6,78,59,.1);background:#fff;outline:none;}
.mb-14{margin-bottom:14px;}
</style>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
    <div><h2 style="font-weight:800;color:#0f172a;margin:0;"><i class="fa-solid fa-bullhorn" style="color:#064e3b;"></i> Convocatorias</h2><p style="color:#64748b;margin:0;">Fondos, becas y apoyos externos activos.</p></div>
    <button class="btn btn-primary" onclick="abrirModalCrear()" style="background:#064e3b;border-color:#064e3b;"><i class="fa-solid fa-plus"></i> Nueva Convocatoria</button>
</div>
<?php if($flash): ?><div class="alert alert-<?=$tipoF?> alert-dismissible fade show" role="alert"><?=esc($flash)?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<div style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;">
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;">
        <select name="tipo" class="form-control" style="width:160px;" onchange="this.form.submit()"><option value="">Todos los tipos</option><?php foreach($tipos as $t): ?><option value="<?=$t?>" <?=$filtroTipo===$t?'selected':''?>><?=ucfirst($t)?></option><?php endforeach; ?></select>
        <select name="estatus" class="form-control" style="width:180px;" onchange="this.form.submit()"><option value="">Todos los estatus</option><?php foreach($estatuses as $e): ?><option value="<?=$e?>" <?=$filtroEst===$e?'selected':''?>><?=ucwords(str_replace('_',' ',$e))?></option><?php endforeach; ?></select>
        <?php if($filtroTipo||$filtroEst): ?><a href="convocatorias.php" class="btn" style="background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;padding:9px 14px;border-radius:10px;font-size:.88rem;">Limpiar</a><?php endif; ?>
    </form>
</div>

<div style="background:#fff;border-radius:18px;border:1px solid #e2e8f0;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,.04);">
<?php if(empty($convocatorias)): ?>
    <div style="padding:70px;text-align:center;color:#94a3b8;"><i class="fa-solid fa-bullhorn" style="font-size:2.8rem;opacity:.3;margin-bottom:14px;display:block;"></i><p>No hay convocatorias registradas.</p></div>
<?php else: ?>
<div style="overflow-x:auto;">
<table class="ja-table">
    <thead><tr><th>#</th><th>Título</th><th>Dependencia</th><th>Tipo</th><th>Monto Máx.</th><th>Cierre</th><th>Estatus</th><th>Acciones</th></tr></thead>
    <tbody>
    <?php foreach($convocatorias as $c): $cls='badge-'.$c['estatus']; ?>
    <tr>
        <td style="color:#94a3b8;font-size:.78rem;font-weight:600;">#<?=str_pad($c['id'],4,'0',STR_PAD_LEFT)?></td>
        <td style="font-weight:600;color:#0f172a;max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?=esc($c['titulo'])?></td>
        <td><?=esc($c['dependencia']?:'—')?></td>
        <td><span class="badge-pill" style="background:#f0fdf4;color:#16a34a;"><?=ucfirst(esc($c['tipo']))?></span></td>
        <td><?=$c['monto_max']?'$'.number_format($c['monto_max'],0):'—'?></td>
        <td style="font-size:.82rem;"><?=$c['fecha_cierre']?date('d/m/Y',strtotime($c['fecha_cierre'])):'—'?></td>
        <td><span class="badge-pill <?=$cls?>"><?=ucwords(str_replace('_',' ',$c['estatus']))?></span></td>
        <td>
            <?php if(!empty($c['url_info'])): ?><a href="<?=esc($c['url_info'])?>" target="_blank" class="action-btn" title="Ver Info"><i class="fa-solid fa-arrow-up-right-from-square"></i></a><?php endif; ?>
            <button class="action-btn" onclick="abrirModalEditar(<?=htmlspecialchars(json_encode($c),ENT_QUOTES)?>)"><i class="fa-solid fa-pencil"></i></button>
            <button class="action-btn danger" onclick="eliminarConv(<?=$c['id']?>,'<?=esc($c['titulo'])?>')"><i class="fa-solid fa-trash-can"></i></button>
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
    <div class="modal-header"><h3 class="modal-title"><i class="fa-solid fa-bullhorn"></i> Nueva Convocatoria</h3><button class="modal-close" onclick="cerrarModal('modalCrear')"><i class="fa-solid fa-xmark"></i></button></div>
    <form method="POST" action="api/convocatorias.php"><?=csrfField()?><input type="hidden" name="accion" value="crear">
    <div class="modal-body">
        <div class="mb-14"><label class="form-label">Título *</label><input type="text" name="titulo" class="form-control" required></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;" class="mb-14">
            <div><label class="form-label">Tipo</label><select name="tipo" class="form-control"><?php foreach($tipos as $t): ?><option value="<?=$t?>"><?=ucfirst($t)?></option><?php endforeach; ?></select></div>
            <div><label class="form-label">Estatus</label><select name="estatus" class="form-control"><?php foreach($estatuses as $e): ?><option value="<?=$e?>"><?=ucwords(str_replace('_',' ',$e))?></option><?php endforeach; ?></select></div>
        </div>
        <div class="mb-14"><label class="form-label">Dependencia / Fondo</label><input type="text" name="dependencia" class="form-control"></div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;" class="mb-14">
            <div><label class="form-label">Monto Máx.</label><input type="number" name="monto_max" class="form-control" step="1"></div>
            <div><label class="form-label">Inicio</label><input type="date" name="fecha_inicio" class="form-control"></div>
            <div><label class="form-label">Cierre</label><input type="date" name="fecha_cierre" class="form-control"></div>
        </div>
        <div class="mb-14"><label class="form-label">URL de Información</label><input type="url" name="url_info" class="form-control" placeholder="https://..."></div>
        <div><label class="form-label">Descripción</label><textarea name="descripcion" class="form-control" rows="3"></textarea></div>
    </div>
    <div class="modal-footer"><button type="button" onclick="cerrarModal('modalCrear')" class="btn" style="background:#f1f5f9;color:#475569;">Cancelar</button><button type="submit" class="btn btn-primary" style="background:#064e3b;border-color:#064e3b;"><i class="fa-solid fa-check"></i> Guardar</button></div>
    </form>
</div></div>

<!-- Modal Editar -->
<div class="modal-backdrop" id="modalEditar"><div class="modal">
    <div class="modal-header"><h3 class="modal-title"><i class="fa-solid fa-pencil"></i> Editar Convocatoria</h3><button class="modal-close" onclick="cerrarModal('modalEditar')"><i class="fa-solid fa-xmark"></i></button></div>
    <form method="POST" action="api/convocatorias.php"><?=csrfField()?><input type="hidden" name="accion" value="editar"><input type="hidden" name="id" id="ed_id">
    <div class="modal-body">
        <div class="mb-14"><label class="form-label">Título *</label><input type="text" name="titulo" id="ed_titulo" class="form-control" required></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;" class="mb-14">
            <div><label class="form-label">Tipo</label><select name="tipo" id="ed_tipo" class="form-control"><?php foreach($tipos as $t): ?><option value="<?=$t?>"><?=ucfirst($t)?></option><?php endforeach; ?></select></div>
            <div><label class="form-label">Estatus</label><select name="estatus" id="ed_est" class="form-control"><?php foreach($estatuses as $e): ?><option value="<?=$e?>"><?=ucwords(str_replace('_',' ',$e))?></option><?php endforeach; ?></select></div>
        </div>
        <div class="mb-14"><label class="form-label">Dependencia</label><input type="text" name="dependencia" id="ed_dep" class="form-control"></div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;" class="mb-14">
            <div><label class="form-label">Monto Máx.</label><input type="number" name="monto_max" id="ed_monto" class="form-control" step="1"></div>
            <div><label class="form-label">Inicio</label><input type="date" name="fecha_inicio" id="ed_fi" class="form-control"></div>
            <div><label class="form-label">Cierre</label><input type="date" name="fecha_cierre" id="ed_ff" class="form-control"></div>
        </div>
        <div class="mb-14"><label class="form-label">URL de Información</label><input type="url" name="url_info" id="ed_url" class="form-control"></div>
        <div><label class="form-label">Descripción</label><textarea name="descripcion" id="ed_desc" class="form-control" rows="3"></textarea></div>
    </div>
    <div class="modal-footer"><button type="button" onclick="cerrarModal('modalEditar')" class="btn" style="background:#f1f5f9;color:#475569;">Cancelar</button><button type="submit" class="btn btn-primary" style="background:#064e3b;border-color:#064e3b;"><i class="fa-solid fa-check"></i> Actualizar</button></div>
    </form>
</div></div>
<form method="POST" action="api/convocatorias.php" id="frmEliminar" style="display:none;"><?=csrfField()?><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" id="del_id"></form>

<script>
function cerrarModal(id){document.getElementById(id).classList.remove('active');}
function abrirModalCrear(){document.getElementById('modalCrear').classList.add('active');}
function abrirModalEditar(c){
    document.getElementById('ed_id').value=c.id; document.getElementById('ed_titulo').value=c.titulo;
    document.getElementById('ed_dep').value=c.dependencia||''; document.getElementById('ed_monto').value=c.monto_max||'';
    document.getElementById('ed_fi').value=c.fecha_inicio||''; document.getElementById('ed_ff').value=c.fecha_cierre||'';
    document.getElementById('ed_url').value=c.url_info||''; document.getElementById('ed_desc').value=c.descripcion||'';
    const sel=(id,val)=>{const s=document.getElementById(id);[...s.options].forEach(o=>o.selected=o.value===val);};
    sel('ed_tipo',c.tipo);sel('ed_est',c.estatus);
    document.getElementById('modalEditar').classList.add('active');
}
function eliminarConv(id,nombre){if(!confirm(`¿Eliminar "${nombre}"?`))return;document.getElementById('del_id').value=id;document.getElementById('frmEliminar').submit();}
document.addEventListener('keydown',e=>{if(e.key==='Escape')document.querySelectorAll('.modal-backdrop.active').forEach(m=>m.classList.remove('active'));});
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
