<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Console\Commands;

use Illuminate\Console\Command;
use NgarakDev\Modularization\ModularizationService;
use NgarakDev\Modularization\Support\ModuleCache;

class ModuleCacheCommand extends Command
{
    protected $signature = 'module:cache';

    protected $description = 'Cache the module manifest for faster boot times';

    public function handle(ModularizationService $service, ModuleCache $cache): int
    {
        $this->info('Caching module manifest...');

        // Force fresh discovery (bypass any existing cache)
        $service->refresh();

        $modules = $service->getModules();

        $cache->write($modules);

        $count = count($modules);
        $this->info("Module manifest cached successfully ({$count} module(s) discovered).");
        $this->line("Cache location: <comment>{$cache->getCachePath()}</comment>");

        return self::SUCCESS;
    }
}
