<?php

declare(strict_types=1);

namespace NgarakDev\Modularization;

use Illuminate\Filesystem\Filesystem;
use NgarakDev\Modularization\Support\ModulePathResolver;

final class ModuleStatusManager
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly ModulePathResolver $paths,
    ) {
    }

    public function isEnabled(string $name): bool
    {
        return ! $this->files->exists($this->paths->child($name, '.disabled'));
    }

    public function enable(string $name): bool
    {
        $path = $this->paths->module($name);
        if (! $this->files->isDirectory($path)) {
            return false;
        }

        return ! $this->files->delete($this->paths->child($name, '.disabled'))
            || ! $this->files->exists($this->paths->child($name, '.disabled'));
    }

    public function disable(string $name): bool
    {
        $path = $this->paths->module($name);
        if (! $this->files->isDirectory($path)) {
            return false;
        }

        $this->files->put($this->paths->child($name, '.disabled'), json_encode([
            'disabled_at' => now()->toIso8601String(),
        ], JSON_THROW_ON_ERROR));

        return true;
    }
}
