<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Exceptions;

use Exception;

/**
 * Base exception for all module-related errors.
 */
class ModuleException extends Exception
{
    protected string $moduleName;

    public function __construct(string $message = '', string $moduleName = '', int $code = 0, ?\Throwable $previous = null)
    {
        $this->moduleName = $moduleName;
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the name of the module that caused the exception.
     */
    public function getModuleName(): string
    {
        return $this->moduleName;
    }
}
