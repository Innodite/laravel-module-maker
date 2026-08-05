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
];
