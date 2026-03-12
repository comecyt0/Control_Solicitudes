<?php
// Prevenir inclusiones duplicadas del componente de carga
if (!defined('LOADER_INCLUDED')) {
    define('LOADER_INCLUDED', true);
    // Definir la base_url si no fue inyectada por el archivo padre
    $baseUrl = defined('BASE_URL') ? BASE_URL : '/COMECyT_Solicitudes/';
?>
<!-- Loader Global (Pantalla de Carga) Autónoma e Inmediata (No FOUC) -->
<style>
/* Estilos en línea críticos para evitar FOUC y problemas de caché del navegador */
.global-loader-comecyt {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background-color: #ffffff;
    z-index: 9999999;
    display: flex;
    justify-content: center;
    align-items: center;
    visibility: visible;
    opacity: 1;
    transition: opacity 0.4s ease-out, visibility 0.4s ease-out;
}
.global-loader-comecyt.hidden {
    opacity: 0;
    visibility: hidden;
}
.global-loader-comecyt .loader-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
    padding: 20px;
}
.global-loader-comecyt .loader-logo {
    max-width: 250px;
    width: 80%;
    z-index: 2;
    /* Elimina el cuadrado blanco de fondo de la imagen JPEG/PNG */
    mix-blend-mode: multiply; 
}
/* Efecto de goteo de agua (Ripple) */
.global-loader-comecyt .loader-content::before,
.global-loader-comecyt .loader-content::after {
    content: "";
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 140px;
    height: 140px;
    border-radius: 50%;
    border: 3px solid #662331; /* Color principal Guinda */
    opacity: 0;
    z-index: 1;
    animation: rippleEffect 2.5s cubic-bezier(0.1, 0.8, 0.3, 1) infinite;
}
.global-loader-comecyt .loader-content::after {
    animation-delay: 1.25s;
    border-color: #B19A6D; /* Color secundario Dorado */
}

@keyframes rippleEffect {
    0% { width: 140px; height: 140px; opacity: 1; }
    100% { width: 380px; height: 380px; opacity: 0; }
}
</style>

<div id="global-loader" class="global-loader-comecyt">
    <div class="loader-content">
        <img src="<?= $baseUrl ?>assets/pantalla_carga.png" alt="Cargando Sistema COMECyT..." class="loader-logo">
        <div class="loader-spinner"></div>
    </div>
</div>

<script>
// Script embebido para garantizar ejecución inmediata independientemente del cache de app.js
(function() {
    // Al cargar la pagina, ocultar suavemente
    window.addEventListener('pageshow', function() {
        var loader = document.getElementById('global-loader');
        if (loader) {
            setTimeout(function() {
                loader.classList.add('hidden');
            }, 100);
        }
    });

    // Al salir de la pagina, mostrar suavemente
    window.addEventListener('beforeunload', function() {
        var loader = document.getElementById('global-loader');
        if (loader) {
            loader.classList.remove('hidden');
        }
    });
})();
</script>
<?php } ?>
