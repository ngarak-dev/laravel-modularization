<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Support;

use Illuminate\Filesystem\Filesystem;
use NgarakDev\Modularization\Exceptions\ModuleGenerationException;

/**
 * Renders stub files with placeholder replacements.
 */
final class StubRenderer
{
    private string $customStubsPath;
    private string $packageStubsPath;

    public function __construct(
        private readonly Filesystem $files,
    ) {
        $this->packageStubsPath = __DIR__ . '/../../stubs';
        $this->customStubsPath = base_path('stubs/vendor/modularization');
    }

    /**
     * Render a stub with the given replacements.
     *
     * @param string $stubName The name of the stub file (without .stub extension)
     * @param array<string, string> $replacements Key-value pairs for placeholder replacement
     * @return string The rendered content
     * @throws ModuleGenerationException
     */
    public function render(string $stubName, array $replacements = []): string
    {
        $content = $this->getStubContent($stubName);

        foreach ($replacements as $placeholder => $value) {
            $content = str_replace($placeholder, $value, $content);
        }

        return $content;
    }

    /**
     * Get the raw content of a stub file.
     *
     * @throws ModuleGenerationException
     */
    public function getStubContent(string $stubName): string
    {
        $stubFile = $stubName . '.stub';

        $customPath = $this->customStubsPath . '/' . $stubFile;
        if ($this->files->exists($customPath)) {
            return $this->files->get($customPath);
        }

        $packagePath = $this->packageStubsPath . '/' . $stubFile;
        if ($this->files->exists($packagePath)) {
            return $this->files->get($packagePath);
        }

        throw ModuleGenerationException::stubNotFound($stubName);
    }

    /**
     * Check if a stub exists.
     */
    public function hasStub(string $stubName): bool
    {
        $stubFile = $stubName . '.stub';

        return $this->files->exists($this->customStubsPath . '/' . $stubFile)
            || $this->files->exists($this->packageStubsPath . '/' . $stubFile);
    }

    /**
     * Get common replacements for a module.
     *
     * @return array<string, string>
     */
    public function getModuleReplacements(string $moduleName, string $namespace): array
    {
        return [
            '{{namespace}}' => $namespace,
            '{{moduleName}}' => $moduleName,
            '{{moduleNameLower}}' => strtolower($moduleName),
            '{{moduleNameKebab}}' => $this->toKebabCase($moduleName),
            '{{moduleNameSnake}}' => $this->toSnakeCase($moduleName),
            '{{moduleNamePlural}}' => $this->pluralize($moduleName),
            '{{moduleNamePluralLower}}' => strtolower($this->pluralize($moduleName)),
            '{{table}}' => $this->toSnakeCase($this->pluralize($moduleName)),
        ];
    }

    /**
     * Convert a string to kebab-case.
     */
    private function toKebabCase(string $string): string
    {
        return strtolower((string) preg_replace('/([a-z])([A-Z])/', '$1-$2', $string));
    }

    /**
     * Convert a string to snake_case.
     */
    private function toSnakeCase(string $string): string
    {
        return strtolower((string) preg_replace('/([a-z])([A-Z])/', '$1_$2', $string));
    }

    /**
     * Simple pluralization.
     */
    private function pluralize(string $string): string
    {
        $lastChar = substr($string, -1);
        $lastTwoChars = substr($string, -2);

        if (in_array($lastTwoChars, ['ch', 'sh', 'ss'], true)) {
            return $string . 'es';
        }

        if ($lastChar === 's' || $lastChar === 'x' || $lastChar === 'z') {
            return $string . 'es';
        }

        if ($lastChar === 'y' && !in_array($lastTwoChars[0] ?? '', ['a', 'e', 'i', 'o', 'u'], true)) {
            return substr($string, 0, -1) . 'ies';
        }

        return $string . 's';
    }
}
