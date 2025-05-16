# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Added CHANGELOG.md and standardized tool names in documentation. (9ddfa4a)
- Enhanced MCP tools with JSON-RPC support and improved error handling. (3cc891b)
- Implement MCP Tools for Laravel Telescope - Added TelescopeMcpServer with improved tool handling and legacy support. (d913f8f)
- Implemented core MCP tools for various Telescope features including Batches, Cache, Commands, Dumps, Events, Exceptions, Gates, HTTP Client, Jobs, Logs, Mail, Models, Notifications, Queries, Redis, Requests, Scheduled Tasks and Views. (d913f8f)
- Added AbstractTool base class for standardization. (d913f8f)
- Initial implementation of MCP tools for Laravel Telescope - Added 19 tool classes in Tools/ directory. (8d40e76)
- Initial implementation of LogsTool and RequestsTool. (8d40e76)
- PruneTool with old records cleanup functionality. (8d40e76)

### Changed

- **Breaking**: Standardized tool names to use `telescope_mcp.` prefix (e.g., `telescope_mcp.logs` instead of `mcp_Laravel_Telescope_MCP_logs`). This change affects all tool names in the MCP manifest and requires updating any client code that directly references tool names. (9ddfa4a)

### Fixed

- Adjust formatting in CI release workflow for Packagist notification. (802048f)
- Correct single quote syntax in CI release workflow for composer name extraction. (aebde03)
- Correct single quote syntax in CI release workflow tag conditions. (84a8748)
- Update PHPUnit command in CI workflow for better debugging. (65b626a)
- Simplify CI release workflow by fixing PHP version and test command. (42b9112)
- Correct syntax in CI release workflow for caching and tag conditions. (191cee7)
- Enable logging configuration in Telescope MCP settings. (3e69679)
- Update default path for Telescope MCP configuration. (b6b27d0)
- Improved date handling in RequestsTool and updated documentation. (8c7161b)

### Refactored

- Update PHPUnit configuration and clean up TelescopeMcpTest. (cbcc7e3)
- Improve date handling and standardize response formatting across MCP tools. (d5d77e5)
- Standardize method signatures and improve documentation across MCP tools. (be24b84)
- Enhance date handling across all tools. (362804c)
- Refactor LogsTool and RequestsTool for improved logging and entry retrieval. (8b07a23)

### Documentation

- Update README.md to enhance installation instructions and usage examples. (244b44b)
- Add CONTRIBUTING.md and update issue/PR templates. (cf60e8f)

## [0.1.0] - 2024-05-15

### Added

- Initial release with support for all Laravel Telescope features via MCP
- Tools for accessing logs, requests, queries, exceptions, and more
- Configuration options for customizing the MCP server endpoint
- Comprehensive documentation and examples 