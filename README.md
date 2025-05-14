# Laravel Telescope MCP Tools

This package provides MCP (Monitoring Control Panel) tools for Laravel Telescope, allowing you to access Telescope data through a standardized API.

## Available Tools

### 1. Telescope Logs (`mcp_telescope_logs`)

Retrieves application logs from Laravel Telescope. The tool provides access to log messages with their levels, timestamps, and context information.

#### Parameters

- `limit` (integer, optional)
  - Maximum number of logs to return
  - Default: 100

- `level` (string, optional)
  - Filter logs by level (case insensitive)
  - Allowed values: debug, info, notice, warning, error, critical, alert, emergency

#### Response Format

```json
{
    "logs": [
        {
            "id": "sequence_number",
            "timestamp": "ISO8601_timestamp",
            "level": "log_level",
            "message": "log_message",
            "context": {}
        }
    ],
    "total": "total_logs_count"
}
```

#### Examples

1. Get last 10 error logs:
```json
{
    "name": "mcp_telescope_logs",
    "arguments": {
        "level": "error",
        "limit": 10
    }
}
```

2. Get all debug logs (up to 100):
```json
{
    "name": "mcp_telescope_logs",
    "arguments": {
        "level": "debug"
    }
}
```

### 2. Telescope Requests (`mcp_telescope_requests`)

Retrieves HTTP requests recorded by Laravel Telescope. Shows detailed information about each request including method, URL, status code, and duration.

#### Parameters

- `limit` (integer, optional)
  - Maximum number of requests to return
  - Default: 50
  - Maximum allowed: 100

- `tag` (string, optional)
  - Filter requests by tag
  - Default: null

#### Response Format

The response is formatted as a table with the following columns:
- ID: Unique identifier for the request
- URL: The requested URL path
- Method: HTTP method (GET, POST, etc.)
- Status: HTTP status code
- Duration: Request duration in milliseconds
- Created At: Timestamp when the request was made

Example output:
```
HTTP Requests:

ID    URL                            Method   Status   Duration   Created At               
------------------------------------------------------------------------------------------
1234  /api/users                     GET      200      45.20ms    2024-03-14 10:30:45
5678  /api/posts                     POST     201      123.45ms   2024-03-14 10:31:12
```

#### Examples

1. Get last 5 requests:
```json
{
    "name": "mcp_telescope_requests",
    "arguments": {
        "limit": 5
    }
}
```

2. Get requests with specific tag:
```json
{
    "name": "mcp_telescope_requests",
    "arguments": {
        "tag": "api",
        "limit": 10
    }
}
```

## Installation

1. Add the package to your Laravel project:
```bash
composer require lucianotonet/laravel-telescope-mcp
```

2. Publish the configuration:
```bash
php artisan vendor:publish --provider="LucianoTonet\TelescopeMcp\TelescopeMcpServiceProvider"
```

## Configuration

The package can be configured through the `config/telescope-mcp.php` file:

```php
return [
    // Enable/disable the MCP tools
    'enabled' => env('TELESCOPE_MCP_ENABLED', true),

    // Path where the MCP endpoint will be available
    'path' => 'mcp',

    // Middleware to apply to MCP routes
    'middleware' => [],

    // Logging configuration
    'logging' => [
        'enabled' => true,
        'channel' => null, // Uses default Laravel log channel if null
        'path' => storage_path('logs/laravel.log'),
        'level' => 'debug',
        'days' => 7
    ]
];
```

## Known Issues

1. Request Tool:
   - The `created_at` field may show as "Unknown" in some cases due to date format handling
   - Tag filtering functionality is currently being improved

2. Logs Tool:
   - Large context arrays are automatically pretty-printed for better readability
   - Some log entries might have empty context arrays if not properly structured

## License

This package is open-sourced software licensed under the MIT license. 