<?php

declare(strict_types=1);

namespace NgarakDev\Modularization;

use Illuminate\Filesystem\Filesystem;

/**
 * Resolves stub files from published application stubs, then the package.
 */
final class StubLocator
{
    public function __construct(
        private readonly Filesystem $files,
    ) {}

    public function path(string $name): ?string
    {
        $name = str_replace('.stub', '', $name);
        $candidates = [
            base_path('stubs/vendor/modularization/'.$name.'.stub'),
            dirname(__DIR__).'/stubs/'.$name.'.stub',
        ];

        foreach ($candidates as $candidate) {
            if ($this->files->isFile($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param  array<string, string>  $replacements
     */
    public function render(string $name, array $replacements = []): string
    {
        $path = $this->path($name);

        if ($path === null) {
            return '';
        }

        $content = $this->files->get($path);

        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }

    public function packageStubsPath(): string
    {
        return dirname(__DIR__).'/stubs';
    }

    public function publishedStubsPath(): string
    {
        return base_path('stubs/vendor/modularization');
    }
}
