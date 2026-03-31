<?php
/**
 * COMECyT — Igualdad de Género (RRHH) — folio IG-YYYY-NNNN
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
$stmt=$pdo->prepare("SELECT * FROM rrhh_igualdad $where ORDER BY created_at DESC");
$stmt->execute($params);$acciones=$stmt->fetchAll();

$tipos=$pdo->query("SELECT DISTINCT tipo FROM rrhh_igualdad WHERE tipo IS NOT NULL ORDER BY tipo")->fetchAll(PDO::FETCH_COLUMN);
$estatuses=['pendiente','en_proceso','concluida'];

$fc=getParam('flash');$flash='';$tipoF='';
if($fc==='creado'){$flash='Acción registrada.';$tipoF='success';}
elseif($fc==='editado'){$flash='Acción actualizada.';$tipoF='success';}
elseif($fc==='eliminado'){$flash='Acción eliminada.';$tipoF='success';}

$pageTitle='Igualdad de Género'; $activeMenu='igualdad';
require_once __DIR__ . '/../../includes/header_admin.php';
?>
<style>
:root{--rrhh-primary:#0f766e;--rrhh-accent:#B19A6D;}
.badge-pendiente{background:#fef3c7;color:#d97706;}.badge-en_proceso{background:#e0f2fe;color:#0284c7;}.badge-concluida{background:#dcfce7;color:#16a34a;}
.badge-pill{padding:3px 10px;border-radius:20px;font-size:.74rem;font-weight:600;}
.ja-table{width:100%;border-collapse:collapse;}.ja-table th{text-align:left;padding:12px 16px;font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#64748b;background:#f8fafc;border-bottom:2px solid #e2e8f0;}
.ja-table td{padding:13px 16px;border-bottom:1px solid #f1f5f9;vertical-align:middle;}.ja-table tr:last-child td{border-bottom:none;}.ja-table tr:hover td{background:#f8fafc;}
.folio-tag{font-family:monospace;background:#f1f5f9;color:#475569;padding:3px 9px;border-radius:8px;font-size:.78rem;font-weight:600;}
.action-btn{width:30px;height:30px;border-radius:7px;border:1px solid #e2e8f0;background:transparent;cursor:pointer;font-size:.8rem;display:inline-flex;align-items:center;justify-content:center;transition:all .15s;}
.action-btn:hover{border-color:var(--rrhh-primary);color:var(--rrhh-primary);}.action-btn.danger:hover{border-color:#ef4444;color:#ef4444;background:#fef2f2;}
.modal-backdrop{display:none;position:fixed;inset:0;background:rgba(15,23,42,.7);backdrop-filter:blur(8px);z-index:2000;align-items:center;justify-content:center;padding:20px;}
.modal-backdrop.active{display:flex;}
.modal{background:#fff;width:100%;max-width:600px;border-radius:20px;overflow:hidden;display:flex;flex-direction:column;max-height:90vh;}
.modal-header{background:linear-gradient(135deg,#0f766e,#134e4a);color:#fff;padding:1.25rem 1.5rem;display:flex;align-items:center;justify-content:space-between;}
.modal-title{margin:0;font-size:1.1rem;font-weight:800;display:flex;align-items:center;gap:10px;}
.modal-close{background:none;border:none;color:rgba(255,255,255,.8);cursor:pointer;font-size:1.4rem;}.modal-close:hover{color:#fff;}
.modal-body{padding:1.5rem 2rem;overflow-y:auto;}.modal-footer{padding:1.25rem 2rem;border-top:1px solid #f1f5f9;background:#fafafa;display:flex;gap:10px;justify-content:flex-end;}
.form-label{font-weight:700;color:#475569;font-size:.85rem;margin-bottom:5px;display:block;}
.form-control{border-radius:10px;border:1.5px solid #e2e8f0;background:#f8fafc;padding:9px 13px;font-family:inherit;font-size:.9rem;width:100%;box-sizing:border-box;transition:all .2s;}
.form-control:focus{border-color:#0f766e;outline:none;}.mb-14{margin-bottom:14px;}
</style>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
    <div><h2 style="font-weight:800;color:#0f172a;margin:0;"><i class="fa-solid fa-scale-balanced" style="color:#0f766e;"></i> Igualdad de Género</h2><p style="color:#64748b;margin:0;">Acciones y compromisos del Programa de Igualdad Laboral (PSIT).</p></div>
    <div style="display:flex;gap:8px;">
        <a href="api/igualdad.php?accion=exportar_csv&csrf_token=<?=$_SESSION['csrf_token']?>" class="btn" style="background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;font-size:.88rem;"><i class="fa-solid fa-file-csv"></i> CSV</a>
        <button class="btn btn-primary" onclick="abrirModalCrear()" style="background:#0f766e;border-color:#0f766e;"><i class="fa-solid fa-plus"></i> Nueva Acción</button>
    </div>
</div>
<?php if($flash): ?><div class="alert alert-<?=$tipoF?> alert-dismissible fade show" role="alert"><?=esc($flash)?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<div style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;">
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;">
        <select name="estatus" class="form-control" style="width:155px;" onchange="this.form.submit()"><option value="">Todos los estatus</option><?php foreach($estatuses as $e): ?><option value="<?=$e?>" <?=$filtroEst===$e?'selected':''?>><?=ucfirst(str_replace('_',' ',$e))?></option><?php endforeach; ?></select>
        <?php if(!empty($tipos)): ?><select name="tipo" class="form-control" style="width:200px;" onchange="this.form.submit()"><option value="">Todos los tipos</option><?php foreach($tipos as $t): ?><option value="<?=$t?>" <?=$filtroTipo===$t?'selected':''?>><?=esc($t)?></option><?php endforeach; ?></select><?php endif; ?>
        <?php if($filtroEst||$filtroTipo): ?><a href="igualdad.php" class="btn" style="background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;padding:9px 14px;border-radius:10px;font-size:.88rem;">Limpiar</a><?php endif; ?>
    </form>
    <span style="margin-left:auto;align-self:center;font-size:.85rem;color:#64748b;"><?=count($acciones)?> acciones</span>
</div>

<div style="background:#fff;border-radius:18px;border:1px solid #e2e8f0;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,.04);">
<?php if(empty($acciones)): ?>
<div style="padding:70px;text-align:center;color:#94a3b8;"><i class="fa-solid fa-scale-balanced" style="font-size:2.8rem;opacity:.3;margin-bottom:14px;display:block;"></i><p>No hay acciones registradas.</p></div>
<?php else: ?>
<div style="overflow-x:auto;">
<table class="ja-table">
    <thead><tr><th>Folio</th><th>Tipo</th><th>Descripción</th><th>Responsable</th><th>Periodo</th><th>Estatus</th><th>Acciones</th></tr></thead>
    <tbody>
    <?php foreach($acciones as $a): $cls='badge-'.$a['estatus']; ?>
    <tr>
        <td><span class="folio-tag"><?=esc($a['folio']??'—')?></span></td>
        <td style="font-size:.82rem;max-width:140px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?=esc($a['tipo']?:'—')?></td>
        <td style="font-size:.82rem;max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?=esc(mb_strimwidth($a['descripcion']??'',0,60,'...'))?></td>
        <td style="font-size:.82rem;"><?=esc($a['responsable']?:'—')?></td>
        <td style="font-size:.78rem;white-space:nowrap;"><?=$a['fecha_inicio']?date('d/m/Y',strtotime($a['fecha_inicio'])):'—'?> – <?=$a['fecha_fin']?date('d/m/Y',strtotime($a['fecha_fin'])):'—'?></td>
        <td><span class="badge-pill <?=$cls?>"><?=ucfirst(str_replace('_',' ',$a['estatus']))?></span></td>
        <td>
            <button class="action-btn" onclick="abrirModalEditar(<?=htmlspecialchars(json_encode($a),ENT_QUOTES)?>)"><i class="fa-solid fa-pencil"></i></button>
            <button class="action-btn danger" onclick="eliminar(<?=$a['id']?>,'<?=esc($a['folio'])?>')"><i class="fa-solid fa-trash-can"></i></button>
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
    <div class="modal-header"><h3 class="modal-title"><i class="fa-solid fa-scale-balanced"></i> Nueva Acción de Igualdad</h3><button class="modal-close" onclick="cerrarModal('modalCrear')"><i class="fa-solid fa-xmark"></i></button></div>
    <form method="POST" action="api/igualdad.php"><?=csrfField()?><input type="hidden" name="accion" value="crear">
    <div class="modal-body">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;" class="mb-14">
            <div><label class="form-label">Tipo de Acción</label><input type="text" name="tipo" class="form-control" placeholder="Ej. Capacitación, Protocolo..."></div>
            <div><label class="form-label">Estatus</label><select name="estatus" class="form-control"><?php foreach($estatuses as $e): ?><option value="<?=$e?>"><?=ucfirst(str_replace('_',' ',$e))?></option><?php endforeach; ?></select></div>
        </div>
        <div class="mb-14"><label class="form-label">Descripción</label><textarea name="descripcion" class="form-control" rows="3"></textarea></div>
        <div class="mb-14"><label class="form-label">Responsable</label><input type="text" name="responsable" class="form-control"></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;" class="mb-14">
            <div><label class="form-label">Fecha Inicio</label><input type="date" name="fecha_inicio" class="form-control"></div>
            <div><label class="form-label">Fecha Fin</label><input type="date" name="fecha_fin" class="form-control"></div>
        </div>
        <div><label class="form-label">Observaciones</label><textarea name="observaciones" class="form-control" rows="2"></textarea></div>
    </div>
    <div class="modal-footer"><button type="button" onclick="cerrarModal('modalCrear')" class="btn" style="background:#f1f5f9;color:#475569;">Cancelar</button><button type="submit" class="btn btn-primary" style="background:#0f766e;border-color:#0f766e;"><i class="fa-solid fa-check"></i> Guardar</button></div>
    </form>
</div></div>

<!-- Modal Editar -->
<div class="modal-backdrop" id="modalEditar"><div class="modal">
    <div class="modal-header"><h3 class="modal-title"><i class="fa-solid fa-pencil"></i> Editar Acción</h3><button class="modal-close" onclick="cerrarModal('modalEditar')"><i class="fa-solid fa-xmark"></i></button></div>
    <form method="POST" action="api/igualdad.php"><?=csrfField()?><input type="hidden" name="accion" value="editar"><input type="hidden" name="id" id="ed_id">
    <div class="modal-body">
        <div class="mb-14" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:8px 12px;font-size:.82rem;color:#64748b;">Folio: <strong id="ed_folio_lbl" style="color:#0f172a;"></strong></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;" class="mb-14">
            <div><label class="form-label">Tipo</label><input type="text" name="tipo" id="ed_tipo" class="form-control"></div>
            <div><label class="form-label">Estatus</label><select name="estatus" id="ed_est" class="form-control"><?php foreach($estatuses as $e): ?><option value="<?=$e?>"><?=ucfirst(str_replace('_',' ',$e))?></option><?php endforeach; ?></select></div>
        </div>
        <div class="mb-14"><label class="form-label">Descripción</label><textarea name="descripcion" id="ed_desc" class="form-control" rows="3"></textarea></div>
        <div class="mb-14"><label class="form-label">Responsable</label><input type="text" name="responsable" id="ed_resp" class="form-control"></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;" class="mb-14">
            <div><label class="form-label">Inicio</label><input type="date" name="fecha_inicio" id="ed_fi" class="form-control"></div>
            <div><label class="form-label">Fin</label><input type="date" name="fecha_fin" id="ed_ff" class="form-control"></div>
        </div>
        <div><label class="form-label">Observaciones</label><textarea name="observaciones" id="ed_obs" class="form-control" rows="2"></textarea></div>
    </div>
    <div class="modal-footer"><button type="button" onclick="cerrarModal('modalEditar')" class="btn" style="background:#f1f5f9;color:#475569;">Cancelar</button><button type="submit" class="btn btn-primary" style="background:#0f766e;border-color:#0f766e;"><i class="fa-solid fa-check"></i> Actualizar</button></div>
    </form>
</div></div>
<form method="POST" action="api/igualdad.php" id="frmEliminar" style="display:none;"><?=csrfField()?><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" id="del_id"></form>

<script>
function cerrarModal(id){document.getElementById(id).classList.remove('active');}
function abrirModalCrear(){document.getElementById('modalCrear').classList.add('active');}
function abrirModalEditar(a){
    document.getElementById('ed_id').value=a.id;document.getElementById('ed_folio_lbl').textContent=a.folio||'—';
    document.getElementById('ed_tipo').value=a.tipo||'';document.getElementById('ed_desc').value=a.descripcion||'';
    document.getElementById('ed_resp').value=a.responsable||'';document.getElementById('ed_fi').value=a.fecha_inicio||'';
    document.getElementById('ed_ff').value=a.fecha_fin||'';document.getElementById('ed_obs').value=a.observaciones||'';
    const sel=(id,val)=>{const s=document.getElementById(id);[...s.options].forEach(o=>o.selected=o.value===val);};
    sel('ed_est',a.estatus);document.getElementById('modalEditar').classList.add('active');
}
function eliminar(id,folio){if(!confirm(`¿Eliminar la acción "${folio}"?`))return;document.getElementById('del_id').value=id;document.getElementById('frmEliminar').submit();}
document.addEventListener('keydown',e=>{if(e.key==='Escape')document.querySelectorAll('.modal-backdrop.active').forEach(m=>m.classList.remove('active'));});
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
