<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Generators\Components;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Innodite\LaravelModuleMaker\Generators\Concerns\HasStubs;
use Innodite\LaravelModuleMaker\Support\ContextResolver;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Clase base para todos los generadores de componentes del módulo.
 *
 * Centraliza la lógica de contexto (Central, Shared, TenantShared, tenant específico)
 * para que cada generator concreto derive automáticamente la carpeta correcta,
 * el prefijo de clase y el namespace sin repetir lógica.
 *
 * El contexto se configura en contexts.json del proyecto (publicado por innodite:setup).
 */
abstract class AbstractComponentGenerator
{
    use HasStubs;

    protected string $moduleName;
    protected string $modulePath;
    protected bool $isClean;
    protected array $componentConfig;
    protected ?OutputInterface $output = null;

    /**
     * Cache de la configuración del contexto activo.
     *
     * @var array<string, mixed>|null
     */
    private ?array $resolvedContext = null;

    /**
     * @param  string  $moduleName       Nombre del módulo (se convierte a StudlyCase)
     * @param  string  $modulePath       Ruta absoluta al directorio del módulo
     * @param  bool    $isClean          true = stubs clean, false = stubs dynamic
     * @param  array   $componentConfig  Configuración del componente (puede incluir 'context' y 'functionality')
     */
    public function __construct(
        string $moduleName,
        string $modulePath,
        bool $isClean,
        array $componentConfig = []
    ) {
        $this->moduleName      = Str::studly($moduleName);
        $this->modulePath      = $modulePath;
        $this->isClean         = $isClean;
        $this->componentConfig = $componentConfig;
    }

    /**
     * Establece el objeto de salida de la consola.
     *
     * @param  OutputInterface  $output
     * @return static
     */
    public function setOutput(OutputInterface $output): static
    {
        $this->output = $output;
        return $this;
    }

    /**
     * Ejecuta la generación del componente.
     * Cada clase concreta debe implementar este método.
     *
     * @return void
     */
    abstract public function generate(): void;

    // ─── Stubs con contexto ──────────────────────────────────────────────────

    /**
     * Sobreescribe getStubContent del trait HasStubs para inyectar
     * automáticamente la clave de contexto activo en la resolución del stub.
     *
     * {@inheritdoc}
     */
    protected function getStubContent(string $stubFile, bool $isClean, array $placeholders = [], ?string $context = null): string
    {
        // Pasar la carpeta de contexto real (ej: "Central", "Tenant/Shared") para resolución por carpeta
        $contextFolder = $context ?? $this->getContextFolder() ?: ($this->componentConfig['context'] ?? null);
        $stub = $this->getStub($stubFile, $isClean, $contextFolder);
        return $this->replacePlaceholders($stub, $placeholders);
    }

    // ─── Helpers de contexto ─────────────────────────────────────────────────

    /**
     * Retorna la configuración completa del contexto activo.
     *
     * Usa 'context_id' del componentConfig para identificar el sub-contexto exacto
     * dentro del array del contexto seleccionado (ej: 'Energía España' en contexts.tenant).
     * Si no hay 'context' definido, retorna array vacío (retrocompatibilidad).
     *
     * @return array<string, mixed>
     */
    protected function getContext(): array
    {
        if ($this->resolvedContext !== null) {
            return $this->resolvedContext;
        }

        $contextKey = $this->componentConfig['context'] ?? null;
        $contextId  = $this->componentConfig['context_id'] ?? null;

        if ($contextKey === null) {
            $this->resolvedContext = [];
            return $this->resolvedContext;
        }

        try {
            $this->resolvedContext = $contextId !== null
                ? ContextResolver::resolveById($contextKey, $contextId)
                : ContextResolver::resolve($contextKey);
        } catch (\InvalidArgumentException) {
            $this->resolvedContext = [];
        }

        return $this->resolvedContext;
    }

    /**
    * Retorna el prefijo de clase del contexto activo.
    * Ej: 'Central', 'Shared', 'TenantShared', 'TenantAlpha'
     * Retorna cadena vacía si no hay contexto definido (retrocompatibilidad).
     *
     * @return string
     */
    protected function getClassPrefix(): string
    {
        return $this->getContext()['class_prefix'] ?? '';
    }

    /**
    * Retorna la subcarpeta del contexto dentro de cada tipo de componente.
    * Ej: 'Central', 'Shared', 'Tenant/Shared', 'Tenant/Alpha'
     * Retorna cadena vacía si no hay contexto definido.
     *
     * @return string
     */
    protected function getContextFolder(): string
    {
        return $this->getContext()['folder'] ?? '';
    }

    /**
     * Retorna el nombre de la entidad para usarlo como subcarpeta dentro de la carpeta de contexto.
     * Patrón: {Tipo}/{Contexto}/{Entidad}/
     * Ej: Models/Central/User/, Http/Controllers/Tenant/EnergySpain/Role/
     * Retorna cadena vacía si no hay entidad definida (retrocompatibilidad).
     *
     * @return string
     */
    protected function getEntityFolder(): string
    {
        return $this->componentConfig['entity'] ?? '';
    }

    /**
    * Retorna el fragmento de namespace del contexto.
    * Ej: 'Central', 'Shared', 'Tenant\\Shared', 'Tenant\\Alpha'
     *
     * @return string
     */
    protected function getContextNamespacePath(): string
    {
        return $this->getContext()['namespace_path'] ?? '';
    }

    /**
     * Construye el namespace completo para un tipo de componente dentro del módulo.
     * Patrón: Modules\{Module}\{Type}\{ContextNs}\{Entity}
     * Ej: buildNamespace('Http\\Controllers') → 'Modules\Products\Http\Controllers\Tenant\Alpha\Product'
     *
     * @param  string  $componentType  Tipo de componente (ej: 'Http\\Controllers', 'Services', 'Models')
     * @return string
     */
    protected function buildNamespace(string $componentType): string
    {
        $base   = "Modules\\{$this->moduleName}\\{$componentType}";
        $ctxNs  = $this->getContextNamespacePath();
        $entity = $this->getEntityFolder();

        $ns = $ctxNs ? "{$base}\\{$ctxNs}" : $base;

        return $entity ? "{$ns}\\{$entity}" : $ns;
    }

    /**
     * Construye el namespace de la carpeta Contracts para un tipo de componente.
     * Patrón: Modules\{Module}\{Type}\Contracts\{ContextNs}\{Entity}
     * Ej: buildContractsNamespace('Services') → 'Modules\User\Services\Contracts\Tenant\INNODITE\Role'
     *
     * @param  string  $componentType  Tipo de componente (ej: 'Services', 'Repositories')
     * @return string
     */
    protected function buildContractsNamespace(string $componentType): string
    {
        $base   = "Modules\\{$this->moduleName}\\{$componentType}\\Contracts";
        $ctxNs  = $this->getContextNamespacePath();
        $entity = $this->getEntityFolder();

        $ns = $ctxNs ? "{$base}\\{$ctxNs}" : $base;

        return $entity ? "{$ns}\\{$entity}" : $ns;
    }

    /**
     * Construye la ruta absoluta de carpeta para un tipo de componente dentro del módulo.
     * Patrón: {ModulePath}/{Type}/{ContextFolder}/{Entity}/
     * Ej: buildPath('Http/Controllers') → '.../Products/Http/Controllers/Tenant/Alpha/Product'
     *
     * @param  string  $componentType  Tipo de componente (ej: 'Http/Controllers', 'Services', 'Models')
     * @return string
     */
    protected function buildPath(string $componentType): string
    {
        $base   = $this->getComponentBasePath() . "/{$componentType}";
        $folder = $this->getContextFolder();
        $entity = $this->getEntityFolder();

        $path = $folder ? "{$base}/{$folder}" : $base;

        return $entity ? "{$path}/{$entity}" : $path;
    }

    /**
     * Construye la ruta absoluta a la carpeta Contracts para un tipo de componente.
     * Patrón: {ModulePath}/{Type}/Contracts/{ContextFolder}/{Entity}/
     * Ej: buildContractsPath('Services') → '.../User/Services/Contracts/Tenant/INNODITE/Role'
     *
     * @param  string  $componentType  Tipo de componente (ej: 'Services', 'Repositories')
     * @return string
     */
    protected function buildContractsPath(string $componentType): string
    {
        $base   = $this->getComponentBasePath() . "/{$componentType}/Contracts";
        $folder = $this->getContextFolder();
        $entity = $this->getEntityFolder();

        $path = $folder ? "{$base}/{$folder}" : $base;

        return $entity ? "{$path}/{$entity}" : $path;
    }

    /**
    * Prefija el nombre de la clase con el prefijo del contexto activo.
    * Ej: prefixClass('UserController') con contexto 'alpha' → 'TenantAlphaUserController'
     *
     * @param  string  $className  Nombre de la clase sin prefijo
     * @return string
     */
    protected function prefixClass(string $className): string
    {
        $prefix = $this->getClassPrefix();
        return $prefix ? $prefix . $className : $className;
    }

    /**
     * Retorna el nombre de la funcionalidad en kebab-case para el prefijo de ruta.
     * Ej: 'users', 'campaign-goals'
     * Si no está definido, usa el nombre del módulo en kebab-case.
     *
     * @return string
     */
    protected function getFunctionality(): string
    {
        return $this->componentConfig['functionality']
            ?? Str::kebab(Str::plural(Str::snake($this->moduleName)));
    }

    // ─── Helpers de filesystem ────────────────────────────────────────────────

    /**
     * Retorna la ruta base del módulo dentro del directorio de módulos.
     *
     * @return string
     */
    protected function getComponentBasePath(): string
    {
        return config('make-module.module_path') . "/{$this->moduleName}";
    }

    /**
     * Crea los directorios necesarios si no existen.
     *
     * @param  string  $directoryPath  Ruta absoluta del directorio
     * @return void
     */
    protected function ensureDirectoryExists(string $directoryPath): void
    {
        File::ensureDirectoryExists($directoryPath);
    }

    /**
     * Escribe el contenido en un archivo y muestra un mensaje de éxito en consola.
     *
     * @param  string  $filePath  Ruta absoluta del archivo a escribir
     * @param  string  $content   Contenido a escribir
     * @param  string  $message   Mensaje a mostrar en consola
     * @return void
     */
    protected function putFile(string $filePath, string $content, string $message): void
    {
        File::put($filePath, $content);
        $this->info("✅ {$message}");
    }

    // ─── Output ───────────────────────────────────────────────────────────────

    /**
     * Muestra un mensaje de información en consola.
     *
     * @param  string  $message
     * @return void
     */
    public function info(string $message): void
    {
        $this->output?->writeln("<info>{$message}</info>");
    }

    /**
     * Muestra un mensaje de advertencia en consola.
     *
     * @param  string  $message
     * @return void
     */
    public function warn(string $message): void
    {
        $this->output?->writeln("<comment>{$message}</comment>");
    }

    /**
     * Muestra un mensaje de error en consola.
     *
     * @param  string  $message
     * @return void
     */
    public function error(string $message): void
    {
        $this->output?->writeln("<error>{$message}</error>");
    }
}
