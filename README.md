[![Latest Version on Packagist](https://img.shields.io/packagist/v/lucianotonet/laravel-telescope-mcp.svg)](https://packagist.org/packages/lucianotonet/laravel-telescope-mcp)
[![Downloads](https://img.shields.io/packagist/dt/lucianotonet/laravel-telescope-mcp.svg)](https://packagist.org/packages/lucianotonet/laravel-telescope-mcp)
[![License](https://img.shields.io/github/license/lucianotonet/laravel-telescope-mcp)](LICENSE)

# Laravel Telescope MCP

An extension for Laravel Telescope that exposes telemetry data via the Model Context Protocol (MCP) to AI assistants (e.g., Cursor, Claude, Copilot Chat). Ideal for developers who use Telescope to inspect application metrics and require quick, precise insights.

## Overview

Telescope MCP translates natural-language queries into MCP operations, retrieves Telescope records, and returns concise responses. This enables developers to query logs, slow queries, HTTP requests, exceptions, jobs, and more using an AI interface.

## Key Capabilities

| Telescope Component  | Sample Query                                                       |
| -------------------- | ------------------------------------------------------------------ |
| **Logs**             | "Fetch the last 10 error-level log entries."                       |
| **Queries**          | "List SQL queries above 500ms in the past hour."                   |
| **HTTP Requests**    | "Show HTTP requests with 5xx status codes since midnight."         |
| **Exceptions**       | "Display stack traces for the three most recent exceptions."       |
| **Jobs & Queues**    | "Which jobs failed in the last 24 hours?"                          |
| **Cache Operations** | "Count cache misses today."                                        |
| **Emails**           | "List emails sent to [user@example.com](mailto:user@example.com)." |
| **Authorization**    | "Which Gate checks were denied?"                                   |
| **Events**           | "How many UserRegistered events fired today?"                      |
| **Artisan Commands** | "When did queue\:work last run?"                                   |

## Installation

1. Add the package via Composer:

   ```bash
   composer require lucianotonet/laravel-telescope-mcp
   ```
2. Publish the configuration:

   ```bash
   php artisan vendor:publish --provider="LucianoTonet\TelescopeMcp\TelescopeMcpServiceProvider"
   ```
3. Update your `.env`:

   ```dotenv
   TELESCOPE_ENABLED=true
   TELESCOPE_MCP_ENABLED=true
   TELESCOPE_MCP_PATH=telescope/mcp
   ```

## Configuration

* **Authentication**: Protect the MCP endpoint using middleware (e.g., `auth:sanctum`, `auth.basic`).
* **Endpoint Path**: Customize `TELESCOPE_MCP_PATH` or modify in `config/telescope-mcp.php`.
* **Logging**: Enable or disable internal MCP logging.
* **Timeouts & Limits**: Adjust request timeouts and payload limits as needed.

## Connecting an AI Client

For Cursor (example):

1. Open Cursor command palette (Cmd/Ctrl+Shift+P).
2. Run **Connect to MCP Server**.
3. Use this configuration:

   ```json
   {
     "mcpServers": {
       "Laravel Telescope MCP": {
         "command": "npx",
         "args": [
            "-y", 
            "mcp-remote", 
            "http://localhost:8000/telescope/mcp", // Same APP_URL and TELESCOPE_MCP_PATH from .env
            "--allow-http"
          ],
         "env": { "NODE_TLS_REJECT_UNAUTHORIZED": "0" }
       }
     }
   }
   ```

> For HTTPS, you can omit `--allow-http` and `NODE_TLS_REJECT_UNAUTHORIZED`.

## Usage Examples

* *"Fetch the last 5 error logs."*
* *"Identify SQL queries longer than 100ms in the past two hours."*
* *"Show failed jobs from today."*
* *"Summarize HTTP requests with status >=500 since yesterday."*

The AI will parse the query, call the MCP endpoint, analyze results, and return a summary.

## Advanced

See `config/telescope-mcp.php` for:

* Custom middleware stacks
* Operation-specific settings
* Route and namespace overrides

## Contributing

Contributions are welcome. Please submit issues or pull requests following our [CONTRIBUTING.md](/CONTRIBUTING.md) guidelines.

## License

Licensed under MIT. See [LICENSE](LICENSE) for details.
