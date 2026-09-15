<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Console\Commands;

use Illuminate\Console\Command;
use NgarakDev\Modularization\Contracts\ModuleInterface;
use NgarakDev\Modularization\ModuleManager;

/**
 * Command to list all modules and their status.
 */
class ModuleListCommand extends Command
{
    protected $signature = 'module:list
                            {--enabled : Only show enabled modules}
                            {--disabled : Only show disabled modules}
                            {--json : Output as JSON}';

    protected $description = 'List all modules and their status';

    public function __construct(
        private readonly ModuleManager $manager,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $modules = $this->getFilteredModules();

        if ($this->option('json')) {
            $this->outputJson($modules);
            return self::SUCCESS;
        }

        $this->outputTable($modules);
        return self::SUCCESS;
    }

    /**
     * @return \Illuminate\Support\Collection<string, ModuleInterface>
     */
    private function getFilteredModules(): \Illuminate\Support\Collection
    {
        if ($this->option('enabled')) {
            return $this->manager->enabled();
        }

        if ($this->option('disabled')) {
            return $this->manager->disabled();
        }

        return $this->manager->all();
    }

    /**
     * @param \Illuminate\Support\Collection<string, ModuleInterface> $modules
     */
    private function outputTable(\Illuminate\Support\Collection $modules): void
    {
        if ($modules->isEmpty()) {
            $this->info('No modules found.');
            return;
        }

        $isCached = $this->manager->isCached();
        $summary = $this->manager->getStatusSummary();

        $this->newLine();
        $this->info("Modules ({$summary['total']} total, {$summary['enabled']} enabled, {$summary['disabled']} disabled)");

        if ($isCached) {
            $this->comment('(Using cached module data)');
        }

        $this->newLine();

        $rows = $modules->map(function (ModuleInterface $module) {
            $status = $module->isEnabled()
                ? '<fg=green>Enabled</>'
                : '<fg=red>Disabled</>';

            $dependencies = implode(', ', $module->getDependencies()) ?: '-';

            return [
                $module->getName(),
                $status,
                $module->getVersion(),
                $module->getDescription() ?: '-',
                $dependencies,
            ];
        })->values()->all();

        $this->table(
            ['Name', 'Status', 'Version', 'Description', 'Dependencies'],
            $rows
        );
    }

    /**
     * @param \Illuminate\Support\Collection<string, ModuleInterface> $modules
     */
    private function outputJson(\Illuminate\Support\Collection $modules): void
    {
        $data = $modules->map(function (ModuleInterface $module) {
            return [
                'name' => $module->getName(),
                'path' => $module->getPath(),
                'namespace' => $module->getNamespace(),
                'enabled' => $module->isEnabled(),
                'version' => $module->getVersion(),
                'description' => $module->getDescription(),
                'dependencies' => $module->getDependencies(),
            ];
        })->values()->all();

        $this->line(json_encode([
            'cached' => $this->manager->isCached(),
            'modules' => $data,
        ], JSON_PRETTY_PRINT));
    }
}
