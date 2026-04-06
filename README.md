<div align="center">
  <img src="https://logodownload.org/wp-content/uploads/2021/04/estado-de-mexico-logo-1.png" alt="Edomex" width="180"/>
  <h1>Gobierno Digital: Intranet Corporativa COMECyT</h1>
  <p><strong>Plataforma integral, orquestamiento departamental y automatización con Inteligencia Artificial.</strong></p>
</div>

---

## 📌 Visión General del Proyecto

Esta plataforma fue conceptualizada originalmente para la gestión y **Control de Solicitudes**, pero rápidamente escaló y fue rediseñada para convertirse en el **Ecosistema Digital y de Colaboración Principal (Intranet)** del Consejo Mexiquense de Ciencia y Tecnología (COMECyT).

El proyecto actúa como el cerebro operativo de la organización pública: unifica procesos que anteriormente se llevaban en papel u hojas de cálculo inconexas, proveyendo a **cada área o departamento** de su propia suite de herramientas aislada, centralizando el flujo de aprobaciones y manteniendo comunicación en tiempo real.

### 💡 Impacto y Valor Agregado

1. **Cero-Papel y Transparencia:** Digitalización al 100% en el ciclo de vida de peticiones y documentos legales (dictámenes, oficios, seguimiento de recursos).
2. **Eliminación de Cuellos de Botella:** Tiempos de seguimiento reducidos de horas/días a cuestión de minutos gracias al tablero Kanban y métricas en tiempo real.
3. **Escalabilidad Gubernamental:** Con un sistema robusto de roles y control de acceso (Firewall por Departamentos), la plataforma aísla expedientes de Asuntos Jurídicos de manera segura frente al área de Difusión o Mantenimiento.
4. **Soporte Hiperconectado (IA):** Implementación de un modelo Large Language Model (Groq / LLaMA-3) operando a escala para resolver problemáticas de sistema a la medida del usuario sin necesidad de recurrir constantemente al área de Sistemas.

---

## ⚙️ Funcionamiento del Sistema General

La arquitectura sigue una convención MVC pura montada sobre servicios acoplados (Docker), separando claramente el Frontend Administrativo de la lógica API-first y persistencia de bases de datos.

### 🏛 Estructura Organizacional Múltiple (Routing Dinámico)
El sistema **no es monolítico en su flujo operativo**. Un Administrador de *Asuntos Jurídicos* inicia sesión y es redirigido a una interfaz ad-hoc con reportes judiciales, mientras que el *Equipo de Sistemas* visualiza tickets de reparación de software web. Esta orquestación dinámica del menú y capacidades mantiene limpia la UX para toda la base de empleados.

### 🧠 El Ecosistema Operativo
- **Dashboard Gerenciales:** Paneles resumen individuales que consumen métricas en tiempo real. Cada jefe de área puede validar su productividad.
- **Workflow de Solicitudes (Aprobadores):** Transición rigurosa de folios *(Pendiente → En Proceso → Completada/Rechazada)*, permitiendo al solicitante externo o interno dar rastreo a la petición.
- **Data Room & Logs:** Todo movimiento dentro del sistema es firmado ("logged") mediante variables de servidor y de base de datos para auditorías fiables.

---

## 🚀 Módulos Especializados & Soluciones Híbridas

| Módulo | Despliegue Técnico y Funcionalidad |
| --- | --- |
| **Chat Platinum Elite** | Mensajería de área ultra-rápida (Long-Polling / Fetch API) con diseño Glassmorphism Platinum, reacciones persistentes mediante emojis en base de datos, auto-limpieza nocturna programática de visibilidad y acceso hiper-securizado mediante Dólar-ID / CSRF. |
| **Asuntos Jurídicos** | Entorno restrictivo altamente confidencial (cve_area = 19). Posee registros vinculantes de Dictámenes, Acuerdos y Contratos Administrativos amparados en sub-cargas de archivos. |
| **PVD (Personal & Estructura)** | Repositorios interconectados del programa "Servicio Social", inventariado de TI, control estricto de asistencia y geolocalización o justificaciones directas. |
| **Productividad Kanban** | Agenda global, vista combinada en Kanban para distribución táctica de tareas entre equipos, con opción directa de interconectarlo desde el chat grupal y emitir una alerta instantánea (`m.tipo = 'tarea'`). |
| **Asistente IA (Groq)** | Un **Chatbot Asistente** en nube a disposición de los administradores que analiza las variables de la sesión interactiva del usuario mediante `System Prompts` dinámicos y lo asiste en dudas técnicas de flujos de la Intranet. |

---

## 🛠 Topología de Desarrollo (Tech Stack)

La infraestructura del servidor es administrada por un orquestador híbrido (`docker-compose.yml`) posicionado bajo el principio de infraestructura as code (IaC), permitiendo recuperar localmente el estado del software en desarrollo o producción con 1 comando.

* **Backend Language:** PHP 8.1 FPM.
* **Database & Persistence:** PostgreSQL 15 (Transaccional, Restricción por Foreign Keys, Time-Zoning adaptado).
* **Interface & UX Engine:** Vanilla Javascript (ES6), HTML 5, SCSS/CSS3 puro (Cero inyección de frameworks pesados).
* **Virtualización:** Docker & Compose Networking.
* **Integraciones Híbridas:** LLaMA-3 Instant Engine (Cloud Node).


## 💻 Iniciar Infraestructura

Para levantar el clonado del repositorio debes contar con **Docker** en tu sistema local operativo o en tu instancia servidor (Proxmox/VPS).

```bash
# 1. Copiar valores locales de la bóveda
cp .env.example .env

# 2. Asignar llave Cloud para usar funcionalidades de la Intranet (Inteligencia Artificial)
# -> (Modificar `GROQ_API_KEY` en tu archivo root .env con credenciales productivas).

# 3. Construir Contenedores Nativos
docker compose up -d --build
```
> *Accede al servicio alojado localmente en `localhost:8080` (App) / `localhost:8081` (pgAdmin PostgreSQL visual GUI).*

---
<p align="center">
  <code>Gobierno del Estado de México</code> • 
  <code>COMECyT Intranet Oficial</code> • 
  <code>Versión 5.3 (Stable Release)</code>
</p>
