---
description: Revisar la salud y conexión de la base de Datos PostgreSQL
---

// turbo-all
1. Verificar si el contenedor de la base de datos está corriendo
```powershell
docker ps --filter "name=comecyt_db"
```

2. Probar conexión y listar tablas críticas
```powershell
docker exec -it comecyt_db psql -U comecyt_user -d bd_sisibic -c "\dt solicitudes"
docker exec -it comecyt_db psql -U comecyt_user -d bd_sisibic -c "\dt login_alertas"
docker exec -it comecyt_db psql -U comecyt_user -d bd_sisibic -c "\dt sb_chat_mensajes"
```

3. Verificar registros recientes para asegurar que el INSERT funciona
```powershell
docker exec -it comecyt_db psql -U comecyt_user -d bd_sisibic -c "SELECT id, fecha_creacion FROM solicitudes ORDER BY id DESC LIMIT 1"
```
