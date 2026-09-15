```bash
php artisan innodite:add-entity {module} {entity} {--context=} [-M] [-C] [-S] [-R] [-G] [-Q]
```

### Qué hace

Agrega una entidad nueva a un módulo **ya existente**, generando sus componentes dentro de la
subcarpeta de la entidad. Valida que el módulo destino exista antes de generar. Respeta lo que ya
hay en el proyecto: no sobrescribe los enlaces del proveedor del módulo ni las pantallas existentes.

Genera exactamente las mismas capas que `innodite:make-module`, para la entidad nueva.

### Parámetros

| Parámetro | Descripción |
|---|---|
| `module` | Nombre del módulo existente (ej. `UserManagement`) |
| `entity` | Nombre de la entidad nueva, PascalCase (ej. `Role`) |
| `--context=` | `central` \| `tenant` \| el que declare el proyecto. Obligatorio en `multitenant` |
| `-M` a `-Q` | Mismos flags que `make-module`. Sin flags, genera todos los componentes |

### Qué genera

```
Modules/UserManagement/
└── Role/
    ├── Models/Central/CentralRole.php
    ├── Http/Controllers/Central/CentralRoleController.php
    ├── Http/Requests/Central/CentralRoleStoreRequest.php
    ├── Http/Requests/Central/CentralRoleUpdateRequest.php
    ├── Services/Central/CentralRoleService.php
    ├── Services/Contracts/Central/CentralRoleServiceInterface.php
    ├── Repositories/Central/CentralRoleRepository.php
    ├── Repositories/Contracts/Central/CentralRoleRepositoryInterface.php
    └── Database/Migrations/Central/..._create_roles_table.php
```

Además inyecta la entidad en el proveedor del módulo (`UserManagementServiceProvider`) sin tocar
los enlaces que ya tenía.

### Personalizar stubs

Mismo mecanismo y mismo orden de resolución que `innodite:make-module` — ver su ficha.

### Ejemplos

**Caso: el módulo `UserManagement` ya existe con la entidad `User`; se agrega `Role`.**
```bash
php artisan innodite:add-entity UserManagement Role --context=central
```

**Caso: la misma entidad, en el contexto del inquilino.**
```bash
php artisan innodite:add-entity UserManagement Role --context=tenant
```

**Caso: solo hace falta el modelo, el controlador y el servicio de la entidad nueva.**
```bash
php artisan innodite:add-entity UserManagement Permission --context=central -M -C -S
```
