<?php

namespace NgarakDev\Modularization\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class PublishStubsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'module:publish-stubs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Publish stubs for customization';

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
        // Source directory in package
        $sourcePath = __DIR__ . '/../../../stubs';

        // Destination directory in application
        $targetPath = base_path('stubs/vendor/modularization');

        if (!$this->files->isDirectory($sourcePath)) {
            $this->error('Stubs directory not found in package!');
            return 1;
        }

        // Create directory if it doesn't exist
        if (!$this->files->isDirectory($targetPath)) {
            $this->files->makeDirectory($targetPath, 0755, true);
        }

        // Get stub files and copy them
        $stubs = [];
        foreach ($this->files->files($sourcePath) as $file) {
            $filename = $file->getFilename();
            $destination = $targetPath . '/' . $filename;

            $this->files->copy($file->getPathname(), $destination);
            $stubs[] = $filename;
        }

        // Extract inline stubs if no files in stubs directory
        if (empty($stubs)) {
            $makeModuleCommand = new MakeModuleCommand($this->files);
            $stubs = $this->extractInlineStubs($makeModuleCommand);

            foreach ($stubs as $stubName => $content) {
                $this->files->put($targetPath . '/' . $stubName . '.stub', $content);
            }
        }

        $this->info('Stubs published successfully!');
        $this->info('You can now customize stubs at: ' . $targetPath);

        return 0;
    }

    /**
     * Extract inline stubs from MakeModuleCommand.
     *
     * @param  MakeModuleCommand  $command
     * @return array
     */
    protected function extractInlineStubs($command)
    {
        // Use reflection to access private property
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('getStubContents');
        $method->setAccessible(true);

        return $method->invoke($command);
    }
}
