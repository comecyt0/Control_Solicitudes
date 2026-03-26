<?php
/**
 * COMECyT — Asistente IA de Dirección General
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../config/auth.php';

verificarSesionAdmin();

$pageTitle  = 'Asistente IA Estratégico';
$activeMenu = 'ia';

require_once __DIR__ . '/../../includes/header_admin.php';
?>

<div style="height: calc(100vh - 200px); display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; background: white; border-radius: 20px; border: 1px solid #e2e8f0; box-shadow: 0 10px 30px rgba(0,0,0,0.05);">
    <div style="width: 120px; height: 120px; border-radius: 30px; background: rgba(177, 154, 109, 0.1); color: var(--color-accent); display: flex; align-items: center; justify-content: center; font-size: 4rem; margin-bottom: 30px;">
        <i class="fa-solid fa-robot"></i>
    </div>
    <h2 style="color: var(--color-primary); font-size: 2.5rem; font-weight: 800; margin-bottom: 15px;">Asistente Estratégico IA</h2>
    <p style="color: #64748b; font-size: 1.2rem; max-width: 500px; margin-bottom: 40px;">Consulte sobre procesos institucionales, estatus de proyectos o solicite ayuda con la redacción de documentos oficiales.</p>
    
    <button class="btn btn-primary" onclick="toggleAsistenteIA()" style="background: var(--color-accent); border-color: var(--color-accent); padding: 15px 40px; font-size: 1.1rem; border-radius: 12px; box-shadow: 0 10px 20px rgba(177, 154, 109, 0.3);">
        <i class="fa-solid fa-wand-magic-sparkles"></i> Iniciar Consulta IA
    </button>
    
    <p style="margin-top: 30px; font-size: 0.9rem; color: #94a3b8;">Disponible en todo momento desde el icono <i class="fa-solid fa-robot"></i> en la cabecera.</p>
</div>

<script>
// Auto-abrir IA al entrar
document.addEventListener('DOMContentLoaded', () => {
    const panel = document.getElementById('iaPanel');
    if (panel && panel.style.display === 'none') {
        setTimeout(toggleAsistenteIA, 500);
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
