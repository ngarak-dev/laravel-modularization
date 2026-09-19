<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Console\Commands;

use Illuminate\Console\Command;
use NgarakDev\Modularization\Console\Commands\Concerns\InteractsWithModules;
use NgarakDev\Modularization\Exceptions\InvalidModuleException;
use NgarakDev\Modularization\Exceptions\ModuleNotFoundException;
use NgarakDev\Modularization\Generators\ModuleExporter;
use NgarakDev\Modularization\Support\ModuleName;
use RuntimeException;

class ModuleExportCommand extends Command
{
    use InteractsWithModules;

    protected $signature = 'module:export
                            {module : The name of the module to export}
                            {--output= : The output directory (default: build)}
                            {--vendor= : The vendor name for the package}
                            {--description= : Package description}
                            {--author= : Package author}
                            {--email= : Author email}
                            {--license=MIT : Package license}
                            {--force : Overwrite the export directory}';

    protected $description = 'Export a module as a standalone package';

    public function handle(ModuleExporter $exporter): int
    {
        try {
            $module = ModuleName::parse((string) $this->argument('module'))->studly();
        } catch (InvalidModuleException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $vendor = $this->option('vendor');
        if (! is_string($vendor) || $vendor === '') {
            $vendor = (string) $this->ask('Please provide a vendor name for the package', 'NgarakDev');
        }

        $outputDir = (string) ($this->option('output') ?: 'build');
        $exportPath = base_path($outputDir.'/'.strtolower(str_replace(' ', '-', $module)));

        if (is_dir($exportPath) && ! $this->option('force')) {
            $this->warn("Export directory already exists: {$exportPath}");
            if (! $this->confirm('Do you want to overwrite it?', false)) {
                $this->info('Export aborted.');

                return self::SUCCESS;
            }
        }

        try {
            $path = $exporter->export($module, [
                'output' => $outputDir,
                'vendor' => $vendor,
                'description' => $this->option('description') ?: "Laravel package for {$module}",
                'author' => $this->option('author') ?: 'Ngara K',
                'email' => $this->option('email') ?: 'ngarakiringo@gmail.com',
                'license' => $this->option('license') ?: 'MIT',
                'force' => true,
            ]);
        } catch (ModuleNotFoundException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Exporting module to {$path}...");
        $this->info("Module [{$module}] exported successfully to: {$path}");

        return self::SUCCESS;
    }
}
