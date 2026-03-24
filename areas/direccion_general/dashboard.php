<?php
/**
 * COMECyT Control de Solicitudes
 * Panel Departamental — En Desarrollo
 */

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../includes/helpers.php";
require_once __DIR__ . "/../../config/auth.php";

verificarSesionAdmin();

$pageTitle  = "Área en Desarrollo";
$activeMenu = "dashboard";

require_once __DIR__ . "/../../includes/header_admin.php";
?>

<div style="display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 70vh; text-align: center; padding: 40px;">
    <div style="width: 120px; height: 120px; background: rgba(190, 18, 60, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 30px; animation: pulse 2s infinite;">
        <i class="fa-solid fa-screwdriver-wrench" style="font-size: 50px; color: #be123c;"></i>
    </div>
    
    <h1 style="font-size: 2.5rem; font-weight: 800; color: #1e293b; margin-bottom: 16px;">Tu área está en desarrollo</h1>
    <p style="font-size: 1.1rem; color: #64748b; max-width: 600px; line-height: 1.6; margin-bottom: 40px;">
        Estamos trabajando para brindarte las mejores herramientas departamentales personalizadas para tu área. 
        Muy pronto podrás gestionar tus solicitudes y recursos aquí.
    </p>
    
    <a href="<?= BASE_URL ?>public/index.php" class="btn btn-primary" style="padding: 12px 30px; font-size: 1.1rem; border-radius: 12px; display: flex; align-items: center; gap: 10px; box-shadow: 0 10px 20px -5px rgba(190, 18, 60, 0.4);">
        <i class="fa-solid fa-house"></i>
        Volver al Hub de Intranet
    </a>
</div>

<style>
@keyframes pulse {
    0% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.05); opacity: 0.8; }
    100% { transform: scale(1); opacity: 1; }
}
</style>

<?php require_once __DIR__ . "/../../includes/footer.php"; ?>
