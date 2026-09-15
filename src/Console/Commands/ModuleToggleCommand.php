<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use NgarakDev\Modularization\Exceptions\ModuleNotFoundException;
use NgarakDev\Modularization\ModuleManager;

/**
 * Command to enable or disable modules.
 */
class ModuleToggleCommand extends Command
{
    protected $signature = 'module:toggle
                            {name : The name of the module}
                            {--enable : Enable the module}
                            {--disable : Disable the module}';

    protected $description = 'Enable or disable a module';

    public function __construct(
        private readonly Filesystem $files,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $moduleName = $this->argument('name');
        $enableFlag = $this->option('enable');
        $disableFlag = $this->option('disable');

        /** @var ModuleManager $manager */
        $manager = app(ModuleManager::class);

        if (!$manager->has($moduleName)) {
            $this->error("Module [{$moduleName}] does not exist.");
            return self::FAILURE;
        }

        try {
            if ($disableFlag) {
                $this->disableModule($manager, $moduleName);
            } elseif ($enableFlag) {
                $this->enableModule($manager, $moduleName);
            } else {
                $this->toggleModule($manager, $moduleName);
            }
        } catch (ModuleNotFoundException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->clearConfigCache();

        return self::SUCCESS;
    }

    private function enableModule(ModuleManager $manager, string $name): void
    {
        if ($manager->isEnabled($name)) {
            $this->info("Module [{$name}] is already enabled.");
            return;
        }

        $manager->enable($name);
        $this->info("Module [{$name}] has been enabled.");
    }

    private function disableModule(ModuleManager $manager, string $name): void
    {
        if ($manager->isDisabled($name)) {
            $this->info("Module [{$name}] is already disabled.");
            return;
        }

        $dependents = $manager->getDependents($name);

        if ($dependents->isNotEmpty()) {
            $depList = $dependents->keys()->implode(', ');
            $this->warn("The following modules depend on [{$name}]: {$depList}");

            if (!$this->confirm('Do you want to continue?', false)) {
                $this->info('Operation cancelled.');
                return;
            }
        }

        $manager->disable($name);
        $this->info("Module [{$name}] has been disabled.");
    }

    private function toggleModule(ModuleManager $manager, string $name): void
    {
        $wasEnabled = $manager->isEnabled($name);

        if ($wasEnabled) {
            $this->disableModule($manager, $name);
        } else {
            $this->enableModule($manager, $name);
        }
    }

    private function clearConfigCache(): void
    {
        if ($this->laravel->bound('Illuminate\Contracts\Console\Kernel')) {
            try {
                $this->call('config:clear');
            } catch (\Exception) {
                // Config cache might not exist
            }
        }
    }
}
