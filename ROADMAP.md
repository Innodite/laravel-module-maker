# Roadmap de Desarrollo

## 🔴 Prioridad 5: Refactor estructural — subfolder por entidad en todos los generadores (R05)

- **Actividad:** Ajustar `AbstractComponentGenerator` para que cada entidad genere sus archivos dentro de su propio subfolder, siguiendo el patrón `{Tipo}/{Contexto}/{Entidad}/{Archivo}`.
- **Problema actual:** Todas las entidades de un módulo caen en la misma carpeta de contexto (`Models/Central/User.php`, `Models/Central/Role.php` — mezclados).
- **Objetivo:** Cada entidad tiene su propio espacio limpio e independiente dentro del módulo:
  ```
  Models/Central/User/CentralUser.php
  Models/Central/Role/CentralRole.php
  Http/Controllers/Central/User/CentralUserController.php
  Http/Controllers/Tenant/EnergySpain/Role/EnergySpainRoleController.php
  Services/Contracts/Tenant/Shared/Permission/TenantSharedPermissionServiceInterface.php
  ```
- **Convenciones de nombres:** Se mantienen intactas (`CentralRoleController.php`, no `RoleController.php`). Solo cambia la subcarpeta donde vive el archivo.
- **Aplica a todos los contextos:** `central`, `shared`, `tenant_shared`, y todos los tenants específicos.
- **Detalle técnico:**
  - `AbstractComponentGenerator::buildPath()` y `buildNamespace()` añaden `/{EntityName}` al final de la ruta/namespace.
  - `buildContractsPath()` y `buildContractsNamespace()` — ídem.
  - `ModuleGenerator::createFolders()` crea el subfolder de la entidad inicial.
  - Los stubs existentes no cambian.
- **Archivos a modificar:**
  - `src/Generators/Components/AbstractComponentGenerator.php`
  - `src/Generators/Components/ModuleGenerator.php`

---

## 🔴 Prioridad 6: Nuevo comando `innodite:add-entity` (R06)

- **Actividad:** Crear el comando `innodite:add-entity` que agrega una nueva entidad a un módulo ya existente.
- **Motivación:** `make-module` usa `{name}` como nombre del módulo Y de la entidad simultáneamente. No es posible agregar `Role` dentro de `UserManagement` — generaría un módulo llamado `Role` independiente.
- **Firma:**
  ```bash
  php artisan innodite:add-entity {module} {entity} {--context=} [-M] [-C] [-S] [-R] [-G] [-Q] [--no-routes]
  ```
- **Ejemplo:**
  ```bash
  php artisan innodite:add-entity UserManagement Role --context=central -M -C -S -R -G -Q
  # Genera dentro de Modules/UserManagement/:
  #   Models/Central/Role/CentralRole.php
  #   Http/Controllers/Central/Role/CentralRoleController.php
  #   Services/Central/Role/CentralRoleService.php + Interface
  #   Repositories/Central/Role/CentralRoleRepository.php + Interface
  #   Database/Migrations/Central/Role/..._create_roles_table.php
  #   Http/Requests/Central/Role/CentralRoleStoreRequest.php
  ```
- **Caso de uso real:** Módulo `UserManagement` con entidades `User`, `Module`, `Role`, `Permission` — cada una en su propio subfolder, todas bajo el mismo módulo contenedor.
- **Detalle técnico:**
  - Nuevo archivo `src/Commands/AddEntityCommand.php`.
  - `ModuleGenerator::createIndividualComponents()` acepta `?string $entityName = null` para desacoplar módulo de entidad.
  - Registrar `AddEntityCommand::class` en `LaravelModuleMakerServiceProvider`.
  - **Dependencia:** Requiere R05 implementado primero.
- **Archivos a crear/modificar:**
  - `src/Commands/AddEntityCommand.php` (nuevo)
  - `src/Generators/Components/ModuleGenerator.php`
  - `src/LaravelModuleMakerServiceProvider.php`
  - `skills/module-maker.md`

---

## 🟡 Prioridad 4: DX - Generador de Conexiones (R04)
- **Actividad:** Nuevo comando `innodite:make-connections`.
- **Detalle:** - Analizar el JSON y detectar conexiones faltantes en `config/database.php`.
    - Inyectar el bloque de código (Stub) al final del array de conexiones con las variables `env()` correspondientes.
    - Sugerir en consola las líneas para el archivo `.env`.