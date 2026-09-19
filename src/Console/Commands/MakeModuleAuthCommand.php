<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Console\Commands;

use Illuminate\Console\Command;
use NgarakDev\Modularization\Console\Commands\Concerns\InteractsWithModules;
use NgarakDev\Modularization\Exceptions\InvalidModuleException;
use NgarakDev\Modularization\Generators\AuthModuleGenerator;
use NgarakDev\Modularization\Support\ModuleName;

class MakeModuleAuthCommand extends Command
{
    use InteractsWithModules;

    protected $signature = 'module:make-auth
                            {name? : The name of the authentication module (defaults to "Auth")}
                            {--force : Force overwrite existing files}';

    protected $description = 'Create a standalone authentication module with Blade templates';

    public function handle(AuthModuleGenerator $generator): int
    {
        try {
            $name = ModuleName::parse((string) ($this->argument('name') ?: 'Auth'))->studly();
        } catch (InvalidModuleException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $force = (bool) $this->option('force');

        if ($generator->exists($name) && ! $force) {
            if (! $this->confirm("Module [{$name}] already exists. Do you want to continue?")) {
                $this->info('Operation cancelled.');

                return self::FAILURE;
            }
        }

        $generator->generate($name, $force);
        $this->info("Authentication module [{$name}] created successfully");
        $this->info('You can now access your authentication system at: /auth/login');

        return self::SUCCESS;
    }
}
