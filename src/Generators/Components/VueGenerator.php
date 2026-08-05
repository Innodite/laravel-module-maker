<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Generators\Components;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Genera los 4 componentes Vue para un módulo contextualizado.
 *
 * Arquitectura Frontend (regla obligatoria):
 *   - Inertia.js SOLO para navegación entre páginas (router.visit)
 *   - Todos los datos via axios (GET, POST, PUT, DELETE)
 *   - Las vistas son "shells" que se autocargan al montarse
 *
 * Archivos generados por contexto (ejemplo central, entidad User):
 *   resources/js/Pages/Central/CentralUserIndex.vue   → lista paginada
 *   resources/js/Pages/Central/CentralUserCreate.vue  → formulario de creación
 *   resources/js/Pages/Central/CentralUserEdit.vue    → formulario de edición
 *   resources/js/Pages/Central/CentralUserShow.vue    → vista de detalle
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
                array_merge($placeholders, ['vueComponentName' => $componentName])
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
     * —replacePlaceholdersInContent()— no lo llamaba nadie (A10), y por eso el fallo era
     * silencioso: la pieza correcta existía, muerta, al lado de la llamada equivocada.
     *
     * @return array<string, string>
     */
    private function buildPlaceholders(): array
    {
        return [
            'moduleName'         => $this->moduleName,
            'subFeaturePlural'   => Str::kebab(Str::plural(Str::snake($this->modelName))),
            'subFeatureSingular' => Str::kebab(Str::snake($this->modelName)),
            'subFeatureLabel'    => $this->modelName,
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
     * Y `resources` en minúscula (B11 · R5): en Linux la diferencia no es cosmética, porque el
     * bundler distingue mayúsculas al resolver la ruta de la página.
     */
    private function buildPagesPath(): string
    {
        return $this->buildPath('resources/js/Pages');
    }
}
