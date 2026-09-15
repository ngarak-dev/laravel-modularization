<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Services;

use Illuminate\Filesystem\Filesystem;
use NgarakDev\Modularization\Contracts\ModuleRepositoryInterface;
use NgarakDev\Modularization\Contracts\ModuleStatusManagerInterface;
use NgarakDev\Modularization\Exceptions\ModuleNotFoundException;
use NgarakDev\Modularization\Support\Module;
use NgarakDev\Modularization\Support\ModulePathResolver;

/**
 * Manages module enabled/disabled status.
 */
final class ModuleStatusManager implements ModuleStatusManagerInterface
{
    public function __construct(
        private readonly ModuleRepositoryInterface $repository,
        private readonly ModulePathResolver $pathResolver,
        private readonly Filesystem $files,
    ) {
    }

    public function enable(string $name): void
    {
        $module = $this->repository->findOrFail($name);
        $disabledFile = $module->path('.disabled');

        if ($this->files->exists($disabledFile)) {
            $this->files->delete($disabledFile);
        }

        $this->updateConfigStatus($module, true);

        if ($module instanceof Module) {
            $module->setEnabled(true);
        }
    }

    public function disable(string $name): void
    {
        $module = $this->repository->findOrFail($name);
        $disabledFile = $module->path('.disabled');

        $this->files->put($disabledFile, json_encode([
            'disabled_at' => date('Y-m-d H:i:s'),
            'disabled_by' => get_current_user(),
        ], JSON_PRETTY_PRINT));

        $this->updateConfigStatus($module, false);

        if ($module instanceof Module) {
            $module->setEnabled(false);
        }
    }

    public function isEnabled(string $name): bool
    {
        $module = $this->repository->find($name);

        if ($module === null) {
            return false;
        }

        return $module->isEnabled();
    }

    public function isDisabled(string $name): bool
    {
        return !$this->isEnabled($name);
    }

    public function toggle(string $name): bool
    {
        if ($this->isEnabled($name)) {
            $this->disable($name);
            return false;
        }

        $this->enable($name);
        return true;
    }

    /**
     * Update the config.php file to reflect enabled status.
     */
    private function updateConfigStatus(\NgarakDev\Modularization\Contracts\ModuleInterface $module, bool $enabled): void
    {
        $configPath = $module->path('Config/config.php');

        if (!$this->files->exists($configPath)) {
            return;
        }

        $config = require $configPath;

        if (!is_array($config)) {
            return;
        }

        $config['enabled'] = $enabled;

        $content = "<?php\n\nreturn " . $this->varExport($config) . ";\n";
        $this->files->put($configPath, $content);
    }

    /**
     * Export a variable with proper formatting.
     */
    private function varExport(mixed $var, int $indent = 0): string
    {
        if (!is_array($var)) {
            return var_export($var, true);
        }

        $isAssoc = array_keys($var) !== range(0, count($var) - 1);
        $spaces = str_repeat('    ', $indent);
        $nextIndent = $indent + 1;
        $nextSpaces = str_repeat('    ', $nextIndent);

        $output = "[\n";

        foreach ($var as $key => $value) {
            $output .= $nextSpaces;

            if ($isAssoc) {
                $output .= var_export($key, true) . ' => ';
            }

            $output .= $this->varExport($value, $nextIndent);
            $output .= ",\n";
        }

        $output .= $spaces . ']';

        return $output;
    }
}
