<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Console\Commands;

use Illuminate\Console\Command;
use NgarakDev\Modularization\ModuleManager;

final class ModuleListCommand extends Command
{
    protected $signature = 'module:list {--cached : Read the discovery cache}';

    protected $description = 'List discovered modules and their status';

    public function handle(ModuleManager $manager): int
    {
        $rows = [];
        foreach ($manager->all((bool) $this->option('cached')) as $module) {
            $rows[] = [
                $module['name'],
                $module['enabled'] ? 'enabled' : 'disabled',
                implode(', ', $module['requires']),
                $module['path'],
            ];
        }

        $this->table(['Module', 'Status', 'Requires', 'Path'], $rows);

        return self::SUCCESS;
    }
}
