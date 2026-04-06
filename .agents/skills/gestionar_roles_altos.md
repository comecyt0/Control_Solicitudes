---
description: Cómo consultar y mostrar roles de Alta Dirección (jefe_departamento, director_area)
---

# Gestión de Roles de Alta Dirección

Al extender cualquier módulo del sistema Intranet que requiera mostrar el estatus jerárquico de los usuarios, es importante incluir los nuevos identificadores visuales de autoridad:

### 1. Extracción de Base de Datos
Al realizar consultas a `cat_personal` (`u`), siempre incluye tanto `rol_jefatura` como `nombre_jefatura`:

```sql
SELECT u.cve_personal, u.nombre, u.appat, u.rol_jefatura, u.nombre_jefatura
FROM cat_personal u
```

### 2. Mostrar Badge Institucional en Tablas (UI)
Utiliza este fragmento HTML/PHP para asegurar el renderizado correcto del Badge púrpura (`#6D28D9`) de FontAwesome para directores:

```php
<?php if (!empty($usr['rol_jefatura'])): ?>
    <div style="margin-top: 5px;">
        <span class="badge" style="background: rgba(109, 40, 217, 0.1); color: #6D28D9; border: 1px solid rgba(109, 40, 217, 0.2); font-size: 0.75rem;">
            <i class="fa-solid <?= $usr['rol_jefatura'] === 'director_area' ? 'fa-user-tie' : 'fa-user-shield' ?>"></i>
            <?= esc(ucwords(str_replace('_', ' ', $usr['rol_jefatura']))) ?>
        </span>
    </div>
<?php endif; ?>
```

Esto consolida de una forma limpia los cargos asignativos sin comprometer la tabla jerárquica de `cve_area`.
