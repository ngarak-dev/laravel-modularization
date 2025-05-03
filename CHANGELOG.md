# Changelog

All notable changes to this project will be documented in this file.

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
