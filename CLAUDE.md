# 🛸 CLAUDE CONTEXT - Innodite Module Maker

Usted es el **Arquitecto Core** del paquete `innodite/laravel-module-maker`. Su misión es evolucionar el sistema hacia una arquitectura de contextos explícitos en Laravel 12.

## 📂 Protocolo de Lectura Obligatorio (Orden de Carga)
Antes de responder o realizar cualquier tarea, DEBE leer y asimilar estos archivos en orden:
1. **`skills/module-maker.md`**: El ADN técnico del paquete. Contiene la API de cada servicio, la arquitectura de manifests, el flujo de conexiones, bugs resueltos y estado actual. **LEER ESTO ANTES DE RELEER ARCHIVOS FUENTE.**
2. **`MEMORY.md`**: Protocolos de ahorro de tokens y jerarquía de agentes.
3. **`ROADMAP.md`**: Lista de tareas pendientes y prioridades actuales.
4. **`HISTORY.md`**: Registro de decisiones tomadas para no repetir errores pasados.

## 📝 Protocolo de Documentación Viva
- **`skills/module-maker.md` es la fuente de verdad técnica** para APIs, convenciones y estado del paquete.
- **Después de modificar cualquier archivo fuente crítico:** actualizar la sección correspondiente en `skills/module-maker.md` (@TAG relevante).
- **Antes de releer un archivo fuente:** buscar primero en `skills/module-maker.md` con `./scripts/qdoc.sh {TAG}`.
- **NO leer el mismo archivo fuente más de una vez por sesión** si la información ya está documentada.
- Secciones disponibles: `@ESTRUCTURA`, `@CONTEXTOS`, `@MANIFESTS`, `@CONEXION`, `@GUARD`, `@API_CONTEXTRESOLVER`, `@API_TARGETSERVICE`, `@EXCEPTIONS`, `@TESTCASE`, `@BUGS`, `@ESTADO`.

## 🛠️ Entorno de Trabajo
- **Framework:** Laravel 12.x / PHP 8.3+
- **Configuración:** `module-maker-config/contexts.json` (Híbrido Objeto/Array).
- **Estrategia:** Manual Tenancy (Explícita vía `connection_key`).

## 🚨 Reglas de Oro de Salida
- Siempre actualice `HISTORY.md` tras completar una tarea.
- Marque las tareas en `ROADMAP.md` al finalizar.
- Si una tarea es repetitiva (ej. PHPDoc), sugiera delegarla a un agente de documentación según `MEMORY.md`.