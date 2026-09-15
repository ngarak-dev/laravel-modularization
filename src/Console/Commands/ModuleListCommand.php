<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Console\Commands;

use Illuminate\Console\Command;
use NgarakDev\Modularization\ModularizationService;
use NgarakDev\Modularization\Support\ModuleCache;

class ModuleListCommand extends Command
{
    protected $signature = 'module:list
                            {--format=table : Output format: table, json}';

    protected $description = 'List all modules and their status';

    public function handle(ModularizationService $service, ModuleCache $cache): int
    {
        $modules = $service->getModules();

        if (empty($modules)) {
            $this->warn('No modules found in the configured modules directory.');

            return self::SUCCESS;
        }

        $format = $this->option('format');

        if ($format === 'json') {
            $this->line(json_encode(
                array_map(fn ($m) => $m->toArray(), array_values($modules)),
                JSON_PRETTY_PRINT,
            ));

            return self::SUCCESS;
        }

        $rows = [];

        foreach ($modules as $module) {
            $status = $module->isEnabled()
                ? '<fg=green>Enabled</>'
                : '<fg=red>Disabled</>';

            $requires = empty($module->requires)
                ? '<fg=gray>—</>'
                : implode(', ', $module->requires);

            $rows[] = [
                $module->name,
                $module->version,
                $status,
                $requires,
                $module->description ?: '<fg=gray>—</>',
            ];
        }

        $cachedLabel = $cache->isCached() ? '<fg=yellow> (cached)</>' : '';
        $this->line('');
        $this->line("  Modules{$cachedLabel}");
        $this->line('');

        $this->table(
            ['Name', 'Version', 'Status', 'Requires', 'Description'],
            $rows,
        );

        $enabled = count($service->getEnabledModules());
        $disabled = count($service->getDisabledModules());
        $this->line("  Total: {$enabled} enabled, {$disabled} disabled");
        $this->line('');

        return self::SUCCESS;
    }
}
