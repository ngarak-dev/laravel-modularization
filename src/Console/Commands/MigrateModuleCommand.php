<?php

namespace NgarakDev\Modularization\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;

class MigrateModuleCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'module:migrate 
                            {name : The name of the module}
                            {--force : Force the operation to run when in production}
                            {--seed : Indicates if the seed task should be re-run}
                            {--step : Force the migrations to be run so they can be rolled back individually}
                            {--pretend : Dump the SQL queries that would be run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run migrations for a specific module';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $moduleName = $this->argument('name');
        $modulesPath = base_path(config('modularization.modules_path', 'modules'));
        $modulePath = $modulesPath . '/' . $moduleName;

        // Check if module exists
        if (!File::isDirectory($modulePath)) {
            $this->error("Module [{$moduleName}] does not exist.");
            return 1;
        }

        // Check if module has migrations
        $migrationsPath = $modulePath . '/Database/Migrations';
        if (!File::isDirectory($migrationsPath) || count(File::glob($migrationsPath . '/*.php')) === 0) {
            $this->warn("No migrations found for module [{$moduleName}].");
            return 0;
        }

        // Get options
        $options = ['--path' => str_replace(base_path() . '/', '', $migrationsPath)];

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

        // Run the migrations
        $this->info("Running migrations for module [{$moduleName}]...");
        $result = Artisan::call('migrate', $options);

        $this->info(Artisan::output());

        if ($result === 0) {
            $this->info("Migrations for module [{$moduleName}] completed successfully.");
        } else {
            $this->error("Migrations for module [{$moduleName}] failed.");
        }

        return $result;
    }
}
