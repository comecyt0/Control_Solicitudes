<?php
/**
 * COMECyT — Publicaciones (Apoyo Investigación)
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../config/auth.php';
verificarSesionAdmin();
$pdo = getConnection();

$filtroTipo = getParam('tipo','');$filtroAnio = getParam('anio','');
$where='WHERE 1=1';$params=[];
if($filtroTipo){$where.=' AND pub.tipo=?';$params[]=$filtroTipo;}
if($filtroAnio){$where.=' AND pub.anio=?';$params[]=(int)$filtroAnio;}
$stmt=$pdo->prepare("SELECT pub.*, proy.nombre AS proy_nombre FROM inv_publicaciones pub LEFT JOIN inv_proyectos proy ON pub.proyecto_id=proy.id $where ORDER BY pub.anio DESC NULLS LAST, pub.created_at DESC");
$stmt->execute($params);$publicaciones=$stmt->fetchAll();

$aniosDisp=$pdo->query("SELECT DISTINCT anio FROM inv_publicaciones WHERE anio IS NOT NULL ORDER BY anio DESC")->fetchAll(PDO::FETCH_COLUMN);
$proyectos=$pdo->query("SELECT id,nombre FROM inv_proyectos WHERE estatus='activo' ORDER BY nombre")->fetchAll();
$tipos=['articulo','libro','capitulo','ponencia'];

$fc=getParam('flash');$flash='';$tipoF='';
if($fc==='creado'){$flash='Publicación registrada.';$tipoF='success';}
elseif($fc==='editado'){$flash='Publicación actualizada.';$tipoF='success';}
elseif($fc==='eliminado'){$flash='Publicación eliminada.';$tipoF='success';}

$pageTitle='Publicaciones';$activeMenu='publicaciones';
require_once __DIR__ . '/../../includes/header_admin.php';
?>
<style>
:root{--inv-primary:#3730a3;--inv-accent:#B19A6D;}
.badge-articulo{background:#ede9fe;color:#6d28d9;}.badge-libro{background:#dcfce7;color:#16a34a;}.badge-capitulo{background:#fef3c7;color:#d97706;}.badge-ponencia{background:#e0f2fe;color:#0284c7;}
.badge-tipo{padding:3px 9px;border-radius:12px;font-size:.72rem;font-weight:700;}
.pub-table{width:100%;border-collapse:collapse;}.pub-table th{text-align:left;padding:12px 16px;font-size:.78rem;text-transform:uppercase;color:#64748b;background:#f8fafc;border-bottom:2px solid #e2e8f0;}
.pub-table td{padding:13px 16px;border-bottom:1px solid #f1f5f9;vertical-align:middle;}.pub-table tr:last-child td{border-bottom:none;}.pub-table tr:hover td{background:#f8fafc;}
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
    <div><h2 style="font-weight:800;color:#0f172a;margin:0;"><i class="fa-solid fa-book-open" style="color:#3730a3;"></i> Publicaciones</h2><p style="color:#64748b;margin:0;">Artículos, libros, capítulos de libros y ponencias.</p></div>
    <div style="display:flex;gap:8px;">
        <a href="api/publicaciones.php?accion=exportar_csv&csrf_token=<?=$_SESSION['csrf_token']?>" class="btn" style="background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;font-size:.88rem;"><i class="fa-solid fa-file-csv"></i> Exportar CSV</a>
        <button class="btn btn-primary" onclick="abrirModalCrear()" style="background:#3730a3;border-color:#3730a3;"><i class="fa-solid fa-plus"></i> Nueva Publicación</button>
    </div>
</div>
<?php if($flash): ?><div class="alert alert-<?=$tipoF?> alert-dismissible fade show" role="alert"><?=esc($flash)?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<div style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;">
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;">
        <select name="tipo" class="form-control" style="width:150px;" onchange="this.form.submit()"><option value="">Todos los tipos</option><?php foreach($tipos as $t): ?><option value="<?=$t?>" <?=$filtroTipo===$t?'selected':''?>><?=ucfirst($t)?></option><?php endforeach; ?></select>
        <select name="anio" class="form-control" style="width:110px;" onchange="this.form.submit()"><option value="">Todos los años</option><?php foreach($aniosDisp as $a): ?><option value="<?=$a?>" <?=($filtroAnio==(string)$a)?'selected':''?>><?=$a?></option><?php endforeach; ?></select>
        <?php if($filtroTipo||$filtroAnio): ?><a href="publicaciones.php" class="btn" style="background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;padding:9px 14px;border-radius:10px;font-size:.88rem;">Limpiar</a><?php endif; ?>
    </form>
    <span style="margin-left:auto;align-self:center;font-size:.85rem;color:#64748b;"><?=count($publicaciones)?> publicaciones</span>
</div>

<div style="background:#fff;border-radius:18px;border:1px solid #e2e8f0;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,.04);">
<?php if(empty($publicaciones)): ?>
    <div style="padding:70px;text-align:center;color:#94a3b8;"><i class="fa-solid fa-book-open" style="font-size:2.8rem;opacity:.3;margin-bottom:14px;display:block;"></i><p>No hay publicaciones registradas.</p></div>
<?php else: ?>
<div style="overflow-x:auto;">
<table class="pub-table">
    <thead><tr><th>#</th><th>Título</th><th>Tipo</th><th>Autores</th><th>Editorial</th><th>Año</th><th>DOI/ISBN</th><th>Acciones</th></tr></thead>
    <tbody>
    <?php foreach($publicaciones as $pub): $cls='badge-'.$pub['tipo']; ?>
    <tr>
        <td style="color:#94a3b8;font-size:.78rem;font-weight:600;">#<?=str_pad($pub['id'],4,'0',STR_PAD_LEFT)?></td>
        <td style="font-weight:600;color:#0f172a;max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?=esc($pub['titulo'])?></td>
        <td><span class="badge-tipo <?=$cls?>"><?=ucfirst(esc($pub['tipo']))?></span></td>
        <td style="font-size:.82rem;max-width:150px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?=esc(mb_strimwidth($pub['autores']??'—',0,40,'...'))?></td>
        <td style="font-size:.82rem;"><?=esc($pub['editorial']?:'—')?></td>
        <td><?=esc($pub['anio']?:'—')?></td>
        <td><?php if(!empty($pub['doi_isbn'])&&!empty($pub['url_acceso'])): ?><a href="<?=esc($pub['url_acceso'])?>" target="_blank" style="color:#3730a3;font-size:.8rem;"><?=esc(mb_strimwidth($pub['doi_isbn'],0,20,'...'))?></a><?php else: ?><span style="font-size:.8rem;"><?=esc($pub['doi_isbn']?:'—')?></span><?php endif; ?></td>
        <td>
            <?php if(!empty($pub['url_acceso'])): ?><a href="<?=esc($pub['url_acceso'])?>" target="_blank" class="action-btn" title="Ver"><i class="fa-solid fa-arrow-up-right-from-square"></i></a><?php endif; ?>
            <button class="action-btn" onclick="abrirModalEditar(<?=htmlspecialchars(json_encode($pub),ENT_QUOTES)?>)"><i class="fa-solid fa-pencil"></i></button>
            <button class="action-btn danger" onclick="eliminar(<?=$pub['id']?>,'<?=esc($pub['titulo'])?>')"><i class="fa-solid fa-trash-can"></i></button>
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
    <div class="modal-header"><h3 class="modal-title"><i class="fa-solid fa-book-open"></i> Nueva Publicación</h3><button class="modal-close" onclick="cerrarModal('modalCrear')"><i class="fa-solid fa-xmark"></i></button></div>
    <form method="POST" action="api/publicaciones.php"><?=csrfField()?><input type="hidden" name="accion" value="crear">
    <div class="modal-body">
        <div class="mb-14"><label class="form-label">Título *</label><input type="text" name="titulo" class="form-control" required></div>
        <div class="mb-14"><label class="form-label">Autores</label><textarea name="autores" class="form-control" rows="2" placeholder="Apellido, N.; Apellido2, N2."></textarea></div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;" class="mb-14">
            <div><label class="form-label">Tipo *</label><select name="tipo" class="form-control"><?php foreach($tipos as $t): ?><option value="<?=$t?>"><?=ucfirst($t)?></option><?php endforeach; ?></select></div>
            <div><label class="form-label">Año</label><input type="number" name="anio" class="form-control" min="1990" max="2099" value="<?=date('Y')?>"></div>
            <div><label class="form-label">Editorial</label><input type="text" name="editorial" class="form-control"></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;" class="mb-14">
            <div><label class="form-label">DOI / ISBN</label><input type="text" name="doi_isbn" class="form-control" placeholder="10.xxxx/..."></div>
            <div><label class="form-label">URL de Acceso</label><input type="url" name="url_acceso" class="form-control" placeholder="https://..."></div>
        </div>
        <div><label class="form-label">Proyecto Vinculado</label>
            <select name="proyecto_id" class="form-control"><option value="">Sin vincular</option><?php foreach($proyectos as $proy): ?><option value="<?=$proy['id']?>"><?=esc($proy['nombre'])?></option><?php endforeach; ?></select>
        </div>
    </div>
    <div class="modal-footer"><button type="button" onclick="cerrarModal('modalCrear')" class="btn" style="background:#f1f5f9;color:#475569;">Cancelar</button><button type="submit" class="btn btn-primary" style="background:#3730a3;border-color:#3730a3;"><i class="fa-solid fa-check"></i> Guardar</button></div>
    </form>
</div></div>

<!-- Modal Editar -->
<div class="modal-backdrop" id="modalEditar"><div class="modal">
    <div class="modal-header"><h3 class="modal-title"><i class="fa-solid fa-pencil"></i> Editar Publicación</h3><button class="modal-close" onclick="cerrarModal('modalEditar')"><i class="fa-solid fa-xmark"></i></button></div>
    <form method="POST" action="api/publicaciones.php"><?=csrfField()?><input type="hidden" name="accion" value="editar"><input type="hidden" name="id" id="ed_id">
    <div class="modal-body">
        <div class="mb-14"><label class="form-label">Título *</label><input type="text" name="titulo" id="ed_titulo" class="form-control" required></div>
        <div class="mb-14"><label class="form-label">Autores</label><textarea name="autores" id="ed_autores" class="form-control" rows="2"></textarea></div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;" class="mb-14">
            <div><label class="form-label">Tipo</label><select name="tipo" id="ed_tipo" class="form-control"><?php foreach($tipos as $t): ?><option value="<?=$t?>"><?=ucfirst($t)?></option><?php endforeach; ?></select></div>
            <div><label class="form-label">Año</label><input type="number" name="anio" id="ed_anio" class="form-control"></div>
            <div><label class="form-label">Editorial</label><input type="text" name="editorial" id="ed_editorial" class="form-control"></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;" class="mb-14">
            <div><label class="form-label">DOI / ISBN</label><input type="text" name="doi_isbn" id="ed_doi" class="form-control"></div>
            <div><label class="form-label">URL Acceso</label><input type="url" name="url_acceso" id="ed_url" class="form-control"></div>
        </div>
        <div><label class="form-label">Proyecto Vinculado</label>
            <select name="proyecto_id" id="ed_proy" class="form-control"><option value="">Sin vincular</option><?php foreach($proyectos as $proy): ?><option value="<?=$proy['id']?>"><?=esc($proy['nombre'])?></option><?php endforeach; ?></select>
        </div>
    </div>
    <div class="modal-footer"><button type="button" onclick="cerrarModal('modalEditar')" class="btn" style="background:#f1f5f9;color:#475569;">Cancelar</button><button type="submit" class="btn btn-primary" style="background:#3730a3;border-color:#3730a3;"><i class="fa-solid fa-check"></i> Actualizar</button></div>
    </form>
</div></div>
<form method="POST" action="api/publicaciones.php" id="frmEliminar" style="display:none;"><?=csrfField()?><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" id="del_id"></form>

<script>
function cerrarModal(id){document.getElementById(id).classList.remove('active');}
function abrirModalCrear(){document.getElementById('modalCrear').classList.add('active');}
function abrirModalEditar(p){
    document.getElementById('ed_id').value=p.id;document.getElementById('ed_titulo').value=p.titulo;
    document.getElementById('ed_autores').value=p.autores||'';document.getElementById('ed_anio').value=p.anio||'';
    document.getElementById('ed_editorial').value=p.editorial||'';document.getElementById('ed_doi').value=p.doi_isbn||'';
    document.getElementById('ed_url').value=p.url_acceso||'';
    const sel=(id,val)=>{const s=document.getElementById(id);[...s.options].forEach(o=>o.selected=o.value==val);};
    sel('ed_tipo',p.tipo);sel('ed_proy',p.proyecto_id||'');
    document.getElementById('modalEditar').classList.add('active');
}
function eliminar(id,titulo){if(!confirm(`¿Eliminar "${titulo}"?`))return;document.getElementById('del_id').value=id;document.getElementById('frmEliminar').submit();}
document.addEventListener('keydown',e=>{if(e.key==='Escape')document.querySelectorAll('.modal-backdrop.active').forEach(m=>m.classList.remove('active'));});
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
