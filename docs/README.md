# COMECyT — Control de Solicitudes Internas
**Sistema de Gestión de Soporte TI — v3.0 | Marzo 2026**

> Sistema institucional de control de tickets de soporte, inventario TI, gestión de personal y colaboración interna para el Consejo Mexiquense de Ciencia y Tecnología.

---

## 🚀 Inicio Rápido

### Opción A — Docker (Recomendado, portable entre computadoras)
```bash
# Requisitos: Docker Desktop instalado
git clone <repo> COMECyT_Solicitudes
cd COMECyT_Solicitudes
cp .env.example .env          # Editar con tus valores
docker compose up --build -d  # Levanta PHP + MySQL + phpMyAdmin
```
- Portal de solicitudes: http://172.30.0.25:8080/public/ (Acceso desde red local)
- Portal Admin:        http://172.30.0.25:8080/admin/login.php
- pgAdmin:             http://172.30.0.25:8081

Ver [README-DOCKER.md](README-DOCKER.md) para instrucciones detalladas y portabilidad entre PCs.

### Opción B — MAMP (Legado)
1. Copiar carpeta a `/Applications/MAMP/htdocs/`
2. Importar `database/bd_sisibic_estructura_completa.sql` en phpMyAdmin
3. Configurar `.env` con `DB_HOST=127.0.0.1` y `DB_PORT=8889`
4. Acceder a `http://localhost:8889/COMECyT_Solicitudes/public/`

---

## 📋 Credenciales por defecto

| Rol | Email | Contraseña |
|-----|-------|-----------|
| Super Admin | cmendoza@comecyt.edomex.gob.mx | Admin123! |
| Admin | lsanchez@comecyt.edomex.gob.mx | Admin123! |
| Revisor | rflores@comecyt.edomex.gob.mx | Admin123! |
| Usuario portal | lsanchez@comecyt.edomex.gob.mx | User123! |

> ⚠️ **Cambiar todas las contraseñas en el primer inicio de sesión en producción.**

---

## 🗂️ Estructura del Proyecto

```
COMECyT_Solicitudes/
│
├── admin/                    # Panel administrativo (protegido)
│   ├── api/                  # Endpoints AJAX internos
│   │   ├── agente_ia.php     # Integración Groq AI
│   │   ├── busqueda_global.php
│   │   ├── cambiar_estatus.php
│   │   ├── chat.php
│   │   ├── comentarios.php
│   │   ├── exportar_pdf.php
│   │   ├── notificacion_email.php
│   │   ├── plantillas.php
│   │   └── toggle_darkmode.php
│   ├── login.php             # Autenticación admin
│   ├── dashboard.php         # Panel principal con métricas
│   ├── solicitudes.php       # Listado y gestión de tickets
│   ├── detalle.php           # Vista detalle de ticket
│   ├── personal.php          # CRUD de personal/empleados
│   ├── equipos.php           # Inventario de hardware
│   ├── calendario.php        # Calendario de eventos + kanban
│   ├── correos.php           # Gestión de correos institucionales
│   ├── reportes.php          # Reportes y estadísticas
│   ├── administradores.php   # Gestión de usuarios admin
│   └── export_csv.php        # Exportación de datos
│
├── public/                   # Portal público (sin autenticación requerida)
│   ├── index.php             # Registro y envío de solicitudes
│   ├── consulta.php          # Consulta de estatus por folio
│   ├── registro.php          # Alta de nuevo usuario en el sistema
│   ├── historial.php         # Historial de solicitudes del usuario
│   ├── equipos_usuario.php   # Equipos asignados al usuario
│   └── uploads/              # Archivos adjuntos (protegido via .htaccess)
│
├── config/
│   ├── database.php          # Conexión PDO singleton + generarFolio()
│   └── auth.php              # Sesiones, CSRF, rate-limiting
│
├── includes/
│   ├── header_admin.php      # Sidebar + navbar del panel admin
│   ├── header_user.php       # Header del portal público
│   ├── footer.php            # Footer admin
│   ├── footer_user.php       # Footer usuario
│   ├── helpers.php           # esc(), badgeEstatus(), csrfField()
│   ├── help_widget.php       # Widget de ayuda contextual
│   └── loader.php            # Pantalla de carga global (FOUC prevention)
│
├── assets/
│   ├── css/
│   │   ├── main.css          # Sistema de diseño con CSS vars
│   │   └── darkmode.css      # Tema oscuro
│   ├── js/
│   │   └── app.js            # JS minimal (modales, UI, uploads)
│   ├── MARCA.png             # Logotipo institucional
│   └── pantalla_carga.png    # Imagen de carga
│
├── database/
│   └── init.sql                            # Schema PostgreSQL unificado (sisibic + ss)
│
├── cron/
│   └── recordatorios.php     # Cron job: notificaciones email automáticas
│
├── docs/                     # Documentación técnica
│   ├── arquitectura_y_flujos.md
│   ├── manual.md
│   ├── auditoria_seguridad.md
│   └── endpoints.md          # [NUEVO] Referencia de APIs
│
├── docker/                   # [NUEVO] Configuración Docker
│   ├── php.ini
│   └── apache.conf
│
├── Dockerfile                # [NUEVO] Imagen PHP 8.1 + Apache
├── docker-compose.yml        # [NUEVO] Orquestación de servicios
├── Makefile                  # [NUEVO] Comandos rápidos
├── .env                      # Variables de entorno (NO subir a git)
├── .env.example              # Plantilla de variables (sí subir a git)
├── .dockerignore             # [NUEVO] Exclusiones para imagen Docker
└── README.md                 # Este archivo
```

---

## 🧱 Stack Tecnológico

| Capa | Tecnología | Versión |
|------|-----------|---------|
| Backend | PHP | 8.1+ |
| Base de datos | MySQL / PostgreSQL | 8.0 / 15+ |
| Servidor web | Apache | 2.4 |
| Frontend | HTML5 + Vanilla CSS + JS | — |
| Iconos | FontAwesome | 6.5 |
| IA | Groq API (LLaMA 3) | — |
| Contenedores | Docker + Compose | 24+ |

---

## 🔐 Seguridad

- **CSRF**: Token criptográfico en todos los formularios POST
- **Rate Limiting**: Bloqueo 5 min tras 5 intentos fallidos de login
- **SQL Injection**: 100% PDO con `prepare()` / `execute()`
- **XSS**: `esc()` helper en todas las salidas HTML
- **RCE**: `.htaccess` desactiva ejecución PHP en `/uploads/`
- **Secrets**: Variables sensibles exclusivamente en `.env`

Ver [docs/auditoria_seguridad.md](docs/auditoria_seguridad.md) para el reporte completo.

---

## 🗄️ Base de Datos

- **Nombre**: `bd_sisibic`
- **Motor**: MySQL 8.0 / PostgreSQL 15
- **Tablas principales**: `solicitudes`, `historial_solicitudes`, `administradores`, `cat_personal`, `usuarios`, `sb_bienes`, `sb_kanban_tareas`, `eventos`, `sb_chat_mensajes`

Ver [docs/arquitectura_y_flujos.md](docs/arquitectura_y_flujos.md) para el mapa relacional completo.

---

## 📡 APIs Internas

Ver [docs/endpoints.md](docs/endpoints.md) para la documentación completa de los endpoints AJAX.

---

## 📦 Backups

Los backups se almacenan en `backups/` (excluida de git via `.gitignore`).

```bash
# Exportar backup de BD (con Docker activo)
make db-export

# Ver historial de cambios (Git)
git log --oneline -n 10
```

---

## 📞 Soporte

**Área de TI — COMECyT Estado de México**
soporte.ti@comecyt.edomex.gob.mx | Ext. 201
