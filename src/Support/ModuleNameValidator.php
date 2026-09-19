<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Support;

use NgarakDev\Modularization\Exceptions\InvalidModuleException;

/**
 * Validates module names for safety and consistency.
 */
final class ModuleNameValidator
{
    /**
     * Valid module name pattern: Must start with letter, contain only alphanumeric and underscores.
     */
    private const VALID_NAME_PATTERN = '/^[A-Za-z][A-Za-z0-9_]*$/';

    /**
     * Reserved names that cannot be used as module names.
     *
     * @var array<string>
     */
    private const RESERVED_NAMES = [
        'App',
        'Config',
        'Database',
        'Routes',
        'Storage',
        'Tests',
        'Vendor',
        'Bootstrap',
        'Public',
        'Resources',
        'Lang',
        'Core',
        'Base',
        'Module',
        'Modules',
    ];

    /**
     * Maximum length for module names.
     */
    private const MAX_NAME_LENGTH = 64;

    /**
     * Validate a module name.
     *
     * @throws InvalidModuleException
     */
    public function validate(string $name): void
    {
        if ($name === '') {
            throw InvalidModuleException::invalidName($name, 'Module name cannot be empty.');
        }

        if (strlen($name) > self::MAX_NAME_LENGTH) {
            throw InvalidModuleException::invalidName(
                $name,
                'Module name cannot exceed '.self::MAX_NAME_LENGTH.' characters.'
            );
        }

        if (! preg_match(self::VALID_NAME_PATTERN, $name)) {
            throw InvalidModuleException::invalidName(
                $name,
                'Module name must start with a letter and contain only letters, numbers, and underscores.'
            );
        }

        if (in_array($name, self::RESERVED_NAMES, true)) {
            throw InvalidModuleException::invalidName(
                $name,
                "'{$name}' is a reserved name and cannot be used as a module name."
            );
        }

        if ($this->containsPathTraversal($name)) {
            throw InvalidModuleException::invalidName(
                $name,
                'Module name cannot contain path traversal sequences.'
            );
        }
    }

    /**
     * Check if the name is valid without throwing exceptions.
     */
    public function isValid(string $name): bool
    {
        try {
            $this->validate($name);

            return true;
        } catch (InvalidModuleException) {
            return false;
        }
    }

    /**
     * Sanitize a module name to make it valid.
     */
    public function sanitize(string $name): string
    {
        $sanitized = preg_replace('/[^A-Za-z0-9_]/', '', $name);

        if ($sanitized === '' || $sanitized === null) {
            return 'Module';
        }

        if (! preg_match('/^[A-Za-z]/', $sanitized)) {
            $sanitized = 'Module'.$sanitized;
        }

        if (strlen($sanitized) > self::MAX_NAME_LENGTH) {
            $sanitized = substr($sanitized, 0, self::MAX_NAME_LENGTH);
        }

        return ucfirst($sanitized);
    }

    /**
     * Check for path traversal attempts.
     */
    private function containsPathTraversal(string $name): bool
    {
        $dangerous = [
            '..',
            '/',
            '\\',
            "\0",
            ':',
        ];

        foreach ($dangerous as $pattern) {
            if (str_contains($name, $pattern)) {
                return true;
            }
        }

        return false;
    }
}
