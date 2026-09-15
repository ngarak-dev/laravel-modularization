<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Console\Commands;

class MakeModuleNotificationCommand extends MakeModuleClassCommand
{
    protected $signature = 'module:make-notification
                            {module : The name of the module}
                            {name : The notification name}
                            {--force : Overwrite existing files}';

    protected $description = 'Create a notification in a module';

    protected string $type = 'notification';
}
