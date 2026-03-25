<?php
/**
 * Componente Unificado: Sidebar Footer
 * Renderiza el perfil del usuario, foto y acciones (Perfil / Salir)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$u_id     = $_SESSION['admin_id']    ?? $_SESSION['user_id']    ?? $_SESSION['ss_id']    ?? 0;
$u_nombre = $_SESSION['admin_nombre']  ?? $_SESSION['user_nombre']  ?? $_SESSION['ss_nombre']  ?? 'Usuario';
$u_rol    = $_SESSION['admin_rol']     ?? (isset($_SESSION['ss_id']) ? 'Servicio Social' : ($_SESSION['user_area'] ?? 'Área General'));
$u_foto   = $_SESSION['admin_foto']   ?? $_SESSION['user_foto']   ?? $_SESSION['ss_foto']   ?? null;

// Lazy Load Foto si no esta en sesion (evita requerir logout para ver cambios)
if ($u_foto === null && $u_id > 0) {
    try {
        require_once __DIR__ . '/../config/database.php';
        $pdoSF = getConnection();
        if (isset($_SESSION['admin_id'])) {
             $stSF = $pdoSF->prepare("SELECT cp.foto_perfil FROM administradores a LEFT JOIN cat_personal cp ON (a.email = cp.correo_institucional OR a.email = cp.correo_personal) WHERE a.id = ?");
             $stSF->execute([$_SESSION['admin_id']]);
             $u_foto = $stSF->fetchColumn();
             $_SESSION['admin_foto'] = $u_foto;
        } elseif (isset($_SESSION['user_id'])) {
             $stSF = $pdoSF->prepare("SELECT foto_perfil FROM cat_personal WHERE cve_personal = ?");
             $stSF->execute([$_SESSION['user_id']]);
             $u_foto = $stSF->fetchColumn();
             $_SESSION['user_foto'] = $u_foto;
        } elseif (isset($_SESSION['ss_id'])) {
             $stSF = $pdoSF->prepare("SELECT foto_perfil FROM ss_usuarios WHERE id = ?");
             $stSF->execute([$_SESSION['ss_id']]);
             $u_foto = $stSF->fetchColumn();
             $_SESSION['ss_foto'] = $u_foto;
        }
    } catch (Throwable $e) { /* Silently fail */ }
}

$u_avatar = $u_foto ? BASE_URL . 'public/uploads/avatares/' . $u_foto : BASE_URL . 'assets/img/default-avatar.png';
$u_perfil_url = BASE_URL . 'public/perfil.php';

// Definir logout segun rol prioritario
$u_logout_url = BASE_URL . 'admin/logout.php';
if (isset($_SESSION['ss_id'])) $u_logout_url = BASE_URL . 'servicio_social/logout.php';
elseif (isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])) $u_logout_url = BASE_URL . 'public/logout.php';
?>

<div class="sf-container">
    <div class="sf-user-block">
        <a href="<?= $u_perfil_url ?>" class="sf-avatar-wrapper" title="Editar mi perfil">
            <img src="<?= $u_avatar ?>" alt="Avatar" class="sf-avatar">
            <div class="sf-avatar-overlay">
                <i class="fa-solid fa-pen"></i>
            </div>
        </a>
        <div class="sf-details">
            <span class="sf-name" title="<?= esc($u_nombre) ?>"><?= esc($u_nombre) ?></span>
            <span class="sf-role"><?= esc($u_rol) ?></span>
        </div>
    </div>
    
    <div class="sf-actions">
        <a href="<?= $u_perfil_url ?>" class="sf-btn" title="Configurar Cuenta">
            <i class="fa-solid fa-gear"></i>
        </a>
        <a href="<?= $u_logout_url ?>" class="sf-btn sf-btn-danger" title="Cerrar sesión">
            <i class="fa-solid fa-right-from-bracket"></i>
        </a>
    </div>
</div>

<style>
/* Sidebar Footer - Unified Design System */
.sf-container {
    padding: 1.25rem 1rem;
    background: #f8fafc; /* Institutional Light Mode */
    border-top: 1px solid #e2e8f0;
    display: flex;
    flex-direction: column;
    gap: 1rem;
    transition: all 0.3s ease;
}

.sf-user-block {
    display: flex;
    align-items: center;
    gap: 0.85rem;
}

.sf-avatar-wrapper {
    position: relative;
    width: 44px;
    height: 44px;
    border-radius: 12px;
    overflow: hidden;
    flex-shrink: 0;
    box-shadow: 0 4px 10px rgba(102, 35, 49, 0.15);
    border: 2px solid white;
    cursor: pointer;
}

.sf-avatar {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.sf-avatar-overlay {
    position: absolute;
    inset: 0;
    background: rgba(102, 35, 49, 0.7);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.sf-avatar-wrapper:hover .sf-avatar {
    transform: scale(1.1);
}

.sf-avatar-wrapper:hover .sf-avatar-overlay {
    opacity: 1;
}

.sf-details {
    min-width: 0;
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.sf-name {
    display: block;
    font-size: 0.85rem;
    font-weight: 700;
    color: #1e293b;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1.25;
}

.sf-role {
    display: block;
    font-size: 0.7rem;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.02em;
    margin-top: 2px;
}

.sf-actions {
    display: flex;
    gap: 8px;
}

.sf-btn {
    flex: 1;
    height: 34px;
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #475569;
    text-decoration: none;
    font-size: 0.85rem;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}

.sf-btn:hover {
    background: #f1f5f9;
    color: var(--color-primary, #662331);
    border-color: #cbd5e1;
    transform: translateY(-1px);
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
}

.sf-btn-danger:hover {
    background: #fef2f2;
    color: #ef4444;
    border-color: #fecaca;
}

/* Dark mode compatibility (if layout-admin has .dark-mode) */
.dark-mode .sf-container {
    background: #1e293b;
    border-top-color: #334155;
}
.dark-mode .sf-name { color: #f8fafc; }
.dark-mode .sf-role { color: #94a3b8; }
.dark-mode .sf-btn {
    background: #334155;
    border-color: #475569;
    color: #cbd5e1;
}
.dark-mode .sf-btn:hover {
    background: #475569;
    color: white;
}
</style>
