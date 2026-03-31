<?php
/**
 * COMECyT — Personal del Área (Financiamiento)
 * Solo lectura. cat_personal filtrado por cve_area = 15.
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../config/auth.php';
verificarSesionAdmin();
$pdo = getConnection();
$stmt = $pdo->prepare("SELECT cve_personal,nombre,appat,apmat,puesto,correo_institucional,correo_personal,telefono,foto_perfil,activo FROM cat_personal WHERE cve_area=15 ORDER BY nombre,appat");
$stmt->execute();
$personal = $stmt->fetchAll();
$totalActivo = count(array_filter($personal,fn($p)=>$p['activo']));
$pageTitle='Personal del Área';$activeMenu='personal';
require_once __DIR__ . '/../../includes/header_admin.php';
?>
<style>
:root{--fin-primary:#064e3b;--fin-accent:#B19A6D;}
.personal-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:28px;flex-wrap:wrap;gap:12px;}
.personal-header h2{font-size:1.6rem;font-weight:800;color:var(--fin-primary);margin:0;display:flex;align-items:center;gap:10px;}
.personal-header h2 i{color:var(--fin-accent);}
.personal-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:22px;}
.personal-card{background:#fff;border-radius:18px;border:1px solid #e2e8f0;overflow:hidden;transition:all .25s;box-shadow:0 4px 12px rgba(0,0,0,.04);}
.personal-card:hover{transform:translateY(-4px);box-shadow:0 14px 30px rgba(6,78,59,.1);border-color:var(--fin-accent);}
.personal-card-header{background:linear-gradient(135deg,var(--fin-primary),#022c22);padding:24px 20px 60px;position:relative;}
.personal-avatar{position:absolute;bottom:-36px;left:50%;transform:translateX(-50%);width:72px;height:72px;border-radius:50%;border:3px solid #fff;object-fit:cover;box-shadow:0 4px 14px rgba(0,0,0,.15);}
.personal-avatar-ph{position:absolute;bottom:-36px;left:50%;transform:translateX(-50%);width:72px;height:72px;border-radius:50%;background:var(--fin-accent);display:flex;align-items:center;justify-content:center;font-size:1.8rem;color:#fff;border:3px solid #fff;box-shadow:0 4px 14px rgba(0,0,0,.15);font-weight:700;}
.personal-card-body{padding:50px 20px 22px;text-align:center;}
.personal-nombre{font-size:1rem;font-weight:700;color:#0f172a;margin-bottom:4px;}
.personal-puesto{font-size:.8rem;color:#64748b;margin-bottom:16px;min-height:18px;}
.badge-activo{background:#dcfce7;color:#16a34a;border-radius:20px;padding:2px 10px;font-size:.72rem;font-weight:700;}
.badge-inactivo{background:#fee2e2;color:#dc2626;border-radius:20px;padding:2px 10px;font-size:.72rem;font-weight:700;}
.personal-meta{display:flex;flex-direction:column;gap:6px;margin-top:14px;}
.personal-meta-item{display:flex;align-items:center;gap:8px;font-size:.8rem;color:#475569;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.personal-meta-item i{color:var(--fin-accent);width:14px;flex-shrink:0;}
</style>
<div class="personal-header">
    <h2><i class="fa-solid fa-id-card"></i> Personal del Área</h2>
    <span style="background:rgba(6,78,59,.05);color:#064e3b;border-radius:20px;padding:5px 14px;font-size:.82rem;font-weight:600;"><?=$totalActivo?> activos · <?=count($personal)?> total</span>
</div>
<?php if(empty($personal)): ?><div style="text-align:center;padding:80px;color:#94a3b8;"><i class="fa-solid fa-users" style="font-size:3rem;opacity:.3;margin-bottom:16px;display:block;"></i><p>No hay personal registrado en esta área.</p></div>
<?php else: ?>
<div class="personal-grid">
<?php foreach($personal as $p): $nombre=trim($p['nombre'].' '.$p['appat'].' '.$p['apmat']);$ini=mb_strtoupper(mb_substr($p['nombre'],0,1,'UTF-8')); ?>
    <div class="personal-card">
        <div class="personal-card-header">
            <?php if(!empty($p['foto_perfil'])): ?><img src="<?=BASE_URL?>public/uploads/avatares/<?=esc($p['foto_perfil'])?>" class="personal-avatar" alt="<?=esc($nombre)?>"><?php else: ?><div class="personal-avatar-ph"><?=$ini?></div><?php endif; ?>
        </div>
        <div class="personal-card-body">
            <div class="personal-nombre"><?=esc($nombre)?></div>
            <div class="personal-puesto"><?=esc($p['puesto']?:'—')?></div>
            <span class="<?=$p['activo']?'badge-activo':'badge-inactivo'?>"><?=$p['activo']?'Activo':'Inactivo'?></span>
            <div class="personal-meta">
                <?php if(!empty($p['correo_institucional'])): ?><div class="personal-meta-item"><i class="fa-solid fa-envelope"></i><?=esc($p['correo_institucional'])?></div><?php endif; ?>
                <?php if(!empty($p['telefono'])): ?><div class="personal-meta-item"><i class="fa-solid fa-phone"></i><?=esc($p['telefono'])?></div><?php endif; ?>
            </div>
        </div>
    </div>
<?php endforeach; ?>
</div>
<?php endif; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
