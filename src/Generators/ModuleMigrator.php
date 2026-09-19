<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Generators;

use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Schema;
use NgarakDev\Modularization\ModuleManager;
use NgarakDev\Modularization\ModulePathResolver;
use NgarakDev\Modularization\Support\ModuleName;

final class ModuleMigrator
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly ModulePathResolver $paths,
        private readonly ModuleManager $modules,
        private readonly ConsoleKernel $artisan,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     * @return array{exit: int, action: string, messages: array<int, array{type: string, text: string}>, artisan: string}
     */
    public function migrate(string $name, array $options = []): array
    {
        $module = ModuleName::parse($name);
        $modulePath = $this->paths->path($module->studly());

        if (! $this->files->isDirectory($modulePath)) {
            return $this->result(1, 'migrate', [['type' => 'error', 'text' => "Module [{$module->studly()}] does not exist."]]);
        }

        $migrationsPath = $this->paths->join($modulePath, 'Database/Migrations');
        $files = $this->files->isDirectory($migrationsPath) ? $this->files->glob($migrationsPath.'/*.php') : [];

        if ($files === [] || $files === false) {
            return $this->result(0, 'migrate', [['type' => 'warn', 'text' => "No migrations found for module [{$module->studly()}]."]]);
        }

        $relativePath = str_replace(base_path().DIRECTORY_SEPARATOR, '', $migrationsPath);
        $artisanOptions = ['--path' => $relativePath];

        foreach (['force', 'seed', 'step', 'pretend'] as $flag) {
            if (! empty($options[$flag])) {
                $artisanOptions['--'.$flag] = true;
            }
        }

        if (! empty($options['status'])) {
            return $this->runArtisan('migrate:status', $artisanOptions, $module->studly(), 'Showing migration status');
        }

        if (! empty($options['fresh'])) {
            $migrationFiles = $this->migrationFileNames($relativePath);

            if ($migrationFiles === []) {
                return $this->result(0, 'fresh', [['type' => 'info', 'text' => "No migrations found for module [{$module->studly()}]. Nothing to refresh."]]);
            }

            $dropped = $this->dropModuleTables($this->tablesFromMigrations($migrationFiles));
            $result = $this->runArtisan('migrate', $artisanOptions, $module->studly(), 'Fresh migrations');
            $result['messages'] = array_merge($dropped, $result['messages']);

            return $result;
        }

        $command = 'migrate';
        $action = 'Running migrations';

        if (! empty($options['rollback'])) {
            $command = 'migrate:rollback';
            $action = 'Rolling back migrations';
        } elseif (! empty($options['reset'])) {
            $command = 'migrate:reset';
            $action = 'Resetting all migrations';
        } elseif (! empty($options['refresh'])) {
            $command = 'migrate:refresh';
            $action = 'Refreshing all migrations';
        }

        return $this->runArtisan($command, $artisanOptions, $module->studly(), $action);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{exit: int, success: int, failed: array<int, string>, messages: array<int, array{type: string, text: string}>}
     */
    public function migrateAll(array $options = []): array
    {
        $modulesPath = $this->paths->modulesPath();

        if (! $this->files->isDirectory($modulesPath)) {
            return [
                'exit' => 1,
                'success' => 0,
                'failed' => [],
                'messages' => [['type' => 'error', 'text' => 'Modules directory does not exist.']],
            ];
        }

        $names = array_map('basename', $this->files->directories($modulesPath));

        if ($names === []) {
            return [
                'exit' => 0,
                'success' => 0,
                'failed' => [],
                'messages' => [['type' => 'warn', 'text' => 'No modules found.']],
            ];
        }

        $onlyEnabled = ! empty($options['only-enabled']);
        $failed = [];
        $success = 0;
        $messages = [];

        foreach ($names as $name) {
            $name = (string) $name;

            if ($onlyEnabled && ! $this->modules->isEnabled($name)) {
                $messages[] = ['type' => 'info', 'text' => "Skipping disabled module [{$name}]"];

                continue;
            }

            $result = $this->migrate($name, $options);
            $messages = array_merge($messages, $result['messages']);

            if ($result['artisan'] !== '') {
                $messages[] = ['type' => 'write', 'text' => $result['artisan']];
            }

            if ($result['exit'] !== 0) {
                $failed[] = $name;
            } else {
                $success++;
            }
        }

        return [
            'exit' => $failed === [] ? 0 : 1,
            'success' => $success,
            'failed' => $failed,
            'messages' => $messages,
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{exit: int, action: string, messages: array<int, array{type: string, text: string}>, artisan: string}
     */
    private function runArtisan(string $command, array $options, string $module, string $action): array
    {
        $messages = [['type' => 'info', 'text' => "{$action} for module [{$module}]..."]];
        $result = $this->artisan->call($command, $options);
        $artisanOutput = $this->artisan->output();

        if ($result === 0) {
            $messages[] = ['type' => 'info', 'text' => "{$action} for module [{$module}] completed successfully."];
        } else {
            $messages[] = ['type' => 'error', 'text' => "{$action} for module [{$module}] failed."];
        }

        return $this->result($result, $action, $messages, $artisanOutput);
    }

    /**
     * @param  array<int, array{type: string, text: string}>  $messages
     * @return array{exit: int, action: string, messages: array<int, array{type: string, text: string}>, artisan: string}
     */
    private function result(int $exit, string $action, array $messages, string $artisan = ''): array
    {
        return [
            'exit' => $exit,
            'action' => $action,
            'messages' => $messages,
            'artisan' => $artisan,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function migrationFileNames(string $relativePath): array
    {
        $files = $this->files->glob(base_path($relativePath).'/*.php');
        $migrations = [];

        foreach ($files as $file) {
            $migrations[] = pathinfo($file, PATHINFO_FILENAME);
        }

        return $migrations;
    }

    /**
     * @param  array<int, string>  $migrations
     * @return array<int, string>
     */
    private function tablesFromMigrations(array $migrations): array
    {
        $tables = [];

        foreach ($migrations as $migration) {
            if (preg_match('/create_(\w+)_table/', $migration, $matches) === 1) {
                $tables[] = $matches[1];
            }
        }

        return $tables;
    }

    /**
     * @param  array<int, string>  $tables
     * @return array<int, array{type: string, text: string}>
     */
    private function dropModuleTables(array $tables): array
    {
        $messages = [];
        Schema::disableForeignKeyConstraints();

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                $messages[] = ['type' => 'info', 'text' => "Dropping table: {$table}"];
                Schema::dropIfExists($table);
            }
        }

        Schema::enableForeignKeyConstraints();

        return $messages;
    }
}
