<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Console\Commands;

use Illuminate\Console\Command;
use NgarakDev\Modularization\ModuleManager;

final class ModuleClearCommand extends Command
{
    protected $signature = 'module:clear';

    protected $description = 'Clear cached module metadata';

    public function handle(ModuleManager $manager): int
    {
        $manager->clearCache();
        $this->info('Module metadata cache cleared.');

        return self::SUCCESS;
    }
}
