<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Console\Commands;

use Illuminate\Console\Command;
use NgarakDev\Modularization\ModuleManager;

/**
 * Command to clear the module cache.
 */
class ModuleClearCommand extends Command
{
    protected $signature = 'module:clear';

    protected $description = 'Clear the module metadata cache';

    public function __construct(
        private readonly ModuleManager $manager,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (!$this->manager->isCached()) {
            $this->info('Module cache is already cleared.');
            return self::SUCCESS;
        }

        $this->manager->clearCache();
        $this->info('Module cache cleared successfully.');

        return self::SUCCESS;
    }
}
