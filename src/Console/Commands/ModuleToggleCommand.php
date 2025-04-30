<?php

namespace NgarakDev\Modularization\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Config;

class ModuleToggleCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'module:toggle {name : The name of the module} {--disable : Disable the module instead of enabling it}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Enable or disable a module';

    /**
     * The filesystem instance.
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $files;

    /**
     * Create a new command instance.
     *
     * @param  \Illuminate\Filesystem\Filesystem  $files
     * @return void
     */
    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $moduleName = $this->argument('name');
        $disable = $this->option('disable');

        $modulesPath = base_path(config('modularization.modules_path', 'modules'));
        $moduleDir = $modulesPath . '/' . $moduleName;

        // Check if module exists
        if (!$this->files->isDirectory($moduleDir)) {
            $this->error("Module [$moduleName] does not exist!");
            return 1;
        }

        // Get or create status file path (.disabled)
        $statusFile = $moduleDir . '/.disabled';

        if ($disable) {
            // Disable the module
            if ($this->files->exists($statusFile)) {
                $this->info("Module [$moduleName] is already disabled.");
                return 0;
            }

            // Create disabled file
            $this->files->put($statusFile, json_encode([
                'disabled_at' => now()->toDateTimeString(),
                'disabled_by' => get_current_user(),
            ]));

            $this->info("Module [$moduleName] has been disabled.");
        } else {
            // Enable the module
            if (!$this->files->exists($statusFile)) {
                $this->info("Module [$moduleName] is already enabled.");
                return 0;
            }

            // Remove disabled file
            $this->files->delete($statusFile);

            $this->info("Module [$moduleName] has been enabled.");
        }

        // Clear configuration cache if exists
        if ($this->laravel->bound('Illuminate\Contracts\Console\Kernel')) {
            $this->call('config:clear');
        }

        return 0;
    }
}
