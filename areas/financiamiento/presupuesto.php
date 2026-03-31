<?php
/**
 * COMECyT — Presupuesto (Financiamiento)
 * Seguimiento de partidas presupuestales con barra de progreso.
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../config/auth.php';
verificarSesionAdmin();
$pdo = getConnection();

$anioFiltro = (int) getParam('anio', date('Y'));
$stmt = $pdo->prepare("SELECT *, CASE WHEN monto_asignado > 0 THEN ROUND((monto_ejercido/monto_asignado)*100) ELSE 0 END AS pct FROM fin_presupuesto WHERE anio=? ORDER BY created_at DESC");
$stmt->execute([$anioFiltro]);
$partidas = $stmt->fetchAll();

$anios = $pdo->query("SELECT DISTINCT anio FROM fin_presupuesto ORDER BY anio DESC")->fetchAll(PDO::FETCH_COLUMN);
if (!in_array(date('Y'), $anios)) $anios = array_merge([date('Y')], $anios);

$total_asig  = array_sum(array_column($partidas, 'monto_asignado'));
$total_ejerc = array_sum(array_column($partidas, 'monto_ejercido'));
$total_disp  = $total_asig - $total_ejerc;
$pct_global  = $total_asig > 0 ? round(($total_ejerc / $total_asig) * 100) : 0;

$fc = getParam('flash');$flash='';$tipoF='';
if($fc==='creado'){$flash='Partida registrada.';$tipoF='success';}
elseif($fc==='editado'){$flash='Partida actualizada.';$tipoF='success';}
elseif($fc==='eliminado'){$flash='Partida eliminada.';$tipoF='success';}

$pageTitle='Presupuesto';$activeMenu='presupuesto';
require_once __DIR__ . '/../../includes/header_admin.php';
?>
<style>
:root{--fin-primary:#064e3b;--fin-accent:#B19A6D;}
.pres-kpi-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:28px;}
.pres-kpi{background:#fff;border-radius:16px;border:1px solid #e2e8f0;padding:22px 20px;}
.pres-kpi-label{font-size:.8rem;color:#64748b;font-weight:600;text-transform:uppercase;letter-spacing:.04em;margin-bottom:6px;}
.pres-kpi-val{font-size:1.8rem;font-weight:800;color:var(--fin-primary);}
.pres-bar-wrap{height:12px;background:#e2e8f0;border-radius:10px;margin-top:8px;overflow:hidden;}
.pres-bar{height:100%;border-radius:10px;background:linear-gradient(90deg,#064e3b,#059669);transition:width .8s;}
.pres-bar.danger{background:linear-gradient(90deg,#dc2626,#ef4444);}
.pres-card{background:#fff;border-radius:18px;border:1px solid #e2e8f0;padding:22px 24px;transition:all .25s;box-shadow:0 4px 12px rgba(0,0,0,.04);}
.pres-card:hover{box-shadow:0 10px 24px rgba(6,78,59,.1);border-color:var(--fin-accent);}
.pres-partida{font-size:1.05rem;font-weight:700;color:#0f172a;margin-bottom:6px;}
.pres-meta{font-size:.82rem;color:#64748b;margin-bottom:14px;}
.pres-numbers{display:flex;justify-content:space-between;font-size:.82rem;margin-bottom:6px;}
.pres-numbers span:first-child{color:#64748b;}
.pres-numbers strong{color:var(--fin-primary);}
.pres-actions{display:flex;gap:8px;margin-top:14px;padding-top:12px;border-top:1px solid #f1f5f9;}
.pres-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:20px;}
.modal-backdrop{display:none;position:fixed;inset:0;background:rgba(15,23,42,.7);backdrop-filter:blur(8px);z-index:2000;align-items:center;justify-content:center;padding:20px;}
.modal-backdrop.active{display:flex;}
.modal{background:#fff;width:100%;max-width:520px;border-radius:20px;overflow:hidden;display:flex;flex-direction:column;}
.modal-header{background:linear-gradient(135deg,#064e3b,#022c22);color:#fff;padding:1.25rem 1.5rem;display:flex;align-items:center;justify-content:space-between;}
.modal-title{margin:0;font-size:1.1rem;font-weight:800;display:flex;align-items:center;gap:10px;}
.modal-close{background:none;border:none;color:rgba(255,255,255,.8);cursor:pointer;font-size:1.4rem;}.modal-close:hover{color:#fff;}
.modal-body{padding:1.5rem 2rem;}.modal-footer{padding:1.25rem 2rem;border-top:1px solid #f1f5f9;background:#fafafa;display:flex;gap:10px;justify-content:flex-end;}
.form-label{font-weight:700;color:#475569;font-size:.85rem;margin-bottom:5px;display:block;}
.form-control{border-radius:10px;border:1.5px solid #e2e8f0;background:#f8fafc;padding:9px 13px;font-family:inherit;font-size:.9rem;width:100%;box-sizing:border-box;transition:all .2s;}
.form-control:focus{border-color:#064e3b;outline:none;}.mb-14{margin-bottom:14px;}
</style>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
    <div><h2 style="font-weight:800;color:#0f172a;margin:0;"><i class="fa-solid fa-chart-pie" style="color:#064e3b;"></i> Control Presupuestal</h2><p style="color:#64748b;margin:0;">Seguimiento de partidas y ejercicio del presupuesto <?=$anioFiltro?>.</p></div>
    <div style="display:flex;gap:8px;align-items:center;">
        <form method="GET" style="display:flex;gap:8px;align-items:center;">
            <select name="anio" class="form-control" style="width:100px;" onchange="this.form.submit()">
                <?php foreach($anios as $a): ?><option value="<?=$a?>" <?=$anioFiltro==$a?'selected':''?>><?=$a?></option><?php endforeach; ?>
            </select>
        </form>
        <button class="btn btn-primary" onclick="abrirModalCrear()" style="background:#064e3b;border-color:#064e3b;"><i class="fa-solid fa-plus"></i> Nueva Partida</button>
    </div>
</div>
<?php if($flash): ?><div class="alert alert-<?=$tipoF?> alert-dismissible fade show" role="alert"><?=esc($flash)?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<!-- KPIs Resumen -->
<div class="pres-kpi-row">
    <div class="pres-kpi">
        <div class="pres-kpi-label">Total Asignado</div>
        <div class="pres-kpi-val">$<?=number_format($total_asig,0)?></div>
    </div>
    <div class="pres-kpi">
        <div class="pres-kpi-label">Total Ejercido</div>
        <div class="pres-kpi-val" style="color:<?=$pct_global>=90?'#dc2626':'#064e3b'?>;">$<?=number_format($total_ejerc,0)?></div>
    </div>
    <div class="pres-kpi">
        <div class="pres-kpi-label">Disponible</div>
        <div class="pres-kpi-val" style="color:#059669;">$<?=number_format($total_disp,0)?></div>
    </div>
    <div class="pres-kpi">
        <div class="pres-kpi-label">% Ejercido</div>
        <div class="pres-kpi-val"><?=$pct_global?>%</div>
        <div class="pres-bar-wrap"><div class="pres-bar <?=$pct_global>=90?'danger':''?>" style="width:<?=min($pct_global,100)?>%;"></div></div>
    </div>
</div>

<!-- Grid de Partidas -->
<?php if(empty($partidas)): ?>
    <div style="background:#fff;border-radius:18px;padding:70px;text-align:center;color:#94a3b8;border:1px solid #e2e8f0;">
        <i class="fa-solid fa-chart-pie" style="font-size:2.8rem;opacity:.3;margin-bottom:14px;display:block;"></i><p>No hay partidas registradas para <?=$anioFiltro?>.</p>
    </div>
<?php else: ?>
<div class="pres-grid">
<?php foreach($partidas as $p): $pct=(int)$p['pct']; ?>
    <div class="pres-card">
        <div class="pres-partida"><?=esc($p['partida'])?></div>
        <?php if(!empty($p['descripcion'])): ?><div class="pres-meta"><?=esc(mb_strimwidth($p['descripcion'],0,80,'...'))?></div><?php endif; ?>
        <div class="pres-numbers"><span>Asignado</span><strong>$<?=number_format($p['monto_asignado'],0)?></strong></div>
        <div class="pres-numbers"><span>Ejercido</span><strong style="color:<?=$pct>=90?'#dc2626':'#059669'?>;">$<?=number_format($p['monto_ejercido'],0)?></strong></div>
        <div class="pres-bar-wrap"><div class="pres-bar <?=$pct>=90?'danger':''?>" style="width:<?=min($pct,100)?>%;"></div></div>
        <div style="font-size:.78rem;color:#94a3b8;margin-top:4px;text-align:right;"><?=$pct?>% ejercido · $<?=number_format($p['monto_asignado']-$p['monto_ejercido'],0)?> disponible</div>
        <div class="pres-actions">
            <button onclick="abrirModalEditar(<?=htmlspecialchars(json_encode($p),ENT_QUOTES)?>)" class="btn" style="background:rgba(6,78,59,.05);color:#064e3b;border:1px solid rgba(6,78,59,.15);padding:6px 12px;border-radius:8px;font-size:.82rem;flex:1;"><i class="fa-solid fa-pencil"></i> Editar</button>
            <button onclick="eliminarPartida(<?=$p['id']?>,'<?=esc($p['partida'])?>')" class="btn" style="background:#fef2f2;color:#ef4444;border:1px solid #fca5a5;padding:6px 12px;border-radius:8px;font-size:.82rem;"><i class="fa-solid fa-trash-can"></i></button>
        </div>
    </div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Modal Crear -->
<div class="modal-backdrop" id="modalCrear"><div class="modal">
    <div class="modal-header"><h3 class="modal-title"><i class="fa-solid fa-plus"></i> Nueva Partida</h3><button class="modal-close" onclick="cerrarModal('modalCrear')"><i class="fa-solid fa-xmark"></i></button></div>
    <form method="POST" action="api/presupuesto.php"><?=csrfField()?><input type="hidden" name="accion" value="crear">
    <div class="modal-body">
        <div class="mb-14"><label class="form-label">Nombre de la Partida *</label><input type="text" name="partida" class="form-control" required></div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;" class="mb-14">
            <div><label class="form-label">Año</label><input type="number" name="anio" class="form-control" value="<?=date('Y')?>" min="2020" max="2099"></div>
            <div><label class="form-label">Monto Asignado</label><input type="number" name="monto_asignado" class="form-control" step="1" min="0"></div>
            <div><label class="form-label">Monto Ejercido</label><input type="number" name="monto_ejercido" class="form-control" step="1" min="0" value="0"></div>
        </div>
        <div><label class="form-label">Descripción</label><textarea name="descripcion" class="form-control" rows="3"></textarea></div>
    </div>
    <div class="modal-footer"><button type="button" onclick="cerrarModal('modalCrear')" class="btn" style="background:#f1f5f9;color:#475569;">Cancelar</button><button type="submit" class="btn btn-primary" style="background:#064e3b;border-color:#064e3b;"><i class="fa-solid fa-check"></i> Guardar</button></div>
    </form>
</div></div>

<!-- Modal Editar -->
<div class="modal-backdrop" id="modalEditar"><div class="modal">
    <div class="modal-header"><h3 class="modal-title"><i class="fa-solid fa-pencil"></i> Editar Partida</h3><button class="modal-close" onclick="cerrarModal('modalEditar')"><i class="fa-solid fa-xmark"></i></button></div>
    <form method="POST" action="api/presupuesto.php"><?=csrfField()?><input type="hidden" name="accion" value="editar"><input type="hidden" name="id" id="ed_id">
    <div class="modal-body">
        <div class="mb-14"><label class="form-label">Partida *</label><input type="text" name="partida" id="ed_partida" class="form-control" required></div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;" class="mb-14">
            <div><label class="form-label">Año</label><input type="number" name="anio" id="ed_anio" class="form-control"></div>
            <div><label class="form-label">Asignado</label><input type="number" name="monto_asignado" id="ed_asig" class="form-control" step="1"></div>
            <div><label class="form-label">Ejercido</label><input type="number" name="monto_ejercido" id="ed_ejec" class="form-control" step="1"></div>
        </div>
        <div><label class="form-label">Descripción</label><textarea name="descripcion" id="ed_desc" class="form-control" rows="3"></textarea></div>
    </div>
    <div class="modal-footer"><button type="button" onclick="cerrarModal('modalEditar')" class="btn" style="background:#f1f5f9;color:#475569;">Cancelar</button><button type="submit" class="btn btn-primary" style="background:#064e3b;border-color:#064e3b;"><i class="fa-solid fa-check"></i> Actualizar</button></div>
    </form>
</div></div>
<form method="POST" action="api/presupuesto.php" id="frmEliminar" style="display:none;"><?=csrfField()?><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" id="del_id"></form>

<script>
function cerrarModal(id){document.getElementById(id).classList.remove('active');}
function abrirModalCrear(){document.getElementById('modalCrear').classList.add('active');}
function abrirModalEditar(p){
    document.getElementById('ed_id').value=p.id;document.getElementById('ed_partida').value=p.partida;
    document.getElementById('ed_anio').value=p.anio;document.getElementById('ed_asig').value=p.monto_asignado;
    document.getElementById('ed_ejec').value=p.monto_ejercido;document.getElementById('ed_desc').value=p.descripcion||'';
    document.getElementById('modalEditar').classList.add('active');
}
function eliminarPartida(id,nombre){if(!confirm(`¿Eliminar la partida "${nombre}"?`))return;document.getElementById('del_id').value=id;document.getElementById('frmEliminar').submit();}
document.addEventListener('keydown',e=>{if(e.key==='Escape')document.querySelectorAll('.modal-backdrop.active').forEach(m=>m.classList.remove('active'));});
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
