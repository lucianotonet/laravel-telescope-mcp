# Laravel Telescope MCP Tools

This package provides MCP (Model Context Protocol) tools for Laravel Telescope, enabling seamless access to Telescope data through a standardized API. With these tools, developers can monitor and manage various aspects of their Laravel applications, such as HTTP requests, logs, and other telemetry data captured by Telescope, via the MCP protocol, directly on your Cursor, Claude, Windsurf, etc.

## Available Tools

This MCP server provides access to a variety of Laravel Telescope data points. Here are some of the main tools:

### 1. Telescope Logs (`telescope_mcp.logs`)

Retrieves application logs from Laravel Telescope.

#### Parameters
- `id` (string, optional): ID of the specific log entry to view details.
- `limit` (integer, optional): Maximum number of log entries to return. Default: 50.
- `level` (string, optional): Filter by log level (debug, info, notice, warning, error, critical, alert, emergency).
- `message` (string, optional): Filter by log message content.

#### Response Format (List)
Tabular text format.

#### Examples
```json
{
    "name": "telescope_mcp.logs",
    "arguments": {
        "level": "error",
        "limit": 10
    }
}
```

### 2. Telescope Requests (`telescope_mcp.requests`)

Retrieves HTTP requests recorded by Laravel Telescope.

#### Parameters
- `id` (string, optional): ID of the specific request to view details.
- `limit` (integer, optional): Maximum number of requests to return. Default: 50.
- `method` (string, optional): Filter by HTTP method (GET, POST, etc.).
- `path` (string, optional): Filter by request path.
- `status` (integer, optional): Filter by HTTP status code.

#### Response Format (List)
Tabular text format.

#### Examples
```json
{
    "name": "telescope_mcp.requests",
    "arguments": {
        "limit": 5,
        "method": "POST"
    }
}
```

### 3. Telescope Queries (`telescope_mcp.queries`)

Retrieves database queries recorded by Laravel Telescope.

#### Parameters
- `id` (string, optional): ID of the specific query to view details.
- `limit` (integer, optional): Maximum number of queries to return. Default: 50.
- `slow` (boolean, optional): Filter for slow queries (typically >100ms).

#### Response Format (List)
Tabular text format.

#### Examples
```json
{
    "name": "telescope_mcp.queries",
    "arguments": {
        "limit": 10,
        "slow": true
    }
}
```

### 4. Telescope Exceptions (`telescope_mcp.exceptions`)

Retrieves exceptions recorded by Laravel Telescope.

#### Parameters
- `id` (string, optional): ID of the specific exception to view details.
- `limit` (integer, optional): Maximum number of exceptions to return. Default: 50.

#### Response Format (List)
Tabular text format.

#### Examples
```json
{
    "name": "telescope_mcp.exceptions",
    "arguments": {
        "limit": 5
    }
}
```

### Other Available Tools

| Category | Tool | Description | Example |
|----------|------|-------------|---------|
| **Debugging** |
| | `telescope_mcp.dumps` | Access variable dumps and debug information | `{"name": "telescope_mcp.dumps", "arguments": {"limit": 10}}` |
| | `telescope_mcp.exceptions` | View application exceptions | `{"name": "telescope_mcp.exceptions", "arguments": {"limit": 5}}` |
| **Database** |
| | `telescope_mcp.queries` | Monitor database queries | `{"name": "telescope_mcp.queries", "arguments": {"slow": true}}` |
| | `telescope_mcp.models` | Track Eloquent model operations | `{"name": "telescope_mcp.models", "arguments": {"action": "created"}}` |
| **Cache & Storage** |
| | `telescope_mcp.cache` | Monitor cache operations | `{"name": "telescope_mcp.cache", "arguments": {"operation": "hit"}}` |
| | `telescope_mcp.redis` | Track Redis commands | `{"name": "telescope_mcp.redis", "arguments": {"command": "GET"}}` |
| **Queue & Jobs** |
| | `telescope_mcp.jobs` | Monitor queued jobs | `{"name": "telescope_mcp.jobs", "arguments": {"status": "failed"}}` |
| | `telescope_mcp.batches` | Track job batches | `{"name": "telescope_mcp.batches", "arguments": {"status": "finished"}}` |
| **HTTP & Network** |
| | `telescope_mcp.requests` | Monitor incoming HTTP requests | `{"name": "telescope_mcp.requests", "arguments": {"method": "POST"}}` |
| | `telescope_mcp.http-client` | Track outgoing HTTP requests | `{"name": "telescope_mcp.http-client", "arguments": {"status": 200}}` |
| **Events & Gates** |
| | `telescope_mcp.events` | Monitor application events | `{"name": "telescope_mcp.events", "arguments": {"limit": 10}}` |
| | `telescope_mcp.gates` | Track authorization gates | `{"name": "telescope_mcp.gates", "arguments": {"ability": "view"}}` |
| **Notifications** |
| | `telescope_mcp.mail` | Monitor email messages | `{"name": "telescope_mcp.mail", "arguments": {"limit": 5}}` |
| | `telescope_mcp.notifications` | Track notifications | `{"name": "telescope_mcp.notifications", "arguments": {"channel": "mail"}}` |
| **System** |
| | `telescope_mcp.commands` | Monitor Artisan commands | `{"name": "telescope_mcp.commands", "arguments": {"status": "success"}}` |
| | `telescope_mcp.schedule` | Track scheduled tasks | `{"name": "telescope_mcp.schedule", "arguments": {"limit": 10}}` |
| | `telescope_mcp.views` | Monitor view renderings | `{"name": "telescope_mcp.views", "arguments": {"limit": 5}}` |
| **Maintenance** |
| | `telescope_mcp.prune` | Clean up old Telescope entries | `{"name": "telescope_mcp.prune", "arguments": {"hours": 48}}` |

Detailed documentation for each tool, including all parameters and response formats, can be obtained by calling the `mcp.manifest` (or `tools/list`) method on the MCP server endpoint.

## MCP Manifest

The MCP manifest provides detailed information about all available tools and their capabilities. You can obtain it by calling the `mcp.manifest` method on your MCP server endpoint.

### Obtaining the Manifest

There are three ways to get the manifest:

1. **HTTP GET Request**:
   ```bash
   curl http://your-app.test/mcp/manifest.json
   ```

2. **JSON-RPC Request**:
   ```json
   {
       "jsonrpc": "2.0",
       "method": "mcp.manifest",
       "params": {},
       "id": 1
   }
   ```

3. **Artisan Command**:
   ```bash
   php artisan telescope:mcp-connect
   ```

### Example Manifest Response

```json
{
    "jsonrpc": "2.0",
    "result": {
        "protocolVersion": "2024-11-05",
        "serverInfo": {
            "name": "Laravel Telescope MCP",
            "version": "1.0.0",
            "description": "MCP Server for Laravel Telescope"
        },
        "capabilities": {
            "tools": [
                {
                    "name": "telescope_mcp.logs",
                    "title": "Telescope Logs",
                    "description": "Access application logs from Laravel Telescope",
                    "schema": {
                        "type": "object",
                        "properties": {
                            "id": {
                                "type": "string",
                                "description": "ID of the specific log entry to view details"
                            },
                            "limit": {
                                "type": "integer",
                                "default": 50,
                                "description": "Maximum number of log entries to return"
                            },
                            "level": {
                                "type": "string",
                                "enum": ["debug", "info", "notice", "warning", "error", "critical", "alert", "emergency"],
                                "description": "Filter by log level"
                            },
                            "message": {
                                "type": "string",
                                "description": "Filter by log message content"
                            }
                        }
                    }
                },
                // ... other tools ...
            ]
        }
    },
    "id": 1
}
```

### Using the Manifest in Your MCP Client

The manifest provides all the information needed to integrate with the MCP server:

1. **Tool Discovery**: The `capabilities.tools` array lists all available tools with their names, descriptions, and parameter schemas.

2. **Parameter Validation**: Each tool's `schema` property defines the expected parameters and their types, making it easy to validate requests before sending them.

3. **Protocol Version**: The `protocolVersion` field indicates the MCP protocol version supported by the server.

4. **Server Information**: The `serverInfo` section provides metadata about the MCP server implementation.

## Installation

1.  Add the package to your Laravel project via Composer:
    ```bash
    composer require lucianotonet/laravel-telescope-mcp
    ```

2.  Publish the configuration file:
    ```bash
    php artisan vendor:publish --provider="LucianoTonet\\TelescopeMcp\\TelescopeMcpServiceProvider" --tag="telescope-mcp-config"
    ```
    This will create a `config/telescope-mcp.php` file.

3.  (Optional) Publish the Telescope MCP assets (if any, e.g., for a custom UI for connection):
    ```bash
    php artisan vendor:publish --provider="LucianoTonet\\TelescopeMcp\\TelescopeMcpServiceProvider" --tag="telescope-mcp-assets"
    ```

## Configuration

The main configuration is done in `config/telescope-mcp.php`:

```php
return [
    // Enable or disable the MCP server endpoint.
    'enabled' => env('TELESCOPE_MCP_ENABLED', true),

    // The URI path where the MCP server will be accessible.
    // Example: 'mcp', 'telescope-mcp', etc.
    'path' => env('TELESCOPE_MCP_PATH', 'mcp'),

    // Middleware group(s) to apply to the MCP server routes.
    // Useful for authentication or other request processing.
    // Example: ['web', 'auth'] or 'api'.
    'middleware' => [
        // Add any middleware necessary to protect your MCP endpoint
        // e.g., \App\Http\Middleware\VerifyMcpAccess::class,
    ],

    // Logging configuration for MCP server activities.
    'logging' => [
        'enabled' => env('TELESCOPE_MCP_LOGGING_ENABLED', true),
        // Specific log channel to use (null for default Laravel channel).
        'channel' => env('TELESCOPE_MCP_LOG_CHANNEL', null),
        // Log level.
        'level' => env('TELESCOPE_MCP_LOG_LEVEL', 'info'),
    ],

    // Configuration for the mcp-connect command
    'connect_command' => [
        // Default URL for the MCP server if not provided to the command.
        // This will be appended with the 'path' defined above.
        // Example: If app_url is 'http://localhost:8000' and path is 'mcp',
        // the command will suggest 'http://localhost:8000/mcp'.
        'app_url' => env('APP_URL', 'http://localhost'),
    ],
];
```

Ensure your `.env` file has `APP_URL` set correctly if you plan to use the `telescope:mcp-connect` command with its default URL.

### Connecting Your MCP Client (e.g., Cursor)

To connect your MCP-compatible client:

1.  **Start your Laravel application.**
2.  **Identify your MCP server endpoint URL.** This will be `your_app_url/your_mcp_path` (e.g., `http://localhost:8000/mcp` if `APP_URL=http://localhost:8000` and `telescope-mcp.path` is `'mcp'`).
3.  **Use the Artisan Command (Recommended):**
    Run the following command in your terminal:
    ```bash
    php artisan telescope:mcp-connect
    ```
    This command will display the MCP server URL and the JSON object required to configure your MCP client (like Cursor). It will use the `APP_URL` from your `.env` file and the `path` from `config/telescope-mcp.php`. You can also provide a specific URL:
    ```bash
    php artisan telescope:mcp-connect http://my-custom-app-url.com/custom-mcp-path
    ```
4.  **Manual Configuration:**
    In your MCP client, add a new Model Context Provider connection with the following details:
    *   **URL**: Your MCP server endpoint URL.
    *   **Auth**: Configure any authentication headers if your MCP endpoint is protected by middleware.
    *   **Model Provider Spec (JSON)**: You can get this by calling `mcp.manifest`

## Quickstart

Get up and running with Laravel Telescope MCP in minutes:

### 1. Installation & Setup

```bash
# Install via Composer
composer require lucianotonet/laravel-telescope-mcp

# Publish configuration
php artisan vendor:publish --provider="LucianoTonet\\TelescopeMcp\\TelescopeMcpServiceProvider" --tag="telescope-mcp-config"
```

### 2. Configure Your Environment

Add these variables to your `.env` file:
```env
TELESCOPE_ENABLED=true
TELESCOPE_MCP_ENABLED=true
TELESCOPE_MCP_PATH=mcp
```

### 3. Connect with Cursor

1. Open Cursor
2. Press `Cmd/Ctrl + Shift + P` to open the command palette
3. Type "Connect to MCP Server" and press Enter
4. Run this command in your Laravel project:
   ```bash
   php artisan telescope:mcp-connect
   ```
5. Copy the connection details from the command output
6. Paste them into Cursor's MCP connection dialog

### 4. Start Monitoring

Here are some common tasks you can try:

1. **View Recent Logs**
   ```json
   {
       "name": "telescope_mcp.logs",
       "arguments": {
           "limit": 5
       }
   }
   ```

2. **Monitor Failed Jobs**
   ```json
   {
       "name": "telescope_mcp.jobs",
       "arguments": {
           "status": "failed"
       }
   }
   ```

3. **Check Slow Queries**
   ```json
   {
       "name": "telescope_mcp.queries",
       "arguments": {
           "slow": true
       }
   }
   ```

### Troubleshooting

Common issues and solutions:

1. **Connection Refused**
   - Ensure your Laravel application is running
   - Check if the MCP path is correct in `config/telescope-mcp.php`
   - Verify your firewall/network settings

2. **Authentication Failed**
   - If you've configured middleware, ensure your credentials are correct
   - Check the middleware configuration in `config/telescope-mcp.php`

3. **No Data Showing**
   - Confirm Telescope is enabled (`TELESCOPE_ENABLED=true`)
   - Check if you have any data in your Telescope tables
   - Try running some application tasks to generate telemetry

4. **Slow Response Times**
   - Consider adjusting the `limit` parameter in your queries
   - Check your database connection and performance
   - Review your Telescope data retention settings

For more detailed examples and advanced usage, see the [Available Tools](#available-tools) section.