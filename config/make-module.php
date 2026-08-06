<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Modo del proyecto — se elige UNA vez, al instalar
    |--------------------------------------------------------------------------
    |
    | Decide la forma de todo lo que se genera: si existe el eje de contexto
    | (Central/Tenant), si los nombres de clase llevan prefijo, si un tenant
    | declara su propia conexión y qué middleware protege cada ruta.
    |
    | Ningún comando lo adivina leyendo el proyecto ni asume uno por defecto:
    | adivinar produce archivos que parecen correctos y están mal en el único
    | sitio que nadie revisa, multiplicado por cada módulo. Sin modo elegido,
    | los comandos se niegan a generar y dicen cómo elegirlo.
    |
    |   'single-app'              Aplicación única, sin tenancy
    |   'multitenant-shared'      Todos los tenants comparten funcionalidad
    |   'multitenant-per-tenant'  Cada tenant con lógica de negocio propia
    |
    */
    'mode' => env('MODULE_MAKER_MODE'),

    /*
    |--------------------------------------------------------------------------
    | Ruta raíz de todos los módulos del proyecto
    |--------------------------------------------------------------------------
    */
    'module_path' => base_path('Modules'),

    /*
    |--------------------------------------------------------------------------
    | Ruta de la carpeta de configuración del paquete (project root)
    | Publicada por: php artisan innodite:module-setup
    |--------------------------------------------------------------------------
    */
    'config_path' => base_path('module-maker-config'),

    /*
    |--------------------------------------------------------------------------
    | Ruta al archivo contexts.json del proyecto.
    | Si no existe, el paquete usa el template incluido en stubs/contexts.json
    |--------------------------------------------------------------------------
    */
    'contexts_path' => base_path('module-maker-config/contexts.json'),

    /*
    |--------------------------------------------------------------------------
    | Ruta base donde el paquete buscará stubs personalizados.
    | Estructura esperada: {stubs_path}/contextual/{stub_file}
    |--------------------------------------------------------------------------
    */
    'stubs' => [
        'path' => base_path('module-maker-config/stubs'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Orden de despliegue — lo declaras tú, y se lee de arriba abajo
    |--------------------------------------------------------------------------
    |
    | Cada línea es la CARPETA de una subfuncionalidad, no un nombre de clase:
    |
    |     'UserManagement/Central/Role'     donde hay eje de contexto
    |     'Invoice/Invoice'                 donde no lo hay
    |
    | Por carpeta y no por FQCN para que renombrar una clase no rompa esta lista
    | —y para escribir mucho menos—. De ahí salen las tres piezas ejecutables de
    | cada subfuncionalidad: quien llama añade Stage, Production o Permissions
    | según lo que esté desplegando.
    |
    | EL ORDEN IMPORTA y el paquete no puede adivinarlo: una tabla con clave
    | foránea no puede sembrarse antes que aquella a la que apunta, y eso lo sabe
    | el negocio. `make-module` añade cada subfuncionalidad nueva AL FINAL;
    | moverla a su sitio es tuyo.
    |
    | Los marcadores son donde se añaden las entradas nuevas. Si los borras, el
    | comando te dice qué línea escribir a mano en vez de fallar.
    |
    | Con contextos se agrupa por contexto, y el maestro de cada uno lee el suyo:
    |
    |     'deploy' => [
    |         'central' => [
    |             'UserManagement/Central/Role',
    |             // {{DEPLOY_CENTRAL_END}}
    |         ],
    |         'tenant' => [
    |             'Invoice/Tenant/Shared/Invoice',
    |             // {{DEPLOY_TENANT_END}}
    |         ],
    |         // {{DEPLOY_END}}
    |     ],
    |
    */
    'deploy' => [
        // {{DEPLOY_END}}
    ],

    /*
    |--------------------------------------------------------------------------
    | El webmaster — el rol que lo puede todo
    |--------------------------------------------------------------------------
    |
    | Su seeder recoge TODOS los permisos que existan en la base donde corra,
    | así que un módulo generado mañana queda cubierto sin tocar ninguna lista.
    | Lo único que no puede adivinar es quién es esa persona.
    |
    | Se declara aquí, y no se lee del entorno dentro del seeder, porque un
    | proyecto en producción cachea la configuración (`config:cache`) y entonces
    | el `.env` ya no se carga: un `env()` fuera de config/ devolvería el valor
    | por defecto y el webmaster se crearía con OTRO correo y OTRA contraseña,
    | sin un solo error. Leído desde aquí, el valor queda horneado en la caché.
    |
    | `password` sin declarar NO es un problema: al crear el usuario se genera
    | una aleatoria y se imprime una sola vez. Lo que no puede haber es una
    | contraseña por defecto escrita en el código, igual en cada instalación.
    |
    */
    'webmaster' => [
        'role'     => env('WEBMASTER_ROLE', 'webmaster'),
        'name'     => env('WEBMASTER_NAME', 'Webmaster'),
        'email'    => env('WEBMASTER_EMAIL', 'webmaster@innodite.local'),
        'password' => env('WEBMASTER_PASSWORD'),
    ],
];
