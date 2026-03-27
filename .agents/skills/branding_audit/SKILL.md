# Skill: Branding Audit
Auditoría visual y técnica de cumplimiento de guías de estilo institucionales.

## Instrucciones de Uso
Cuando se realicen cambios en las cabeceras o el diseño global, ejecutar esta auditoría para asegurar consistencia.

## Checklist de Verificación
1. **Pestañas**: Verificar que `<title>` termine en `| COMECyT Intranet`.
2. **Icono**: Verificar que exista `<link rel="icon" ... MARCA.png">`.
3. **Colores**: Comprobar que se usen las variables `--color-primary` (#662331) y `--color-secondary` para elementos de acento.
4. **Resposividad**: Las tablas deben usar `.table-premium` y los grids `.intranet-grid` o `.responsive-grid-2`.

## Archivos Críticos a Revisar
- `includes/header_admin.php`
- `includes/header_user.php`
- `includes/header_ss.php`
- `admin/login.php`
