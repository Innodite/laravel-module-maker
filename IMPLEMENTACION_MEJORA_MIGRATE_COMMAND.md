# Implementacion de Mejora - Comando de Migraciones Modulares

## Contexto
Este documento convierte a plan ejecutable la propuesta del archivo ANALYSIS-MIGRATE-COMMAND .md.

## Validacion de estado actual

### Implementado hoy
1. El comando innodite:migrate-modules existe, pero solo migra estructura v2 a v3 (routes/ a Routes/).
2. El paquete ya hace discovery de rutas de migracion en Database/Migrations y subdirectorios.
3. Hay generadores de migration y seeder por contexto.

### No implementado hoy
1. No existe manifiesto global de orden de migraciones y seeders.
2. No existe parser de coordenadas Modulo:Contexto/Archivo.
3. No existe innodite:migration-sync.
4. No existe soporte --seed en un comando de plan de migracion.
5. No existe --all-tenants para ejecucion masiva.
6. No existe rollback atomico por capas de contexto.

## Opinion sobre el analisis
La direccion arquitectonica es correcta y necesaria para proyectos modulares multicontexto.

## Plan de implementacion

### Fase 1 - MVP de orquestacion
1. Crear carpeta module-maker-config/migrations.
2. Definir archivos de orden:
- central_order.json
- tenant_shared_order.json
- tenant_{name}_order.json opcional
3. Crear comando innodite:migrate-plan con flags:
- --manifest=
- --dry-run
- --seed
4. Resolver coordenadas logicas y ejecutar en orden declarado.

### Fase 2 - Sincronizacion automatica
1. Crear comando innodite:migration-sync.
2. Escanear Modules/*/Database/Migrations y Database/Seeders.
3. Detectar archivos no registrados y agregarlos al final del manifiesto.

### Fase 3 - Multi tenant
1. Agregar --all-tenants a innodite:migrate-plan.
2. Resolver tenants desde contexts.json.
3. Ejecutar por tenant en orden Shared, Tenant/Shared y Tenant/{Name}.

### Fase 4 - Rollback por capas
1. Crear innodite:migrate-plan:rollback.
2. Aplicar orden inverso del manifiesto.
3. Para tenant especifico: Tenant/{Name}, luego Tenant/Shared, luego Shared.

## Archivos previstos

### Nuevos
1. src/Commands/MigratePlanCommand.php
2. src/Commands/MigrationSyncCommand.php
3. src/Services/MigrationPlanResolver.php
4. src/Services/MigrationExecutor.php
5. src/Services/SeederExecutor.php
6. src/ValueObjects/MigrationCoordinate.php
7. src/ValueObjects/SeederCoordinate.php

### Modificados
1. src/LaravelModuleMakerServiceProvider.php
2. README.md
3. TROUBLESHOOTING.md

## Criterios de aceptacion
1. El plan ejecuta migraciones en orden exacto del manifiesto.
2. --dry-run muestra paso a paso sin ejecutar cambios.
3. --seed ejecuta seeders en orden luego de migraciones.
4. Falla con error claro si una coordenada no existe.
5. migration-sync detecta y registra faltantes.

## Nota operativa
Se recomienda renombrar ANALYSIS-MIGRATE-COMMAND .md a ANALYSIS-MIGRATE-COMMAND.md para evitar errores de tooling.
