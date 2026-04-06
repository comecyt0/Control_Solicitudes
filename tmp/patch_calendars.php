<?php
$base_dir = '/var/www/html';
$directory = new RecursiveDirectoryIterator($base_dir);
$iterator  = new RecursiveIteratorIterator($directory);
$regex     = new RegexIterator($iterator, '/^.+calendario\.php$/i', RecursiveRegexIterator::GET_MATCH);

$count = 0;
foreach ($regex as $file) {
    if (strpos($file[0], 'tmp/') !== false) continue;
    
    $fpath = $file[0];
    $content = file_get_contents($fpath);
    $original = $content;

    // 1. Avatars Fix
    $content = str_replace(
        "SELECT nombre, appat, apmat, fecha_nacimiento, foto_perfil, foto_perfil",
        "SELECT TRIM(CONCAT(nombre, ' ', COALESCE(appat, ''), ' ', COALESCE(apmat, ''))) AS nombre, fecha_nacimiento, foto_perfil",
        $content
    );
    $content = str_replace(
        "SELECT nombre, appat, apmat, fecha_nacimiento, foto_perfil",
        "SELECT TRIM(CONCAT(nombre, ' ', COALESCE(appat, ''), ' ', COALESCE(apmat, ''))) AS nombre, fecha_nacimiento, foto_perfil",
        $content
    );
    $content = str_replace(
        "SELECT nombre, fecha_nacimiento, foto_perfil FROM cat_personal",
        "SELECT TRIM(CONCAT(nombre, ' ', COALESCE(appat, ''), ' ', COALESCE(apmat, ''))) AS nombre, fecha_nacimiento, foto_perfil FROM cat_personal",
        $content
    );
    $content = str_replace(
        "SELECT nombre, fecha_nacimiento, foto_perfil FROM ss_usuarios",
        "SELECT TRIM(CONCAT(nombre, ' ', COALESCE(appat, ''), ' ', COALESCE(apmat, ''))) AS nombre, fecha_nacimiento, foto_perfil FROM ss_usuarios",
        $content
    );
    $content = str_replace(
        "\$nombreCompleto = trim(\$cp['nombre'] . ' ' . \$cp['appat'] . ' ' . \$cp['apmat']);",
        "\$nombreCompleto = trim(\$cp['nombre']);",
        $content
    );

    // 2. Eventos Isolation
    if (strpos($content, "INSERT INTO eventos ") !== false && strpos(explode("INSERT INTO eventos ", $content)[1] ?? "", "cve_area") === false) {
        $content = str_replace(
            "INSERT INTO eventos (titulo, descripcion, fecha_inicio, fecha_fin, color, creado_por, publico) VALUES (?, ?, ?, ?, ?, ?, \$publico)",
            "INSERT INTO eventos (titulo, descripcion, fecha_inicio, fecha_fin, color, creado_por, publico, cve_area) VALUES (?, ?, ?, ?, ?, ?, \$publico, ?)",
            $content
        );
        $content = str_replace(
            "execute([\$titulo, \$descripcion, \$fechaInicio, \$fechaFin, \$color, \$_SESSION['admin_id']])",
            "execute([\$titulo, \$descripcion, \$fechaInicio, \$fechaFin, \$color, \$_SESSION['admin_id'], \$_SESSION['admin_cve_area'] ?? \$_SESSION['user_cve_area'] ?? 0])",
            $content
        );
    }
    
    if (strpos($content, "\"SELECT * FROM eventos WHERE fecha_inicio < ? AND fecha_fin > ? ORDER BY fecha_inicio ASC\"") !== false) {
        $content = str_replace(
            "\"SELECT * FROM eventos WHERE fecha_inicio < ? AND fecha_fin > ? ORDER BY fecha_inicio ASC\"",
            "\"SELECT * FROM eventos WHERE fecha_inicio < ? AND fecha_fin > ? AND (publico = TRUE OR cve_area = \" . (\$_SESSION['admin_cve_area'] ?? \$_SESSION['user_cve_area'] ?? 0) . \") ORDER BY fecha_inicio ASC\"",
            $content
        );
    }

    // 3. Kanban Isolation
    if (strpos($content, "\"SELECT t.*, a.nombre AS asignado_nombre FROM sb_kanban_tareas t LEFT JOIN administradores a ON t.asignado_a = a.id ORDER BY t.estatus DESC, t.id DESC\"") !== false) {
        $content = str_replace(
            "\"SELECT t.*, a.nombre AS asignado_nombre FROM sb_kanban_tareas t LEFT JOIN administradores a ON t.asignado_a = a.id ORDER BY t.estatus DESC, t.id DESC\"",
            "\"SELECT t.*, a.nombre AS asignado_nombre FROM sb_kanban_tareas t LEFT JOIN administradores a ON t.asignado_a = a.id WHERE t.cve_area = \" . (\$_SESSION['admin_cve_area'] ?? \$_SESSION['user_cve_area'] ?? 0) . \" ORDER BY t.estatus DESC, t.id DESC\"",
            $content
        );
    }

    if (strpos($content, "INSERT INTO sb_kanban_tareas ") !== false && strpos(explode("INSERT INTO sb_kanban_tareas ", $content)[1] ?? "", "cve_area") === false) {
        $content = str_replace(
            "INSERT INTO sb_kanban_tareas (titulo, descripcion, color, estatus, creado_por, asignado_a) VALUES (?, ?, ?, 'pendiente', ?, ?)",
            "INSERT INTO sb_kanban_tareas (titulo, descripcion, color, estatus, creado_por, asignado_a, cve_area) VALUES (?, ?, ?, 'pendiente', ?, ?, ?)",
            $content
        );
        $content = str_replace(
            "execute([\$titulo, \$descripcion, \$color, \$_SESSION['admin_id'], \$asignado_a])",
            "execute([\$titulo, \$descripcion, \$color, \$_SESSION['admin_id'], \$asignado_a, \$_SESSION['admin_cve_area'] ?? \$_SESSION['user_cve_area'] ?? 0])",
            $content
        );
    }

    if ($original !== $content) {
        file_put_contents($fpath, $content);
        $count++;
    }
}
echo "Patched $count files.\n";
