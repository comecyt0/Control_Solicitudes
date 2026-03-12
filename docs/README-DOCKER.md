# README — Docker | COMECyT Control de Solicitudes
**Guía de instalación y portabilidad entre computadoras**

---

## ✅ Requisitos previos

- **Docker Desktop** instalado y corriendo
  - Mac: https://docs.docker.com/desktop/install/mac-install/
  - Windows: https://docs.docker.com/desktop/install/windows-install/
- **Git** (opcional, para clonar)
- **Make** (incluido en Mac/Linux; en Windows usar Git Bash)

---

## 🚀 Primer inicio (computadora nueva)

```bash
# 1. Copiar el proyecto a la nueva computadora
#    (vía USB, BackUP .tar.gz, o git clone)

# 2. Descomprimir si viene como .tar.gz
tar -xzf COMECyT_20260306.tar.gz

# 3. Entrar al directorio
cd COMECyT_Solicitudes

# 4. Configurar variables de entorno
cp .env.example .env
# Editar .env con tu editor favorito y llenar los valores

# 5. Levantar todos los servicios
make up
# O si no tienes make:
# docker compose up --build -d
```

**URLs disponibles:**
| Servicio | URL |
|----------|-----|
| Portal de solicitudes | http://localhost:8080/public/ |
| Panel de administración | http://localhost:8080/admin/login.php |
| phpMyAdmin | http://localhost:8081 |

---

## 🔑 Credenciales por defecto

| Qué | Usuario | Contraseña |
|-----|---------|-----------|
| Admin panel | cmendoza@comecyt.edomex.gob.mx | Admin123! |
| MySQL (app) | comecyt_user | comecyt_pass2026 |
| MySQL (root) | root | root_comecyt2026 |

> ⚠️ Cambiar contraseñas en producción editando `docker-compose.yml` y `.env`.

---

## 📦 Cómo mover el sistema a otra PC

### Opción A — Con el archivo .tar.gz (recomendado)

```bash
# En la PC origen: exportar BD actualizada y comprimir
make db-export                  # → genera backups/bd_sisibic_TIMESTAMP.sql
# Comprimir TODO el proyecto
tar -czf COMECyT_backup_completo.tar.gz \
    --exclude='.git' \
    --exclude='*.DS_Store' \
    ../COMECyT_Solicitudes/

# En la PC destino:
tar -xzf COMECyT_backup_completo.tar.gz
cd COMECyT_Solicitudes
cp .env.example .env   # Editar con tus valores
make up
make db-import FILE=backups/bd_sisibic_TIMESTAMP.sql
```

### Opción B — Con Git + backup de BD

```bash
# En la PC origen:
make db-export           # genera el .sql
git add backups/
git commit -m "backup antes de migrar"
git push

# En la PC destino:
git clone <repo-url> COMECyT_Solicitudes
cd COMECyT_Solicitudes
cp .env.example .env
make up
make db-import FILE=backups/bd_sisibic_TIMESTAMP.sql
```

---

## 🛠️ Comandos disponibles

```bash
make up          # Levantar todos los servicios
make down        # Detener servicios (datos preservados)
make restart     # Reiniciar servicios
make build       # Reconstruir imagen PHP
make logs        # Ver logs de todos los servicios en tiempo real
make logs-app    # Ver logs solo del servidor PHP
make logs-db     # Ver logs solo de MySQL
make shell       # Abrir terminal bash dentro del contenedor PHP
make db-shell    # Abrir cliente MySQL dentro del contenedor
make db-export   # Exportar backup de BD → backups/bd_sisibic_TIMESTAMP.sql
make db-import FILE=ruta.sql  # Importar backup de BD
make cron        # Ejecutar manualmente el cron de recordatorios
make status      # Ver estado de los contenedores
make clean       # ⚠️ BORRAR TODO (incluida la BD)
```

---

## 🔧 Solución de problemas comunes

### El contenedor `app` no inicia
```bash
make logs-app
# Verificar que el .env esté configurado correctamente
# Verificar que el puerto 8080 no esté ocupado
```

### MySQL tarda en iniciar (healthcheck)
```bash
# Normal en primer inicio (descarga imagen + init BD)
# Esperar ~60 segundos y verificar:
docker compose ps
```

### Reconstruir todo desde cero
```bash
make clean   # Confirmar con 's'
make up
make db-import FILE=backups/bd_sisibic_ULTIMO.sql
```

### El puerto 8080 ya está ocupado
Editar `docker-compose.yml`:
```yaml
app:
  ports:
    - "9090:80"   # Cambiar 8080 por otro puerto disponible
```

### Ver logs de PHP en tiempo real
```bash
make shell
tail -f /var/log/php_errors.log
```

---

## 📁 Estructura de archivos Docker

```
COMECyT_Solicitudes/
├── Dockerfile              → Imagen PHP 8.1 + Apache + extensiones
├── docker-compose.yml      → Orquestación: app + db + phpmyadmin
├── .dockerignore           → Archivos excluidos de la imagen
├── .env                    → Variables de entorno (NO subir a git)
├── .env.example            → Plantilla de variables (sí subir a git)
├── Makefile                → Comandos rápidos
├── docker/
│   ├── php.ini             → Configuración PHP (upload, timezone, etc.)
│   └── apache.conf         → VirtualHost Apache
└── backups/                → Dumps de BD (ignorado por git)
```

---

## 🌐 Arquitectura de red Docker

```
┌─────────────────────── comecyt_network ────────────────────────┐
│                                                                  │
│   ┌──────────────┐    ┌──────────────┐    ┌──────────────────┐ │
│   │  comecyt_app │    │  comecyt_db  │    │comecyt_phpmyadmin│ │
│   │ PHP 8.1+Apache│◄──►│  MySQL 8.0  │◄──►│  phpMyAdmin 5.2  │ │
│   │  :80 (→8080) │    │:3306 (→3307) │    │  :80 (→8081)     │ │
│   └──────────────┘    └──────────────┘    └──────────────────┘ │
│                               │                                  │
│                     ┌─────────▼──────┐                         │
│                     │  mysql_data    │  ← Volumen persistente  │
│                     │  uploads_data  │  ← Uploads persistentes │
│                     └────────────────┘                         │
└──────────────────────────────────────────────────────────────── ┘
```
