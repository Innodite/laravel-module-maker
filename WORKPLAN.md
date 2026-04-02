# WORKPLAN — Laravel Module Maker

**Versión objetivo:** v3.0.0
**Última actualización:** 2026-03-28

---

Fase 1: Arquitectura de Directorios y Documentación

Cada módulo generado debe implementar la siguiente estructura de carpetas. El motor de generación creará subcarpetas basadas en el contexto elegido (Central, Shared, Tenant Shared o Tenant {Name}).

1. Documentación (Docs/)

Cada módulo nace con tres archivos maestros de seguimiento (única carpeta no segregada por contexto):

    history.md: Registro cronológico de cambios y versiones.

    architecture.md: Decisiones técnicas y diagramas de flujo.

    schema.md: Diccionario de datos y relaciones de base de datos.

2. Capa de Datos y Lógica (Database/, Models/, Repositories/)

    Database/: Contiene Factories/, Migrations/ y Seeders/, cada una con subcarpetas por contexto.

        Estructura Tenant: Dentro de la carpeta Tenant/ existirán Shared/ y carpetas específicas por cliente (ej: INNODITE/).

        Regla de Oro: Las migraciones mantienen el nombre original de la tabla para asegurar la normalización.

    Models/: Organizado por subcarpetas de contexto (incluyendo específicas por Tenant).

        Regla de Oro: El modelo generado debe incluir obligatoriamente protected $table = 'nombre_original';.

    Repositories/:

        Contracts/: Subcarpetas de contexto con las interfaces.

        Implementaciones: Subcarpetas de contexto con las clases concretas.

3. Capa de Servicio y Procesos (Services/, Jobs/, Console/)

    Services/:

        Contracts/: Subcarpetas de contexto con las interfaces.

        Implementaciones: Subcarpetas de contexto con la lógica de negocio.

    Jobs/ y Console/Commands/: Organizados estrictamente por subcarpetas de contexto para procesos y comandos específicos.

4. Capa de Entrada y Rutas (Http/, Routes/)

    Http/: Controllers/, Middleware/ y Requests/, todos divididos por las subcarpetas de contexto (incluyendo específicas por Tenant).

    Routes/: Se generan tres archivos base en la raíz del módulo:

        web.php: Para rutas centrales/administrativas.

        tenant.php: Para la lógica específica de clientes (Shared y Específicos).

        api.php: (Opcional/Soporte) Para exposición de servicios externos.

5. Gestión y Configuración del Paquete

    Ubicación de Configuración: La carpeta module-maker-config/ se ubica en la raíz del proyecto (base_path()).

    Gestión de Stubs: El paquete buscará primero stubs personalizados en module-maker-config/stubs/contextual/. Si no existen, usará los stubs por defecto.

    Registro Automático: El InnoditeServiceProvider debe realizar el discovery de todas las subcarpetas de contexto para registrar rutas y migraciones automáticamente.


Fase 2: Convenciones de Nombres y Prefijos (Identidad Universal)

    Define cómo se crea cada archivo en cualquier capa del sistema para identificarlo visualmente y asegurar la segregación total por contexto.
    1. Tabla de Identidad y Rutas
    Contexto    Prefijo de Clase    Carpeta de Destino  Archivo(s) de Ruta
    Central Central Central/    web.php
    Shared  Shared  Shared/ web.php Y tenant.php (Dual)
    Tenant Shared   TenantShared    Tenant/Shared/  tenant.php
    Tenant {Name}   Tenant{Name}    Tenant/{Name}/  tenant.php

    2. Reglas Estrictas de Generación (Aplicables a todas las capas)

    Prefijo Universal: Todo archivo generado (Controllers, Services, Repositories, Jobs, Commands, Requests, etc.) DEBE llevar el prefijo correspondiente según el contexto o el nombre del Tenant.

        Ejemplo: TenantINNODITEUserRequest.php, CentralUserStoreRequest.php.

    Modelos Eloquent: La clase lleva el prefijo (ej: TenantINNODITEUser.php), pero para mantener la base de datos normalizada, debe incluir obligatoriamente: protected $table = 'nombre_original_en_plural';.

    Interfaces (Contracts): Se generan obligatoriamente dentro de una carpeta Contracts/ que respete la misma subestructura de contexto.

        Ejemplo: Services/Contracts/Tenant/INNODITE/TenantINNODITEUserServiceInterface.php.

     Namespaces Dinámicos: El generador debe calcular el namespace según la ruta física. (Ej: namespace Modules\User\Http\Controllers\Tenant\INNODITE;).

 
    Componentes Vue: Ubicados en Resources/js/Pages/{Contexto}/{Entidad}/ con el prefijo de clase (Ej: TenantINNODITEUserList.vue).

        Ejemplo: Resources/js/Pages/Tenant/INNODITE/User/TenantINNODITEUserList.vue.

Fase 3: Motor de Inyección de Rutas (Contextual & Dual Registration)

        Define la lógica para que el paquete escriba automáticamente en web.php y tenant.php utilizando marcadores de posición (// {{MARKER}}) para garantizar que las rutas queden protegidas por los middlewares correctos.

        1.Reglas de Inyección y Herencia según Contexto

            Contexto Shared (Dual Registration): Inyecta el bloque en ambos archivos. IMPORTANTE: Si route_middleware en el JSON está vacío, el bloque inyectado NO incluirá ->middleware(), heredando la seguridad del grupo padre en web.php o tenant.php.

            Contexto "Tenant {Name}" (Específico): Inyecta exclusivamente en tenant.php, preferiblemente dentro de un grupo identificado para ese cliente.

            Contexto "Central": Inyecta exclusivamente en web.php dentro del grupo de dominios administrativos.

            Marcadores:

            web.php: // {{CENTRAL_ROUTES_END}}

            tenant.php (Shared): // {{TENANT_SHARED_ROUTES_END}}

            tenant.php (Específico): // {{TENANT_{NAME}_ROUTES_END}}

        2. Ejemplo de Estructura Final en web.php (Central/Shared)

        El generador debe buscar el marcador y realizar el append manteniendo la indentación:
        PHP

        foreach (config('tenancy.central_domains') as $domain) {
            Route::domain($domain)->middleware(['web', 'auth'])->group(function () {
                
                // Rutas Centrales existentes...

                // {{CENTRAL_ROUTES_END}}
                // Bloque generado para: Roles (Contexto: Shared)
                $routePrefix = 'central.roles';
                $permissionPrefix = 'central.roles.';
                Route::prefix($routePrefix)->name($routePrefix.'.')->group(function () use ($permissionPrefix) {
                    Route::get('/', [SharedRoleController::class, 'index'])->name('index');
                });
            });
        }

        3. Ejemplo de Estructura Final en tenant.php (Shared/Specific)

        Aquí es donde se gestiona la separación de lo que ven todos los clientes vs lo que ve uno solo:
        PHP

        Route::middleware(['web', 'auth', 'tenant.initialize'])->group(function () {

            // --- SECCIÓN SHARED (Todos los Tenants) ---
            // {{TENANT_SHARED_ROUTES_END}}
            $routePrefix = 'tenant.roles';
            Route::prefix($routePrefix)->name($routePrefix.'.')->group(function () {
                Route::get('/', [SharedRoleController::class, 'index'])->name('index');
            });

            // --- SECCIÓN ESPECÍFICA (Por Cliente) ---
            // {{TENANT_INNODITE_ROUTES_END}}
            $routePrefix = 'innodite.custom-reports';
            $permissionPrefix = 'innodite.reports.';
            Route::prefix($routePrefix)->name($routePrefix.'.')->group(function () use ($permissionPrefix) {
                Route::get('/', [TenantINNODITEReportController::class, 'index'])->name('index');
            });
        });

        4. Estructura Estándar del Bloque Inyectado

        Todo bloque inyectado debe seguir este patrón para ser consistente:
        PHP

        // Bloque generado para: {{Entity}} (Contexto: {{Context}})
        $routePrefix = '{{route_prefix}}'; 
        $permissionPrefix = '{{permission_prefix}}';
        $middleware = {{route_middleware}}; 

        Route::prefix($routePrefix)
            ->name($routePrefix . '.')
            ->middleware($middleware)
            ->group(function () use ($permissionPrefix) {
                Route::get('/', [{{ClassName}}Controller::class, 'index'])->name('index');
                Route::get('/create', [{{ClassName}}Controller::class, 'create'])->name('create');
                Route::post('/', [{{ClassName}}Controller::class, 'store'])->name('store');
                Route::get('/{id}/edit', [{{ClassName}}Controller::class, 'edit'])->name('edit');
                Route::put('/{id}', [{{ClassName}}Controller::class, 'update'])->name('update');
                Route::delete('/{id}', [{{ClassName}}Controller::class, 'destroy'])->name('destroy');
            });

Fase 4: Flexibilidad de Infraestructura y Middlewares

        Garantiza que el paquete sea compatible tanto con el stack de la empresa (stancl/tenancy) como con cualquier otra implementación personalizada.
        1. Configuración vía contexts.json

            El generador no debe tener middlewares "hardcoded". Leerá estas llaves desde el archivo de configuración global:

                route_middleware: Array de strings con los middlewares (ej: ['web', 'auth', 'central-permission']).

                wrap_central_domains: Booleano. Si es true, el generador envolverá el código en el bucle: foreach (config('tenancy.central_domains') as $domain).

                permission_middleware: Define qué middleware de permisos inyectar en el array de rutas.

        1.1. Estructura Maestra del Archivo contexts.json

            El paquete debe inicializar y leer un archivo de configuración en module-maker-config/contexts.json. Este archivo define el comportamiento de los comandos de generación y la inyección de rutas.

            Modelo de Referencia:
            JSON

            {
                "central": {
                    "prefix": "Central",
                    "path": "Central/",
                    "route_file": "web.php",
                    "route_middleware": ["web"],
                    "permission_prefix": "central",
                    "wrap_central_domains": true
                },
                "shared": {
                    "prefix": "Shared",
                    "path": "Shared/",
                    "route_file": ["web.php", "tenant.php"],
                    "route_middleware": [],
                    "permission_prefix": ""
                },
                "tenant_shared": {
                    "prefix": "TenantShared",
                    "path": "Tenant/Shared/",
                    "route_file": "tenant.php",
                    "route_middleware": [],
                    "permission_prefix": "tenant"
                },
                tenant:{
                    "tenant1": {
                        "prefix": "Tenant{Name}",
                        "path": "Tenant/{Name}/",
                        "route_file": "tenant.php",
                        "route_middleware": ["web"],
                        "permission_prefix": "{name}"
                    },
                    "tenant2": {
                        "prefix": "Tenant{Name}",
                        "path": "Tenant/{Name}/",
                        "route_file": "tenant.php",
                        "route_middleware": ["web"],
                        "permission_prefix": "{name}"
                    },
                }
            }

                Regla de Inyección Shared: Si route_middleware es un array vacío, el bloque inyectado en web.php y tenant.php no debe incluir el método ->middleware(), delegando la seguridad al grupo padre.
            1.2. Abstracción de Permisos (Agnosticismo):
                El archivo contexts.json debe incluir el campo permission_prefix. Este campo no está amarrado a ninguna librería externa (como Spatie), sino que sirve como una etiqueta de metadatos que el paquete inyectará en las rutas y compartirá con el Frontend (vía Inertia) para que la lógica de autorización sea coherente con el contexto actual.

        2. Comportamiento de Fallback (Empresa)

        Si el usuario no define valores específicos en el JSON, el paquete aplicará por defecto:

            Para Central: Envoltorio de dominios activado y middlewares estándar de la empresa.

            Para Tenant: Middlewares de inicialización de tenant y autenticación de cliente.

        3. Inyección en HandleInertiaRequests

        El comando innodite:module-setup debe ofrecer la opción de publicar un Middleware o modificar el existente para compartir los prefijos (route_prefix, permission_prefix) con el frontend automáticamente, permitiendo que la vista sea agnóstica al contexto.

Fase 5: Orquestación Global de Infraestructura (Migrations & Seeders Unified)

        Esta fase centraliza el control de la estructura (tablas) y los datos iniciales (seeders) en la raíz del proyecto, utilizando un sistema de "coordenadas lógicas" para garantizar que el orden de ejecución respete las dependencias de negocio entre múltiples módulos.
        1. El Manifiesto Maestro y su Nomenclatura (order.json)

        El control de ejecución se traslada a base_path('module-maker-config/migrations/'). El motor utiliza una sintaxis de tres partes: [Modulo]:[Contexto]/[Archivo], permitiendo localizar cualquier recurso en el ecosistema de módulos.

        Casos de Uso de la Nomenclatura:
        Contexto    Ejemplo de Registro en el JSON  Ruta Física (Path)
        Shared  User:Shared/2026_01_01_create_users.php Modules/User/Database/Migrations/Shared/...
        Central Admin:Central/2026_01_01_create_logs.php    Modules/Admin/Database/Migrations/Central/...
        Tenant Shared   Core:Tenant/Shared/2026_01_01_settings.php  Modules/Core/Database/Migrations/Tenant/Shared/...
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

        3. Lógica de Ejecución por "Inyección de Capas"

        El comando innodite:migrate construye dinámicamente la "escalera de ejecución" según el contexto solicitado, asegurando que las tablas compartidas existan antes que las específicas:

            Para Central: Ejecuta shared_order.json (Migraciones -> Seeders) + central_order.json (Migraciones -> Seeders).

            Para Tenant (Específico): Ejecuta shared_order.json + tenant_shared_order.json + El bloque del cliente en tenant_specific_order.json.

        4. Automatización y Sincronización

            Auto-Append: Al crear una entidad con innodite:add-entity, el paquete registra automáticamente la migración y el seeder en el archivo de orden correspondiente (central, shared o tenant) según el contexto elegido en el prompt.

            Comando innodite:migration-sync: Escanea todos los módulos en busca de archivos de base de datos no registrados. Genera sus coordenadas y las añade al final de los manifiestos globales para que el desarrollador solo deba ajustar el orden de prioridad manualmente.

        5. Operaciones Masivas y Rollbacks

            Modo All-Tenants (--all): Permite ejecutar el flujo completo (Migración + Seeders) para todos los clientes definidos en el contexts.json de forma automática, ideal para despliegues masivos.

            Rollback Atómico: Realiza la operación inversa de la jerarquía de capas. Si se revierte un Tenant específico, primero se eliminan sus tablas exclusivas, luego las de Tenant Shared y finalmente las de Shared.

            Flag --seed: Integración nativa en los comandos de migración para activar el sembrado de datos inmediatamente después de la creación de las tablas, respetando el orden definido en el JSON.
Fase 6: Client-Side Bridge (Vue & Inertia Infrastructure)
             Esta fase automatiza la entrega de herramientas de Frontend (Composables) para que los componentes Vue interactúen con los contextos de forma nativa, eliminando la necesidad de escribir rutas o prefijos estáticos en la interfaz de usuario.

             1. Sincronización Automática (Middleware Bridge)

                El paquete incluye un middleware que detecta la ruta actual y comparte vía Inertia::share():

                    auth.context.route_prefix: El prefijo de ruta activo.

                    auth.context.permission_prefix: El prefijo de permiso (Ej: innodite).

                    auth.permissions: Array plano de permisos del usuario (Detección automática de Spatie o Interface InnoditeUserPermissions).

            2. El Nodo Maestro de Datos (Inertia Shared)

                Para que el Composable usePermissions.js no falle, el paquete exigirá que los permisos residan en una ubicación única. No permitiremos variaciones para evitar errores de "undefined".

                La ubicación obligatoria será: page.props.auth.permissions (un array plano de strings).
           

           
            3. Los Composables de Infraestructura (Paquete Stubs)

            El paquete incluirá y mantendrá dos archivos base que abstraen la lógica de rutas y permisos. Estos archivos se inyectan en el proyecto del usuario en resources/js/Composables/.

                useModuleContext.js:

                    Propósito: Gestionar la ubicación lógica actual (Central, Shared o Tenant).

                    Helper contextRoute(name): Recibe el nombre de la ruta (ej: roles.index) y le concatena automáticamente el prefijo del contexto activo (ej: admin.roles.index o innodite.roles.index).

                    Propiedad routePrefix: Expone el prefijo actual para etiquetas de UI o lógica condicional.

                usePermissions.js:

                    Propósito: Validar permisos de forma contextual y transparente.

                    Función can(permission): Concatena el permission_prefix del contexto actual antes de verificar contra la lista de permisos del usuario en las props de Inertia. Permite validar permisos específicos (innodite.roles.create) y compartidos (roles.view) en una sola llamada.
            3. Diagnóstico y Validación
            
                innodite:check-env: Verifica si el modelo User tiene los traits/interfaces necesarios y si Inertia está recibiendo el nodo auth.permissions.

        
            4. Sincronización Automática de Datos (Middleware Bridge)

            El paquete garantiza que el objeto de contexto siempre esté disponible en el Frontend. El Service Provider del paquete ofrecerá un Trait o una configuración para el HandleInertiaRequests.php que inyecte automáticamente:
            PHP

            'auth' => [
                'context' => [
                    'route_prefix' => $currentContext->route_prefix,
                    'permission_prefix' => $currentContext->permission_prefix,
                ],
                'permissions' => $user->getAllPermissions()->pluck('name'),
            ]

            4. Automatización de "Provisioning" (innodite:publish-frontend)

                 Se implementará un comando específico para preparar el frontend del proyecto:

                    Detección de Entorno: Verifica la existencia de la carpeta de recursos de Vue.

                    Instalación de Stubs: Copia los archivos useModuleContext.js y usePermissions.js.

                    Verificación de Dependencias: Avisa si faltan paquetes necesarios (como @inertiajs/vue3).

                4.1. Contrato de Datos Frontend:
                    El paquete establece como estándar absoluto que el array de permisos debe viajar en el nodo auth.permissions. El Composable usePermissions.js debe incluir un console.error descriptivo si detecta que page.props.auth existe pero permissions es undefined.

                4.2. El comando innodite:check-env:
                    Se implementará un comando de diagnóstico que verifique:

                        Si el modelo User tiene los Traits necesarios.

                        Si los permisos se están compartiendo correctamente en el HandleInertiaRequests.

                        Sugerir el código exacto que el usuario debe pegar en su Middleware de Inertia si la detección automática falla.

            5. Metodología de Uso Genérico en Vistas

            Cualquier vista del sistema (central o tenant) utilizará una sintaxis estandarizada:

                Navegación: :href="route(contextRoute('roles.edit'), id)"

                Formularios: form.post(route(contextRoute('roles.store')))

                Acceso: v-if="can('roles.delete')"

            Esta metodología permite que un mismo componente .vue funcione en cualquier contexto sin modificar una sola línea de código, delegando la responsabilidad de la "identidad" al paquete.

            6. Detección Automática de Capacidades (Backend)

                El paquete, al ejecutarse (vía ServiceProvider), realizará una validación en cadena:

                    Detección de Librerías: El paquete revisará si existe la clase Spati\Permission\PermissionServiceProvider. Si existe, el paquete asumirá que puede usar el método getAllPermissions().

                    Validación del Modelo User: Si no detecta Spatie, el paquete verificará si el modelo User implementa una Interface propia del paquete (ej: InnoditeUserPermissions).

                    Advertencia en Consola: Si el desarrollador intenta usar comandos de generación y el paquete detecta que no hay un sistema de permisos vinculado, lanzará un Warning crítico:

                        "⚠️ No se detectó un sistema de permisos (Spatie o Interface Innodite). El Composable de Vue no funcionará correctamente hasta que compartas el array 'auth.permissions' en Inertia."