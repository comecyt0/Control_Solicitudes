<?php
/**
 * COMECyT — Becas y Apoyos (Financiamiento)
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../config/auth.php';
verificarSesionAdmin();
$pdo = getConnection();

$filtroEst  = getParam('estatus','');$filtroCiclo = getParam('ciclo','');
$where='WHERE 1=1';$params=[];
if($filtroEst){$where.=' AND b.estatus_pago=?';$params[]=$filtroEst;}
if($filtroCiclo){$where.=' AND b.ciclo=?';$params[]=$filtroCiclo;}
$stmt=$pdo->prepare("SELECT b.*, c.titulo AS conv_titulo FROM fin_becas b LEFT JOIN fin_convocatorias c ON b.convocatoria_id=c.id $where ORDER BY b.created_at DESC");
$stmt->execute($params);$becas=$stmt->fetchAll();

// Convocatorias activas para selector
$convActivas=$pdo->query("SELECT id,titulo FROM fin_convocatorias WHERE estatus='activa' OR estatus='en_evaluacion' ORDER BY titulo")->fetchAll();
// Ciclos únicos
$ciclos=$pdo->query("SELECT DISTINCT ciclo FROM fin_becas WHERE ciclo IS NOT NULL ORDER BY ciclo DESC")->fetchAll(PDO::FETCH_COLUMN);

$fc=getParam('flash');$flash='';$tipoF='';
if($fc==='creado'){$flash='Beca registrada.';$tipoF='success';}
elseif($fc==='editado'){$flash='Beca actualizada.';$tipoF='success';}
elseif($fc==='eliminado'){$flash='Beca eliminada.';$tipoF='success';}

$estatuses=['pendiente','pagado','suspendido'];
$tiposApoyo=['beca_posgrado','beca_investigacion','beca_licenciatura','apoyo_especial','otro'];
$pageTitle='Becas y Apoyos';$activeMenu='becas';
require_once __DIR__ . '/../../includes/header_admin.php';
?>
<style>
:root{--fin-primary:#064e3b;--fin-accent:#B19A6D;}
.badge-pendiente{background:#fef3c7;color:#d97706;}.badge-pagado{background:#dcfce7;color:#16a34a;}.badge-suspendido{background:#fee2e2;color:#dc2626;}
.badge-pill{padding:3px 10px;border-radius:20px;font-size:.75rem;font-weight:600;}
.ja-table{width:100%;border-collapse:collapse;}.ja-table th{text-align:left;padding:12px 16px;font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#64748b;background:#f8fafc;border-bottom:2px solid #e2e8f0;}
.ja-table td{padding:14px 16px;border-bottom:1px solid #f1f5f9;vertical-align:middle;}.ja-table tr:last-child td{border-bottom:none;}.ja-table tr:hover td{background:#f8fafc;}
.action-btn{width:30px;height:30px;border-radius:7px;border:1px solid #e2e8f0;background:transparent;cursor:pointer;font-size:.8rem;display:inline-flex;align-items:center;justify-content:center;transition:all .15s;}
.action-btn:hover{border-color:var(--fin-primary);color:var(--fin-primary);}.action-btn.danger:hover{border-color:#ef4444;color:#ef4444;background:#fef2f2;}
.modal-backdrop{display:none;position:fixed;inset:0;background:rgba(15,23,42,.7);backdrop-filter:blur(8px);z-index:2000;align-items:center;justify-content:center;padding:20px;}
.modal-backdrop.active{display:flex;}
.modal{background:#fff;width:100%;max-width:580px;border-radius:20px;overflow:hidden;display:flex;flex-direction:column;max-height:90vh;}
.modal-header{background:linear-gradient(135deg,#064e3b,#022c22);color:#fff;padding:1.25rem 1.5rem;display:flex;align-items:center;justify-content:space-between;}
.modal-title{margin:0;font-size:1.1rem;font-weight:800;display:flex;align-items:center;gap:10px;}
.modal-close{background:none;border:none;color:rgba(255,255,255,.8);cursor:pointer;font-size:1.4rem;}.modal-close:hover{color:#fff;}
.modal-body{padding:1.5rem 2rem;overflow-y:auto;}.modal-footer{padding:1.25rem 2rem;border-top:1px solid #f1f5f9;background:#fafafa;display:flex;gap:10px;justify-content:flex-end;}
.form-label{font-weight:700;color:#475569;font-size:.85rem;margin-bottom:5px;display:block;}
.form-control{border-radius:10px;border:1.5px solid #e2e8f0;background:#f8fafc;padding:9px 13px;font-family:inherit;font-size:.9rem;width:100%;box-sizing:border-box;transition:all .2s;}
.form-control:focus{border-color:#064e3b;outline:none;}.mb-14{margin-bottom:14px;}
</style>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
    <div><h2 style="font-weight:800;color:#0f172a;margin:0;"><i class="fa-solid fa-graduation-cap" style="color:#064e3b;"></i> Becas y Apoyos</h2><p style="color:#64748b;margin:0;">Seguimiento de beneficiarios y estatus de pago.</p></div>
    <div style="display:flex;gap:8px;">
        <a href="api/becas.php?accion=exportar_csv&estatus=<?=urlencode($filtroEst)?>&ciclo=<?=urlencode($filtroCiclo)?>&csrf_token=<?=$_SESSION['csrf_token']?>" class="btn" style="background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;font-size:.88rem;"><i class="fa-solid fa-file-csv"></i> Exportar CSV</a>
        <button class="btn btn-primary" onclick="abrirModalCrear()" style="background:#064e3b;border-color:#064e3b;"><i class="fa-solid fa-plus"></i> Nueva Beca</button>
    </div>
</div>
<?php if($flash): ?><div class="alert alert-<?=$tipoF?> alert-dismissible fade show" role="alert"><?=esc($flash)?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<div style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;">
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;">
        <select name="estatus" class="form-control" style="width:180px;" onchange="this.form.submit()"><option value="">Todos los estatus</option><?php foreach($estatuses as $e): ?><option value="<?=$e?>" <?=$filtroEst===$e?'selected':''?>><?=ucfirst($e)?></option><?php endforeach; ?></select>
        <select name="ciclo" class="form-control" style="width:140px;" onchange="this.form.submit()"><option value="">Todos los ciclos</option><?php foreach($ciclos as $c): ?><option value="<?=$c?>" <?=$filtroCiclo===$c?'selected':''?>><?=$c?></option><?php endforeach; ?></select>
        <?php if($filtroEst||$filtroCiclo): ?><a href="becas.php" class="btn" style="background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;padding:9px 14px;border-radius:10px;font-size:.88rem;">Limpiar</a><?php endif; ?>
    </form>
</div>

<div style="background:#fff;border-radius:18px;border:1px solid #e2e8f0;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,.04);">
<?php if(empty($becas)): ?>
    <div style="padding:70px;text-align:center;color:#94a3b8;"><i class="fa-solid fa-graduation-cap" style="font-size:2.8rem;opacity:.3;margin-bottom:14px;display:block;"></i><p>No hay becas registradas.</p></div>
<?php else: ?>
<div style="overflow-x:auto;">
<table class="ja-table">
    <thead><tr><th>#</th><th>Beneficiario</th><th>Tipo</th><th>Monto</th><th>Ciclo</th><th>Convocatoria</th><th>Pago</th><th>Acciones</th></tr></thead>
    <tbody>
    <?php foreach($becas as $b): $cls='badge-'.$b['estatus_pago']; ?>
    <tr>
        <td style="color:#94a3b8;font-size:.78rem;font-weight:600;">#<?=str_pad($b['id'],4,'0',STR_PAD_LEFT)?></td>
        <td style="font-weight:600;color:#0f172a;"><?=esc($b['nombre'])?></td>
        <td style="font-size:.82rem;"><?=esc(ucwords(str_replace('_',' ',$b['tipo_apoyo']?:'—')))?></td>
        <td><?=$b['monto']?'$'.number_format($b['monto'],0):'—'?></td>
        <td><span style="background:#eff6ff;color:#2563eb;padding:2px 8px;border-radius:12px;font-size:.75rem;font-weight:600;"><?=esc($b['ciclo']?:'—')?></span></td>
        <td style="font-size:.82rem;max-width:150px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?=esc($b['conv_titulo']?:'—')?></td>
        <td><span class="badge-pill <?=$cls?>"><?=ucfirst(esc($b['estatus_pago']))?></span></td>
        <td>
            <button class="action-btn" onclick="abrirModalEditar(<?=htmlspecialchars(json_encode($b),ENT_QUOTES)?>)"><i class="fa-solid fa-pencil"></i></button>
            <button class="action-btn danger" onclick="eliminarBeca(<?=$b['id']?>,'<?=esc($b['nombre'])?>')"><i class="fa-solid fa-trash-can"></i></button>
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
    <div class="modal-header"><h3 class="modal-title"><i class="fa-solid fa-user-graduate"></i> Nueva Beca / Apoyo</h3><button class="modal-close" onclick="cerrarModal('modalCrear')"><i class="fa-solid fa-xmark"></i></button></div>
    <form method="POST" action="api/becas.php"><?=csrfField()?><input type="hidden" name="accion" value="crear">
    <div class="modal-body">
        <div class="mb-14"><label class="form-label">Nombre del Beneficiario *</label><input type="text" name="nombre" class="form-control" required></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;" class="mb-14">
            <div><label class="form-label">Tipo de Apoyo</label><select name="tipo_apoyo" class="form-control"><?php foreach($tiposApoyo as $t): ?><option value="<?=$t?>"><?=ucwords(str_replace('_',' ',$t))?></option><?php endforeach; ?></select></div>
            <div><label class="form-label">Estatus de Pago</label><select name="estatus_pago" class="form-control"><?php foreach($estatuses as $e): ?><option value="<?=$e?>"><?=ucfirst($e)?></option><?php endforeach; ?></select></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;" class="mb-14">
            <div><label class="form-label">Monto (MXN)</label><input type="number" name="monto" class="form-control" step="1"></div>
            <div><label class="form-label">Ciclo (ej. 2026-1)</label><input type="text" name="ciclo" class="form-control" placeholder="2026-1"></div>
        </div>
        <div class="mb-14"><label class="form-label">Convocatoria Vinculada</label>
            <select name="convocatoria_id" class="form-control"><option value="">Sin vincular</option><?php foreach($convActivas as $cv): ?><option value="<?=$cv['id']?>"><?=esc($cv['titulo'])?></option><?php endforeach; ?></select>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;" class="mb-14">
            <div><label class="form-label">Inicio</label><input type="date" name="fecha_inicio" class="form-control"></div>
            <div><label class="form-label">Fin</label><input type="date" name="fecha_fin" class="form-control"></div>
        </div>
        <div><label class="form-label">Notas</label><textarea name="notas" class="form-control" rows="2"></textarea></div>
    </div>
    <div class="modal-footer"><button type="button" onclick="cerrarModal('modalCrear')" class="btn" style="background:#f1f5f9;color:#475569;">Cancelar</button><button type="submit" class="btn btn-primary" style="background:#064e3b;border-color:#064e3b;"><i class="fa-solid fa-check"></i> Guardar</button></div>
    </form>
</div></div>

<!-- Modal Editar -->
<div class="modal-backdrop" id="modalEditar"><div class="modal">
    <div class="modal-header"><h3 class="modal-title"><i class="fa-solid fa-pencil"></i> Editar Beca</h3><button class="modal-close" onclick="cerrarModal('modalEditar')"><i class="fa-solid fa-xmark"></i></button></div>
    <form method="POST" action="api/becas.php"><?=csrfField()?><input type="hidden" name="accion" value="editar"><input type="hidden" name="id" id="ed_id">
    <div class="modal-body">
        <div class="mb-14"><label class="form-label">Nombre *</label><input type="text" name="nombre" id="ed_nom" class="form-control" required></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;" class="mb-14">
            <div><label class="form-label">Tipo</label><select name="tipo_apoyo" id="ed_tipo" class="form-control"><?php foreach($tiposApoyo as $t): ?><option value="<?=$t?>"><?=ucwords(str_replace('_',' ',$t))?></option><?php endforeach; ?></select></div>
            <div><label class="form-label">Estatus Pago</label><select name="estatus_pago" id="ed_est" class="form-control"><?php foreach($estatuses as $e): ?><option value="<?=$e?>"><?=ucfirst($e)?></option><?php endforeach; ?></select></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;" class="mb-14">
            <div><label class="form-label">Monto</label><input type="number" name="monto" id="ed_monto" class="form-control" step="1"></div>
            <div><label class="form-label">Ciclo</label><input type="text" name="ciclo" id="ed_ciclo" class="form-control"></div>
        </div>
        <div class="mb-14"><label class="form-label">Convocatoria</label>
            <select name="convocatoria_id" id="ed_conv" class="form-control"><option value="">Sin vincular</option><?php foreach($convActivas as $cv): ?><option value="<?=$cv['id']?>"><?=esc($cv['titulo'])?></option><?php endforeach; ?></select>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;" class="mb-14">
            <div><label class="form-label">Inicio</label><input type="date" name="fecha_inicio" id="ed_fi" class="form-control"></div>
            <div><label class="form-label">Fin</label><input type="date" name="fecha_fin" id="ed_ff" class="form-control"></div>
        </div>
        <div><label class="form-label">Notas</label><textarea name="notas" id="ed_notas" class="form-control" rows="2"></textarea></div>
    </div>
    <div class="modal-footer"><button type="button" onclick="cerrarModal('modalEditar')" class="btn" style="background:#f1f5f9;color:#475569;">Cancelar</button><button type="submit" class="btn btn-primary" style="background:#064e3b;border-color:#064e3b;"><i class="fa-solid fa-check"></i> Actualizar</button></div>
    </form>
</div></div>
<form method="POST" action="api/becas.php" id="frmEliminar" style="display:none;"><?=csrfField()?><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" id="del_id"></form>

<script>
function cerrarModal(id){document.getElementById(id).classList.remove('active');}
function abrirModalCrear(){document.getElementById('modalCrear').classList.add('active');}
function abrirModalEditar(b){
    document.getElementById('ed_id').value=b.id;document.getElementById('ed_nom').value=b.nombre;
    document.getElementById('ed_monto').value=b.monto||'';document.getElementById('ed_ciclo').value=b.ciclo||'';
    document.getElementById('ed_fi').value=b.fecha_inicio||'';document.getElementById('ed_ff').value=b.fecha_fin||'';
    document.getElementById('ed_notas').value=b.notas||'';
    const sel=(id,val)=>{const s=document.getElementById(id);[...s.options].forEach(o=>o.selected=o.value===val);};
    sel('ed_tipo',b.tipo_apoyo);sel('ed_est',b.estatus_pago);sel('ed_conv',b.convocatoria_id||'');
    document.getElementById('modalEditar').classList.add('active');
}
function eliminarBeca(id,nombre){if(!confirm(`¿Eliminar la beca de "${nombre}"?`))return;document.getElementById('del_id').value=id;document.getElementById('frmEliminar').submit();}
document.addEventListener('keydown',e=>{if(e.key==='Escape')document.querySelectorAll('.modal-backdrop.active').forEach(m=>m.classList.remove('active'));});
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
