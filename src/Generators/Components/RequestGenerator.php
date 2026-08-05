<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Generators\Components;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Innodite\LaravelModuleMaker\Generators\Concerns\HasStubs;

class RequestGenerator extends AbstractComponentGenerator
{
    protected string $requestName;

    public function __construct(string $moduleName, string $modulePath, bool $isClean, string $requestName, array $componentConfig = [])
    {
        parent::__construct($moduleName, $modulePath, $isClean, $componentConfig);
        $this->requestName = Str::studly($requestName);
    }

    /**
     * Genera el/los archivo(s) de Request según el contexto.
     *
     * - Central / TenantName con contexto: genera StoreRequest + UpdateRequest contextuales.
     * - TenantShared con contexto: genera un único Request genérico en la carpeta de contexto.
     * - Sin contexto (fallback): genera un único Request en Http/Requests raíz.
     *
     * @return void
     */
    public function generate(): void
    {
        $contextKey    = $this->componentConfig['context'] ?? null;
        $contextFolder = $this->getContextFolder();

        // ── Sin contexto NI subfuncionalidad: comportamiento legacy ───────────
        // La condición era «sin contexto», y eso convertía single-app en un caso degradado: como
        // ahí el contexto siempre está vacío, un proyecto sin tenants caía en el camino legacy y
        // recibía una estructura recortada. No es un fallback, es un modo de primera clase — lo
        // que decide es si hay subfuncionalidad, que la hay siempre que se genere de verdad.
        if ($contextFolder === '' && $this->getEntityFolder() === '') {
            $requestDir = $this->getComponentBasePath() . '/Http/Requests';
            $this->ensureDirectoryExists($requestDir);

            $stub = $this->getStubContent('request.stub', $this->isClean, [
                'namespace'   => "Modules\\{$this->moduleName}\\Http\\Requests",
                'requestName' => $this->requestName,
            ]);

            $this->putFile(
                "{$requestDir}/{$this->requestName}.php",
                $stub,
                "Request {$this->requestName}.php creado en Modules/{$this->moduleName}/Http/Requests"
            );
            return;
        }

        $requestDir      = $this->buildPath('Http/Requests');
        $moduleNamespace = "Modules\\{$this->moduleName}";
        $entity          = $this->componentConfig['entity'] ?? $this->moduleName;
        $className       = $this->getClassPrefix() . $entity;

        // Namespace del stub: contexto + entidad (espejo de buildPath)
        $ctxNs    = str_replace('/', '\\', $contextFolder); // ej: "Central", "Tenant\\Shared"
        $entityNs = $this->getEntityFolder();               // ej: "Role", ""
        $contextNamespace = $entityNs ? "{$ctxNs}\\{$entityNs}" : $ctxNs;

        $this->ensureDirectoryExists($requestDir);

        // TenantShared → único Request genérico (usa stub legacy con nombre contextual)
        $isTenantShared = ($contextKey === 'tenant_shared' || ($contextKey === 'shared' && str_contains($contextFolder, 'Tenant')));

        if ($isTenantShared) {
            $stub = $this->getStubContent('request.stub', $this->isClean, [
                'namespace'   => $this->buildNamespace('Http\\Requests'),
                'requestName' => $className . 'Request',
            ]);

            $this->putFile(
                "{$requestDir}/{$className}Request.php",
                $stub,
                "Request {$className}Request.php creado en Modules/{$this->moduleName}/Http/Requests/{$contextFolder}"
            );
            return;
        }

        // Central / TenantName → StoreRequest + UpdateRequest
        $storeStub = $this->getStubContent('request-store.stub', $this->isClean, [
            'namespace' => $this->buildNamespace('Http\\Requests'),
            'className' => $className,
        ]);

        $updateStub = $this->getStubContent('request-update.stub', $this->isClean, [
            'namespace' => $this->buildNamespace('Http\\Requests'),
            'className' => $className,
        ]);

        $this->putFile(
            "{$requestDir}/{$className}StoreRequest.php",
            $storeStub,
            "Request {$className}StoreRequest.php creado en Modules/{$this->moduleName}/Http/Requests/{$contextFolder}"
        );

        $this->putFile(
            "{$requestDir}/{$className}UpdateRequest.php",
            $updateStub,
            "Request {$className}UpdateRequest.php creado en Modules/{$this->moduleName}/Http/Requests/{$contextFolder}"
        );
    }
}
