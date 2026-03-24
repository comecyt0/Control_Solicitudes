<?php
/**
 * COMECyT — Calendario Editorial del Departamento de Difusión
 * Agenda propia del área + vinculación con el Calendario Público Institucional.
 * Los eventos creados aquí son independientes del calendario global de Sistemas.
 * Si se marcan como "públicos", aparecerán también en el Calendario Institucional.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../config/auth.php';

verificarSesionAdmin();

$pdo         = getConnection();
$mensajeFlash = '';
$tipoFlash    = '';

// ─── Procesar acciones (PRG) ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_accion'])) {
    validarCsrfPost();
    $accion = $_POST['_accion'];

    if ($accion === 'crear_evento') {
        $titulo      = trim(postParam('titulo'));
        $descripcion = trim(postParam('descripcion'));
        $fechaInicio = postParam('fecha_inicio');
        $fechaFin    = postParam('fecha_fin') ?: $fechaInicio;
        $color       = postParam('color', '#e11d48');
        $tipo_editorial = postParam('tipo_editorial', 'publicacion');
        $publico     = isset($_POST['publico']) ? 'TRUE' : 'FALSE';

        if ($titulo && $fechaInicio) {
            // Guardar en tabla propia del área de Difusión
            $stmt = $pdo->prepare(
                "INSERT INTO df_eventos_editoriales
                    (titulo, descripcion, fecha_inicio, fecha_fin, color, tipo, creado_por, publico)
                 VALUES (?, ?, ?, ?, ?, ?, ?, $publico)"
            );
            $stmt->execute([$titulo, $descripcion, $fechaInicio, $fechaFin, $color, $tipo_editorial, $_SESSION['admin_id']]);

            // Si es público, se añade TAMBIÉN a la tabla global de eventos
            if ($publico === 'TRUE') {
                $eventoId = (int) $pdo->query("SELECT lastval()")->fetchColumn();
                $pdo->prepare(
                    "INSERT INTO eventos (titulo, descripcion, fecha_inicio, fecha_fin, color, creado_por, publico)
                     VALUES (?, ?, ?, ?, ?, ?, TRUE)"
                )->execute([$titulo, "(Difusión) " . $descripcion, $fechaInicio, $fechaFin, $color, $_SESSION['admin_id']]);
            }

            header('Location: ' . BASE_URL . 'areas/difusion/calendario_editorial.php?flash=creado');
            exit;
        } else {
            $mensajeFlash = "El título y la fecha de inicio son obligatorios.";
            $tipoFlash    = "error";
        }
    } elseif ($accion === 'eliminar_evento') {
        $id = (int) postParam('evento_id');
        if ($id > 0) {
            $pdo->prepare("DELETE FROM df_eventos_editoriales WHERE id = ?")->execute([$id]);
            header('Location: ' . BASE_URL . 'areas/difusion/calendario_editorial.php?flash=eliminado');
            exit;
        }
    }
}

// ─── Flash Messages ───────────────────────────────────────────────────────────
$flashCode = getParam('flash');
if ($flashCode === 'creado')   { $mensajeFlash = "Evento editorial agendado correctamente."; $tipoFlash = "success"; }
if ($flashCode === 'eliminado'){ $mensajeFlash = "Evento eliminado del calendario.";         $tipoFlash = "success"; }

// ─── Lógica de mes ────────────────────────────────────────────────────────────
$hoy    = new DateTime();
$mes    = max(1, min(12, (int) getParam('mes', $hoy->format('m'))));
$anio   = max(2000, min(2100, (int) getParam('anio', $hoy->format('Y'))));

$dtMes       = DateTime::createFromFormat('Y-m-d', sprintf('%04d-%02d-01', $anio, $mes));
$mesAnterior = (clone $dtMes)->modify('-1 month');
$mesSiguiente= (clone $dtMes)->modify('+1 month');

$diasEnMes        = (int) $dtMes->format('t');
$diaSemanaInicio  = (int) $dtMes->format('N'); // 1=Lunes

// ─── Eventos del área de Difusión (tabla propia) ─────────────────────────────
$stmt = $pdo->prepare(
    "SELECT * FROM df_eventos_editoriales
     WHERE fecha_inicio < ? AND fecha_fin >= ?
     ORDER BY fecha_inicio ASC"
);
$stmt->execute([
    $mesSiguiente->format('Y-m-01 00:00:00'),
    $dtMes->format('Y-m-01 00:00:00')
]);
$eventosRaw = $stmt->fetchAll();

// También eventos globales públicos del mes para referencia
$stmtG = $pdo->prepare(
    "SELECT *, 'global' as origen FROM eventos
     WHERE publico = TRUE AND fecha_inicio < ? AND fecha_fin >= ?
     ORDER BY fecha_inicio ASC"
);
$stmtG->execute([
    $mesSiguiente->format('Y-m-01 00:00:00'),
    $dtMes->format('Y-m-01 00:00:00')
]);
$eventosGlobales = $stmtG->fetchAll();

// Indexar por día
$calendarioEventos = [];
foreach ($eventosRaw as $ev) {
    $dia = (int) (new DateTime($ev['fecha_inicio']))->format('d');
    $calendarioEventos[$dia][] = array_merge($ev, ['origen' => 'difusion']);
}
foreach ($eventosGlobales as $ev) {
    $dia = (int) (new DateTime($ev['fecha_inicio']))->format('d');
    $calendarioEventos[$dia][] = $ev;
}

$mesesNombres = [
    1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',
    7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'
];

$pageTitle  = 'Calendario Editorial';
$activeMenu = 'calendario';

$extraHead = '<style>
:root { --dif: #e11d48; --dif-light: #fff1f2; --dif-dark: #881337; }

.cal-wrapper {
    background: #ffffff;
    border-radius: 18px;
    box-shadow: 0 8px 30px rgba(0,0,0,0.07);
    overflow: hidden;
    border: 1px solid #f1f5f9;
    margin-top: 1.5rem;
}

.cal-header-nav {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 22px 30px;
    background: linear-gradient(135deg, #be123c 0%, #e11d48 100%);
    color: white;
}
.cal-header-nav h3 { margin: 0; font-size: 1.5rem; font-weight: 800; letter-spacing: -0.4px; }

.cal-nav-btn {
    background: rgba(255,255,255,0.15);
    border: 1px solid rgba(255,255,255,0.25);
    color: white;
    border-radius: 8px;
    padding: 7px 16px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    font-size: 0.9rem;
    transition: background 0.2s;
}
.cal-nav-btn:hover { background: rgba(255,255,255,0.3); }

.cal-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    background: #f1f5f9;
    gap: 1px;
}

.cal-day-name {
    padding: 12px 8px;
    text-align: center;
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #64748b;
    background: #f8fafc;
}

.cal-cell {
    min-height: 120px;
    background: #fff;
    padding: 8px;
    display: flex;
    flex-direction: column;
    gap: 4px;
    cursor: pointer;
    transition: background 0.15s;
}
.cal-cell:hover { background: #fafafa; }
.cal-cell.empty { background: #f8fafc; cursor: default; }

.cal-day-num {
    font-size: 0.95rem;
    font-weight: 600;
    color: #475569;
    width: 28px; height: 28px;
    display: flex; align-items: center; justify-content: center;
    border-radius: 50%;
    align-self: flex-end;
}
.cal-cell.today .cal-day-num {
    background: var(--dif);
    color: white;
}

.ev-pill {
    font-size: 0.72rem;
    padding: 4px 8px;
    border-radius: 4px;
    cursor: pointer;
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    transition: transform 0.2s ease;
}
.ev-pill:hover { transform: translateY(-2px); }
.ev-pill.ev-area { background: var(--dif-light); color: var(--dif-dark); border-left: 3px solid var(--dif); }
.ev-pill.ev-global { background: #eff6ff; color: #1d4ed8; border-left: 3px solid #3b82f6; font-style: italic; }

.leyenda {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
    align-items: center;
    padding: 12px 24px;
    background: #f8fafc;
    border-top: 1px solid #f1f5f9;
    font-size: 0.82rem;
    color: #64748b;
}
.leyenda-dot { display: inline-block; width: 12px; height: 12px; border-radius: 3px; margin-right: 5px; }
.leyenda-dif    { background: var(--dif-light); border-left: 3px solid var(--dif); }
.leyenda-global { background: #eff6ff; border-left: 3px solid #3b82f6; }

/* Modal */
.cal-modal-overlay {
    position: fixed; inset: 0;
    background: rgba(0,0,0,0.5);
    backdrop-filter: blur(6px);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    padding: 20px;
}
.cal-modal-overlay.open { display: flex; animation: fadeIn 0.3s ease; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

.cal-modal-box {
    background: white;
    border-radius: 20px;
    width: 100%;
    max-width: 540px;
    box-shadow: 0 30px 60px rgba(0,0,0,0.2);
    overflow: hidden;
    animation: slideUp 0.35s cubic-bezier(0.25, 0.8, 0.25, 1);
}
@keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

.cal-modal-head {
    padding: 22px 28px;
    background: linear-gradient(135deg, #be123c 0%, #e11d48 100%);
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.cal-modal-head h3 { margin: 0; font-size: 1.2rem; font-weight: 700; }
.cal-modal-close {
    background: rgba(255,255,255,0.2); border: none; color: white;
    width: 30px; height: 30px; border-radius: 50%;
    cursor: pointer; font-size: 1rem;
    display: flex; align-items: center; justify-content: center;
    transition: background 0.2s;
}
.cal-modal-close:hover { background: rgba(255,255,255,0.35); }

.cal-modal-body { padding: 26px; display: flex; flex-direction: column; gap: 16px; }

.form-g label { display: block; font-size: 0.88rem; font-weight: 600; color: #374151; margin-bottom: 6px; }
.form-g input, .form-g select, .form-g textarea {
    width: 100%; padding: 10px 13px;
    border: 1.5px solid #e2e8f0; border-radius: 10px;
    font-size: 0.93rem; background: #f9fafb;
    transition: border-color 0.2s, box-shadow 0.2s;
    box-sizing: border-box;
}
.form-g input:focus, .form-g select:focus {
    outline: none;
    border-color: var(--dif); box-shadow: 0 0 0 3px rgba(225,29,72,0.1);
}

.cal-modal-foot {
    padding: 18px 26px; background: #f8fafc;
    display: flex; justify-content: flex-end; gap: 12px;
    border-top: 1px solid #f1f5f9;
}
.btn-cancel-sm {
    padding: 9px 22px; border-radius: 50px;
    border: 1px solid #e2e8f0; background: white;
    color: #64748b; font-weight: 600; cursor: pointer;
    transition: background 0.2s;
}
.btn-cancel-sm:hover { background: #f1f5f9; }
.btn-save-sm {
    padding: 9px 26px; border-radius: 50px;
    background: var(--dif); border: none; color: white;
    font-weight: 700; cursor: pointer;
    transition: all 0.3s ease;
}
.btn-save-sm:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(225,29,72,0.3); }

/* Reveal */
.reveal-up { opacity: 0; transform: translateY(24px); transition: opacity 0.7s ease, transform 0.7s ease; }
.reveal-up.active { opacity: 1; transform: translateY(0); }

@media (max-width: 700px) {
    .cal-grid { grid-template-columns: repeat(7, 1fr); }
    .cal-cell { min-height: 60px; }
    .cal-modal-body { grid-template-columns: 1fr; }
}
</style>';

require_once __DIR__ . '/../../includes/header_admin.php';
?>

<!-- ══ HEADER ══════════════════════════════════════════════════════════════ -->
<div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:16px; margin-bottom:24px;" class="reveal-up">
    <div>
        <h1 style="font-size:1.9rem; font-weight:800; color:#0f172a; margin:0 0 4px;">
            <i class="fa-solid fa-calendar-week" style="color: var(--dif-secondary, #e11d48); margin-right:10px;"></i>Calendario Editorial
        </h1>
        <p style="color:#64748b; margin:0; font-size:1rem;">Agenda del área de Difusión. Los eventos públicos también aparecen en el Calendario Institucional.</p>
    </div>
    <button class="btn-repo-add" onclick="document.getElementById('modalNuevo').classList.add('open')"
        style="background:linear-gradient(135deg,#be123c,#e11d48); color:white; border:none; border-radius:50px; padding:12px 26px; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; gap:8px; box-shadow:0 8px 20px rgba(225,29,72,0.3); transition: transform 0.2s, box-shadow 0.2s; text-decoration:none;">
        <i class="fa-solid fa-plus"></i> Nuevo Evento
    </button>
</div>

<?php if ($mensajeFlash): ?>
<div class="alert alert-<?= $tipoFlash ?>" style="margin-bottom:16px;">
    <?= esc($mensajeFlash) ?>
</div>
<?php endif; ?>

<!-- ══ CALENDARIO ══════════════════════════════════════════════════════════ -->
<div class="cal-wrapper reveal-up" style="transition-delay:0.1s;">
    <!-- Navegación -->
    <div class="cal-header-nav">
        <div style="display:flex; gap:8px; align-items:center;">
            <a class="cal-nav-btn" href="?mes=<?= $mesAnterior->format('m') ?>&anio=<?= $mesAnterior->format('Y') ?>">
                <i class="fa-solid fa-chevron-left"></i>
            </a>
            <a class="cal-nav-btn" href="?mes=<?= $mesSiguiente->format('m') ?>&anio=<?= $mesSiguiente->format('Y') ?>">
                <i class="fa-solid fa-chevron-right"></i>
            </a>
        </div>
        <h3><?= $mesesNombres[$mes] ?> <?= $anio ?></h3>
        <a class="cal-nav-btn" href="?mes=<?= $hoy->format('m') ?>&anio=<?= $hoy->format('Y') ?>">
            <i class="fa-regular fa-calendar-check" style="margin-right:5px;"></i> Hoy
        </a>
    </div>

    <!-- Grid -->
    <div class="cal-grid">
        <?php foreach (['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'] as $d): ?>
            <div class="cal-day-name"><?= $d ?></div>
        <?php endforeach; ?>

        <?php
        $diaHoy = (int) $hoy->format('d');
        $mesHoy  = (int) $hoy->format('m');
        $anioHoy = (int) $hoy->format('Y');

        // Celdas vacías antes del día 1
        for ($i = 1; $i < $diaSemanaInicio; $i++): ?>
            <div class="cal-cell empty"></div>
        <?php endfor;

        // Días del mes
        for ($dia = 1; $dia <= $diasEnMes; $dia++):
            $esHoy = ($dia === $diaHoy && $mes === $mesHoy && $anio === $anioHoy);
        ?>
        <div class="cal-cell <?= $esHoy ? 'today' : '' ?>">
            <div class="cal-day-num"><?= $dia ?></div>
            <?php foreach ($calendarioEventos[$dia] ?? [] as $ev): ?>
                <div class="ev-pill <?= $ev['origen'] === 'global' ? 'ev-global' : 'ev-area' ?>"
                     title="<?= esc($ev['titulo']) ?>">
                    <?= esc(mb_strimwidth($ev['titulo'], 0, 28, '…')) ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endfor; ?>
    </div>

    <!-- Leyenda -->
    <div class="leyenda">
        <span><span class="leyenda-dot leyenda-dif"></span> Evento Editorial (Difusión)</span>
        <span><span class="leyenda-dot leyenda-global"></span> Evento Global Público</span>
        <a href="<?= BASE_URL ?>public/calendario.php" target="_blank" style="margin-left:auto; color: #e11d48; font-size:0.82rem; font-weight:600; text-decoration:none;">
            <i class="fa-solid fa-arrow-up-right-from-square" style="margin-right:4px;"></i> Ver Calendario Público
        </a>
    </div>
</div>

<!-- ══ LISTA DE PRÓXIMOS EVENTOS ══════════════════════════════════════════ -->
<div style="margin-top:28px;" class="reveal-up" style="transition-delay:0.2s;">
    <?php
    $proximos = $pdo->query(
        "SELECT * FROM df_eventos_editoriales
         WHERE fecha_inicio >= CURRENT_DATE
         ORDER BY fecha_inicio ASC LIMIT 8"
    )->fetchAll();
    ?>
    <h2 style="font-size:1.2rem; font-weight:700; color:#1e293b; margin-bottom:16px;">
        <i class="fa-solid fa-list-ul" style="color:#e11d48; margin-right:8px;"></i> Próximos Eventos del Área
    </h2>
    <?php if (!empty($proximos)): ?>
    <div style="display:grid; gap:12px;">
        <?php foreach ($proximos as $ev): ?>
        <div style="background:#fff; border-radius:14px; border:1px solid #f1f5f9; padding:18px 22px; display:flex; align-items:center; gap:18px; box-shadow:0 2px 8px rgba(0,0,0,0.04); transition: all 0.25s ease;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
            <div style="min-width:50px; height:50px; background:var(--dif-light); border-radius:12px; display:flex; flex-direction:column; align-items:center; justify-content:center; border:1px solid rgba(225,29,72,0.1);">
                <span style="font-size:0.7rem; font-weight:700; color:#be123c; text-transform:uppercase;"><?= date('M', strtotime($ev['fecha_inicio'])) ?></span>
                <span style="font-size:1.3rem; font-weight:800; color:#0f172a; line-height:1;"><?= date('d', strtotime($ev['fecha_inicio'])) ?></span>
            </div>
            <div style="flex:1;">
                <div style="font-weight:700; color:#0f172a;"><?= esc($ev['titulo']) ?></div>
                <div style="font-size:0.85rem; color:#64748b; margin-top:3px;"><?= esc($ev['descripcion'] ?: '–') ?></div>
            </div>
            <div style="display:flex; gap:8px; align-items:center;">
                <span style="font-size:0.78rem; padding:4px 10px; border-radius:20px; background:#f1f5f9; color:#64748b; font-weight:600;">
                    <?= strtoupper(esc($ev['tipo'] ?? 'evento')) ?>
                </span>
                <?php if ($ev['publico']): ?>
                <span title="Visible en Calendario Público" style="font-size:0.78rem; padding:4px 10px; border-radius:20px; background:#dcfce7; color:#15803d; font-weight:600;">
                    <i class="fa-solid fa-globe"></i> Público
                </span>
                <?php endif; ?>
                <form method="POST" style="margin:0;" onsubmit="return confirm('¿Eliminar este evento?')">
                    <?= csrfField() ?>
                    <input type="hidden" name="_accion" value="eliminar_evento">
                    <input type="hidden" name="evento_id" value="<?= $ev['id'] ?>">
                    <button type="submit" style="width:32px; height:32px; border-radius:8px; border:1px solid #fca5a5; background:#fff; color:#dc2626; cursor:pointer; font-size:0.85rem; display:flex; align-items:center; justify-content:center; transition:background 0.2s;" onmouseover="this.style.background='#fee2e2'" onmouseout="this.style.background='#fff'">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div style="text-align:center; padding:50px 20px; color:#94a3b8;">
        <i class="fa-regular fa-calendar-xmark fa-3x" style="opacity:0.4; margin-bottom:16px; display:block;"></i>
        <p>No hay eventos editoriales programados próximamente.</p>
    </div>
    <?php endif; ?>
</div>

<!-- ══ MODAL NUEVO EVENTO ══════════════════════════════════════════════════ -->
<div class="cal-modal-overlay" id="modalNuevo">
    <div class="cal-modal-box">
        <div class="cal-modal-head">
            <h3><i class="fa-solid fa-calendar-plus" style="margin-right:8px;"></i> Agendar Evento Editorial</h3>
            <button class="cal-modal-close" onclick="document.getElementById('modalNuevo').classList.remove('open')">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form method="POST" action="">
            <?= csrfField() ?>
            <input type="hidden" name="_accion" value="crear_evento">
            <div class="cal-modal-body">
                <div class="form-g">
                    <label>Título del evento *</label>
                    <input type="text" name="titulo" placeholder="Ej. Publicación Semana de la Ciencia" required>
                </div>
                <div class="form-g">
                    <label>Descripción</label>
                    <input type="text" name="descripcion" placeholder="Canal, notas o contexto...">
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
                    <div class="form-g">
                        <label>Fecha de inicio *</label>
                        <input type="date" name="fecha_inicio" required value="<?= $hoy->format('Y-m-d') ?>">
                    </div>
                    <div class="form-g">
                        <label>Fecha de fin</label>
                        <input type="date" name="fecha_fin" value="<?= $hoy->format('Y-m-d') ?>">
                    </div>
                </div>
                <div class="form-g">
                    <label>Tipo Editorial</label>
                    <select name="tipo_editorial">
                        <option value="publicacion">Publicación (Redes / Web)</option>
                        <option value="diseno">Diseño de Material</option>
                        <option value="entrevista">Entrevista / Cobertura</option>
                        <option value="evento">Evento Institucional</option>
                        <option value="reunion">Reunión de Equipo</option>
                    </select>
                </div>
                <div class="form-g" style="display:flex; align-items:center; gap:10px;">
                    <input type="checkbox" name="publico" id="chkPublico" style="width:auto; accent-color:#e11d48;">
                    <label for="chkPublico" style="margin:0; font-weight:500; color:#374151; cursor:pointer;">
                        Publicar en el <strong>Calendario Institucional</strong> (visible para toda la intranet)
                    </label>
                </div>
            </div>
            <div class="cal-modal-foot">
                <button type="button" class="btn-cancel-sm" onclick="document.getElementById('modalNuevo').classList.remove('open')">Cancelar</button>
                <button type="submit" class="btn-save-sm"><i class="fa-solid fa-check"></i> Agendar</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
    const reveals = document.querySelectorAll(".reveal-up");
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add("active");
                observer.unobserve(entry.target);
            }
        });
    }, { root: null, rootMargin: "0px", threshold: 0.08 });
    
    reveals.forEach(el => observer.observe(el));
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
