# Skill: Gestión de Personal y Estructura Directiva

Instrucciones para la gestión avanzada de personal, jefaturas y áreas gestionadas.

## 🏗️ Estructura de Liderazgo
- **Líderes**: Usuarios con `rol_jefatura` (`jefe_departamento`, `director_area`).
- **Subordinados**: Vinculados mediante la columna `jefe_directo_id` que apunta al `cve_personal` del líder.
- **Áreas Gestionadas**: Se derivan dinámicamente en el panel administrativo (`personal.php`) mediante un `STRING_AGG` de las áreas de sus subordinados.

## 💻 Consultas SQL Clave
Para obtener el equipo de un líder:
```sql
SELECT * FROM cat_personal WHERE jefe_directo_id = :id_lider;
```

Para listar áreas gestionadas por un líder:
```sql
SELECT DISTINCT a.des_area 
FROM cat_personal p 
JOIN cat_areas a ON p.cve_area = a.cve_area 
WHERE p.jefe_directo_id = :id_lider;
```

## 🎨 UI/UX para Líderes
- Usar badges específicos para roles.
- Mostrar el bloque "Gestión de Áreas" con fondo sutil (`rgba(109, 40, 217, 0.05)`) y borde izquierdo para jerarquía visual.
- Incluir acceso rápido a "Gestionar Operativos" (modal `abrirModalOperativos`).

## 🚨 Consideraciones de Rendimiento
En listados masivos, preferir subconsultas agregadas (`STRING_AGG`) en lugar de loops de consulta N+1 en PHP.
