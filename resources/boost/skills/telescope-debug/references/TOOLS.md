# Telescope MCP Tools Reference

Complete technical reference for all 20 Telescope MCP tools.

## Tool Index

1. [telescope_requests](#telescope_requests) - HTTP requests
2. [telescope_logs](#telescope_logs) - Application logs
3. [telescope_exceptions](#telescope_exceptions) - Exceptions and errors
4. [telescope_queries](#telescope_queries) - Database queries
5. [telescope_jobs](#telescope_jobs) - Queue jobs
6. [telescope_batches](#telescope_batches) - Batch jobs
7. [telescope_cache](#telescope_cache) - Cache operations
8. [telescope_commands](#telescope_commands) - Artisan commands
9. [telescope_schedule](#telescope_schedule) - Scheduled tasks
10. [telescope_events](#telescope_events) - Event dispatching
11. [telescope_gates](#telescope_gates) - Authorization gates
12. [telescope_mail](#telescope_mail) - Email sending
13. [telescope_notifications](#telescope_notifications) - Notifications
14. [telescope_models](#telescope_models) - Model operations
15. [telescope_views](#telescope_views) - View rendering
16. [telescope_redis](#telescope_redis) - Redis operations
17. [telescope_dumps](#telescope_dumps) - Debug dumps
18. [telescope_http_client](#telescope_http_client) - HTTP client calls
19. [telescope_prune](#telescope_prune) - Data cleanup

---

## telescope_requests

Lists and analyzes HTTP requests recorded by Telescope.

### Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `id` | string | No | - | Get details of specific request |
| `limit` | integer | No | 50 | Maximum requests to return (max: 100) |
| `method` | string | No | - | Filter by HTTP method (GET, POST, PUT, DELETE, etc.) |
| `status` | integer | No | - | Filter by HTTP status code (200, 404, 500, etc.) |
| `path` | string | No | - | Filter by request path |

### Response Fields

**List Response:**
- `id`: Entry identifier
- `method`: HTTP method
- `uri`: Request URI
- `status`: Response status code
- `duration`: Request duration in ms
- `created_at`: Timestamp

**Detail Response (with id):**
- All list fields plus:
- `headers`: Request headers
- `payload`: Request body/params
- `response_headers`: Response headers
- `response`: Response body (truncated if large)

### Examples

```json
// List last 10 POST requests
{"limit": 10, "method": "POST"}

// Get all 500 errors
{"status": 500}

// Get specific request details
{"id": "abc123"}
```

---

## telescope_logs

Access application log entries with context.

### Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `id` | string | No | - | Get details of specific log entry |
| `limit` | integer | No | 50 | Maximum logs to return (max: 100) |
| `level` | string | No | - | Filter by level: emergency, alert, critical, error, warning, notice, info, debug |

### Response Fields

- `id`: Entry identifier
- `level`: Log level
- `message`: Log message
- `context`: Additional context data
- `created_at`: Timestamp

---

## telescope_exceptions

Get recent exceptions with full stack traces.

### Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `id` | string | No | - | Get details of specific exception |
| `limit` | integer | No | 50 | Maximum exceptions to return (max: 100) |

### Response Fields

**List Response:**
- `id`: Entry identifier
- `class`: Exception class name
- `message`: Exception message
- `file`: File where exception occurred
- `line`: Line number
- `occurred_at`: Timestamp

**Detail Response (with id):**
- All list fields plus:
- `trace`: Full stack trace array
- `context`: Request/application context

---

## telescope_queries

Analyze database queries with execution time.

### Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `id` | string | No | - | Get details of specific query |
| `limit` | integer | No | 50 | Maximum queries to return (max: 100) |
| `slow` | boolean | No | false | Filter only slow queries (>100ms) |

### Response Fields

**List Response:**
- `id`: Entry identifier
- `sql`: SQL query (truncated in list)
- `duration`: Execution time in ms
- `connection`: Database connection name
- `created_at`: Timestamp

**Detail Response (with id):**
- All list fields plus:
- `sql`: Full SQL query
- `bindings`: Query parameter bindings

---

## telescope_jobs

Monitor queue job execution and failures.

### Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `id` | string | No | - | Get details of specific job |
| `limit` | integer | No | 50 | Maximum jobs to return (max: 100) |
| `failed` | boolean | No | - | Filter only failed jobs |

### Response Fields

- `id`: Entry identifier
- `name`: Job class name
- `queue`: Queue name
- `connection`: Queue connection
- `status`: Job status (pending, completed, failed)
- `attempts`: Number of attempts
- `exception`: Exception details (if failed)
- `created_at`: Timestamp

---

## telescope_batches

Track batch job processing.

### Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `id` | string | No | - | Get details of specific batch |
| `limit` | integer | No | 50 | Maximum batches to return (max: 100) |

### Response Fields

- `id`: Entry identifier
- `name`: Batch name
- `total_jobs`: Total jobs in batch
- `pending_jobs`: Jobs still pending
- `failed_jobs`: Jobs that failed
- `created_at`: Timestamp

---

## telescope_cache

Analyze cache operations.

### Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `id` | string | No | - | Get details of specific cache operation |
| `limit` | integer | No | 50 | Maximum operations to return (max: 100) |

### Response Fields

- `id`: Entry identifier
- `type`: Operation type (hit, miss, put, forget)
- `key`: Cache key
- `value`: Cached value (for puts)
- `expiration`: TTL in seconds
- `created_at`: Timestamp

---

## telescope_commands

Monitor Artisan command execution.

### Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `id` | string | No | - | Get details of specific command |
| `limit` | integer | No | 50 | Maximum commands to return (max: 100) |

### Response Fields

- `id`: Entry identifier
- `command`: Command name
- `arguments`: Command arguments
- `options`: Command options
- `exit_code`: Exit code
- `output`: Command output
- `created_at`: Timestamp

---

## telescope_schedule

Track scheduled task execution.

### Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `id` | string | No | - | Get details of specific scheduled task |
| `limit` | integer | No | 50 | Maximum tasks to return (max: 100) |

### Response Fields

- `id`: Entry identifier
- `command`: Scheduled command
- `expression`: Cron expression
- `output`: Task output
- `created_at`: Timestamp

---

## telescope_events

View dispatched events and listeners.

### Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `id` | string | No | - | Get details of specific event |
| `limit` | integer | No | 50 | Maximum events to return (max: 100) |

### Response Fields

- `id`: Entry identifier
- `name`: Event class name
- `payload`: Event data
- `listeners`: Registered listeners
- `broadcast`: Whether event was broadcast
- `created_at`: Timestamp

---

## telescope_gates

Monitor authorization gate checks.

### Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `id` | string | No | - | Get details of specific gate check |
| `limit` | integer | No | 50 | Maximum checks to return (max: 100) |

### Response Fields

- `id`: Entry identifier
- `ability`: Gate ability checked
- `result`: Authorization result (allowed/denied)
- `arguments`: Gate arguments
- `created_at`: Timestamp

---

## telescope_mail

View sent emails.

### Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `id` | string | No | - | Get details of specific email |
| `limit` | integer | No | 50 | Maximum emails to return (max: 100) |

### Response Fields

- `id`: Entry identifier
- `mailable`: Mailable class name
- `to`: Recipients
- `cc`: CC recipients
- `bcc`: BCC recipients
- `subject`: Email subject
- `queued`: Whether email was queued
- `created_at`: Timestamp

---

## telescope_notifications

Track notification dispatching.

### Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `id` | string | No | - | Get details of specific notification |
| `limit` | integer | No | 50 | Maximum notifications to return (max: 100) |

### Response Fields

- `id`: Entry identifier
- `notification`: Notification class name
- `notifiable`: Notifiable type and ID
- `channel`: Notification channel
- `queued`: Whether notification was queued
- `created_at`: Timestamp

---

## telescope_models

Track Eloquent model operations.

### Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `id` | string | No | - | Get details of specific model operation |
| `limit` | integer | No | 50 | Maximum operations to return (max: 100) |

### Response Fields

- `id`: Entry identifier
- `action`: Action type (created, updated, deleted)
- `model`: Model class name
- `key`: Model primary key
- `changes`: Changed attributes
- `created_at`: Timestamp

---

## telescope_views

Track view rendering.

### Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `id` | string | No | - | Get details of specific view |
| `limit` | integer | No | 50 | Maximum views to return (max: 100) |

### Response Fields

- `id`: Entry identifier
- `name`: View name
- `path`: View file path
- `data`: View data (keys only)
- `created_at`: Timestamp

---

## telescope_redis

Monitor Redis operations.

### Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `id` | string | No | - | Get details of specific Redis operation |
| `limit` | integer | No | 50 | Maximum operations to return (max: 100) |

### Response Fields

- `id`: Entry identifier
- `command`: Redis command
- `connection`: Redis connection name
- `duration`: Execution time in ms
- `created_at`: Timestamp

---

## telescope_dumps

Access debug dumps (dump(), dd()).

### Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `id` | string | No | - | Get details of specific dump |
| `limit` | integer | No | 50 | Maximum dumps to return (max: 100) |

### Response Fields

- `id`: Entry identifier
- `dump`: Dumped data
- `created_at`: Timestamp

---

## telescope_http_client

Monitor outgoing HTTP requests.

### Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `id` | string | No | - | Get details of specific HTTP client request |
| `limit` | integer | No | 50 | Maximum requests to return (max: 100) |

### Response Fields

- `id`: Entry identifier
- `method`: HTTP method
- `uri`: Request URI
- `status`: Response status
- `duration`: Request duration in ms
- `request_headers`: Outgoing headers
- `request_body`: Request body
- `response_headers`: Response headers
- `response_body`: Response body
- `created_at`: Timestamp

---

## telescope_prune

Clean up old Telescope entries.

### Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `hours` | integer | No | 24 | Delete entries older than this many hours |

### Response Fields

- `deleted`: Number of entries deleted
- `message`: Confirmation message
