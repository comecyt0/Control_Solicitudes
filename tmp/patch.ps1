$baseDir = "c:\Intranet"
$files = @(
    "admin\calendario.php",
    "areas\administrativo\calendario.php",
    "areas\apoyo_investigacion\calendario.php",
    "areas\archivo\calendario.php",
    "areas\desarrollo_tecnologico\calendario.php",
    "areas\desarrollo_vinculacion\calendario.php",
    "areas\difusion\calendario.php",
    "areas\direccion_general\calendario.php",
    "areas\financiamiento\calendario.php",
    "areas\financiamiento_divulgacion\calendario.php",
    "areas\formacion_rrhh\calendario.php",
    "areas\investigacion_cientifica\calendario.php",
    "areas\juridico\calendario.php",
    "areas\juridico_igualdad\calendario.php",
    "areas\organo_interno\calendario.php",
    "areas\planeacion_evaluacion\calendario.php",
    "areas\quejas\calendario.php",
    "areas\responsabilidades\calendario.php",
    "areas\secretaria_particular\calendario.php",
    "areas\vinculacion\calendario.php",
    "public\calendario.php"
)

foreach ($relative in $files) {
    if (-not (Test-Path "$baseDir\$relative")) { continue }
    $content = Get-Content -Path "$baseDir\$relative" -Raw

    # Avatares SQL
    $content = $content.Replace("SELECT nombre, appat, apmat, fecha_nacimiento, foto_perfil, foto_perfil", "SELECT TRIM(CONCAT(nombre, ' ', COALESCE(appat, ''), ' ', COALESCE(apmat, ''))) AS nombre, fecha_nacimiento, foto_perfil")
    $content = $content.Replace("SELECT nombre, appat, apmat, fecha_nacimiento, foto_perfil", "SELECT TRIM(CONCAT(nombre, ' ', COALESCE(appat, ''), ' ', COALESCE(apmat, ''))) AS nombre, fecha_nacimiento, foto_perfil")
    $content = $content.Replace("SELECT nombre, fecha_nacimiento, foto_perfil FROM cat_personal", "SELECT TRIM(CONCAT(nombre, ' ', COALESCE(appat, ''), ' ', COALESCE(apmat, ''))) AS nombre, fecha_nacimiento, foto_perfil FROM cat_personal")
    $content = $content.Replace("SELECT nombre, fecha_nacimiento, foto_perfil FROM ss_usuarios", "SELECT TRIM(CONCAT(nombre, ' ', COALESCE(appat, ''), ' ', COALESCE(apmat, ''))) AS nombre, fecha_nacimiento, foto_perfil FROM ss_usuarios")

    # Fix Nombre completo parsing
    $content = $content.Replace("`$nombreCompleto = trim(`$cp['nombre'] . ' ' . `$cp['appat'] . ' ' . `$cp['apmat']);", "`$nombreCompleto = trim(`$cp['nombre']);")
    
    # Eventos Data Isolation (Public o por Area)
    $content = $content.Replace(
        "INSERT INTO eventos (titulo, descripcion, fecha_inicio, fecha_fin, color, creado_por, publico) VALUES (?, ?, ?, ?, ?, ?, `$publico)",
        "INSERT INTO eventos (titulo, descripcion, fecha_inicio, fecha_fin, color, creado_por, publico, cve_area) VALUES (?, ?, ?, ?, ?, ?, `$publico, ?)"
    )
    $content = $content.Replace(
        "execute([`$titulo, `$descripcion, `$fechaInicio, `$fechaFin, `$color, `$_SESSION['admin_id']])",
        "execute([`$titulo, `$descripcion, `$fechaInicio, `$fechaFin, `$color, `$_SESSION['admin_id'], `$_SESSION['admin_cve_area'] ?? `$_SESSION['user_cve_area'] ?? 0])"
    )

    $content = $content.Replace(
        "`"SELECT * FROM eventos WHERE fecha_inicio < ? AND fecha_fin > ? ORDER BY fecha_inicio ASC`"",
        "`"SELECT * FROM eventos WHERE fecha_inicio < ? AND fecha_fin > ? AND (publico = TRUE OR cve_area = `" . (`$_SESSION['admin_cve_area'] ?? `$_SESSION['user_cve_area'] ?? 0) . `") ORDER BY fecha_inicio ASC`""
    )

    # Kanban Data Isolation
    $content = $content.Replace(
        "`"SELECT t.*, a.nombre AS asignado_nombre FROM sb_kanban_tareas t LEFT JOIN administradores a ON t.asignado_a = a.id ORDER BY t.estatus DESC, t.id DESC`"",
        "`"SELECT t.*, a.nombre AS asignado_nombre FROM sb_kanban_tareas t LEFT JOIN administradores a ON t.asignado_a = a.id WHERE t.cve_area = `" . (`$_SESSION['admin_cve_area'] ?? `$_SESSION['user_cve_area'] ?? 0) . `" ORDER BY t.estatus DESC, t.id DESC`""
    )

    $content = $content.Replace(
        "INSERT INTO sb_kanban_tareas (titulo, descripcion, color, estatus, creado_por, asignado_a) VALUES (?, ?, ?, 'pendiente', ?, ?)",
        "INSERT INTO sb_kanban_tareas (titulo, descripcion, color, estatus, creado_por, asignado_a, cve_area) VALUES (?, ?, ?, 'pendiente', ?, ?, ?)"
    )
    $content = $content.Replace(
        "execute([`$titulo, `$descripcion, `$color, `$_SESSION['admin_id'], `$asignado_a])",
        "execute([`$titulo, `$descripcion, `$color, `$_SESSION['admin_id'], `$asignado_a, `$_SESSION['admin_cve_area'] ?? `$_SESSION['user_cve_area'] ?? 0])"
    )

    Set-Content -Path ("$baseDir\$relative") -Value $content -NoNewline
}
Write-Output "Done"
