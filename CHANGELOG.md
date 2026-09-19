# Changelog

All notable changes to this project will be documented in this file.

## [1.1.0] - 2026-09-19

### Added

- `module.json` module contract (`name`, `namespace`, `provider`, `version`, `description`, `enabled`, `requires`)
- Semver constraints on `requires` (`^`, `~`, `>=`, and exact versions)
- Module dependency detection, circular-dependency errors, and deterministic load order
- Metadata cache with `module:cache`, `module:clear`, and `module:discover`
- Shared JSON registry (`bootstrap/cache/modules-registry.json`) written by `module:cache`
- `module:list` status table (enabled, disabled, installing, broken, invalid, missing dependencies, cached)
- Lifecycle events: `ModuleDiscovered`, `ModuleEnabled`, `ModuleDisabled`, `ModuleRegistered`, `ModuleBroken`
- `.installing` and `.broken` marker files in addition to `.disabled`
- Route-name collision detection (`modularization.routes.fail_on_collision`)
- Per-module Vite inputs collected onto `modularization.vite.inputs`
- `composer dump-autoload` after `module:make` (`dump_autoload`, skipped in testing)
- Generators for controller, model, repository, service, request, resource, seeder, factory, policy, job, notification, command, test, listener, auth, manager, translation, livewire, export, and migrate
- Optional `--no-repository` / `--no-service` module scaffolding
- `--requires` on `module:make`
- Package-specific exceptions with actionable messages
- Laravel Pint, PHPStan level 6 on the full `src/` tree, and GitHub Actions for PHP 8.1–8.3 and Laravel 10–12
- Composer scripts: `test`, `test-coverage`, `format`, `format-check`, `analyse`, `ci`
- `modules_path()` helper and a typed `ModuleManager` API behind the existing facade
- `ModularizationService::VERSION` is `1.1.0`

### Changed

- One runtime: `ModuleManager` plus contracts, implemented by the `src/` core (duplicate `Support\*` / `Services\*` runtimes removed)
- Auth, manager, event, translation, livewire, export, and migrate commands delegate to generators
- `enable()` / `disable()` persist through `.disabled` and invalidate the module cache
- Livewire is a suggested dependency instead of a hard requirement
- Generated stubs are typed PHP 8.1+ and keep module vs class namespaces distinct
- `directories` and auto-register config keys are actually honored
- Module names and generator paths are validated so they cannot leave the modules directory
- Module cache ignores stored paths and reconstructs them from the module name; missing directories are dropped
- Missing dependencies warn by default (`dependencies.warn_on_missing`) instead of only existing/enabled checks

### Fixed

- Resource classes generated inside the parent module namespace
- Enable/disable only updating in-memory state
- Missing `createTranslationFiles` path on module creation
- Livewire view paths using mixed `resources` / `Resources` directories
- `module:make` / `make:module` aliases not both being registered
- Windows-style absolute paths and mixed separators in module path joining
- Duplicate CI workflows racing the same jobs

### Upgrade

See [UPGRADE.md](UPGRADE.md). Existing modules without `module.json` continue to load from `Config/config.php`.

## [1.0.6] - 2024-05-31

### Added

#### Core Architecture
- **ModuleManager**: New centralized module management class with clean public API
- **ModuleInterface**: Contract defining what constitutes a module
- **ModuleRepositoryInterface**: Contract for module storage and retrieval
- **ModuleDiscoveryInterface**: Contract for filesystem module discovery
- **ModuleLoaderInterface**: Contract for loading module components
- **ModuleStatusManagerInterface**: Contract for enable/disable functionality
- **ModuleCacheInterface**: Contract for module metadata caching

#### Module Manifest
- **module.json support**: Modules now have a JSON manifest file for metadata
- **Module dependencies**: Declare dependencies with `"requires": ["OtherModule"]`
- **Circular dependency detection**: Prevents infinite loops in module loading
- **Topological sorting**: Modules load in correct dependency order

#### Commands
- `module:list`: List all modules with status, version, and dependencies
- `module:cache`: Cache module metadata for production performance
- `module:clear`: Clear the module cache

#### Security
- **ModuleNameValidator**: Validates module names for safety
- **Path traversal protection**: Prevents malicious module names from escaping directories
- **Reserved name checking**: Blocks system-reserved names like "App", "Config", etc.

#### Code Quality
- **Strict types**: All new code uses `declare(strict_types=1)`
- **Typed properties**: PHP 8.1+ typed properties throughout
- **PHPStan**: Level 6 static analysis
- **Laravel Pint**: PSR-12 code style enforcement
- **GitHub Actions CI**: Automated testing for PHP 8.1-8.3 and Laravel 10-12

#### Stubs
- Modernized all stubs with strict types and typed properties
- Updated controller stubs with proper return types
- Updated repository stubs with collection return types
- Updated service stubs with dependency injection
- Added `{{moduleNameKebab}}` placeholder for kebab-case names

### Changed

- **Service Provider**: Completely rewritten with dependency injection
- **ModularizationService**: Now delegates to ModuleManager (backwards compatible)
- **Facade**: Updated to proxy to ModuleManager
- **Configuration**: Enhanced with detailed documentation
- **Helper functions**: Added `modules()`, `module()`, `module_enabled()`

### Deprecated

- `ModularizationService::getModules()` - Use `ModuleManager::all()`
- `ModularizationService::scanModules()` - Modules are discovered automatically

### Fixed

- Module discovery no longer scans filesystem on every request when cached
- Route namespace handling improved for Laravel 11 compatibility
- View namespace registration properly handles custom paths

## [1.0.6] - 2024-05-31

### Added
- Compatibility methods (`getAll()`, `findById()`) for repository-service naming conventions
- Module-specific navigation file template
- Navigation.stub for generating consistent navigation files

### Fixed
- Double-prefixing of route names in module routes
- "View [layouts.app] not found" errors
- Type errors in repository pattern implementation

### Changed
- Updated view stubs to use module-specific layouts
- Enhanced route naming convention

## [1.0.5] - 2024-05-29

### Added
- `module:make-migration` command for module-specific migrations
- Custom migration path support within modules
- Table creation and modification options

## [1.0.4] - 2024-05-28

### Added
- Module migration commands (`module:migrate`, `module:migrate-all`)
- Migration operations: fresh, rollback, status, reset, refresh
- Documentation for `--with-resource` option

## [1.0.3] - 2024-05-28

### Fixed
- Missing RouteServiceProvider in `module:make-manager`
- Module manager routes registration issues

## [1.0.2] - 2024-05-27

### Added
- `module_path()` helper function
- Standardized module configuration structure
- `module:make-manager` command for module management UI
- Icon support for module menu items

## [1.0.1] - 2024-05-26

### Fixed
- Missing Config/config.php in Auth module
- Automatic config file creation for Auth module
- Config directory creation in module structure
- Auth logout route naming

## [1.0.0] - 2024-05-26

### Added
- Stable release of Laravel Modularization package
- `module_path()` helper function
- Complete authentication module generation
- Improved Tailwind CSS styling for auth views
- Comprehensive test coverage

### Changed
- Package status from alpha to stable
- Service provider helper function handling
- Documentation with detailed examples

## [0.1.9-alpha] - 2024-05-24

### Added
- Initial authentication module functionality
- Basic module creation
- Repository and service pattern implementation
- Module auto-discovery
