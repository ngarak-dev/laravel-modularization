# Changelog

All notable changes to this project will be documented in this file.

## [1.1.0] - Unreleased

### Added

- **`Module` value object** (`src/Module.php`): typed readonly properties, `fromManifest()` / `fromDirectory()` constructors, `withEnabled()`, `toArray()`, `providerClass()`, `providerPath()`.
- **`module.json` manifest** support: each module can declare `name`, `namespace`, `version`, `description`, `enabled`, `requires`.
- **`ModuleDiscovery`** class: focused filesystem scanning with module name validation (rejects path traversal, slashes, special characters, names starting with numbers).
- **`ModuleStatusManager`** class: persistent enable/disable via `.disabled` sentinel file; also updates `module.json`.
- **`ModuleCache`** class: write/read/clear a PHP bootstrap cache at `bootstrap/cache/modules.php`.
- **`ModuleDependencyResolver`** class: topological sort of modules; detects circular and missing dependencies.
- **`module:list` command**: table and JSON output (`--format=json`) showing all modules with status, version, description, dependencies.
- **`module:cache` command**: builds the bootstrap cache.
- **`module:clear` command**: removes the bootstrap cache.
- **Package-specific exceptions**: `ModuleException`, `ModuleNotFoundException`, `InvalidModuleException`, `ModuleDependencyException`, `ModuleGenerationException` — all `final`, with descriptive factory methods.
- **GitHub Actions CI**: tests matrix across PHP 8.1/8.2/8.3 × Laravel 10/11/12; separate Pint and PHPStan jobs.
- **Laravel Pint** configuration (`pint.json`) enforcing Laravel preset with `declare_strict_types`, ordered imports, trailing commas.
- **PHPStan/Larastan** configuration (`phpstan.neon`) at level 5.
- **41 new tests**: `ModuleCacheTest`, `ModuleDependencyResolverTest`, `ModuleStatusManagerTest`, `ModuleSecurityTest`, `ModuleDiscoveryTest`.

### Changed

- `ModularizationService` is now `final`, uses constructor DI for `ModuleDiscovery`, `ModuleStatusManager`, `ModuleCache`; removed unused `Filesystem` dependency.
- `ModularizationServiceProvider::register()` binds all new support classes as singletons.
- Exception classes are now `final`.
- All command methods have complete parameter and return type annotations.
- All core `src/` files pass Pint (Laravel preset) and PHPStan level 5.
- Stubs modernized: `declare(strict_types=1)`, `readonly` constructor promotion, typed return types.
- Minimum PHP bumped to **8.1** (was 8.0).

### Fixed

- Removed redundant `Filesystem $files` parameter from `ModularizationService` constructor.
- Fixed `booleanOr.rightAlwaysFalse` in `MakeModuleAuthCommand` and `MakeModuleManagerCommand` where `$force` was always `false` inside an `else if (!$force)` block.
- `Route::namespace()` deprecation removed from service provider.
- Livewire registration gracefully skips when Livewire is not installed.

### Security

- Module names are validated against a strict regex; names with `..`, `/`, `\`, special characters, or numeric starts are rejected.
- `ModuleDiscovery` ignores dot-prefixed directories.
- Path traversal via malicious module names is blocked at the `module:make` command level.

## [1.0.6] - 2024-05-31

### Added

- Compatibility methods (`getAll()`, `findById()`) to bridge between repository and service naming conventions
- Module-specific navigation file template to support proper layouts
- Navigation.stub for generating consistent navigation files in all new modules

### Fixed

- Double-prefixing of route names in module routes (e.g., "products.products.index")
- "View [layouts.app] not found" errors by updating module-layout.stub to use module-specific layouts
- Type errors in repository pattern implementation where services were passing IDs instead of model objects
- Issues with service implementations failing to properly fetch models by ID

### Changed

- Updated view stubs to consistently use module-specific layouts
- Enhanced route naming convention for clearer route identification
- Improved module services to handle both direct model objects and IDs

## [1.0.5] - 2024-05-29

### Added

- Module:make-migration command for creating module-specific migrations
- Support for custom migration paths within modules
- Options for table creation and modification in module migrations

### Fixed

- Issues with migration handling in modules

## [1.0.4] - 2024-05-28

### Added

- Module migration commands to run migrations for specific modules
- Added migrate:fresh, migrate:rollback, and migrate:status functionality to module migrations
- Documentation for --with-resource option in README

### Fixed

- Migration commands to ensure they only affect module-specific migrations

## [1.0.3] - 2024-05-28

### Fixed

- Missing RouteServiceProvider in module:make-manager command
- Issues with module manager routes registration

## [1.0.2] - 2024-05-27

### Added

- Module_path() helper function for easier module path resolution
- Standardized module configuration structure with name, description, routes, and menu settings
- New `module:make-manager` command to create a module management dashboard
- Module Manager UI for enabling/disabling modules through a web interface
- Icon support for module menu items in configuration

### Changed

- Updated config file structure for all modules to follow a consistent format
- Improved Authentication module with better route handling

## [1.0.1] - 2024-05-26

### Fixed

- Issue with missing Config/config.php file in Auth module causing "Failed to open stream" errors
- Added automatic config file creation for the Auth module
- Ensured Config directory is always created in the module structure
- Fixed auth logout route naming

## [1.0.0] - 2024-05-26

### Added

- Stable release of Laravel Modularization package
- Helper function `module_path()` for easier module path resolution
- Complete authentication module generation with `module:make-auth` command
- Improved styling for authentication views with Tailwind CSS
- Comprehensive test coverage for all features

### Changed

- Moved package status from alpha/development to stable
- Updated service provider to properly handle helper functions
- Improved documentation with detailed examples
- Enhanced module creation and auto-discovery process

### Fixed

- Issue with undefined `module_path()` function by adding proper helper
- Authentication views width and styling issues
- Module service provider template to follow best practices

## [0.1.9-alpha] - 2024-05-24

### Added

- Initial authentication module functionality
- Basic module creation functionality
- Repository and service pattern implementation
- Module auto-discovery
