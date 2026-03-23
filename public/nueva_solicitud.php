<?php
/**
 * COMECyT Control de Solicitudes
 * Vista Publica — Nueva Solicitud
 *
 * Permite a cualquier usuario (sin login) registrar una solicitud.
 * Al enviarse correctamente, muestra el folio asignado.
 * No requiere autenticacion.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/auth.php';

// Validar que el usuario esté loggeado (Admin o Usuario)
verificarSesionUsuario();

$pdo = getConnection();

// Estado del formulario
$exito      = false;
$folioNuevo = '';
$errores    = [];
$datos      = [];

$solicitanteNombre = $_SESSION['user_nombre'] ?? $_SESSION['admin_nombre'] ?? '';
$solicitanteEmail  = $_SESSION['user_email'] ?? $_SESSION['admin_email'] ?? '';
$solicitanteArea   = $_SESSION['user_area'] ?? 'Sistema';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCsrfPost();
    // Recoger y sanitizar entradas (datos fijos desde la sesion)
    $datos = [
        'tipo'              => postParam('tipo'),
        'solicitante'       => $solicitanteNombre,
        'email_solicitante' => $solicitanteEmail,
        'area'              => $solicitanteArea,
        'prioridad'         => postParam('prioridad'),
        'descripcion'       => postParam('descripcion'),
    ];

    // Validaciones
    $tiposValidos     = array_keys(ETIQUETAS_TIPO);
    $prioridadesValidas = array_keys(ETIQUETAS_PRIORIDAD);

    if (!in_array($datos['tipo'], $tiposValidos, true)) {
        $errores[] = 'Seleccione un tipo de solicitud valido.';
    }
    if (mb_strlen($datos['solicitante']) < 3) {
        $errores[] = 'El nombre del solicitante debe tener al menos 3 caracteres.';
    }
    if (!empty($datos['email_solicitante']) && !filter_var($datos['email_solicitante'], FILTER_VALIDATE_EMAIL)) {
        $errores[] = 'El correo electronico no es valido.';
    }
    if (mb_strlen($datos['area']) < 2) {
        $errores[] = 'Indique el area o departamento de origen.';
    }
    if (mb_strlen($datos['descripcion']) < 20) {
        $errores[] = 'La descripcion debe tener al menos 20 caracteres.';
    }
    if (!in_array($datos['prioridad'], $prioridadesValidas, true)) {
        $datos['prioridad'] = 'media';
    }

    if (empty($errores)) {
        $archivoFinal      = null;
        $archivosJsonFinal = null;

        // ── Logica de validacion y subida de archivos (Sistemas / Web) ──────────
        if ($datos['tipo'] === 'sistemas' && !empty($_FILES['archivos_adjuntos']['name'][0])) {
            $permitidos   = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'png', 'jpg', 'jpeg', 'gif', 'mp4', 'avi', 'webp'];
            $subidoOk     = [];
            $detallesOk   = [];
            $uploadDir    = UPLOAD_DIR_SOLICITUDES;

            foreach ($_FILES['archivos_adjuntos']['tmp_name'] as $idx => $tmpName) {
                if ($_FILES['archivos_adjuntos']['error'][$idx] !== UPLOAD_ERR_OK) {
                    continue; 
                }
                $rawName    = basename($_FILES['archivos_adjuntos']['name'][$idx]);
                $ext        = strtolower(pathinfo($rawName, PATHINFO_EXTENSION));

                if (!in_array($ext, $permitidos, true)) {
                    $errores[] = "Tipo no permitido: {$rawName}.";
                    continue;
                }

                // Validar MIME type real (previene archivos maliciosos renombrados)
                $mimesPermitidos = [
                    'application/pdf', 'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'image/png', 'image/jpeg', 'image/gif', 'image/webp',
                    'video/mp4', 'video/x-msvideo',
                ];
                $finfo    = new finfo(FILEINFO_MIME_TYPE);
                $mimeReal = $finfo->file($tmpName);
                if (!in_array($mimeReal, $mimesPermitidos, true)) {
                    $errores[] = "Tipo de archivo no permitido (MIME: {$mimeReal}): {$rawName}.";
                    continue;
                }

                $safeRaw    = preg_replace('/[^a-zA-Z0-9.\-_]/', '', $rawName);
                $nuevoNombre = uniqid('req_sys_') . '_' . $safeRaw;

                if (move_uploaded_file($tmpName, $uploadDir . $nuevoNombre)) {
                    $subidoOk[] = $nuevoNombre;
                    
                    // Buscar si este archivo especifico trae detalle en la variable POST (indexado por nombre seguro)
                    $safeKey = preg_replace('/[^a-zA-Z0-9]/', '_', $rawName);
                    if (!empty($_POST['archivo_detalle'][$safeKey])) {
                        $detallesOk[] = "- Archivo: {$rawName}\n  Detalle: " . trim($_POST['archivo_detalle'][$safeKey]);
                    }
                } else {
                    $errores[] = "Error de permisos al guardar: {$rawName}";
                }
            }

            // Guardar lista como JSON; si no subio nada, queda null
            $archivoFinal        = !empty($subidoOk) ? $subidoOk[0] : null; // legado campo unico
            $archivosJsonFinal   = !empty($subidoOk) ? json_encode($subidoOk, JSON_UNESCAPED_UNICODE) : null;
        }

        if (empty($errores)) {
            $equipo_id = null;

            // Logica para Equipo en Solicitudes de Mantenimiento
            if ($datos['tipo'] === 'mantenimiento') {
                $opcion_equipo = postParam('opcion_equipo');
                $cve_personal = $_SESSION['user_id'] ?? ($_SESSION['admin_id'] ?? 0);
                
                if ($opcion_equipo === 'seleccionar_equipo') {
                    $equipo_str = postParam('equipo_existente');
                    if (!empty($equipo_str)) {
                        $equipo_id = (int)$equipo_str;
                    }
                } elseif ($opcion_equipo === 'registrar_equipo') {
                    $eq_marca = trim(postParam('eq_marca'));
                    $eq_modelo = trim(postParam('eq_modelo'));
                    $eq_num_serie = trim(postParam('eq_num_serie'));
                    $eq_num_inv = trim(postParam('eq_num_inventario'));
                    
                    if (!empty($eq_marca) && !empty($eq_modelo)) {
                        // Extraer o asumir cve_area del usuario
                        $cve_area_usr = (int)($_SESSION['user_cve_area'] ?? 1); 
                        
                        // Insertar equipo en calidad de pendiente
                        $stmtEqIns = $pdo->prepare(
                            "INSERT INTO sb_bienes (marca, modelo, num_serie, num_inventario, resguardatario, cve_area, cve_estatus, estatus_alta) 
                            VALUES (?, ?, ?, ?, ?, ?, 1, 'pendiente')"
                        );
                        $stmtEqIns->execute([$eq_marca, $eq_modelo, $eq_num_serie, $eq_num_inv, $cve_personal, $cve_area_usr]);
                        $equipo_id = (int)$pdo->lastInsertId();
                    }
                }
            }

            // Capturar subtipo de sistemas cuando aplica
            $subtipoSistemas = ($datos['tipo'] === 'sistemas') ? postParam('subtipo_sistemas') : null;
            $subtiposValidos = array_keys(ETIQUETAS_SUBTIPO_SISTEMAS);
            if ($subtipoSistemas !== null && !in_array($subtipoSistemas, $subtiposValidos, true)) {
                $subtipoSistemas = null;
            }

            // Apendizar detalles dinámicos a la descripción principal
            if ($subtipoSistemas === 'generar_link') {
                $url_ref = postParam('url_referencia');
                if (!empty($url_ref)) {
                    $datos['descripcion'] .= "\n\n--- Enlace de Referencia ---\n" . $url_ref;
                }
                // Al adjuntar archivo, la evidencia ya quedó subida en archivos_adjuntos.
                // Solo insertamos un mensaje genérico para que el de sistemas sepa que debe ver el archivo.
                $datos['descripcion'] .= "\n\n--- Actualización de Hipervínculos ---\nSe adjuntó documento con la lista de hipervínculos a actualizar.";
            } elseif ($subtipoSistemas === 'problema_sistema') {
                if (!empty($detallesOk)) {
                    $datos['descripcion'] .= "\n\n--- Detalles de Evidencia ---\n" . implode("\n\n", $detallesOk);
                }
                
            }

            // Generar folio y persistir
            $folio = generarFolio($pdo);

            $ins = $pdo->prepare(
                "INSERT INTO solicitudes (folio, tipo, solicitante, email_solicitante, area, prioridad, descripcion, equipo_id, archivo_adjunto, archivos_adjuntos, subtipo_sistemas)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $ins->execute([
                $folio,
                $datos['tipo'],
                $datos['solicitante'],
                $datos['email_solicitante'] ?: '',
                $datos['area'],
                $datos['prioridad'],
                $datos['descripcion'],
                $equipo_id,
                $archivoFinal,
                $archivosJsonFinal,
                $subtipoSistemas
            ]);

            $idNuevo = (int) $pdo->lastInsertId();

            // Primer registro en historial
            $pdo->prepare(
                "INSERT INTO historial_solicitudes (solicitud_id, estatus_anterior, estatus_nuevo, comentario)
                VALUES (?, NULL, 'pendiente', 'Solicitud registrada.')"
            )->execute([$idNuevo]);

            $folioNuevo = $folio;
            $exito      = true;
            $datos      = []; // Limpiar formulario
        } // Cierre if validacion final (upload safe)
    }
}
?>
<?php
// -------------------------------------------------------
// Variables para la vista
// -------------------------------------------------------
$pageTitle  = 'Nueva Solicitud';
$activeMenu = 'nueva_solicitud';
$helpPage   = 'nueva_solicitud';

$usuariologueado = !empty($_SESSION['user_id']) || !empty($_SESSION['admin_id']);

if ($usuariologueado) {
    // Layout de Dashboard para Usuario Regular (o admin en vista publica)
    require_once __DIR__ . '/../includes/header_user.php';
} else {
    // Fallback: Layout público/clásico u oculto (Aunque el middleware restringe el acceso)
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="description" content="Registre una solicitud interna en el sistema COMECyT. Atencion, soporte, mantenimiento y administracion.">
        <title><?= esc($pageTitle) ?> — COMECyT</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
        <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/main.css?v=2.0">
    </head>
    <body class="layout-public">
    <?php require_once __DIR__ . '/../includes/loader.php'; ?>
    <?php
}
?>

<!-- Contenedor principal condicional ajustado al contexto activo -->
<div class="<?= $usuariologueado ? '' : 'public-main' ?>" <?= $usuariologueado ? 'style="margin: 24px;"' : '' ?>>

    <?php if (!$usuariologueado): ?>
    <!-- Encabezado publico fallback -->
    <header class="public-header" style="margin-bottom: 24px;">
        <div class="public-brand">
            <div class="public-brand-text">
                <span class="brand-name">COMECyT</span>
                <span class="brand-sub">Control de Solicitudes</span>
            </div>
        </div>
        <nav class="public-nav" style="display: flex; align-items: center; gap: 1rem;">
            <?php if (!empty($solicitanteNombre)): ?>
                <div style="font-size: 0.9rem; font-weight: 500; color: var(--text-dark);">
                    Hola, <?= esc(explode(' ', $solicitanteNombre)[0]) ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($_SESSION['admin_id'])): ?>
                <a href="<?= BASE_URL ?>admin/dashboard.php" class="btn btn-outline btn-sm">
                    <i class="fa-solid fa-shapes"></i> Panel de Admin
                </a>
            <?php endif; ?>
        </nav>
    </header>
    <?php endif; ?>

    <?php if ($exito): ?>
    <!-- Pantalla de exito -->
    <div class="card success-screen visible" style="text-align: center; padding: 48px 32px; max-width: 520px; margin: 0 auto;">
        <div style="width: 72px; height: 72px; border-radius: 50%; background: rgba(22,163,74,0.15); display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 2rem; color: #4ADE80;">
            <i class="fa-solid fa-circle-check"></i>
        </div>
        <h2 style="font-size: 1.3rem; font-weight: 700; margin-bottom: 8px;">Solicitud registrada</h2>
        <p class="text-muted" style="margin-bottom: 24px; font-size: 0.9rem;">
            Su solicitud fue enviada exitosamente. Conserve su folio para dar seguimiento.
        </p>
        <div class="folio-display">
            <div class="folio-label">Numero de Folio</div>
            <div class="folio-value"><?= esc($folioNuevo) ?></div>
        </div>
        <div style="margin-top: 28px; display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
            <a href="<?= BASE_URL ?>public/consulta.php?folio=<?= urlencode($folioNuevo) ?>"
               class="btn btn-outline">
                <i class="fa-solid fa-magnifying-glass"></i>
                Rastrear solicitud
            </a>
            <a href="<?= BASE_URL ?>public/index.php" class="btn btn-primary">
                <i class="fa-solid fa-plus"></i>
                Nueva solicitud
            </a>
        </div>
    </div>

    <?php else: ?>
    <!-- Formulario -->
    <div class="public-hero reveal-up" style="text-align: center; padding: 40px 20px 20px;">
        <img src="<?= BASE_URL ?>assets/MARCA.png" alt="Logo COMECyT" style="max-width: 260px; width: 100%; height: auto; margin: 0 auto 24px; display: block; filter: drop-shadow(0 0 30px rgba(177, 154, 109, 0.15));">
        <h2 style="font-size: 2rem; color: var(--color-primary); font-weight: 700; margin-bottom: 8px;">Registrar Solicitud</h2>
        <p style="color: #64748b; font-size: 1.1rem;">Complete el formulario y recibirá un folio de seguimiento de inmediato.</p>
    </div>

    <?php if (!empty($errores)): ?>
    <div class="alert alert-error reveal-up" style="max-width: 820px; margin: 0 auto 20px;">
        <div>
            <i class="fa-solid fa-triangle-exclamation"></i>
            <strong>Corrija los siguientes errores:</strong>
            <ul style="margin-top: 8px; padding-left: 20px;">
                <?php foreach ($errores as $err): ?>
                <li><?= esc($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <?php endif; ?>

    <style>
        .premium-card {
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid #f1f5f9;
            box-shadow: 0 10px 30px rgba(0,0,0,0.03);
            margin-bottom: 24px;
            overflow: hidden;
            transition: transform 0.4s ease, box-shadow 0.4s ease;
        }
        .premium-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.06);
        }
        .premium-card .card-header border-bottom {
            border-color: #f1f5f9;
        }
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
        .responsive-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        @media (max-width: 768px) {
            .responsive-grid-2 {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <form method="POST" action="" enctype="multipart/form-data" novalidate>
        <?= csrfField() ?>
        <!-- 1. Tipo de solicitud -->
        <div class="premium-card reveal-up" style="transition-delay: 0.1s;">
            <div class="card-header" style="border-bottom: 1px solid #f1f5f9; padding: 20px;">
                <h3 class="card-title" style="color: #1e293b; font-size: 1.25rem;">
                    <i class="fa-solid fa-tag text-primary"></i>
                    Tipo de Solicitud <span style="color: #F87171; margin-left: 3px;">*</span>
                </h3>
            </div>
            <div class="type-grid" style="padding: 20px;">
                <?php
                $tiposConfig = [
                    'mantenimiento' => ['icono' => 'fa-wrench', 'desc' => 'Infraestructura física y equipo'],
                    'atencion'      => ['icono' => 'fa-headset', 'desc' => 'Atención a usuarios'],
                    'soporte'       => ['icono' => 'fa-laptop-code', 'desc' => 'Soporte técnico o informático'],
                    'administracion'=> ['icono' => 'fa-folder-open', 'desc' => 'Trámites y procesos administrativos'],
                    'sistemas'      => ['icono' => 'fa-globe', 'desc' => 'Sistemas y páginas web (adjuntos permitidos)'],
                ];
                foreach ($tiposConfig as $valor => $cfg):
                    $seleccionado = (postParam('tipo') === $valor || ($datos['tipo'] ?? '') === $valor) ? 'selected' : '';
                ?>
                <div class="type-option <?= $seleccionado ?>"
                     data-tipo="<?= $valor ?>"
                     onclick="seleccionarTipo('<?= $valor ?>')">
                    <div class="type-icon">
                        <i class="fa-solid <?= $cfg['icono'] ?>"></i>
                    </div>
                    <span class="type-label"><?= esc(getEtiqueta('tipo', $valor)) ?></span>
                    <span class="type-desc"><?= esc($cfg['desc']) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <input type="hidden" name="tipo" id="campoTipo" value="<?= esc($datos['tipo'] ?? '') ?>">
        </div>

        <!-- 2. Datos del solicitante -->
        <div class="premium-card reveal-up" style="transition-delay: 0.2s;">
            <div class="card-header" style="border-bottom: 1px solid #f1f5f9; padding: 20px;">
                <h3 class="card-title" style="color: #1e293b; font-size: 1.25rem;">
                    <i class="fa-solid fa-user text-primary"></i>
                    Datos del Solicitante
                </h3>
            </div>
            <div class="card-body" style="background-color: var(--bg-body); padding: 20px;">
                <p class="text-muted" style="margin-bottom: 16px; font-size: 0.9rem;">
                    <i class="fa-solid fa-circle-info"></i> Estos datos se obtienen automáticamente de tu sesión activa.
                </p>
                <div class="responsive-grid-2">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Nombre Completo</label>
                        <input type="text" class="form-control" value="<?= esc($solicitanteNombre) ?>" readonly style="background-color: #f8fafc; cursor: not-allowed; border-color: #e2e8f0;">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Área de Adscripción</label>
                        <input type="text" class="form-control" value="<?= esc($solicitanteArea) ?>" readonly style="background-color: #f8fafc; cursor: not-allowed; border-color: #e2e8f0;">
                    </div>
                </div>
                <div class="responsive-grid-2" style="margin-top: 16px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Correo Electrónico</label>
                        <input type="email" class="form-control" value="<?= esc($solicitanteEmail) ?>" readonly style="background-color: #f8fafc; cursor: not-allowed; border-color: #e2e8f0;">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" for="prioridad">Prioridad de Atención</label>
                        <select name="prioridad" id="prioridad" class="form-control">
                            <?php foreach (ETIQUETAS_PRIORIDAD as $val => $lbl): ?>
                            <option value="<?= $val ?>" <?= ($datos['prioridad'] ?? 'media') === $val ? 'selected' : '' ?>>
                                <?= esc($lbl) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECCION DINÁMICA: VINCULACIÓN DE EQUIPO (MANTENIMIENTO) -->
        <div id="seccion_equipo" class="premium-card reveal-up" style="display: none; border-left: 4px solid var(--color-primary); transition-delay: 0.3s;">
            <div class="card-header" style="border-bottom: 1px solid #f1f5f9; padding: 20px;">
                <h3 class="card-title" style="color: var(--color-primary); font-size: 1.25rem;">
                    <i class="fa-solid fa-laptop-medical"></i> Información del Equipo Involucrado
                </h3>
            </div>
            <div class="card-body" style="background-color: var(--bg-body); padding: 20px;">
                <div style="display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap;">
                    <?php if(!empty($_SESSION['user_id']) || !empty($_SESSION['admin_id'])): ?>
                    <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                        <input type="radio" name="opcion_equipo" value="seleccionar_equipo" onchange="toggleFormularioEquipo()" checked>
                        <span style="font-weight: 500;">Seleccionar mi equipo asignado</span>
                    </label>
                    <?php endif; ?>
                    <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                        <input type="radio" name="opcion_equipo" value="registrar_equipo" onchange="toggleFormularioEquipo()" <?= (empty($_SESSION['user_id']) && empty($_SESSION['admin_id'])) ? 'checked' : '' ?>>
                        <span style="font-weight: 500;">Registrar un equipo nuevo</span>
                    </label>
                </div>

                <!-- CAJA: Seleccionar Equipo -->
                <div id="caja_seleccionar_equipo" style="display: <?= (!empty($_SESSION['user_id']) || !empty($_SESSION['admin_id'])) ? 'block' : 'none' ?>;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" for="equipo_existente">Inventario Asignado a tu Persona</label>
                        <select id="equipo_existente" name="equipo_existente" class="form-control">
                            <option value="">-- Elige un equipo --</option>
                            <?php
                                // Fetch user's equipments along with their personal data
                                $cve_personal = $_SESSION['user_id'] ?? ($_SESSION['admin_id'] ?? 0);
                                if ($cve_personal > 0) {
                                    $stmtEq = $pdo->prepare("SELECT b.cve_bienes, b.marca, b.modelo, b.num_serie, b.estatus_alta, p.nombre, p.appat, p.apmat FROM sb_bienes b LEFT JOIN cat_personal p ON b.resguardatario = p.cve_personal WHERE b.resguardatario = ?");
                                    $stmtEq->execute([$cve_personal]);
                                    while ($eq = $stmtEq->fetch()) {
                                        $tag = $eq['estatus_alta'] === 'pendiente' ? ' (Pendiente Autorizar)' : '';
                                        $nombreAsignado = trim(($eq['nombre'] ?? '') . ' ' . ($eq['appat'] ?? '') . ' ' . ($eq['apmat'] ?? ''));
                                        $lbl = "{$nombreAsignado} - {$eq['marca']} {$eq['modelo']} - S/N: {$eq['num_serie']}{$tag}";
                                        echo "<option value=\"{$eq['cve_bienes']}\">" . esc($lbl) . "</option>";
                                    }
                                }
                            ?>
                        </select>
                    </div>
                </div>

                <!-- CAJA: Registrar Nuevo Equipo -->
                <div id="caja_registrar_equipo" style="display: <?= (empty($_SESSION['user_id']) && empty($_SESSION['admin_id'])) ? 'block' : 'none' ?>;">
                    <div class="responsive-grid-2">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" for="eq_marca">Marca / Fabricante <span class="required">*</span></label>
                            <input type="text" id="eq_marca" name="eq_marca" class="form-control" placeholder="Ej. HP, Dell, Apple">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" for="eq_modelo">Modelo <span class="required">*</span></label>
                            <input type="text" id="eq_modelo" name="eq_modelo" class="form-control">
                        </div>
                    </div>
                    <div class="responsive-grid-2" style="margin-top: 16px;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" for="eq_num_serie">Número de Serie</label>
                            <input type="text" id="eq_num_serie" name="eq_num_serie" class="form-control">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" for="eq_num_inventario">No. Inventario (Etiqueta COMECyT)</label>
                            <input type="text" id="eq_num_inventario" name="eq_num_inventario" class="form-control">
                        </div>
                    </div>
                    <p style="font-size: 0.85rem; color: #DC2626; margin-top: 1rem;"><i class="fa-solid fa-clock-rotate-left"></i> El equipo será reportado informáticamente con <strong>estatus pendiente</strong> hasta que TI valide su existencia en oficinas.</p>
                </div>
            </div>
        </div>

        <!-- SECCION DINÁMICA: SUBTIPO + ADJUNTOS (SISTEMAS / WEB) -->
        <div id="seccion_sistemas" class="premium-card reveal-up" style="display: none; border-left: 4px solid #8b5cf6; transition-delay: 0.3s;">
            <div class="card-header" style="border-bottom: 1px solid #f1f5f9; padding: 20px;">
                <h3 class="card-title" style="color: #8b5cf6; font-size: 1.25rem;">
                    <i class="fa-solid fa-globe"></i> Especificación del Requerimiento Web / Sistema
                </h3>
            </div>
            <div class="card-body" style="background-color: var(--bg-body); padding: 20px;">

                <!-- ① SELECTOR DE SUBTIPO (pills) -->
                <div class="form-group" style="margin-bottom: 24px;">
                    <label class="form-label" style="display:block; margin-bottom: 10px;">
                        Tipo de requerimiento <span style="color:#F87171">*</span>
                    </label>
                    <div style="display: flex; flex-wrap: wrap; gap: 10px;" id="subtipo_pills">
                        <?php
                        $subtiposConfig = [
                            'adjuntar_archivo'        => ['icono' => 'fa-paperclip',    'color' => '#8b5cf6'],
                            'generar_link'            => ['icono' => 'fa-link',          'color' => '#06b6d4'],
                            'actualizar_hipervinculo' => ['icono' => 'fa-pen-to-square', 'color' => '#f59e0b'],
                            'problema_sistema'        => ['icono' => 'fa-bug',           'color' => '#ef4444'],
                        ];
                        foreach ($subtiposConfig as $stVal => $stCfg):
                            $stLabel = ETIQUETAS_SUBTIPO_SISTEMAS[$stVal];
                            $isAct = isset($_POST['subtipo_sistemas']) && $_POST['subtipo_sistemas'] === $stVal;
                        ?>
                        <label class="subtipo-pill <?= $isAct ? 'active' : '' ?>" data-subtipo="<?= $stVal ?>" style="border-color: <?= $stCfg['color'] ?>;">
                            <input type="radio" name="subtipo_sistemas" value="<?= $stVal ?>" style="display:none;" <?= $isAct ? 'checked' : '' ?>>
                            <i class="fa-solid <?= $stCfg['icono'] ?>" style="color: <?= $stCfg['color'] ?>;"></i>
                            <span><?= esc($stLabel) ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- ② ADJUNTOS: visible solo cuando subtipo = adjuntar_archivo -->
                <div id="caja_adjuntos_sistema" style="display: none;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" for="archivos_adjuntos">
                            <i class="fa-solid fa-paperclip"></i> Subir Archivos de Referencia
                            <span class="text-muted" style="font-weight:400; font-size:0.85rem;"> (puedes seleccionar varios)</span>
                        </label>
                        <input type="file" id="archivos_adjuntos_input" class="form-control"
                               accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg" multiple>
                        <p class="form-hint">Tipos permitidos: Word, Excel, PDF, PNG, JPG. Máximo sugerido 5 MB por archivo.</p>
                        <!-- Preview dinámico con botón eliminar por archivo -->
                        <div id="archivos_preview" style="margin-top: 10px; display: flex; flex-wrap: wrap; gap: 8px;"></div>
                    </div>
                </div>

                <!-- ③ CAMPO DE ENLACE: visible cuando subtipo = generar_link -->
                <div id="caja_link_sistema" style="display: none;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" for="url_referencia">
                            <i class="fa-solid fa-link"></i> URL o Enlace de Referencia
                        </label>
                        <input type="url" id="url_referencia" name="url_referencia" class="form-control"
                               placeholder="https://ejemplo.comecyt.gob.mx/pagina" maxlength="500">
                        <p class="form-hint">Indica la URL del enlace o página afectada. (Opcional pero ayuda a priorizar)</p>
                    </div>
                </div>

                <!-- ④ ACTUALIZAR HIPERVÍNCULOS: documento adjunto -->
                <div id="caja_hipervinculos" style="display: none;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" for="hipervinculo_archivo_input">
                            <i class="fa-solid fa-pen-to-square" style="color:#f59e0b;"></i> Documento de Hipervínculos a Actualizar
                            <span class="required">*</span>
                        </label>
                        <input type="file" id="hipervinculo_archivo_input" class="form-control"
                               accept=".doc,.docx,.xls,.xlsx,.pdf" multiple required>
                        <p class="form-hint">Anexa el documento (Word, Excel, PDF) con la información detallada de los hipervínculos actuales y por cuáles reemplazarlos.</p>
                        <!-- Preview dinámico con botón eliminar por archivo -->
                        <div id="hipervinculo_archivo_preview" style="margin-top: 10px; display: flex; flex-wrap: wrap; gap: 8px;"></div>
                    </div>
                </div>

                <!-- ⑤ PROBLEMA EN SISTEMA: evidencia con archivos + detalle por archivo -->
                <div id="caja_problema_sistema" style="display: none;">
                    <div class="form-group" style="margin-bottom: 12px;">
                        <label class="form-label">
                            <i class="fa-solid fa-bug" style="color:#ef4444;"></i> Evidencia del Problema
                            <span class="text-muted" style="font-weight:400; font-size:0.85rem;"> (capturas de pantalla, logs, etc.)</span>
                        </label>
                        <input type="file" id="problema_archivos_input" class="form-control"
                               accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg,.gif,.mp4,.webp" multiple>
                        <p class="form-hint">Sube capturas, documentos o vídeos de evidencia. Puedes agregar varios archivos y eliminar los que no necesites.</p>
                        <!-- Preview con botón eliminar -->
                        <div id="problema_archivos_preview" style="margin-top: 10px; display: flex; flex-direction:column; gap: 8px;"></div>
                    </div>
                </div>

            </div>
        </div>

        <!-- 3. Descripcion -->
        <div class="premium-card reveal-up" style="transition-delay: 0.4s;">
            <div class="card-header" style="border-bottom: 1px solid #f1f5f9; padding: 20px;">
                <h3 class="card-title" style="color: #1e293b; font-size: 1.25rem;">
                    <i class="fa-solid fa-align-left text-primary"></i>
                    Descripción de la Solicitud
                </h3>
            </div>
            <div class="form-group" style="margin-bottom: 0; padding: 20px; background-color: var(--bg-body);">
                <label class="form-label" for="descripcion">
                    Detalle el motivo y lo que se requiere <span class="required">*</span>
                </label>
                <textarea id="descripcion" name="descripcion" class="form-control" rows="6"
                          placeholder="Describa con el mayor detalle posible lo que necesita, el contexto y cualquier información relevante para atender su solicitud de manera eficiente."
                          required minlength="20" style="border-color: #cbd5e1;"><?= esc($datos['descripcion'] ?? '') ?></textarea>
                <p class="form-hint" style="margin-top: 8px;">Mínimo 20 caracteres.</p>
            </div>
        </div>

        <div class="reveal-up" style="text-align: center; transition-delay: 0.5s;">
            <button type="submit" class="btn btn-primary btn-lg" style="box-shadow: 0 4px 15px rgba(102, 35, 49, 0.2); padding: 12px 32px; font-size: 1.1rem; border-radius: 12px;">
                <i class="fa-solid fa-paper-plane"></i>
                Enviar Solicitud Institucional
            </button>
        </div>
    </form>
    <?php endif; ?>

</div><!-- /.public-main -->

<script src="<?= BASE_URL ?>assets/js/app.js?v=2.0"></script>
<script>
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
});
/**
 * UI Dinámica — Formulario de Nueva Solicitud
 * Controla: selector de tipo, sección de equipo, sección Sistemas/Web.
 *
 * Mejoras v2:
 *  - Adjuntar Archivo:  chips removibles (DataTransfer)
 *  - Hipervínculos:     pares URL actual → nueva, dinámicos
 *  - Problema Sistema:  multi-archivo con detalle por archivo
 */
const seccionEquipo   = document.getElementById('seccion_equipo');
const cajaSeleccionar = document.getElementById('caja_seleccionar_equipo');
const cajaRegistrar   = document.getElementById('caja_registrar_equipo');
const seccionSistemas = document.getElementById('seccion_sistemas');
const cajaAdjuntos    = document.getElementById('caja_adjuntos_sistema');
const cajaLink        = document.getElementById('caja_link_sistema');
const cajaHiper       = document.getElementById('caja_hipervinculos');
const cajaProblema    = document.getElementById('caja_problema_sistema');

// ── Seleccion principal de tipo ───────────────────────────────────
function toggleFormularioEquipo() {
    const tipo = document.getElementById('campoTipo').value;

    seccionSistemas.style.display = (tipo === 'sistemas') ? 'block' : 'none';
    if (tipo !== 'sistemas') resetSubtipoSistemas();

    if (tipo === 'mantenimiento') {
        seccionEquipo.style.display = 'block';
        const radioActivo = document.querySelector('input[name="opcion_equipo"]:checked');
        const opcion = radioActivo ? radioActivo.value : '';
        cajaSeleccionar.style.display = (opcion === 'seleccionar_equipo') ? 'block' : 'none';
        cajaRegistrar.style.display   = (opcion === 'registrar_equipo')   ? 'block' : 'none';
        document.getElementById('eq_marca').required  = (opcion === 'registrar_equipo');
        document.getElementById('eq_modelo').required = (opcion === 'registrar_equipo');
    } else {
        seccionEquipo.style.display = 'none';
        document.getElementById('eq_marca').required  = false;
        document.getElementById('eq_modelo').required = false;
    }
}

function seleccionarTipo(valor) {
    document.querySelectorAll('.type-option').forEach(el => el.classList.remove('selected'));
    const opcion = document.querySelector(`.type-option[data-tipo="${valor}"]`);
    if (opcion) opcion.classList.add('selected');
    document.getElementById('campoTipo').value = valor;
    toggleFormularioEquipo();
}

// ── Lógica de Subtipo Sistemas ────────────────────────────────
function resetSubtipoSistemas() {
    document.querySelectorAll('.subtipo-pill').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('input[name="subtipo_sistemas"]').forEach(r => r.checked = false);
    cajaAdjuntos.style.display  = 'none';
    cajaLink.style.display      = 'none';
    cajaHiper.style.display     = 'none';
    cajaProblema.style.display  = 'none';
}

// =================================================================
//  UTILIDADES: Chip de archivo removible (DataTransfer pattern)
// =================================================================

/**
 * Renderiza chips con botón eliminar para un file input.
 * @param {HTMLInputElement} input       - El input[type=file] nativo
 * @param {HTMLElement}      previewBox  - Contenedor donde van los chips
 * @param {object}           opts        - {color, showDetail, detailName}
 */
function renderFileChips(input, previewBox, opts) {
    const color      = opts.color || '#8b5cf6';
    const showDetail = opts.showDetail || false;
    const detailName = opts.detailName || 'archivo_detalle';
    
    // Guardar valores actuales de los textareas para no perderlos al re-renderizar
    const textareas = previewBox.querySelectorAll('textarea');
    const valoresActuales = Array.from(textareas).map(ta => ta.value);

    previewBox.innerHTML = '';
    const files = input.files;

    if (!files.length) return;

    Array.from(files).forEach((f, idx) => {
        const row = document.createElement('div');
        row.style.cssText = 'display:flex; flex-direction:column; gap:6px;';

        // ─ Chip con ícono + nombre + botón ✕ ─
        const chip = document.createElement('div');
        chip.style.cssText = `display:inline-flex;align-items:center;gap:8px;
            background:rgba(${hexToRgb(color)},0.1);border:1px solid rgba(${hexToRgb(color)},0.35);
            color:${color};border-radius:20px;padding:5px 8px 5px 12px;font-size:0.8rem;
            max-width:100%;`;

        const icon = getFileIcon(f.name);
        const sizeMB = (f.size / (1024 * 1024)).toFixed(2);

        chip.innerHTML = `
            <i class="fa-solid ${icon}" style="font-size:0.75rem; flex-shrink:0;"></i>
            <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1;">${escHtml(f.name)}</span>
            <span style="font-size:0.7rem;color:#6b7280;flex-shrink:0;">${sizeMB} MB</span>
        `;

        // Botón eliminar
        const btnDel = document.createElement('button');
        btnDel.type = 'button';
        btnDel.title = 'Eliminar archivo';
        btnDel.style.cssText = `display:flex;align-items:center;justify-content:center;
            width:20px;height:20px;border-radius:50%;border:none;
            background:rgba(239,68,68,0.15);color:#ef4444;font-size:0.7rem;
            cursor:pointer;flex-shrink:0;margin-left:2px;`;
        btnDel.innerHTML = '<i class="fa-solid fa-xmark"></i>';
        btnDel.addEventListener('click', () => removeFileAt(input, previewBox, idx, opts));
        chip.appendChild(btnDel);
        row.appendChild(chip);

        // ─ Campo de detalle opcional (para "problema en sistema") ─
        if (showDetail) {
            const ta = document.createElement('textarea');
            // Usar un key seguro basado en el nombre del archivo para mapear en PHP sin errores de indice
            const safeKey = f.name.replace(/[^a-zA-Z0-9]/g, '_');
            ta.name = detailName + '[' + safeKey + ']';
            
            ta.placeholder = `Detalla qué muestra este archivo (${f.name})…`;
            ta.rows = 2;
            ta.maxLength = 500;
            ta.style.cssText = `width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;
                background:#f9fafb;color:#111827;font-size:0.82rem;outline:none;
                resize:vertical;font-family:inherit;box-sizing:border-box;`;
            
            // Restaurar el texto previamente escrito, si existe
            if (valoresActuales[idx] !== undefined) {
                ta.value = valoresActuales[idx];
            }
            
            row.appendChild(ta);
        }

        previewBox.appendChild(row);
    });
}

/**
 * Elimina un archivo individual de un input[type=file] usando DataTransfer.
 */
function removeFileAt(input, previewBox, indexToRemove, opts) {
    // Al eliminar un archivo, primero quitamos su valor del mapa temporal
    const textareas = previewBox.querySelectorAll('textarea');
    if (textareas.length > indexToRemove) {
        textareas[indexToRemove].value = ''; // limpiar para que no se herede
        // Evitamos usar array.splice directo en el DOM; mejor clonamos valores limpios
        textareas[indexToRemove].remove(); 
    }

    const dt = new DataTransfer();
    Array.from(input.files).forEach((f, i) => {
        if (i !== indexToRemove) dt.items.add(f);
    });
    input.files = dt.files;
    renderFileChips(input, previewBox, opts);
}

function getFileIcon(name) {
    const ext = name.split('.').pop().toLowerCase();
    const map = {
        pdf:'fa-file-pdf', doc:'fa-file-word', docx:'fa-file-word',
        xls:'fa-file-excel', xlsx:'fa-file-excel',
        png:'fa-file-image', jpg:'fa-file-image', jpeg:'fa-file-image',
        gif:'fa-file-image', webp:'fa-file-image',
        mp4:'fa-file-video', avi:'fa-file-video',
    };
    return map[ext] || 'fa-file';
}

function hexToRgb(hex) {
    hex = hex.replace('#','');
    const r = parseInt(hex.substring(0,2),16);
    const g = parseInt(hex.substring(2,4),16);
    const b = parseInt(hex.substring(4,6),16);
    return `${r},${g},${b}`;
}

function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// =================================================================
/* CÓDIGO ANTERIOR: Funciones para filas dinámicas (Comentadas por solicitud)
let hiperCounter = 0;

function agregarFilaHipervinculo() {
    const lista = document.getElementById('hipervinculos_lista');
    const idx = hiperCounter++;
    const row = document.createElement('div');
    row.style.cssText = `display:flex; flex-wrap:wrap; gap:8px; align-items:flex-start;
        padding:12px; background:#fffbeb; border:1px solid rgba(245,158,11,0.25);
        border-radius:10px;`;
    row.dataset.hiperIdx = idx;

    row.innerHTML = `
        <div style="flex:1; min-width:200px;">
            <label style="display:block;font-size:0.75rem;font-weight:600;color:#92400e;margin-bottom:4px;">
                URL Actual
            </label>
            <input type="url" name="hiper_url_actual[]" placeholder="https://actual.ejemplo.com/pagina"
                   maxlength="500" style="width:100%;padding:7px 10px;border:1px solid #fbbf24;
                   border-radius:8px;background:#fff;color:#111827;font-size:0.82rem;outline:none;box-sizing:border-box;">
        </div>
        <div style="display:flex;align-items:center;padding-top:20px;color:#d97706;font-size:1rem;">
            <i class="fa-solid fa-arrow-right"></i>
        </div>
        <div style="flex:1; min-width:200px;">
            <label style="display:block;font-size:0.75rem;font-weight:600;color:#065f46;margin-bottom:4px;">
                URL Nueva
            </label>
            <input type="url" name="hiper_url_nueva[]" placeholder="https://nueva.ejemplo.com/pagina"
                   maxlength="500" style="width:100%;padding:7px 10px;border:1px solid #34d399;
                   border-radius:8px;background:#fff;color:#111827;font-size:0.82rem;outline:none;box-sizing:border-box;">
        </div>
        <button type="button" onclick="eliminarFilaHipervinculo(this)" title="Eliminar"
                style="display:flex;align-items:center;justify-content:center;
                       width:28px;height:28px;border-radius:50%;border:none;
                       background:rgba(239,68,68,0.12);color:#ef4444;font-size:0.8rem;
                       cursor:pointer;margin-top:20px;flex-shrink:0;">
            <i class="fa-solid fa-trash-can"></i>
        </button>
    `;
    lista.appendChild(row);
}

function eliminarFilaHipervinculo(btn) {
    const row = btn.closest('[data-hiper-idx]');
    if (row) row.remove();
    // Siempre dejar al menos 1 fila
    const lista = document.getElementById('hipervinculos_lista');
    if (lista.children.length === 0) agregarFilaHipervinculo();
}
*/
// =================================================================

// =================================================================
//  DOMContentLoaded: inicializar todos los listeners
// =================================================================
document.addEventListener('DOMContentLoaded', () => {

    // ── Pills de subtipo ────────────────────────────────────────
    document.querySelectorAll('.subtipo-pill').forEach(pill => {
        pill.addEventListener('click', () => {
            document.querySelectorAll('.subtipo-pill').forEach(p => p.classList.remove('active'));
            pill.classList.add('active');

            const radio = pill.querySelector('input[type="radio"]');
            if (radio) radio.checked = true;
            const subtipo = pill.dataset.subtipo;

            // Quitar name[] de todos los inputs de archivo para evitar colisión en POST
            const inAdjunto = document.getElementById('archivos_adjuntos_input');
            const inProb    = document.getElementById('problema_archivos_input');
            const inHiper   = document.getElementById('hipervinculo_archivo_input');
            if (inAdjunto) inAdjunto.removeAttribute('name');
            if (inProb)    inProb.removeAttribute('name');
            if (inHiper)   inHiper.removeAttribute('name');
            
            // Remover 'required' dinámico al ocultar
            if (inAdjunto) inAdjunto.required = false;
            if (inProb)    inProb.required = false;
            if (inHiper)   inHiper.required = false;

            // Ocultar todas las subsecciones, luego mostrar la que corresponde
            cajaAdjuntos.style.display  = 'none';
            cajaLink.style.display      = 'none';
            cajaHiper.style.display     = 'none';
            cajaProblema.style.display  = 'none';

            if (subtipo === 'adjuntar_archivo') {
                cajaAdjuntos.style.display = 'block';
                if (inAdjunto) inAdjunto.setAttribute('name', 'archivos_adjuntos[]');
            }
            else if (subtipo === 'generar_link') {
                cajaLink.style.display = 'block';
            }
            else if (subtipo === 'actualizar_hipervinculo') {
                cajaHiper.style.display = 'block';
                if (inHiper) {
                    inHiper.setAttribute('name', 'archivos_adjuntos[]');
                }
                
                /* CÓDIGO ANTERIOR:
                if (document.getElementById('hipervinculos_lista') && document.getElementById('hipervinculos_lista').children.length === 0) {
                    agregarFilaHipervinculo();
                }
                */
            }
            else if (subtipo === 'problema_sistema') {
                cajaProblema.style.display = 'block';
                if (inProb) inProb.setAttribute('name', 'archivos_adjuntos[]');
            }
        });
    });

    // ── Adjuntar Archivo: preview con chips removibles ──────────
    const fileInput  = document.getElementById('archivos_adjuntos_input');
    const previewBox = document.getElementById('archivos_preview');
    if (fileInput && previewBox) {
        const optsAdj = { color: '#8b5cf6', showDetail: false };
        fileInput.addEventListener('change', () => renderFileChips(fileInput, previewBox, optsAdj));
    }

    // ── Problema Sistema: preview con chips + detalle ───────────
    const probInput  = document.getElementById('problema_archivos_input');
    const probPreview = document.getElementById('problema_archivos_preview');
    if (probInput && probPreview) {
        const optsProb = { color: '#ef4444', showDetail: true, detailName: 'archivo_detalle' };
        probInput.addEventListener('change', () => renderFileChips(probInput, probPreview, optsProb));
    }

    // ── Actualizar Hipervínculos: preview con chips removibles ─
    const hiperInput   = document.getElementById('hipervinculo_archivo_input');
    const hiperPreview = document.getElementById('hipervinculo_archivo_preview');
    if (hiperInput && hiperPreview) {
        const optsHiper = { color: '#f59e0b', showDetail: false };
        hiperInput.addEventListener('change', () => renderFileChips(hiperInput, hiperPreview, optsHiper));
    }

    /* CÓDIGO ANTERIOR: Event listener del botón
    const btnHiper = document.getElementById('btnAgregarHipervinculo');
    if (btnHiper) btnHiper.addEventListener('click', agregarFilaHipervinculo);
    */

    // ── Estado inicial (recargas con error) ──────────────────────
    if (document.getElementById('campoTipo').value) toggleFormularioEquipo();
    
    // Si ya había un subtipo seleccionado (por un $_POST previo con error de validación)
    const stChecked = document.querySelector('input[name="subtipo_sistemas"]:checked');
    if (stChecked) {
        // En lugar de hacer click (que podría resetear otras variables), 
        // simulamos el evento para mostrar la caja correspondiente
        stChecked.closest('label').dispatchEvent(new Event('click'));
    }
});
</script>
</div>

<?php if ($usuariologueado): ?>
    <?php require_once __DIR__ . '/../includes/footer_user.php'; ?>
<?php else: ?>
    </body>
    </html>
<?php endif; ?>
