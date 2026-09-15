<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Console\Commands;

use Illuminate\Console\Command;
use NgarakDev\Modularization\Exceptions\ModuleNotFoundException;
use NgarakDev\Modularization\ModularizationService;
use NgarakDev\Modularization\Support\ModuleCache;

class ModuleToggleCommand extends Command
{
    protected $signature = 'module:toggle
                            {name : The name of the module to enable or disable}
                            {--enable : Explicitly enable the module}
                            {--disable : Disable the module}';

    protected $description = 'Enable or disable a module';

    public function handle(ModularizationService $service, ModuleCache $cache): int
    {
        $name = (string) $this->argument('name');
        $shouldDisable = (bool) $this->option('disable');
        // Default behaviour (no --enable / --disable) is to enable the module
        $shouldEnable = $this->option('enable') || (! $shouldDisable);

        try {
            $module = $service->findOrFail($name);
        } catch (ModuleNotFoundException) {
            $this->error("Module [{$name}] does not exist!");

            return self::FAILURE;
        }

        if ($shouldDisable) {
            if ($module->isDisabled()) {
                $this->info("Module [{$name}] is already disabled.");

                return self::SUCCESS;
            }

            $service->disable($name);
            $this->info("Module [{$name}] has been disabled.");
        } else {
            if ($module->isEnabled()) {
                $this->info("Module [{$name}] is already enabled.");

                return self::SUCCESS;
            }

            $service->enable($name);
            $this->info("Module [{$name}] has been enabled.");
        }

        // Invalidate the file cache so next boot picks up the change
        if ($cache->isCached()) {
            $cache->clear();
            $this->line('  <comment>→</comment> Module cache cleared. Run <comment>php artisan module:cache</comment> to rebuild.');
        }

        return self::SUCCESS;
    }
}
