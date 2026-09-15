<?php

declare(strict_types=1);

namespace NgarakDev\Modularization;

use Illuminate\Filesystem\Filesystem;

final class ModularizationService
{
    private readonly ModuleManager $manager;

    public function __construct(ModuleManager|Filesystem $manager)
    {
        if ($manager instanceof ModuleManager) {
            $this->manager = $manager;
            return;
        }

        $resolver = new Support\ModulePathResolver(base_path((string) config('modularization.modules_path', 'modules')));
        $this->manager = new ModuleManager(
            new ModuleDiscovery($manager, $resolver),
            new ModuleStatusManager($manager, $resolver),
            $manager,
            base_path((string) config('modularization.discovery.cache_path', 'bootstrap/cache/modularization.php'))
        );
    }

    /** @return array<string, array<string, mixed>> */
    public function scanModules(): array
    {
        $this->manager->refresh();

        return $this->manager->all();
    }

    /** @return array<string, array<string, mixed>> */
    public function getModules(): array
    {
        return $this->manager->all();
    }

    public function hasModule(string $name): bool
    {
        return $this->manager->has($name);
    }

    public function isEnabled(string $name): bool
    {
        return $this->manager->isEnabled($name);
    }

    public function enable(string $name): bool
    {
        return $this->manager->enable($name);
    }

    public function disable(string $name): bool
    {
        return $this->manager->disable($name);
    }
}
