# Changelog

All notable changes to this project will be documented in this file.

## [1.0.2] - 2024-05-27

### Added

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
