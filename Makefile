# COMECyT — Comandos Rápidos Docker + PostgreSQL
# Uso: make <comando>

.PHONY: up down build restart logs shell db-export db-import db-shell health cron clean status

# ─── Servicios ────────────────────────────────────────────────────
## Levantar todos los servicios (construcción si es necesario)
up:
	docker compose up -d --build
	@echo ""
	@echo "✅ COMECyT levantado:"
	@echo "   App:      http://localhost:8080/public/"
	@echo "   Admin:    http://localhost:8080/admin/login.php"
	@echo "   pgAdmin:  http://localhost:8081"
	@echo "   Health:   http://localhost:8080/public/health.php"

## Detener servicios (sin borrar datos)
down:
	docker compose down

## Solo reconstruir la imagen PHP
build:
	docker compose build app

## Reiniciar todos los servicios
restart:
	docker compose restart

## Ver estado de los contenedores
status:
	docker compose ps

# ─── Logs ─────────────────────────────────────────────────────────
## Logs de todos los servicios en tiempo real
logs:
	docker compose logs -f

## Logs solo de PHP/Apache
logs-app:
	docker compose logs -f app

## Logs solo de PostgreSQL
logs-db:
	docker compose logs -f db

# ─── Shells ───────────────────────────────────────────────────────
## Abrir bash en el contenedor PHP
shell:
	docker compose exec app bash

## Abrir psql en el contenedor PostgreSQL
db-shell:
	docker compose exec db psql -U comecyt_user -d bd_sisibic

# ─── Base de Datos (PostgreSQL) ────────────────────────────────────
## Exportar dump de BD (se guarda en backups/)
db-export:
	@mkdir -p backups
	@TIMESTAMP=$$(date +%Y%m%d_%H%M%S); \
	docker compose exec -T db pg_dump -U comecyt_user bd_sisibic > backups/bd_sisibic_$$TIMESTAMP.sql; \
	echo "✅ Backup guardado en: backups/bd_sisibic_$$TIMESTAMP.sql"

## Importar dump de BD: make db-import FILE=backups/bd_sisibic_20260306.sql
db-import:
	@if [ -z "$(FILE)" ]; then echo "❌ Error: Especifica FILE=ruta/al/archivo.sql"; exit 1; fi
	docker compose exec -T db psql -U comecyt_user -d bd_sisibic < $(FILE)
	@echo "✅ BD importada desde: $(FILE)"

## Ejecutar migración v3.1 sobre BD activa
db-migrate:
	docker compose exec -T db psql -U comecyt_user -d bd_sisibic \
		< database/migracion_v3.1_postgres.sql
	@echo "✅ Migración v3.1 ejecutada"

# ─── Salud y Mantenimiento ─────────────────────────────────────────
## Verificar salud del sistema
health:
	@curl -s http://localhost:8080/public/health.php | python3 -m json.tool || \
	curl -s http://localhost:8080/public/health.php

## Ejecutar cron de recordatorios manualmente
cron:
	docker compose exec app php /var/www/html/cron/recordatorios.php

## Limpiar contenedores, imágenes y volúmenes (⚠️ borra datos BD)
clean:
	@echo "⚠️  Esto borrará TODOS los datos de BD y uploads. ¿Continuar? [s/N]" && read ans && [ $${ans:-N} = s ]
	docker compose down -v
	docker image rm comecyt_solicitudes-app 2>/dev/null || true
	@echo "✅ Limpieza completada"
