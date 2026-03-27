# COMECyT — Control de Solicitudes Internas
**Sistema de Gestión de Soporte TI e Intranet | v3.1 | Marzo 2026**

> Sistema institucional de control de tickets de soporte, inventario TI, gestión de personal y colaboración interna para el **Consejo Mexiquense de Ciencia y Tecnología (COMECyT)**.

---

## 🚀 Inicio Rápido (Docker — Recomendado)

```bash
# Requisitos: Docker Desktop instalado
git clone <repo> Intranet
cd Intranet
cp .env.example .env        # Editar con tus valores reales
docker compose up --build -d
```

| Servicio | URL |
|---|---|
| Portal de Solicitudes | http://localhost:8080/public/ |
| Panel Administrativo | http://localhost:8080/admin/login.php |
| pgAdmin | http://localhost:8081 |

> Para acceso en red local, reemplaza `localhost` por la IP del servidor (ej. `172.30.0.44`).

---

## 📋 Credenciales por Defecto

| Rol | Email | Contraseña |
|-----|-------|------------|
| Super Admin | cmendoza@comecyt.edomex.gob.mx | Admin123! |
| Admin | lsanchez@comecyt.edomex.gob.mx | Admin123! |
| Revisor | rflores@comecyt.edomex.gob.mx | Admin123! |

> ⚠️ **Cambiar todas las contraseñas en el primer inicio de sesión en producción.**

---
## 🗂️ Estructura del Proyecto

```
Intranet/
│
├── admin/                         # Panel administrativo (requiere sesión admin)
│   ├── api/                       # Endpoints AJAX internos (responden JSON)
│   │   ├── agente_ia.php          # Integración Groq AI (LLaMA 3)
│   │   ├── busqueda_global.php    # Búsqueda full-text: tickets + personal + equipos
│   │   ├── cambiar_estatus.php    # Cambia estatus de solicitud + bitácora
│   │   ├── chat.php               # Chat en tiempo real entre administradores
│   │   ├── comentarios.php        # Comentarios privados por ticket
│   │   ├── exportar_pdf.php       # PDF de solicitud individual
│   │   ├── notificacion_email.php # Email al solicitante sobre cambios
│   │   ├── plantillas.php         # CRUD de plantillas de respuesta
│   │   ├── servicio_social.php    # API del módulo de Servicio Social
│   │   └── toggle_darkmode.php    # Persiste preferencia de dark mode
│   ├── login.php                  # Autenticación administradores
│   ├── dashboard.php              # Métricas, gráficas y KPIs
│   ├── solicitudes.php            # Listado con filtros avanzados
│   ├── detalle.php                # Vista completa de un ticket
│   ├── personal.php               # CRUD de empleados (cat_personal)
│   ├── equipos.php                # Inventario de hardware
│   ├── calendario.php             # Calendario de eventos + tablero Kanban
│   ├── correos.php                # Gestión de correos institucionales
│   ├── reportes.php               # Reportes exportables
│   ├── administradores.php        # Gestión de cuentas de administrador
│   ├── servicio_social.php        # Módulo completo de Servicio Social
│   └── export_csv.php             # Exportación CSV de tickets
│
├── public/                        # Portal público (acceso sin autenticación)
│   ├── index.php                  # Alta y envío de solicitudes (con login personal)
│   ├── consulta.php               # Consulta de estatus por folio
│   ├── registro.php               # Alta de cuenta de usuario en el sistema
│   ├── historial.php              # Historial de solicitudes del usuario
│   ├── equipos_usuario.php        # Equipos asignados a mi cuenta
│   └── uploads/                   # Archivos adjuntos (ejecución PHP bloqueada)
│
├── config/
│   ├── database.php               # Conexión PDO singleton + generarFolio()
│   └── auth.php                   # Sesiones, CSRF, rate-limiting, roles
│
├── includes/
│   ├── header_admin.php           # HTML+JS completo del panel admin (sidebar, chat, IA)
│   ├── header_user.php            # Header del portal de usuarios
│   ├── header_ss.php              # Header del módulo de Servicio Social
│   ├── footer.php                 # Footer del panel admin (app.js)
│   ├── footer_user.php            # Footer del portal de usuarios
│   ├── helpers.php                # esc(), badgeEstatus(), csrfField(), constantes
│   ├── help_widget.php            # Widget de ayuda contextual flotante
│   └── loader.php                 # Pantalla de carga global (previene FOUC)
│
├── assets/
│   ├── css/
│   │   ├── main.css               # Sistema de diseño completo (CSS vars, responsive)
│   │   ├── admin_extra.css        # Overrides específicos del panel admin
│   │   └── login.css              # Estilos exclusivos de la pantalla de login
│   ├── js/
│   │   └── app.js                 # JS minimal: sidebar móvil, modales, toasts
│   ├── MARCA.png                  # Logotipo institucional COMECyT
│   └── pantalla_carga.png         # Imagen de pantalla de carga
│
├── database/
│   ├── init.sql                   # Schema PostgreSQL completo + datos iniciales
│   └── migration_history.sql      # Script de migración de 85 registros históricos
│
├── servicio_social/               # Portal independiente del módulo SS
│   ├── dashboard.php              # Panel del becario de SS
│   └── api/                       # Endpoints del módulo SS
│
├── cron/
│   └── recordatorios.php          # Cron job: notificaciones automáticas por email
│
├── docs/                          # Documentación técnica completa
│   ├── README.md                  # Este archivo — inicio rápido y estructura
│   ├── README-DOCKER.md           # Guía Docker completa
│   ├── analisis_sistema.md        # Análisis técnico de infraestructura y componentes
│   ├── arquitectura_y_flujos.md   # Arquitectura, BD, flujos y patrones de desarrollo
│   ├── auditoria_seguridad.md     # Reporte de seguridad e hardening
│   ├── endpoints.md               # Referencia completa de APIs internas
│   └── manual.md                  # Manual de usuario y administrador
│
├── docker/
│   ├── php.ini                    # Configuración PHP con OPcache
│   └── apache.conf                # Configuración Apache + URL rewriting
│
├── Dockerfile                     # Imagen PHP 8.1 + Apache + extensiones
├── docker-compose.yml             # Orquestación: app + db (PostgreSQL) + pgAdmin
├── Makefile                       # Comandos rápidos (make up, make logs, make db-export)
├── .env                           # Variables de entorno — NO subir a git
├── .env.example                   # Plantilla de variables — sí en git
├── .gitignore                     # Excluye .env, backups, logs, uploads
└── .dockerignore                  # Excluye node_modules, .git de la imagen Docker
```

---

## 🧱 Stack Tecnológico

| Capa | Tecnología | Versión |
|------|-----------|----|
| Backend | PHP | 8.1+ |
| Base de datos | PostgreSQL | 15+ |
| Servidor web | Apache | 2.4 |
| Frontend | HTML5 + Vanilla CSS + Vanilla JS | — |
| Iconos | FontAwesome | 6.5.1 |
| Tipografía | Google Fonts (Inter) | — |
| IA | Groq API (LLaMA 3) | — |
| Contenedores | Docker + Compose | 24+ |
| Control de versiones | Git | — |

---

## 🔐 Seguridad

- **CSRF**: Token criptográfico (`csrf_token`) en todos los formularios POST
- **Rate Limiting**: Bloqueo automático 5 min tras 5 intentos fallidos de login
- **SQL Injection**: 100% PDO con `prepare()` / `execute()`, sin concatenación
- **XSS**: Función `esc()` aplicada en todas las salidas HTML
- **RCE**: `.htaccess` bloquea ejecución PHP en `/uploads/`
- **Secrets**: Credenciales exclusivamente en `.env` (nunca en código)
- **Headers**: HTTPS, HSTS activable vía `APP_HTTPS=true` en `.env`

Ver [auditoria_seguridad.md](auditoria_seguridad.md) para el reporte completo.

---

## 🗄️ Base de Datos (PostgreSQL 15)

- **Nombre**: `bd_sisibic`
- **Motor**: PostgreSQL 15+
- **Usuario**: `comecyt_user`
- **Puerto Docker internal**: `5432` | **Puerto externo**: `5433`

### Tablas Principales

| Módulo | Tablas clave |
|---|---|
| Tickets | `solicitudes`, `historial_solicitudes`, `comentarios_solicitudes` |
| ERP | `cat_personal`, `usuarios`, `cat_areas`, `solicitudes_actualizacion_personal` |
| Inventario | `sb_bienes`, `sb_bienes_altum`, `sb_bienes_impresoras`, `sb_bienes_red` |
| Colaboración | `sb_chat_mensajes`, `sb_kanban_tareas`, `eventos`, `sb_correos_oficial` |
| Servicio Social | `ss_alumnos`, `ss_asistencia`, `ss_evidencias`, `ss_kanban_tareas` |

---

## 📡 APIs Internas

Ver [endpoints.md](endpoints.md) para la documentación completa.

---

## 📱 Diseño Responsive

El sistema es **completamente responsive**, soportando:

- 🖥️ PC grande (≥1400px) — Layout completo con sidebar expandido
- 💻 Laptop (1024–1399px) — Sidebar reducido, cards optimizadas
- 📱 Tablet (768–1023px) — Sidebar colapsable con hamburguesa
- 📱 Móvil (≤480px) — Layout de una columna, modales bottom-sheet
- 📱 Móvil mínimo (≤360px) — Adaptación máxima para pantallas compactas

El **dark mode** está soportado en todos los breakpoints vía clase `.dark-mode` en `<body>`.

---

## 📦 Comandos Útiles

```bash
# Docker
make up          # Subir contenedores
make down        # Apagar contenedores
make logs        # Ver logs en tiempo real
make db-export   # Exportar backup de BD

# Git
git log --oneline -10    # Ver historial de commits
git diff HEAD            # Ver cambios non-commiteados
git status               # Estado del repositorio
```

---

## 📞 Soporte

**Área de TI — COMECyT Estado de México**
desarrollo.comecyt@edomex.gob.mx | Ext. 114
