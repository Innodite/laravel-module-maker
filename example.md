{
    "_readme": "Configuración de contextos para NeoCenter ERP (Geatel Telecom). Copia este archivo a Modules/module-maker-config/contexts.json después de ejecutar innodite:module-setup.",

    "contexts": {
        "central": {
            {
            "name": "tst3",
            "class_prefix": "Central",
            "folder": "Central",
            "namespace_path": "Central",
            "route_prefix": "central",
            "route_name": "central.",
            "permission_prefix": "central",
            "permission_middleware": "central-permission",
            "route_middleware": ["web", "auth"],
            "wrap_central_domains": true
            }
        },

        "shared": {
            {
            "name": "Teste4",
            "class_prefix": "Shared",
            "folder": "Shared",
            "namespace_path": "Shared",
            "route_prefix": "shared",
            "route_name": "shared.",
            "permission_prefix": "shared",
            "permission_middleware": "central-permission",
            "route_middleware": ["web", "auth"],
            "wrap_central_domains": false
            },
            {
            "name": "Point",
            "class_prefix": "SharedPoint",
            "folder": "Shared/Point",
            "namespace_path": "Shared\\Point",
            "route_prefix": "shared-point",
            "route_name": "shared-point.",
            "permission_prefix": "",
            "permission_middleware": "",
            "route_middleware": {
                  "web",              
                "auth"
        }
        },

        "tenant": {
            {
            "label": "Tes2",
            "class_prefix": "TenantEnergySpain",
            "folder": "Tenant/EnergySpain",
            "namespace_path": "Tenant\\EnergySpain",
            "route_prefix": "energy-spain",
            "route_name": "energy-spain.",
            "permission_prefix": "energy_spain",
            "permission_middleware": "tenant-permission",
            "route_middleware": [
                "web",
                "Stancl\\Tenancy\\Middleware\\InitializeTenancyByDomain::class",
                "Stancl\\Tenancy\\Middleware\\PreventAccessFromCentralDomains::class",
                "auth",
                "tenant-auth"
            ],
            
           
        },

        "tenant_shared": {
            {
            
            "name": "Test",
            "class_prefix": "TenantShared",
            "folder": "Tenant/Shared",
            "namespace_path": "Tenant\\Shared",
            "route_prefix": null,
            "route_name": null,
            "permission_prefix": null,
            "permission_middleware": "tenant-permission",
            "route_middleware": [
                "web",
                "Stancl\\Tenancy\\Middleware\\InitializeTenancyByDomain::class",
                "Stancl\\Tenancy\\Middleware\\PreventAccessFromCentralDomains::class",
                "auth",
                "tenant-auth"
            ],
            "wrap_central_domains": false
            }
        }
    },

   
}


Esto deberia funcionar asi 

yo ejecuto el comando 

- el me pregunta en que context quieres crear el modulo ?

- me lista todos los contextos para seleccionar 1 

- qluego me pregunta busco estructura en el json  o lo hago automatico 

- si le digo que use el json me pregunta el nombre  si le digo uno que este en el arrelgo 
- si elijo automatico e entonces aggrega el json con el estandar por defefecto imaginemos que elegi el contexto shared yy moodulo se llama Point 



CREACION DE ARCHIVOS INDIVIDUALES

PHP ARTISAN INNODITE:MAKE MODULO -M -C -R -S 

SELECCIONA EL CONTEXT 1) CENTRAL 2)SHARED  ETC.  SELECCIONE SHARED 

LE DAS Y TE PREGUNTA QUIERES QUE USE LE NOMBRE DEL MODULO O QUIERES UNO PARTICULAR ?

SI SELECCIONA PARTICULAR  TOKENS 

ENTONCES SEGUN LOS FLAG CREA MIGRATION CONTROLLER REPOSITORY Y SERVICE

SHARED/MODULE/HTTP/CONTROLLER/SHAREDTOKENSCONTROLLER.PHP

QUDA CLARA LA IDEA?

