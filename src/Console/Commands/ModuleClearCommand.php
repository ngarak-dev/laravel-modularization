<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Console\Commands;

use Illuminate\Console\Command;
use NgarakDev\Modularization\Console\Commands\Concerns\InteractsWithModules;

class ModuleClearCommand extends Command
{
    use InteractsWithModules;

    protected $signature = 'module:clear';

    protected $description = 'Clear the cached module metadata';

    public function handle(): int
    {
        $this->modules()->clearCache();
        $this->info('Module cache cleared.');

        return self::SUCCESS;
    }
}
