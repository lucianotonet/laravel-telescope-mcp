# Laravel Telescope MCP Tools

This package provides MCP (Model Context Protocol) tools for Laravel Telescope, enabling seamless access to Telescope data through a standardized API. With these tools, developers can monitor and manage various aspects of their Laravel applications, such as HTTP requests, logs, and other telemetry data captured by Telescope, via the MCP protocol, directly on your Cursor, Claude, Windsurf, etc.

## Available Tools

This MCP server provides access to a variety of Laravel Telescope data points. Here are some of the main tools:

### 1. Telescope Logs (`mcp_Laravel_Telescope_MCP_logs`)

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
    "name": "mcp_Laravel_Telescope_MCP_logs",
    "arguments": {
        "level": "error",
        "limit": 10
    }
}
```

### 2. Telescope Requests (`mcp_Laravel_Telescope_MCP_requests`)

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
    "name": "mcp_Laravel_Telescope_MCP_requests",
    "arguments": {
        "limit": 5,
        "method": "POST"
    }
}
```

### 3. Telescope Queries (`mcp_Laravel_Telescope_MCP_queries`)

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
    "name": "mcp_Laravel_Telescope_MCP_queries",
    "arguments": {
        "limit": 10,
        "slow": true
    }
}
```

### 4. Telescope Exceptions (`mcp_Laravel_Telescope_MCP_exceptions`)

Retrieves exceptions recorded by Laravel Telescope.

#### Parameters
- `id` (string, optional): ID of the specific exception to view details.
- `limit` (integer, optional): Maximum number of exceptions to return. Default: 50.

#### Response Format (List)
Tabular text format.

#### Examples
```json
{
    "name": "mcp_Laravel_Telescope_MCP_exceptions",
    "arguments": {
        "limit": 5
    }
}
```

### Other Available Tools

This package also includes tools for interacting with:
- Batches (`mcp_Laravel_Telescope_MCP_batches`)
- Cache (`mcp_Laravel_Telescope_MCP_cache`)
- Commands (`mcp_Laravel_Telescope_MCP_commands`)
- Dumps (`mcp_Laravel_Telescope_MCP_dumps`)
- Events (`mcp_Laravel_Telescope_MCP_events`)
- Gates (`mcp_Laravel_Telescope_MCP_gates`)
- HTTP Client Requests (`mcp_Laravel_Telescope_MCP_http-client`)
- Jobs (`mcp_Laravel_Telescope_MCP_jobs`)
- Mail (`mcp_Laravel_Telescope_MCP_mail`)
- Models (`mcp_Laravel_Telescope_MCP_models`)
- Notifications (`mcp_Laravel_Telescope_MCP_notifications`)
- Pruning Telescope Entries (`mcp_Laravel_Telescope_MCP_prune`)
- Redis Commands (`mcp_Laravel_Telescope_MCP_redis`)
- Scheduled Tasks (`mcp_Laravel_Telescope_MCP_schedule`)
- Views (`mcp_Laravel_Telescope_MCP_views`)

Detailed documentation for each tool, including all parameters and response formats, can be obtained by calling the `mcp.manifest` (or `tools/list`) method on the MCP server endpoint.

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
    *   **Model Provider Spec (JSON)**: You can get this by calling `mcp.manifest` on your endpoint, or use the output from the `telescope:mcp-connect` command.

## Known Issues

1.  **Date Formatting**:
    *   For `RequestsTool` and `QueriesTool`, the `Created At` field in list views may sometimes display as "Unknown". Detailed views usually show the correct date if available from Telescope. This is an area for future improvement in date parsing and fallback.
2.  **Tag Filtering in `RequestsTool`**:
    *   The `tag` parameter in `mcp_Laravel_Telescope_MCP_requests` might not be fully implemented or may have limitations.

## Contributing

Contributions are welcome! Here's how you can help:

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

### Development Setup

1. Clone your fork:
```bash
git clone https://github.com/YOUR_USERNAME/laravel-telescope-mcp.git
cd laravel-telescope-mcp
```

2. Install dependencies:
```bash
composer install
```

3. Run tests:
```bash
vendor/bin/phpunit
```

### Code Style

This project follows the PSR-12 coding standard. To ensure your code follows the standard:

1. Install PHP CS Fixer:
```bash
composer require --dev friendsofphp/php-cs-fixer
```

2. Run the fixer:
```bash
vendor/bin/php-cs-fixer fix
```

### Reporting Issues

If you find a bug or have a suggestion for improvement:

1. Check if the issue already exists in the [GitHub Issues](https://github.com/lucianotonet/laravel-telescope-mcp/issues)
2. If not, create a new issue with:
   - A clear title and description
   - As much relevant information as possible
   - A code sample or test case demonstrating the issue

## License

This package is open-sourced software licensed under the [MIT license](LICENSE). 