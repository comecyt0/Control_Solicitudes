# Auditoría de Seguridad y Arquitectura
**COMECyT - Control de Solicitudes Internas**

Como desarrollador web senior, he analizado la estructura, el código fuente (PHP/JS/CSS), y los flujos de datos (PRG) del sistema. El sistema tiene una base sólida y funcional, pero requiere refactorizaciones puntuales para alcanzar un estándar profesional de nivel **Enterprise/Producción**, especialmente en áreas de ciberseguridad y mantenibilidad.

 A continuación, presento mi auditoría clasificada por criticidad.

---

## 🛡️ 1. Vulnerabilidades Críticas (Resueltas y Parcheadas)

Durante la auditoría inicial se detectaron 4 vulnerabilidades severas que **ya han sido parcheadas** e implementadas en producción:

### A. Credenciales de Base de Datos (Mitigado)
- **Problema Original:** Credenciales en texto plano (`DB_USER`, `DB_PASS`) en `database.php`.
- **Solución Implementada:** Implementación de un archivo `.env` en el directorio raíz. Las configuraciones de acceso ahora se extraen de forma segura y dinámica con un `EnvParser` (`$_ENV`), manteniendo los secretos 100% aislados del repositorio de código fuente.

### B. Protección CSRF Global (Mitigado)
- **Problema Original:** Carencia de Tokens anti-falsificación en peticiones POST.
- **Solución Implementada:** Se programó un generador criptográfico `$_SESSION['csrf_token']` y la variable helper `csrfField()`. Todos los módulos administrativos, registros públicos, endpoints de chat AJAX, e interceptores en `auth.php` y `helpers.php` ahora ejecutan estrictamente `validarCsrfPost()`, blindando el aplicativo contra ataques Cross-Site Request Forgery.

### C. Rate Limiting Anti-Fuerza Bruta (Mitigado)
- **Problema Original:** Carencia de protecciones contra intentos de logueo masivos.
- **Solución Implementada:** Se inyectó un motor Rate Limiter en `auth.php`. Tras 5 intentos de inicio de sesión fallidos continuos en `login.php`, el sistema devuelve un status `429 Too Many Requests` y expulsa bloqueando cualquier acceso por 5 minutos enteros (`bloqueo_login_hasta`).

### D. Ejecución de Código Remoto (RCE) en Uploads (Mitigado)
- **Problema Original:** Riesgo de subida de Web Shells encubiertos con doble extensión en evidencias HTTP.
- **Solución Implementada:** Inyección de un archivo `.htaccess` restrictivo en la carpeta raíz de adjuntos (`public/uploads/solicitudes/`). Se apagó expresamente la funcionalidad `mod_php`, inhabilitando la ejecución de _cualquier_ archivo en esa ruta y forzando la interpretación de firmas dudosas como simple `text/plain`.

---

## ⚠️ 2. Puntos de Mejora Arquitectónica (Prioridad Media)

### A. Enrutador y Front Controller (Single Point of Entry)
- **Hallazgo:** El sistema utiliza docenas de archivos `.php` físicos individuales como rutas (`index.php`, `consulta.php`, `login.php`, `detalle.php`).
- **Problema:** Si queremos añadir un Middleware global (ej. verificación de Mantenimiento de Servidor, compresión GZIP, etc.), tenemos que inyectarlo manualmente en 20 archivos.
- **Mejor Práctica:** Transicionar a un Front Controller (todo redirige mediante `.htaccess` a un único `index.php` principal del aplicativo), el cual despacha la "Vista" o "Controlador" de forma dinámica, ocultando rutas reales y centralizando el Bootstraping de la seguridad.

### B. Autoloader Orientado a Objetos (Composer & PSR-4)
- **Hallazgo:** Hay muchas referencias sueltas con `require_once __DIR__ . '/.../archivo.php'`. Funciones procedimentales acumuladas en `helpers.php` y `auth.php`.
- **Problema:** Crecimiento inescalable (Spaghetti Code a largo plazo).
- **Mejor Práctica:** Refactorizar módulos clave (Autenticación, Database, Mailer) hacia CLASES orientadas a objetos (OOP) y apoyarse en **Autoloading** con namespaces (`use App\Controllers\AdminController`). 

### C. Alertas de JS vs Notificaciones Flash Modernas
- **Hallazgo:** El sistema inyecta HTML directo o alertas crudas vía Javascript al redireccionar vía PRG.
- **Mejor Práctica:** Estandarizar una librería de Web Notifications (ej. SweetAlert2 nativo o Toastr importado dinámicamente) y canalizarlas eficientemente desde el Backend pasando variables semánticas `$_SESSION['toast'] = ['type' => 'success', 'msg' => 'Guardado']`.

---

## 🟢 3. Aciertos Analizados (Lo que está muy bien hecho)

A pesar de los detalles a refactorizar, el sistema ya posee **marcas de madurez ingenieril muy sólidas**:

1. **Evacuación FOUC y Caching:** La refactorización del Loader Global autónomo (inyectando su propio `<style>` inline) para mitigar el caché duro es un insight de Senior Engineering magistral.
2. **Defensa contra SQL Injection:** Toda interacción analizada usa `$pdo->prepare()` eficientemente. No hay concatenaciones vulnerables en los queries base.
3. **Persistencia de Eventos Back-Forward Cache:** Uso elegante de los events de la API de ventanas DOM (`pageshow`, `beforeunload`) para atrapar UX friction.
4. **Almacenamiento JSON Flexibilidad:** Uso de arreglos JSON nativos (`json_encode`) en MaríaDB para los multíples `archivos_adjuntos[]` de Evidencias de Sistemas, evitando la sobresaturación y JOINs en tablas pivote innecesarias para casos de baja relatividad.
5. **Mitigación XSS (Parcial):** La inyección de la función global `esc()` como wrapper de `htmlspecialchars` en el Backend en las Vistas públicas es esencial y correcta.

---

El aplicativo "funciona", pero si este sistema va a convivir en servidores de Gobierno (con alta observancia de seguridad e inspección pública), mi recomendación arquitectónica es tomar un **sprint dedicado exclusivo** (sin añadir módulos web nuevos) a "tapar túneles" (CSRF, Brute Force limiters, dotenv de BD). Reflexiona estos puntos; con estas pinceladas pasará de ser un producto "operativo" a ser "Software Grado Institucional".

---

## 🚨 2. Nueva Auditoría de Base de Datos Estructural (`bd_sisibic`)

He realizado un perfilamiento Senior sobre el volcado SQL de la base de datos `bd_sisibic`. Se han detectado **patrones sumamente peligrosos** y "Red Flags" de arquitectura que ponen en riesgo severo a toda la institución y deben ser corregidos de inmediato:

### A. Almacenamiento de Contraseñas Gubernamentales en Base64 (Nivel de Riesgo: CRÍTICO EXTREMO)
- **Hallazgo:** Las tablas `det_sistemainterno` y `det_sistemasgem` están funcionando como una bóveda de contraseñas de servidores gubernamentales físicos (ej. 10.10.x.x), bases de datos Oracle (PDB1.mx1.ocm.s7208131...), SQL Server, y otros sistemas externos. Las columnas `passwd_servidor` y `password` no están encriptadas, sino simplemente ofuscadas en **Base64** (ej. la supuesta contraseña segura `YWRtaW5fMTIz` es simplemente `admin_123`).
- **Peligro:** Base64 NO es un mecanismo de encriptación, es simplemente codificación. Cualquier atacante o empleado malicioso que pueda hacer un volcado de esta tabla obtendrá en texto plano las credenciales maestras de redes y VPNs de toda la institución, causando un colapso gubernamental.
- **Acción Obligatoria:** Implementar un algoritmo fuerte de **Encripción Simétrica (AES-256-GCM)**. La aplicación backend debe encriptar el texto con una llave maestra (almacenada en un `.env` fuera del servidor web) antes de hacer el `INSERT` y desencriptar `AES_DECRYPT` al consultarlo. ¡JAMÁS almacenar credenciales IP en Base64!

### B. Motor de Almacenamiento Obsoleto (MyISAM)
- **Hallazgo:** Múltiples tablas operativas (`cat_doc_fase`, `det_bienes`, `det_sistemainterno`) están creadas forzando `ENGINE=MyISAM`.
- **Peligro:** MyISAM es una tecnología legada, profundamente obsoleta e inestable. Carece de soporte para "Transacciones ACID" (si falla un query a la mitad, la BD se corrompe) y ejecuta bloqueos a nivel de tabla entera (*Table-level locking*), aniquilando el rendimiento en concurrencia. Además, MyISAM **no soporta Restricciones de Clave Foránea (Foreign Keys)**, lo que explica la existencia de registros huérfanos.
- **Acción Obligatoria:** Ejecutar un comando masivo de migración: `ALTER TABLE nombre_tabla ENGINE = InnoDB;` en absolutamente todas las tablas.

### C. Ausencia absoluta de Integridad Referencial (Foreign Keys)
- **Hallazgo:** A pesar de haber desactivado la validación en el dump (`FOREIGN_KEY_CHECKS=0`), el esquema de diseño carece por completo de `CONSTRAINT FOREIGN KEY`. Las uniones se hacen solo de "plabra", por lo que no hay protecciones de Cascada (`ON DELETE CASCADE`).
- **Peligro:** Si alguien borra la fase "2" de `cat_fases_sistemas`, todos los documentos que referenciaban a la fase 2 se quedarán huérfanos viviendo en la BD como basura digital para siempre.
- **Acción Obligatoria:** Declarar invariablemente los *Constraints* en cada relación.

### D. Uso de Tablas Basura (Residuales en Producción)
- **Hallazgo:** Existe una tabla llamada `det_bienes_ui_copy` llena de registros en producción. 
- **Peligro:** En un sistema profesional, los volcados temporales ensucian el Entity-Relationship (ERD), complican los mantenimientos y exponen datos. Si era un backup o prueba, no debe vivir en el esquema maestro en rama productiva.

### E. Uso Antipatrón de Tipos de Datos y Fechas Nulas
- **Hallazgo:**
  1. Tipos de dato sobredimensionados: Se usa extensivamente la declaración estática `TEXT` para columnas diminutas como `marca`, `modelo`, `dd` en computadoras. El motor de BD prohíbe crear índices rápidos completos sobre tipo Text. Se debe reemplazar por `VARCHAR(100)` u optimizados.
  2. Fechas Nulas Hardcodeadas: Existe proliferación del valor `0000-00-00` en columnas date. Los motores MySQL modernos (>= 8.0) con configuración de seguridad `STRICT_TRANS_TABLES` arrojarán errores letales (Crasheo) al intentar insertar un `0000-00-00`. Deben reestructurarse a admitir valores nativos `NULL`.
  3. Validación deficiente: Presencia de valores sucios como `CURP` ='000000000000000000' para usuarios legítimos, demostrando una deficiencia en la validación Front-End (donde el usuario metió ceros para saltarse el candado).
