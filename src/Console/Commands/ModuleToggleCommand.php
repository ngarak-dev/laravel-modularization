<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Console\Commands;

use Illuminate\Console\Command;
use NgarakDev\Modularization\Console\Commands\Concerns\InteractsWithModules;

/**
 * Command to enable or disable modules.
 */
class ModuleToggleCommand extends Command
{
    use InteractsWithModules;

    protected $signature = 'module:toggle
                            {name : The name of the module}
                            {--disable : Disable the module instead of enabling it}
                            {--enable : Enable the module}';

    protected $description = 'Enable or disable a module';

    public function handle(): int
    {
        try {
            $name = $this->parseModuleName((string) $this->argument('name'));
            $module = $this->moduleOrFail($name);
        } catch (\Throwable $exception) {
            return $this->failOnInvalidName($exception);
        }

        if ($this->option('enable') && $this->option('disable')) {
            $this->error('Pass either --enable or --disable, not both.');

            return self::FAILURE;
        }

        $disable = (bool) $this->option('disable');

        if ($disable) {
            if (! $module->enabled) {
                $this->info("Module [{$name}] is already disabled.");

                return self::SUCCESS;
            }

            $dependents = $this->modules()->getDependents($name);

            if ($dependents->isNotEmpty()) {
                $this->warn("The following modules depend on [{$name}]: ".$dependents->keys()->implode(', '));

                if (! $this->confirm('Do you want to continue?', false)) {
                    $this->info('Operation cancelled.');

                    return self::SUCCESS;
                }
            }

            $this->modules()->disable($name);
            $this->info("Module [{$name}] has been disabled.");
        } else {
            if ($module->enabled) {
                $this->info("Module [{$name}] is already enabled.");

                return self::SUCCESS;
            }

            $this->modules()->enable($name);
            $this->info("Module [{$name}] has been enabled.");
        }

        if ($this->modules()->isCached()) {
            $this->warn('Module cache was cleared. Run `php artisan module:cache` before deploying to production.');
        }

        return self::SUCCESS;
    }
}
