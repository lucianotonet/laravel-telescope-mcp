[![Latest Version on Packagist](https://img.shields.io/packagist/v/lucianotonet/laravel-telescope-mcp.svg)](https://packagist.org/packages/lucianotonet/laravel-telescope-mcp)
[![Downloads](https://img.shields.io/packagist/dt/lucianotonet/laravel-telescope-mcp.svg)](https://packagist.org/packages/lucianotonet/laravel-telescope-mcp)
[![License](https://img.shields.io/github/license/lucianotonet/laravel-telescope-mcp)](LICENSE)

# Laravel Telescope MCP

An extension for Laravel Telescope that exposes telemetry data via the Model Context Protocol (MCP) to AI assistants (e.g., Cursor, Claude, Copilot Chat). Ideal for developers who use Telescope to inspect application metrics and require quick, precise insights.

## Overview

Telescope MCP translates natural-language queries into MCP operations, retrieves Telescope records, and returns concise responses. This enables developers to query logs, slow queries, HTTP requests, exceptions, jobs, and more using an AI interface.

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

## Connecting an AI Client

For Cursor (example):

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
           "http://localhost:8000/telescope-mcp",
           "--allow-http"
         ],
         "env": { "NODE_TLS_REJECT_UNAUTHORIZED": "0" }
       }
     }
   }
   ```
   
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
           "https://example.com/telescope/mcp"            
         ]
       }
     }
   }
   ```


## Usage Examples

* *"Using your MCP tools, fetch the last 5 error logs."*
* *"Identify SQL queries longer than 100ms in the past twenty minutes."*
* *"Show all last failed jobs."*
* *"Summarize HTTP requests with status >=500 since last hour."*

The AI will parse the query, call the MCP endpoint, analyze results, and return a summary.

## Available Tools

| Tool | Description | Parameters |
| ---- | ----------- | ---------- |
| **Batches** | Lists and analyzes batch operations | `id`, `limit`, `status` (pending/processing/finished/failed), `name` |
| **Cache** | Monitors cache operations | `id`, `limit`, `operation` (hit/miss/set/forget), `key` |
| **Commands** | Tracks Artisan command executions | `id`, `limit`, `command`, `status` (success/error) |
| **Dumps** | Records var_dump and dd() calls | `id`, `limit`, `file`, `line` |
| **Events** | Monitors event dispatches | `id`, `limit`, `name` |
| **Exceptions** | Tracks application errors | `id`, `limit` |
| **Gates** | Records authorization checks | `id`, `limit`, `ability`, `result` (allowed/denied) |
| **HTTP Client** | Monitors outgoing HTTP requests | `id`, `limit`, `method`, `status`, `url` |
| **Jobs** | Tracks queued job executions | `id`, `limit`, `status` (pending/processed/failed), `queue` |
| **Logs** | Records application logs | `id`, `limit`, `level`, `message` |
| **Mail** | Monitors email operations | `id`, `limit`, `to`, `subject` |
| **Models** | Tracks Eloquent model operations | `id`, `limit`, `action` (created/updated/deleted), `model` |
| **Notifications** | Records notification dispatches | `id`, `limit`, `channel`, `status` (sent/failed) |
| **Queries** | Monitors database queries | `id`, `limit`, `slow` (boolean) |
| **Redis** | Tracks Redis operations | `id`, `limit`, `command` |
| **Requests** | Records incoming HTTP requests | `id`, `limit`, `method`, `status`, `path` |
| **Schedule** | Monitors scheduled task executions | `id`, `limit` |
| **Views** | Records view renders | `id`, `limit` |
| **Prune** | Removes old Telescope entries | `hours` |

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

## Contributing

Contributions are welcome. Please submit issues or pull requests following our [CONTRIBUTING.md](/CONTRIBUTING.md) guidelines.

## License

Licensed under MIT. See [LICENSE](LICENSE) for details.
