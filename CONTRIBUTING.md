# Contributing

Thank you for considering contributing to Laravel Modularization!

## Development Setup

1. Fork the repository
2. Clone your fork:
   ```bash
   git clone https://github.com/YOUR_USERNAME/laravel-modularization.git
   cd laravel-modularization
   ```
3. Install dependencies:
   ```bash
   composer install
   ```

## Running Tests

```bash
# Run all tests
composer test

# Run specific test file
vendor/bin/phpunit tests/Unit/Support/ModuleTest.php

# Run with coverage
composer test-coverage
```

## Code Style

This project uses [Laravel Pint](https://laravel.com/docs/pint) for code style:

```bash
# Check code style
composer format-check

# Fix code style
composer format
```

## Static Analysis

We use [PHPStan](https://phpstan.org/) at level 6:

```bash
composer analyse
```

## Pull Request Process

1. Create a feature branch: `git checkout -b feature/my-feature`
2. Make your changes
3. Write tests for your changes
4. Ensure all tests pass: `composer ci`
5. Commit your changes with a descriptive message
6. Push to your fork: `git push origin feature/my-feature`
7. Open a Pull Request

## Commit Messages

Follow the [Conventional Commits](https://www.conventionalcommits.org/) specification:

- `feat:` New features
- `fix:` Bug fixes
- `docs:` Documentation changes
- `style:` Code style changes (formatting, etc.)
- `refactor:` Code refactoring
- `test:` Adding or updating tests
- `chore:` Maintenance tasks

Examples:
```
feat: add module:list command
fix: resolve circular dependency detection
docs: update README with caching instructions
```

## Branch Naming

- `feature/` - New features
- `fix/` - Bug fixes
- `docs/` - Documentation
- `refactor/` - Code refactoring

## Testing Guidelines

- Write tests for all new features
- Write tests for bug fixes (regression tests)
- Prefer behavioral tests over implementation tests
- Use descriptive test method names

```php
/** @test */
public function it_creates_module_with_dependencies(): void
{
    // Arrange
    // Act
    // Assert
}
```

## Code Guidelines

1. **Use strict types**: `declare(strict_types=1);`
2. **Type everything**: Properties, parameters, return types
3. **Final by default**: Make classes `final` unless extension is intended
4. **Dependency injection**: Prefer constructor injection
5. **No static calls**: Avoid facades in core package code
6. **Document contracts**: Add PHPDoc to interface methods

## Questions?

Feel free to open an issue for any questions about contributing.
