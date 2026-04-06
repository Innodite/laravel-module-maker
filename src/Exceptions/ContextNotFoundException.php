<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Exceptions;

use InvalidArgumentException;

/**
 * Excepción lanzada cuando un contexto no puede ser resuelto por su ID.
 *
 * @package Innodite\LaravelModuleMaker
 * @version 3.5.0
 */
class ContextNotFoundException extends InvalidArgumentException
{
    /**
     * Crea excepción para ID no encontrado en un contexto.
     *
     * @param  string  $contextKey  Clave del contexto (central|shared|tenant_shared|tenant)
     * @param  string  $id          ID buscado
     * @param  array<int, string>  $availableIds  IDs disponibles en el contexto
     * @return self
     */
    public static function forId(string $contextKey, string $id, array $availableIds = []): self
    {
        $message = sprintf(
            "[ContextResolver] No se encontró el contexto con id '%s' en '%s'.",
            $id,
            $contextKey
        );

        if (!empty($availableIds)) {
            $message .= sprintf(
                "\nIDs disponibles: %s\nEdita module-maker-config/contexts.json.",
                implode(', ', $availableIds)
            );
        }

        return new self($message);
    }

    /**
     * Crea excepción para clave de contexto no encontrada.
     *
     * @param  string  $contextKey  Clave del contexto buscada
     * @param  array<int, string>  $availableKeys  Claves disponibles
     * @return self
     */
    public static function contextKeyNotFound(string $contextKey, array $availableKeys = []): self
    {
        $message = sprintf(
            "[ContextResolver] La clave de contexto '%s' no existe en contexts.json.",
            $contextKey
        );

        if (!empty($availableKeys)) {
            $message .= sprintf(
                "\nClaves disponibles: %s\nEdita module-maker-config/contexts.json.",
                implode(', ', $availableKeys)
            );
        }

        return new self($message);
    }

    /**
     * Crea excepción para connection_key inválido.
     *
     * @param  string  $contextId  ID del contexto
     * @param  string  $connectionKey  Clave de conexión inexistente
     * @return self
     */
    public static function invalidConnectionKey(string $contextId, string $connectionKey): self
    {
        return new self(sprintf(
            "[ContextResolver] El contexto '%s' define connection_key='%s' pero no existe en config/database.php.\n" .
            "Añade la conexión a config/database.php o ejecuta: php artisan innodite:make-connections",
            $contextId,
            $connectionKey
        ));
    }
}
