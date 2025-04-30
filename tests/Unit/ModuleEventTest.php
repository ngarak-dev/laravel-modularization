<?php

namespace NgarakDev\Modularization\Tests\Unit;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Orchestra\Testbench\TestCase;
use NgarakDev\Modularization\Console\Commands\MakeModuleCommand;
use NgarakDev\Modularization\Console\Commands\MakeModuleEventCommand;
use NgarakDev\Modularization\Providers\ModularizationServiceProvider;

class ModuleEventTest extends TestCase
{
    protected $files;
    protected $testModuleName = 'EventTest';
    protected $modulesPath;

    protected function getPackageProviders($app)
    {
        return [
            ModularizationServiceProvider::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem();
        $this->modulesPath = base_path('modules');

        // Make sure we have a clean test environment
        if ($this->files->isDirectory($this->modulesPath . '/' . $this->testModuleName)) {
            $this->files->deleteDirectory($this->modulesPath . '/' . $this->testModuleName);
        }

        // Ensure modules directory exists
        if (!$this->files->isDirectory($this->modulesPath)) {
            $this->files->makeDirectory($this->modulesPath, 0755, true);
        }

        // Register the commands
        $this->app->singleton('command.module.make', function ($app) {
            return new MakeModuleCommand($app['files']);
        });

        $this->app->singleton('command.module.make-event', function ($app) {
            return new MakeModuleEventCommand($app['files']);
        });

        // Create a test module first
        $this->artisan('make:module', ['name' => $this->testModuleName])->run();
    }

    protected function tearDown(): void
    {
        // Clean up the test module
        if ($this->files->isDirectory($this->modulesPath . '/' . $this->testModuleName)) {
            $this->files->deleteDirectory($this->modulesPath . '/' . $this->testModuleName);
        }

        parent::tearDown();
    }

    /** @test */
    public function it_can_create_an_event()
    {
        $eventName = 'UserRegistered';

        // Execute the command
        $this->artisan('module:make-event', [
            'module' => $this->testModuleName,
            'name' => $eventName
        ])
            ->expectsOutput("Event [{$eventName}Event] created successfully.")
            ->assertExitCode(0);

        // Check that the event file was created
        $eventPath = $this->modulesPath . '/' . $this->testModuleName . '/Events/' . $eventName . 'Event.php';
        $this->assertTrue($this->files->exists($eventPath));

        // Check event content
        $eventContent = $this->files->get($eventPath);
        $this->assertStringContainsString("class {$eventName}Event", $eventContent);
        $this->assertStringContainsString('use Dispatchable, InteractsWithSockets, SerializesModels;', $eventContent);
    }

    /** @test */
    public function it_can_create_an_event_with_a_listener()
    {
        $eventName = 'ItemCreated';
        $listenerName = 'UpdateInventory';

        // Execute the command with listener
        $this->artisan('module:make-event', [
            'module' => $this->testModuleName,
            'name' => $eventName,
            '--listener' => $listenerName
        ])
            ->expectsOutput("Event [{$eventName}Event] created successfully.")
            ->expectsOutput("Listener [{$listenerName}Listener] created successfully.")
            ->assertExitCode(0);

        // Check that the event file was created
        $eventPath = $this->modulesPath . '/' . $this->testModuleName . '/Events/' . $eventName . 'Event.php';
        $this->assertTrue($this->files->exists($eventPath));

        // Check that the listener file was created
        $listenerPath = $this->modulesPath . '/' . $this->testModuleName . '/Listeners/' . $listenerName . 'Listener.php';
        $this->assertTrue($this->files->exists($listenerPath));

        // Check listener content
        $listenerContent = $this->files->get($listenerPath);
        $this->assertStringContainsString("class {$listenerName}Listener", $listenerContent);
        $this->assertStringContainsString("use " . config('modularization.namespace', 'Modules') . "\\{$this->testModuleName}\\Events\\{$eventName}Event;", $listenerContent);
        $this->assertStringContainsString("public function handle({$eventName}Event \$event)", $listenerContent);
    }

    /** @test */
    public function it_can_create_an_event_with_multiple_listeners()
    {
        $eventName = 'OrderPlaced';
        $listeners = ['SendOrderConfirmation', 'UpdateStock', 'NotifyAdmins'];

        // Execute the command with multiple listeners
        $this->artisan('module:make-event', [
            'module' => $this->testModuleName,
            'name' => $eventName,
            '--listeners' => $listeners
        ])
            ->expectsOutput("Event [{$eventName}Event] created successfully.")
            ->assertExitCode(0);

        // Check that the event file was created
        $eventPath = $this->modulesPath . '/' . $this->testModuleName . '/Events/' . $eventName . 'Event.php';
        $this->assertTrue($this->files->exists($eventPath));

        // Check that each listener file was created
        foreach ($listeners as $listener) {
            $listenerPath = $this->modulesPath . '/' . $this->testModuleName . '/Listeners/' . $listener . 'Listener.php';
            $this->assertTrue($this->files->exists($listenerPath));

            // Check listener content
            $listenerContent = $this->files->get($listenerPath);
            $this->assertStringContainsString("class {$listener}Listener", $listenerContent);
        }
    }

    /** @test */
    public function it_registers_events_in_module_service_provider()
    {
        $eventName = 'UserSubscribed';
        $listenerName = 'SendWelcomeEmail';

        // Execute the command
        $this->artisan('module:make-event', [
            'module' => $this->testModuleName,
            'name' => $eventName,
            '--listener' => $listenerName
        ])->assertExitCode(0);

        // Check service provider for event registration
        $providerPath = $this->modulesPath . '/' . $this->testModuleName . '/Providers/' . $this->testModuleName . 'ServiceProvider.php';
        $this->assertTrue($this->files->exists($providerPath));

        $providerContent = $this->files->get($providerPath);

        // Check for event registration
        $namespace = config('modularization.namespace', 'Modules');
        $this->assertStringContainsString("\$this->app['events']->listen(", $providerContent);
        $this->assertStringContainsString("\\{$namespace}\\{$this->testModuleName}\\Events\\{$eventName}Event::class", $providerContent);
        $this->assertStringContainsString("\\{$namespace}\\{$this->testModuleName}\\Listeners\\{$listenerName}Listener::class", $providerContent);
    }
}
