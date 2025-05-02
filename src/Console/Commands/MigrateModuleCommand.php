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
                            {--pretend : Dump the SQL queries that would be run}
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

        // Get base options
        $options = ['--path' => str_replace(base_path() . '/', '', $migrationsPath)];

        // Process standard options
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

        // Determine which migrate command to run based on flags
        $command = 'migrate';
        $action = 'Running migrations';

        if ($this->option('fresh')) {
            $command = 'migrate:fresh';
            $action = 'Refreshing database and re-running all migrations';
        } else if ($this->option('rollback')) {
            $command = 'migrate:rollback';
            $action = 'Rolling back migrations';
        } else if ($this->option('status')) {
            $command = 'migrate:status';
            $action = 'Showing migration status';
        } else if ($this->option('reset')) {
            $command = 'migrate:reset';
            $action = 'Resetting all migrations';
        } else if ($this->option('refresh')) {
            $command = 'migrate:refresh';
            $action = 'Refreshing all migrations';
        }

        // Run the migrations
        $this->info("{$action} for module [{$moduleName}]...");
        $result = Artisan::call($command, $options);

        $this->output->write(Artisan::output());

        if ($result === 0) {
            $this->info("{$action} for module [{$moduleName}] completed successfully.");
        } else {
            $this->error("{$action} for module [{$moduleName}] failed.");
        }

        return $result;
    }
}
