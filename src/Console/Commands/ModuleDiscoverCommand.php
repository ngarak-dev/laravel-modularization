<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Console\Commands;

use Illuminate\Console\Command;
use NgarakDev\Modularization\Console\Commands\Concerns\InteractsWithModules;

class ModuleDiscoverCommand extends Command
{
    use InteractsWithModules;

    protected $signature = 'module:discover {--cache : Write the discovered metadata to the module cache}';

    protected $description = 'Rescan the modules directory and show what was found';

    public function handle(): int
    {
        $modules = $this->modules()->discover(false);

        if ($this->option('cache')) {
            $this->modules()->cache();
        }

        $this->info(count($modules).' module(s) discovered.');

        foreach ($modules as $module) {
            $this->line(sprintf(
                '  [%s] %s%s',
                $module->status(),
                $module->name,
                $module->requires === [] ? '' : ' requires: '.implode(', ', $module->requires)
            ));
        }

        return self::SUCCESS;
    }
}
