<?php

namespace NgarakDev\Modularization\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

        // Prepare path parameter (always used)
        $relativePath = str_replace(base_path() . '/', '', $migrationsPath);

        // Get base options, ensuring --path is always set to scope to this module's migrations
        $options = ['--path' => $relativePath];

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

        if ($this->option('status')) {
            $command = 'migrate:status';
            $action = 'Showing migration status';
            // For status, run the migration command directly
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

        // For other commands that could affect the entire database, we need special handling
        if ($this->option('fresh')) {
            $this->info("Executing fresh migrations for module [{$moduleName}]...");

            // Find all migration batches for this module
            $migrationFiles = $this->getMigrationFilesFromPath($relativePath);

            if (empty($migrationFiles)) {
                $this->info("No migrations found for module [{$moduleName}]. Nothing to refresh.");
                return 0;
            }

            // Get tables corresponding to these migrations
            $tables = $this->getTablesFromMigrations($migrationFiles);

            // Drop tables if they exist
            $this->dropModuleTables($tables);

            // Run the migrations 
            $result = Artisan::call('migrate', $options);
            $this->output->write(Artisan::output());

            if ($result === 0) {
                $this->info("Fresh migrations for module [{$moduleName}] completed successfully.");
            } else {
                $this->error("Fresh migrations for module [{$moduleName}] failed.");
            }

            return $result;
        } else if ($this->option('rollback')) {
            $command = 'migrate:rollback';
            $action = 'Rolling back migrations';
            // Add path to ensure we only rollback this module's migrations
            $options['--path'] = $relativePath;
        } else if ($this->option('reset')) {
            $command = 'migrate:reset';
            $action = 'Resetting all migrations';
            // Add path to ensure we only reset this module's migrations
            $options['--path'] = $relativePath;
        } else if ($this->option('refresh')) {
            $command = 'migrate:refresh';
            $action = 'Refreshing all migrations';
            // Add path to ensure we only refresh this module's migrations
            $options['--path'] = $relativePath;
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

    /**
     * Get migration files from a specific path
     * 
     * @param string $relativePath
     * @return array
     */
    protected function getMigrationFilesFromPath($relativePath)
    {
        // Get all migrations for this path
        $fullPath = base_path($relativePath);
        $files = File::glob($fullPath . '/*.php');

        $migrations = [];
        foreach ($files as $file) {
            $migrations[] = pathinfo($file, PATHINFO_FILENAME);
        }

        return $migrations;
    }

    /**
     * Get tables from migration files
     * 
     * @param array $migrations
     * @return array
     */
    protected function getTablesFromMigrations($migrations)
    {
        // Query the migrations table to find matching migrations
        $migrationRecords = DB::table('migrations')
            ->whereIn('migration', $migrations)
            ->get();

        // This is a simplification - we'd ideally parse the migration files
        // to extract table names, but for this example we'll just look for
        // create_*_table pattern in migration names
        $tables = [];
        foreach ($migrations as $migration) {
            if (preg_match('/create_(\w+)_table/', $migration, $matches)) {
                $tables[] = $matches[1];
            }
        }

        return $tables;
    }

    /**
     * Drop tables associated with the module
     * 
     * @param array $tables
     * @return void
     */
    protected function dropModuleTables($tables)
    {
        Schema::disableForeignKeyConstraints();

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                $this->info("Dropping table: {$table}");
                Schema::dropIfExists($table);
            }
        }

        Schema::enableForeignKeyConstraints();
    }
}
