# Memory & Delegation Protocol

## Perfiles de Agentes
- **Core Architect (Gemini/Claude sonnet):** Diseño de arquitectura, resolución de lógica compleja (`ContextResolver`), y toma de decisiones de seguridad.
- **Documentation Agent (Haiku/Flash):** Generación de PHPDoc, creación de archivos README, actualización de changelogs y formateo de tablas.
- **Testing Agent:** Creación de Unit Tests básicos basados en la lógica definida por el Arquitecto.

## Reglas de Ahorro de Tokens
- **Contexto Incremental:** No enviar el código completo en cada iteración; enviar solo el fragmento o clase afectada.
- **Delegación Automática:** Toda tarea que consista en "documentar", "comentar" o "formatear" debe ser marcada para el Agente de Documentación.
- **Validación de Cambios:** Antes de procesar, verificar `history.md` para no repetir lógica ya descartada.