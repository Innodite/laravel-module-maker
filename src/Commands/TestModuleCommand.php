<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Innodite\LaravelModuleMaker\Services\TestContextConfigService;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

/**
 * Comando para ejecutar tests de módulos con cobertura de código.
 * 
 * Características:
 * - Ejecuta tests de un módulo específico o todos los módulos
 * - Genera reportes de cobertura en múltiples formatos (HTML, Text, Clover)
 * - Permite filtrar por contexto (Central, Shared, Tenant, etc.)
 * - Escanea recursivamente todas las carpetas de tests
 * - Valida que Xdebug/PCOV estén activos para coverage
 * 
 * @package Innodite\LaravelModuleMaker\Commands
 * @version 1.0.0
 */
class TestModuleCommand extends Command
{
    /**
     * Signature del comando con todos los flags disponibles.
     *
     * @var string
     */
    protected $signature = 'innodite:test-module 
        {module? : Nombre del módulo (ej: User)}
        {--all : Ejecutar tests de TODOS los módulos}
        {--context= : Ejecutar tests de un contexto específico (key en Tests/test-config.json)}
        {--all-contexts : Ejecutar todos los contextos habilitados del módulo}
        {--coverage : Generar reporte de coverage (requiere Xdebug/PCOV)}
        {--format=* : Formatos de coverage (html,text,clover). Default: html,text}
        {--filter= : Patrón de filtro para PHPUnit (ej: testExample)}
        {--stop-on-failure : Detener en el primer fallo}
        {--no-output : No mostrar salida de PHPUnit (solo resumen)}';

    /**
     * Descripción del comando.
     *
     * @var string
     */
    protected $description = 'Ejecuta los tests de un módulo o todos los módulos con cobertura de código';

    /**
     * Ruta base para guardar reportes.
     *
     * @var string
     */
    protected string $reportsBasePath;

    /**
     * Indica si el entorno soporta coverage.
     *
     * @var bool
     */
    protected bool $coverageEnabled = false;

    /**
     * Carpeta relativa para logs de errores de test.
     *
     * @var string
     */
    protected string $failureLogsPath = 'logs/module_maker/test_failures';

    /**
     * Servicio para sincronizar y resolver configuración de tests por contexto.
     *
     * @var TestContextConfigService
     */
    protected TestContextConfigService $testConfigService;

    /**
     * Resultados de ejecución de tests.
     *
     * @var array
     */
    protected array $results = [];

    /**
     * Constructor del comando.
     */
    public function __construct()
    {
        parent::__construct();
        $this->reportsBasePath = base_path('docs/test-reports');
        $this->testConfigService = new TestContextConfigService();
    }

    /**
     * Ejecuta el comando.
     *
     * @return int
     */
    public function handle(): int
    {
        $this->info('🧪 Innodite Module Maker - Test Runner');
        $this->newLine();

        // Validar entorno
        if (!$this->validateEnvironment()) {
            return self::FAILURE;
        }

        // Obtener módulos a testear
        $modules = $this->getModulesToTest();

        if (empty($modules)) {
            $this->error('❌ No se encontraron módulos para testear.');
            return self::FAILURE;
        }

        $this->info('📦 Módulos a testear: ' . implode(', ', $modules));
        $this->newLine();

        // Ejecutar tests por cada módulo
        foreach ($modules as $module) {
            $this->runTestsForModule($module);
        }

        // Mostrar resumen
        $this->displaySummary();

        // Determinar código de salida
        $hasFailures = collect($this->results)->contains(fn($result) => $result['status'] === 'failed');

        return $hasFailures ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Valida que el entorno esté correctamente configurado.
     *
     * @return bool
     */
    protected function validateEnvironment(): bool
    {
        // Verificar que PHPUnit esté disponible
        $phpunitPath = base_path('vendor/bin/phpunit');
        
        if (!File::exists($phpunitPath)) {
            $this->error('❌ PHPUnit no está instalado.');
            $this->info('💡 Ejecuta: composer require --dev phpunit/phpunit');
            return false;
        }

        $this->info('✅ PHPUnit encontrado');

        // Verificar extensión de coverage si se solicita
        if ($this->option('coverage')) {
            $hasXdebug = extension_loaded('xdebug');
            $hasPcov = extension_loaded('pcov');

            if (!$hasXdebug && !$hasPcov) {
                $this->warn('⚠️  Xdebug/PCOV no están activos. Coverage no estará disponible.');
                $this->info('💡 Para instalar Xdebug: https://xdebug.org/docs/install');
                $this->info('💡 Para instalar PCOV: pecl install pcov');
                $this->newLine();
                
                if (!$this->confirm('¿Continuar sin coverage?', true)) {
                    return false;
                }
            } else {
                $this->coverageEnabled = true;
                $extension = $hasXdebug ? 'Xdebug' : 'PCOV';
                $this->info("✅ {$extension} activo - Coverage disponible");
            }
        }

        $this->newLine();
        return true;
    }

    /**
     * Obtiene la lista de módulos a testear.
     *
     * @return array
     */
    protected function getModulesToTest(): array
    {
        $modulesPath = base_path('Modules');

        if (!File::isDirectory($modulesPath)) {
            $this->warn("⚠️  La carpeta 'Modules/' no existe.");
            return [];
        }

        // Si se especifica --all, obtener todos los módulos
        if ($this->option('all')) {
            return $this->getAllModules($modulesPath);
        }

        // Si se especifica un módulo, validarlo
        $moduleName = $this->argument('module');

        if (!$moduleName) {
            $this->error('❌ Debes especificar un módulo o usar --all');
            return [];
        }

        $modulePath = "{$modulesPath}/{$moduleName}";

        if (!File::isDirectory($modulePath)) {
            $this->error("❌ El módulo '{$moduleName}' no existe.");
            return [];
        }

        return [$moduleName];
    }

    /**
     * Obtiene todos los módulos disponibles.
     *
     * @param string $modulesPath
     * @return array
     */
    protected function getAllModules(string $modulesPath): array
    {
        $modules = [];
        $directories = File::directories($modulesPath);

        foreach ($directories as $directory) {
            $moduleName = basename($directory);
            
            // Verificar que tenga carpeta Tests
            if (File::isDirectory("{$directory}/Tests")) {
                $modules[] = $moduleName;
            }
        }

        return $modules;
    }

    /**
     * Ejecuta los tests de un módulo específico.
     *
     * @param string $module
     * @return void
     */
    protected function runTestsForModule(string $module): void
    {
        $this->info("🔍 Ejecutando tests del módulo: {$module}");

        $modulePath = base_path("Modules/{$module}");
        $testsPath = "{$modulePath}/Tests";

        // Verificar que exista carpeta Tests
        if (!File::isDirectory($testsPath)) {
            $this->warn("  ⚠️  El módulo '{$module}' no tiene carpeta Tests/");
            $this->results[] = [
                'module' => $module,
                'status' => 'skipped',
                'reason' => 'No tests found',
            ];
            $this->newLine();
            return;
        }

        $moduleConfig = $this->testConfigService->loadModuleTestConfig($module);
        $contexts = is_array($moduleConfig['contexts'] ?? null) ? $moduleConfig['contexts'] : [];

        if ($this->option('all-contexts')) {
            $enabledContexts = array_filter(
                $contexts,
                static fn ($context): bool => is_array($context) && (bool) ($context['enabled'] ?? true)
            );

            if (empty($enabledContexts)) {
                $this->warn("  ⚠️  No hay contextos habilitados en {$this->testConfigService->getTestConfigPath($module)}");
                $this->results[] = [
                    'module' => $module,
                    'status' => 'skipped',
                    'reason' => 'No enabled contexts',
                ];
                $this->newLine();
                return;
            }

            foreach ($enabledContexts as $contextKey => $contextConfig) {
                if (!is_array($contextConfig)) {
                    continue;
                }

                $this->runTestsForModuleContext($module, $testsPath, (string) $contextKey, $contextConfig);
            }

            return;
        }

        $contextOption = trim((string) $this->option('context'));
        if ($contextOption !== '') {
            $contextKey = $this->testConfigService->normalizeContextKey($contextOption);
            $contextConfig = is_array($contexts[$contextKey] ?? null) ? $contexts[$contextKey] : [];

            $this->runTestsForModuleContext($module, $testsPath, $contextKey, $contextConfig);
            return;
        }

        $this->runTestsForModuleContext($module, $testsPath, null, []);
    }

    /**
     * Ejecuta tests de un módulo para un contexto específico.
     *
     * @param string $module
     * @param string $testsPath
     * @param string|null $contextKey
     * @param array<string,mixed> $contextConfig
     * @return void
     */
    protected function runTestsForModuleContext(string $module, string $testsPath, ?string $contextKey, array $contextConfig): void
    {
        $label = $contextKey ?? 'default';
        $moduleLabel = $contextKey ? "{$module}@{$contextKey}" : $module;

        $this->info("  🧭 Contexto: {$label}");

        $testFiles = $this->getTestFiles($testsPath, $contextKey, $contextConfig);

        if (empty($testFiles)) {
            $this->warn("  ⚠️  No se encontraron archivos de test para '{$moduleLabel}'");
            $this->results[] = [
                'module' => $moduleLabel,
                'status' => 'skipped',
                'reason' => 'No test files',
            ];
            $this->newLine();
            return;
        }

        $this->info("  📄 Archivos de test encontrados: " . count($testFiles));

        if (!$this->runContextSeeder($contextConfig, $moduleLabel)) {
            $this->results[] = [
                'module' => $moduleLabel,
                'status' => 'failed',
                'reason' => 'Seeder execution failed',
            ];
            $this->newLine();
            return;
        }

        // Resolver configuración de PHPUnit en la carpeta Tests del módulo.
        $phpunitXmlPath = $this->createOrResolvePhpunitConfig($module, $testsPath, $contextKey, $contextConfig);
        $reportPath = $this->resolveReportPath($module, $contextKey);

        // Construir comando PHPUnit
        $command = $this->buildPhpunitCommand($module, $phpunitXmlPath, $testFiles);

        // Ejecutar PHPUnit
        $result = $this->executePhpunit($command, $moduleLabel, $reportPath);

        // Guardar resultado
        $this->results[] = $result;

        $this->newLine();
    }

    /**
     * Obtiene todos los archivos de test, opcionalmente filtrados por contexto.
     *
     * @param string $testsPath
     * @return array
     */
    protected function getTestFiles(string $testsPath, ?string $contextKey = null, array $contextConfig = []): array
    {
        $contextFolder = null;
        $tenantMarker = null;
        $tenantMarkers = [];
        $contextGroup = (string) ($contextConfig['group'] ?? '');

        if ($contextKey !== null && $contextKey !== '') {
            $configuredFolder = trim((string) ($contextConfig['folder'] ?? ''));
            $contextFolder = $configuredFolder !== ''
                ? str_replace('\\', '/', $configuredFolder)
                : $this->normalizeContextForPath($contextKey);

            if ($contextGroup === 'tenant') {
                $tenantMarker = trim((string) basename($contextFolder));
                $tenantMarkers = $this->testConfigService->getTenantMarkers();
            }
        }

        $allFiles = File::allFiles($testsPath);
        $testFiles = [];

        foreach ($allFiles as $file) {
            $relativePath = str_replace('\\', '/', $file->getRelativePathname());
            
            // Solo archivos *Test.php
            if (!str_ends_with($relativePath, 'Test.php')) {
                continue;
            }

            // Filtrar por contexto si se especificó
            if ($contextFolder !== null && $contextFolder !== '') {
                if (str_contains($relativePath, $contextFolder)) {
                    $testFiles[] = $file->getPathname();
                    continue;
                }

                if ($contextGroup === 'tenant' && str_contains($relativePath, 'Tenant/')) {
                    $matchesTenantSpecific = $tenantMarker !== null && $tenantMarker !== ''
                        && str_contains($relativePath, $tenantMarker);

                    $containsOtherTenantMarker = false;
                    foreach ($tenantMarkers as $marker) {
                        if ($marker === $tenantMarker) {
                            continue;
                        }

                        if (str_contains($relativePath, $marker)) {
                            $containsOtherTenantMarker = true;
                            break;
                        }
                    }

                    $isGenericTenantTest = !$containsOtherTenantMarker
                        && !$matchesTenantSpecific;

                    if ($matchesTenantSpecific || $isGenericTenantTest) {
                        $testFiles[] = $file->getPathname();
                    }

                    continue;
                }

                continue;
            }

            $testFiles[] = $file->getPathname();
        }

        return $testFiles;
    }

    /**
     * Normaliza el nombre del contexto para búsqueda en path.
     *
     * @param string $context
     * @return string
     */
    protected function normalizeContextForPath(string $context): string
    {
        return match (strtolower($context)) {
            'central' => 'Central',
            'shared' => 'Shared',
            'tenant' => 'Tenant',
            'tenant-shared' => 'Tenant/Shared',
            'tenantshared' => 'Tenant/Shared',
            default => ucfirst($context),
        };
    }

    /**
     * Crea un archivo phpunit.xml temporal para el módulo.
     *
     * @param string $module
     * @param string $testsPath
     * @return string Path del archivo creado
     */
    protected function createOrResolvePhpunitConfig(string $module, string $testsPath, ?string $contextKey = null, array $contextConfig = []): string
    {
        $modulePath = base_path("Modules/{$module}");
        $reportPath = $this->resolveReportPath($module, $contextKey);
        $configPath = $this->resolveModulePhpunitConfigPath($module, $contextKey);

        // Si ya existe, se respeta el archivo editable por el usuario.
        if (File::exists($configPath)) {
            return $configPath;
        }
        
        // Crear carpeta de reportes si no existe
        if (!File::isDirectory($reportPath)) {
            File::makeDirectory($reportPath, 0755, true);
        }

        $formats = $this->getCoverageFormats();
        $coverageReports = '';

        if ($this->option('coverage') && $this->coverageEnabled) {
            $reports = [];
            
            if (in_array('html', $formats)) {
                $reports[] = "        <html outputDirectory=\"{$reportPath}/html\"/>";
            }
            
            if (in_array('text', $formats)) {
                $reports[] = "        <text outputFile=\"php://stdout\" showUncoveredFiles=\"false\"/>";
            }
            
            if (in_array('clover', $formats)) {
                $reports[] = "        <clover outputFile=\"{$reportPath}/clover.xml\"/>";
            }

            if (!empty($reports)) {
                $coverageReports = "\n    <coverage>\n        <include>\n            <directory suffix=\".php\">{$modulePath}/Services</directory>\n            <directory suffix=\".php\">{$modulePath}/Repositories</directory>\n            <directory suffix=\".php\">{$modulePath}/Models</directory>\n            <directory suffix=\".php\">{$modulePath}/Http/Controllers</directory>\n        </include>\n        <report>\n" . implode("\n", $reports) . "\n        </report>\n    </coverage>";
            }
        }

        $bootstrapPath = base_path('vendor/autoload.php');

        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<phpunit
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
    bootstrap="{$bootstrapPath}"
    colors="true"
    processIsolation="false"
    stopOnFailure="false"
>
    <testsuites>
        <testsuite name="{$module}">
            <directory suffix="Test.php">{$testsPath}</directory>
        </testsuite>
    </testsuites>{$coverageReports}

    <php>
{$this->renderPhpunitEnvBlock($contextConfig)}
    </php>
</phpunit>
XML;

        File::put($configPath, $xml);
        @chmod($configPath, 0666);
        $this->line("  📝 Config PHPUnit creada: {$configPath}");

        return $configPath;
    }

    protected function resolveModulePhpunitConfigPath(string $module, ?string $contextKey): string
    {
        $testsPath = base_path("Modules/{$module}/Tests");
        $contextSuffix = $contextKey
            ? $this->testConfigService->normalizeContextKey($contextKey)
            : 'default';

        return "{$testsPath}/phpunit-{$contextSuffix}.xml";
    }

    /**
     * Obtiene los formatos de coverage solicitados.
     *
     * @return array
     */
    protected function getCoverageFormats(): array
    {
        $formats = $this->option('format');

        if (empty($formats)) {
            return ['html', 'text'];
        }

        $validFormats = ['html', 'text', 'clover'];
        $requestedFormats = [];

        foreach ($formats as $format) {
            $normalized = strtolower(trim($format));
            if (in_array($normalized, $validFormats)) {
                $requestedFormats[] = $normalized;
            }
        }

        return !empty($requestedFormats) ? $requestedFormats : ['html', 'text'];
    }

    /**
     * Construye el comando PHPUnit con todos los flags.
     *
     * @param string $module
     * @param string $phpunitXmlPath
     * @param array $testFiles
     * @return array
     */
    protected function buildPhpunitCommand(string $module, string $phpunitXmlPath, array $testFiles): array
    {
        $phpunitBin = base_path('vendor/bin/phpunit');
        
        $command = [
            PHP_BINARY,
            $phpunitBin,
            '--configuration=' . $phpunitXmlPath,
        ];

        // Flag --filter si se especifica
        if ($filter = $this->option('filter')) {
            $command[] = '--filter=' . $filter;
        }

        // Flag --stop-on-failure
        if ($this->option('stop-on-failure')) {
            $command[] = '--stop-on-failure';
        }

        // Flag --testdox para salida legible
        if (!$this->option('no-output')) {
            $command[] = '--testdox';
        }

        // Ejecutar exclusivamente los archivos detectados para el módulo/contexto.
        foreach ($testFiles as $testFile) {
            $command[] = (string) $testFile;
        }

        return $command;
    }

    /**
     * Ejecuta el comando PHPUnit y captura el resultado.
     *
     * @param array $command
     * @param string $module
     * @return array
     */
    protected function executePhpunit(array $command, string $module, string $reportPath): array
    {
        $process = new Process($command);
        $process->setTimeout(300); // 5 minutos timeout
        $process->setWorkingDirectory(base_path());

        $output = '';
        $errorOutput = '';

        try {
            $process->run(function ($type, $buffer) use (&$output, &$errorOutput) {
                if (Process::ERR === $type) {
                    $errorOutput .= $buffer;
                } else {
                    $output .= $buffer;
                }

                // Mostrar output en tiempo real si no está silenciado
                if (!$this->option('no-output')) {
                    $this->output->write($buffer);
                }
            });

            $exitCode = $process->getExitCode();
            $success = $exitCode === 0;

            // Parsear cobertura si está disponible
            $coverage = $this->parseCoverageFromOutput($output);
            $failureLogPath = null;

            if (!$success) {
                $failureLogPath = $this->persistFailedTestOutput(
                    $module,
                    $command,
                    $exitCode,
                    $output,
                    $errorOutput,
                    'failed'
                );
            }

            return [
                'module' => $module,
                'status' => $success ? 'passed' : 'failed',
                'exit_code' => $exitCode,
                'coverage' => $coverage,
                'report_path' => $reportPath,
                'failure_log_path' => $failureLogPath,
                'output' => $output,
                'error' => $errorOutput,
            ];

        } catch (ProcessFailedException $exception) {
            $failureLogPath = $this->persistFailedTestOutput(
                $module,
                $command,
                $exception->getProcess()->getExitCode(),
                $output,
                $exception->getMessage(),
                'error'
            );

            return [
                'module' => $module,
                'status' => 'error',
                'exit_code' => $exception->getProcess()->getExitCode(),
                'coverage' => null,
                'report_path' => $reportPath,
                'failure_log_path' => $failureLogPath,
                'output' => $output,
                'error' => $exception->getMessage(),
            ];
        }
    }

    /**
     * Guarda un log detallado de un fallo de ejecución.
     *
     * @param string $module
     * @param array<int,string> $command
     * @param int|null $exitCode
     * @param string $output
     * @param string $errorOutput
     * @param string $status
     * @return string
     */
    protected function persistFailedTestOutput(
        string $module,
        array $command,
        ?int $exitCode,
        string $output,
        string $errorOutput,
        string $status
    ): string {
        $logDir = storage_path($this->failureLogsPath);

        if (!File::isDirectory($logDir)) {
            File::makeDirectory($logDir, 0755, true);
        }

        $timestamp = date('Ymd_His');
        $moduleSafe = str_replace(['@', '/', '\\', ' '], '_', strtolower($module));
        $fileName = "{$moduleSafe}_{$status}_{$timestamp}.log";
        $filePath = $logDir . DIRECTORY_SEPARATOR . $fileName;

        $content = [
            'Package: innodite/laravel-module-maker',
            'Timestamp: ' . date(DATE_ATOM),
            'Module: ' . $module,
            'Status: ' . $status,
            'Exit code: ' . ($exitCode ?? 'null'),
            'Context option: ' . (trim((string) $this->option('context')) !== '' ? (string) $this->option('context') : '[none]'),
            'Coverage requested: ' . ($this->option('coverage') ? 'yes' : 'no'),
            'Filter: ' . ($this->option('filter') ?: 'none'),
            'Command: ' . implode(' ', array_map('strval', $command)),
            '',
            '===== STDOUT =====',
            $output !== '' ? $output : '[empty]',
            '',
            '===== STDERR =====',
            $errorOutput !== '' ? $errorOutput : '[empty]',
            '',
        ];

        File::put($filePath, implode(PHP_EOL, $content));

        return $filePath;
    }

    /**
     * Devuelve un extracto corto para mostrar fallos sin inundar la consola.
     */
    protected function buildFailureSnippet(string $output, string $error): string
    {
        $combined = trim($output . PHP_EOL . $error);
        if ($combined === '') {
            return '[sin salida adicional]';
        }

        $lines = preg_split('/\r\n|\r|\n/', $combined) ?: [];
        $lines = array_values(array_filter(array_map('trim', $lines), static fn (string $line): bool => $line !== ''));

        if (count($lines) <= 20) {
            return implode(PHP_EOL, $lines);
        }

        return implode(PHP_EOL, array_slice($lines, -20));
    }

    /**
     * Ejecuta seeder del contexto si está configurado.
     *
     * @param array<string,mixed> $contextConfig
     * @param string $moduleLabel
     * @return bool
     */
    protected function runContextSeeder(array $contextConfig, string $moduleLabel): bool
    {
        $seeder = trim((string) ($contextConfig['seeder'] ?? ''));
        if ($seeder === '') {
            return true;
        }

        $this->info("  🌱 Ejecutando seeder previo: {$seeder}");

        $parameters = [
            '--class' => $seeder,
            '--force' => true,
        ];

        $connection = trim((string) ($contextConfig['db_connection'] ?? ''));
        if ($connection !== '') {
            $parameters['--database'] = $connection;
        }

        $exitCode = $this->call('db:seed', $parameters);
        if ($exitCode !== self::SUCCESS) {
            $this->error("  ❌ Falló el seeder configurado para {$moduleLabel}");
            return false;
        }

        return true;
    }

    /**
     * Renderiza el bloque <php> de phpunit.xml según contexto.
     *
     * @param array<string,mixed> $contextConfig
     * @return string
     */
    protected function renderPhpunitEnvBlock(array $contextConfig): string
    {
        $env = [
            'APP_ENV' => 'testing',
            'CACHE_DRIVER' => 'array',
            'SESSION_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
        ];

        $configuredConnection = trim((string) ($contextConfig['db_connection'] ?? ''));
        if ($configuredConnection !== '') {
            $env['DB_CONNECTION'] = $configuredConnection;
        }

        if (array_key_exists('db_database', $contextConfig) && $contextConfig['db_database'] !== null) {
            $configuredDatabase = trim((string) $contextConfig['db_database']);
            if ($configuredDatabase !== '') {
                $env['DB_DATABASE'] = $configuredDatabase;
            }
        }

        $customEnv = $contextConfig['env'] ?? [];
        if (is_array($customEnv)) {
            foreach ($customEnv as $key => $value) {
                if (!is_string($key) || $key === '') {
                    continue;
                }

                $env[$key] = (string) $value;
            }
        }

        $lines = [];
        foreach ($env as $key => $value) {
            $escapedValue = htmlspecialchars($value, ENT_QUOTES);
            $lines[] = "        <env name=\"{$key}\" value=\"{$escapedValue}\"/>";
        }

        return implode(PHP_EOL, $lines);
    }

    protected function resolveReportPath(string $module, ?string $contextKey): string
    {
        if ($contextKey === null || $contextKey === '') {
            return "{$this->reportsBasePath}/{$module}/default";
        }

        $normalizedContext = $this->testConfigService->normalizeContextKey($contextKey);

        return "{$this->reportsBasePath}/{$module}/{$normalizedContext}";
    }

    /**
     * Parsea la cobertura de código desde la salida de PHPUnit.
     *
     * @param string $output
     * @return array|null
     */
    protected function parseCoverageFromOutput(string $output): ?array
    {
        // Buscar líneas de coverage en formato texto
        // Ejemplo: "Lines: 85.5% (123/144)"
        if (preg_match('/Lines:\s+(\d+\.\d+)%\s+\((\d+)\/(\d+)\)/', $output, $matches)) {
            return [
                'percentage' => (float) $matches[1],
                'covered' => (int) $matches[2],
                'total' => (int) $matches[3],
            ];
        }

        return null;
    }

    /**
     * Muestra un resumen de todos los tests ejecutados.
     *
     * @return void
     */
    protected function displaySummary(): void
    {
        $this->newLine();
        $this->info('═══════════════════════════════════════════════════════');
        $this->info('📊 RESUMEN DE EJECUCIÓN');
        $this->info('═══════════════════════════════════════════════════════');
        $this->newLine();

        $headers = ['Módulo', 'Estado', 'Coverage'];
        $rows = [];

        foreach ($this->results as $result) {
            $module = (string) ($result['module'] ?? 'N/A');
            
            $status = match ($result['status']) {
                'passed' => '<fg=green>✓ PASSED</>',
                'failed' => '<fg=red>✗ FAILED</>',
                'skipped' => '<fg=yellow>⊘ SKIPPED</>',
                'error' => '<fg=red>⚠ ERROR</>',
                default => '? UNKNOWN',
            };

            $coverageData = $result['coverage'] ?? null;
            $coverage = is_array($coverageData)
                ? sprintf('<fg=cyan>%.1f%%</> (%d/%d)', 
                    (float) ($coverageData['percentage'] ?? 0),
                    (int) ($coverageData['covered'] ?? 0),
                    (int) ($coverageData['total'] ?? 0))
                : '<fg=gray>N/A</>';

            $rows[] = [$module, $status, $coverage];
        }

        $this->table($headers, $rows);

        // Mostrar estadísticas globales
        $total = count($this->results);
        $passed = collect($this->results)->where('status', 'passed')->count();
        $failed = collect($this->results)->where('status', 'failed')->count();
        $skipped = collect($this->results)->where('status', 'skipped')->count();

        $this->newLine();
        $this->info("Total: {$total} | Passed: {$passed} | Failed: {$failed} | Skipped: {$skipped}");

        $failedResults = array_values(array_filter(
            $this->results,
            static fn (array $result): bool => in_array((string) ($result['status'] ?? ''), ['failed', 'error'], true)
        ));

        if (!empty($failedResults)) {
            $this->newLine();
            $this->error('❌ Detalle de errores detectados:');

            foreach ($failedResults as $result) {
                $module = (string) ($result['module'] ?? 'N/A');
                $exitCode = (string) ($result['exit_code'] ?? 'N/A');
                $logPath = (string) ($result['failure_log_path'] ?? 'N/A');
                $snippet = $this->buildFailureSnippet(
                    (string) ($result['output'] ?? ''),
                    (string) ($result['error'] ?? '')
                );

                $this->line("  • {$module} (exit: {$exitCode})");
                $this->line("    Log: {$logPath}");
                $this->line('    Extracto:');
                $this->line('    ' . str_replace(PHP_EOL, PHP_EOL . '    ', $snippet));
                $this->newLine();
            }
        }

        // Mostrar ubicación de reportes si se generó coverage
        if ($this->option('coverage') && $this->coverageEnabled) {
            $this->newLine();
            $this->info('📁 Reportes de coverage guardados en:');
            $this->info("   {$this->reportsBasePath}/");
            
            $formats = $this->getCoverageFormats();
            foreach ($this->results as $result) {
                if ($result['status'] === 'passed' || $result['status'] === 'failed') {
                    $module = $result['module'];
                    
                    if (in_array('html', $formats)) {
                        $reportPath = (string) ($result['report_path'] ?? "{$this->reportsBasePath}/{$module}");
                        $htmlPath = "{$reportPath}/html/index.html";
                        if (File::exists($htmlPath)) {
                            $this->info("   • {$module}: {$htmlPath}");
                        }
                    }
                }
            }
        }

        $this->newLine();
    }
}
