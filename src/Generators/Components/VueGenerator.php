<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Generators\Components;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Innodite\LaravelModuleMaker\Support\Frontend;
use Innodite\LaravelModuleMaker\Support\ModuleMode;
use Innodite\LaravelModuleMaker\Support\SubFeaturePermissions;

/**
 * Genera los 4 componentes Vue de una subfuncionalidad: **una pantalla y tres modales**.
 *
 * Arquitectura Frontend (regla obligatoria):
 *   - Inertia.js SOLO para navegación entre páginas (router.visit)
 *   - Todos los datos via axios (GET, POST, PUT, DELETE)
 *   - Las vistas son "shells" que se autocargan al montarse
 *
 * Archivos generados por contexto (ejemplo central, entidad User):
 *   resources/js/Pages/Central/CentralUserIndex.vue   → la pantalla: lista paginada
 *   resources/js/Pages/Central/CentralUserCreate.vue  → modal de alta, montado por el índice
 *   resources/js/Pages/Central/CentralUserEdit.vue    → modal de edición, montado por el índice
 *   resources/js/Pages/Central/CentralUserShow.vue    → modal de detalle, montado por el índice
 *
 * Los tres últimos **no son pantallas** y no tienen ruta propia: se importan desde el índice y se
 * abren encima de él. Es lo que encaja con las rutas que el paquete genera —de las seis, solo
 * `index` devuelve una pantalla; las otras cinco devuelven datos—, y por eso no existen `create`
 * ni `edit` en el controlador. Describirlos como pantallas aparte fue lo que sostuvo durante toda
 * la v3 un bloque de rutas inyectado que apuntaba a dos métodos inexistentes.
 */
class VueGenerator extends AbstractComponentGenerator
{
    protected string $modelName;

    private const VIEWS = [
        'index'  => 'vue-index.stub',
        'create' => 'vue-create.stub',
        'edit'   => 'vue-edit.stub',
        'show'   => 'vue-show.stub',
    ];

    public function __construct(
        string $moduleName,
        string $modulePath,
        bool $isClean,
        string $modelName,
        array $componentConfig = []
    ) {
        parent::__construct($moduleName, $modulePath, $isClean, $componentConfig);
        $this->modelName = Str::studly($modelName);
    }

    /**
     * Genera los 4 componentes Vue en la carpeta de páginas del contexto.
     */
    public function generate(): void
    {
        $outputDir = $this->buildPagesPath();
        $this->ensureDirectoryExists($outputDir);

        $placeholders = $this->buildPlaceholders();

        foreach (self::VIEWS as $suffix => $stubFile) {
            $componentName = $this->prefixClass("{$this->modelName}") . Str::ucfirst($suffix);
            $targetFile    = "{$outputDir}/{$componentName}.vue";

            if (File::exists($targetFile)) {
                $this->info("⏭️  Vista ya existe, omitida: {$componentName}.vue");
                continue;
            }

            $content = $this->getStubContent(
                $stubFile,
                $this->isClean,
                array_merge($placeholders, [
                    'vueComponentName' => $componentName,
                    // El nombre **sin** el sufijo de la vista, para que el índice pueda componer el
                    // de sus tres modales. Sin esto habría que pegarlos a mano en el stub, que es
                    // la forma de que dejen de coincidir el día que cambie la convención.
                    'vueComponentBase' => $this->prefixClass($this->modelName),
                ])
            );

            $this->putFile($targetFile, $content, "Vista generada: {$componentName}.vue");
        }
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Construye el mapa de placeholders comunes a los 4 stubs Vue.
     *
     * Claves desnudas: envolverlas aquí era B15 — el trait las envolvía otra vez y no se
     * sustituía ninguna, así que las cuatro vistas salían con los placeholders literales,
     * incluida la ruta que piden a Axios. El método que sí sabía resolver ese formato
     * —replacePlaceholdersInContent()— no lo llamaba nadie, y por eso el fallo era
     * silencioso: la pieza correcta existía, muerta, al lado de la llamada equivocada.
     *
     * @return array<string, string>
     */
    private function buildPlaceholders(): array
    {
        return [
            'moduleName'         => $this->moduleName,
            // El prefijo del **nombre** de las rutas, salido de `getFunctionality()`: el mismo sitio
            // del que lo toma el generador que las escribe. La vista lo derivaba por su cuenta del
            // nombre del modelo — coinciden mientras el módulo tenga una sola subfuncionalidad, y
            // dejan de coincidir en cuanto tiene dos. El síntoma no es un error de compilación: es
            // un `route()` que no encuentra la ruta, ya en el navegador del usuario.
            'routeBase'          => $this->getFunctionality(),
            // El nombre COMPLETO de la ruta, con el prefijo de su contexto ya puesto.
            //
            // Antes lo componía la vista en tiempo de ejecución, con un composable que leía el
            // contexto activo de las props de Inertia. Es el mismo caso que la ruta de la pantalla:
            // el generador **sabe** en qué contexto escribe —es él quien elige el prefijo de las
            // rutas—, así que resolverlo aquí evita un composable, una lectura por petición y un
            // `if` de modo delante de cada llamada.
            'routeName'          => ($this->getContext()['route_name'] ?? '') . $this->getFunctionality(),
            ...$this->buildLayout(),
            'subFeaturePlural'   => Str::kebab(Str::plural(Str::snake($this->modelName))),
            'subFeatureSingular' => Str::kebab(Str::snake($this->modelName)),
            'subFeatureLabel'    => $this->modelName,
            ...$this->buildViewPermissions(),
        ];
    }

    /**
     * El layout que envuelve la pantalla, si el proyecto declara uno.
     *
     * Va por `defineOptions({ layout })`, que es como Inertia declara un layout persistente: dos
     * líneas y **ni una sola modificación del `<template>`**. Envolverlo a mano habría obligado a
     * reindentar la plantilla entera y a que el generador supiera dónde empieza y acaba — un
     * trabajo frágil para conseguir lo mismo.
     *
     * Sin layout declarado los dos placeholders salen vacíos y la vista se dibuja suelta. Es lo
     * correcto mientras el proyecto no diga cuál es el suyo: la ruta y el nombre del layout son de
     * cada proyecto, así que inventar uno produce una vista que no compila, y ese error aparece en
     * el navegador del usuario, no al generar.
     *
     * ⛔ Solo lo lleva el listado. Las otras tres vistas son modales que viven **dentro** de esa
     * pantalla: darles layout propio dibujaría la aplicación entera dentro de una ventana.
     *
     * @return array<string, string>
     */
    private function buildLayout(): array
    {
        if (! Frontend::tieneLayout()) {
            return ['layoutImport' => '', 'layoutOption' => ''];
        }

        $nombre = Frontend::nombreDelLayout();
        $ruta   = Frontend::layout();

        return [
            'layoutImport' => "import {$nombre} from '{$ruta}'\n",
            'layoutOption' => "\ndefineOptions({ layout: {$nombre} })\n",
        ];
    }

    /**
     * Los permisos que deciden si cada elemento de la vista se muestra.
     *
     * Salen de `SubFeaturePermissions`, que es de donde los toma también el `PermissionsSeeder` que
     * los crea. Antes los stubs los escribían por su cuenta como `{subFeaturePlural}.create` —con
     * punto, verbo `create` y el plural del modelo—, mientras las rutas exigían `{funcionalidad}_store`
     * y el seeder no creaba ni unos ni otros.
     *
     * El resultado no era un error visible: `can()` devolvía `false` siempre, así que la pantalla
     * cargaba perfecta y **sin un solo botón**, para todo el mundo — incluido el webmaster, que recoge
     * los permisos que existen, y esos no existían.
     *
     * @return array<string, string>
     */
    private function buildViewPermissions(): array
    {
        $context    = $this->getContext();
        $contextKey = $this->componentConfig['context'] ?? null;

        $permPrefix = $context['permission_prefix'] ?? '';
        if ($permPrefix === '') {
            $permPrefix = ModuleMode::current()->permissionPrefix($contextKey);
        }

        $elementos = SubFeaturePermissions::viewElements($permPrefix, $this->getFunctionality());

        return [
            'permViewStore'   => $elementos['boton-crear'],
            'permViewShow'    => $elementos['enlace-ver'],
            'permViewUpdate'  => $elementos['boton-editar'],
            'permViewDestroy' => $elementos['boton-eliminar'],
            'permViewRestore' => $elementos['boton-restaurar'],
        ];
    }

    /**
     * Construye la ruta de salida de los componentes Vue.
     *
     * Por buildPath(), como el resto de las capas: añade el contexto **si el modo lo tiene** y la
     * carpeta de la subfuncionalidad, en vez de resolverlo aquí a mano.
     *
     *   single-app          → {module}/resources/js/Pages/Role/
     *   multitenant central → {module}/resources/js/Pages/Central/Role/
     *   tenants iguales     → {module}/resources/js/Pages/Tenant/Shared/Role/
     *
     * Y `resources` en minúscula: en Linux la diferencia no es cosmética, porque el
     * bundler distingue mayúsculas al resolver la ruta de la página.
     */
    private function buildPagesPath(): string
    {
        return $this->buildPath('resources/js/Pages');
    }
}
