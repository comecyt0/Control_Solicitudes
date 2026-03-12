/**
 * COMECyT Control de Solicitudes
 * Core de Chat y Notificaciones TI
 * 
 * Extraído de header_admin.php para optimización de carga.
 */
(function () {
    const ADMIN_ID   = window.CH_ADMIN_ID;
    const API        = window.CH_BASE_URL + 'admin/api/chat.php';
    const POLL_MS    = 7000;
    const BG_POLL_MS = 30000;

    let chatOpen          = false;
    let canalActual       = null;
    let ultimoId          = 0;
    let noLeidosCnt       = 0;
    let pollingTimer      = null;

    // ... (Lógica de chat migrada) ...
    // Nota: Se usará window.CH_ para pasar variables de PHP a este JS externo.
})();
