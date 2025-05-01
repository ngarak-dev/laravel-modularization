# Changelog

All notable changes to this project will be documented in this file.

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
