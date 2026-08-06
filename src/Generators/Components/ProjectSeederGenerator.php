<?php

declare(strict_types=1);

namespace Innodite\LaravelModuleMaker\Generators\Components;

use Illuminate\Support\Facades\File;
use Innodite\LaravelModuleMaker\Generators\Concerns\HasStubs;
use Innodite\LaravelModuleMaker\Generators\Concerns\WritesGeneratedFiles;
use Innodite\LaravelModuleMaker\Support\ModuleMode;
use Innodite\LaravelModuleMaker\Support\SeederNames;

/**
 * Escribe los seeders **del proyecto**, en `database/seeders/`: los despliegues y el webmaster.
 *
 * **Los escribe el instalador y no `make-module`, y esa es la decisión de fondo.** Son del proyecto:
 * hay uno —o dos— por proyecto, no uno por módulo, y existen antes que el primer módulo. `make-module`
 * solo añade la entrada de cada subfuncionalidad al orden que estos leen. Generarlos al crear un
 * módulo los ataría al primero que se creara, y reescribirlos al crear el segundo se llevaría por
 * delante lo que el desarrollador hubiera añadido dentro.
 *
 * **Cuántos despliegues, lo dice el modo.** En una aplicación única, **uno**: no hay dos contextos que
 * separar ni dos bases de datos que llenar. En multitenant, **dos**, porque son dos despliegues
 * distintos contra dos bases distintas — y el de un tenant se ejecuta una vez por tenant.
 *
 * **El webmaster, en cambio, es siempre uno**, y no porque se decida aquí: no sabe nada de contextos,
 * recoge los permisos que haya **en la base donde se le invoque**. En multitenant el despliegue
 * central le da los centrales y el de cada tenant los suyos, con el mismo archivo.
 *
 * **No sobreescribe.** El `run()` de un despliegue es la secuencia de pasos que el desarrollador lee y
 * amplía, y el del webmaster puede acabar creando también los roles de la casa; volver a instalar no
 * puede llevarse nada de eso.
 */
class ProjectSeederGenerator
{
    use HasStubs;
    use WritesGeneratedFiles;

    public function __construct(
        private readonly ModuleMode $mode,
        private readonly ?object $output = null,
    ) {
    }

    /**
     * Escribe los que falten y devuelve **todos** los que el modo pide, existieran ya o no.
     *
     * Los devuelve todos a propósito: quien los engancha al `DatabaseSeeder` necesita la lista
     * completa, no solo la de los recién escritos — reinstalar sobre un proyecto que ya los tenía
     * dejaría el `DatabaseSeeder` sin enganchar ninguno.
     *
     * @return array<int, string> Nombres de clase, en el orden en que se despliegan
     */
    public function generate(): array
    {
        $destino = database_path('seeders');

        File::ensureDirectoryExists($destino);

        $escritos = [];

        foreach ($this->deployments() as $deployKey => $definicion) {
            $className = SeederNames::projectDeploySeeder($deployKey ?: null);
            $escritos[] = $className;

            $archivo = "{$destino}/{$className}.php";

            if (File::exists($archivo)) {
                $this->warn("   {$className} ya existe. No se sobreescribió.");

                continue;
            }

            $this->putFile(
                $archivo,
                $this->getStubContent('project-deploy-seeder.stub', false, [
                    'seederName'    => $className,
                    'deployLabel'   => $definicion['label'],
                    'contextOption' => $deployKey === '' ? '' : " --context={$deployKey}",
                    'contextsDoc'   => $this->envuelto($definicion['doc']),
                    'contexts'      => $this->literal($definicion['contexts']),
                ]),
                "Seeder de despliegue '{$className}' creado en database/seeders/."
            );
        }

        $this->writeWebmaster($destino);

        return $escritos;
    }

    /**
     * El webmaster: uno, y el mismo para todos los contextos.
     *
     * Lo llama el despliegue justo después de los permisos, así que no se engancha al
     * `DatabaseSeeder` — enganchar los dos lo ejecutaría dos veces por despliegue.
     */
    private function writeWebmaster(string $destino): void
    {
        $archivo = "{$destino}/WebmasterSeeder.php";

        if (File::exists($archivo)) {
            $this->warn('   WebmasterSeeder ya existe. No se sobreescribió.');

            return;
        }

        $this->putFile(
            $archivo,
            $this->getStubContent('webmaster-seeder.stub', false),
            "Seeder 'WebmasterSeeder' creado en database/seeders/."
        );
    }

    /**
     * Qué despliegues necesita este proyecto — lo decide el modo, no el contenido del proyecto.
     *
     * La clave es la del despliegue (`--context` del comando), no la del array `deploy`: un
     * despliegue de tenant cubre **varias** claves del orden, porque `tenant`, `tenant_shared` y
     * `shared` viven en la misma base de datos. Que cuáles son quede escrito en el archivo generado
     * —y no aquí— es lo que permite corregirlo en un proyecto donde `shared` viva en la central.
     *
     * @return array<string, array{label: string, doc: string, contexts: array<int, string>}>
     */
    private function deployments(): array
    {
        if (! $this->mode->hasContextAxis()) {
            return ['' => [
                'label'    => 'Despliegue del proyecto',
                'doc'      => 'Vacío: sin eje de contexto se lee la lista entera, de arriba abajo.',
                'contexts' => [],
            ]];
        }

        return [
            'central' => [
                'label'    => 'Despliegue de la aplicación central',
                'doc'      => 'Solo lo central. Lo de los tenants lo despliega '
                    . SeederNames::projectDeploySeeder('tenant') . ', contra otra base de datos.',
                'contexts' => ['central'],
            ],
            'tenant' => [
                'label'    => 'Despliegue de un tenant',
                'doc'      => 'Las claves que viven en la base del tenant. Si en tu proyecto '
                    . "'shared' vive en la central, quítala de aquí y añádela al despliegue central.",
                'contexts' => ['tenant', 'tenant_shared', 'shared'],
            ],
        ];
    }

    /**
     * El texto de la explicación, ajustado al ancho del archivo que lo va a alojar.
     *
     * Se ve al abrir lo generado y no en ninguna afirmación: el placeholder cae dentro de un
     * docblock, así que una frase larga sale como una línea que desborda mientras el resto del
     * archivo respeta el margen. Es de la misma familia que la sangría del array de permisos de
     * TASK-003 — nada que rompa, y sin embargo lo primero que se nota al leerlo (R77).
     */
    private function envuelto(string $texto): string
    {
        return wordwrap($texto, 92, "\n     * ");
    }

    /**
     * El array de contextos, escrito como PHP legible dentro del archivo generado.
     *
     * @param  array<int, string>  $contexts
     */
    private function literal(array $contexts): string
    {
        if ($contexts === []) {
            return '[]';
        }

        return "['" . implode("', '", $contexts) . "']";
    }

    public function info(string $message): void
    {
        if ($this->output !== null && method_exists($this->output, 'info')) {
            $this->output->info($message);
        }
    }

    public function warn(string $message): void
    {
        if ($this->output !== null && method_exists($this->output, 'warn')) {
            $this->output->warn($message);
        }
    }
}
