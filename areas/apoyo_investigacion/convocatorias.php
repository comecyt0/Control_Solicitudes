<?php
/**
 * COMECyT — Convocatorias (Apoyo Investigación) — SOLO LECTURA
 * Reutiliza fin_convocatorias filtrando activas. Sin botones de CRUD.
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../config/auth.php';
verificarSesionAdmin();
$pdo = getConnection();

$filtroTipo = getParam('tipo','');
$where='WHERE estatus=\'activa\'';$params=[];
if($filtroTipo){$where.=' AND tipo=?';$params[]=$filtroTipo;}
$stmt=$pdo->prepare("SELECT * FROM fin_convocatorias $where ORDER BY fecha_cierre ASC NULLS LAST, created_at DESC");
$stmt->execute($params);$convocatorias=$stmt->fetchAll();

$tipos=['fondo','beca','apoyo','concurso'];
$pageTitle='Convocatorias';$activeMenu='convocatorias';
require_once __DIR__ . '/../../includes/header_admin.php';
?>
<style>
:root{--inv-primary:#3730a3;--inv-accent:#B19A6D;}
.conv-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:18px;}
.conv-card{background:#fff;border-radius:16px;border:1px solid #e2e8f0;padding:22px;box-shadow:0 4px 12px rgba(0,0,0,.04);transition:all .25s;border-top:4px solid var(--inv-primary);}
.conv-card:hover{transform:translateY(-4px);box-shadow:0 12px 28px rgba(55,48,163,.1);border-top-color:var(--inv-accent);}
.conv-card-titulo{font-size:1rem;font-weight:700;color:#0f172a;margin-bottom:8px;line-height:1.4;}
.conv-card-dep{font-size:.82rem;color:#64748b;margin-bottom:12px;}
.badge-tipo{padding:3px 9px;border-radius:12px;font-size:.72rem;font-weight:700;}
.badge-fondo{background:#ede9fe;color:#6d28d9;}.badge-beca{background:#e0f2fe;color:#0284c7;}.badge-apoyo{background:#dcfce7;color:#16a34a;}.badge-concurso{background:#fef3c7;color:#d97706;}
.conv-meta{display:flex;flex-direction:column;gap:5px;margin:12px 0;}
.conv-meta-item{display:flex;align-items:center;gap:7px;font-size:.8rem;color:#64748b;}
.conv-meta-item i{color:var(--inv-accent);width:13px;}
.conv-footer{display:flex;justify-content:space-between;align-items:center;padding-top:12px;border-top:1px solid #f1f5f9;margin-top:8px;}
.dias-badge{font-size:.75rem;font-weight:700;padding:3px 10px;border-radius:12px;}
.form-control{border-radius:10px;border:1.5px solid #e2e8f0;background:#f8fafc;padding:9px 13px;font-family:inherit;font-size:.9rem;transition:all .2s;}
.form-control:focus{border-color:#3730a3;outline:none;}
</style>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
    <div>
        <h2 style="font-weight:800;color:#0f172a;margin:0;"><i class="fa-solid fa-satellite-dish" style="color:#3730a3;"></i> Convocatorias Activas</h2>
        <p style="color:#64748b;margin:0;">Fondos, becas y apoyos disponibles actualmente. Vista de consulta.</p>
    </div>
    <span style="background:rgba(55,48,163,.06);color:#3730a3;border-radius:20px;padding:5px 14px;font-size:.82rem;font-weight:600;"><?=count($convocatorias)?> convocatorias abiertas</span>
</div>

<div style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;">
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;">
        <select name="tipo" class="form-control" style="width:160px;" onchange="this.form.submit()"><option value="">Todos los tipos</option><?php foreach($tipos as $t): ?><option value="<?=$t?>" <?=$filtroTipo===$t?'selected':''?>><?=ucfirst($t)?></option><?php endforeach; ?></select>
        <?php if($filtroTipo): ?><a href="convocatorias.php" class="btn" style="background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;padding:9px 14px;border-radius:10px;font-size:.88rem;">Ver todas</a><?php endif; ?>
    </form>
</div>

<?php if(empty($convocatorias)): ?>
<div style="background:#fff;border-radius:18px;padding:70px;text-align:center;color:#94a3b8;border:1px solid #e2e8f0;">
    <i class="fa-solid fa-satellite-dish" style="font-size:2.8rem;opacity:.3;margin-bottom:14px;display:block;"></i>
    <p>No hay convocatorias activas en este momento.</p>
</div>
<?php else: ?>
<div class="conv-grid">
<?php foreach($convocatorias as $c):
    $dias = $c['fecha_cierre'] ? (int)(new DateTime($c['fecha_cierre']))->diff(new DateTime())->format('%r%a') : null;
    $clsBadge = $dias !== null && $dias <= 15 ? 'background:#fef3c7;color:#d97706;' : 'background:#dcfce7;color:#16a34a;';
    $tipolbl = 'badge-'.($c['tipo']?:'fondo');
?>
<div class="conv-card">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px;">
        <span class="badge-tipo <?=$tipolbl?>"><?=ucfirst(esc($c['tipo']))?></span>
        <?php if($c['monto_max']): ?><span style="font-size:.8rem;font-weight:700;color:#3730a3;">$<?=number_format($c['monto_max'],0)?></span><?php endif; ?>
    </div>
    <div class="conv-card-titulo"><?=esc($c['titulo'])?></div>
    <div class="conv-card-dep"><i class="fa-solid fa-building fa-xs"></i> <?=esc($c['dependencia']?:'Sin dependencia especificada')?></div>
    <div class="conv-meta">
        <?php if($c['fecha_inicio']): ?><div class="conv-meta-item"><i class="fa-solid fa-calendar-check"></i>Inicio: <?=date('d/m/Y',strtotime($c['fecha_inicio']))?></div><?php endif; ?>
        <?php if($c['fecha_cierre']): ?><div class="conv-meta-item"><i class="fa-solid fa-calendar-xmark"></i>Cierre: <?=date('d/m/Y',strtotime($c['fecha_cierre']))?></div><?php endif; ?>
        <?php if($c['descripcion']): ?><div class="conv-meta-item" style="white-space:normal;"><i class="fa-solid fa-info-circle"></i><?=esc(mb_strimwidth($c['descripcion'],0,90,'...'))?></div><?php endif; ?>
    </div>
    <div class="conv-footer">
        <?php if($dias!==null): ?><span class="dias-badge" style="<?=$clsBadge?>"><?=$dias<=15?"Cierra en {$dias} días":'Vigente'?></span><?php else: ?><span></span><?php endif; ?>
        <?php if(!empty($c['url_info'])): ?><a href="<?=esc($c['url_info'])?>" target="_blank" class="btn" style="background:var(--inv-primary);color:#fff;border:none;padding:6px 14px;border-radius:8px;font-size:.8rem;font-weight:600;text-decoration:none;" rel="noopener">Ver Convocatoria <i class="fa-solid fa-external-link"></i></a><?php endif; ?>
    </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
