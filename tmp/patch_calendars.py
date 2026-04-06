import os
import glob
import re

base_dir = r"c:\Intranet"
files = glob.glob(os.path.join(base_dir, "**", "calendario.php"), recursive=True)

queries_eventos_insert = [
    (r"INSERT INTO eventos \(titulo, descripcion, fecha_inicio, fecha_fin, color, creado_por, publico\) VALUES \(\?, \?, \?, \?, \?, \?, \$publico\)",
     r"INSERT INTO eventos (titulo, descripcion, fecha_inicio, fecha_fin, color, creado_por, publico, cve_area) VALUES (?, ?, ?, ?, ?, ?, $publico, ?)")
]

queries_eventos_select = [
    (r"SELECT \* FROM eventos WHERE fecha_inicio < \? AND fecha_fin > \? ORDER BY fecha_inicio ASC",
     r"SELECT * FROM eventos WHERE fecha_inicio < ? AND fecha_fin > ? AND (publico = TRUE OR cve_area = " . str("$_SESSION['admin_cve_area'] ?? $_SESSION['user_cve_area'] ?? 0"). ") ORDER BY fecha_inicio ASC")
]

queries_kanban_select = [
    (r"SELECT t\.\*, a\.nombre AS asignado_nombre FROM sb_kanban_tareas t LEFT JOIN administradores a ON t\.asignado_a = a\.id ORDER BY t\.estatus DESC, t\.id DESC",
     r"SELECT t.*, a.nombre AS asignado_nombre FROM sb_kanban_tareas t LEFT JOIN administradores a ON t.asignado_a = a.id WHERE t.cve_area = " . str("$_SESSION['admin_cve_area'] ?? $_SESSION['user_cve_area'] ?? 0"). " ORDER BY t.estatus DESC, t.id DESC")
]

queries_kanban_insert = [
    (r"INSERT INTO sb_kanban_tareas \(titulo, descripcion, color, estatus, creado_por, asignado_a\) VALUES \(\?, \?, \?, 'pendiente', \?, \?\)",
     r"INSERT INTO sb_kanban_tareas (titulo, descripcion, color, estatus, creado_por, asignado_a, cve_area) VALUES (?, ?, ?, 'pendiente', ?, ?, ?)")
]

for fpath in files:
    with open(fpath, "r", encoding="utf-8") as f:
        content = f.read()

    # 1. Avatars Fix
    content = content.replace(
        "SELECT nombre, appat, apmat, fecha_nacimiento, foto_perfil, foto_perfil",
        "SELECT TRIM(CONCAT(nombre, ' ', COALESCE(appat, ''), ' ', COALESCE(apmat, ''))) AS nombre, fecha_nacimiento, foto_perfil"
    )
    content = content.replace(
        "SELECT nombre, appat, apmat, fecha_nacimiento, foto_perfil",
        "SELECT TRIM(CONCAT(nombre, ' ', COALESCE(appat, ''), ' ', COALESCE(apmat, ''))) AS nombre, fecha_nacimiento, foto_perfil"
    )
    content = content.replace(
        "SELECT nombre, fecha_nacimiento, foto_perfil FROM cat_personal",
        "SELECT TRIM(CONCAT(nombre, ' ', COALESCE(appat, ''), ' ', COALESCE(apmat, ''))) AS nombre, fecha_nacimiento, foto_perfil FROM cat_personal"
    )
    content = content.replace(
        "SELECT nombre, fecha_nacimiento, foto_perfil FROM ss_usuarios",
        "SELECT TRIM(CONCAT(nombre, ' ', COALESCE(appat, ''), ' ', COALESCE(apmat, ''))) AS nombre, fecha_nacimiento, foto_perfil FROM ss_usuarios"
    )
    
    # Render Fix (Nombre Completo to just Nombre since SQL handles it)
    content = content.replace(
        "$nombreCompleto = trim($cp['nombre'] . ' ' . $cp['appat'] . ' ' . $cp['apmat']);",
        "$nombreCompleto = trim($cp['nombre']);"
    )

    # 2. Eventos Isolation
    # Insert
    if "INSERT INTO eventos " in content and "cve_area" not in content.split("INSERT INTO eventos ")[1].split(")")[0]:
        content = content.replace(
            "INSERT INTO eventos (titulo, descripcion, fecha_inicio, fecha_fin, color, creado_por, publico) VALUES (?, ?, ?, ?, ?, ?, $publico)",
            "INSERT INTO eventos (titulo, descripcion, fecha_inicio, fecha_fin, color, creado_por, publico, cve_area) VALUES (?, ?, ?, ?, ?, ?, $publico, ?)"
        )
        # Fix execute
        content = content.replace(
            "execute([$titulo, $descripcion, $fechaInicio, $fechaFin, $color, $_SESSION['admin_id']])",
            "execute([$titulo, $descripcion, $fechaInicio, $fechaFin, $color, $_SESSION['admin_id'], $_SESSION['admin_cve_area'] ?? $_SESSION['user_cve_area'] ?? 0])"
        )
    
    # Select
    if "SELECT * FROM eventos WHERE fecha_inicio < ?" in content and "publico = TRUE OR cve_area =" not in content:
        # In double quotes string in PHP we must escape quotes or break string
        content = content.replace(
            "\"SELECT * FROM eventos WHERE fecha_inicio < ? AND fecha_fin > ? ORDER BY fecha_inicio ASC\"",
            "\"SELECT * FROM eventos WHERE fecha_inicio < ? AND fecha_fin > ? AND (publico = TRUE OR cve_area = \" . ($_SESSION['admin_cve_area'] ?? $_SESSION['user_cve_area'] ?? 0) . \") ORDER BY fecha_inicio ASC\""
        )
    
    # 3. Kanban Isolation
    if "SELECT t.*, a.nombre AS asignado_nombre FROM sb_kanban_tareas t" in content and "cve_area" not in content:
        content = content.replace(
            "\"SELECT t.*, a.nombre AS asignado_nombre FROM sb_kanban_tareas t LEFT JOIN administradores a ON t.asignado_a = a.id ORDER BY t.estatus DESC, t.id DESC\"",
            "\"SELECT t.*, a.nombre AS asignado_nombre FROM sb_kanban_tareas t LEFT JOIN administradores a ON t.asignado_a = a.id WHERE t.cve_area = \" . ($_SESSION['admin_cve_area'] ?? $_SESSION['user_cve_area'] ?? 0) . \" ORDER BY t.estatus DESC, t.id DESC\""
        )

    if "INSERT INTO sb_kanban_tareas" in content and "cve_area" not in content.split("INSERT INTO sb_kanban_tareas ")[1].split(")")[0]:
        content = content.replace(
            "INSERT INTO sb_kanban_tareas (titulo, descripcion, color, estatus, creado_por, asignado_a) VALUES (?, ?, ?, 'pendiente', ?, ?)",
            "INSERT INTO sb_kanban_tareas (titulo, descripcion, color, estatus, creado_por, asignado_a, cve_area) VALUES (?, ?, ?, 'pendiente', ?, ?, ?)"
        )
        content = content.replace(
            "execute([$titulo, $descripcion, $color, $_SESSION['admin_id'], $asignado_a])",
            "execute([$titulo, $descripcion, $color, $_SESSION['admin_id'], $asignado_a, $_SESSION['admin_cve_area'] ?? $_SESSION['user_cve_area'] ?? 0])"
        )

    with open(fpath, "w", encoding="utf-8") as f:
        f.write(content)

print(f"Patched scripts in {base_dir}")
