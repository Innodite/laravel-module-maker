1. Executive Summary

El paquete innodite/laravel-module-maker es un motor de generación de código diseñado para ecosistemas Laravel multi-tenant. Su función principal es automatizar la creación de módulos segregados por contexto (Central, Shared, Tenant) garantizando la consistencia en nombres, rutas, migraciones y autorización tanto en Backend como en Frontend (Inertia/Vue).

2. Core Components (Backend)
2.1. Context Orchestrator (contexts.json)

Es el cerebro del paquete. Debe permitir definir:

    Contextos Fijos: central, shared, tenant_shared.

    Contextos Dinámicos: El nodo tenant: {} que contendrá configuraciones por cliente (ej: tenant1, geatel).

    Metadatos por Nodo: prefix, path, route_file, route_middleware y permission_prefix.

2.2. Intelligent Route Injector

El inyector debe ser "consciente" del entorno:

    Herencia de Seguridad: Si route_middleware es un array vacío [], no debe escribir el método ->middleware() en el bloque inyectado.

    Dualidad Shared: Capacidad de escribir el mismo bloque lógico en web.php y tenant.php simultáneamente.

    Uso de Namespaces: Importar automáticamente los Controllers correspondientes al inicio del archivo de rutas.

2.3. Migration & Seeder Orchestrator

Basado en order.json, el paquete debe:

    Resolver Coordenadas: Traducir Modulo:Contexto/Archivo a una ruta real en Modules/.

    Jerarquía de Ejecución: Implementar la lógica de "Inyección de Capas" (Shared -> Central o Shared -> TenantShared -> TenantSpecific).

    Sync Tool: Un comando que detecte archivos huérfanos y los proponga para el manifiesto de orden.

3. Core Components (Frontend Bridge)
3.1. The Inertia Bridge Middleware

El paquete proveerá un middleware que realice el Inertia::share() de:

    auth.context: Objeto con route_prefix y permission_prefix detectados por la URL/Ruta actual.

    auth.permissions: Array plano extraído del usuario autenticado.

3.2. Standard Composables (Stubs)

    useModuleContext.js: Implementar contextRoute(name) para prefijar rutas dinámicamente.

    usePermissions.js: Implementar can(action) que valide automáticamente contra prefix.action.

4. Automation Commands

    innodite:add-entity: Prompt interactivo para elegir contexto, nombre de entidad y campos.

    innodite:migrate: Ejecución jerárquica con flags --all y --seed.

    innodite:publish-frontend: Instalación de composables y verificación de package.json.

    innodite:check-env: Diagnóstico de salud del sistema (Traits en User, Middlewares en Kernel, Configuración de Inertia).

5. Technical Constraints

    Namespaces: Deben seguir estrictamente la estructura PSR-4 basada en la carpeta física del contexto.

    Database Normalization: Los modelos DEBEN forzar el nombre de la tabla original para que el prefijo del modelo no altere la base de datos.

    Inertia Path: Los componentes Vue deben generarse en Resources/js/Pages/ siguiendo la misma subestructura de carpetas del contexto.