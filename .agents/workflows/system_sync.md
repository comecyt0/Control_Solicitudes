---
description: Sincronizar y actualizar el entorno Docker y Git
---
Este flujo automatiza la actualización del sistema después de realizar cambios en el código.

1. Reconstruir la imagen de la aplicación para integrar cambios de código.
// turbo
`docker compose build app`

2. Reiniciar los servicios en segundo plano.
// turbo
`docker compose up -d`

3. Registrar los cambios en el repositorio local.
`git add .`
`git commit -m "update: sincronización de sistema"`
