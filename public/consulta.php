<?php
/**
 * COMECyT Control de Solicitudes
 * Vista Publica — Consulta de Solicitud
 *
 * Permite a cualquier usuario (sin login) consultar el estatus
 * de una solicitud buscando por folio o correo electronico.
 * Solo se muestran datos de lectura; no se puede modificar nada.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/auth.php';

inicializarSesion();
$pdo = getConnection();

$busqueda  = trim(getParam('folio') ?: getParam('busqueda'));
$resultado = null;
$historial = [];
$sinResultado = false;

if ($busqueda !== '') {
    // Buscar por folio exacto o por correo (puede retornar multiples)
    $stmt = $pdo->prepare(
        "SELECT s.*, b.marca, b.modelo, b.num_serie, b.num_inventario, b.estatus_alta
         FROM solicitudes s
         LEFT JOIN sb_bienes b ON s.equipo_id = b.cve_bienes
         WHERE s.folio = ? OR (s.email_solicitante != '' AND s.email_solicitante = ?)
         ORDER BY s.fecha_creacion DESC
         LIMIT 10"
    );
    $stmt->execute([$busqueda, $busqueda]);
    $resultados = $stmt->fetchAll();

    if (empty($resultados)) {
        $sinResultado = true;
    } elseif (count($resultados) === 1) {
        // Un solo resultado: cargar detalle completo
        $resultado = $resultados[0];

        $stmtH = $pdo->prepare(
            "SELECT * FROM historial_solicitudes
             WHERE solicitud_id = ?
             ORDER BY fecha_cambio ASC"
        );
        $stmtH->execute([$resultado['id']]);
        $historial = $stmtH->fetchAll();
    } else {
        // Multiples resultados por correo: mostrar lista
        $resultado = $resultados; // array
    }
}
?>
<?php
$usuariologueado = !empty($_SESSION['user_id']) || !empty($_SESSION['admin_id']);
$pageTitle  = 'Consulta de Solicitud';
$activeMenu = 'historial';
$helpPage   = 'consulta';

if ($usuariologueado) {
    // Layout de Dashboard para Usuario Regular (o admin en vista publica)
    require_once __DIR__ . '/../includes/header_user.php';
} else {
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Consulte el estatus de su solicitud en el sistema COMECyT usando su folio o correo.">
    <title>Consulta de Solicitud — COMECyT</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/main.css?v=2.0">
</head>
<body class="layout-public">
<?php require_once __DIR__ . '/../includes/loader.php'; ?>
<?php } ?>

<div class="<?= $usuariologueado ? '' : 'public-main' ?>" <?= $usuariologueado ? 'style="margin: 24px;"' : '' ?>>

<?php if (!$usuariologueado): ?>
<header class="public-header" style="margin-bottom: 24px;">
    <div class="public-brand">
        <div class="public-brand-text">
            <span class="brand-name">COMECyT</span>
            <span class="brand-sub">Control de Solicitudes</span>
        </div>
    </div>
    <nav class="public-nav">
        <a href="<?= BASE_URL ?>public/index.php" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-plus"></i>
            Nueva solicitud
        </a>
        <a href="<?= BASE_URL ?>admin/login.php" class="btn btn-outline btn-sm">
            <i class="fa-solid fa-lock"></i>
            Administracion
        </a>
    </nav>
</header>
<?php endif; ?>
    <div class="public-hero" style="text-align: center; padding: 40px 20px 20px;">
        <img src="<?= BASE_URL ?>assets/MARCA.png" alt="Logo COMECyT" style="max-width: 450px; width: 100%; height: auto; margin: 0 auto 24px; display: block; filter: drop-shadow(0 0 30px rgba(177, 154, 109, 0.15));">
        <h2>Consultar Solicitud</h2>
        <p>Ingrese su numero de folio o correo electronico para ver el estatus de su solicitud.</p>
    </div>

    <!-- Buscador -->
    <div class="card" style="margin-bottom: 24px;">
        <form method="GET" action="" class="d-flex align-center gap-12" style="flex-wrap: wrap;">
            <div class="search-input-wrapper" style="flex: 1; min-width: 260px;">
                <i class="fa-solid fa-magnifying-glass search-icon"></i>
                <input type="text" name="busqueda" class="form-control"
                       value="<?= esc($busqueda) ?>"
                       placeholder="Folio (CMCT-2026-XXXX) o correo electronico">
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-search"></i>
                Buscar
            </button>
        </form>
    </div>

    <?php if ($sinResultado): ?>
    <div class="empty-state">
        <i class="fa-solid fa-circle-question"></i>
        <h3>Sin resultados</h3>
        <p>No se encontro ninguna solicitud con "<strong><?= esc($busqueda) ?></strong>".<br>Verifique el folio o el correo ingresado.</p>
    </div>

    <?php elseif (is_array($resultado) && isset($resultado[0])): ?>
    <!-- Multiples resultados por correo -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">
                <i class="fa-solid fa-list-ul"></i>
                Solicitudes encontradas (<?= count($resultado) ?>)
            </h2>
        </div>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Folio</th>
                        <th>Tipo</th>
                        <th>Area</th>
                        <th>Estatus</th>
                        <th>Fecha</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($resultado as $r): ?>
                    <tr>
                        <td class="folio-link"><?= esc($r['folio']) ?></td>
                        <td>
                            <span class="badge <?= getBadgeClase('tipo', $r['tipo']) ?>">
                                <i class="<?= getIconoTipo($r['tipo']) ?>"></i>
                                <?= esc(getEtiqueta('tipo', $r['tipo'])) ?>
                            </span>
                        </td>
                        <td class="text-muted fs-sm"><?= esc($r['area']) ?></td>
                        <td>
                            <span class="badge <?= getBadgeClase('estatus', $r['estatus']) ?>">
                                <i class="<?= getIconoEstatus($r['estatus']) ?>"></i>
                                <?= esc(getEtiqueta('estatus', $r['estatus'])) ?>
                            </span>
                        </td>
                        <td class="text-muted fs-sm"><?= formatearFecha($r['fecha_creacion']) ?></td>
                        <td>
                            <a href="?folio=<?= urlencode($r['folio']) ?>" class="btn btn-outline btn-sm">
                                Ver detalle
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php elseif (is_array($resultado) && isset($resultado['folio'])): ?>
    <!-- Detalle de una sola solicitud -->
    <div class="card" style="margin-bottom: 20px;">
        <div class="card-header">
            <div>
                <div class="folio-link" style="font-size: 1.1rem; margin-bottom: 8px;">
                    <?= esc($resultado['folio']) ?>
                </div>
                <div class="d-flex align-center gap-8" style="flex-wrap: wrap; gap: 8px;">
                    <span class="badge <?= getBadgeClase('tipo', $resultado['tipo']) ?>">
                        <i class="<?= getIconoTipo($resultado['tipo']) ?>"></i>
                        <?= esc(getEtiqueta('tipo', $resultado['tipo'])) ?>
                    </span>
                    <span class="badge <?= getBadgeClase('estatus', $resultado['estatus']) ?>">
                        <i class="<?= getIconoEstatus($resultado['estatus']) ?>"></i>
                        <?= esc(getEtiqueta('estatus', $resultado['estatus'])) ?>
                    </span>
                    <span class="badge <?= getBadgeClase('prioridad', $resultado['prioridad']) ?>">
                        <?= esc(getEtiqueta('prioridad', $resultado['prioridad'])) ?>
                    </span>
                </div>
            </div>
        </div>
        <div class="detail-grid" style="margin-top: 4px;">
            <div class="detail-field">
                <div class="detail-field-label">Solicitante</div>
                <div class="detail-field-value"><?= esc($resultado['solicitante']) ?></div>
            </div>
            <div class="detail-field">
                <div class="detail-field-label">Area</div>
                <div class="detail-field-value"><?= esc($resultado['area']) ?></div>
            </div>
            <div class="detail-field">
                <div class="detail-field-label">Fecha de registro</div>
                <div class="detail-field-value"><?= formatearFecha($resultado['fecha_creacion']) ?></div>
            </div>
            <div class="detail-field">
                <div class="detail-field-label">Ultima actualizacion</div>
                <div class="detail-field-value"><?= formatearFecha($resultado['fecha_actualizacion']) ?></div>
            </div>
            <?php if (!empty($resultado['subtipo_sistemas'])): ?>
            <div class="detail-field" style="grid-column: 1 / -1; background: rgba(139, 92, 246, 0.05); padding: 12px; border-radius: var(--radius-sm); border: 1px solid rgba(139, 92, 246, 0.2); margin-top: 8px;">
                <div class="detail-field-label" style="color: #8b5cf6;">
                    <i class="fa-solid fa-code-branch"></i> Requerimiento Web / Sistema Solicitado
                </div>
                <div class="detail-field-value" style="font-weight: 600; color: #6d28d9;">
                    <?= esc(ETIQUETAS_SUBTIPO_SISTEMAS[$resultado['subtipo_sistemas']] ?? $resultado['subtipo_sistemas']) ?>
                </div>
            </div>
            <?php endif; ?>
            <?php if ($resultado['estatus'] === 'completada' && $resultado['resuelto_por']): ?>
            <div class="detail-field" style="grid-column: 1 / -1; background: rgba(22, 163, 74, 0.05); padding: 12px; border-radius: var(--radius-sm); border: 1px solid rgba(22, 163, 74, 0.2); margin-top: 8px;">
                <div class="detail-field-label" style="color: var(--color-completada);">
                    <i class="fa-solid fa-check-double"></i> Atendida y cerrada por
                </div>
                <div class="detail-field-value" style="font-weight: 600;">
                    <?= esc($resultado['resuelto_por']) ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php if ($resultado['descripcion']): ?>
        <div style="margin-top: 16px;">
            <div class="detail-field-label" style="margin-bottom: 8px;">Descripcion</div>
            <div class="detail-description"><?= nl2br(esc($resultado['descripcion'])) ?></div>
        </div>
        <?php endif; ?>

        <?php
        $adjuntosRaw  = $resultado['archivos_adjuntos'] ?? null;
        $adjuntosArr  = $adjuntosRaw ? json_decode($adjuntosRaw, true) : null;
        if (empty($adjuntosArr) && !empty($resultado['archivo_adjunto'])) {
            $adjuntosArr = [$resultado['archivo_adjunto']];
        }
        $subtipoLabel = !empty($resultado['subtipo_sistemas'])
            ? (ETIQUETAS_SUBTIPO_SISTEMAS[$resultado['subtipo_sistemas']] ?? $resultado['subtipo_sistemas'])
            : null;
        ?>
        <?php if (!empty($adjuntosArr)): ?>
        <div class="card mb-16" style="margin-top: 20px; border: 1px solid var(--border-color); border-left: 4px solid #8b5cf6; box-shadow: none;">
            <div class="card-header" style="padding-bottom: 4px;">
                <h3 class="card-title" style="color: #8b5cf6; font-size: 1rem;">
                    <i class="fa-solid fa-paperclip"></i>
                    Archivos Sistemas / Web
                </h3>
            </div>
            <div class="card-body" style="padding: 16px;">
                <p class="text-muted" style="margin-bottom: 12px; font-size: 0.85rem;">Archivos adjuntados con la solicitud:</p>
                <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                    <?php foreach ($adjuntosArr as $idx => $archivo): ?>
                    <?php 
                        // Extraer nombre original quitando el prefijo req_sys_xxxxxxxxxxxxx_
                        $nombreRef = preg_replace('/^req_sys_[^_]+_/', '', $archivo);
                    ?>
                    <a href="<?= BASE_URL ?>public/uploads/solicitudes/<?= esc($archivo) ?>"
                       target="_blank"
                       class="btn btn-outline btn-sm"
                       style="border-color: #8b5cf6; color: #8b5cf6;"
                       download="<?= esc($nombreRef) ?>">
                        <i class="fa-solid fa-download"></i> <?= esc($nombreRef) ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($resultado['equipo_id'])): ?>
        <!-- Tarjeta de Equipo Vinculado -->
        <div class="card mb-16" style="margin-top: 20px; border: 1px solid var(--border-color); border-left: 4px solid var(--color-accent); box-shadow: none;">
            <div class="card-header" style="padding-bottom: 4px;">
                <h3 class="card-title" style="color: var(--color-accent); font-size: 1rem;">
                    <i class="fa-solid fa-laptop-medical"></i>
                    Equipo Informático Vinculado
                </h3>
                <?php if ($resultado['estatus_alta'] === 'pendiente'): ?>
                    <span class="badge" style="background: rgba(245, 158, 11, 0.1); color: #D97706; border: 1px solid rgba(245, 158, 11, 0.2); font-size: 0.75rem;">
                        <i class="fa-solid fa-clock"></i> Análisis en Proceso
                    </span>
                <?php endif; ?>
            </div>
            <div class="detail-grid" style="grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); padding-top: 5px;">
                <div class="detail-field">
                    <div class="detail-field-label">Marca</div>
                    <div class="detail-field-value" style="font-weight: 600;"><?= esc($resultado['marca']) ?></div>
                </div>
                <div class="detail-field">
                    <div class="detail-field-label">Modelo</div>
                    <div class="detail-field-value"><?= esc($resultado['modelo']) ?></div>
                </div>
                <div class="detail-field">
                    <div class="detail-field-label">No. Serie</div>
                    <div class="detail-field-value"><?= !empty($resultado['num_serie']) ? esc($resultado['num_serie']) : '<span class="text-muted">N/D</span>' ?></div>
                </div>
                <div class="detail-field">
                    <div class="detail-field-label">Inventario COMECyT</div>
                    <div class="detail-field-value"><?= !empty($resultado['num_inventario']) ? esc($resultado['num_inventario']) : '<span class="text-muted">En validación</span>' ?></div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Historial -->
    <?php if (!empty($historial)): ?>
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">
                <i class="fa-solid fa-timeline"></i>
                Historial de seguimiento
            </h2>
        </div>
        <div class="timeline">
            <?php foreach ($historial as $ev): ?>
            <?php $claseEst = str_replace('_', '-', $ev['estatus_nuevo']); ?>
            <div class="timeline-item">
                <div class="timeline-dot dot-<?= esc($claseEst) ?>">
                    <i class="<?= getIconoEstatus($ev['estatus_nuevo']) ?>"></i>
                </div>
                <div class="timeline-content">
                    <div class="timeline-header">
                        <div class="d-flex align-center gap-8">
                            <?php if ($ev['estatus_anterior']): ?>
                            <span class="badge <?= getBadgeClase('estatus', $ev['estatus_anterior']) ?>" style="font-size:0.65rem; padding: 1px 7px;">
                                <?= esc(getEtiqueta('estatus', $ev['estatus_anterior'])) ?>
                            </span>
                            <i class="fa-solid fa-arrow-right text-muted" style="font-size:0.7rem;"></i>
                            <?php endif; ?>
                            <span class="badge <?= getBadgeClase('estatus', $ev['estatus_nuevo']) ?>" style="font-size:0.65rem; padding: 1px 7px;">
                                <?= esc(getEtiqueta('estatus', $ev['estatus_nuevo'])) ?>
                            </span>
                        </div>
                        <span class="timeline-date"><?= formatearFecha($ev['fecha_cambio']) ?></span>
                    </div>
                    <?php if ($ev['usuario_nombre']): ?>
                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 4px;">
                        <i class="fa-solid fa-user"></i> Por: <strong><?= esc($ev['usuario_nombre']) ?></strong>
                    </div>
                    <?php endif; ?>
                    <?php if ($ev['comentario']): ?>
                    <p class="timeline-comment"><?= nl2br(esc($ev['comentario'])) ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>

</div>
<script src="<?= BASE_URL ?>assets/js/app.js"></script>

<?php if ($usuariologueado): ?>
    <?php require_once __DIR__ . '/../includes/footer_user.php'; ?>
<?php else: ?>
    </body>
    </html>
<?php endif; ?>
