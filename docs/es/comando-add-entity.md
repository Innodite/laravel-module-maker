```bash
php artisan innodite:add-entity {module} {entity} {--context=} [-M] [-C] [-S] [-R] [-G] [-Q] {--dry-run}
```

### Qué hace

Agrega una entidad nueva a un módulo **ya existente**. Exige que el módulo exista; si no, sugiere
`make-module`.

**Sin ningún flag, se activan los seis** (a diferencia de `make-module`, donde ausencia de flags
dispara un camino de código distinto para el módulo completo). Con las seis activas se genera
también el paso de cierre: rutas, vistas, tests, factory, y se actualiza el `ServiceProvider` del
módulo (no se recrea — se le inyecta el `use` y el `bind` nuevos en sus marcadores).

Los 3 seeders maestros del módulo (`Application*Seeder`), si ya existen, se saltan.

### Parámetros

| Parámetro | Descripción |
|---|---|
| `module` | Nombre del módulo existente (ej. `UserManagement`) |
| `entity` | Nombre de la entidad nueva, PascalCase (ej. `Role`) |
| `--context=` | `central` \| `tenant` \| el que declare el proyecto. Obligatorio en `multitenant` |
| `-M` a `-Q` | Mismos flags que `make-module`. Sin ninguno, equivale a las seis activas |
| `--dry-run` | Registra qué escribiría, sin escribir nada |

La ruta y el permiso de la entidad nueva salen de la **entidad**, no del módulo — por eso `Role` no
hereda rutas ni permisos de `UserManagement`.

### Personalizar stubs

Mismo mecanismo y mismo orden de resolución que `innodite:make-module` — ver su ficha.

### Aplicación única

`php artisan innodite:add-entity UserManagement Role` genera, bajo `Modules/UserManagement/Role/`:

```
Role/Models/Role.php
Role/Http/Controllers/RoleController.php
Role/Http/Requests/RoleStoreRequest.php
Role/Http/Requests/RoleUpdateRequest.php
Role/Services/RoleService.php
Role/Services/Contracts/RoleServiceInterface.php
Role/Repositories/RoleRepository.php
Role/Repositories/Contracts/RoleRepositoryInterface.php
Role/Database/Migrations/{timestamp}_create_roles_table_final.php
Role/Database/Seeders/UserManagementRoleMigrationsList.php
Role/Database/Seeders/UserManagementRoleInlineAlters.php
Role/Database/Seeders/UserManagementRoleStageSeeder.php
Role/Database/Seeders/UserManagementRoleProductionSeeder.php
Role/Database/Seeders/UserManagementRolePermissionsSeeder.php
Role/Database/Seeders/UserManagementRoleData.php
Role/Database/Factories/RoleFactory.php
Role/Tests/Feature/RoleContract.php
Role/Tests/Feature/RoleTestCase.php
Role/Tests/Feature/RoleScaffoldTest.php
Role/Tests/Feature/RoleSchemaTest.php
Role/Tests/Feature/RolePermissionsTest.php
Role/Tests/Feature/RoleDeploymentTest.php
Role/Tests/Feature/RoleHttpTest.php
Role/resources/js/__tests__/RoleContract.js
Role/resources/js/__tests__/RoleIndex.test.js
Role/resources/js/Pages/RoleIndex.vue
Role/resources/js/Pages/RoleCreate.vue
Role/resources/js/Pages/RoleEdit.vue
Role/resources/js/Pages/RoleShow.vue
Role/Jobs/.gitkeep
Role/Notifications/.gitkeep
Role/Console/Commands/.gitkeep
Role/Exceptions/.gitkeep
```

Aquí `Role` ≠ `UserManagement`, así que los nombres de seeder **no** se duplican
(`UserManagementRoleStageSeeder.php`, no `UserManagementUserManagementStageSeeder.php`).

Modificados, no nuevos: `Modules/UserManagement/Routes/web.php` (nueva sección de rutas antes del
marcador) y `config/make-module.php` (línea `'UserManagement/Role',` en `deploy`). Omitidos porque
ya existían: `Providers/UserManagementServiceProvider.php` (se actualiza, no se recrea) y los 3
seeders maestros.

### Multiinquilino

`--context=central`:

```
Role/Models/Central/CentralRole.php
Role/Http/Controllers/Central/CentralRoleController.php
Role/Http/Requests/Central/CentralRoleStoreRequest.php
Role/Http/Requests/Central/CentralRoleUpdateRequest.php
Role/Services/Central/CentralRoleService.php
Role/Services/Contracts/Central/CentralRoleServiceInterface.php
Role/Repositories/Central/CentralRoleRepository.php
Role/Repositories/Contracts/Central/CentralRoleRepositoryInterface.php
Role/Database/Migrations/Central/{timestamp}_create_roles_table_final.php
Role/Database/Seeders/Central/CentralUserManagementRoleMigrationsList.php
Role/Database/Seeders/Central/CentralUserManagementRoleInlineAlters.php
Role/Database/Seeders/Central/CentralUserManagementRoleStageSeeder.php
Role/Database/Seeders/Central/CentralUserManagementRoleProductionSeeder.php
Role/Database/Seeders/Central/CentralUserManagementRolePermissionsSeeder.php
Role/Database/Seeders/Central/CentralUserManagementRoleData.php
Role/Database/Factories/Central/CentralRoleFactory.php
Role/Tests/Feature/Central/CentralRoleContract.php
Role/Tests/Feature/Central/CentralRoleTestCase.php
Role/Tests/Feature/Central/CentralRoleScaffoldTest.php
Role/Tests/Feature/Central/CentralRoleSchemaTest.php
Role/Tests/Feature/Central/CentralRolePermissionsTest.php
Role/Tests/Feature/Central/CentralRoleDeploymentTest.php
Role/Tests/Feature/Central/CentralRoleHttpTest.php
Role/resources/js/__tests__/Central/CentralRoleContract.js
Role/resources/js/__tests__/Central/CentralRoleIndex.test.js
Role/resources/js/Pages/Central/CentralRoleIndex.vue
Role/resources/js/Pages/Central/CentralRoleCreate.vue
Role/resources/js/Pages/Central/CentralRoleEdit.vue
Role/resources/js/Pages/Central/CentralRoleShow.vue
Role/Jobs/Central/.gitkeep
Role/Notifications/Central/.gitkeep
Role/Console/Commands/Central/.gitkeep
Role/Exceptions/Central/.gitkeep
```

Modificados: `Routes/web.php` + `Providers/Central/CentralUserManagementServiceProvider.php`
(inyección) + `config/make-module.php` → `deploy['central']` recibe `'UserManagement/Central/Role',`.

Con `--context=tenant`: mismo listado sustituyendo `Central` → `Tenant`, escribiendo en
`Routes/tenant.php`, con las mismas diferencias de fondo que en `make-module` (envoltura de
middleware, sin `$connection` en el modelo, prefijo de permiso).

### Ejemplos

**Caso: el módulo `UserManagement` ya existe con la entidad `User`; se agrega `Role`.**
```bash
php artisan innodite:add-entity UserManagement Role --context=central
```
```
✔ Generado Modules/UserManagement/Role/ (29 archivos)
✔ Modules/UserManagement/Routes/web.php actualizado
✔ Providers/Central/CentralUserManagementServiceProvider.php actualizado
✔ Registrado en el orden de despliegue: UserManagement/Central/Role
```

**Caso: la misma entidad, en el contexto del inquilino.**
```bash
php artisan innodite:add-entity UserManagement Role --context=tenant
```

**Caso: solo hace falta el modelo, el controlador y el servicio de la entidad nueva.**
```bash
php artisan innodite:add-entity UserManagement Permission --context=central -M -C -S
```
No genera rutas, vistas ni tests — solo esas tres capas.
