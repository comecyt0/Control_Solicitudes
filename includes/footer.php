    </main><!-- /.page-content -->
</div><!-- /.main-wrapper -->

<!-- Global Custom Confirm Modal -->
<div class="modal-backdrop" id="modalConfirmacionGlobal" style="z-index: 9999;">
    <div class="modal" style="max-width: 400px; text-align: center;">
        <div class="modal-body" style="padding: 2rem 1.5rem 1.5rem;">
            <div style="font-size: 3rem; color: #DC2626; margin-bottom: 1rem;">
                <i class="fa-solid fa-circle-exclamation" id="confirmIcon"></i>
            </div>
            <h3 id="confirmTitle" style="margin-bottom: 0.5rem; font-size: 1.25rem;">¿Estás seguro?</h3>
            <p id="confirmMessage" style="color: #64748b; margin-bottom: 1.5rem; font-size: 0.95rem;"></p>
            
            <div style="display: flex; gap: 10px; justify-content: center;">
                <button type="button" class="btn btn-outline" id="btnConfirmCancel" style="flex: 1;">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnConfirmAccept" style="flex: 1; background-color: #DC2626; border-color: #DC2626;">Confirmar</button>
            </div>
        </div>
    </div>
</div>

<script src="<?= BASE_URL ?>assets/js/app.js?v=<?= filemtime(__DIR__ . '/../assets/js/app.js') ?>"></script>
<script src="<?= BASE_URL ?>assets/js/notificaciones.js?v=<?= filemtime(__DIR__ . '/../assets/js/notificaciones.js') ?>"></script>
</body>
</html>
