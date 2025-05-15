# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed

- **Breaking**: Standardized tool names to use `telescope_mcp.` prefix (e.g., `telescope_mcp.logs` instead of `mcp_Laravel_Telescope_MCP_logs`). This change affects all tool names in the MCP manifest and requires updating any client code that directly references tool names.

## [1.0.0] - 2024-05-15

### Added

- Initial release with support for all Laravel Telescope features via MCP
- Tools for accessing logs, requests, queries, exceptions, and more
- Configuration options for customizing the MCP server endpoint
- Comprehensive documentation and examples 