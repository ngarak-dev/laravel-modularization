<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use NgarakDev\Modularization\StubLocator;

class PublishStubsCommand extends Command
{
    protected $signature = 'module:publish-stubs {--force : Overwrite published stubs}';

    protected $description = 'Publish stubs for customization';

    public function handle(Filesystem $files, StubLocator $stubs): int
    {
        $sourcePath = $stubs->packageStubsPath();
        $targetPath = $stubs->publishedStubsPath();

        if (! $files->isDirectory($sourcePath)) {
            $this->error('Stubs directory not found in package!');

            return self::FAILURE;
        }

        $files->ensureDirectoryExists($targetPath, 0755);

        foreach ($files->allFiles($sourcePath) as $file) {
            $relative = $file->getRelativePathname();
            $destination = $targetPath.DIRECTORY_SEPARATOR.$relative;

            if ($files->exists($destination) && ! $this->option('force')) {
                continue;
            }

            $files->ensureDirectoryExists(dirname($destination), 0755);
            $files->copy($file->getPathname(), $destination);
        }

        $this->info('Stubs published successfully!');
        $this->info('You can now customize stubs at: '.$targetPath);

        return self::SUCCESS;
    }
}
