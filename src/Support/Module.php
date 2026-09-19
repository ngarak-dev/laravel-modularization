<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Support;

use NgarakDev\Modularization\Contracts\ModuleInterface;

/**
 * Represents a single module in the application.
 */
final class Module implements ModuleInterface
{
    /**
     * @param array<string, mixed> $manifest
     */
    public function __construct(
        private readonly string $name,
        private readonly string $path,
        private readonly string $namespace,
        private bool $enabled = true,
        private readonly array $manifest = [],
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function getProviderClass(): ?string
    {
        $providerClass = $this->manifest['provider'] ?? null;

        if ($providerClass !== null) {
            return $providerClass;
        }

        $defaultProvider = $this->namespace . '\\Providers\\' . $this->name . 'ServiceProvider';
        return class_exists($defaultProvider) ? $defaultProvider : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getManifest(): array
    {
        return $this->manifest;
    }

    /**
     * @return array<string>
     */
    public function getDependencies(): array
    {
        return $this->manifest['requires'] ?? $this->manifest['dependencies'] ?? [];
    }

    public function getDescription(): string
    {
        return $this->manifest['description'] ?? '';
    }

    public function getVersion(): string
    {
        return $this->manifest['version'] ?? '1.0.0';
    }

    /**
     * Get a value from the manifest.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->manifest[$key] ?? $default;
    }

    /**
     * Get the path to a specific location within the module.
     */
    public function path(string $subPath = ''): string
    {
        if ($subPath === '') {
            return $this->path;
        }

        return $this->path . DIRECTORY_SEPARATOR . ltrim($subPath, DIRECTORY_SEPARATOR);
    }

    /**
     * Convert module to array for serialization/caching.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'path' => $this->path,
            'namespace' => $this->namespace,
            'enabled' => $this->enabled,
            'manifest' => $this->manifest,
        ];
    }

    /**
     * Create a Module from an array.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            path: $data['path'],
            namespace: $data['namespace'],
            enabled: $data['enabled'] ?? true,
            manifest: $data['manifest'] ?? [],
        );
    }
}
