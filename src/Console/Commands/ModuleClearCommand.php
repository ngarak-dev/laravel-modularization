<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Console\Commands;

use Illuminate\Console\Command;
use NgarakDev\Modularization\Support\ModuleCache;

class ModuleClearCommand extends Command
{
    protected $signature = 'module:clear';

    protected $description = 'Remove the cached module manifest';

    public function handle(ModuleCache $cache): int
    {
        if ($cache->clear()) {
            $this->info('Module cache cleared successfully.');
        } else {
            $this->warn('No module cache file found — nothing to clear.');
        }

        return self::SUCCESS;
    }
}
