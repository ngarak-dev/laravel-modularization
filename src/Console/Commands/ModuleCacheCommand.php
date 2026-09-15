<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Console\Commands;

use Illuminate\Console\Command;
use NgarakDev\Modularization\Console\Commands\Concerns\InteractsWithModules;

class ModuleCacheCommand extends Command
{
    use InteractsWithModules;

    protected $signature = 'module:cache';

    protected $description = 'Cache discovered module metadata for faster boots';

    public function handle(): int
    {
        $path = $this->modules()->cache();
        $count = count($this->modules()->all());

        $this->info("Module metadata cached for {$count} module(s).");
        $this->line($path);

        return self::SUCCESS;
    }
}
