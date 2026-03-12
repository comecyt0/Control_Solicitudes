</main>
</div>

<!-- Scripts Comunes -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    // Toggle Sidebar en Dispositivos Moviles
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');

    if (menuToggle && sidebar && overlay) {
        function toggleMenu() {
            const isExpanded = menuToggle.getAttribute('aria-expanded') === 'true';
            menuToggle.setAttribute('aria-expanded', !isExpanded);
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
            document.body.style.overflow = isExpanded ? '' : 'hidden'; // Prevenir scroll del body
        }

        menuToggle.addEventListener('click', toggleMenu);
        overlay.addEventListener('click', toggleMenu);
    }
    
    // Auto-ocultar alertas web (flash messages)
    const alertBox = document.querySelector('.alert');
    if (alertBox) {
        setTimeout(() => alertBox.style.display = 'none', 5000);
    }
});
</script>
</body>
</html>
