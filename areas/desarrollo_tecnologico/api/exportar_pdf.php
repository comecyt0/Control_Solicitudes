<?php
/**
 * COMECyT Control de Solicitudes
 * API: Exportar Solicitud a PDF (via print CSS)
 *
 * GET ?id=N → Página HTML optimizada para impresión / PDF
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../config/auth.php';

verificarSesionAdmin();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    die('ID de solicitud inválido.');
}

$pdo = getConnection();

$stmt = $pdo->prepare(
    "SELECT s.*, b.marca, b.modelo, b.num_serie, b.num_inventario
     FROM solicitudes s
     LEFT JOIN sb_bienes b ON s.equipo_id = b.cve_bienes
     WHERE s.id = ?"
);
$stmt->execute([$id]);
$sol = $stmt->fetch();

if (!$sol) {
    http_response_code(404);
    die('Solicitud no encontrada.');
}

$stmtH = $pdo->prepare(
    "SELECT * FROM historial_solicitudes
     WHERE solicitud_id = ?
     ORDER BY fecha_cambio ASC"
);
$stmtH->execute([$id]);
$historial = $stmtH->fetchAll();

$adminGenera = getNombreAdmin();
$fechaExport = date('d/m/Y H:i');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Solicitud <?= esc($sol['folio']) ?> — COMECyT</title>
<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap');

    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
        font-family: 'Inter', sans-serif;
        font-size: 11pt;
        color: #111827;
        background: #fff;
        padding: 0;
    }

    /* Botón de impresión — solo en pantalla */
    @media screen {
        .print-actions {
            position: fixed;
            top: 20px;
            right: 20px;
            display: flex;
            gap: 10px;
            z-index: 999;
        }
        .btn-print {
            background: linear-gradient(135deg,#662331,#8b2f42);
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-family: inherit;
            font-size: 0.9rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .btn-back {
            background: #fff;
            color: #662331;
            border: 1px solid #662331;
            padding: 10px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-family: inherit;
            font-size: 0.9rem;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .page { max-width: 800px; margin: 20px auto; padding: 32px; border: 1px solid #e5e7eb; border-radius: 12px; }
    }

    @media print {
        .print-actions { display: none !important; }
        .page { padding: 0; border: none; }
        body { font-size: 10pt; }
    }

    /* Header institucional */
    .doc-header {
        background: linear-gradient(135deg, #662331, #8b2f42);
        color: #fff;
        padding: 20px 28px;
        border-radius: 8px;
        margin-bottom: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .doc-header h1 { font-size: 1.1rem; font-weight: 700; }
    .doc-header .folio { font-size: 1.4rem; font-weight: 700; color: #B19A6D; font-family: monospace; }
    .doc-header .sub { font-size: 0.75rem; opacity: 0.8; margin-top: 3px; }

    /* Secciones */
    .section { margin-bottom: 16px; }
    .section-title {
        font-size: 0.78rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #662331;
        border-bottom: 2px solid #B19A6D;
        padding-bottom: 4px;
        margin-bottom: 10px;
    }
    .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    .field { margin-bottom: 8px; }
    .field-label { font-size: 0.7rem; color: #6B7280; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; }
    .field-value { font-size: 0.88rem; color: #111827; margin-top: 2px; }

    /* Descripción */
    .descripcion {
        background: #F9FAFB;
        border: 1px solid #E5E7EB;
        border-radius: 6px;
        padding: 14px;
        font-size: 0.88rem;
        line-height: 1.6;
        color: #374151;
        white-space: pre-wrap;
    }

    /* Timeline */
    .timeline { border-left: 3px solid #E5E7EB; margin-left: 10px; padding-left: 16px; }
    .tl-item { position: relative; margin-bottom: 14px; padding-bottom: 14px; border-bottom: 1px dashed #F3F4F6; }
    .tl-item:last-child { border-bottom: none; margin-bottom: 0; }
    .tl-dot {
        position: absolute;
        left: -23px;
        top: 2px;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: #662331;
    }
    .tl-date { font-size: 0.72rem; color: #9CA3AF; }
    .tl-act { font-size: 0.85rem; font-weight: 600; color: #111827; margin-bottom: 2px; }
    .tl-comment { font-size: 0.8rem; color: #6B7280; font-style: italic; margin-top: 4px; }

    /* Footer */
    .doc-footer {
        margin-top: 24px;
        padding-top: 12px;
        border-top: 1px solid #E5E7EB;
        font-size: 0.72rem;
        color: #9CA3AF;
        display: flex;
        justify-content: space-between;
    }
</style>
</head>
<body>

<div class="print-actions">
    <a href="<?= BASE_URL ?>admin/detalle.php?id=<?= $id ?>" class="btn-back">← Volver</a>
    <button class="btn-print" onclick="window.print()">🖨️ Imprimir / Guardar PDF</button>
</div>

<div class="page">
    <!-- Header -->
    <div class="doc-header">
        <div>
            <div class="sub">COMECyT — Control de Solicitudes Internas</div>
            <h1>Comprobante de Solicitud</h1>
        </div>
        <div style="text-align:right;">
            <div class="folio"><?= esc($sol['folio']) ?></div>
            <div class="sub"><?= esc(getEtiqueta('estatus', $sol['estatus'])) ?></div>
        </div>
    </div>

    <!-- Datos de la solicitud -->
    <div class="section">
        <div class="section-title">Información del Solicitante</div>
        <div class="grid-2">
            <div class="field">
                <div class="field-label">Nombre</div>
                <div class="field-value"><?= esc($sol['solicitante']) ?></div>
            </div>
            <div class="field">
                <div class="field-label">Área</div>
                <div class="field-value"><?= esc($sol['area']) ?></div>
            </div>
            <div class="field">
                <div class="field-label">Correo Electrónico</div>
                <div class="field-value"><?= esc($sol['email_solicitante'] ?: '—') ?></div>
            </div>
            <div class="field">
                <div class="field-label">Fecha de Registro</div>
                <div class="field-value"><?= formatearFecha($sol['fecha_creacion']) ?></div>
            </div>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Detalle de la Solicitud</div>
        <div class="grid-2" style="margin-bottom:10px;">
            <div class="field">
                <div class="field-label">Tipo</div>
                <div class="field-value"><?= esc(getEtiqueta('tipo', $sol['tipo'])) ?></div>
            </div>
            <div class="field">
                <div class="field-label">Prioridad</div>
                <div class="field-value"><?= esc(getEtiqueta('prioridad', $sol['prioridad'])) ?></div>
            </div>
            <div class="field">
                <div class="field-label">Estatus Actual</div>
                <div class="field-value" style="font-weight:600;"><?= esc(getEtiqueta('estatus', $sol['estatus'])) ?></div>
            </div>
            <?php if ($sol['resuelto_por']): ?>
            <div class="field">
                <div class="field-label">Atendido por</div>
                <div class="field-value"><?= esc($sol['resuelto_por']) ?></div>
            </div>
            <?php endif; ?>
        </div>
        <div class="field">
            <div class="field-label">Descripción</div>
            <div class="descripcion"><?= esc($sol['descripcion']) ?></div>
        </div>
    </div>

    <?php if ($historial): ?>
    <div class="section">
        <div class="section-title">Historial de Cambios (<?= count($historial) ?> eventos)</div>
        <div class="timeline">
            <?php foreach ($historial as $evt): ?>
            <div class="tl-item">
                <div class="tl-dot"></div>
                <div class="tl-date"><?= formatearFecha($evt['fecha_cambio']) ?> · <?= esc($evt['usuario_nombre'] ?? '—') ?></div>
                <div class="tl-act">
                    <?= esc(getEtiqueta('estatus', $evt['estatus_anterior'] ?? '')) ?>
                    → <?= esc(getEtiqueta('estatus', $evt['estatus_nuevo'])) ?>
                </div>
                <?php if ($evt['comentario']): ?>
                <div class="tl-comment"><?= esc($evt['comentario']) ?></div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="doc-footer">
        <span>Generado por: <?= esc($adminGenera) ?></span>
        <span><?= esc($fechaExport) ?></span>
        <span>COMECyT © <?= date('Y') ?></span>
    </div>
</div>

</body>
</html>
<?php
