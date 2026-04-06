<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Exceptions;

class ConnectionNotConfiguredException extends \RuntimeException
{
    public static function forContext(string $contextId, string $connectionKey): self
    {
        return new self(
            "La conexión '{$connectionKey}' del contexto '{$contextId}' no existe en config/database.php. " .
            "Créala manualmente o ejecuta innodite:make-connections."
        );
    }
}
