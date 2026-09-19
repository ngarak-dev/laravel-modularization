<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use NgarakDev\Modularization\Console\Commands\Concerns\InteractsWithModules;
use NgarakDev\Modularization\Exceptions\InvalidModuleException;
use NgarakDev\Modularization\Exceptions\ModuleGenerationException;
use NgarakDev\Modularization\Exceptions\ModuleNotFoundException;
use NgarakDev\Modularization\Generators\ClassGenerator;
use NgarakDev\Modularization\Support\ModuleName;

class MakeModuleEventCommand extends Command
{
    use InteractsWithModules;

    protected $signature = 'module:make-event
                            {module : The name of the module}
                            {name : The name of the event}
                            {--listener= : Generate a listener for the event}
                            {--listeners=* : Generate multiple listeners for the event}
                            {--force : Overwrite existing files}';

    protected $description = 'Create a new event and optional listeners for a module';

    public function handle(ClassGenerator $generator): int
    {
        try {
            $module = ModuleName::parse((string) $this->argument('module'))->studly();
            $name = (string) $this->argument('name');
            $eventClass = $name.(Str::endsWith($name, 'Event') ? '' : 'Event');
            $force = (bool) $this->option('force');

            $generator->generate($module, 'event', $eventClass, $force);
            $this->info("Event [{$eventClass}] created successfully.");

            $listeners = array_values(array_filter(array_map(
                'strval',
                array_merge(
                    $this->option('listener') ? [(string) $this->option('listener')] : [],
                    is_array($this->option('listeners')) ? $this->option('listeners') : []
                )
            )));

            foreach ($listeners as $listener) {
                $listenerClass = $listener.(Str::endsWith($listener, 'Listener') ? '' : 'Listener');
                $generator->generate($module, 'listener', $listenerClass, $force, [
                    '{{eventName}}' => $eventClass,
                    '{{eventType}}' => $eventClass,
                    '{{eventImport}}' => "\nuse ".$this->laravel['config']->get('modularization.namespace', 'Modules')."\\{$module}\\Events\\{$eventClass};\n",
                ]);
                $this->info("Listener [{$listenerClass}] created successfully.");
            }

            $this->registerInProvider($module, $eventClass, $listeners);
        } catch (InvalidModuleException|ModuleNotFoundException|ModuleGenerationException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<int, string>  $listeners
     */
    private function registerInProvider(string $module, string $eventClass, array $listeners): void
    {
        $providerPath = $this->modules()->path($module, 'Providers/'.$module.'ServiceProvider.php');
        /** @var Filesystem $files */
        $files = $this->laravel->make(Filesystem::class);

        if (! $files->exists($providerPath)) {
            $this->warn('Service provider not found, skipping registration.');

            return;
        }

        $content = $files->get($providerPath);

        if (! str_contains($content, 'boot()')) {
            $this->warn('Could not locate boot method in service provider, skipping registration.');

            return;
        }

        $namespace = config('modularization.namespace', 'Modules');
        $eventMapping = "\$this->app['events']->listen(\n";
        $eventMapping .= "            \\{$namespace}\\{$module}\\Events\\{$eventClass}::class,\n";

        if ($listeners === []) {
            $eventMapping .= "            // Add your listener classes here\n";
        } elseif (count($listeners) === 1) {
            $listenerClass = $listeners[0].(Str::endsWith($listeners[0], 'Listener') ? '' : 'Listener');
            $eventMapping .= "            \\{$namespace}\\{$module}\\Listeners\\{$listenerClass}::class\n";
        } else {
            $eventMapping .= "            [\n";
            foreach ($listeners as $index => $listener) {
                $listenerClass = $listener.(Str::endsWith($listener, 'Listener') ? '' : 'Listener');
                $eventMapping .= "                \\{$namespace}\\{$module}\\Listeners\\{$listenerClass}::class";
                $eventMapping .= ($index < count($listeners) - 1) ? ",\n" : "\n";
            }
            $eventMapping .= "            ]\n";
        }

        $eventMapping .= '        );';

        if (preg_match('/public function boot\(\).*?\{.*?\}/s', $content, $matches) !== 1) {
            $this->warn('Could not update service provider, please register the event manually.');

            return;
        }

        $bootMethod = $matches[0];
        $newBoot = Str::replaceLast('}', "    // Register Events\n        {$eventMapping}\n    }", $bootMethod);
        $files->put($providerPath, Str::replaceFirst($bootMethod, $newBoot, $content));
        $this->info('Event and listeners registered in service provider.');
    }
}
