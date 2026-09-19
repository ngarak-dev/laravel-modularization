<?php

declare(strict_types=1);

namespace NgarakDev\Modularization;

use NgarakDev\Modularization\Contracts\ModuleInterface;

/**
 * Immutable snapshot of a discovered module and its metadata.
 */
final class Module implements ModuleInterface
{
    /**
     * @param  array<int, string>  $requires
     * @param  array<string, string|null>  $routes
     * @param  array<int, array{alias: string, class: string}>  $livewire
     * @param  array<int, string>  $configFiles
     */
    public function __construct(
        public readonly string $name,
        public readonly string $path,
        public readonly string $namespace,
        public readonly ?string $provider = null,
        public readonly string $version = '1.0.0',
        public readonly string $description = '',
        public readonly bool $enabled = true,
        public readonly array $requires = [],
        public readonly bool $cached = false,
        public readonly bool $valid = true,
        public readonly ?string $invalidReason = null,
        public readonly array $routes = [],
        public readonly ?string $views = null,
        public readonly ?string $translations = null,
        public readonly ?string $migrations = null,
        public readonly ?string $assets = null,
        public readonly array $livewire = [],
        public readonly array $configFiles = [],
        public readonly ?string $manifestPath = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) ($data['name'] ?? ''),
            path: (string) ($data['path'] ?? ''),
            namespace: (string) ($data['namespace'] ?? ''),
            provider: isset($data['provider']) ? (string) $data['provider'] : null,
            version: (string) ($data['version'] ?? '1.0.0'),
            description: (string) ($data['description'] ?? ''),
            enabled: (bool) ($data['enabled'] ?? true),
            requires: array_values(array_map('strval', $data['requires'] ?? [])),
            cached: (bool) ($data['cached'] ?? false),
            valid: (bool) ($data['valid'] ?? true),
            invalidReason: isset($data['invalid_reason']) ? (string) $data['invalid_reason'] : null,
            routes: is_array($data['routes'] ?? null) ? $data['routes'] : [],
            views: isset($data['views']) ? (string) $data['views'] : null,
            translations: isset($data['translations']) ? (string) $data['translations'] : null,
            migrations: isset($data['migrations']) ? (string) $data['migrations'] : null,
            assets: isset($data['assets']) ? (string) $data['assets'] : null,
            livewire: is_array($data['livewire'] ?? null) ? $data['livewire'] : [],
            configFiles: array_values(array_map('strval', $data['config_files'] ?? [])),
            manifestPath: isset($data['manifest_path']) ? (string) $data['manifest_path'] : null,
        );
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    public static function fromManifest(string $directory, array $manifest, string $defaultNamespace): self
    {
        $name = (string) ($manifest['name'] ?? basename($directory));

        return self::fromArray([
            'name' => $name,
            'path' => $directory,
            'namespace' => $manifest['namespace'] ?? $defaultNamespace.'\\'.$name,
            'provider' => $manifest['provider'] ?? null,
            'version' => $manifest['version'] ?? '1.0.0',
            'description' => $manifest['description'] ?? '',
            'enabled' => $manifest['enabled'] ?? true,
            'requires' => $manifest['requires'] ?? [],
            'manifest_path' => $directory.'/module.json',
        ]);
    }

    public static function fromDirectory(string $directory, string $defaultNamespace, bool $enabled = true): self
    {
        $name = basename($directory);

        return self::fromArray([
            'name' => $name,
            'path' => $directory,
            'namespace' => $defaultNamespace.'\\'.$name,
            'enabled' => $enabled,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'path' => $this->path,
            'namespace' => $this->namespace,
            'provider' => $this->provider,
            'version' => $this->version,
            'description' => $this->description,
            'enabled' => $this->enabled,
            'requires' => $this->requires,
            'cached' => $this->cached,
            'valid' => $this->valid,
            'invalid_reason' => $this->invalidReason,
            'routes' => $this->routes,
            'views' => $this->views,
            'translations' => $this->translations,
            'migrations' => $this->migrations,
            'assets' => $this->assets,
            'livewire' => $this->livewire,
            'config_files' => $this->configFiles,
            'manifest_path' => $this->manifestPath,
        ];
    }

    public function viewNamespace(): string
    {
        return strtolower($this->name);
    }

    public function withEnabled(bool $enabled): self
    {
        return new self(
            name: $this->name,
            path: $this->path,
            namespace: $this->namespace,
            provider: $this->provider,
            version: $this->version,
            description: $this->description,
            enabled: $enabled,
            requires: $this->requires,
            cached: $this->cached,
            valid: $this->valid,
            invalidReason: $this->invalidReason,
            routes: $this->routes,
            views: $this->views,
            translations: $this->translations,
            migrations: $this->migrations,
            assets: $this->assets,
            livewire: $this->livewire,
            configFiles: $this->configFiles,
            manifestPath: $this->manifestPath,
        );
    }

    public function markCached(): self
    {
        return new self(
            name: $this->name,
            path: $this->path,
            namespace: $this->namespace,
            provider: $this->provider,
            version: $this->version,
            description: $this->description,
            enabled: $this->enabled,
            requires: $this->requires,
            cached: true,
            valid: $this->valid,
            invalidReason: $this->invalidReason,
            routes: $this->routes,
            views: $this->views,
            translations: $this->translations,
            migrations: $this->migrations,
            assets: $this->assets,
            livewire: $this->livewire,
            configFiles: $this->configFiles,
            manifestPath: $this->manifestPath,
        );
    }

    public function status(): string
    {
        if (! $this->valid) {
            return 'invalid';
        }

        return $this->enabled ? 'enabled' : 'disabled';
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

    public function getProviderClass(): ?string
    {
        return $this->provider;
    }

    /**
     * @return array<string, mixed>
     */
    public function getManifest(): array
    {
        return $this->toArray();
    }

    /**
     * @return array<int, string>
     */
    public function getDependencies(): array
    {
        return $this->requires;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getVersion(): string
    {
        return $this->version;
    }
}
