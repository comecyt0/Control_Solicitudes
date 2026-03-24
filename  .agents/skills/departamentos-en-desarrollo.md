---
name: gestion-areas-desarrollo
description: Skill para aplicar y mantener el estado de 'En Desarrollo' en los módulos departamentales. Úsalo cuando necesites bloquear el acceso a datos globales en carpetas de áreas que aún no tienen su funcionalidad específica implementada.
---

# Gestión de Áreas en Desarrollo

## Contexto
El sistema COMECyT utiliza una arquitectura multidepartamental donde cada área tiene su propia carpeta en `areas/{slug}/`. Originalmente, estas carpetas son clones del panel administrativo central (`admin/`), lo que expone datos globales de Sistemas a usuarios de otras áreas.

## Aplicación del Placeholder (Banner)
Para marcar un área como "En Desarrollo", el archivo `areas/{slug}/dashboard.php` debe ser reemplazado por la plantilla institucional de "En Desarrollo".

### Plantilla `dashboard.php` Estándar:
```php
<?php
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../includes/helpers.php";
require_once __DIR__ . "/../../config/auth.php";

verificarSesionAdmin();

$pageTitle  = "Área en Desarrollo";
$activeMenu = "dashboard";

require_once __DIR__ . "/../../includes/header_admin.php";
?>
<div class="dev-banner-container">
   <!-- ... Contenido del banner con botón al Hub ... -->
</div>
<?php require_once __DIR__ . "/../../includes/footer.php"; ?>
```

## Cortafuegos de Ingeniería (Routing Firewall)
El archivo `includes/header_admin.php` contiene la lógica centralizada para bloquear el acceso a otros archivos operativos (`solicitudes.php`, `reportes.php`, etc.) en estas áreas.

### Selección de Áreas Funcionales
La variable `$areas_funcionales` en `header_admin.php` define qué slugs tienen acceso completo a sus herramientas:
```php
$areas_funcionales = ['sistemas', 'difusion'];
```

### Lógica de Redirección
Si un área no está en la lista de funcionales, cualquier acceso a un archivo distinto de `dashboard.php` dentro de `/areas/` resultará en una redirección forzada al dashboard local.

## Sidebar Dinámico
El menú lateral filtra las opciones de gestión basándose en el `$slug_menu`. Para áreas en desarrollo, solo se muestra el Dashboard y un mensaje de "Más herramientas pronto".

## Proceso de Activación de un Área
Cuando un área está lista para producción:
1.  Implementar la funcionalidad específica en sus respectivos archivos.
2.  Agregar el slug del área al array `$areas_funcionales` en `includes/header_admin.php`.
3.  Definir su menú lateral específico en `includes/header_admin.php` (dentro del bloque `switch` o `if/else`).
