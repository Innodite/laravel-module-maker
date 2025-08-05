<?php

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
     * Genera el archivo del Request.
     *
     * @return void
     */
    public function generate(): void
    {
        $requestDir = $this->getComponentBasePath() . "/Http/Requests";
        $this->ensureDirectoryExists($requestDir);

        $stubFile = 'request.stub';
        $stub = $this->getStubContent($stubFile, $this->isClean, [
            'namespace' => "Modules\\{$this->moduleName}\\Http\\Requests",
            'requestName' => $this->requestName,
        ]);

        $this->putFile("{$requestDir}/{$this->requestName}.php", $stub, "Request {$this->requestName}.php creado en Modules/{$this->moduleName}/Http/Requests");
    }
}
