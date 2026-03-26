<?php
/**
 * COMECyT — Chat de Dirección General
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../config/auth.php';

verificarSesionAdmin();

$pageTitle  = 'Chat Departamental';
$activeMenu = 'chat';

require_once __DIR__ . '/../../includes/header_admin.php';
?>

<div style="height: calc(100vh - 200px); display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; background: white; border-radius: 20px; border: 1px solid #e2e8f0; box-shadow: 0 10px 30px rgba(0,0,0,0.05);">
    <div style="width: 120px; height: 120px; border-radius: 30px; background: rgba(102, 35, 49, 0.1); color: var(--color-primary); display: flex; align-items: center; justify-content: center; font-size: 4rem; margin-bottom: 30px;">
        <i class="fa-solid fa-comments"></i>
    </div>
    <h2 style="color: var(--color-primary); font-size: 2.5rem; font-weight: 800; margin-bottom: 15px;">Comunicación Estratégica</h2>
    <p style="color: #64748b; font-size: 1.2rem; max-width: 500px; margin-bottom: 40px;">Utilice la plataforma de mensajería integrada para coordinarse con el equipo de Dirección General en tiempo real.</p>
    
    <button class="btn btn-primary" onclick="toggleChat()" style="padding: 15px 40px; font-size: 1.1rem; border-radius: 12px; box-shadow: 0 10px 20px rgba(102, 35, 49, 0.3);">
        <i class="fa-solid fa-comment-dots"></i> Abrir Chat del Área
    </button>
    
    <p style="margin-top: 30px; font-size: 0.9rem; color: #94a3b8;">También puede acceder rápidamente desde el icono de chat en la barra superior.</p>
</div>

<script>
// Auto-abrir chat al entrar a esta sección si no está abierto
document.addEventListener('DOMContentLoaded', () => {
    const panel = document.getElementById('chatPanel');
    if (panel && panel.style.display === 'none') {
        setTimeout(toggleChat, 500);
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
