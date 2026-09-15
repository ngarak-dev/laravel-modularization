<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Console\Commands;

use Illuminate\Console\Command;
use NgarakDev\Modularization\ModuleManager;

/**
 * Command to cache module metadata for production.
 */
class ModuleCacheCommand extends Command
{
    protected $signature = 'module:cache';

    protected $description = 'Cache module metadata for faster boot performance';

    public function __construct(
        private readonly ModuleManager $manager,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->components->task('Discovering modules', function () {
            $this->manager->refresh();
        });

        $this->components->task('Caching module data', function () {
            $this->manager->cache();
        });

        $summary = $this->manager->getStatusSummary();

        $this->newLine();
        $this->info("Cached {$summary['total']} modules ({$summary['enabled']} enabled, {$summary['disabled']} disabled).");

        return self::SUCCESS;
    }
}
