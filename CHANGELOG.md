# Changelog

All notable changes to `laravel-telescope-mcp` will be documented in this file.

## [Unreleased]

## [1.0.0-beta.1] - 2026-01-30

### Added
- Native integration with Laravel Boost via `laravel/mcp` package
- 20 Boost tools that register directly in the MCP server
- `TelescopeBoostTool` base class for tool wrappers
- `GenerateBoostToolsCommand` for automatic tool generation
- Automatic tool discovery and registration via `TelescopeBoostServiceProvider`
- Skills and Guidelines for Laravel Boost integration

### Fixed
- Fixed integration with Laravel Boost MCP server v0
- `TelescopeBoostTool::handle()` now correctly accepts `Laravel\Mcp\Request` objects
- `TelescopeBoostTool::handle()` now correctly returns `Laravel\Mcp\Response` objects
- Removed redundant abstract methods that caused fatal errors

### Changed
- **BREAKING**: Removed standalone HTTP/MCP mode
- Simplified configuration (removed `TELESCOPE_MCP_STANDALONE` and `TELESCOPE_MCP_PATH`)
- Updated documentation to reflect Boost-only integration
- Improved performance by eliminating tinker overhead

### Removed
- **BREAKING**: Standalone HTTP routes and controllers
- **BREAKING**: `TELESCOPE_MCP_STANDALONE` configuration option
- **BREAKING**: `TELESCOPE_MCP_PATH` configuration option

## [1.0.0] - 2026-01-30

### Added
- Initial release with 20 specialized debugging tools
- Skills and Guidelines for Laravel Boost integration
- Comprehensive documentation
- Test suite with Pest

### Features
- Exception tracking with stack traces
- Database query analysis with slow query detection
- HTTP request/response logging
- Application log entries
- Queue job execution monitoring
- Batch job processing
- Cache operations analysis
- Redis operations monitoring
- Eloquent model operations
- Email tracking
- Notification dispatching
- Artisan command execution
- Scheduled task monitoring
- Event dispatching
- Authorization gate checks
- View rendering
- Debug dumps (dump/dd)
- Outgoing HTTP requests
- Telescope entry pruning