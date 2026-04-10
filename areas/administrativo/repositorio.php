<?php
/**
 * COMECyT — Repositorio de Documentos
 * Departamento Administrativo — Vista principal del gestor de archivos
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../config/auth.php';

verificarSesionAdmin();

$pageTitle  = 'Archivo Administrativo';
$activeMenu = 'repositorio';

require_once __DIR__ . '/../../includes/header_admin.php';
?>

<style>
/* ═══════════════════════════════════════════════════════════════
   REPOSITORIO ADMINISTRATIVO — Estilos Premium (Slate Blue)
   ═══════════════════════════════════════════════════════════════ */
:root {
    --adm-primary : #334155;
    --adm-accent  : #94a3b8;
    --adm-bg-soft : rgba(51, 65, 85, 0.05);
    --adm-border  : rgba(51, 65, 85, 0.15);
    --repo-sidebar: 270px;
}

/* Redefinir variables globales para este módulo */
:root {
    --dg-primary: var(--adm-primary);
    --dg-accent: var(--adm-accent);
    --dg-bg-soft: var(--adm-bg-soft);
    --dg-border: var(--adm-border);
}

/* ── Layout principal ──────────────────────────────────────────── */
.repo-wrapper {
    display: grid;
    grid-template-rows: auto 1fr;
    gap: 0;
    background: var(--bg-card, #fff);
    border-radius: 20px;
    border: 1px solid var(--border-color, #e2e8f0);
    overflow: hidden;
    box-shadow: 0 8px 30px rgba(0,0,0,.07);
    min-height: 75vh;
}

/* ── Barra superior ────────────────────────────────────────────── */
.repo-topbar {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px 22px;
    background: linear-gradient(135deg, var(--adm-primary), #1e293b);
    flex-wrap: wrap;
}

.repo-topbar h2 {
    color: var(--adm-accent);
    font-size: 1.15rem;
    font-weight: 700;
    flex: 1;
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0;
}

.repo-topbar-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.repo-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 14px;
    border-radius: 8px;
    border: none;
    font-size: 0.82rem;
    font-weight: 600;
    cursor: pointer;
    transition: all .2s;
    font-family: inherit;
}

.repo-btn-primary {
    background: var(--adm-accent);
    color: #fff;
}
.repo-btn-primary:hover { background: #64748b; }

.repo-btn-outline {
    background: rgba(255,255,255,.12);
    color: #fff;
    border: 1px solid rgba(255,255,255,.25);
}
.repo-btn-outline:hover { background: rgba(255,255,255,.22); }

/* ── Cuerpo (sidebar + contenido) ────────────────────────────── */
.repo-body {
    display: grid;
    grid-template-columns: var(--repo-sidebar) 1fr;
    overflow: hidden;
    min-height: 0;
}

/* ── Sidebar de carpetas ─────────────────────────────────────── */
.repo-sidebar {
    border-right: 1px solid var(--adm-border);
    padding: 18px 0;
    overflow-y: auto;
    background: var(--bg-hover, #f8fafc);
}

.folder-tree-title {
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: .08em;
    color: var(--text-muted, #64748b);
    padding: 0 16px 10px;
    font-weight: 700;
}

.folder-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    cursor: pointer;
    font-size: 0.875rem;
    color: var(--text-primary, #1e293b);
    transition: all .15s;
    border-right: 3px solid transparent;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.folder-item:hover {
    background: var(--adm-bg-soft);
    color: var(--adm-primary);
}

.folder-item.active {
    background: var(--adm-bg-soft);
    color: var(--adm-primary);
    border-right-color: var(--adm-primary);
    font-weight: 600;
}

.folder-item i.fa-folder { color: var(--adm-accent); }
.folder-item i.fa-folder-open { color: var(--adm-primary); }

.folder-item .folder-delete {
    margin-left: auto;
    font-size: 0.7rem;
    opacity: 0;
    color: #ef4444;
    flex-shrink: 0;
}

.folder-item:hover .folder-delete { opacity: 1; }
.folder-item .folder-rename {
    margin-left: 6px;
    font-size: 0.7rem;
    opacity: 0;
    color: var(--adm-accent);
    flex-shrink: 0;
}
.folder-item:hover .folder-rename { opacity: 1; }

.folder-toggle {
    width: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 0.7rem;
    color: var(--text-muted);
}
.folder-toggle:hover { color: var(--adm-primary); }

.folder-children { padding-left: 18px; display: block; }
.folder-children.collapsed { display: none; }

/* ── Zona de archivos ────────────────────────────────────────── */
.repo-content {
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

/* Breadcrumb */
.repo-breadcrumb {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 10px 20px;
    font-size: 0.8rem;
    border-bottom: 1px solid var(--adm-border);
    color: var(--text-muted);
    flex-wrap: wrap;
}

.repo-breadcrumb span { cursor: pointer; }
.repo-breadcrumb span:hover { color: var(--adm-primary); text-decoration: underline; }
.repo-breadcrumb .sep { opacity: .4; }
.repo-breadcrumb .current { color: var(--adm-primary); font-weight: 600; }

/* Drop zone */
.drop-zone {
    flex: 1;
    padding: 20px;
    overflow-y: auto;
    position: relative;
    transition: background .2s;
}

.drop-zone.dragover { background: rgba(51, 65, 85, 0.04); }

.drop-overlay {
    display: none;
    position: absolute;
    inset: 0;
    background: rgba(51, 65, 85, .1);
    border: 3px dashed var(--adm-accent);
    border-radius: 10px;
    z-index: 10;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    gap: 14px;
    font-size: 1.4rem;
    color: var(--adm-primary);
    font-weight: 700;
}

.drop-zone.dragover .drop-overlay { display: flex; }

.repo-filebar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
    flex-wrap: wrap;
    gap: 8px;
}

.file-count { font-size: 0.8rem; color: var(--text-muted, #64748b); }

.file-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 14px;
}

.file-card {
    background: var(--bg-card, #fff);
    border: 1px solid var(--border-color, #e2e8f0);
    border-radius: 14px;
    padding: 16px 12px 12px;
    text-align: center;
    position: relative;
    transition: all .2s;
    cursor: pointer;
}

.file-card:hover {
    border-color: var(--adm-accent);
    box-shadow: 0 8px 20px rgba(51, 65, 85, .1);
    transform: translateY(-2px);
}

.file-icon { font-size: 3rem; margin-bottom: 10px; display: block; line-height: 1; }

.file-name {
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--text-primary, #1e293b);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-bottom: 4px;
}

.file-meta { font-size: 0.68rem; color: var(--text-muted, #94a3b8); }

/* Marcadores */
.file-marcador {
    position: absolute;
    top: 10px;
    right: 10px;
    padding: 2px 8px;
    border-radius: 20px;
    font-size: 0.6rem;
    font-weight: 800;
    text-transform: uppercase;
    color: #fff;
    box-shadow: 0 2px 5px rgba(0,0,0,.1);
}
.m-ninguno { display: none; }
.m-pendiente { background: #f59e0b; }
.m-completado { background: #10b981; }
.m-cancelado { background: #ef4444; }
.m-revision { background: #3b82f6; }
.m-urgente { background: #991b1b; }
.m-borrador { background: #64748b; }

.btn-marcador:hover { border-color: #f59e0b; color: #f59e0b; background: rgba(245,158,11,0.05); }

.marcador-opciones { display: flex; flex-direction: column; gap: 8px; margin-top: 10px; }
.marcador-opt {
    display: flex; align-items: center; gap: 12px; padding: 10px 16px;
    border-radius: 10px; border: 1.5px solid var(--border-color);
    cursor: pointer; transition: all .2s; font-size: 0.88rem; font-weight: 600;
}
.marcador-opt:hover { border-color: var(--adm-accent); background: var(--adm-bg-soft); }
.marcador-opt.active { border-color: var(--adm-primary); background: var(--adm-bg-soft); box-shadow: 0 4px 12px rgba(51, 65, 85, 0.1); }
.marcador-opt i { font-size: 0.9rem; }

.file-actions { display: flex; justify-content: center; gap: 6px; margin-top: 10px; }

.file-action-btn {
    width: 28px;
    height: 28px;
    border-radius: 6px;
    border: 1px solid var(--border-color, #e2e8f0);
    background: transparent;
    cursor: pointer;
    font-size: 0.75rem;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all .15s;
    color: var(--text-muted);
}

.file-action-btn:hover { border-color: var(--adm-primary); color: var(--adm-primary); background: var(--adm-bg-soft); }
.file-action-btn.danger:hover { border-color: #ef4444; color: #ef4444; background: #fef2f2; }

.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 60px 20px;
    gap: 14px;
    color: var(--text-muted);
    text-align: center;
}
.empty-state i { font-size: 3.5rem; opacity: .3; }

.upload-progress {
    display: none;
    align-items: center;
    gap: 8px;
    font-size: 0.82rem;
    color: var(--adm-primary);
    font-weight: 600;
}

/* Modales */
.repo-modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.6);
    z-index: 9000;
    align-items: center;
    justify-content: center;
    padding: 20px;
}
.repo-modal-overlay.open { display: flex; }

.repo-modal {
    background: var(--bg-card, #fff);
    border-radius: 20px;
    max-width: 860px;
    width: 100%;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    box-shadow: 0 25px 60px rgba(0,0,0,.25);
    animation: modalIn .2s ease;
}

@keyframes modalIn {
    from { transform: scale(.94); opacity: 0; }
    to   { transform: scale(1);   opacity: 1; }
}

.modal-header {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 18px 24px;
    border-bottom: 1px solid var(--border-color);
    background: linear-gradient(135deg, var(--adm-primary), #1e293b);
    color: #fff;
}
.modal-header h3 { flex: 1; margin: 0; font-size: 1rem; color: var(--adm-accent); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.modal-close {
    background: rgba(255,255,255,.15);
    border: none;
    color: #fff;
    width: 32px;
    height: 32px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background .15s;
}
.modal-close:hover { background: rgba(255,255,255,.3); }

.modal-preview {
    flex: 1;
    overflow: auto;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
    min-height: 300px;
    background: var(--bg-hover, #f8fafc);
}

.modal-preview img {
    max-width: 100%;
    max-height: 500px;
    object-fit: contain;
    border-radius: 10px;
    box-shadow: 0 4px 20px rgba(0,0,0,.1);
}

.modal-preview iframe {
    width: 100%;
    height: 500px;
    border: none;
    border-radius: 10px;
}

.modal-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 24px;
    border-top: 1px solid var(--border-color);
    gap: 10px;
    flex-wrap: wrap;
}
.modal-meta { font-size: 0.78rem; color: var(--text-muted); }

.mini-modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.45);
    z-index: 9100;
    align-items: center;
    justify-content: center;
}
.mini-modal-overlay.open { display: flex; }

.mini-modal {
    background: var(--bg-card, #fff);
    border-radius: 16px;
    padding: 28px;
    width: 380px;
    max-width: 95vw;
    box-shadow: 0 20px 50px rgba(0,0,0,.2);
    animation: modalIn .2s ease;
}

.mini-modal h3 { font-size: 1.05rem; font-weight: 700; color: var(--adm-primary); margin-bottom: 16px; }

.mini-modal input {
    width: 100%;
    padding: 10px 14px;
    border-radius: 8px;
    border: 1.5px solid var(--border-color, #e2e8f0);
    font-size: 0.9rem;
    font-family: inherit;
    margin-bottom: 16px;
    transition: border-color .2s;
    box-sizing: border-box;
}
.mini-modal input:focus { outline: none; border-color: var(--adm-accent); }

@media (max-width: 720px) {
    .repo-body { grid-template-columns: 1fr; }
    .repo-sidebar { border-right: none; border-bottom: 1px solid var(--adm-border); }
    .file-grid { grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); }
}
</style>

<!-- ═══════════════════════════════════════════════════════════════
     HERO (Slate Blue Theme)
     ═══════════════════════════════════════════════════════════════ -->
<div style="background:linear-gradient(135deg,#334155,#1e293b);border-radius:20px;padding:36px 48px;margin-bottom:26px;color:#fff;display:flex;justify-content:space-between;align-items:center;box-shadow:0 20px 40px rgba(51,65,85,.2);overflow:hidden;position:relative;">
    <div>
        <h1 style="font-size:2rem;font-weight:800;color:#94a3b8;margin:0 0 8px;">Archivo Administrativo</h1>
        <p style="margin:0;opacity:.85;font-size:1rem;">Gestión centralizada de documentos, reglamentos e información operativa.<br>Organiza por carpetas y mantén el control institucional.</p>
    </div>
    <i class="fa-solid fa-folder-tree" style="font-size:5rem;opacity:.12;color:#94a3b8;flex-shrink:0;"></i>
</div>

<div class="repo-wrapper">
    <div class="repo-topbar">
        <h2><i class="fa-solid fa-folder-open"></i> Archivo Digital</h2>
        <div class="repo-topbar-actions">
            <div class="upload-progress" id="uploadProgress">
                <i class="fa-solid fa-spinner fa-spin"></i> Subiendo...
            </div>
            <button class="repo-btn repo-btn-outline" onclick="abrirModalCarpeta()">
                <i class="fa-solid fa-folder-plus"></i> Nueva Carpeta
            </button>
            <label class="repo-btn repo-btn-primary" style="cursor:pointer;">
                <i class="fa-solid fa-upload"></i> Subir Archivos
                <input type="file" id="fileInput" multiple style="display:none;" onchange="subirArchivos(this.files)">
            </label>
        </div>
    </div>

    <div class="repo-body">
        <div class="repo-sidebar" id="folderTree">
            <div class="folder-tree-title"><i class="fa-solid fa-sitemap" style="margin-right:4px;"></i> Carpetas</div>
            <div id="folderList">
                <div style="padding:20px;text-align:center;color:var(--text-muted);font-size:.82rem;">
                    <i class="fa-solid fa-spinner fa-spin"></i> Cargando...
                </div>
            </div>
        </div>

        <div class="repo-content">
            <nav class="repo-breadcrumb" id="breadcrumb">
                <i class="fa-solid fa-home" style="color:var(--adm-accent);"></i>
                <span class="sep">/</span>
                <span class="current" id="currentCarpetaNombre">General</span>
            </nav>

            <div class="drop-zone" id="dropZone">
                <div class="drop-overlay">
                    <i class="fa-solid fa-cloud-upload-alt" style="font-size:3rem;color:var(--adm-accent);"></i>
                    <span>Soltar para subir</span>
                </div>
                <div class="repo-filebar">
                    <span class="file-count" id="fileCount">—</span>
                </div>
                <div class="file-grid" id="fileGrid"></div>
            </div>
        </div>
    </div>
</div>

<!-- Modales omitted for brevity in thought, but included in full in actual file -->
<!-- (Using same HTML structure as DG but with ADM classes/colors) -->

<div class="mini-modal-overlay" id="marcadorModal" onclick="if(event.target===this) cerrarModalMarcador()">
    <div class="mini-modal" style="width:340px;">
        <h3><i class="fa-solid fa-tag" style="color:var(--adm-accent);margin-right:8px;"></i>Marcador</h3>
        <p style="font-size:0.8rem;color:var(--text-muted);margin-bottom:16px;">Selecciona una etiqueta organizacional.</p>
        <div class="marcador-opciones" id="marcadorOpcionesList"></div>
        <div class="mini-modal-actions" style="margin-top:24px;">
            <button class="repo-btn" style="background:var(--bg-hover);color:var(--text-muted);" onclick="cerrarModalMarcador()">Cancelar</button>
        </div>
    </div>
</div>

<div class="repo-modal-overlay" id="previewModal" onclick="if(event.target===this) cerrarPreview()">
    <div class="repo-modal">
        <div class="modal-header">
            <i class="fa-solid fa-eye" style="color:var(--adm-accent);"></i>
            <h3 id="previewModalTitle">Vista Previa</h3>
            <button class="modal-close" onclick="cerrarPreview()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-preview" id="previewContent"></div>
        <div class="modal-footer">
            <span class="modal-meta" id="previewMeta"></span>
            <a id="previewDownloadBtn" href="#" download class="repo-btn repo-btn-primary" style="text-decoration:none;">
                <i class="fa-solid fa-download"></i> Descargar
            </a>
        </div>
    </div>
</div>

<div class="mini-modal-overlay" id="carpetaModal" onclick="if(event.target===this) cerrarModalCarpeta()">
    <div class="mini-modal">
        <h3 id="carpetaModalTitle"><i class="fa-solid fa-folder-plus" style="color:var(--adm-accent);margin-right:8px;"></i>Nueva Carpeta</h3>
        <input type="text" id="carpetaNombreInput" placeholder="Nombre de la carpeta" maxlength="120" onkeydown="if(event.key==='Enter') guardarCarpeta()">
        <div class="mini-modal-actions">
            <button class="repo-btn" style="background:var(--bg-hover);color:var(--text-muted);" onclick="cerrarModalCarpeta()">Cancelar</button>
            <button class="repo-btn repo-btn-primary" onclick="guardarCarpeta()"><i class="fa-solid fa-check"></i> Guardar</button>
        </div>
    </div>
</div>

<div class="mini-modal-overlay" id="renombrarModal" onclick="if(event.target===this) cerrarRenombrar()">
    <div class="mini-modal">
        <h3><i class="fa-solid fa-pencil" style="color:var(--adm-accent);margin-right:8px;"></i>Renombrar</h3>
        <input type="text" id="renombrarInput" placeholder="Nuevo nombre" maxlength="255" onkeydown="if(event.key==='Enter') guardarRenombre()">
        <div class="mini-modal-actions">
            <button class="repo-btn" style="background:var(--bg-hover);color:var(--text-muted);" onclick="cerrarRenombrar()">Cancelar</button>
            <button class="repo-btn repo-btn-primary" onclick="guardarRenombre()"><i class="fa-solid fa-check"></i> Renombrar</button>
        </div>
    </div>
</div>

<script>
const API_REPO = '<?= BASE_URL ?>areas/administrativo/api/repositorio.php';
const UPLOAD_BASE = '<?= BASE_URL ?>public/uploads/';
const CSRF = '<?= $_SESSION['csrf_token'] ?? '' ?>';

let carpetaActual = 1;
let carpetaActualNombre = 'General';
let carpetasBreadcrumb = [{id: 1, nombre: 'General'}];
let renombrarTipo = '';
let renombrarId   = 0;
let openFolders   = new Set([1]); // IDs de carpetas expandidas

function iconoPorMime(mime) {
    const m = mime || '';
    if (m.startsWith('image/')) return {icon: 'fa-file-image', color: '#22c55e'};
    if (m === 'application/pdf') return {icon: 'fa-file-pdf', color: '#ef4444'};
    if (m.includes('word')) return {icon: 'fa-file-word', color: '#2563eb'};
    if (m.includes('excel') || m.includes('spreadsheet')) return {icon: 'fa-file-excel', color: '#16a34a'};
    if (m.includes('powerpoint') || m.includes('presentation')) return {icon: 'fa-file-powerpoint', color: '#ea580c'};
    if (m.includes('zip')) return {icon: 'fa-file-zipper', color: '#a855f7'};
    return {icon: 'fa-file', color: '#94a3b8'};
}

function formatBytes(b) {
    if (!b) return '—';
    if (b > 1048576) return (b/1048576).toFixed(1) + ' MB';
    if (b > 1024) return (b/1024).toFixed(0) + ' KB';
    return b + ' B';
}

function escHtml(s) {
    const d = document.createElement('div');
    d.textContent = s; return d.innerHTML;
}

async function cargarCarpetas() {
    const res = await fetch(API_REPO + '?accion=listar_carpetas');
    const data = await res.json();
    if (data.ok) {
        window.__carpetasCache = data.carpetas;
        renderArbol(data.carpetas);
    }
}

function renderArbol(carpetas) {
    const buildTree = (parentId) => carpetas
        .filter(c => (c.padre_id == parentId) || (parentId === null && c.padre_id === null))
        .map(c => {
            const hasChildren = carpetas.some(child => Number(child.padre_id) == Number(c.id));
            const children = buildTree(c.id);
            const isActive = Number(c.id) == Number(carpetaActual);
            const isOpen = openFolders.has(Number(c.id));
            
            return `
            <div class="folder-node">
                <div class="folder-item ${isActive ? 'active' : ''}"
                     onclick="navegarCarpeta(${c.id}, '${escHtml(c.nombre).replace(/'/g,"\\\'")}')">
                    <div class="folder-toggle" onclick="event.stopPropagation(); toggleFolder(${c.id})">
                        ${hasChildren ? `<i class="fa-solid ${isOpen ? 'fa-chevron-down' : 'fa-chevron-right'}"></i>` : ''}
                    </div>
                    <i class="fa-solid ${isActive ? 'fa-folder-open' : 'fa-folder'}"></i>
                    <span style="flex:1;overflow:hidden;text-overflow:ellipsis;">${escHtml(c.nombre)}</span>
                    ${c.id > 1 ? `
                        <button class="folder-rename" onclick="event.stopPropagation(); abrirRenombrar('carpeta', ${c.id}, '${escHtml(c.nombre).replace(/'/g,"\\\'")}')"><i class="fa-solid fa-pencil"></i></button>
                        <button class="folder-delete" onclick="event.stopPropagation(); eliminarCarpeta(${c.id},'${escHtml(c.nombre).replace(/'/g,"\\\'")}')"><i class="fa-solid fa-trash-can"></i></button>
                    ` : ''}
                </div>
                ${children ? `<div class="folder-children ${isOpen ? '' : 'collapsed'}">${children}</div>` : ''}
            </div>`;
        }).join('');
    document.getElementById('folderList').innerHTML = buildTree(null) || '<p style="padding:16px;font-size:.8rem;color:var(--text-muted);">Sin carpetas</p>';
}

function toggleFolder(id) {
    id = Number(id);
    if (openFolders.has(id)) openFolders.delete(id);
    else openFolders.add(id);
    renderArbol(window.__carpetasCache || []);
}

function navegarCarpeta(id, nombre, agregar = true) {
    carpetaActual = id;
    carpetaActualNombre = nombre;
    if (agregar) {
        const idx = carpetasBreadcrumb.findIndex(c => c.id == id);
        if (idx >= 0) carpetasBreadcrumb = carpetasBreadcrumb.slice(0, idx + 1);
        else carpetasBreadcrumb.push({id, nombre});
    }
    renderArbol(window.__carpetasCache || []);
    renderBreadcrumb();
    cargarArchivos();
}

function renderBreadcrumb() {
    const html = carpetasBreadcrumb.map((c, i) => {
        const isLast = i === carpetasBreadcrumb.length - 1;
        return isLast ? `<span class="current">${escHtml(c.nombre)}</span>` : `<span onclick="navegarCarpeta(${c.id},'${escHtml(c.nombre)}', false)">${escHtml(c.nombre)}</span><span class="sep">/</span>`;
    }).join('');
    document.getElementById('breadcrumb').innerHTML = `<i class="fa-solid fa-home" style="color:var(--adm-accent);"></i><span class="sep">/</span>${html}`;
}

async function cargarArchivos() {
    const grid = document.getElementById('fileGrid');
    grid.innerHTML = '<div class="empty-state"><i class="fa-solid fa-spinner fa-spin"></i><span>Cargando...</span></div>';
    const res = await fetch(API_REPO + `?accion=listar_archivos&carpeta_id=${carpetaActual}`);
    const data = await res.json();
    if (!data.ok) return;
    const archivos = data.archivos;
    document.getElementById('fileCount').textContent = archivos.length + ' archivo(s)';
    if (!archivos.length) {
        grid.innerHTML = `<div class="empty-state" style="width:100%;"><i class="fa-solid fa-cloud-arrow-up"></i><strong>Carpeta vacía</strong></div>`;
        return;
    }
    grid.innerHTML = archivos.map(f => {
        const {icon, color} = iconoPorMime(f.tipo_mime);
        const urlArchivo = UPLOAD_BASE + f.archivo_path;
        const mClase = 'm-' + (f.marcador || 'ninguno').toLowerCase().replace(' ', '-').replace('ó','o');
        return `
        <div class="file-card">
            <div class="file-marcador ${mClase}">${escHtml(f.marcador)}</div>
            <span class="file-icon" onclick="abrirPreview('${escHtml(f.nombre_original).replace(/'/g,"\\\'")}', '${urlArchivo}', '${f.tipo_mime}', '${formatBytes(f.tamano_bytes)}', '${f.fecha}')">
                <i class="fa-solid ${icon}" style="color:${color};"></i>
            </span>
            <div class="file-name">${escHtml(f.nombre_original)}</div>
            <div class="file-meta">${formatBytes(f.tamano_bytes)}</div>
            <div class="file-actions">
                <button class="file-action-btn" onclick="event.stopPropagation(); abrirModalMarcador(${f.id}, '${f.marcador || 'Ninguno'}')"><i class="fa-solid fa-tag"></i></button>
                <button class="file-action-btn" onclick="abrirPreview('${escHtml(f.nombre_original).replace(/'/g,"\\\'")}', '${urlArchivo}', '${f.tipo_mime}', '${formatBytes(f.tamano_bytes)}', '${f.fecha}')"><i class="fa-solid fa-eye"></i></button>
                <button class="file-action-btn" onclick="event.stopPropagation(); abrirRenombrar('archivo', ${f.id}, '${escHtml(f.nombre_original).replace(/'/g,"\\\'")}')"><i class="fa-solid fa-pencil"></i></button>
                <button class="file-action-btn danger" onclick="eliminarArchivo(${f.id}, '${escHtml(f.nombre_original).replace(/'/g,"\\\'")}')"><i class="fa-solid fa-trash-can"></i></button>
            </div>
        </div>`;
    }).join('');
}

async function subirArchivos(files) {
    if (!files || !files.length) return;
    document.getElementById('uploadProgress').style.display = 'flex';
    for (const file of files) {
        const fd = new FormData();
        fd.append('csrf_token', CSRF);
        fd.append('accion', 'subir_archivo');
        fd.append('carpeta_id', carpetaActual);
        fd.append('archivo', file);
        await fetch(API_REPO, {method: 'POST', body: fd});
    }
    document.getElementById('uploadProgress').style.display = 'none';
    cargarArchivos();
}

async function guardarCarpeta() {
    const nombre = document.getElementById('carpetaNombreInput').value.trim();
    if (!nombre) return;
    const fd = new FormData();
    fd.append('csrf_token', CSRF);
    fd.append('accion', 'crear_carpeta');
    fd.append('nombre', nombre);
    fd.append('padre_id', carpetaActual);
    await fetch(API_REPO, {method: 'POST', body: fd});
    cerrarModalCarpeta();
    await cargarCarpetas();
}

async function eliminarArchivo(id, nombre) {
    COMECyTUI.confirm(`¿Eliminar ${nombre}?`, async () => {
        const fd = new FormData();
        fd.append('csrf_token', CSRF);
        fd.append('accion', 'eliminar_archivo');
        fd.append('id', id);
        await fetch(API_REPO, {method: 'POST', body: fd});
        cargarArchivos();
    });
}

async function eliminarCarpeta(id, nombre) {
    COMECyTUI.confirm(`¿Eliminar la carpeta "${nombre}" y todo su contenido?`, async () => {
        const fd = new FormData();
        fd.append('csrf_token', CSRF);
        fd.append('accion', 'eliminar_carpeta');
        fd.append('id', id);
        const res = await fetch(API_REPO, {method: 'POST', body: fd});
        const data = await res.json();
        if (data.ok) {
            if (carpetaActual == id) navegarCarpeta(1, 'General');
            await cargarCarpetas();
        } else {
            COMECyTUI.alert("Error: " + data.error);
        }
    });
}

function abrirRenombrar(tipo, id, nombre) {
    renombrarTipo = tipo;
    renombrarId = id;
    document.getElementById('renombrarInput').value = nombre;
    document.getElementById('renombrarModal').classList.add('open');
    setTimeout(() => document.getElementById('renombrarInput').select(), 100);
}

function cerrarRenombrar() {
    document.getElementById('renombrarModal').classList.remove('open');
}

async function guardarCarpeta() {
    const nombre = document.getElementById('carpetaNombreInput').value.trim();
    if (!nombre) return;
    const fd = new FormData();
    fd.append('csrf_token', CSRF);
    fd.append('accion', 'crear_carpeta');
    fd.append('nombre', nombre);
    fd.append('padre_id', carpetaActual);
    const res = await fetch(API_REPO, {method: 'POST', body: fd});
    const data = await res.json();
    cerrarModalCarpeta();
    if (data.ok) {
        COMECyTUI.toast('Carpeta creada', 'success');
        await cargarCarpetas();
    } else {
        COMECyTUI.alert("Error: " + data.error);
    }
}

async function guardarRenombre() {
    const nuevoNombre = document.getElementById('renombrarInput').value.trim();
    if (!nuevoNombre) return;
    const fd = new FormData();
    fd.append('csrf_token', CSRF);
    fd.append('accion', 'renombrar');
    fd.append('tipo', renombrarTipo);
    fd.append('id', renombrarId);
    fd.append('nombre', nuevoNombre);
    const res = await fetch(API_REPO, {method: 'POST', body: fd});
    const data = await res.json();
    if (data.ok) {
        cerrarRenombrar();
        if (renombrarTipo === 'carpeta') {
            await cargarCarpetas();
            if (carpetaActual == renombrarId) {
                carpetaActualNombre = nuevoNombre;
                const bIdx = carpetasBreadcrumb.findIndex(b => b.id == renombrarId);
                if (bIdx >= 0) carpetasBreadcrumb[bIdx].nombre = nuevoNombre;
                renderBreadcrumb();
            }
        } else {
            cargarArchivos();
        }
    } else {
        COMECyTUI.alert("Error: " + data.error);
    }
}

function abrirModalMarcador(id, actual) {
    marcadorArchivoId = id;
    const OPCIONES = [
        {v: 'Ninguno', n: 'Ninguno', c: '#94a3b8', i: 'fa-ban'},
        {v: 'Pendiente', n: 'Pendiente', c: '#f59e0b', i: 'fa-clock'},
        {v: 'Completado', n: 'Completado', c: '#10b981', i: 'fa-check-circle'},
        {v: 'Urgente', n: 'Urgente', c: '#991b1b', i: 'fa-triangle-exclamation'}
    ];
    document.getElementById('marcadorOpcionesList').innerHTML = OPCIONES.map(o => `
        <div class="marcador-opt ${actual === o.v ? 'active' : ''}" onclick="cambiarMarcador(${id}, '${o.v}')">
            <i class="fa-solid ${o.i}" style="color:${o.c}"></i>
            <span>${o.n}</span>
        </div>
    `).join('');
    document.getElementById('marcadorModal').classList.add('open');
}

async function cambiarMarcador(id, valor) {
    const fd = new FormData();
    fd.append('accion', 'set_marcador');
    fd.append('csrf_token', CSRF);
    fd.append('id', id);
    fd.append('marcador', valor);
    await fetch(API_REPO, {method: 'POST', body: fd});
    cerrarModalMarcador();
    cargarArchivos();
}

function abrirPreview(nombre, url, mime, tamano, fecha) {
    document.getElementById('previewModalTitle').textContent = nombre;
    document.getElementById('previewMeta').textContent = tamano + ' · ' + fecha;
    document.getElementById('previewDownloadBtn').href = url;
    let html = '';
    if (mime.startsWith('image/')) html = `<img src="${url}" style="max-width:100%">`;
    else if (mime === 'application/pdf') html = `<iframe src="${url}" style="width:100%;height:500px"></iframe>`;
    else html = '<p>Sin vista previa</p>';
    document.getElementById('previewContent').innerHTML = html;
    document.getElementById('previewModal').classList.add('open');
}

function cerrarPreview() { document.getElementById('previewModal').classList.remove('open'); }
function abrirModalCarpeta() { document.getElementById('carpetaModal').classList.add('open'); }
function cerrarModalCarpeta() { document.getElementById('carpetaModal').classList.remove('open'); }
function cerrarModalMarcador() { document.getElementById('marcadorModal').classList.remove('open'); }
function cerrarRenombrar() { document.getElementById('renombrarModal').classList.remove('open'); }

(async () => {
    await cargarCarpetas();
    cargarArchivos();
})();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
