<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Console\Commands;

use Illuminate\Console\Command;
use NgarakDev\Modularization\ModuleManager;

final class ModuleCacheCommand extends Command
{
    protected $signature = 'module:cache';

    protected $description = 'Cache discovered module metadata for production';

    public function handle(ModuleManager $manager): int
    {
        $manager->cache();
        $this->info('Module metadata cached successfully.');

        return self::SUCCESS;
    }
}
