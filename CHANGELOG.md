# Changelog

Todos los cambios notables en este proyecto serán documentados en este archivo.

El formato está basado en [Keep a Changelog](https://keepachangelog.com/es/1.0.0/)
y este proyecto adhiere a [Semantic Versioning](https://semver.org/lang/es/).

---

## [v3.0.6] - 2025-XX-XX

### 🔧 Fixes
- **CRITICAL**: Implementada compatibilidad hacia atrás con módulos v2.x
  - El ServiceProvider ahora detecta y carga ambas convenciones:
    - `Routes/` (v3.0.0+ uppercase)
    - `routes/` (v2.x legacy lowercase)
  - Los módulos generados antes de v3.0.0 funcionan sin migración
  
### ✨ Added
- **Comando `innodite:migrate-modules`**: Migración automática de estructura v2.x → v3.0.0
  - Opción `--dry-run` para vista previa sin cambios
  - Renombra `routes/` → `Routes/` en todos los módulos
  
### 📚 Documentation
- **MIGRATION.md**: Guía completa de migración v2.x → v3.0.0
  - Soluciones automáticas y manuales
  - Problemas comunes y diagnóstico
  - Nuevas funcionalidades de v3.0.0
  
### 🔍 Changed
- Refactorizado `loadModuleRoutes()` en `LaravelModuleMakerServiceProvider`
  - Métodos separados: `loadRoutesV3()` y `loadRoutesV2()`
  - Fallback automático a convención v2.x si no existe v3.0.0

---

## [v3.0.5] - 2025-01-XX

### 🔧 Fixes
- **VueGenerator**: Corregido error `Too few arguments to function getStubPath()`
  - Ahora usa `getStubContent()` correctamente
  - Compatibilidad total con la nueva arquitectura de stubs contextuales

### 📚 Documentation
- **TROUBLESHOOTING.md**: Guía de diagnóstico completa
  - Errores comunes y soluciones
  - Pasos de verificación del sistema
  - Comandos de validación
  
- **SKILL.md**: Base de conocimiento arquitectónico experto (94KB)
  - Auditoría arquitectónica completa
  - Patrones de diseño y decisiones técnicas
  - Guías de debugging avanzadas
  - Diagramas de flujo y estructura

### 🛠️ Developer Experience
- Validación pre-commit de integridad de stubs
- Scripts de diagnóstico (`scripts/validate-stubs.sh`)

---

## [v3.0.0] - 2025-01-XX

### 🚀 BREAKING CHANGES

#### **1. Nueva Estructura de Carpetas**
```diff
Modules/User/
- routes/          ← Removido
+ Routes/          ← Nueva convención (UPPERCASE)
    web.php
    tenant.php     ← Nuevo archivo (contexto tenant)
    api.php
```

> **Nota**: v3.0.6+ es backward compatible con la estructura antigua `routes/`

#### **2. Sistema de Contextos (`contexts.json`)**
- Introducción de arquitectura multi-contexto
- Configuración explícita de contextos: `central`, `tenant`, `shared`, `tenant_shared`
- Generación condicional de componentes según contexto

#### **3. Stubs Contextualizados**
- Nueva estructura: `stubs/contextual/{Context}/`
- Stubs específicos por contexto reemplazan stubs globales
- Mayor flexibilidad arquitectónica

#### **4. Trait `RendersInertiaModule` (Controladores)**
- **OBLIGATORIO** para controladores con vistas Inertia
- Reemplazo de `Inertia::render()` por `$this->renderModule()`
- Resolución automática de rutas frontend

```diff
- return Inertia::render('User/Index');
+ return $this->renderModule('User', 'CentralUserIndex');
```

#### **5. Middleware `InnoditeContextBridge`**
- Inyección automática de contexto en props de Inertia
- Variables globales disponibles en todas las vistas:
  - `context`: Contexto actual del request
  - `modelContext`: Contexto del modelo (si aplica)
  - `routePrefix`: Prefijo de ruta contextual

### ✨ Added
- **Comando `innodite:module-setup`**: Configuración inicial del proyecto
- **Comando `innodite:module-check`**: Validación de integridad de módulos
- **Comando `innodite:check-env`**: Verificación de entorno

### 🔍 Changed
- Refactorización completa de generadores (factory pattern)
- Mejor gestión de errores y validaciones
- Logging estructurado con contexto

### 📚 Documentation
- SPEC.md: Especificación técnica completa
- WORKPLAN.md: Plan de trabajo y roadmap
- files-and-folders-structure.md: Estructura detallada

---

## [v2.5.1] - 2024-XX-XX (Legacy)

### ✨ Features
- Generación básica de módulos Laravel
- Estructura de carpetas estándar (`routes/` lowercase)
- Generación de modelos, controladores, servicios, repositorios
- Integración con Inertia.js

### 📝 Notes
- Última versión con estructura `routes/` (lowercase)
- Compatible con Laravel 10.x
- Sin sistema de contextos

---

## Guía de Migración

Para migrar de v2.x a v3.0.0+, consulta [MIGRATION.md](MIGRATION.md)

---

## Versionado

- **v3.x.x**: Sistema multi-contexto (architecture v3.0.0)
- **v2.x.x**: Generación básica sin contextos (deprecated)
- **v1.x.x**: Versiones legacy (sin soporte)
