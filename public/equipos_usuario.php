<?php
/**
 * COMECyT Control de Solicitudes
 * Vista Publica Usuario — Mis Equipos Asignados
 *
 * Muestra el parque informático (bienes) asignados al usuario en sesion.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/auth.php';

verificarSesionUsuario();

$pdo = getConnection();

// El ID del usuario activo como FK para `resguardatario`
$usuarioId = (int)($_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? 0);

// Consultar mis bienes asignados
$stmt = $pdo->prepare(
    "SELECT cve_bienes AS id, cve_estatus, marca, modelo, num_serie, num_inventario, estatus_alta 
     FROM sb_bienes 
     WHERE resguardatario = ? 
     ORDER BY cve_estatus ASC, cve_bienes DESC"
);
$stmt->execute([$usuarioId]);
$misEquipos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle  = 'Mis Equipos Asignados';
$activeMenu = 'equipos';
$helpPage   = 'equipos_usuario';

require_once __DIR__ . '/../includes/header_user.php';
?>

<div style="margin: 24px;">
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">
                <i class="fa-solid fa-laptop-code"></i>
                Hardware e Inventario
            </h2>
        </div>

        <?php if (!empty($misEquipos)): ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Marca</th>
                        <th>Modelo</th>
                        <th>No. Serie</th>
                        <th>Inventario</th>
                        <th>Estatus Físico</th>
                        <th>Estado de Alta</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($misEquipos as $eq): ?>
                    <tr>
                        <td style="font-weight: 500;"><?= esc($eq['marca']) ?></td>
                        <td><?= esc($eq['modelo']) ?></td>
                        <td><span class="badge" style="background:#e2e8f0; color:#475569; font-family:monospace;"><?= esc($eq['num_serie']) ?></span></td>
                        <td><?= $eq['num_inventario'] ? esc($eq['num_inventario']) : '<em class="text-muted">Sin Etiqueta</em>' ?></td>
                        <td>
                            <?php if ((int)$eq['cve_estatus'] === 1): ?>
                                <span class="badge" style="background: rgba(16,185,129,0.15); color: #10B981;"><i class="fa-solid fa-circle-check"></i> Operativo</span>
                            <?php else: ?>
                                <span class="badge" style="background: rgba(245,158,11,0.15); color: #F59E0B;"><i class="fa-solid fa-screwdriver-wrench"></i> Mantenimiento/Baja</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($eq['estatus_alta'] === 'pendiente'): ?>
                                <span class="badge" style="background: rgba(245,158,11,0.15); color: #F59E0B;"><i class="fa-solid fa-clock"></i> Pendiente Autorización</span>
                            <?php else: ?>
                                <span class="badge" style="background: rgba(16,185,129,0.15); color: #10B981;"><i class="fa-solid fa-shield-check"></i> Autorizado Oficial</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="fa-solid fa-boxes-stacked"></i>
            <h3>Sin equipos en resguardo</h3>
            <p>No tienes hardware asignado oficialmente a tu cuenta. Si requieres equipo, levanta una solicitud de soporte o mantenimiento registrando tu bien informático.</p>
            <div style="margin-top: 20px;">
                <a href="<?= BASE_URL ?>public/index.php" class="btn btn-outline">Crear un Reporte</a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer_user.php'; ?>
