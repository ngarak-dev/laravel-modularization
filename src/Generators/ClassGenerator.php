<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Generators;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use NgarakDev\Modularization\Exceptions\ModuleGenerationException;
use NgarakDev\Modularization\Exceptions\ModuleNotFoundException;
use NgarakDev\Modularization\ModuleConfiguration;
use NgarakDev\Modularization\ModulePathResolver;
use NgarakDev\Modularization\StubLocator;
use NgarakDev\Modularization\Support\ModuleName;

final class ClassGenerator
{
    /**
     * @var array<string, array{stub: string, path: string, suffix?: string, namespace?: string}>
     */
    private const TYPES = [
        'controller' => ['stub' => 'web-controller', 'path' => 'Http/Controllers', 'suffix' => 'Controller'],
        'api-controller' => ['stub' => 'api-controller', 'path' => 'Http/Controllers/API', 'suffix' => 'Controller'],
        'model' => ['stub' => 'model', 'path' => 'Models'],
        'repository' => ['stub' => 'repository', 'path' => 'Repositories', 'suffix' => 'Repository'],
        'repository-interface' => ['stub' => 'repository-interface', 'path' => 'Repositories/Interfaces', 'suffix' => 'RepositoryInterface'],
        'service' => ['stub' => 'service', 'path' => 'Services', 'suffix' => 'Service'],
        'service-interface' => ['stub' => 'service-interface', 'path' => 'Services/Interfaces', 'suffix' => 'ServiceInterface'],
        'request' => ['stub' => 'request', 'path' => 'Http/Requests', 'suffix' => 'Request'],
        'resource' => ['stub' => 'api-resource', 'path' => 'Http/Resources', 'suffix' => 'Resource'],
        'seeder' => ['stub' => 'seeder', 'path' => 'Database/Seeders', 'suffix' => 'Seeder'],
        'factory' => ['stub' => 'factory', 'path' => 'Database/Factories', 'suffix' => 'Factory'],
        'policy' => ['stub' => 'policy', 'path' => 'Policies', 'suffix' => 'Policy'],
        'event' => ['stub' => 'event', 'path' => 'Events', 'suffix' => 'Event'],
        'listener' => ['stub' => 'listener', 'path' => 'Listeners', 'suffix' => 'Listener'],
        'job' => ['stub' => 'job', 'path' => 'Jobs', 'suffix' => 'Job'],
        'notification' => ['stub' => 'notification', 'path' => 'Notifications', 'suffix' => 'Notification'],
        'command' => ['stub' => 'command', 'path' => 'Console', 'suffix' => 'Command'],
        'test' => ['stub' => 'test', 'path' => 'Tests/Feature', 'suffix' => 'Test'],
        'unit-test' => ['stub' => 'test', 'path' => 'Tests/Unit', 'suffix' => 'Test'],
        'middleware' => ['stub' => 'middleware', 'path' => 'Http/Middleware'],
        'observer' => ['stub' => 'observer', 'path' => 'Observers', 'suffix' => 'Observer'],
    ];

    public function __construct(
        private readonly Filesystem $files,
        private readonly ModuleConfiguration $configuration,
        private readonly ModulePathResolver $paths,
        private readonly StubLocator $stubs,
    ) {}

    /**
     * @param  array<string, mixed>  $extra
     * @return array<int, string>
     */
    public function generate(string $moduleName, string $type, string $className, bool $force = false, array $extra = []): array
    {
        if (! isset(self::TYPES[$type])) {
            throw ModuleGenerationException::make($type, 'Unknown generator type.');
        }

        $module = ModuleName::parse($moduleName);
        $modulePath = $this->paths->path($module->studly());

        if (! $this->files->isDirectory($modulePath)) {
            throw ModuleNotFoundException::make($module->studly(), $this->paths->modulesPath());
        }

        $class = ModuleName::parseClass($className);
        $definition = self::TYPES[$type];
        $suffix = $definition['suffix'] ?? '';
        $baseName = $class->basename();

        if ($suffix !== '' && ! str_ends_with($baseName, $suffix)) {
            $baseName .= $suffix;
        }

        $relativeDir = $definition['path'];
        $nested = trim(str_replace('\\', '/', $class->relativePath()), '/');
        $nestedDir = dirname($nested);
        $targetDir = $relativeDir.($nestedDir !== '.' ? '/'.$nestedDir : '');
        $target = $this->paths->join($modulePath, $targetDir.'/'.$baseName.'.php');

        if ($this->files->exists($target) && ! $force) {
            throw ModuleGenerationException::alreadyExists($target);
        }

        $namespace = $this->configuration->namespace().'\\'.$module->studly().'\\'.str_replace('/', '\\', $targetDir);
        $classWithoutSuffix = $this->classNameWithoutSuffix($baseName, $suffix) ?: $baseName;

        $replacements = array_merge([
            '{{namespace}}' => $this->configuration->namespace(),
            '{{moduleName}}' => $module->studly(),
            '{{moduleNameLower}}' => $module->lower(),
            '{{className}}' => $classWithoutSuffix,
            '{{classNameLower}}' => strtolower($classWithoutSuffix),
            '{{class}}' => $baseName,
            '{{classNamespace}}' => $namespace,
            '{{moduleNamespace}}' => $this->configuration->namespace().'\\'.$module->studly(),
            '{{table}}' => Str::snake(Str::pluralStudly($classWithoutSuffix)),
        ], $extra);

        $content = $this->stubs->render($definition['stub'], $replacements);

        if ($content === '') {
            throw ModuleGenerationException::make($type, "Stub [{$definition['stub']}] was not found.");
        }

        $this->files->ensureDirectoryExists(dirname($target), 0755);
        $this->files->put($target, $content);

        $created = [$target];

        if ($type === 'repository') {
            $created = array_merge($created, $this->generate($moduleName, 'repository-interface', $className, $force, $extra));
        }

        if ($type === 'service') {
            $created = array_merge($created, $this->generate($moduleName, 'service-interface', $className, $force, $extra));
        }

        if ($type === 'model' && ($extra['factory'] ?? false)) {
            $created = array_merge($created, $this->generate($moduleName, 'factory', $className, $force, $extra));
        }

        return $created;
    }

    /**
     * @return array<int, string>
     */
    public function types(): array
    {
        return array_keys(self::TYPES);
    }

    private function classNameWithoutSuffix(string $name, string $suffix): string
    {
        if ($suffix !== '' && str_ends_with($name, $suffix)) {
            return substr($name, 0, -strlen($suffix));
        }

        return $name;
    }
}
