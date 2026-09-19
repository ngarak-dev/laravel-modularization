<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Console\Commands;

use Illuminate\Console\Command;
use NgarakDev\Modularization\Console\Commands\Concerns\InteractsWithModules;
use NgarakDev\Modularization\Exceptions\InvalidModuleException;
use NgarakDev\Modularization\Generators\ModuleMigrator;

class MigrateModuleCommand extends Command
{
    use InteractsWithModules;

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

    protected $description = 'Run migrations for a specific module';

    public function handle(ModuleMigrator $migrator): int
    {
        try {
            $result = $migrator->migrate((string) $this->argument('name'), [
                'force' => (bool) $this->option('force'),
                'seed' => (bool) $this->option('seed'),
                'step' => (bool) $this->option('step'),
                'pretend' => (bool) $this->option('pretend'),
                'fresh' => (bool) $this->option('fresh'),
                'rollback' => (bool) $this->option('rollback'),
                'status' => (bool) $this->option('status'),
                'reset' => (bool) $this->option('reset'),
                'refresh' => (bool) $this->option('refresh'),
            ]);
        } catch (InvalidModuleException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->writeMessages($result['messages']);

        if ($result['artisan'] !== '') {
            $this->output->write($result['artisan']);
        }

        return $result['exit'];
    }

    /**
     * @param  array<int, array{type: string, text: string}>  $messages
     */
    private function writeMessages(array $messages): void
    {
        foreach ($messages as $message) {
            match ($message['type']) {
                'error' => $this->error($message['text']),
                'warn' => $this->warn($message['text']),
                'write' => $this->output->write($message['text']),
                default => $this->info($message['text']),
            };
        }
    }
}
