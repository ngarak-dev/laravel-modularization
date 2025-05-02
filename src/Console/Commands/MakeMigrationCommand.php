<?php

namespace NgarakDev\Modularization\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeMigrationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'module:make-migration 
                            {name : The name of the migration}
                            {module : The name of the module}
                            {--create= : The table to be created}
                            {--table= : The table to be migrated}
                            {--path= : The location where the migration file should be created}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new migration file for a specific module';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $name = $this->argument('name');
        $moduleName = $this->argument('module');
        $modulesPath = base_path(config('modularization.modules_path', 'modules'));
        $modulePath = $modulesPath . '/' . $moduleName;

        // Check if module exists
        if (!File::isDirectory($modulePath)) {
            $this->error("Module [{$moduleName}] does not exist.");
            return 1;
        }

        // Make sure the migrations directory exists
        $migrationsPath = $this->option('path')
            ? $modulePath . '/' . $this->option('path')
            : $modulePath . '/Database/Migrations';

        if (!File::isDirectory($migrationsPath)) {
            File::makeDirectory($migrationsPath, 0755, true);
        }

        // Build migration parameters
        $params = [
            'name' => $name,
            '--path' => str_replace(base_path() . '/', '', $migrationsPath),
        ];

        // Add table parameters if provided
        if ($this->option('create')) {
            $params['--create'] = $this->option('create');
        }

        if ($this->option('table')) {
            $params['--table'] = $this->option('table');
        }

        // Generate migration via Laravel's native command
        $this->info("Creating migration for module [{$moduleName}]...");
        $exitCode = $this->call('make:migration', $params);

        if ($exitCode === 0) {
            // Find the generated migration file and show its path
            $datePrefix = date('Y_m_d_His');
            $migrationName = Str::snake($name);

            $pattern = $migrationsPath . "/*_*_{$migrationName}.php";
            $files = glob($pattern);

            if (!empty($files)) {
                $this->info("Migration created successfully: " . basename(end($files)));

                // Show table name if applicable
                if ($this->option('create')) {
                    $this->info("Table to be created: " . $this->option('create'));
                } elseif ($this->option('table')) {
                    $this->info("Table to be migrated: " . $this->option('table'));
                }
            }
        }

        return $exitCode;
    }
}
