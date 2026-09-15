<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Console\Commands;

use Illuminate\Console\Command;
use NgarakDev\Modularization\Console\Commands\Concerns\InteractsWithModules;

class ModuleListCommand extends Command
{
    use InteractsWithModules;

    protected $signature = 'module:list {--json : Output module metadata as JSON}';

    protected $description = 'List discovered modules and their status';

    public function handle(): int
    {
        $modules = $this->modules()->all();
        $cached = $this->modules()->isCached();

        if ($this->option('json')) {
            $this->line(json_encode(array_map(
                static fn ($module) => $module->toArray(),
                $modules
            ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        if ($modules === []) {
            $this->warn('No modules found.');
            $this->line('Create one with `php artisan module:make Billing`.');

            return self::SUCCESS;
        }

        $rows = [];

        foreach ($modules as $module) {
            $missing = $this->modules()->hasModule($module->name)
                ? $this->modules()->missingDependencies($module->name)
                : [];

            $status = $module->status();

            if ($missing !== [] && $module->enabled) {
                $status = 'missing deps';
            }

            $rows[] = [
                $module->name,
                $status,
                $module->version,
                $module->requires === [] ? '-' : implode(', ', $module->requires),
                $missing === [] ? '-' : implode(', ', $missing),
                $cached ? 'yes' : 'no',
                $module->description,
            ];
        }

        $this->table(
            ['Module', 'Status', 'Version', 'Requires', 'Missing', 'Cached', 'Description'],
            $rows
        );

        $this->line(sprintf(
            '%d module(s) discovered. Cache: %s.',
            count($modules),
            $cached ? 'active' : 'not built'
        ));

        return self::SUCCESS;
    }
}
