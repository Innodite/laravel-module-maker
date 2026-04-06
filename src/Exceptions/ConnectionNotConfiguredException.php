<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Exceptions;

class ConnectionNotConfiguredException extends \RuntimeException
{
    public static function forContext(string $contextId, string $connectionKey): self
    {
        return new self(
            "La conexión '{$connectionKey}' para el contexto '{$contextId}' no está definida en config/database.php. Regístrela en config/database.php antes de ejecutar migraciones."
        );
    }
}
