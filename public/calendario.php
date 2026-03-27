<?php
/**
 * COMECyT Control de Solicitudes
 * Vista Pública — Calendario Institucional — v1.1
 * Diseño Unificado (Sticky Notes)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/auth.php';

verificarSesionUsuario();
$pdo = getConnection();

// -------------------------------------------------------
// Logica de Mes y Fechas (Copiada de Admin para consistencia)
// -------------------------------------------------------
$hoy = new DateTime();
$mesStr = getParam('mes', $hoy->format('m'));
$anioStr = getParam('anio', $hoy->format('Y'));

$mes = (int)$mesStr;
$anio = (int)$anioStr;

if ($mes < 1 || $mes > 12) { $mes = (int)$hoy->format('m'); }
if ($anio < 2000 || $anio > 2100) { $anio = (int)$hoy->format('Y'); }

$dtMes = DateTime::createFromFormat('Y-m-d', sprintf('%04d-%02d-01', $anio, $mes));
$mesAnterior = (clone $dtMes)->modify('-1 month');
$mesSiguiente = (clone $dtMes)->modify('+1 month');

$diasEnMes = (int)$dtMes->format('t');
$diaSemanaInicio = (int)$dtMes->format('N'); 

// 1. Consultar SOLO eventos públicos del mes
$inicioMesBusqueda = $dtMes->format('Y-m-01 00:00:00');
$finMesBusqueda    = $mesSiguiente->format('Y-m-01 00:00:00');

$sqlEventos = "
    SELECT id, titulo, descripcion, fecha_inicio, fecha_fin, color, publico, TRUE as es_institucional FROM eventos WHERE publico = TRUE AND fecha_inicio < ? AND fecha_fin > ?
    UNION ALL
    SELECT id, titulo, descripcion, fecha_inicio, fecha_fin, color, publico, FALSE as es_institucional FROM df_eventos_editoriales WHERE publico = TRUE AND fecha_inicio < ? AND fecha_fin > ?
    ORDER BY fecha_inicio ASC
";
$stmt = $pdo->prepare($sqlEventos);
$stmt->execute([$finMesBusqueda, $inicioMesBusqueda, $finMesBusqueda, $inicioMesBusqueda]);
$eventosRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 2. Consultar cumpleaños activos institucionales del mes
$mesAlineado = str_pad($mes, 2, '0', STR_PAD_LEFT);
$sqlCumpleanos = "
    SELECT nombre, fecha_nacimiento, foto_perfil FROM cat_personal WHERE activo = TRUE AND fecha_nacimiento IS NOT NULL AND TO_CHAR(fecha_nacimiento, 'MM') = ?
    UNION
    SELECT nombre, fecha_nacimiento, foto_perfil FROM ss_usuarios WHERE activo = TRUE AND fecha_nacimiento IS NOT NULL AND TO_CHAR(fecha_nacimiento, 'MM') = ?
";
$stmtCump = $pdo->prepare($sqlCumpleanos);
$stmtCump->execute([$mesAlineado, $mesAlineado]);
$cumpleanosRaw = $stmtCump->fetchAll(PDO::FETCH_ASSOC);

// Mapear eventos por dia
$calendarioEventos = [];
foreach ($cumpleanosRaw as $cump) {
    $dia = (int)DateTime::createFromFormat('Y-m-d', $cump['fecha_nacimiento'])->format('d');
    if (!isset($calendarioEventos[$dia])) $calendarioEventos[$dia] = [];
    
    // Calcular edad
    $anioNacimiento = (int)DateTime::createFromFormat('Y-m-d', $cump['fecha_nacimiento'])->format('Y');
    $edadAnios = $anio - $anioNacimiento;
    $nombreLimpio = esc($cump['nombre']);

    $calendarioEventos[$dia][] = [
        'titulo'       => "🎂 " . $nombreLimpio,
        'descripcion'  => 'Cumpleaños institucional (' . $edadAnios . ' años)',
        'fecha_inicio' => $anio . '-' . $mesAlineado . '-' . str_pad($dia, 2, '0', STR_PAD_LEFT) . ' 00:00:00',
        'fecha_fin'    => $anio . '-' . $mesAlineado . '-' . str_pad($dia, 2, '0', STR_PAD_LEFT) . ' 23:59:59',
        'color'        => '#B19A6D', // nota-dorado
        'es_cumple'    => true,
        'foto_perfil'  => $cump['foto_perfil'] ?? null,
        'nombre_cumple'=> $cump['nombre'],
        'edad'         => $edadAnios
    ];
}
foreach ($eventosRaw as $ev) {
    $dIni = new DateTime($ev['fecha_inicio']);
    $dia = (int)$dIni->format('d');
    if (!isset($calendarioEventos[$dia])) $calendarioEventos[$dia] = [];
    $calendarioEventos[$dia][] = $ev;
}

$mesesNombres = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
    7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];

$pageTitle = 'Calendario Institucional';
$activeMenu = 'calendario';

// -------------------------------------------------------
// Estilos locales (Identicos a Admin para consistencia visual)
// -------------------------------------------------------
$extraHead = '
<style>
.calendar-wrapper {
    background: #ffffff;
    border-radius: 16px;
    box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.08), 0 4px 6px -4px rgba(0, 0, 0, 0.04);
    overflow: hidden;
    margin-top: 0.5rem;
    border: 1px solid rgba(0,0,0,0.05);
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
}
.calendar-header-nav {
    display: flex; justify-content: space-between; align-items: center;
    padding: 1.25rem 2rem; background: #fdfdfd; border-bottom: 1px solid rgba(0,0,0,0.06);
}
.calendar-header-nav h3 { margin: 0; font-size: 1.4rem; font-weight: 700; color: var(--color-primary); }
.nav-btn-group { display: flex; gap: 0.5rem; align-items: center; }
.calendar-header-nav .btn-outline {
    border-radius: 8px; padding: 0.4rem 0.8rem; font-weight: 500;
    border: 1px solid #e2e8f0; color: #475569; background: white; transition: all 0.2s ease;
}
.calendar-header-nav .btn-outline:hover { background: #f8fafc; color: var(--color-primary); }
.calendar-grid { display: grid; grid-template-columns: repeat(7, 1fr); background: #f8fafc; gap: 1px; }
.calendar-day-name { padding: 1rem 0.5rem; text-align: right; font-weight: 600; font-size: 0.8rem; color: #64748b; text-transform: uppercase; background: #ffffff; }
.calendar-cell { min-height: 140px; padding: 0.5rem; background: #ffffff; cursor: pointer; transition: background 0.2s ease; display: flex; flex-direction: column; gap: 0.4rem; }
.calendar-cell:hover { background: #fdfdfd; }
.calendar-cell.empty { background: #f8fafc; cursor: default; }
.day-number { font-weight: 500; color: #334155; font-size: 1rem; align-self: flex-end; width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; border-radius: 50%; }
.calendar-cell.today .day-number { color: #fff; background: var(--color-primary); font-weight: 600; }

/* Sticky Notes Styles */
.evento-pildora {
    font-size: 0.75rem; padding: 0.5rem 0.6rem; border-radius: 2px 2px 12px 2px;
    color: #1e293b; margin-bottom: 0.25rem; cursor: pointer; position: relative;
    box-shadow: 2px 2px 4px rgba(0,0,0,0.05), inset -10px -10px 20px rgba(0,0,0,0.03);
    transition: all 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275); display: flex; flex-direction: column; gap: 0.2rem;
}
.evento-pildora:hover { transform: scale(1.02) translateY(-2px); z-index: 10; box-shadow: 4px 6px 12px rgba(0,0,0,0.1); }
.evento-pildora::after {
    content: ""; position: absolute; bottom: 0; right: 0; border-width: 0 0 12px 12px;
    border-style: solid; border-color: rgba(0,0,0,0.06) white; border-radius: 0 0 0 2px;
}
.evento-titulo { font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.evento-hora { font-size: 0.65rem; opacity: 0.75; font-weight: 500; display: flex; align-items: center; gap: 3px; }

.nota-azul { background: #e0f2fe; border-top: 3px solid #0284c7; }
.nota-verde { background: #dcfce7; border-top: 3px solid #16a34a; }
.nota-dorado { background: #fef08a; border-top: 3px solid #ca8a04; }
.nota-rojo { background: #fee2e2; border-top: 3px solid #dc2626; }
.nota-gris { background: #f1f5f9; border-top: 3px solid #64748b; }

/* Mini-avatar para cumpleaños */
.cumple-mini-avatar {
    width: 20px; height: 20px; border-radius: 50%; object-fit: cover;
    border: 1.5px solid #ca8a04; flex-shrink: 0; display: inline-block; vertical-align: middle;
}
.cumple-mini-placeholder {
    background: #fef08a; display: inline-flex; align-items: center; justify-content: center;
    font-size: 0.65rem; color: #ca8a04;
}
.evento-pildora.es-cumple { border-top-color: #ca8a04; }

/* Modal Solicitud */
.modal-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); display: none; align-items: center; justify-content: center; z-index: 9999; }
.modal { background: #fff; width: 100%; max-width: 450px; border-radius: 14px; overflow: hidden; box-shadow: 0 20px 50px rgba(0,0,0,0.2); }
.modal-header { padding: 1.25rem; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; }
.modal-title { margin: 0; font-size: 1.1rem; color: #334155; display: flex; align-items: center; gap: 10px; }
.modal-body { padding: 1.5rem; }
.modal-footer { padding: 1.25rem; border-top: 1px solid #f1f5f9; display: flex; justify-content: flex-end; gap: 10px; }
.form-group { margin-bottom: 1rem; }
.form-label { display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.5rem; color: #475569; }
.form-control { width: 100%; padding: 0.6rem 0.8rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.9rem; }

@media (max-width: 768px) {
    .calendar-grid { display: block; padding: 1rem; }
    .calendar-day-name { display: none; }
    .calendar-cell { min-height: auto; margin-bottom: 1rem; border: 1px solid #e2e8f0; border-radius: 12px; }
    .calendar-cell.empty { display: none; }
}

/* Mailbox Styles */
.mailbox-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}
.btn-mailbox {
    background: #fff; border: 1px solid #e2e8f0; color: #64748b;
    padding: 0.5rem 1rem; border-radius: 10px; cursor: pointer;
    display: flex; align-items: center; gap: 8px; font-weight: 600;
    transition: all 0.2s ease; position: relative;
}
.btn-mailbox:hover { background: #f8fafc; color: var(--color-primary); border-color: var(--color-primary); }
.mailbox-badge {
    position: absolute; top: -5px; right: -5px;
    background: #ef4444; color: #fff; font-size: 0.65rem;
    min-width: 18px; hieght: 18px; padding: 2px 5px;
    border-radius: 20px; display: none; align-items: center; justify-content: center;
    border: 2px solid #fff; font-weight: 700;
}
.mailbox-panel {
    position: absolute; top: calc(100% + 10px); right: 0;
    width: 320px; background: #fff; border-radius: 14px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.15); display: none;
    flex-direction: column; z-index: 9999; border: 1px solid #f1f5f9;
    overflow: hidden; animation: ui-slide-up 0.3s ease;
}
.mailbox-header {
    padding: 12px 16px; background: #f8fafc; border-bottom: 1px solid #f1f5f9;
    font-size: 0.9rem; font-weight: 700; color: #334155;
    display: flex; justify-content: space-between; align-items: center;
}
.mailbox-list { max-height: 350px; overflow-y: auto; padding: 10px; }
.mailbox-item {
    padding: 12px; border-radius: 10px; margin-bottom: 8px;
    background: #fff; border: 1px solid #f1f5f9; font-size: 0.85rem;
    cursor: pointer; transition: all 0.2s;
}
.mailbox-item:hover { background: #f9fafb; border-color: #e2e8f0; }
.mailbox-item--aceptado { border-left: 4px solid #16a34a; }
.mailbox-item--rechazado { border-left: 4px solid #ef4444; }
.mailbox-item-title { font-weight: 700; margin-bottom: 4px; display: flex; align-items: center; gap: 6px; }
.mailbox-empty { padding: 30px 20px; text-align: center; color: #94a3b8; font-size: 0.85rem; }

/* Clases para animación de scroll */
.reveal-up {
    opacity: 0;
    transform: translateY(30px);
    transition: opacity 0.8s cubic-bezier(0.165, 0.84, 0.44, 1), transform 0.8s cubic-bezier(0.165, 0.84, 0.44, 1);
}
.reveal-up.active {
    opacity: 1;
    transform: translateY(0);
}

/* Header Responsivo del Calendario */
.page-header-calendario {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 2rem;
    gap: 1rem;
    padding: 1.5rem;
    background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
    border-radius: 16px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 20px rgba(0,0,0,0.03);
    position: relative;
    z-index: 100;
}

.header-text-zone h2 {
    margin: 0;
    font-size: 1.8rem;
    font-weight: 800;
    color: var(--color-primary);
    display: flex;
    align-items: center;
    gap: 12px;
}

.header-text-zone p {
    margin: 8px 0 0;
    font-size: 1rem;
    color: #475569;
    line-height: 1.5;
}

.header-actions-zone {
    display: flex;
    gap: 12px;
    align-items: center;
    flex-wrap: wrap;
}

@media (max-width: 768px) {
    .page-header-calendario {
        flex-direction: column;
    }
    .header-actions-zone {
        width: 100%;
        justify-content: flex-start;
    }
}
</style>
';

require_once __DIR__ . '/../includes/header_user.php';
?>

<div class="page-header-calendario reveal-up" style="transition-delay: 0.1s;">
    <div class="header-text-zone">
        <h2><i class="fa-solid fa-calendar-days" style="color: var(--color-primary); background: rgba(102, 35, 49, 0.1); padding: 12px; border-radius: 12px;"></i> Agenda Institucional</h2>
        <p>Consulte los eventos oficiales, fechas cívicas y solicite la reserva de espacios o salas de forma rápida.</p>
    </div>
    <div class="header-actions-zone">
        <div class="mailbox-wrapper">
            <button class="btn-mailbox" id="btnMailbox" onclick="toggleMailbox()">
                <i class="fa-solid fa-bell"></i> <span>Buzón</span>
                <span class="mailbox-badge" id="mailboxBadge">0</span>
            </button>
            <div class="mailbox-panel" id="mailboxPanel">
                <div class="mailbox-header">
                    <span>Solicitudes Recientes</span>
                    <i class="fa-solid fa-inbox text-muted"></i>
                </div>
                <div class="mailbox-list" id="mailboxList">
                    <div class="mailbox-empty">Sin notificaciones nuevas</div>
                </div>
            </div>
        </div>
        <button class="btn btn-primary" onclick="abrirModalSolicitud()" style="box-shadow: 0 4px 15px rgba(102, 35, 49, 0.2); border-radius: 8px;">
            <i class="fa-solid fa-plus"></i> Solicitar Espacio
        </button>
    </div>
</div>

<div class="calendar-wrapper reveal-up" style="transition-delay: 0.2s;">
    <div class="calendar-header-nav">
        <h3><?= $mesesNombres[$mes] ?> de <?= $anio ?></h3>
        <div class="nav-btn-group">
            <a href="?mes=<?= $mesAnterior->format('m') ?>&anio=<?= $mesAnterior->format('Y') ?>" class="btn-outline"><i class="fa-solid fa-chevron-left"></i></a>
            <a href="?mes=<?= date('m') ?>&anio=<?= date('Y') ?>" class="btn-outline">Hoy</a>
            <a href="?mes=<?= $mesSiguiente->format('m') ?>&anio=<?= $mesSiguiente->format('Y') ?>" class="btn-outline"><i class="fa-solid fa-chevron-right"></i></a>
        </div>
    </div>

    <div class="calendar-grid">
        <div class="calendar-day-name">Lunes</div>
        <div class="calendar-day-name">Martes</div>
        <div class="calendar-day-name">Miércoles</div>
        <div class="calendar-day-name">Jueves</div>
        <div class="calendar-day-name">Viernes</div>
        <div class="calendar-day-name">Sábado</div>
        <div class="calendar-day-name">Domingo</div>

        <?php for ($i = 1; $i < $diaSemanaInicio; $i++): ?>
            <div class="calendar-cell empty"></div>
        <?php endfor; ?>

        <?php
        $mapaColoresNotas = ['#3788d8' => 'nota-azul', '#16A34A' => 'nota-verde', '#B19A6D' => 'nota-dorado', '#DC2626' => 'nota-rojo', '#6B7280' => 'nota-gris'];
        for ($dia = 1; $dia <= $diasEnMes; $dia++): 
            $esHoy = ($dia === (int)$hoy->format('d') && $mes === (int)$hoy->format('m') && $anio === (int)$hoy->format('Y'));
            $fechaRef = sprintf('%04d-%02d-%02d', $anio, $mes, $dia);
        ?>
            <div class="calendar-cell <?= $esHoy ? 'today' : '' ?>" onclick="abrirModalSolicitudDesdeCelda('<?= $fechaRef ?>')">
                <span class="day-number"><?= $dia ?></span>
                <?php if (isset($calendarioEventos[$dia])): ?>
                    <?php foreach ($calendarioEventos[$dia] as $ev): ?>
                        <?php 
                            $esCumple = !empty($ev['es_cumple']);
                            $fotoUrl  = '';
                            if ($esCumple && !empty($ev['foto_perfil'])) {
                                $fotoUrl = BASE_URL . 'public/uploads/avatares/' . $ev['foto_perfil'];
                            }
                            $colorBase = $ev['color'] ?? '#3788d8';
                            $styleAttr = "background-color: {$colorBase}1a; border-top: 3px solid {$colorBase};";
                        ?>
                        <div class="evento-pildora <?= $esCumple ? ' es-cumple' : '' ?>" 
                             style="<?= $styleAttr ?>"
                             onclick="event.stopPropagation(); <?= $esCumple ? "abrirModalCumple('".esc(addslashes($ev['nombre_cumple']))."', '".esc($fotoUrl)."', '".$ev['edad']."', '".date('d/m', strtotime($ev['fecha_inicio']))."')" : "verDetalleEvento('".esc($ev['titulo'])."', '".esc($ev['descripcion']??'Sin detalles')."', '".date('H:i', strtotime($ev['fecha_inicio']))."', '".date('H:i', strtotime($ev['fecha_fin']))."')" ?>">
                            <div class="evento-titulo" style="display:flex; align-items:center; gap:5px;">
                                <?php if ($esCumple): ?>
                                    <?php if (!empty($fotoUrl)): ?>
                                        <img src="<?= esc($fotoUrl) ?>" alt="" class="cumple-mini-avatar">
                                    <?php else: ?>
                                        <span class="cumple-mini-avatar cumple-mini-placeholder"><i class="fa-solid fa-user"></i></span>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?= esc($ev['titulo']) ?>
                            </div>
                            <div class="evento-hora"><i class="fa-regular fa-clock"></i> <?= date('H:i', strtotime($ev['fecha_inicio'])) ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endfor; ?>
    </div>
</div>

<!-- Modal Solicitud -->
<div class="modal-backdrop" id="modalSolicitud">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fa-solid fa-calendar-plus text-primary"></i> Nueva Solicitud</h3>
            <button type="button" class="btn-outline" onclick="cerrarModal('modalSolicitud')" style="border:none; font-size:1.2rem;">&times;</button>
        </div>
        <form id="formSolicitud">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="accion" value="solicitar">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Evento / Espacio solicitado</label>
                    <input type="text" name="titulo" class="form-control" required placeholder="Ej: Auditorio para capacitación">
                </div>
                <div class="form-group">
                    <label class="form-label">Descripción</label>
                    <textarea name="descripcion" class="form-control" rows="3"></textarea>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                    <div class="form-group">
                        <label class="form-label">Inicio</label>
                        <input type="datetime-local" name="fecha_inicio" id="s_inicio" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Fin</label>
                        <input type="datetime-local" name="fecha_fin" id="s_fin" class="form-control" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Color Sugerido</label>
                    <input type="color" name="color" value="#B19A6D" style="width:100%; height:40px; border-radius:8px; border:1px solid #cbd5e1; cursor:pointer;">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="cerrarModal('modalSolicitud')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Enviar Petición</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Ver Cumpleaños -->
<div class="modal-backdrop" id="modalVerCumple">
    <div class="modal" style="max-width: 400px;">
        <div class="modal-header" style="background: linear-gradient(135deg, #fef9c3 0%, #fde68a 100%); border-bottom: 1px solid #fcd34d;">
            <h3 class="modal-title" style="color: #92400e;">🎂 ¡Feliz Cumpleaños!</h3>
            <button type="button" class="btn-outline" onclick="cerrarModal('modalVerCumple')" style="border:none; font-size:1.2rem; color: #92400e;">&times;</button>
        </div>
        <div class="modal-body" style="text-align:center; padding: 2rem 1.5rem;">
            <div style="margin-bottom: 1.25rem;">
                <img id="mc_foto" src="" alt="Foto" style="width: 110px; height: 110px; border-radius: 50%; object-fit: cover; border: 4px solid #fcd34d; box-shadow: 0 8px 20px rgba(0,0,0,0.1); display: block; margin: 0 auto;">
                <div id="mc_foto_placeholder" style="width: 110px; height: 110px; border-radius: 50%; background: #fde68a; display: none; align-items: center; justify-content: center; margin: 0 auto; border: 4px solid #fcd34d; font-size: 2.5rem;">🎂</div>
            </div>
            <h2 id="mc_nombre" style="margin: 0 0 0.25rem; font-size: 1.25rem; font-weight: 800; color: #1e293b;"></h2>
            <p id="mc_edad" style="margin: 0 0 1rem; color: #78350f; font-weight: 600;"></p>
            <div style="display:inline-block; background:#fef3c7; border:1px solid #fde68a; border-radius:20px; padding:4px 15px; font-size:0.85rem; color:#92400e; font-weight:700;">
                <i class="fa-solid fa-cake-candles"></i> <span id="mc_fecha"></span>
            </div>
        </div>
        <div class="modal-footer" style="justify-content:center; background:#fff;">
            <button type="button" class="btn btn-primary" onclick="cerrarModal('modalVerCumple')">¡Felicidades!</button>
        </div>
    </div>
</div>


<script>
function abrirModalSolicitud() {
    const ahora = new Date();
    ahora.setMinutes(0,0,0);
    const iso = ahora.toLocaleString('sv').replace(' ', 'T').slice(0,16);
    document.getElementById('s_inicio').value = iso;
    document.getElementById('s_fin').value = iso;
    abrirModal('modalSolicitud');
}

function abrirModalSolicitudDesdeCelda(f) {
    document.getElementById('s_inicio').value = f + 'T09:00';
    document.getElementById('s_fin').value = f + 'T10:00';
    abrirModal('modalSolicitud');
}

function verDetalleEvento(t, d, h1, h2) {
    COMECyTUI.info(`${d}\n\nHorario: ${h1} - ${h2}`, t);
}

function abrirModalCumple(nombre, fotoUrl, edad, fecha) {
    document.getElementById('mc_nombre').textContent = nombre;
    document.getElementById('mc_edad').textContent = '¡Cumple ' + edad + ' años!';
    document.getElementById('mc_fecha').textContent = fecha;
    const img = document.getElementById('mc_foto');
    const ph = document.getElementById('mc_foto_placeholder');
    if (fotoUrl) { img.src = fotoUrl; img.style.display = 'block'; ph.style.display = 'none'; }
    else { img.style.display = 'none'; ph.style.display = 'flex'; }
    abrirModal('modalVerCumple');
}

function abrirModal(id) { document.getElementById(id).style.display = 'flex'; }
function cerrarModal(id) { document.getElementById(id).style.display = 'none'; }

document.getElementById('formSolicitud').onsubmit = function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    fetch('<?= BASE_URL ?>public/api/calendario.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.ok) {
                COMECyTUI.info('Solicitud enviada. Recibirá una notificación cuando sea revisada.', 'Petición Recibida');
                cerrarModal('modalSolicitud');
                this.reset();
            } else COMECyTUI.alert('Error: ' + d.error);
        });
};

function toggleMailbox() {
    const p = document.getElementById('mailboxPanel');
    p.style.display = (p.style.display === 'flex') ? 'none' : 'flex';
}

// Cerrar buzón al hacer clic fuera
document.addEventListener('click', (e) => {
    const w = document.querySelector('.mailbox-wrapper');
    const p = document.getElementById('mailboxPanel');
    if (w && !w.contains(e.target)) p.style.display = 'none';
});

// Polling
setInterval(verificarRespuestas, 15000);
function verificarRespuestas() {
    const fd = new FormData();
    fd.append('accion', 'verificar_respuestas');
    fd.append('csrf_token', '<?= $_SESSION['csrf_token'] ?>');
    fetch('<?= BASE_URL ?>public/api/calendario.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.ok) actualizarBuzon(d.respuestas);
        });
}

function actualizarBuzon(respuestas) {
    const badge = document.getElementById('mailboxBadge');
    const list = document.getElementById('mailboxList');
    
    if (respuestas.length > 0) {
        badge.innerText = respuestas.length;
        badge.style.display = 'flex';
        
        list.innerHTML = respuestas.map(r => {
            const esAceptado = r.estatus === 'aceptado';
            const icon = esAceptado ? 'fa-check-circle text-success' : 'fa-circle-xmark text-danger';
            const cls = esAceptado ? 'mailbox-item--aceptado' : 'mailbox-item--rechazado';
            const detalle = esAceptado ? '¡Aprobado! Ya disponible en el calendario.' : `Rechazado. Motivo: ${r.motivo_rechazo || 'No especificado'}`;
            
            return `
                <div class="mailbox-item ${cls}" onclick="marcarLeidoBuzon(${r.id}, '${r.estatus}')">
                    <div class="mailbox-item-title">
                        <i class="fa-solid ${icon}"></i> 
                        ${r.titulo}
                    </div>
                    <div style="color:#64748b; font-size:0.75rem;">${detalle}</div>
                    <div style="margin-top:5px; color:var(--color-primary); font-weight:700; font-size:0.65rem;">Haga clic para archivar</div>
                </div>
            `;
        }).join('');
    } else {
        badge.style.display = 'none';
        list.innerHTML = '<div class="mailbox-empty">Sin notificaciones nuevas</div>';
    }
}

function marcarLeidoBuzon(id, estatus) {
    const fd = new FormData();
    fd.append('accion', 'marcar_leido');
    fd.append('id', id);
    fd.append('csrf_token', '<?= $_SESSION['csrf_token'] ?>');
    fetch('<?= BASE_URL ?>public/api/calendario.php', { method: 'POST', body: fd })
        .then(() => {
            if (estatus === 'aceptado') location.reload();
            else verificarRespuestas(); // Refrescar contador
        });
}

// Carga inicial
verificarRespuestas();

function reproducirSonido(exito) {
    try {
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.connect(gain); gain.connect(audioCtx.destination);
        if (exito) { osc.frequency.setValueAtTime(523, audioCtx.currentTime); osc.frequency.exponentialRampToValueAtTime(1046, audioCtx.currentTime + 0.3); }
        else { osc.frequency.setValueAtTime(220, audioCtx.currentTime); osc.frequency.exponentialRampToValueAtTime(110, audioCtx.currentTime + 0.5); }
        gain.gain.setValueAtTime(0.1, audioCtx.currentTime); gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.5);
        osc.start(); osc.stop(audioCtx.currentTime + 0.5);
    } catch(e) {}
}

// Animaciones al hacer scroll (Reveal Up)
document.addEventListener("DOMContentLoaded", () => {
    const reveals = document.querySelectorAll(".reveal-up");
    const revealOnScroll = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add("active");
                observer.unobserve(entry.target);
            }
        });
    }, { root: null, rootMargin: "0px 0px 0px 0px", threshold: 0.1 });
    reveals.forEach(el => revealOnScroll.observe(el));

    // Auto-abrir modal de solicitud si viene el parametro
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('solicitar') === '1') {
        setTimeout(abrirModalSolicitud, 500);
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
