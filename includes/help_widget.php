<?php
/**
 * COMECyT Control de Solicitudes
 * Widget de Ayuda Contextual
 *
 * Uso: Definir $helpPage antes de incluir el header correspondiente.
 *   $helpPage = 'dashboard'; // Clave de la pagina actual
 *
 * El widget inyecta un boton flotante "?" y un modal con instrucciones
 * especificas segun la pagina. No tiene dependencias externas.
 */

// -----------------------------------------------------------------------
// Catalogo de contenido de ayuda por pagina
// Estructura: 'clave' => ['titulo', 'icono_fa', 'pasos' => [...], 'tips' => [...]]
// -----------------------------------------------------------------------
$HELP_CATALOG = [

    // ── Panel de Administración ──────────────────────────────────────
    'dashboard' => [
        'titulo'   => 'Panel de Control (Dashboard)',
        'icono'    => 'fa-solid fa-chart-pie',
        'intro'    => 'Esta es la vista principal del sistema. Aquí tienes un resumen en tiempo real del estado de todas las solicitudes.',
        'pasos'    => [
            ['icono' => 'fa-solid fa-square-poll-vertical', 'titulo' => 'Tarjetas de Resumen', 'desc' => 'Las 4 tarjetas superiores muestran el total de solicitudes, las activas, las completadas y las urgentes. Haz clic en cualquier tarjeta para filtrar la lista de solicitudes por ese estado.'],
            ['icono' => 'fa-solid fa-chart-bar',            'titulo' => 'Gráfica por Tipo',    'desc' => 'El panel izquierdo muestra la distribución de solicitudes por categoría: Mantenimiento, Soporte, Atención, etc. Las barras reflejan la proporción de cada tipo.'],
            ['icono' => 'fa-solid fa-chart-pie',            'titulo' => 'Distribución por Estatus', 'desc' => 'El gráfico circular (derecha) muestra cuántas solicitudes están Pendientes, En Proceso, Completadas o Canceladas en este momento.'],
            ['icono' => 'fa-solid fa-clock-rotate-left',    'titulo' => 'Solicitudes Recientes', 'desc' => 'La tabla inferior lista las 8 últimas solicitudes registradas. Haz clic en el folio o en el icono del ojo para ver el detalle completo de cada una.'],
        ],
        'tips'     => ['Los datos se actualizan automáticamente cada vez que recargas la página.', 'Usa el menú lateral para navegar a secciones específicas.'],
    ],

    'solicitudes' => [
        'titulo'   => 'Gestión de Solicitudes',
        'icono'    => 'fa-solid fa-list-ul',
        'intro'    => 'Aquí puedes ver, filtrar, buscar y gestionar todas las solicitudes del sistema.',
        'pasos'    => [
            ['icono' => 'fa-solid fa-magnifying-glass', 'titulo' => 'Buscar Solicitudes',   'desc' => 'Usa la barra de búsqueda para encontrar solicitudes por folio, nombre del solicitante, área o descripción. La búsqueda es en tiempo real al presionar "Filtrar".'],
            ['icono' => 'fa-solid fa-filter',           'titulo' => 'Filtros Avanzados',    'desc' => 'Combina los selectores de Tipo, Estatus y Prioridad para acotar los resultados. Usa el botón "Limpiar" para restablecer todos los filtros.'],
            ['icono' => 'fa-solid fa-eye',              'titulo' => 'Ver Detalle',           'desc' => 'Haz clic en el folio (enlace azul) o en el botón del ojo para abrir la vista de detalle completa de una solicitud, incluyendo su historial de cambios.'],
            ['icono' => 'fa-solid fa-pen-to-square',    'titulo' => 'Cambiar Estatus',       'desc' => 'El botón del lápiz abre un modal donde puedes mover la solicitud al siguiente estado válido (Ej: Pendiente → En Proceso → Completada) y agregar un comentario.'],
            ['icono' => 'fa-solid fa-file-csv',         'titulo' => 'Exportar a CSV',        'desc' => 'El botón "Exportar CSV" descarga todos los registros visibles según los filtros activos en formato de hoja de cálculo para reportes externos.'],
            ['icono' => 'fa-solid fa-trash-can',        'titulo' => 'Eliminar Solicitud',    'desc' => 'El botón rojo elimina permanentemente la solicitud y todo su historial. El sistema pedirá confirmación dos veces antes de ejecutar esta acción irreversible.'],
        ],
        'tips'     => ['Los resultados están paginados de 15 en 15. Navega con los botones de paginación abajo de la tabla.', 'La columna "Resuelto por" muestra el nombre del administrador que completó la solicitud.'],
    ],

    'detalle' => [
        'titulo'   => 'Detalle de Solicitud',
        'icono'    => 'fa-solid fa-file-lines',
        'intro'    => 'Esta página muestra toda la información de una solicitud específica, incluyendo la línea de tiempo de sus cambios.',
        'pasos'    => [
            ['icono' => 'fa-solid fa-id-card',       'titulo' => 'Datos del Solicitante',  'desc' => 'La sección superior muestra el nombre, área y correo del empleado que registró la solicitud, junto con el folio único, tipo y prioridad asignada.'],
            ['icono' => 'fa-solid fa-file-alt',       'titulo' => 'Descripción',            'desc' => 'El cuerpo de la solicitud detalla qué se necesita. Si el tipo es "Sistemas/Web", verás los subtipos específicos seleccionados por el empleado.'],
            ['icono' => 'fa-solid fa-paperclip',      'titulo' => 'Archivos Adjuntos',      'desc' => 'Si el empleado adjuntó documentos o capturas de pantalla, aparecerán aquí como enlaces para descargarlos o visualizarlos directamente.'],
            ['icono' => 'fa-solid fa-pen-to-square',  'titulo' => 'Cambiar Estatus',        'desc' => 'Usa el botón "Cambiar Estatus" para avanzar el ticket. Puedes agregar un comentario descriptivo que quedará registrado en el historial.'],
            ['icono' => 'fa-solid fa-clock-rotate-left', 'titulo' => 'Historial de Cambios', 'desc' => 'La línea de tiempo al final muestra cada transición de estado, quién la realizó, cuándo y qué comentario dejó el administrador responsable.'],
        ],
        'tips'     => ['El historial de cambios es inmutable: sirve como bitácora oficial de auditoría.', 'Puedes regresar a la lista con el botón "← Volver a Solicitudes".'],
    ],

    'administradores' => [
        'titulo'   => 'Gestión de Administradores',
        'icono'    => 'fa-solid fa-users-gear',
        'intro'    => 'Aquí controlas quién tiene acceso al panel de administración del sistema.',
        'pasos'    => [
            ['icono' => 'fa-solid fa-user-plus',   'titulo' => 'Crear Administrador',   'desc' => 'Haz clic en "Nuevo Administrador" para registrar una cuenta nueva. Completa el nombre, correo electrónico y una contraseña inicial segura.'],
            ['icono' => 'fa-solid fa-key',         'titulo' => 'Contraseñas Seguras',   'desc' => 'Las contraseñas se almacenan cifradas (bcrypt). Si necesitas resetear el acceso de alguien, usa la opción "Editar" para asignar una nueva contraseña.'],
            ['icono' => 'fa-solid fa-toggle-on',   'titulo' => 'Activar / Desactivar',  'desc' => 'En lugar de borrar cuentas (lo cual perdería el historial de acciones), puedes desactivar a un administrador. Esto bloquea su acceso pero conserva sus registros.'],
            ['icono' => 'fa-solid fa-pencil',      'titulo' => 'Editar Datos',          'desc' => 'El botón del lápiz te permite actualizar el nombre o correo de un administrador existente sin afectar su historial de actividad.'],
        ],
        'tips'     => ['Un administrador desactivado NO puede iniciar sesión, pero su nombre seguirá apareciendo en el historial de solicitudes que gestionó.', 'Siempre mantén al menos 2 cuentas de administrador activas.'],
    ],

    'personal' => [
        'titulo'   => 'Catálogo de Personal',
        'icono'    => 'fa-solid fa-address-card',
        'intro'    => 'Este módulo contiene el directorio del personal institucional que puede registrar solicitudes en el sistema.',
        'pasos'    => [
            ['icono' => 'fa-solid fa-user-plus',   'titulo' => 'Registrar Empleado',    'desc' => 'Agrega un nuevo servidor público con su nombre completo, correo institucional, área y extensión telefónica. El correo es su identificador único de acceso al portal.'],
            ['icono' => 'fa-solid fa-pencil',      'titulo' => 'Editar Información',    'desc' => 'Usa el botón de edición para corregir datos desactualizados (cambios de área, extensión, etc.) de cualquier empleado registrado.'],
            ['icono' => 'fa-solid fa-magnifying-glass', 'titulo' => 'Buscar Personal',  'desc' => 'La barra de búsqueda permite localizar empleados por nombre, apellido, área o correo electrónico rápidamente.'],
            ['icono' => 'fa-solid fa-building',    'titulo' => 'Filtrar por Área',      'desc' => 'Usa el selector de área para ver únicamente el personal de una dirección o departamento específico.'],
        ],
        'tips'     => ['Si un empleado cambia de área, actualiza su registro aquí para que sus futuras solicitudes reflejen el área correcta.', 'El correo electrónico es la clave de acceso al portal público de solicitudes.'],
    ],

    'equipos' => [
        'titulo'   => 'Control de Equipos de Cómputo',
        'icono'    => 'fa-solid fa-laptop-code',
        'intro'    => 'Administra el inventario de equipos tecnológicos del COMECyT y controla qué empleado tiene asignado cada equipo.',
        'pasos'    => [
            ['icono' => 'fa-solid fa-desktop',         'titulo' => 'Registrar Equipo',       'desc' => 'Agrega un nuevo equipo con su clave de bienes (CVE), marca, modelo, número de serie y tipo (Laptop, Monitor, Impresora, etc.).'],
            ['icono' => 'fa-solid fa-user-check',      'titulo' => 'Asignar a Empleado',     'desc' => 'Selecciona un equipo y asígnalo a un empleado del catálogo de personal. Quedará registrada la fecha de asignación automáticamente.'],
            ['icono' => 'fa-solid fa-right-left',      'titulo' => 'Reasignar o Liberar',   'desc' => 'Si el equipo cambia de usuario o se devuelve, puedes reasignarlo o marcarlo como disponible. El historial de asignaciones anterior se conserva.'],
            ['icono' => 'fa-solid fa-magnifying-glass','titulo' => 'Buscar y Filtrar',       'desc' => 'Localiza equipos por clave, marca, modelo o filtrar por el área del empleado asignado para saber qué equipos están en cada departamento.'],
        ],
        'tips'     => ['La clave de bienes (CVE) debe coincidir con el inventario oficial del COMECyT.', 'Un empleado puede consultar sus equipos asignados desde el portal público en "Mis Equipos".'],
    ],

    'correos' => [
        'titulo'   => 'Correos Oficiales',
        'icono'    => 'fa-solid fa-envelope-open-text',
        'intro'    => 'Gestiona el directorio de correos electrónicos institucionales y de contacto del COMECyT.',
        'pasos'    => [
            ['icono' => 'fa-solid fa-envelope-circle-check', 'titulo' => 'Ver Directorio',    'desc' => 'Consulta la lista completa de correos institucionales registrados, organizados por área o departamento.'],
            ['icono' => 'fa-solid fa-plus-circle',           'titulo' => 'Agregar Correo',    'desc' => 'Registra un nuevo correo de contacto indicando el área, nombre del responsable y la dirección de correo electrónico correspondiente.'],
            ['icono' => 'fa-solid fa-pencil',                'titulo' => 'Editar Registro',   'desc' => 'Actualiza la información de un correo registrado si el titular cambia o si la dirección de correo es modificada.'],
            ['icono' => 'fa-solid fa-trash-can',             'titulo' => 'Eliminar Registro', 'desc' => 'Elimina permanentemente un correo que ya no esté en uso o pertenezca a un área que ya no existe.'],
        ],
        'tips'     => ['Mantén este directorio actualizado para que el personal pueda contactar fácilmente a las áreas correspondientes.'],
    ],

    'calendario' => [
        'titulo'   => 'Calendario y Tablero Kanban',
        'icono'    => 'fa-solid fa-calendar-days',
        'intro'    => 'Este módulo integra un calendario de eventos institucionales y un tablero de tareas colaborativo (Kanban) para el equipo de Tecnologías de la Información.',
        'pasos'    => [
            ['icono' => 'fa-solid fa-calendar-plus',  'titulo' => 'Crear Evento',        'desc' => 'Haz clic en cualquier día del calendario o en el botón "+ Evento" para registrar reuniones, fechas límite o actividades institucionales. Puedes asignar un color identificador.'],
            ['icono' => 'fa-solid fa-arrows-up-down-left-right', 'titulo' => 'Mover en Calendario', 'desc' => 'Arrastra y suelta los eventos en el calendario para cambiar su fecha sin necesidad de editarlos.'],
            ['icono' => 'fa-solid fa-list-check',     'titulo' => 'Tablero Kanban',      'desc' => 'El tablero organiza las tareas del equipo TI en columnas: "Por hacer", "En progreso" y "Completado". Arrastra las tarjetas entre columnas para actualizar su estado.'],
            ['icono' => 'fa-solid fa-plus-circle',    'titulo' => 'Nueva Tarea Kanban',  'desc' => 'Crea tareas directamente desde el chat del equipo con el botón "+ Tarea Kanban", o desde el propio tablero. Puedes asignarla a otro administrador y darle un color.'],
            ['icono' => 'fa-solid fa-users',          'titulo' => 'Chat del Equipo TI',  'desc' => 'El panel de chat en la esquina Superior derecha permite comunicación en tiempo real entre los administradores. Desde aquí también puedes crear eventos y tareas rápidamente.'],
        ],
        'tips'     => ['El chat solo es visible para usuarios con sesión de administrador.', 'Las tareas Kanban creadas desde el chat aparecen automáticamente en el tablero.'],
    ],

    // ── Portal Público de Personal ───────────────────────────────────
    'nueva_solicitud' => [
        'titulo'   => 'Registrar Nueva Solicitud',
        'icono'    => 'fa-solid fa-paper-plane',
        'intro'    => 'Usa este formulario para enviar una solicitud al área de Tecnologías de la Información del COMECyT.',
        'pasos'    => [
            ['icono' => 'fa-solid fa-right-to-bracket', 'titulo' => 'Ingresa tu Correo', 'desc' => 'Escribe tu correo institucional registrado y haz clic en "Continuar". El sistema verificará tu identidad y precargará tus datos automáticamente.'],
            ['icono' => 'fa-solid fa-tag',              'titulo' => 'Tipo de Solicitud', 'desc' => 'Selecciona la categoría que mejor describa tu necesidad: Mantenimiento de equipo, Soporte técnico, Atención administrativa, Sistemas/Web, etc.'],
            ['icono' => 'fa-solid fa-align-left',       'titulo' => 'Descripción',       'desc' => 'Describe detalladamente tu problema o necesidad. Mientras más información proporciones, más rápido podrá atenderse tu solicitud.'],
            ['icono' => 'fa-solid fa-flag',             'titulo' => 'Prioridad',         'desc' => 'Indica la urgencia: Baja (puede esperar días), Media (esta semana), Alta (hoy) o Urgente (atención inmediata requerida). Evita marcar como Urgente si no lo es.'],
            ['icono' => 'fa-solid fa-paperclip',        'titulo' => 'Adjuntar Archivos', 'desc' => 'Si tienes capturas de pantalla, documentos de referencia o evidencia del problema, puedes adjuntarlos aquí. Formatos aceptados: PDF, JPG, PNG (máx. 5 MB c/u).'],
            ['icono' => 'fa-solid fa-paper-plane',      'titulo' => 'Enviar',            'desc' => 'Al presionar "Enviar Solicitud", recibirás un folio único de seguimiento. Guárdalo para consultar el estado de tu solicitud desde el "Historial".'],
        ],
        'tips'     => ['Tu folio tiene el formato CMCT-XXXX-AAAA. Guárdalo para dar seguimiento.', 'No es necesario que reenvíes una solicitud ya registrada; el equipo TI la revisará.'],
    ],

    'historial' => [
        'titulo'   => 'Mi Historial de Solicitudes',
        'icono'    => 'fa-solid fa-clock-rotate-left',
        'intro'    => 'Aquí puedes consultar todas las solicitudes que has registrado y ver el estado actual de cada una.',
        'pasos'    => [
            ['icono' => 'fa-solid fa-list',           'titulo' => 'Ver Mis Solicitudes', 'desc' => 'La tabla muestra todas tus solicitudes ordenadas de la más reciente a la más antigua, con su folio, tipo, prioridad y estado actual.'],
            ['icono' => 'fa-solid fa-circle-dot',     'titulo' => 'Entender los Estados', 'desc' => 'Pendiente (en espera de atención) → En Proceso (el equipo TI está trabajando en ello) → Completada (tu solicitud fue resuelta) / Cancelada (fue desestimada con justificación).'],
            ['icono' => 'fa-solid fa-eye',            'titulo' => 'Ver Detalle',         'desc' => 'Haz clic en el folio de cualquier solicitud para ver su historial completo de cambios, los comentarios del equipo TI y la información que registraste.'],
        ],
        'tips'     => ['Si tu solicitud lleva más de 3 días en "Pendiente", puedes contactar directamente al área de TI.', 'Las solicitudes "Canceladas" incluyen el motivo en el detalle.'],
    ],

    'consulta' => [
        'titulo'   => 'Consulta de Solicitud por Folio',
        'icono'    => 'fa-solid fa-magnifying-glass',
        'intro'    => 'Puedes rastrear el estado de cualquier solicitud usando su número de folio, sin necesidad de iniciar sesión.',
        'pasos'    => [
            ['icono' => 'fa-solid fa-ticket',         'titulo' => 'Ingresa el Folio',    'desc' => 'Escribe el folio completo de tu solicitud en el formato CMCT-XXXX-AAAA (lo recibiste al enviar tu solicitud) y haz clic en "Consultar".'],
            ['icono' => 'fa-solid fa-circle-info',    'titulo' => 'Ver Estado Actual',   'desc' => 'Verás el estado de tu solicitud: Pendiente, En Proceso, Completada o Cancelada, junto con la fecha de registro y el último cambio registrado.'],
            ['icono' => 'fa-solid fa-timeline',       'titulo' => 'Historial de Cambios','desc' => 'El sistema muestra la línea de tiempo con cada cambio de estado realizado por el equipo de TI, incluyendo sus comentarios explicativos.'],
        ],
        'tips'     => ['No necesitas iniciar sesión para usar esta consulta pública.', 'Si no encuentras tu folio, verifica que lo estés escribiendo correctamente, incluyendo los guiones.'],
    ],

    'equipos_usuario' => [
        'titulo'   => 'Mis Equipos Asignados',
        'icono'    => 'fa-solid fa-laptop',
        'intro'    => 'Aquí puedes ver el inventario de equipos tecnológicos que el COMECyT tiene asignados a tu nombre.',
        'pasos'    => [
            ['icono' => 'fa-solid fa-desktop',   'titulo' => 'Lista de Equipos',     'desc' => 'Se muestran todos los equipos registrados bajo tu nombre: laptops, monitores, impresoras u otros dispositivos, con su clave de bienes oficial.'],
            ['icono' => 'fa-solid fa-barcode',   'titulo' => 'Datos del Equipo',     'desc' => 'Cada tarjeta muestra la clave de bienes (CVE), marca, modelo, número de serie y la fecha en que te fue asignado formalmente.'],
            ['icono' => 'fa-solid fa-triangle-exclamation', 'titulo' => 'Reportar Problema', 'desc' => 'Si tienes un problema con alguno de tus equipos asignados, ve a "Registrar Solicitud" y selecciona el tipo "Mantenimiento" o "Soporte" para escalarlo al equipo TI.'],
        ],
        'tips'     => ['Si crees que hay un equipo faltante o uno que ya no tienes, contacta al área de TI para actualizar el inventario.', 'Esta vista es de solo lectura; los cambios de asignación los realiza el administrador.'],
    ],
];

// -----------------------------------------------------------------------
// Renderizar el widget solo si se definió una clave válida
// -----------------------------------------------------------------------
$helpPage     = $helpPage ?? '';
$helpData     = $HELP_CATALOG[$helpPage] ?? null;

// Si no hay datos para esta página, no renderizar nada
if (!$helpData) return;

$modalId = 'helpModal_' . htmlspecialchars($helpPage, ENT_QUOTES, 'UTF-8');
?>

<?php /* =====================================================================
   BOTÓN FLOTANTE DE AYUDA
   Position: fixed, esquina inferior derecha, z-index sobre todo
   ===================================================================== */ ?>
<button
    id="helpWidgetBtn"
    aria-label="Abrir ayuda de esta página"
    title="¿Cómo funciona esta sección?"
    onclick="document.getElementById('<?= $modalId ?>').classList.add('hw-open')"
>
    <i class="fa-solid fa-circle-question"></i>
    <span class="hw-btn-label">Ayuda</span>
</button>

<?php /* =====================================================================
   MODAL DE AYUDA
   ===================================================================== */ ?>
<div id="<?= $modalId ?>" class="hw-overlay" role="dialog" aria-modal="true" aria-labelledby="hw-title-<?= $helpPage ?>"
     onclick="if(event.target===this)this.classList.remove('hw-open')">

    <div class="hw-panel">

        <!-- Cabecera del Modal -->
        <div class="hw-header">
            <div class="hw-header-icon">
                <i class="<?= htmlspecialchars($helpData['icono'], ENT_QUOTES, 'UTF-8') ?>"></i>
            </div>
            <div class="hw-header-text">
                <p class="hw-header-label">Guía de uso</p>
                <h2 id="hw-title-<?= $helpPage ?>" class="hw-header-title">
                    <?= htmlspecialchars($helpData['titulo'], ENT_QUOTES, 'UTF-8') ?>
                </h2>
            </div>
            <button class="hw-close"
                    onclick="document.getElementById('<?= $modalId ?>').classList.remove('hw-open')"
                    aria-label="Cerrar ayuda">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <!-- Cuerpo del Modal -->
        <div class="hw-body">

            <!-- Introducción -->
            <p class="hw-intro">
                <i class="fa-solid fa-circle-info hw-intro-icon"></i>
                <?= htmlspecialchars($helpData['intro'], ENT_QUOTES, 'UTF-8') ?>
            </p>

            <!-- Pasos -->
            <div class="hw-steps-title">
                <i class="fa-solid fa-list-ol"></i>
                ¿Qué puedo hacer aquí?
            </div>
            <ol class="hw-steps">
                <?php foreach ($helpData['pasos'] as $i => $paso): ?>
                <li class="hw-step">
                    <div class="hw-step-num"><?= $i + 1 ?></div>
                    <div class="hw-step-icon">
                        <i class="<?= htmlspecialchars($paso['icono'], ENT_QUOTES, 'UTF-8') ?>"></i>
                    </div>
                    <div class="hw-step-content">
                        <strong class="hw-step-title"><?= htmlspecialchars($paso['titulo'], ENT_QUOTES, 'UTF-8') ?></strong>
                        <p class="hw-step-desc"><?= htmlspecialchars($paso['desc'], ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                </li>
                <?php endforeach; ?>
            </ol>

            <!-- Tips (si existen) -->
            <?php if (!empty($helpData['tips'])): ?>
            <div class="hw-tips-box">
                <div class="hw-tips-header">
                    <i class="fa-solid fa-lightbulb"></i>
                    Consejos rápidos
                </div>
                <ul class="hw-tips-list">
                    <?php foreach ($helpData['tips'] as $tip): ?>
                    <li><?= htmlspecialchars($tip, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

        </div>

        <!-- Pie del Modal -->
        <div class="hw-footer">
            <button class="hw-footer-close"
                    onclick="document.getElementById('<?= $modalId ?>').classList.remove('hw-open')">
                <i class="fa-solid fa-circle-check"></i>
                ¡Entendido!
            </button>
        </div>

    </div>
</div>

<?php /* =====================================================================
   ESTILOS INLINE — Autónomos, no dependen de main.css
   Siguiendo el patron del loader.php del proyecto (componente autonomo)
   ===================================================================== */ ?>
<style>
/* ── Variables propias del widget (alineadas con branding COMECyT) ── */
.hw-overlay,
.hw-panel,
.hw-header,
.hw-body,
.hw-footer,
#helpWidgetBtn {
    --hw-primary:      #662331;
    --hw-primary-dark: #4d1a25;
    --hw-accent:       #B19A6D;
    --hw-accent-light: rgba(177,154,109,0.12);
    --hw-white:        #ffffff;
    --hw-bg:           #f8f7f5;
    --hw-text:         #111827;
    --hw-text-muted:   #6b7280;
    --hw-border:       #e5e7eb;
    --hw-radius:       16px;
    --hw-shadow:       0 24px 80px rgba(102,35,49,0.22), 0 4px 20px rgba(0,0,0,0.08);
    font-family: Inter, 'Segoe UI', system-ui, sans-serif;
}

/* ── Botón Flotante ─────────────────────────────────────────────────── */
#helpWidgetBtn {
    position: fixed;
    bottom: 26px;
    right: 26px;
    z-index: 8990; /* debajo del chat (9999) */
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 20px;
    background: linear-gradient(135deg, var(--hw-primary) 0%, #8b2f42 100%);
    color: var(--hw-white);
    border: none;
    border-radius: 50px;
    cursor: pointer;
    font-size: 0.88rem;
    font-weight: 600;
    box-shadow: 0 6px 24px rgba(102,35,49,0.35), 0 2px 6px rgba(0,0,0,0.15);
    transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
    letter-spacing: 0.02em;
    outline: none;
}
#helpWidgetBtn i {
    font-size: 1.1rem;
    transition: transform 0.3s ease;
}
#helpWidgetBtn:hover {
    transform: translateY(-3px) scale(1.03);
    box-shadow: 0 10px 32px rgba(102,35,49,0.4), 0 4px 10px rgba(0,0,0,0.18);
    background: linear-gradient(135deg, #7a2a3b 0%, #9e3549 100%);
}
#helpWidgetBtn:hover i {
    transform: rotate(15deg);
}
#helpWidgetBtn:active {
    transform: translateY(0) scale(0.98);
}
/* Ocultar etiqueta en móvil muy pequeño */
@media (max-width: 380px) {
    .hw-btn-label { display: none; }
    #helpWidgetBtn { padding: 14px; border-radius: 50%; }
}

/* ── Overlay / Backdrop ─────────────────────────────────────────────── */
.hw-overlay {
    position: fixed;
    inset: 0;
    z-index: 9100;
    background: rgba(17, 24, 39, 0.55);
    backdrop-filter: blur(5px);
    -webkit-backdrop-filter: blur(5px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
    /* Oculto por defecto */
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.25s ease;
}
.hw-overlay.hw-open {
    opacity: 1;
    pointer-events: all;
}

/* ── Panel Principal del Modal ──────────────────────────────────────── */
.hw-panel {
    background: var(--hw-white);
    border-radius: var(--hw-radius);
    box-shadow: var(--hw-shadow);
    width: 100%;
    max-width: 560px;
    max-height: 88vh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    transform: translateY(20px) scale(0.97);
    transition: transform 0.28s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.hw-overlay.hw-open .hw-panel {
    transform: translateY(0) scale(1);
}

/* ── Cabecera del Modal ─────────────────────────────────────────────── */
.hw-header {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 22px 24px 18px;
    background: linear-gradient(135deg, var(--hw-primary) 0%, #8b2f42 100%);
    flex-shrink: 0;
    position: relative;
}
.hw-header-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: rgba(255,255,255,0.18);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    color: var(--hw-white);
    flex-shrink: 0;
    border: 1px solid rgba(255,255,255,0.25);
}
.hw-header-text {
    flex: 1;
    min-width: 0;
}
.hw-header-label {
    font-size: 0.68rem;
    font-weight: 700;
    color: rgba(255,255,255,0.65);
    text-transform: uppercase;
    letter-spacing: 0.08em;
    margin: 0 0 2px;
}
.hw-header-title {
    font-size: 1.05rem;
    font-weight: 700;
    color: var(--hw-white);
    margin: 0;
    line-height: 1.25;
}
.hw-close {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: rgba(255,255,255,0.18);
    border: 1px solid rgba(255,255,255,0.25);
    color: var(--hw-white);
    font-size: 0.9rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: background 0.2s ease;
}
.hw-close:hover  { background: rgba(255,255,255,0.3); }

/* ── Cuerpo scrollable ──────────────────────────────────────────────── */
.hw-body {
    flex: 1;
    overflow-y: auto;
    padding: 22px 24px 0;
    scroll-behavior: smooth;
}
/* scrollbar personalizado */
.hw-body::-webkit-scrollbar { width: 5px; }
.hw-body::-webkit-scrollbar-track { background: transparent; }
.hw-body::-webkit-scrollbar-thumb { background: rgba(102,35,49,0.25); border-radius: 4px; }

/* ── Introducción ───────────────────────────────────────────────────── */
.hw-intro {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    background: var(--hw-accent-light);
    border: 1px solid rgba(177,154,109,0.3);
    border-left: 4px solid var(--hw-accent);
    border-radius: 10px;
    padding: 12px 14px;
    font-size: 0.875rem;
    color: var(--hw-text);
    line-height: 1.55;
    margin: 0 0 20px;
}
.hw-intro-icon {
    color: var(--hw-accent);
    flex-shrink: 0;
    margin-top: 2px;
    font-size: 0.95rem;
}

/* ── Título de sección de pasos ─────────────────────────────────────── */
.hw-steps-title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--hw-primary);
    text-transform: uppercase;
    letter-spacing: 0.07em;
    margin-bottom: 12px;
}

/* ── Lista de pasos ─────────────────────────────────────────────────── */
.hw-steps {
    list-style: none;
    margin: 0 0 20px;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.hw-step {
    display: grid;
    grid-template-columns: 28px 38px 1fr;
    align-items: flex-start;
    gap: 0 10px;
    background: var(--hw-bg);
    border: 1px solid var(--hw-border);
    border-radius: 12px;
    padding: 13px 14px;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}
.hw-step:hover {
    border-color: rgba(102,35,49,0.25);
    box-shadow: 0 2px 10px rgba(102,35,49,0.07);
}

/* Número del paso */
.hw-step-num {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--hw-primary), #8b2f42);
    color: #fff;
    font-size: 0.7rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    margin-top: 1px;
}
/* Ícono del paso */
.hw-step-icon {
    width: 34px;
    height: 34px;
    border-radius: 9px;
    background: rgba(102,35,49,0.08);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--hw-primary);
    font-size: 0.9rem;
    flex-shrink: 0;
    margin-top: -5px;
}
/* Contenido del paso */
.hw-step-content { min-width: 0; }
.hw-step-title {
    display: block;
    font-size: 0.875rem;
    font-weight: 700;
    color: var(--hw-text);
    margin-bottom: 3px;
}
.hw-step-desc {
    font-size: 0.815rem;
    color: var(--hw-text-muted);
    line-height: 1.5;
    margin: 0;
}

/* ── Caja de Tips ───────────────────────────────────────────────────── */
.hw-tips-box {
    background: linear-gradient(135deg, #fffbf0 0%, #fef9ee 100%);
    border: 1px solid rgba(177,154,109,0.35);
    border-radius: 12px;
    padding: 14px 16px;
    margin-bottom: 22px;
}
.hw-tips-header {
    display: flex;
    align-items: center;
    gap: 7px;
    font-size: 0.78rem;
    font-weight: 700;
    color: #7d6535;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    margin-bottom: 10px;
}
.hw-tips-header i { color: var(--hw-accent); }
.hw-tips-list {
    margin: 0;
    padding-left: 18px;
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.hw-tips-list li {
    font-size: 0.825rem;
    color: #5c4a28;
    line-height: 1.5;
}
.hw-tips-list li::marker { color: var(--hw-accent); }

/* ── Pie del Modal ──────────────────────────────────────────────────── */
.hw-footer {
    padding: 14px 24px 18px;
    border-top: 1px solid var(--hw-border);
    display: flex;
    justify-content: flex-end;
    flex-shrink: 0;
    background: var(--hw-white);
}
.hw-footer-close {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 22px;
    background: linear-gradient(135deg, var(--hw-primary) 0%, #8b2f42 100%);
    color: var(--hw-white);
    border: none;
    border-radius: 8px;
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    transition: opacity 0.2s ease, transform 0.15s ease;
    font-family: inherit;
}
.hw-footer-close:hover  { opacity: 0.9; transform: translateY(-1px); }
.hw-footer-close:active { transform: translateY(0); }

/* ── Responsive ─────────────────────────────────────────────────────── */
@media (max-width: 600px) {
    .hw-panel { max-height: 95vh; border-radius: 14px; }
    .hw-header { padding: 16px 18px 14px; }
    .hw-body   { padding: 16px 18px 0; }
    .hw-footer { padding: 12px 18px 16px; }
    .hw-step   { grid-template-columns: 24px 32px 1fr; }
}
</style>

<?php /* =====================================================================
   SCRIPT — Cierre con tecla Escape
   ===================================================================== */ ?>
<script>
(function() {
    const modalId = '<?= $modalId ?>';
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const m = document.getElementById(modalId);
            if (m) m.classList.remove('hw-open');
        }
    });
})();
</script>
