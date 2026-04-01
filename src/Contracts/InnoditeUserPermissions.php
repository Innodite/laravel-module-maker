<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Contracts;

/**
 * InnoditeUserPermissions — Interfaz de compatibilidad de permisos
 *
 * Permite que cualquier modelo User exponga sus permisos al bridge
 * sin depender de Spatie\Permission.
 *
 * Implementación de ejemplo en el modelo User:
 *
 *   use Innodite\LaravelModuleMaker\Contracts\InnoditeUserPermissions;
 *
 *   class User extends Authenticatable implements InnoditeUserPermissions
 *   {
 *       public function getInnoditePermissions(): array
 *       {
 *           return $this->permissions->pluck('name')->toArray();
 *       }
 *   }
 */
interface InnoditeUserPermissions
{
    /**
     * Retorna un array plano de strings con los permisos del usuario.
     *
     * Cada elemento debe ser el nombre del permiso tal como se almacena
     * en la base de datos (ej: 'central.roles.edit', 'users.view').
     *
     * @return array<string>
     */
    public function getInnoditePermissions(): array;
}
