# Telescope MCP Debugging Tools

This Laravel application has **Laravel Telescope MCP** installed, providing AI-powered access to structured debugging data through 20 specialized tools.

## Quick Reference

When debugging, use these tools based on the scenario:

| Scenario | Tool | Key Parameters |
|----------|------|----------------|
| Application errors | `telescope_exceptions` | `id`, `limit` |
| Slow performance | `telescope_queries` | `slow: true` |
| API/HTTP issues | `telescope_requests` | `method`, `status`, `path` |
| Job failures | `telescope_jobs` | `failed: true` |
| Cache problems | `telescope_cache` | `limit` |
| Log analysis | `telescope_logs` | `level` |

## Available Tools

### Core Debugging
- `telescope_exceptions` - Exception tracking with stack traces
- `telescope_queries` - Database query analysis (supports `slow` filter)
- `telescope_requests` - HTTP request/response logging
- `telescope_logs` - Application log entries

### Queue & Jobs
- `telescope_jobs` - Job execution and failures
- `telescope_batches` - Batch job processing

### Cache & Data
- `telescope_cache` - Cache operations (hits/misses)
- `telescope_redis` - Redis operations
- `telescope_models` - Eloquent model operations

### Communication
- `telescope_mail` - Email tracking
- `telescope_notifications` - Notification dispatching

### System
- `telescope_commands` - Artisan command execution
- `telescope_schedule` - Scheduled task monitoring
- `telescope_events` - Event dispatching
- `telescope_gates` - Authorization checks
- `telescope_views` - View rendering
- `telescope_dumps` - Debug dumps (dump/dd)
- `telescope_http_client` - Outgoing HTTP requests

### Maintenance
- `telescope_prune` - Clean up old entries

## Best Practices

1. **Start broad, then narrow**: List entries first, then get specific details with `id`
2. **Correlate data**: Cross-reference requests with queries and exceptions
3. **Use filters**: Leverage `slow`, `failed`, `status`, `level` parameters
4. **Check context**: Each entry includes timestamps and full context data
