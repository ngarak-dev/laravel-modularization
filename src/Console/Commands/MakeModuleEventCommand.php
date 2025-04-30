<?php

namespace NgarakDev\Modularization\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class MakeModuleEventCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'module:make-event 
                            {module : The name of the module}
                            {name : The name of the event}
                            {--listener= : Generate a listener for the event}
                            {--listeners=* : Generate multiple listeners for the event}
                            {--force : Overwrite existing files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new event and optional listeners for a module';

    /**
     * The filesystem instance.
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $files;

    /**
     * Create a new command instance.
     *
     * @param  \Illuminate\Filesystem\Filesystem  $files
     * @return void
     */
    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $module = $this->argument('module');
        $name = $this->argument('name');
        $listener = $this->option('listener');
        $listeners = $this->option('listeners');
        $force = $this->option('force');

        $modulesPath = base_path(config('modularization.modules_path', 'modules'));
        $modulePath = $modulesPath . '/' . $module;

        // Check if module exists
        if (!$this->files->isDirectory($modulePath)) {
            $this->error("Module [{$module}] does not exist!");
            return 1;
        }

        // Create necessary directories
        $eventsDir = $modulePath . '/Events';
        $listenersDir = $modulePath . '/Listeners';

        if (!$this->files->isDirectory($eventsDir)) {
            $this->files->makeDirectory($eventsDir, 0755, true);
        }

        if (!$this->files->isDirectory($listenersDir)) {
            $this->files->makeDirectory($listenersDir, 0755, true);
        }

        // Create Event class
        $eventClass = $name . (Str::endsWith($name, 'Event') ? '' : 'Event');
        $eventPath = $eventsDir . '/' . $eventClass . '.php';

        if ($this->files->exists($eventPath) && !$force) {
            $this->error("Event [{$eventClass}] already exists!");
            return 1;
        }

        $namespace = config('modularization.namespace', 'Modules');

        $eventContent = $this->getEventStub([
            '{{namespace}}' => $namespace,
            '{{moduleName}}' => $module,
            '{{eventName}}' => $eventClass,
        ]);

        $this->files->put($eventPath, $eventContent);
        $this->info("Event [{$eventClass}] created successfully.");

        // Create a single listener if specified with --listener
        if ($listener) {
            $this->createListener($module, $namespace, $listenersDir, $eventClass, $listener, $force);
        }

        // Create multiple listeners if specified with --listeners
        if (!empty($listeners)) {
            foreach ($listeners as $listener) {
                $this->createListener($module, $namespace, $listenersDir, $eventClass, $listener, $force);
            }
        }

        // Register event and listeners in the service provider
        $this->updateServiceProvider($module, $modulePath, $namespace, $eventClass, $listener, $listeners);

        return 0;
    }

    /**
     * Create a new event listener.
     *
     * @param string $module
     * @param string $namespace
     * @param string $listenersDir
     * @param string $eventClass
     * @param string $listener
     * @param bool $force
     * @return void
     */
    protected function createListener($module, $namespace, $listenersDir, $eventClass, $listener, $force)
    {
        $listenerClass = $listener . (Str::endsWith($listener, 'Listener') ? '' : 'Listener');
        $listenerPath = $listenersDir . '/' . $listenerClass . '.php';

        if ($this->files->exists($listenerPath) && !$force) {
            $this->error("Listener [{$listenerClass}] already exists!");
            return;
        }

        $listenerContent = $this->getListenerStub([
            '{{namespace}}' => $namespace,
            '{{moduleName}}' => $module,
            '{{listenerName}}' => $listenerClass,
            '{{eventName}}' => $eventClass,
        ]);

        $this->files->put($listenerPath, $listenerContent);
        $this->info("Listener [{$listenerClass}] created successfully.");
    }

    /**
     * Update the module service provider to register the event and listeners.
     *
     * @param string $module
     * @param string $modulePath
     * @param string $namespace
     * @param string $eventClass
     * @param string|null $listener
     * @param array $listeners
     * @return void
     */
    protected function updateServiceProvider($module, $modulePath, $namespace, $eventClass, $listener, $listeners)
    {
        $providerPath = $modulePath . '/Providers/' . $module . 'ServiceProvider.php';

        if (!$this->files->exists($providerPath)) {
            $this->warn("Service provider not found, skipping registration.");
            return;
        }

        $content = $this->files->get($providerPath);

        // Check if the file contains boot method
        if (!Str::contains($content, 'boot()')) {
            $this->warn("Could not locate boot method in service provider, skipping registration.");
            return;
        }

        // Prepare the event mapping code
        $eventMapping = "\$this->app['events']->listen(\n";
        $eventMapping .= "            \\{$namespace}\\{$module}\\Events\\{$eventClass}::class,\n";

        // Add single listener if specified
        if ($listener) {
            $listenerClass = $listener . (Str::endsWith($listener, 'Listener') ? '' : 'Listener');
            $eventMapping .= "            \\{$namespace}\\{$module}\\Listeners\\{$listenerClass}::class\n";
        }
        // Or add multiple listeners if specified
        elseif (!empty($listeners)) {
            $eventMapping .= "            [\n";
            foreach ($listeners as $index => $listener) {
                $listenerClass = $listener . (Str::endsWith($listener, 'Listener') ? '' : 'Listener');
                $eventMapping .= "                \\{$namespace}\\{$module}\\Listeners\\{$listenerClass}::class";
                $eventMapping .= ($index < count($listeners) - 1) ? ",\n" : "\n";
            }
            $eventMapping .= "            ]\n";
        } else {
            // No listeners registered
            $eventMapping .= "            // Add your listener classes here\n";
        }

        $eventMapping .= "        );";

        // Find the position to insert the event mapping code
        $bootMethod = $this->findBootMethod($content);

        if ($bootMethod) {
            $newContent = Str::replaceFirst(
                $bootMethod,
                Str::replaceLast(
                    '}',
                    "    // Register Events\n        {$eventMapping}\n    }",
                    $bootMethod
                ),
                $content
            );

            $this->files->put($providerPath, $newContent);
            $this->info("Event and listeners registered in service provider.");
        } else {
            $this->warn("Could not update service provider, please register the event manually.");
        }
    }

    /**
     * Find the boot method in the service provider content.
     *
     * @param string $content
     * @return string|null
     */
    protected function findBootMethod($content)
    {
        preg_match('/public function boot\(\).*?{.*?}/s', $content, $matches);
        return $matches[0] ?? null;
    }

    /**
     * Get the event stub content.
     *
     * @param array $replacements
     * @return string
     */
    protected function getEventStub($replacements = [])
    {
        $stub = <<<'EOT'
<?php

namespace {{namespace}}\{{moduleName}}\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class {{eventName}}
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('channel-name'),
        ];
    }
}
EOT;

        foreach ($replacements as $search => $replace) {
            $stub = str_replace($search, $replace, $stub);
        }

        return $stub;
    }

    /**
     * Get the listener stub content.
     *
     * @param array $replacements
     * @return string
     */
    protected function getListenerStub($replacements = [])
    {
        $stub = <<<'EOT'
<?php

namespace {{namespace}}\{{moduleName}}\Listeners;

use {{namespace}}\{{moduleName}}\Events\{{eventName}};
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class {{listenerName}}
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle({{eventName}} $event): void
    {
        // Handle the event
    }
}
EOT;

        foreach ($replacements as $search => $replace) {
            $stub = str_replace($search, $replace, $stub);
        }

        return $stub;
    }
}
