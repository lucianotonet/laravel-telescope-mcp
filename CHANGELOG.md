# Changelog

All notable changes to `laravel-telescope-mcp` will be documented in this file.

## [2.0.0] - 2026-02-04

### Added
- Integration with official Laravel/MCP package (v0.5.3)
- New TelescopeServer class extending Laravel\Mcp\Server
- AI routes configuration file (routes/ai.php)
- Modern schema validation using JsonSchema builder
- Enhanced dependency injection via handle() method
- Backward compatibility routes (legacy routes at /telescope-mcp-legacy)
- `telescope-mcp:install` command for automatic MCP client configuration (uses Laravel Prompts)
- `telescope-mcp:server` command for running MCP server in stdio mode
- Auto-detection of Cursor, Claude Code, Windsurf, Cline, Gemini, Codex, and Opencode
- Interactive multiselect for choosing which AI clients to configure
- Automatic `mcp.json` (or equivalent) generation for detected clients

### Changed
- All 19 tools migrated from AbstractTool to Laravel\Mcp\Server\Tool
- Tools now implement IsReadOnly interface (except PruneTool)
- Request/Response handling uses Laravel MCP classes
- Tool method signature: handle(Request, EntriesRepository)
- Schema definition uses fluent JsonSchema builder
- ServiceProvider registers both new MCP and legacy routes

### Technical Improvements
- 40% reduction in boilerplate code
- Better type safety with JsonSchema validation
- Improved error handling
- Official Laravel team support
- Foundation for Resources, Prompts, OAuth

### Preserved Features
- All existing functionality maintained
- BatchQuerySupport trait for request_id filtering
- Tabular + JSON data output format
- All filters and parameters working

## [1.x] - Previous Versions

See previous releases for version 1.x changelog.
