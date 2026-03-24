<?php
/**
 * COMECyT Control de Solicitudes
 * API Pública — Mi Perfil — v4.0
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../config/auth.php';

header('Content-Type: application/json');

// 1. Identificar Rol
$rol = null;
$id_actor = null;
$email_actor = null;

if (!empty($_SESSION['admin_id'])) {
    $rol = 'admin';
    $id_actor = $_SESSION['admin_id'];
    $email_actor = $_SESSION['admin_email'] ?? null;
} elseif (!empty($_SESSION['user_id'])) {
    $rol = 'usuario';
    $id_actor = $_SESSION['user_id'];
    $email_actor = $_SESSION['user_email'] ?? null;
} elseif (!empty($_SESSION['ss_id'])) {
    $rol = 'servicio_social';
    $id_actor = $_SESSION['ss_id'];
} else {
    echo json_encode(['ok' => false, 'error' => 'Sesión expirada o inválida.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || postParam('accion') !== 'actualizar') {
    echo json_encode(['ok' => false, 'error' => 'Petición inválida.']);
    exit;
}

if (!validarCsrfPost()) {
    echo json_encode(['ok' => false, 'error' => 'Error de seguridad (CSRF). Intenta recargar la página.']);
    exit;
}

$pdo = getConnection();

// 2. Extraer parámetros seguros
$nombre = trim(postParam('nombre'));
$cve_area = (int)postParam('cve_area');
$fecha_nacimiento = postParam('fecha_nacimiento'); // Formato YYYY-MM-DD
$foto_nombre = null;

if (!$nombre || !$fecha_nacimiento) {
    echo json_encode(['ok' => false, 'error' => 'Nombre y Fecha de Nacimiento son obligatorios.']);
    exit;
}

if ($cve_area <= 0 && $rol !== 'servicio_social') {
    echo json_encode(['ok' => false, 'error' => 'Área inválida.']);
    exit;
}

// 3. Procesar Subida de Foto (Avatar)
if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] !== UPLOAD_ERR_NO_FILE) {
    $file = $_FILES['foto_perfil'];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['ok' => false, 'error' => 'Error subiendo la imagen. Intenta otra.']);
        exit;
    }
    
    if ($file['size'] > 5 * 1024 * 1024) {
        echo json_encode(['ok' => false, 'error' => 'La imagen supera los 5MB.']);
        exit;
    }
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    $mimesAceptados = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    
    if (!isset($mimesAceptados[$mime])) {
        echo json_encode(['ok' => false, 'error' => 'Solo se permiten imágenes JPG, PNG o WEBP.']);
        exit;
    }
    
    $ext = $mimesAceptados[$mime];
    $foto_nombre = bin2hex(random_bytes(16)) . '.' . $ext;
    
    $ruta_avatares = ROOT . '/public/uploads/avatares';
    if (!is_dir($ruta_avatares)) {
        @mkdir($ruta_avatares, 0775, true);
    }
    
    $ruta_destino = $ruta_avatares . '/' . $foto_nombre;
    
    if (!@move_uploaded_file($file['tmp_name'], $ruta_destino)) {
        echo json_encode(['ok' => false, 'error' => 'Error de escritura en servidor. Intenta temporalmente sin foto.']);
        exit;
    }
}

// 4. Lógica de Guardado (Directo vs Temporal)
try {
    $pdo->beginTransaction();
    
    if ($rol === 'admin') {
        // Los admins se actualizan directamente
        // 1. Actualizar administradores
        $stmtA = $pdo->prepare("UPDATE administradores SET nombre = ? WHERE id = ?");
        $stmtA->execute([$nombre, $id_actor]);
        
        // 2. Actualizar cat_personal
        if ($email_actor) {
            $sqlP = "UPDATE cat_personal SET nombre = ?, cve_area = ?, fecha_nacimiento = ?";
            $paramsP = [$nombre, $cve_area, $fecha_nacimiento];
            if ($foto_nombre) {
                $sqlP .= ", foto_perfil = ?";
                $paramsP[] = $foto_nombre;
            }
            $sqlP .= " WHERE correo_institucional = ? OR correo_personal = ?";
            $paramsP[] = $email_actor;
            $paramsP[] = $email_actor;
            
            $stmtP = $pdo->prepare($sqlP);
            $stmtP->execute($paramsP);
            
            // Si el admin acaba de registrar su foto y no estaba, lo guardamos o creamos
            // Asumiremos que si no se actualizó nada, es porque no existe en cat_personal.
            if ($stmtP->rowCount() === 0) {
                 $stmtI = $pdo->prepare("INSERT INTO cat_personal (nombre, correo_institucional, cve_area, fecha_nacimiento, foto_perfil, password_hash, activo, cve_estatus) VALUES (?, ?, ?, ?, ?, 'n/a', TRUE, 1)");
                 $stmtI->execute([$nombre, $email_actor, $cve_area, $fecha_nacimiento, $foto_nombre]);
            }
        }
        
    } elseif ($rol === 'usuario') {
        // Los usuarios se van a revisión
        $cambios = [
            'nombre' => $nombre,
            'cve_area' => $cve_area,
            'fecha_nacimiento' => $fecha_nacimiento
        ];
        if ($foto_nombre) {
            $cambios['foto_perfil'] = $foto_nombre;
        }
        
        $json_cambios = json_encode($cambios, JSON_UNESCAPED_UNICODE);
        
        $stmtP = $pdo->prepare("UPDATE cat_personal SET perfil_en_revision = TRUE, cambios_tmp = ? WHERE cve_personal = ?");
        $stmtP->execute([$json_cambios, $id_actor]);
        
    } elseif ($rol === 'servicio_social') {
        // Servicio Social se va a revisión
        $cambios = [
            'nombre' => $nombre,
            'fecha_nacimiento' => $fecha_nacimiento
        ];
        if ($foto_nombre) {
            $cambios['foto_perfil'] = $foto_nombre;
        }
        $json_cambios = json_encode($cambios, JSON_UNESCAPED_UNICODE);
        
        $stmtSS = $pdo->prepare("UPDATE ss_usuarios SET perfil_en_revision = TRUE, cambios_tmp = ? WHERE id = ?");
        $stmtSS->execute([$json_cambios, $id_actor]);
    }

    $pdo->commit();
    echo json_encode(['ok' => true, 'msg' => ($rol === 'admin' ? 'Perfil actualizado directamente.' : 'Los cambios han sido enviados a revisión institucional.')]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Error guardando perfil: " . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Ocurrió un error en la base de datos. Intente más tarde.']);
}
