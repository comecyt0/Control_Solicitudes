<div class="modal-backdrop" id="modalVerCumple">
    <div class="modal" style="max-width: 440px;">
        <div class="modal-header" style="background: linear-gradient(135deg, #fef9c3 0%, #fde68a 100%); border-bottom: 1px solid #fcd34d;">
            <h3 class="modal-title" style="color: #92400e;">
                🎂 <span id="mc_nombre">Cumpleaños</span>
            </h3>
            <button type="button" class="modal-close" onclick="cerrarModal('modalVerCumple')">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="modal-body" style="text-align:center; padding: 2rem 1.5rem;">
            <!-- Foto del cumpleañero -->
            <div id="mc_foto_wrap" style="margin-bottom: 1.25rem;">
                <img id="mc_foto" src="" alt="Foto" style="
                    width: 120px; height: 120px;
                    border-radius: 50%;
                    object-fit: cover;
                    border: 4px solid #fcd34d;
                    box-shadow: 0 0 0 6px rgba(251,191,36,0.2), 0 8px 24px rgba(0,0,0,0.12);
                    display: block;
                    margin: 0 auto;
                ">
                <div id="mc_foto_placeholder" style="
                    width: 120px; height: 120px;
                    border-radius: 50%;
                    background: linear-gradient(135deg, #fde68a, #fbbf24);
                    display: none;
                    align-items: center;
                    justify-content: center;
                    margin: 0 auto;
                    border: 4px solid #fcd34d;
                    box-shadow: 0 0 0 6px rgba(251,191,36,0.2), 0 8px 24px rgba(0,0,0,0.12);
                    font-size: 3rem;
                ">🎂</div>
            </div>
            <!-- Nombre y edad -->
            <h2 id="mc_nombre_grande" style="margin: 0 0 0.25rem; font-size: 1.3rem; font-weight: 800; color: #1e293b; line-height: 1.3;"></h2>
            <p id="mc_edad_label" style="margin: 0 0 1rem; color: #78350f; font-size: 1rem; font-weight: 600; display:none;"></p>
            <!-- Fecha -->
            <div style="
                display: inline-flex;
                align-items: center;
                gap: 8px;
                background: #fef3c7;
                border: 1px solid #fde68a;
                border-radius: 999px;
                padding: 6px 18px;
                font-size: 0.9rem;
                color: #92400e;
                font-weight: 600;
                margin-bottom: 1.5rem;
            ">
                <i class="fa-solid fa-calendar-heart"></i>
                <span id="mc_fecha"></span>
            </div>
            <!-- Mensaje celebración -->
            <div style="
                background: linear-gradient(135deg, #fef9c3, #fde68a);
                border-radius: 12px;
                padding: 1rem;
                font-size: 0.9rem;
                color: #78350f;
                line-height: 1.5;
                border: 1px solid rgba(251,191,36,0.4);
            ">
                <i class="fa-solid fa-party-horn" style="color: #d97706;"></i>
                <strong>¡Feliz Cumpleaños!</strong><br>
                Recuerda felicitar a tu compañero/a en este día especial.
            </div>
        </div>
        <div class="modal-footer" style="justify-content: center;">
            <button type="button" class="btn btn-primary" onclick="cerrarModal('modalVerCumple')">
                <i class="fa-solid fa-heart"></i> ¡Muchas Felicidades!
            </button>
        </div>
    </div>
</div>
