<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Generators\Components;

use Illuminate\Support\Facades\File;
use Innodite\LaravelModuleMaker\Generators\Concerns\HasStubs;
use Innodite\LaravelModuleMaker\Generators\Concerns\WritesGeneratedFiles;

/**
 * Genera el archivo de Notification para el contexto dado.
 *
 * Ejemplos de salida según contexto:
 *   central  → Notifications/Central/CentralModuleWelcomeNotification.php
 *   tenant   → Notifications/Tenant/INNODITE/TenantINNODITEModuleCustomAlert.php
 */
class NotificationGenerator
{
    use HasStubs;
    use WritesGeneratedFiles;

    public function __construct(
        private readonly array  $context,
        private readonly string $modulePath,
        private readonly string $moduleName,
    ) {}

    /**
     * Genera el archivo de Notification en la carpeta correcta según el contexto.
     *
     * @return void
     */
    public function generate(): void
    {
        $contextPrefix    = $this->context['class_prefix'] ?? '';
        $contextFolder    = str_replace('\\', '/', $this->context['folder'] ?? '');
        $contextNamespace = str_replace('/', '\\', $contextFolder);
        $moduleNamespace  = 'Modules\\' . $this->moduleName;
        $className        = $contextPrefix . $this->moduleName;

        // Central → WelcomeNotification, Tenant específico → CustomAlert
        $isTenantSpecific        = str_starts_with($contextFolder, 'Tenant/') && !str_ends_with($contextFolder, '/Shared') && $contextFolder !== 'Tenant/Shared';
        $notificationSuffix      = $isTenantSpecific ? 'CustomAlert' : 'WelcomeNotification';

        // El namespace completo lo arma quien sabe si hay contexto, no la plantilla:
        // concatenarlo en el stub deja \\ cuando el contexto esta vacio, y eso no es PHP.
        $namespace = $moduleNamespace . '\\Notifications'
            . ($contextNamespace !== '' ? '\\' . $contextNamespace : '');

        $placeholders = [
            'namespace'          => $namespace,
            'moduleNamespace'    => $moduleNamespace,
            'contextFolder'      => $contextNamespace,
            'className'          => $className,
            'moduleName'         => $this->moduleName,
            'contextPrefix'      => $contextPrefix,
            'notificationSuffix' => $notificationSuffix,
        ];

        $content = $this->getStubContent('notification.stub', true, $placeholders);

        $dir = $this->modulePath . '/Notifications/' . $contextFolder;
        File::ensureDirectoryExists($dir);
        $this->putFile(
            $dir . '/' . $className . $notificationSuffix . '.php',
            $content,
            "Notificación generada: {$className}{$notificationSuffix}.php"
        );
    }
}
