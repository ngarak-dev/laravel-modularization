<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Console\Commands;

use Illuminate\Console\Command;
use NgarakDev\Modularization\Generators\ModuleMigrator;

class MigrateModulesCommand extends Command
{
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

    protected $description = 'Run migrations for all modules';

    public function handle(ModuleMigrator $migrator): int
    {
        $options = [
            'force' => (bool) $this->option('force'),
            'seed' => (bool) $this->option('seed'),
            'step' => (bool) $this->option('step'),
            'pretend' => (bool) $this->option('pretend'),
            'only-enabled' => (bool) $this->option('only-enabled'),
            'fresh' => (bool) $this->option('fresh'),
            'rollback' => (bool) $this->option('rollback'),
            'status' => (bool) $this->option('status'),
            'reset' => (bool) $this->option('reset'),
            'refresh' => (bool) $this->option('refresh'),
        ];

        $action = 'migration';
        if ($options['fresh']) {
            $action = 'fresh migration';
        } elseif ($options['rollback']) {
            $action = 'rollback';
        } elseif ($options['status']) {
            $action = 'status check';
        } elseif ($options['reset']) {
            $action = 'reset';
        } elseif ($options['refresh']) {
            $action = 'refresh';
        }

        $this->info('Starting '.$action.' process for all modules'.($options['only-enabled'] ? ' (enabled only)' : '').'...');

        $result = $migrator->migrateAll($options);

        foreach ($result['messages'] as $message) {
            match ($message['type']) {
                'error' => $this->error($message['text']),
                'warn' => $this->warn($message['text']),
                'write' => $this->output->write($message['text']),
                default => $this->info($message['text']),
            };
        }

        if ($result['messages'] !== [] && ($result['success'] > 0 || $result['failed'] !== [])) {
            $this->newLine();
            $this->info('Migration Summary:');
            $this->info('- '.$result['success'].' modules processed successfully');

            if ($result['failed'] !== []) {
                $this->error('- '.count($result['failed']).' modules failed: '.implode(', ', $result['failed']));
            }
        }

        return $result['exit'];
    }
}
