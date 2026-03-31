<?php
/**
 * COMECyT — Cursos y Capacitación (RRHH)
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../config/auth.php';
verificarSesionAdmin();
$pdo = getConnection();

$filtroEst  = getParam('estatus','');
$filtroMod  = getParam('modalidad','');
$where='WHERE 1=1';$params=[];
if($filtroEst){$where.=' AND estatus=?';$params[]=$filtroEst;}
if($filtroMod){$where.=' AND modalidad=?';$params[]=$filtroMod;}
$stmt=$pdo->prepare("SELECT * FROM rrhh_cursos $where ORDER BY fecha_inicio ASC NULLS LAST, created_at DESC");
$stmt->execute($params);$cursos=$stmt->fetchAll();

$estatuses=['programado','en_curso','concluido','cancelado'];
$modalidades=['presencial','virtual','mixto'];
$fc=getParam('flash');$flash='';$tipoF='';
if($fc==='creado'){$flash='Curso registrado.';$tipoF='success';}
elseif($fc==='editado'){$flash='Curso actualizado.';$tipoF='success';}
elseif($fc==='eliminado'){$flash='Curso eliminado.';$tipoF='success';}

$pageTitle='Cursos y Capacitación'; $activeMenu='cursos';
require_once __DIR__ . '/../../includes/header_admin.php';
?>
<style>
:root{--rrhh-primary:#0f766e;--rrhh-accent:#B19A6D;}
.badge-programado{background:#e0f2fe;color:#0284c7;}.badge-en_curso{background:#dcfce7;color:#16a34a;}.badge-concluido{background:#f1f5f9;color:#475569;}.badge-cancelado{background:#fee2e2;color:#dc2626;}
.badge-pill{padding:3px 10px;border-radius:20px;font-size:.74rem;font-weight:600;}
.badge-presencial{background:#ccfbf1;color:#0f766e;}.badge-virtual{background:#ede9fe;color:#6d28d9;}.badge-mixto{background:#fef3c7;color:#d97706;}
.ja-table{width:100%;border-collapse:collapse;}.ja-table th{text-align:left;padding:12px 16px;font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#64748b;background:#f8fafc;border-bottom:2px solid #e2e8f0;}
.ja-table td{padding:13px 16px;border-bottom:1px solid #f1f5f9;vertical-align:middle;}.ja-table tr:last-child td{border-bottom:none;}.ja-table tr:hover td{background:#f8fafc;}
.cupo-bar{width:80px;height:7px;background:#e2e8f0;border-radius:10px;display:inline-block;overflow:hidden;vertical-align:middle;margin-left:6px;}
.cupo-fill{height:100%;border-radius:10px;background:linear-gradient(90deg,#0f766e,#14b8a6);}
.action-btn{width:30px;height:30px;border-radius:7px;border:1px solid #e2e8f0;background:transparent;cursor:pointer;font-size:.8rem;display:inline-flex;align-items:center;justify-content:center;transition:all .15s;}
.action-btn:hover{border-color:var(--rrhh-primary);color:var(--rrhh-primary);}.action-btn.danger:hover{border-color:#ef4444;color:#ef4444;background:#fef2f2;}
.modal-backdrop{display:none;position:fixed;inset:0;background:rgba(15,23,42,.7);backdrop-filter:blur(8px);z-index:2000;align-items:center;justify-content:center;padding:20px;}
.modal-backdrop.active{display:flex;}
.modal{background:#fff;width:100%;max-width:620px;border-radius:20px;overflow:hidden;display:flex;flex-direction:column;max-height:90vh;}
.modal-header{background:linear-gradient(135deg,#0f766e,#134e4a);color:#fff;padding:1.25rem 1.5rem;display:flex;align-items:center;justify-content:space-between;}
.modal-title{margin:0;font-size:1.1rem;font-weight:800;display:flex;align-items:center;gap:10px;}
.modal-close{background:none;border:none;color:rgba(255,255,255,.8);cursor:pointer;font-size:1.4rem;}.modal-close:hover{color:#fff;}
.modal-body{padding:1.5rem 2rem;overflow-y:auto;}.modal-footer{padding:1.25rem 2rem;border-top:1px solid #f1f5f9;background:#fafafa;display:flex;gap:10px;justify-content:flex-end;}
.form-label{font-weight:700;color:#475569;font-size:.85rem;margin-bottom:5px;display:block;}
.form-control{border-radius:10px;border:1.5px solid #e2e8f0;background:#f8fafc;padding:9px 13px;font-family:inherit;font-size:.9rem;width:100%;box-sizing:border-box;transition:all .2s;}
.form-control:focus{border-color:#0f766e;outline:none;}.mb-14{margin-bottom:14px;}
</style>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
    <div><h2 style="font-weight:800;color:#0f172a;margin:0;"><i class="fa-solid fa-chalkboard-user" style="color:#0f766e;"></i> Cursos y Capacitación</h2><p style="color:#64748b;margin:0;">Registro y seguimiento de cursos de formación del área.</p></div>
    <button class="btn btn-primary" onclick="abrirModalCrear()" style="background:#0f766e;border-color:#0f766e;"><i class="fa-solid fa-plus"></i> Nuevo Curso</button>
</div>
<?php if($flash): ?><div class="alert alert-<?=$tipoF?> alert-dismissible fade show" role="alert"><?=esc($flash)?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<div style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;">
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;">
        <select name="estatus" class="form-control" style="width:160px;" onchange="this.form.submit()"><option value="">Todos los estatus</option><?php foreach($estatuses as $e): ?><option value="<?=$e?>" <?=$filtroEst===$e?'selected':''?>><?=ucfirst(str_replace('_',' ',$e))?></option><?php endforeach; ?></select>
        <select name="modalidad" class="form-control" style="width:150px;" onchange="this.form.submit()"><option value="">Todas modalidades</option><?php foreach($modalidades as $m): ?><option value="<?=$m?>" <?=$filtroMod===$m?'selected':''?>><?=ucfirst($m)?></option><?php endforeach; ?></select>
        <?php if($filtroEst||$filtroMod): ?><a href="cursos.php" class="btn" style="background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;padding:9px 14px;border-radius:10px;font-size:.88rem;">Limpiar</a><?php endif; ?>
    </form>
    <span style="margin-left:auto;align-self:center;font-size:.85rem;color:#64748b;"><?=count($cursos)?> cursos</span>
</div>

<div style="background:#fff;border-radius:18px;border:1px solid #e2e8f0;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,.04);">
<?php if(empty($cursos)): ?>
<div style="padding:70px;text-align:center;color:#94a3b8;"><i class="fa-solid fa-chalkboard-user" style="font-size:2.8rem;opacity:.3;margin-bottom:14px;display:block;"></i><p>No hay cursos registrados.</p></div>
<?php else: ?>
<div style="overflow-x:auto;">
<table class="ja-table">
    <thead><tr><th>#</th><th>Curso</th><th>Modalidad</th><th>Ponente</th><th>Horas</th><th>Fecha</th><th>Cupo</th><th>Estatus</th><th>Acciones</th></tr></thead>
    <tbody>
    <?php foreach($cursos as $c): $pct=$c['cupo']>0?min(100,round(($c['inscritos']??0)/$c['cupo']*100)):0; ?>
    <tr>
        <td style="color:#94a3b8;font-size:.78rem;font-weight:600;">#<?=str_pad($c['id'],4,'0',STR_PAD_LEFT)?></td>
        <td style="font-weight:600;color:#0f172a;max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?=esc($c['nombre'])?></td>
        <td><span class="badge-pill badge-<?=$c['modalidad']?>"><?=ucfirst(esc($c['modalidad']))?></span></td>
        <td style="font-size:.82rem;"><?=esc($c['ponente']?:'—')?></td>
        <td><?=esc($c['horas']?:'—')?> h</td>
        <td style="font-size:.78rem;white-space:nowrap;"><?=$c['fecha_inicio']?date('d/m/Y',strtotime($c['fecha_inicio'])):'—'?></td>
        <td style="white-space:nowrap;"><?=($c['inscritos']??0)?>/<?=($c['cupo']??'∞')?><span class="cupo-bar"><span class="cupo-fill" style="width:<?=$pct?>%;display:block;"></span></span></td>
        <td><span class="badge-pill badge-<?=$c['estatus']?>"><?=ucfirst(str_replace('_',' ',$c['estatus']))?></span></td>
        <td>
            <button class="action-btn" onclick="abrirModalEditar(<?=htmlspecialchars(json_encode($c),ENT_QUOTES)?>)"><i class="fa-solid fa-pencil"></i></button>
            <button class="action-btn danger" onclick="eliminar(<?=$c['id']?>,'<?=esc($c['nombre'])?>')"><i class="fa-solid fa-trash-can"></i></button>
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
    <div class="modal-header"><h3 class="modal-title"><i class="fa-solid fa-chalkboard-user"></i> Nuevo Curso</h3><button class="modal-close" onclick="cerrarModal('modalCrear')"><i class="fa-solid fa-xmark"></i></button></div>
    <form method="POST" action="api/cursos.php"><?=csrfField()?><input type="hidden" name="accion" value="crear">
    <div class="modal-body">
        <div class="mb-14"><label class="form-label">Nombre del Curso *</label><input type="text" name="nombre" class="form-control" required></div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;" class="mb-14">
            <div><label class="form-label">Modalidad</label><select name="modalidad" class="form-control"><?php foreach($modalidades as $m): ?><option value="<?=$m?>"><?=ucfirst($m)?></option><?php endforeach; ?></select></div>
            <div><label class="form-label">Horas</label><input type="number" name="horas" class="form-control" min="1"></div>
            <div><label class="form-label">Estatus</label><select name="estatus" class="form-control"><?php foreach($estatuses as $e): ?><option value="<?=$e?>"><?=ucfirst(str_replace('_',' ',$e))?></option><?php endforeach; ?></select></div>
        </div>
        <div class="mb-14"><label class="form-label">Ponente / Instructor</label><input type="text" name="ponente" class="form-control"></div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;" class="mb-14">
            <div><label class="form-label">Inicio</label><input type="date" name="fecha_inicio" class="form-control"></div>
            <div><label class="form-label">Fin</label><input type="date" name="fecha_fin" class="form-control"></div>
            <div><label class="form-label">Cupo</label><input type="number" name="cupo" class="form-control" min="1"></div>
        </div>
        <div><label class="form-label">Descripción</label><textarea name="descripcion" class="form-control" rows="3"></textarea></div>
    </div>
    <div class="modal-footer"><button type="button" onclick="cerrarModal('modalCrear')" class="btn" style="background:#f1f5f9;color:#475569;">Cancelar</button><button type="submit" class="btn btn-primary" style="background:#0f766e;border-color:#0f766e;"><i class="fa-solid fa-check"></i> Guardar</button></div>
    </form>
</div></div>

<!-- Modal Editar -->
<div class="modal-backdrop" id="modalEditar"><div class="modal">
    <div class="modal-header"><h3 class="modal-title"><i class="fa-solid fa-pencil"></i> Editar Curso</h3><button class="modal-close" onclick="cerrarModal('modalEditar')"><i class="fa-solid fa-xmark"></i></button></div>
    <form method="POST" action="api/cursos.php"><?=csrfField()?><input type="hidden" name="accion" value="editar"><input type="hidden" name="id" id="ed_id">
    <div class="modal-body">
        <div class="mb-14"><label class="form-label">Nombre *</label><input type="text" name="nombre" id="ed_nom" class="form-control" required></div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;" class="mb-14">
            <div><label class="form-label">Modalidad</label><select name="modalidad" id="ed_mod" class="form-control"><?php foreach($modalidades as $m): ?><option value="<?=$m?>"><?=ucfirst($m)?></option><?php endforeach; ?></select></div>
            <div><label class="form-label">Horas</label><input type="number" name="horas" id="ed_horas" class="form-control" min="1"></div>
            <div><label class="form-label">Estatus</label><select name="estatus" id="ed_est" class="form-control"><?php foreach($estatuses as $e): ?><option value="<?=$e?>"><?=ucfirst(str_replace('_',' ',$e))?></option><?php endforeach; ?></select></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;" class="mb-14">
            <div><label class="form-label">Ponente</label><input type="text" name="ponente" id="ed_pon" class="form-control"></div>
            <div><label class="form-label">Inscritos</label><input type="number" name="inscritos" id="ed_ins" class="form-control" min="0"></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;" class="mb-14">
            <div><label class="form-label">Inicio</label><input type="date" name="fecha_inicio" id="ed_fi" class="form-control"></div>
            <div><label class="form-label">Fin</label><input type="date" name="fecha_fin" id="ed_ff" class="form-control"></div>
            <div><label class="form-label">Cupo</label><input type="number" name="cupo" id="ed_cupo" class="form-control" min="1"></div>
        </div>
        <div><label class="form-label">Descripción</label><textarea name="descripcion" id="ed_desc" class="form-control" rows="3"></textarea></div>
    </div>
    <div class="modal-footer"><button type="button" onclick="cerrarModal('modalEditar')" class="btn" style="background:#f1f5f9;color:#475569;">Cancelar</button><button type="submit" class="btn btn-primary" style="background:#0f766e;border-color:#0f766e;"><i class="fa-solid fa-check"></i> Actualizar</button></div>
    </form>
</div></div>
<form method="POST" action="api/cursos.php" id="frmEliminar" style="display:none;"><?=csrfField()?><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" id="del_id"></form>
<script>
function cerrarModal(id){document.getElementById(id).classList.remove('active');}
function abrirModalCrear(){document.getElementById('modalCrear').classList.add('active');}
function abrirModalEditar(c){
    document.getElementById('ed_id').value=c.id;document.getElementById('ed_nom').value=c.nombre;
    document.getElementById('ed_horas').value=c.horas||'';document.getElementById('ed_pon').value=c.ponente||'';
    document.getElementById('ed_fi').value=c.fecha_inicio||'';document.getElementById('ed_ff').value=c.fecha_fin||'';
    document.getElementById('ed_cupo').value=c.cupo||'';document.getElementById('ed_ins').value=c.inscritos||'0';
    document.getElementById('ed_desc').value=c.descripcion||'';
    const sel=(id,val)=>{const s=document.getElementById(id);[...s.options].forEach(o=>o.selected=o.value===val);};
    sel('ed_mod',c.modalidad);sel('ed_est',c.estatus);
    document.getElementById('modalEditar').classList.add('active');
}
function eliminar(id,nombre){if(!confirm(`¿Eliminar "${nombre}"?`))return;document.getElementById('del_id').value=id;document.getElementById('frmEliminar').submit();}
document.addEventListener('keydown',e=>{if(e.key==='Escape')document.querySelectorAll('.modal-backdrop.active').forEach(m=>m.classList.remove('active'));});
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
