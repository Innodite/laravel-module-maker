---
description: Skill para consulta rápida de documentación técnica usando @tags sin consumir tokens innecesarios.
---
# Skill: Consultas @tags (Ahorro de Contexto)

Al buscar información sobre un módulo, NO leas el archivo completo. Usa el script `./scripts/qdoc.sh`.

### Mapeo de Tags:
- `@ESTADO`: Situación actual (backend/frontend).
- `@CRITICO`: Reglas que NO se pueden romper.
- `@BD`: Tablas y conexiones.
- `@HISTORIAL`: Últimos 3 cambios.

### Instrucción:
Si el usuario pregunta "¿Cómo está el módulo X?", ejecuta: `shell_execute("./scripts/qdoc.sh X ESTADO")`.

# Sistema de Anotaciones (@tags)

Sistema para consultar secciones específicas de documentos por consola,
sin necesidad de leer archivos completos.

---

## Uso

```bash
# Consultar una anotación específica de un módulo
./scripts/qdoc.sh {Modulo} {TAG}

# Ejemplos
./scripts/qdoc.sh Person ESTADO
./scripts/qdoc.sh Forms PENDIENTE
./scripts/qdoc.sh UserManagement PATRON
./scripts/qdoc.sh Campaigns HISTORIAL
```

---

## Tags disponibles

| Tag | Dónde va | Qué contiene |
|-----|----------|--------------|
| `@ESTADO` | `ARQUITECTURA.md` o `HISTORIAL.md` | Estado actual del módulo (desarrollo, estable, migración) |
| `@PATRON` | `ARQUITECTURA.md` | Patrón de diseño aplicado y cómo funciona |
| `@CRITICO` | `ARQUITECTURA.md` | Reglas que NO se pueden ignorar al tocar este módulo |
| `@PENDIENTE` | `HISTORIAL.md` | Tareas pendientes en el módulo |
| `@HISTORIAL` | `HISTORIAL.md` | Últimos cambios realizados |
| `@RUTAS` | `ARQUITECTURA.md` | Prefijos de rutas y middleware del módulo |
| `@BD` | `ARQUITECTURA.md` | Tablas y conexiones de BD del módulo |

---

## Formato en los documentos

Las anotaciones se escriben como secciones markdown con el prefijo `@`:

```markdown
## @ESTADO
activo — patrón DRY/SOLID aplicado parcialmente (backend listo, frontend pendiente)

## @PENDIENTE
- Thin wrapper Vue para TelephonySpain
- Docblocks en AbstractPersonController
- Tests Feature del endpoint de importación

## @HISTORIAL
### 2026-03-25
- Implementada importación desde Excel con validaciones in: y errores inline
- Fix: DocumentTypeSeeder faltaba en DatabaseSeeder
- Rama: feat/import-people-excel (mergeado)
```

---

## Archivos donde aplican las anotaciones

```
Modules/{Modulo}/docs/
  ARQUITECTURA.md   → @ESTADO, @PATRON, @CRITICO, @RUTAS, @BD
  HISTORIAL.md      → @HISTORIAL, @PENDIENTE
```

---

## Reglas para mantener las anotaciones útiles

1. `@HISTORIAL` — solo los últimos 3 cambios significativos (no changelog completo)
2. `@PENDIENTE` — máximo 5 items; si hay más, priorizar los más importantes
3. `@ESTADO` — una línea descriptiva, no un párrafo
4. `@CRITICO` — solo lo que causa bugs silenciosos si se ignora
5. Actualizar siempre al terminar una tarea en el módulo
