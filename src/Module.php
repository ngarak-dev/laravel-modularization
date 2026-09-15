<?php

declare(strict_types=1);

namespace NgarakDev\Modularization;

/**
 * Represents a discovered module and its metadata.
 */
final class Module
{
    public function __construct(
        public readonly string $name,
        public readonly string $path,
        public readonly bool $enabled,
        public readonly string $namespace,
        public readonly string $version,
        public readonly string $description,
        /** @var string[] */
        public readonly array $requires,
    ) {}

    /**
     * Create a Module from a manifest array and filesystem path.
     *
     * @param  array<string, mixed>  $manifest
     */
    public static function fromManifest(string $path, array $manifest, string $defaultNamespace): static
    {
        $name = $manifest['name'] ?? basename($path);

        return new self(
            name: $name,
            path: $path,
            enabled: (bool) ($manifest['enabled'] ?? true),
            namespace: $manifest['namespace'] ?? "{$defaultNamespace}\\{$name}",
            version: $manifest['version'] ?? '1.0.0',
            description: $manifest['description'] ?? '',
            requires: (array) ($manifest['requires'] ?? []),
        );
    }

    /**
     * Create a Module from a directory with no manifest.
     */
    public static function fromDirectory(string $path, string $defaultNamespace, bool $enabled): static
    {
        $name = basename($path);

        return new self(
            name: $name,
            path: $path,
            enabled: $enabled,
            namespace: "{$defaultNamespace}\\{$name}",
            version: '1.0.0',
            description: '',
            requires: [],
        );
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function isDisabled(): bool
    {
        return ! $this->enabled;
    }

    /** Get the path to the module's service provider class file. */
    public function providerPath(): string
    {
        return $this->path.'/Providers/'.$this->name.'ServiceProvider.php';
    }

    /** Get the fully-qualified class name of the module's service provider. */
    public function providerClass(): string
    {
        return $this->namespace.'\\Providers\\'.$this->name.'ServiceProvider';
    }

    /**
     * Return a copy of this module with a different enabled state.
     */
    public function withEnabled(bool $enabled): static
    {
        return new self(
            name: $this->name,
            path: $this->path,
            enabled: $enabled,
            namespace: $this->namespace,
            version: $this->version,
            description: $this->description,
            requires: $this->requires,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'path' => $this->path,
            'enabled' => $this->enabled,
            'namespace' => $this->namespace,
            'version' => $this->version,
            'description' => $this->description,
            'requires' => $this->requires,
        ];
    }
}
