<?php
/**
 * COMECyT Control de Solicitudes
 * Vista Pública — Mi Perfil — v4.0
 * Arquitectura Multidepartamento
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/auth.php';

inicializarSesion();

// Validar cualquier tipo de sesión activa
$rol = null;
$id_actor = null;
$esAdmin = false;

if (!empty($_SESSION['admin_id'])) {
    $rol = 'admin';
    $id_actor = $_SESSION['admin_id'];
    $esAdmin = true;
} elseif (!empty($_SESSION['user_id'])) {
    $rol = 'usuario';
    $id_actor = $_SESSION['user_id'];
} elseif (!empty($_SESSION['ss_id'])) {
    $rol = 'servicio_social';
    $id_actor = $_SESSION['ss_id'];
} else {
    header("Location: ../admin/login.php");
    exit;
}

$pdo = getConnection();
$datos_perfil = [];

// Obtener la información del perfil según el rol del actor
if ($rol === 'admin') {
    $stmt = $pdo->prepare("SELECT a.id, a.nombre, a.email, a.rol, cp.cve_area, cp.fecha_nacimiento, cp.foto_perfil, cp.perfil_en_revision, ca.des_area AS area_nombre 
                           FROM administradores a 
                           LEFT JOIN cat_personal cp ON a.email = cp.email 
                           LEFT JOIN cat_areas ca ON cp.cve_area = ca.cve_area 
                           WHERE a.id = ?");
    $stmt->execute([$id_actor]);
    $datos_perfil = $stmt->fetch(PDO::FETCH_ASSOC);
} elseif ($rol === 'usuario') {
    $stmt = $pdo->prepare("SELECT u.id, cp.nombre, cp.email, cp.cve_area, cp.fecha_nacimiento, cp.foto_perfil, cp.perfil_en_revision, ca.des_area AS area_nombre 
                           FROM usuarios u 
                           INNER JOIN cat_personal cp ON u.email = cp.email 
                           LEFT JOIN cat_areas ca ON cp.cve_area = ca.cve_area 
                           WHERE u.id = ?");
    $stmt->execute([$id_actor]);
    $datos_perfil = $stmt->fetch(PDO::FETCH_ASSOC);
} elseif ($rol === 'servicio_social') {
    $stmt = $pdo->prepare("SELECT id, nombre, email, institucion AS area_nombre, fecha_nacimiento, foto_perfil, perfil_en_revision 
                           FROM ss_usuarios WHERE id = ?");
    $stmt->execute([$id_actor]);
    $datos_perfil = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$datos_perfil) {
    die("Error crítico: No se encontraron los datos de perfil para el usuario actual.");
}

$foto_ruta = $datos_perfil['foto_perfil'] ? BASE_URL . 'public/uploads/avatares/' . $datos_perfil['foto_perfil'] : BASE_URL . 'assets/img/default-avatar.png';

$pageTitle = 'Mi Perfil';
$hideSidebar = true; // Ocultar sidebar para vista limpia tipo Hub

$extraHead = '
<style>
/* Estilos Premium Unificados y Glassmorphism */
.profile-hub-hero {
    background: linear-gradient(135deg, rgba(102, 35, 49, 0.05) 0%, rgba(139, 47, 66, 0.1) 100%);
    border-radius: 20px;
    padding: 60px 40px;
    margin-bottom: 40px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.04);
    border: 1px solid rgba(102, 35, 49, 0.08);
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
}

.profile-avatar-wrapper {
    position: relative;
    margin-bottom: 24px;
    z-index: 2;
}

.profile-avatar {
    width: 140px;
    height: 140px;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid #ffffff;
    box-shadow: 0 15px 35px rgba(102, 35, 49, 0.2);
    transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

.profile-avatar-wrapper:hover .profile-avatar {
    transform: scale(1.05);
}

.profile-edit-badge {
    position: absolute;
    bottom: 5px;
    right: 5px;
    background: var(--color-primary);
    color: white;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    cursor: pointer;
    border: 3px solid #ffffff;
    transition: background 0.3s;
}

.profile-edit-badge:hover {
    background: var(--color-accent);
}

.profile-info h1 {
    font-size: 2.2rem;
    font-weight: 800;
    color: #1e293b;
    margin: 0 0 8px 0;
    letter-spacing: -0.5px;
}

.profile-info p {
    font-size: 1.1rem;
    color: #64748b;
    margin: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.premium-form-card {
    background: #ffffff;
    border-radius: 16px;
    box-shadow: 0 10px 30px -5px rgba(0, 0, 0, 0.05);
    border: 1px solid #f1f5f9;
    padding: 30px;
    margin-bottom: 30px;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.premium-form-card:hover {
    box-shadow: 0 15px 40px -5px rgba(102, 35, 49, 0.08);
}

.responsive-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
}

@media (max-width: 768px) {
    .responsive-grid-2 {
        grid-template-columns: 1fr;
    }
    .profile-info h1 {
        font-size: 1.8rem;
    }
}

.status-banner {
    background: #fffbeb;
    border-left: 4px solid #f59e0b;
    padding: 16px;
    border-radius: 8px;
    margin-bottom: 24px;
    color: #92400e;
    display: flex;
    align-items: center;
    gap: 12px;
}

/* Animaciones Reveal-up */
.reveal-up {
    opacity: 0;
    transform: translateY(30px);
    transition: opacity 0.8s cubic-bezier(0.165, 0.84, 0.44, 1), transform 0.8s cubic-bezier(0.165, 0.84, 0.44, 1);
}
.reveal-up.active {
    opacity: 1;
    transform: translateY(0);
}
</style>
';

require_once __DIR__ . '/../includes/header_user.php';
?>

<div class="container" style="max-width: 900px; margin: 0 auto; padding-top: 20px;">
    
    <?php if(!empty($datos_perfil['perfil_en_revision'])): ?>
    <div class="status-banner reveal-up" style="transition-delay: 0.1s;">
        <i class="fa-solid fa-clock-rotate-left fa-2x"></i>
        <div>
            <h4 style="margin:0; font-weight:700;">Tus cambios están en revisión</h4>
            <p style="margin:4px 0 0 0; font-size:0.9rem;">Las modificaciones a tu perfil fueron enviadas y están pendientes de aprobación por tu Administrador de Área. De momento visualizas tu información actual.</p>
        </div>
    </div>
    <?php endif; ?>

    <div class="profile-hub-hero reveal-up" style="transition-delay: 0.2s;">
        <div class="profile-avatar-wrapper">
            <img src="<?= $foto_ruta ?>" alt="Avatar de Perfil" class="profile-avatar" id="avatarPreview">
            <label for="fotoInput" class="profile-edit-badge" title="Cambiar fotografía">
                <i class="fa-solid fa-camera"></i>
            </label>
        </div>
        <div class="profile-info">
            <h1><?= esc($datos_perfil['nombre']) ?></h1>
            <p>
                <i class="fa-solid fa-building text-primary"></i> 
                <?= esc($datos_perfil['area_nombre'] ?? 'Sin Área Asignada') ?>
                <span style="opacity:0.3;">|</span>
                <i class="fa-solid fa-id-badge text-accent"></i> 
                <?= esc(ucfirst(str_replace('_', ' ', $rol))) ?>
            </p>
        </div>
    </div>

    <form id="formPerfil" action="api/perfil.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <input type="hidden" name="accion" value="actualizar">
        <input type="file" id="fotoInput" name="foto_perfil" style="display:none;" accept="image/jpeg, image/png, image/webp">

        <div class="premium-form-card reveal-up" style="transition-delay: 0.3s;">
            <h3 style="color: #334155; margin-bottom: 24px; font-weight: 700;">
                <i class="fa-solid fa-user-pen" style="color:var(--color-primary); margin-right:8px;"></i>
                Información Personal
            </h3>
            
            <div class="responsive-grid-2">
                <div class="form-group">
                    <label class="form-label" style="font-weight:600; color:#475569;">Nombre Completo</label>
                    <input type="text" name="nombre" class="form-control" value="<?= esc($datos_perfil['nombre']) ?>" required style="border-radius:10px;">
                </div>
                <div class="form-group">
                    <label class="form-label" style="font-weight:600; color:#475569;">Correo Electrónico (No modificable)</label>
                    <input type="email" class="form-control" value="<?= esc($datos_perfil['email']) ?>" readonly style="border-radius:10px; background:#f8fafc; color:#94a3b8; cursor:not-allowed;">
                </div>
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label class="form-label" style="font-weight:600; color:#475569;">Área de Adscripción</label>
                    <?php if($rol === 'servicio_social'): ?>
                        <input type="text" class="form-control" value="<?= esc($datos_perfil['area_nombre']) ?>" readonly style="border-radius:10px; background:#f8fafc; color:#94a3b8;">
                    <?php else: ?>
                        <select name="cve_area" class="form-control" style="border-radius:10px;">
                            <?php 
                            $stmt_areas = $pdo->query("SELECT cve_area, des_area FROM cat_areas ORDER BY cve_area ASC");
                            while($area = $stmt_areas->fetch(PDO::FETCH_ASSOC)):
                            ?>
                            <option value="<?= $area['cve_area'] ?>" <?= ($datos_perfil['cve_area'] == $area['cve_area']) ? 'selected' : '' ?>><?= esc($area['des_area']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="premium-form-card reveal-up" style="transition-delay: 0.4s;">
            <h3 style="color: #334155; margin-bottom: 24px; font-weight: 700;">
                <i class="fa-solid fa-cake-candles" style="color:var(--color-accent); margin-right:8px;"></i>
                Cumpleaños (Calendario Público)
            </h3>
            <p style="color:#64748b; font-size:0.9rem; margin-bottom: 20px;">Tu fecha de nacimiento se utilizará para destacar tu cumpleaños en el calendario institucional de tu área. El año no se revelará públicamente.</p>
            
            <div class="form-group" style="max-width:300px;">
                <label class="form-label" style="font-weight:600; color:#475569;">Fecha de Nacimiento</label>
                <input type="date" name="fecha_nacimiento" class="form-control" value="<?= esc($datos_perfil['fecha_nacimiento'] ?? '') ?>" required style="border-radius:10px;">
            </div>
        </div>

        <div class="reveal-up" style="text-align: right; transition-delay: 0.5s; margin-bottom: 60px;">
            <button type="submit" class="btn btn-primary btn-lg" style="box-shadow: 0 4px 15px rgba(102, 35, 49, 0.2); padding: 14px 40px; font-size: 1.1rem; border-radius: 12px; font-weight:700;">
                <i class="fa-solid fa-cloud-arrow-up"></i> Guardar Cambios
            </button>
        </div>
    </form>
</div>

<script>
// Animaciones al hacer scroll (Reveal Up)
document.addEventListener("DOMContentLoaded", () => {
    const reveals = document.querySelectorAll(".reveal-up");
    const revealOnScroll = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add("active");
                observer.unobserve(entry.target);
            }
        });
    }, { root: null, rootMargin: "0px 0px 0px 0px", threshold: 0.1 });
    reveals.forEach(el => revealOnScroll.observe(el));
});

// Previsualización de Avatar en tiempo real
document.getElementById('fotoInput').addEventListener('change', function(e) {
    if (this.files && this.files[0]) {
        if(this.files[0].size > 5 * 1024 * 1024) {
            COMECyTUI.alert('Error', 'La imagen es demasiado pesada. Máximo 5MB permitidos.');
            this.value = '';
            return;
        }
        const img = document.getElementById('avatarPreview');
        img.src = URL.createObjectURL(this.files[0]);
    }
});

// Manejo del formulario por AJAX
document.getElementById('formPerfil').addEventListener('submit', function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    fetch(this.action, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.ok) {
                COMECyTUI.info(res.msg, 'Perfil Actualizado');
                setTimeout(() => location.reload(), 2000);
            } else {
                COMECyTUI.alert('Error', res.error || 'Ocurrió un error al procesar la solicitud.');
            }
        }).catch(err => {
            COMECyTUI.alert('Error', 'Fallo de conexión con el servidor.');
        });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
