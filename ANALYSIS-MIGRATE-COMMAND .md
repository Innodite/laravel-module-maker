
Fase 5: Orquestación Global de Infraestructura (Migrations & Seeders Unified)


        Esta fase centraliza el control de la estructura (tablas) y los datos iniciales (seeders) en la raíz del proyecto, utilizando un sistema de "coordenadas lógicas" para garantizar que el orden de ejecución respete las dependencias de negocio entre múltiples módulos.

        1. El Manifiesto Maestro y su Nomenclatura (order.json)


        El control de ejecución se traslada a base_path('module-maker-config/migrations/'). El motor utiliza una sintaxis de tres partes: [Modulo]:[Contexto]/[Archivo], permitiendo localizar cualquier recurso en el ecosistema de módulos.


        Casos de Uso de la Nomenclatura:

        Contexto    Ejemplo de Registro en el JSON  Ruta Física (Path)

        Shared  User:Shared/2026_01_01_create_users.php Modules/User/Database/Migrations/Shared/...

        Central Admin:Central/2026_01_01_create_logs.php    Modules/Admin/Database/Migrations/Central/...

        Tenant Shared   Core:Tenant/Shared/2026_01_01_settings.php  Modules/Core/Database/Migrations/Tenant/Shared/...

        Tenant Specific Custom:Tenant/INNODITE/2026_01_01_extra.php Modules/Custom/Database/Migrations/Tenant/INNODITE/...


        2. Estructura Unificada de los Archivos de Orden (Ejemplo Multicontexto)


        Cada manifiesto (central_order.json, tenant_shared_order.json, etc.) gestiona tanto la creación de tablas como el sembrado de datos. Así se vería una configuración que cubre todos los casos:

        JSON


        {

        "migrations": [

            "User:Shared/2026_01_01_000001_create_users_table.php",

            "Admin:Central/2026_01_01_000002_create_system_logs_table.php",

            "Roles:Tenant/Shared/2026_02_01_000001_create_tenant_roles_table.php",

            "Custom:Tenant/INNODITE/2026_03_01_000001_innodite_extra_table.php"

        ],

        "seeders": [

            "User:Shared/SharedUserSeeder",

            "Admin:Central/CentralAdminSeeder",

            "Roles:Tenant/Shared/TenantSharedRoleSeeder",

            "Custom:Tenant/INNODITE/TenantINNODITECustomSeeder"

        ]

        }




        3. Automatización y Sincronización


            Auto-Append: Al crear una entidad con innodite:add-entity, el paquete registra automáticamente la migración y el seeder en el archivo de orden correspondiente (central, shared o tenant) según el contexto elegido en el prompt.


            Comando innodite:migration-sync: Escanea todos los módulos en busca de archivos de base de datos no registrados. Genera sus coordenadas y las añade al final de los manifiestos globales para que el desarrollador solo deba ajustar el orden de prioridad manualmente.


        4. Operaciones Masivas y Rollbacks


            Modo All-Tenants (--all): Permite ejecutar el flujo completo (Migración + Seeders) para todos los clientes definidos en el contexts.json de forma automática, ideal para despliegues masivos.


            Rollback Atómico: Realiza la operación inversa de la jerarquía de capas. Si se revierte un Tenant específico, primero se eliminan sus tablas exclusivas, luego las de Tenant Shared y finalmente las de Shared.


            Flag --seed: Integración nativa en los comandos de migración para activar el sembrado de datos inmediatamente después de la creación de las tablas, respetando el orden definido en el JSON. 