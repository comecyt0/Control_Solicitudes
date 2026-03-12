<?php
/**
 * COMECyT Control de Solicitudes
 * Vista Publica Usuario — Historial de Solicitudes
 *
 * Muestra el historial completo de solicitudes generadas
 * por el empleado autenticado.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/auth.php';

// Validar sesion de usuario
verificarSesionUsuario();

$pdo = getConnection();

// El correo personal o institucional se usa como pivote (es como guardamos el registro en public/index.php)
$solicitanteNombre = $_SESSION['user_nombre'] ?? $_SESSION['admin_nombre'] ?? '';

// Extraer solicitudes
$stmt = $pdo->prepare(
    "SELECT id, folio, tipo, prioridad, descripcion, estatus, fecha_creacion, resuelto_por 
     FROM solicitudes 
     WHERE solicitante = ? 
     ORDER BY fecha_creacion DESC"
);
$stmt->execute([$solicitanteNombre]);
$misSolicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Variables Vista y Sidebar
$pageTitle  = 'Historial de Solicitudes';
$activeMenu = 'historial';
$helpPage   = 'historial';

require_once __DIR__ . '/../includes/header_user.php';
?>

<div style="padding: 24px;">
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">
                <i class="fa-solid fa-list-ul"></i>
                Mis Solicitudes Registradas
            </h2>
        </div>

        <?php if (!empty($misSolicitudes)): ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Folio</th>
                        <th>Tipo</th>
                        <th>Prioridad</th>
                        <th>Descripción</th>
                        <th>Estatus Actual</th>
                        <th>Fecha de Registro</th>
                        <th>Atendido Por</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($misSolicitudes as $sol): ?>
                    <tr>
                        <td style="font-weight: 500; font-family: monospace;">
                            <a href="<?= BASE_URL ?>public/consulta.php?folio=<?= urlencode($sol['folio']) ?>" style="color: var(--primary); text-decoration: none;">
                                <?= esc($sol['folio']) ?>
                            </a>
                        </td>
                        <td>
                            <span class="badge <?= getBadgeClase('tipo', $sol['tipo']) ?>">
                                <i class="<?= getIconoTipo($sol['tipo']) ?>"></i>
                                <?= esc(getEtiqueta('tipo', $sol['tipo'])) ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge <?= getBadgeClase('prioridad', $sol['prioridad']) ?>">
                                <?= esc(getEtiqueta('prioridad', $sol['prioridad'])) ?>
                            </span>
                        </td>
                        <td style="max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= esc($sol['descripcion']) ?>">
                            <?= esc($sol['descripcion']) ?>
                        </td>
                        <td>
                            <span class="badge <?= getBadgeClase('estatus', $sol['estatus']) ?>">
                                <i class="<?= getIconoEstatus($sol['estatus']) ?>"></i>
                                <?= esc(getEtiqueta('estatus', $sol['estatus'])) ?>
                            </span>
                        </td>
                        <td class="text-muted fs-sm"><?= formatearFecha($sol['fecha_creacion']) ?></td>
                        <td class="text-muted fs-sm"><?= esc($sol['resuelto_por'] ?? 'Aún no asignado') ?></td>
                        <td>
                            <a href="<?= BASE_URL ?>public/consulta.php?folio=<?= urlencode($sol['folio']) ?>" class="btn btn-outline btn-sm">
                                <i class="fa-solid fa-eye"></i> Ver detalle
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="fa-solid fa-folder-open"></i>
            <h3>Aún no tienes solicitudes registradas</h3>
            <p>Cuando registres asistencias, soportes o mantenimientos, aparecerán listados aquí.</p>
            <div style="margin-top: 20px;">
                <a href="<?= BASE_URL ?>public/index.php" class="btn btn-primary">Crear mi primera solicitud</a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer_user.php'; ?>
