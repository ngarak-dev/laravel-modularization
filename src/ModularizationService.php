<?php

namespace NgarakDev\Modularization;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class ModularizationService
{
    /**
     * @var Filesystem
     */
    protected $files;

    /**
     * @var string
     */
    protected $basePath;

    /**
     * @var array
     */
    protected $modules = [];

    /**
     * Create a new ModularizationService instance.
     *
     * @param Filesystem $files
     */
    public function __construct(Filesystem $files)
    {
        $this->files = $files;
        $this->basePath = base_path(config('modularization.modules_path'));
        $this->scanModules();
    }

    /**
     * Scan for all available modules.
     *
     * @return void
     */
    public function scanModules()
    {
        if (!$this->files->isDirectory($this->basePath)) {
            return;
        }

        $modules = $this->files->directories($this->basePath);

        foreach ($modules as $module) {
            $name = basename($module);
            $this->modules[$name] = [
                'name' => $name,
                'path' => $module,
                'enabled' => true, // By default, all modules are enabled
            ];
        }
    }

    /**
     * Get all modules.
     *
     * @return array
     */
    public function getModules()
    {
        return $this->modules;
    }

    /**
     * Determine whether the given module exists.
     *
     * @param string $name
     * @return bool
     */
    public function hasModule($name)
    {
        return isset($this->modules[$name]);
    }

    /**
     * Determine whether the given module is enabled.
     *
     * @param string $name
     * @return bool
     */
    public function isEnabled($name)
    {
        return $this->hasModule($name) && $this->modules[$name]['enabled'];
    }

    /**
     * Enable a module.
     *
     * @param string $name
     * @return bool
     */
    public function enable($name)
    {
        if ($this->hasModule($name)) {
            $this->modules[$name]['enabled'] = true;
            return true;
        }

        return false;
    }

    /**
     * Disable a module.
     *
     * @param string $name
     * @return bool
     */
    public function disable($name)
    {
        if ($this->hasModule($name)) {
            $this->modules[$name]['enabled'] = false;
            return true;
        }

        return false;
    }
}
