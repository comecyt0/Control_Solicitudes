# Proyecto: COMECyT — Intranet Institucional

## 🎯 Visión General
Sistema integral de gestión de solicitudes, agenda institucional, comunicación interna y repositorio multimedia para el COMECyT. Diseñado bajo una arquitectura de "Intranets Departamentales" (19 áreas) orquestadas por un Hub Global de alto impacto visual.

### Objetivos Clave:
- Digitalización de procesos administrativos.
- Comunicación en tiempo real (Chat Híbrido).
- Gestión de evidencias y transparencia institucional.
- Planeación estratégica mediante calendarios editoriales e institucionales.

---

## 💻 Comandos Clave
Para garantizar la estabilidad del entorno Docker en Windows (sin Bind-Mount dinámico), es obligatorio seguir estos protocolos:

- **Despliegue Completo**: `docker compose up -d --build app` (Obligatorio para aplicar cambios en PHP/CSS/JS).
- **Control de Servicios**: `docker compose up -d`, `docker compose stop`.
- **Base de Datos (PSQL)**: `docker exec -it comecyt_db psql -U comecyt_user -d bd_sisibic`
- **Sincronización Git**: `git add . ; git commit -m "mensaje" ; git push` (Usar `;` en PowerShell).

---

## 🎨 Guías de Estilo & UX
- **Estética Premium**: Uso de **Glassmorphism**, bordes `20px+`, sombras profundas (`0 15px 45px rgba(0,0,0,0.15)`) y desenfoque de fondo.
- **Tipografía**: Familia 'Inter' (300 a 700).
- **Iconografía**: FontAwesome 6 (Sólidos y Regulares).
- **Animaciones**: Máximo respeto a la fluidez. Usar `IntersectionObserver` para reveals (`.reveal-up`). Prohibido: Rotaciones de 360° o pulsos agresivos.
- **Branding**: Títulos de pestaña con sufijo `| COMECyT Intranet`. Favicon institucional obligatorio (`assets/MARCA.png`).

---

## 🧠 Contexto Importante (Arquitectura)
- **Ruteo Centralizado**: `public/router.php` es el director de tráfico. Mapea `cve_area` a slugs de carpetas físicas en `/areas/`.
- **Autenticación Híbrida**: Soporta `admin_id` y `user_id` (Personal). Centralizado en `config/auth.php`.
- **Chat Multi-tenant**: Aislado por `cve_area` y persistido en `sb_chat_lectura` (notificaciones proactivas).
- **Constantes de Ruta**: Usar siempre `ROOT` para subidas (`move_uploaded_file`) y `BASE_URL` para enlaces front-end.
- **Timezone**: Siempre `America/Mexico_City` tanto en PHP como en PostgreSQL.

---

## 📂 Errores y Soluciones (Memory Log)

### 🚨 Errores de Infraestructura y Docker
- **Cambios no se ven**: En Windows, no hay bind-mount. **Solución**: Reconstruir imagen con `--build app`.
- **Rutas de Upload fallidas**: `dirname(__DIR__, N)` varía según la profundidad del script. **Solución**: Usar la constante global `ROOT`.
- **PowerShell `&&` Error**: El operador `&&` no existe en PS. **Solución**: Usar `;` para separar comandos.

### 🔐 Seguridad y Sesiones
- **Loop de Redirección (Auth)**: Causado por headers HTTP 302 conflictivos. **Solución**: Redirección por Meta-Refresh en HTML.
- **CSRF en APIs JSON**: `validarCsrfPost()` emite HTML y rompe el JSON. **Solución**: Validación inline (`$_POST['csrf_token'] === $_SESSION['csrf_token']`).
- **PHP Tag Missing**: Omitir `<?php` al inicio tras una edición automática. **Solución**: Verificar siempre la etiqueta de apertura en reemplazos de bloque.

### 🖼️ UI/UX y Layout
- **Desfasado de Títulos en Alertas**: Títulos absolutos tapan la imagen. **Solución**: Mover títulos a contenedores externos (footer/header) fuera del frame de la imagen.
- **Avatares Gigantes**: Para el diseño "Wow" de 300px, usar `margin` correctivo para no romper el grid de 3 columnas del Hero.
- **Truncado de Calendario**: Flex-items no se limitan solos. **Solución**: Usar `min-width: 0` + `overflow: hidden` en celdas de calendario.

---

## 🤖 Subagentes & IA
- **Agente IA Local**: Integración con Ollama (`host.docker.internal:11434`).
- **Capacidades**: Generación de código, auditoría de seguridad y asistencia en chat administrativo.
- **Workflows Disponibles**:
  - `/system_sync`: Sincronización completa de Docker y Git.
  - `/branding_check`: (Propuesto) Verificación de cumplimiento de guías de estilo.
