<?php

return [
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
