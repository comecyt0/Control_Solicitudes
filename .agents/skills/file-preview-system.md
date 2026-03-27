# Skill: Sistema de Vista Previa Multi-formato

Esta skill permite integrar visualizadores premium de archivos (imágenes, video, PDF) en cualquier módulo administrativo.

## Componentes Necesarios

### 1. Estructura HTML (Modal)
```html
<div class="modal-preview" id="modalPreview">
    <button class="modal-preview-close" onclick="cerrarPreview()"><i class="fa-solid fa-xmark"></i></button>
    <div class="modal-preview-content" id="previewContent">
        <div class="protection-shield"></div>
    </div>
    <div class="modal-preview-info">
        <h3 id="previewTitle"></h3>
        <p id="previewDesc">Vista previa</p>
    </div>
</div>
```

### 2. Estilos CSS (Premium)
```css
.modal-preview {
    position: fixed; inset:0; background: rgba(15, 23, 42, 0.9); 
    display: none; flex-direction: column; align-items: center; justify-content: center;
    z-index: 12000; padding: 20px; backdrop-filter: blur(8px);
}
.modal-preview.active { display: flex; }
```

### 3. Lógica JavaScript
```javascript
function abrirPreview(url, tipo, titulo) {
    // Detectar extensión y renderizar (img, video o iframe para PDF)
    // ... (Ver implementación en admin/detalle.php)
}
```

## Casos de Uso
- Previsualización de evidencias de staff.
- Visualización de adjuntos de ciudadanos.
- Galería institucional segura.
