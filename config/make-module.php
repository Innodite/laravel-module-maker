<?php

return [
    'module_path' => base_path('Modules'),

    // Ruta donde se copian los stubs y archivos de configuración de ejemplo
    'default_config_path' => base_path('Modules/module-maker-config'),

    'stubs' => [
        'path' => base_path('Modules/module-maker-config/stubs'),
    ],

    // Ruta al archivo contexts.json del proyecto.
    // Publicado por innodite:setup en Modules/module-maker-config/contexts.json
    // Si no existe, el paquete usa el template incluido en stubs/contexts.json
    'contexts_path' => base_path('Modules/module-maker-config/contexts.json'),
];