[![Latest Version on Packagist](https://img.shields.io/packagist/v/lucianotonet/laravel-telescope-mcp.svg)](https://packagist.org/packages/lucianotonet/laravel-telescope-mcp)
[![Downloads](https://img.shields.io/packagist/dt/lucianotonet/laravel-telescope-mcp.svg)](https://packagist.org/packages/lucianotonet/laravel-telescope-mcp)
[![License](https://img.shields.io/github/license/lucianotonet/laravel-telescope-mcp)](LICENSE)

# Laravel Telescope MCP

An extension for Laravel Telescope that exposes telemetry data via the Model Context Protocol (MCP) to AI assistants (e.g., Cursor, Claude, Copilot Chat). Ideal for developers who use Telescope to inspect application metrics and require quick, precise insights.

## Overview

Telescope MCP exposes all Laravel Telescope telemetry data via the Model Context Protocol (MCP), enabling AI assistants to directly access and analyze application metrics. This provides developers with instant insights into logs, slow queries, HTTP requests, exceptions, jobs, and more through natural language queries.

**Status**: ✅ **19 MCP tools fully operational and integrated**

## Laravel Boost users

Using [Laravel Boost](https://laravel.com/docs/boost)? **Prefer the dedicated package:**

**[lucianotonet/laravel-boost-telescope](https://packagist.org/packages/lucianotonet/laravel-boost-telescope)** — Telescope MCP built for the Boost MCP stack. It plugs straight into your existing Boost setup, so you get one unified MCP server instead of running this package alongside Boost.

```bash
composer require lucianotonet/laravel-boost-telescope --dev
```

This package detects Laravel Boost and suggests the switch during `php artisan package:discover` (which runs automatically during `composer install` or `composer update`). You can still use this package with Boost if you prefer, but **lucianotonet/laravel-boost-telescope** is the recommended choice for Boost projects.

## Installation

Make sure you have Laravel Telescope properly installed and configured in your application before proceeding

1. Add the package via Composer:

    ```bash
    composer require lucianotonet/laravel-telescope-mcp
    ```
2. Publish the configuration (optional):

   ```bash
   php artisan vendor:publish --provider="LucianoTonet\TelescopeMcp\TelescopeMcpServiceProvider"
   ```
3. Update your `.env` (optional):

   ```dotenv
   TELESCOPE_MCP_ENABLED=true
   TELESCOPE_MCP_PATH=telescope-mcp
   ```
   You can now verify the installation by accessing http://localhost:8000/telescope-mcp/manifest.json in your browser

## Connecting an AI Client (Cursor, Windsurf, Claude, etc.)

To connect your AI assistant, you'll generally need to add a new MCP server with the following remote configuration via `mcp-remote`:

1. Open Cursor command palette (Cmd/Ctrl+Shift+P).
2. Run **View: Open MCP Settings**.
3. Add this configuration:

   ```json
   {
     "mcpServers": {
       "Laravel Telescope MCP": {
         "command": "npx",
         "args": [
           "-y", 
           "mcp-remote", 
           "http://127.0.0.1:8000/telescope-mcp",
           "--allow-http"
         ],
         "env": { "NODE_TLS_REJECT_UNAUTHORIZED": "0" }
       }
     }
   }
   ```
   
   > **Important**: Use `127.0.0.1` instead of `localhost` to avoid IPv6 connection issues
   > Make sure the URL matches your `.env` configuration, combining `APP_URL` with `TELESCOPE_MCP_PATH`
  
4. For HTTPS, you can omit `--allow-http` and `NODE_TLS_REJECT_UNAUTHORIZED` like this:
   
   ```json
   {
     "mcpServers": {
       "Laravel Telescope MCP": {
         "command": "npx",
         "args": [
           "-y", 
           "mcp-remote", 
           "https://example.com/telescope-mcp"            
         ]
       }
     }
   }
   ```

## Troubleshooting

### Connection Refused Error

If you encounter `ECONNREFUSED` errors when trying to connect:

**Problem**: The MCP client is trying to connect via IPv6 (`::1`) but your Laravel server only accepts IPv4 connections.

**Solution**: Use `127.0.0.1` instead of `localhost` in your MCP configuration URL.

**Example**:
```json
// ❌ This may cause IPv6 connection issues
"http://localhost:8000/telescope-mcp"

// ✅ Use this to force IPv4 connection
"http://127.0.0.1:8000/telescope-mcp"
```

**Alternative**: If you prefer using `localhost`, you can start your Laravel server with IPv6 support:
```bash
php artisan serve --host=0.0.0.0 --port=8000
```

### MCP Tool Issues

**Problem**: Some tools may show empty results or errors.

**Solutions**:
1. **Ensure Telescope is recording data**: Check if your application is generating the type of data you're querying
2. **Verify tool parameters**: Some tools require specific parameters (e.g., `slow: true` for queries)
3. **Check data freshness**: Some tools may not have recent data depending on your application activity

**Tool-specific notes**:
- **Prune tool**: May show errors but doesn't affect other tools
- **Empty results**: Normal when no data of that type exists in Telescope


## Quick Start

### 1. **Install and Configure**
```bash
composer require lucianotonet/laravel-telescope-mcp
php artisan vendor:publish --provider="LucianoTonet\TelescopeMcp\TelescopeMcpServiceProvider"
```

### 2. **Connect AI Assistant**
Add to your assistant's MCP settings (e.g., Cursor, Windsurf):
```json
{
  "mcpServers": {
    "Laravel Telescope MCP": {
      "command": "npx",
      "args": ["-y", "mcp-remote", "http://127.0.0.1:8000/telescope-mcp", "--allow-http"],
      "env": { "NODE_TLS_REJECT_UNAUTHORIZED": "0" }
    }
  }
}
```

### 3. **Start Using MCP Tools**
```bash
# Check recent requests
@laravel-telescope-mcp requests --limit 5

# Find errors
@laravel-telescope-mcp exceptions --limit 3

# Monitor database performance
@laravel-telescope-mcp queries --slow true
```

## Usage Examples

### Direct MCP Tool Usage (Recommended)

Once connected, you can use the MCP tools directly in your AI assistant:

```bash
# List recent HTTP requests
@laravel-telescope-mcp requests --limit 5

# Get details of a specific exception
@laravel-telescope-mcp exceptions --id 123456

# Find slow database queries
@laravel-telescope-mcp queries --slow true --limit 10

# Check recent logs
@laravel-telescope-mcp logs --level error --limit 5
```

### Natural Language Queries

* *"Show me the last 5 error logs from the application"*
* *"Identify SQL queries taking longer than 100ms"*
* *"Display all failed jobs from the last hour"*
* *"Summarize HTTP requests with 5xx status codes"*

The AI will automatically use the appropriate MCP tools to fetch and analyze the data.

## Available Tools

All 19 MCP tools are fully operational and provide structured responses with both human-readable text and JSON data.

| Tool | Status | Description | Key Parameters |
| ---- | ------ | ----------- | -------------- |
| **Requests** | ✅ | Records incoming HTTP requests | `id`, `limit`, `method`, `status`, `path` |
| **Exceptions** | ✅ | Tracks application errors with stack traces | `id`, `limit` |
| **Queries** | ✅ | Monitors database queries with performance metrics | `id`, `limit`, `slow` (boolean) |
| **Logs** | ✅ | Records application logs with filtering | `id`, `limit`, `level`, `message` |
| **HTTP Client** | ✅ | Monitors outgoing HTTP requests | `id`, `limit`, `method`, `status`, `url` |
| **Mail** | ✅ | Monitors email operations | `id`, `limit`, `to`, `subject` |
| **Notifications** | ✅ | Records notification dispatches | `id`, `limit`, `channel`, `status` |
| **Jobs** | ✅ | Tracks queued job executions | `id`, `limit`, `status`, `queue` |
| **Events** | ✅ | Monitors event dispatches | `id`, `limit`, `name` |
| **Models** | ✅ | Tracks Eloquent model operations | `id`, `limit`, `action`, `model` |
| **Cache** | ✅ | Monitors cache operations | `id`, `limit`, `operation`, `key` |
| **Redis** | ✅ | Tracks Redis operations | `id`, `limit`, `command` |
| **Schedule** | ✅ | Monitors scheduled task executions | `id`, `limit` |
| **Views** | ✅ | Records view renders | `id`, `limit` |
| **Dumps** | ✅ | Records var_dump and dd() calls | `id`, `limit`, `file`, `line` |
| **Commands** | ✅ | Tracks Artisan command executions | `id`, `limit`, `command`, `status` |
| **Gates** | ✅ | Records authorization checks | `id`, `limit`, `ability`, `result` |
| **Batches** | ✅ | Lists and analyzes batch operations | `id`, `limit`, `status`, `name` |
| **Prune** | ⚠️ | Removes old Telescope entries | `hours` |

**Legend**: ✅ Fully Operational | ⚠️ Minor Issues

## Current Status & Features

### ✅ **MCP Integration Status**
- **19 MCP tools operational**: All major Telescope features are now accessible via MCP
- **Native Cursor integration**: Tools work directly within Cursor without external commands
- **Structured responses**: Each tool returns both human-readable text and JSON data
- **Real-time data access**: Direct access to Telescope telemetry without HTTP requests

### 🚀 **Key Benefits**
- **No more cURL needed**: Use MCP tools directly in your AI assistant
- **Instant insights**: Get application metrics through natural language
- **Structured data**: Both readable summaries and programmatic access
- **Full Telescope coverage**: Access to all major monitoring features

### 📊 **Response Format**
Each MCP tool provides:
- **Human-readable output**: Formatted tables and summaries
- **JSON data**: Structured data for programmatic processing
- **MCP compliance**: Standard MCP response format

### 🔧 **Tool Capabilities**
- **List operations**: Get overviews with customizable limits
- **Detail views**: Drill down into specific entries by ID
- **Filtering**: Apply filters like status, level, time ranges
- **Performance metrics**: Track slow queries, failed jobs, errors

## Configuration

* **Authentication**: Protect the MCP endpoint using middleware (e.g., `auth:sanctum`, `auth.basic`).
* **Endpoint Path**: Customize `TELESCOPE_MCP_PATH` or modify in `config/telescope-mcp.php`.
* **Logging**: Enable or disable internal MCP logging.
* **Timeouts & Limits**: Adjust request timeouts and payload limits as needed.

## Advanced

See `config/telescope-mcp.php` for:

* Custom middleware stacks
* Operation-specific settings
* Route and namespace overrides

## Performance & Monitoring

### **Real-time Insights**
- **HTTP Requests**: Monitor incoming traffic, response times, and status codes
- **Database Queries**: Track slow queries and optimize performance
- **Application Errors**: Get detailed stack traces and error context
- **Job Processing**: Monitor queue performance and failures
- **Cache Operations**: Track cache hit/miss ratios and performance

### **Data Retention**
- **Configurable limits**: Set appropriate limits for each tool based on your needs
- **Efficient queries**: Tools use optimized Telescope queries for fast responses
- **Memory management**: Responses are formatted efficiently for MCP clients

## Contributing

Contributions are welcome. Please submit issues or pull requests following our [CONTRIBUTING.md](/CONTRIBUTING.md) guidelines.

## License

Licensed under MIT. See [LICENSE](LICENSE) for details.
