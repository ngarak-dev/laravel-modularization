<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Console\Commands\Concerns;

use NgarakDev\Modularization\Exceptions\InvalidModuleException;
use NgarakDev\Modularization\Exceptions\ModuleNotFoundException;
use NgarakDev\Modularization\Module;
use NgarakDev\Modularization\ModuleManager;
use NgarakDev\Modularization\Support\ModuleName;

trait InteractsWithModules
{
    protected function modules(): ModuleManager
    {
        return $this->laravel->make(ModuleManager::class);
    }

    protected function parseModuleName(string $name): string
    {
        return ModuleName::parse($name)->studly();
    }

    protected function moduleOrFail(string $name): Module
    {
        $studly = $this->parseModuleName($name);

        if (! $this->modules()->hasModule($studly)) {
            throw ModuleNotFoundException::make($studly, $this->modules()->path());
        }

        return $this->modules()->get($studly);
    }

    protected function failOnInvalidName(\Throwable $exception): int
    {
        if ($exception instanceof InvalidModuleException || $exception instanceof ModuleNotFoundException) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        throw $exception;
    }
}
