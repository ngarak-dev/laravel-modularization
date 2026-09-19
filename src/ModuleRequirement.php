<?php

declare(strict_types=1);

namespace NgarakDev\Modularization;

use NgarakDev\Modularization\Support\ModuleName;

/**
 * A named module dependency with an optional Composer-style version constraint.
 */
final class ModuleRequirement
{
    public function __construct(
        public readonly string $name,
        public readonly string $constraint = '*',
    ) {}

    /**
     * @param  array<int|string, mixed>|string  $raw
     */
    public static function parse(array|string $raw): self
    {
        if (is_array($raw)) {
            if (isset($raw['name'])) {
                return new self(
                    ModuleName::parse((string) $raw['name'])->studly(),
                    (string) ($raw['version'] ?? $raw['constraint'] ?? '*')
                );
            }

            $name = (string) array_key_first($raw);
            $constraint = $raw[$name] ?? '*';

            return new self(ModuleName::parse($name)->studly(), is_string($constraint) ? $constraint : '*');
        }

        $trimmed = trim($raw);

        if (str_contains($trimmed, ':') && ! str_contains($trimmed, '://')) {
            [$name, $constraint] = explode(':', $trimmed, 2);

            return new self(ModuleName::parse($name)->studly(), trim($constraint) === '' ? '*' : trim($constraint));
        }

        return new self(ModuleName::parse($trimmed)->studly(), '*');
    }

    /**
     * @param  array<int|string, mixed>  $requires
     * @return array<string, self>
     */
    public static function parseList(array $requires): array
    {
        $parsed = [];

        foreach ($requires as $key => $value) {
            if (is_string($key) && ! is_numeric($key)) {
                $requirement = new self(
                    ModuleName::parse($key)->studly(),
                    is_string($value) && $value !== '' ? $value : '*'
                );
            } else {
                $requirement = self::parse(is_array($value) || is_string($value) ? $value : (string) $value);
            }

            $parsed[$requirement->name] = $requirement;
        }

        return $parsed;
    }

    public function isSatisfiedBy(string $version): bool
    {
        $constraint = trim($this->constraint);

        if ($constraint === '' || $constraint === '*') {
            return true;
        }

        $normalizedVersion = self::normalizeVersion($version);

        if (preg_match('/^\^(\d+)(?:\.(\d+))?(?:\.(\d+))?$/', $constraint, $matches) === 1) {
            $major = (int) $matches[1];
            $minor = (int) ($matches[2] ?? 0);
            $patch = (int) ($matches[3] ?? 0);
            $min = "{$major}.{$minor}.{$patch}";
            $max = ($major + 1).'.0.0';

            return version_compare($normalizedVersion, $min, '>=') && version_compare($normalizedVersion, $max, '<');
        }

        if (preg_match('/^~(\d+)\.(\d+)(?:\.(\d+))?$/', $constraint, $matches) === 1) {
            $major = (int) $matches[1];
            $minor = (int) $matches[2];
            $patch = (int) ($matches[3] ?? 0);
            $min = "{$major}.{$minor}.{$patch}";
            $max = $major.'.'.($minor + 1).'.0';

            return version_compare($normalizedVersion, $min, '>=') && version_compare($normalizedVersion, $max, '<');
        }

        if (preg_match('/^(>=|<=|>|<|=)?\s*(\d+\.\d+(?:\.\d+)?)$/', $constraint, $matches) === 1) {
            $operator = $matches[1] === '' || $matches[1] === '=' ? '==' : $matches[1];

            return version_compare($normalizedVersion, self::normalizeVersion($matches[2]), $operator);
        }

        return version_compare($normalizedVersion, self::normalizeVersion($constraint), '==');
    }

    public static function normalizeVersion(string $version): string
    {
        $version = ltrim(trim($version), 'vV');
        $parts = array_pad(explode('.', $version), 3, '0');

        return $parts[0].'.'.$parts[1].'.'.$parts[2];
    }
}
