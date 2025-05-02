<?php

namespace NgarakDev\Modularization\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;

class MigrateModulesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'module:migrate-all
                            {--force : Force the operation to run when in production}
                            {--seed : Indicates if the seed task should be re-run}
                            {--step : Force the migrations to be run so they can be rolled back individually}
                            {--pretend : Dump the SQL queries that would be run}
                            {--only-enabled : Run migrations only for enabled modules}
                            {--fresh : Drop all tables and re-run all migrations}
                            {--rollback : Rollback the last database migration}
                            {--status : Show the status of each migration}
                            {--reset : Rollback all database migrations}
                            {--refresh : Reset and re-run all migrations}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run migrations for all modules';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $modulesPath = base_path(config('modularization.modules_path', 'modules'));

        // Check if modules directory exists
        if (!File::isDirectory($modulesPath)) {
            $this->error("Modules directory does not exist.");
            return 1;
        }

        // Get all module directories
        $modules = collect(File::directories($modulesPath))
            ->map(function ($directory) {
                return basename($directory);
            });

        if ($modules->isEmpty()) {
            $this->warn("No modules found.");
            return 0;
        }

        // Determine which migrate command is being requested
        $action = 'migration';
        if ($this->option('fresh')) {
            $action = 'fresh migration';
        } else if ($this->option('rollback')) {
            $action = 'rollback';
        } else if ($this->option('status')) {
            $action = 'status check';
        } else if ($this->option('reset')) {
            $action = 'reset';
        } else if ($this->option('refresh')) {
            $action = 'refresh';
        }

        $onlyEnabled = $this->option('only-enabled');
        $failedModules = [];
        $successCount = 0;

        foreach ($modules as $module) {
            // Skip disabled modules if only-enabled flag is set
            if ($onlyEnabled) {
                $isDisabled = File::exists($modulesPath . '/' . $module . '/.disabled');
                $configFile = $modulesPath . '/' . $module . '/Config/config.php';

                if ($isDisabled) {
                    $this->info("Skipping disabled module [{$module}]");
                    continue;
                }

                if (File::exists($configFile)) {
                    $config = include $configFile;
                    if (isset($config['enabled']) && $config['enabled'] === false) {
                        $this->info("Skipping disabled module [{$module}] (per config)");
                        continue;
                    }
                }
            }

            // Build command options
            $options = ['name' => $module];

            if ($this->option('force')) {
                $options['--force'] = true;
            }

            if ($this->option('seed')) {
                $options['--seed'] = true;
            }

            if ($this->option('step')) {
                $options['--step'] = true;
            }

            if ($this->option('pretend')) {
                $options['--pretend'] = true;
            }

            if ($this->option('fresh')) {
                $options['--fresh'] = true;
            }

            if ($this->option('rollback')) {
                $options['--rollback'] = true;
            }

            if ($this->option('status')) {
                $options['--status'] = true;
            }

            if ($this->option('reset')) {
                $options['--reset'] = true;
            }

            if ($this->option('refresh')) {
                $options['--refresh'] = true;
            }

            // Execute migration for this module
            $this->info("\nProcessing {$action} for module [{$module}]...");
            $result = Artisan::call('module:migrate', $options);

            $this->output->write(Artisan::output());

            if ($result !== 0) {
                $failedModules[] = $module;
            } else {
                $successCount++;
            }
        }

        $this->newLine();
        $this->info("Migration Summary:");
        $this->info("- {$successCount} modules processed successfully");

        if (count($failedModules) > 0) {
            $this->error("- " . count($failedModules) . " modules failed: " . implode(', ', $failedModules));
            return 1;
        }

        return 0;
    }
}
