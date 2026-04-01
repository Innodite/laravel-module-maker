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
 *   Resources/js/Pages/Central/CentralUserIndex.vue   → lista paginada
 *   Resources/js/Pages/Central/CentralUserCreate.vue  → formulario de creación
 *   Resources/js/Pages/Central/CentralUserEdit.vue    → formulario de edición
 *   Resources/js/Pages/Central/CentralUserShow.vue    → vista de detalle
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

            $stubPath = $this->getStubPath($stubFile);

            if (!File::exists($stubPath)) {
                $this->warn("⚠️  Stub no encontrado: {$stubFile}");
                continue;
            }

            $content = File::get($stubPath);
            $content = $this->replacePlaceholdersInContent(
                $content,
                array_merge($placeholders, ['{{ vueComponentName }}' => $componentName])
            );

            File::put($targetFile, $content);
            $this->info("✅ Vista generada: {$componentName}.vue");
        }
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Construye el mapa de placeholders comunes a los 4 stubs Vue.
     *
     * @return array<string, string>
     */
    private function buildPlaceholders(): array
    {
        $entityPlural   = Str::kebab(Str::plural(Str::snake($this->modelName)));
        $entitySingular = Str::kebab(Str::snake($this->modelName));

        return [
            '{{ moduleName }}'     => $this->moduleName,
            '{{ entityPlural }}'   => $entityPlural,
            '{{ entitySingular }}' => $entitySingular,
            '{{ entityLabel }}'    => $this->modelName,
        ];
    }

    /**
     * Reemplaza todos los placeholders en el contenido del stub.
     *
     * @param  string               $content
     * @param  array<string,string> $placeholders
     * @return string
     */
    private function replacePlaceholdersInContent(string $content, array $placeholders): string
    {
        return str_replace(array_keys($placeholders), array_values($placeholders), $content);
    }

    /**
     * Construye la ruta de salida de los componentes Vue.
     * Usa la carpeta del contexto activo dentro de Resources/js/Pages/.
     *
     * Ejemplos:
     *   central       → {module}/Resources/js/Pages/Central/
     *   tenant_shared → {module}/Resources/js/Pages/Tenant/Shared/
     *   TenantOne     → {module}/Resources/js/Pages/Tenant/TenantOne/
     */
    private function buildPagesPath(): string
    {
        $base   = "{$this->modulePath}/Resources/js/Pages";
        $folder = $this->getContextFolder();

        return $folder ? "{$base}/{$folder}" : $base;
    }
}
