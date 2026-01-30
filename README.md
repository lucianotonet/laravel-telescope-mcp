# Laravel Telescope MCP

**Laravel Boost Telescope Plugin - AI-Powered Debugging**

Give AI superpowers to debug your Laravel applications with access to Telescope's rich, structured debugging data.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/lucianotonet/laravel-telescope-mcp.svg)](https://packagist.org/packages/lucianotonet/laravel-telescope-mcp)
[![Total Downloads](https://img.shields.io/packagist/dt/lucianotonet/laravel-telescope-mcp.svg)](https://packagist.org/packages/lucianotonet/laravel-telescope-mcp)
[![License](https://img.shields.io/packagist/l/lucianotonet/laravel-telescope-mcp.svg)](https://packagist.org/packages/lucianotonet/laravel-telescope-mcp)

## The Difference

While Laravel Boost reads log files, this plugin gives your AI assistant access to Telescope's rich, structured debugging data.

| Laravel Boost Alone | + Telescope Plugin |
|---------------------|-------------------|
| "Error in log file" | "Query on `orders` table taking 2s due to missing index on `user_id`" |
| Generic stack trace | Full request context + query history + event timeline |
| "Job failed" | "Job failed on 3rd retry with payload X after queries Y and Z" |

## Requirements

- PHP 8.1+
- Laravel 10, 11, or 12
- Laravel Telescope 4.0+

## Installation

```bash
composer require lucianotonet/laravel-telescope-mcp --dev
```

### Integration with Laravel Boost

After installation, run:

```bash
php artisan boost:install
```

The Telescope debugging tools will be automatically discovered and available to your AI assistant through the Boost MCP server.

## Configuration

### Environment Variables

```env
# Enable/disable the package
TELESCOPE_MCP_ENABLED=true

# Logging
TELESCOPE_MCP_LOGGING_ENABLED=true
TELESCOPE_MCP_LOG_CHANNEL=stack
```

### Configuration File

Publish and customize the configuration:

```bash
php artisan vendor:publish --tag=telescope-mcp-config
```

## Available Tools

The package provides 20 specialized debugging tools:

### Core Debugging
- `telescope_exceptions` - Application exceptions with stack traces
- `telescope_queries` - Database queries with timing and bindings
- `telescope_requests` - HTTP requests with headers and payloads
- `telescope_logs` - Application logs with context

### Queue & Jobs
- `telescope_jobs` - Queue job execution and failures
- `telescope_batches` - Batch job processing

### Cache & Data
- `telescope_cache` - Cache hits, misses, and writes
- `telescope_redis` - Redis operations
- `telescope_models` - Eloquent model operations

### Communication
- `telescope_mail` - Sent emails
- `telescope_notifications` - Dispatched notifications

### System
- `telescope_commands` - Artisan command execution
- `telescope_schedule` - Scheduled task execution
- `telescope_events` - Event dispatching
- `telescope_gates` - Authorization gate checks
- `telescope_views` - View rendering
- `telescope_dumps` - Debug dumps (dump(), dd())
- `telescope_http_client` - Outgoing HTTP requests

### Maintenance
- `telescope_prune` - Clean up old entries

## Usage Examples

### Debugging Slow Queries

Ask your AI assistant:

> "Find slow queries in my application"

The AI will use `telescope_queries` with `slow: true` to identify problematic queries.

### Investigating Exceptions

> "What exceptions occurred in the last hour?"

The AI will use `telescope_exceptions` to retrieve and analyze recent errors.

### Analyzing Request Flow

> "Show me the details of the last failed request"

The AI will use `telescope_requests` filtered by status code to find and analyze the failure.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for recent changes.

## Contributing

Contributions are welcome! Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover a security vulnerability, please email tonetlds@gmail.com instead of using the issue tracker.

## Credits

- [Luciano Tonet](https://github.com/lucianotonet)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
