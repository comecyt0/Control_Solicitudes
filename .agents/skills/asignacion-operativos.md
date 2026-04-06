---
description: Cómo obtener los operadores/subordinados y la jerarquía desde el jefe directo
---

# Asignación y Control de Operativos (`jefe_directo_id`)

EL sistema COMECyT utiliza un modelo adyacente para establecer jerarquías operativas entre personal.
Cuando un usuario possee un identificador jerárquico directivo (e.g. `jefe_departamento`, `director_area`), otros empleados pueden ser subodinados a su mando directamente en la columna `jefe_directo_id` referenciando su llave primaria.

## Patrón de Extracción:

Para obtener a todos los empleados o subordinados de un Jefe en particular para listarlos:

```sql
SELECT u.cve_personal, u.nombre, u.appat, u.apmat, u.correo_institucional, a.des_area
FROM cat_personal u
LEFT JOIN cat_areas a ON u.cve_area = a.cve_area
WHERE u.jefe_directo_id = :id_jefe AND u.activo = 1
ORDER BY u.nombre ASC;
```

A nivel backend, la herramienta estándar provee la API interna asíncrona: `admin/api/operativos.php?jefe_id=ID`. Esta retornará un `json_encode` estructurado para que visualices relaciones de pertenencia por medio del campo homónimo. 

Para editar masivamente los miembros del equipo, se utiliza PRG action `_accion = asignar_operativos` de HTML forms.

## UI

Los cuadros de asignación están resueltos mediante un Modal lateral dinámico que lee desde AJAX (`fetch()`) y recarga la UI en Javascript puro para no saturar el DOM con miles de nodos si la base de datos es muy grande de antemano.
