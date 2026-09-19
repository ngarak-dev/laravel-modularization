<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Console\Commands;

use Illuminate\Console\Command;
use NgarakDev\Modularization\Console\Commands\Concerns\InteractsWithModules;
use NgarakDev\Modularization\Exceptions\InvalidModuleException;
use NgarakDev\Modularization\Exceptions\ModuleNotFoundException;
use NgarakDev\Modularization\Generators\ModuleGenerator;
use NgarakDev\Modularization\Support\ModuleName;

class MakeModuleTranslationCommand extends Command
{
    use InteractsWithModules;

    protected $signature = 'module:make-translation
                            {module : The name of the module}
                            {--languages=* : Languages to generate (default: en,es,fr,de)}
                            {--force : Overwrite existing translation files}';

    protected $description = 'Create translation files for a module';

    public function handle(ModuleGenerator $generator): int
    {
        try {
            $module = ModuleName::parse((string) $this->argument('module'))->studly();
            $languages = $this->option('languages');
            $languages = is_array($languages) ? array_values(array_map('strval', $languages)) : [];
            $created = $generator->generateTranslations($module, $languages, (bool) $this->option('force'));
        } catch (InvalidModuleException|ModuleNotFoundException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($created === []) {
            $this->warn("Skipping translation files for module [{$module}] (already exists)");
        }

        $this->info("Translation files created successfully for module [{$module}]");

        return self::SUCCESS;
    }
}
