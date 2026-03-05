---
name: telescope-mcp-debugging
description: Analyze and debug Laravel applications using Telescope MCP telemetry data including HTTP requests, exceptions, database queries, logs, jobs, mail, notifications, cache operations, redis commands, scheduled tasks, model events, gates, views, and dumps. Use when debugging application issues, investigating slow queries, inspecting HTTP traffic, reviewing error logs, or analyzing any Telescope-recorded data via MCP protocol tools.
---

# Telescope MCP Debugging

Laravel Telescope MCP exposes real-time application telemetry through 19 MCP tools. Use these tools to inspect, correlate, and debug your Laravel application without touching log files or the Telescope UI.

## Prerequisites

This skill requires the Laravel Telescope MCP server to be running and connected.
If tools return errors, verify:

1. Telescope MCP is enabled: `TELESCOPE_MCP_ENABLED=true` in `.env`
2. The MCP server is registered in your agent's config (run `php artisan telescope-mcp:install` to auto-configure)
3. For HTTP mode: the endpoint is accessible at `http://127.0.0.1:8000/{path}` (default path: `telescope-mcp`)
4. For stdio mode: the server starts via `php artisan telescope-mcp:server`

## Available MCP Tools

### Observability Tools

| Tool | Description | Key Parameters |
|------|-------------|----------------|
| `requests` | HTTP requests recorded by Telescope | `method`, `status`, `path`, `include_related`, `include_queries` |
| `logs` | Application log entries | `level` (debug/info/notice/warning/error/critical/alert/emergency), `message` |
| `exceptions` | Application exceptions with stack traces | `request_id` |
| `queries` | Database queries with timing | `slow` (>100ms filter), `path`, `request_id` |
| `jobs` | Queued job executions | `status` (pending/processed/failed), `queue` |
| `cache` | Cache operations | `operation` (hit/miss/set/forget), `key` |
| `commands` | Artisan command executions | `command`, `status` (success/error) |
| `events` | Application events and listeners | `name`, `request_id` |
| `mail` | Sent emails | `to`, `subject` |
| `notifications` | Sent notifications | `channel`, `status` (sent/failed) |
| `models` | Eloquent model operations | `action` (created/updated/deleted), `model` |
| `gates` | Authorization gate checks | `ability`, `result` (allowed/denied) |
| `http-client` | Outgoing HTTP requests (Laravel HTTP client) | `method`, `status`, `url` |
| `redis` | Redis command executions | `command` |
| `schedule` | Scheduled task executions | — |
| `views` | Rendered Blade views | `request_id` |
| `dumps` | Variable dumps (dd/dump) | `file`, `line` |
| `batches` | Job batch operations | `status` (pending/processing/finished/failed), `name` |

### Administrative Tools

| Tool | Description | Key Parameters |
|------|-------------|----------------|
| `prune` | Delete old Telescope entries | `hours` (default: 24) — **destructive** |

### Common Parameters (all tools)

- `id` — Retrieve detailed entry by its Telescope ID
- `limit` — Maximum results to return (default: 50, max: 100)
- `request_id` — Filter entries belonging to a specific HTTP request (available on: logs, exceptions, queries, cache, events, models, views)

## Common Debugging Workflows

### 1. Investigate HTTP 500 Errors

```
Step 1: Get recent exceptions
→ exceptions (limit: 5)

Step 2: Get the full exception detail
→ exceptions (id: {exception_id})
  — Shows: class, message, file, line, full stack trace, context

Step 3: Find the request that caused it
→ requests (id: {request_id from exception})
  — Shows: method, URI, status, duration, middleware, related entries

Step 4: Check queries executed during that request
→ queries (request_id: {request_id})
  — Shows: SQL, bindings, duration, source file/line
```

### 2. Debug Slow Database Queries

```
Step 1: Find slow queries (>100ms)
→ queries (slow: true, limit: 20)

Step 2: Identify the source code location
→ queries (id: {query_id})
  — Shows: full SQL, bindings, backtrace with file/line/function

Step 3: Find the request that triggered it
→ requests (id: {request_id from query})
  — Shows: endpoint, total duration, all related entries
```

### 3. Full Request Lifecycle Analysis

```
Step 1: Find the request
→ requests (path: "/api/users", limit: 1)
  or
→ requests (status: 500, limit: 5)

Step 2: Get complete request details with all related entries
→ requests (id: {request_id}, include_related: true, include_queries: true)
  — Shows: request details + summary of all related logs, queries, exceptions, etc.

Step 3: Drill into specific areas
→ logs (request_id: {request_id})
→ queries (request_id: {request_id})
→ events (request_id: {request_id})
→ models (request_id: {request_id})
```

### 4. Debug Failed Jobs

```
Step 1: List failed jobs
→ jobs (status: "failed", limit: 10)

Step 2: Get failure details
→ jobs (id: {job_id})
  — Shows: job class, queue, attempts, exception message and trace

Step 3: Check related entries for that job's batch
→ logs (request_id: {batch_id})
→ queries (request_id: {batch_id})
```

### 5. Audit Cache Performance

```
Step 1: Check cache misses
→ cache (operation: "miss", limit: 20)

Step 2: Compare with hits
→ cache (operation: "hit", limit: 20)

Step 3: Check cache operations for a specific request
→ cache (request_id: {request_id})
  — Shows: all cache operations during that request lifecycle
```

## Configuration

### Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `TELESCOPE_MCP_ENABLED` | `true` | Enable/disable the MCP server |
| `TELESCOPE_MCP_PATH` | `telescope-mcp` | URL path for HTTP mode |
| `TELESCOPE_MCP_ROUTES_ENABLED` | `true` | Enable/disable web routes (HTTP mode) |
| `TELESCOPE_MCP_LOGGING_ENABLED` | `true` | Enable/disable internal logging |
| `TELESCOPE_MCP_LOG_CHANNEL` | `stack` | Laravel log channel to use |

### Config File

Publish with: `php artisan vendor:publish --tag=telescope-mcp-config`

Located at: `config/telescope-mcp.php`

## Tips

- **Correlation is key**: Use `request_id` to trace everything that happened during a single HTTP request — queries, logs, exceptions, cache hits, model operations, and more.
- **Start broad, then narrow**: List recent entries first, then use `id` to drill into details.
- **Slow query threshold**: The `queries` tool flags queries over 100ms as slow. Use `slow: true` to filter only these.
- **Batch awareness**: Many tools support batch-level queries. A request and all its related entries share a `batch_id`.
- **Prune with care**: The `prune` tool permanently deletes entries. Default is 24 hours. Use with caution.

## Reference

For detailed parameter documentation of each tool including types, defaults, enums, and response formats, see `references/TOOLS.md`.
