# Intranet COMECyT 🏢

> **De "Control de Solicitudes" a Intranet Institucional**
> Este proyecto evolucionó de un simple gestor unificado de peticiones a la plataforma integral corporativa del **Colegio Mexiquense de Ciencia y Tecnología (COMECyT)**, gestionando desde la asistencia técnica hasta calendarios compartidos y sistemas de comunicación interdepartamental y herramientas de inteligencia artificial.

---

## 🌟 Características Principales

1. **Gestión Departamental Segura**
   - Espacios de despliegue para áreas como **Sistemas**, **Difusión**, **Asuntos Jurídicos**, entre otros.
   - Seguridad por roles (Superadmin, Admin de Área, Jefaturas, Personal) y aislamiento total de datos de acuerdo al perfil del usuario.

2. **Chat Colaborativo "Platinum Elite"**
   - Chat grupal por áreas y soporte a Mensajes Directos entre administradores.
   - Reacciones interactivas persistentes con Emojis (👍 ♥️ 😂 😮 🚀).
   - Acciones enriquecidas directamente desde la ventana de chat: **Creación rápida de Tareas Kanban** y agendado de **Eventos de Calendario**.
   - Auto-limpieza visual y seguridad perimetral de visibilidad temporal dependiendo el usuario, resguardada con diseño Glassmorphism Platinum.

3. **Inteligencia Artificial (Groq Backend)**
   - Asistente de Inteligencia Artificial propio anidado en todas las interfaces de Administración conectado en nube, listo para atender consultas de uso en instantes.

4. **Productividad y Planeación**
   - Calendarios interactivos (Editoriales y generales) para cada dependencia con restricciones dinámicas.
   - Tablero Kanban (Pendientes, En Proceso, Completado).

5. **Módulos Periféricos**
   - Gestión y rastreo de personal en el programa de Servicio Social.
   - Inventariado y asignación de equipo institucional.
   - Expedientes, Reportes y Dictámenes para departamentos rigurosos de índole legal.

---

## 🚀 Arquitectura Tecnológica

La Intranet de COMECyT está desplegada bajo orquestamiento seguro en la intranet nativa del usuario, propulsado por un Stack rápido, dinámico y escalable:

- **Backend:** PHP 8.1 🐘
- **Motor SQL:** PostgreSQL 15 🐘
- **Framework V. Gráfica:** HTML5 + Vanilla JS + CSS3 (Zero Framework)
- **Despliegue y Orquestación:** Docker + Docker Compose 🐋
- **Peticiones Asíncronas e IA:** GroqCloud LLM Node integration + Fetch API

---

## 🔌 Inicialización Local con Docker

Asegúrate de preparar y duplicar correctamente las credenciales `.env` previo a compilar si accedes a una copia limpia. 

```bash
# Variables
cp .env.example .env 
# Edita tus credenciales base, variables CSRF y en especial tu [GROQ_API_KEY] para habilitar la Inteligencia Artificial.

# Iniciar la infraestructura de contenedores 
docker compose up -d --build
```
*Esto levantará el servidor Web `comecyt_app` en el puerto `8080`, la instancia central Postgres `comecyt_db` (y su contraparte gráfica pgAdmin `comecyt_pgadmin`).*


---
*Sistemas / COMECyT © Todos los derechos reservados.*
