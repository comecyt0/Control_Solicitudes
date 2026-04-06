<?php
/**
 * COMECyT - Agente IA Widget
 * Restaurado y aislado para su integración universal.
 */
?>
<!-- ================================================================
     PANEL ASISTENTE IA
     ================================================================ -->
<div id="iaPanel" style="display:none; position:fixed; right:350px; top:60px; width:340px; height:500px; background:#fff; z-index:9998; border:1px solid #e5e7eb; border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,0.1); flex-direction:column; font-family:Inter,sans-serif;">
    <div id="iaPanelHeader" style="padding:12px 15px; background:linear-gradient(135deg,#9b865f,#B19A6D); border-radius:12px 12px 0 0; display:flex; align-items:center; gap:10px; cursor:grab;">
        <div style="width:32px; height:32px; border-radius:50%; background:rgba(255,255,255,0.2); display:flex; align-items:center; justify-content:center; color:#fff;"><i class="fa-solid fa-robot"></i></div>
        <div style="flex:1; display:flex; flex-direction:column;"><span style="font-size:0.9rem; font-weight:700; color:#fff;">Asistente IA</span></div>
        <button onclick="toggleAsistenteIA()" style="background:rgba(255,255,255,0.15); border:none; color:#fff; width:26px; height:26px; border-radius:50%; cursor:pointer;"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div id="iaMessages" style="flex:1; padding:15px; overflow-y:auto; display:flex; flex-direction:column; gap:10px; background:#f9fafb;"></div>
    <div id="iaTyping" style="display:none; padding:8px 15px; font-size:0.75rem; color:#6b7280; background:#f9fafb; font-style:italic;"><i class="fa-solid fa-circle-notch fa-spin"></i> Escribiendo...</div>
    <div style="padding:10px 15px; border-top:1px solid #e5e7eb; background:#fff; display:flex; gap:8px;">
        <textarea id="iaInput" placeholder="Pregunta algo..." rows="1" style="flex:1; border:1px solid #d1d5db; border-radius:12px; padding:8px 12px; outline:none; font-size:0.85rem; resize:none;" onkeydown="iaKeyDown(event)"></textarea>
        <button onclick="iaEnviar()" style="width:34px; height:34px; border-radius:50%; background:linear-gradient(135deg,#9b865f,#B19A6D); color:#fff; border:none; cursor:pointer; align-self:flex-end;"><i class="fa-solid fa-paper-plane"></i></button>
    </div>
</div>

<script>
// AI Assistant
(function() {
    const API = '<?= BASE_URL ?>admin/api/agente_ia.php';
    window.toggleAsistenteIA = () => {
        const p = document.getElementById('iaPanel');
        p.style.display = (p.style.display === 'none' ? 'flex' : 'none');
    };
    window.iaEnviar = () => {
        const inp = document.getElementById('iaInput');
        const txt = inp.value.trim();
        if (!txt) return;
        const zona = document.getElementById('iaMessages');
        const b = document.createElement('div');
        b.style.cssText = 'align-self:flex-end; background:#662331; color:#fff; padding:8px; border-radius:10px; margin:4px;';
        b.textContent = txt;
        zona.appendChild(b);
        inp.value = '';
        const fd = new FormData(); fd.append('mensaje', txt);

        // Show typing indicator
        const typing = document.getElementById('iaTyping');
        if (typing) typing.style.display = 'block';
        zona.scrollTop = zona.scrollHeight;

        fetch(API, { method: 'POST', body: fd }).then(r => r.json()).then(d => {
            if (typing) typing.style.display = 'none';
            const r = document.createElement('div');
            r.style.cssText = 'align-self:flex-start; background:#f0f0f0; border-radius:10px; margin:4px; max-width:95%; line-height:1.4;';
            r.innerHTML = `<div style="padding:4px 8px; font-size:0.65rem; color:#6b7280; font-weight:700;"><i class="fa-solid fa-robot"></i> Asistente</div><div style="padding:0 8px 8px; font-size:0.85rem;">${d.respuesta || 'Error al conectar con la IA.'}</div>`;
            zona.appendChild(r);
            zona.scrollTop = zona.scrollHeight;
        }).catch(err => {
            if (typing) typing.style.display = 'none';
            const r = document.createElement('div');
            r.style.cssText = 'align-self:flex-start; background:#fecdd3; color:#9f1239; padding:8px; border-radius:10px; margin:4px;';
            r.innerHTML = 'Error de conexión: ' + err.message;
            zona.appendChild(r);
            zona.scrollTop = zona.scrollHeight;
        });
    };
    window.iaKeyDown = (e) => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); iaEnviar(); } };
})();
</script>
