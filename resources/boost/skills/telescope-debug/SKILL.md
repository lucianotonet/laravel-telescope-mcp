---
name: telescope-debug
description: Advanced debugging tools using Laravel Telescope's structured telemetry data. Use when debugging performance issues, investigating exceptions, analyzing slow queries, tracking job failures, understanding request flows, or troubleshooting cache and event problems.
license: MIT
metadata:
  author: lucianotonet
  version: "1.0.0"
---

# Laravel Telescope Debugging Skill

## What This Skill Provides

Access to structured debugging data from Laravel Telescope, giving you deep visibility into your application's runtime behavior through 20 specialized tools.

## When to Activate

Use this skill when:

1. **Debugging Performance Issues**
   - Slow page loads or API responses
   - Database query optimization
   - N+1 query detection
   - Cache efficiency analysis

2. **Investigating Errors**
   - Exception traces with full context
   - Stack traces and error details
   - Request context at time of error

3. **Analyzing Request Flows**
   - HTTP request/response details
   - Headers, payloads, and timing
   - Failed requests investigation

4. **Queue & Job Debugging**
   - Failed job analysis
   - Retry patterns
   - Job execution context

5. **System Monitoring**
   - Event dispatching records
   - Mail and notification logs
   - Command execution history
   - Scheduled task monitoring

## Available Tools

### Core Debugging Tools

#### `telescope_exceptions`
Get recent exceptions with full stack traces and context.

**Parameters:**
- `id` (string, optional): Get details of a specific exception
- `limit` (integer, default: 50): Maximum exceptions to return

**Use when:** Investigating application errors, debugging crashes, analyzing error patterns.

---

#### `telescope_queries`
Analyze database queries with execution time and SQL details.

**Parameters:**
- `id` (string, optional): Get details of a specific query
- `limit` (integer, default: 50): Maximum queries to return
- `slow` (boolean, default: false): Filter only slow queries (>100ms)

**Use when:** Optimizing database performance, finding N+1 queries, analyzing slow operations.

---

#### `telescope_requests`
View HTTP requests with headers, payloads, and response details.

**Parameters:**
- `id` (string, optional): Get details of a specific request
- `limit` (integer, default: 50): Maximum requests to return
- `method` (string, optional): Filter by HTTP method (GET, POST, etc.)
- `status` (integer, optional): Filter by HTTP status code
- `path` (string, optional): Filter by request path

**Use when:** Debugging API issues, analyzing request/response cycles, investigating failed requests.

---

#### `telescope_logs`
Access application log entries with context.

**Parameters:**
- `id` (string, optional): Get details of a specific log entry
- `limit` (integer, default: 50): Maximum logs to return
- `level` (string, optional): Filter by log level (error, warning, info, debug)

**Use when:** Tracing application flow, debugging issues, monitoring application behavior.

---

### Queue & Jobs Tools

#### `telescope_jobs`
Monitor queue job execution and failures.

**Parameters:**
- `id` (string, optional): Get details of a specific job
- `limit` (integer, default: 50): Maximum jobs to return
- `failed` (boolean, optional): Filter only failed jobs

**Use when:** Debugging job failures, analyzing queue performance, investigating retry patterns.

---

#### `telescope_batches`
Track batch job processing.

**Parameters:**
- `id` (string, optional): Get details of a specific batch
- `limit` (integer, default: 50): Maximum batches to return

**Use when:** Monitoring batch operations, debugging batch failures.

---

### Cache & Data Tools

#### `telescope_cache`
Analyze cache operations (hits, misses, writes).

**Parameters:**
- `id` (string, optional): Get details of a specific cache operation
- `limit` (integer, default: 50): Maximum operations to return

**Use when:** Optimizing cache usage, debugging cache misses, analyzing cache patterns.

---

#### `telescope_redis`
Monitor Redis operations.

**Parameters:**
- `id` (string, optional): Get details of a specific Redis operation
- `limit` (integer, default: 50): Maximum operations to return

**Use when:** Debugging Redis connectivity, analyzing Redis performance.

---

#### `telescope_models`
Track Eloquent model operations.

**Parameters:**
- `id` (string, optional): Get details of a specific model operation
- `limit` (integer, default: 50): Maximum operations to return

**Use when:** Debugging model events, tracking data changes.

---

### Communication Tools

#### `telescope_mail`
View sent emails with content and recipients.

**Parameters:**
- `id` (string, optional): Get details of a specific email
- `limit` (integer, default: 50): Maximum emails to return

**Use when:** Debugging email delivery, verifying email content.

---

#### `telescope_notifications`
Track notification dispatching.

**Parameters:**
- `id` (string, optional): Get details of a specific notification
- `limit` (integer, default: 50): Maximum notifications to return

**Use when:** Debugging notification delivery, analyzing notification patterns.

---

### System Tools

#### `telescope_commands`
Monitor Artisan command execution.

**Parameters:**
- `id` (string, optional): Get details of a specific command
- `limit` (integer, default: 50): Maximum commands to return

**Use when:** Debugging CLI commands, monitoring scheduled tasks.

---

#### `telescope_schedule`
Track scheduled task execution.

**Parameters:**
- `id` (string, optional): Get details of a specific scheduled task
- `limit` (integer, default: 50): Maximum tasks to return

**Use when:** Debugging cron jobs, monitoring scheduled operations.

---

#### `telescope_events`
View dispatched events and listeners.

**Parameters:**
- `id` (string, optional): Get details of a specific event
- `limit` (integer, default: 50): Maximum events to return

**Use when:** Debugging event-driven logic, tracing event propagation.

---

#### `telescope_gates`
Monitor authorization gate checks.

**Parameters:**
- `id` (string, optional): Get details of a specific gate check
- `limit` (integer, default: 50): Maximum checks to return

**Use when:** Debugging authorization issues, analyzing access patterns.

---

#### `telescope_views`
Track view rendering.

**Parameters:**
- `id` (string, optional): Get details of a specific view
- `limit` (integer, default: 50): Maximum views to return

**Use when:** Debugging view rendering, analyzing template usage.

---

#### `telescope_dumps`
Access debug dumps (dump(), dd()).

**Parameters:**
- `id` (string, optional): Get details of a specific dump
- `limit` (integer, default: 50): Maximum dumps to return

**Use when:** Reviewing debug output, analyzing dumped data.

---

#### `telescope_http_client`
Monitor outgoing HTTP requests.

**Parameters:**
- `id` (string, optional): Get details of a specific HTTP client request
- `limit` (integer, default: 50): Maximum requests to return

**Use when:** Debugging API integrations, analyzing external service calls.

---

### Maintenance Tools

#### `telescope_prune`
Clean up old Telescope entries.

**Parameters:**
- `hours` (integer, default: 24): Delete entries older than this many hours

**Use when:** Managing database size, cleaning up development data.

---

## Debugging Workflow Examples

### Investigating a Slow Page

1. Use `telescope_requests` to find the slow request
2. Get request details with the `id` parameter
3. Use `telescope_queries` with `slow: true` to find slow queries
4. Analyze query SQL and consider adding indexes

### Debugging a Failed Job

1. Use `telescope_jobs` with `failed: true` to find failures
2. Get job details with the `id` parameter
3. Check `telescope_exceptions` for related errors
4. Review `telescope_logs` for additional context

### Analyzing Cache Efficiency

1. Use `telescope_cache` to see recent operations
2. Count hits vs misses
3. Identify frequently missed keys
4. Optimize cache warming or TTL settings
