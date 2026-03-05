# Telescope MCP Tools Reference

Complete parameter documentation for all 19 MCP tools. Each tool queries Laravel Telescope's recorded entries.

**Global constraints:** All `limit` parameters cap at 100. All tools return combined human-readable (ASCII table) and structured (JSON) output.

---

## requests

Lists and analyzes HTTP requests recorded by Telescope.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `id` | string | — | ID of specific request to view details |
| `limit` | integer | 50 | Maximum number of requests to return |
| `method` | string | — | Filter by HTTP method (GET, POST, PUT, DELETE, etc.) |
| `status` | integer | — | Filter by HTTP status code (200, 404, 500, etc.) |
| `path` | string | — | Filter by request path |
| `include_related` | boolean | true | Include summary of related entries (logs, queries, exceptions, etc.) |
| `include_queries` | boolean | false | Include detailed queries associated with this request (max 10) |

**When to use:** Investigating HTTP traffic, finding specific endpoints, understanding request lifecycle.

**Detail view (with `id`):** Shows method, URI, status, headers, payload, response, duration, middleware, controller action, and optionally a batch summary of all related entries.

---

## logs

Lists and analyzes log entries recorded by Telescope.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `id` | string | — | ID of the specific log entry to view details |
| `request_id` | string | — | Filter logs by request ID (uses batch_id grouping) |
| `limit` | integer | 50 | Maximum number of log entries to return |
| `level` | string | — | Filter by log level. Enum: `debug`, `info`, `notice`, `warning`, `error`, `critical`, `alert`, `emergency` |
| `message` | string | — | Filter by log message content (case-insensitive partial match) |

**When to use:** Reviewing application log output, filtering by severity, finding logs from a specific request.

**Detail view (with `id`):** Shows level, message, and full context data.

---

## exceptions

Displays exceptions recorded by Telescope with complete error details.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `id` | string | — | ID of specific exception to view details |
| `request_id` | string | — | Filter exceptions by request ID (uses batch_id grouping) |
| `limit` | integer | 50 | Maximum number of exceptions to return |

**When to use:** Investigating application errors, viewing stack traces, finding exceptions from specific requests.

**Detail view (with `id`):** Shows exception class, message, file, line, full stack trace (file/line/function for each frame), and context data.

---

## queries

Lists and analyzes database queries recorded by Telescope.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `id` | string | — | ID of specific database query to view details. **Caution:** do not pass a request ID here |
| `path` | string | — | Get queries for the most recent request matching this path (e.g., `/api/users`) |
| `request_id` | string | — | Filter queries by a specific request ID |
| `limit` | integer | 50 | Maximum number of queries to return |
| `slow` | boolean | false | Filter only slow queries (>100ms) |

**Priority:** `path` > `request_id` > generic list.

**Smart fallback:** If `id` is provided but not found as a query entry, the tool checks if it's a request ID and returns queries for that request instead.

**When to use:** Debugging N+1 problems, finding slow queries, understanding database load for a request.

**Detail view (with `id`):** Shows full SQL, bindings, duration (ms), connection, source file/line, and backtrace (first 5 frames).

---

## batches

Lists and analyzes job batch operations recorded by Telescope.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `id` | string | — | ID of specific batch operation |
| `limit` | integer | 50 | Maximum number of batch operations to return |
| `status` | string | — | Filter by batch status. Enum: `pending`, `processing`, `finished`, `failed` |
| `name` | string | — | Filter by batch name |

**When to use:** Monitoring batch job progress, investigating batch failures.

**Detail view (with `id`):** Shows batch name, progress percentage, total/pending/failed job counts, and failed job details with error messages and stack traces.

**Status indicators:** `[!]` failed, `[✓]` finished, `[→]` processing.

---

## cache

Lists and analyzes cache operations recorded by Telescope.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `id` | string | — | ID of specific cache operation |
| `request_id` | string | — | Filter cache operations by request ID (uses batch_id grouping) |
| `limit` | integer | 50 | Maximum number of cache operations to return |
| `operation` | string | — | Filter by operation type. Enum: `hit`, `miss`, `set`, `forget` |
| `key` | string | — | Filter by cache key (partial match) |

**When to use:** Auditing cache hit/miss ratios, debugging cache invalidation, understanding cache usage per request.

**Detail view (with `id`):** Shows operation type, key, value (if available), expiration, and tags.

---

## commands

Lists and analyzes Artisan command executions recorded by Telescope.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `id` | string | — | ID of specific command execution |
| `limit` | integer | 50 | Maximum number of command executions to return |
| `command` | string | — | Filter by command name |
| `status` | string | — | Filter by execution status. Enum: `success`, `error` |

**When to use:** Checking if scheduled commands ran, debugging command failures.

**Detail view (with `id`):** Shows command name, exit code, arguments, options, and output. Status derived from exit code: 0 = Success, null = Unknown, other = Error.

---

## dumps

Lists and analyzes dump entries (dd/dump calls) recorded by Telescope.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `id` | string | — | ID of specific dump entry |
| `limit` | integer | 50 | Maximum number of dump entries to return |
| `file` | string | — | Filter by source file path |
| `line` | integer | — | Filter by source line number |

**When to use:** Finding dump/dd output in the application, locating debug statements.

**Detail view (with `id`):** Shows full dump content, source file path, and line number.

---

## events

Lists and analyzes application events recorded by Telescope.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `id` | string | — | ID of specific event |
| `request_id` | string | — | Filter events by request ID (uses batch_id grouping) |
| `limit` | integer | 50 | Maximum number of events to return |
| `name` | string | — | Filter by event name |

**When to use:** Understanding event flow, checking which listeners were triggered, debugging event-driven logic.

**Detail view (with `id`):** Shows event name, payload data, and list of listeners with count.

---

## gates

Lists and analyzes gate/authorization checks recorded by Telescope.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `id` | string | — | ID of specific gate check |
| `limit` | integer | 50 | Maximum number of gate checks to return |
| `ability` | string | — | Filter by gate ability name |
| `result` | string | — | Filter by check result: `allowed` or `denied` |

**When to use:** Debugging authorization issues, auditing access control decisions.

**Detail view (with `id`):** Shows ability name, result (Allowed/Denied), arguments, and context. Denied results marked with `[!]`.

---

## http-client

Lists and analyzes outgoing HTTP requests made by Laravel's HTTP client.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `id` | string | — | ID of specific HTTP client request |
| `limit` | integer | 50 | Maximum number of requests to return |
| `method` | string | — | Filter by HTTP method (GET, POST, etc.) |
| `status` | integer | — | Filter by HTTP status code |
| `url` | string | — | Filter by URL (partial match) |

**When to use:** Debugging third-party API calls, monitoring outgoing HTTP traffic, checking response times.

**Detail view (with `id`):** Shows method, URL, status, duration (seconds), request/response headers, and body.

**Note:** This tool tracks *outgoing* requests (via `Http::get()`, etc.), not *incoming* requests — use `requests` for those.

---

## jobs

Lists and analyzes queued job executions recorded by Telescope.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `id` | string | — | ID of specific job |
| `limit` | integer | 50 | Maximum number of jobs to return |
| `status` | string | — | Filter by status: `pending`, `processed`, `failed` |
| `queue` | string | — | Filter by specific queue name |

**When to use:** Monitoring job processing, debugging job failures, checking queue health.

**Detail view (with `id`):** Shows job class, queue, connection, attempts, data payload, and exception details (message + trace) if failed.

---

## mail

Lists and analyzes emails sent, recorded by Telescope.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `id` | string | — | ID of specific email |
| `limit` | integer | 50 | Maximum number of emails to return |
| `to` | string | — | Filter by recipient |
| `subject` | string | — | Filter by subject |
| `include_related` | boolean | true | Include summary of related entries |

**When to use:** Verifying emails were sent, debugging mail content, checking recipients.

**Detail view (with `id`):** Shows mailable class, subject, to/cc/bcc recipients (with names), HTML/text body, attachments, and related batch entries.

---

## models

Lists and analyzes Eloquent model operations recorded by Telescope.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `id` | string | — | ID of specific model operation |
| `request_id` | string | — | Filter by request ID (uses batch_id grouping) |
| `limit` | integer | 50 | Maximum number of operations to return |
| `action` | string | — | Filter by action type: `created`, `updated`, `deleted` |
| `model` | string | — | Filter by model class name |
| `include_related` | boolean | true | Include summary of related entries |

**When to use:** Tracking model changes, auditing data modifications, debugging unexpected writes.

**Detail view (with `id`):** Shows model class, action, key, old attributes, new attributes, changes diff, and related batch entries.

---

## notifications

Lists and analyzes notifications recorded by Telescope.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `id` | string | — | ID of specific notification |
| `limit` | integer | 50 | Maximum number of notifications to return |
| `channel` | string | — | Filter by channel (mail, database, slack, etc.) |
| `status` | string | — | Filter by status: `sent`, `failed` |
| `include_related` | boolean | true | Include summary of related entries |

**When to use:** Verifying notifications were delivered, debugging notification failures.

**Detail view (with `id`):** Shows notification class, channel, notifiable, response data, exception details (if failed, with first 5 trace frames), data payload, and related batch entries.

---

## prune

Prunes old Telescope entries from the database.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `hours` | integer | 24 | Number of hours to keep. Entries older than this will be permanently deleted |

**⚠️ DESTRUCTIVE OPERATION** — This tool permanently deletes Telescope entries. It cannot be undone.

**When to use:** Cleaning up old telemetry data to free database space. Equivalent to running `php artisan telescope:prune --hours={hours}`.

---

## redis

Lists and analyzes Redis command executions recorded by Telescope.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `id` | string | — | ID of specific Redis operation |
| `limit` | integer | 50 | Maximum number of operations to return |
| `command` | string | — | Filter by Redis command (e.g., GET, SET, DEL). Case-insensitive |

**When to use:** Debugging Redis usage, monitoring command frequency, checking connection and duration.

**Detail view (with `id`):** Shows command name, parameters (truncated to 20 chars each), duration, connection, and result.

---

## schedule

Lists and analyzes scheduled task executions recorded by Telescope.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `id` | string | — | ID of specific scheduled task |
| `limit` | integer | 50 | Maximum number of tasks to return |

**When to use:** Verifying scheduled tasks ran correctly, debugging scheduler issues.

**Detail view (with `id`):** Shows command/description, cron expression, status (Success/Running/Failed based on exit code), and output.

---

## views

Lists and analyzes Blade view renderings recorded by Telescope.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `id` | string | — | ID of specific view rendering |
| `request_id` | string | — | Filter views by request ID (uses batch_id grouping) |
| `limit` | integer | 50 | Maximum number of views to return |

**When to use:** Understanding which views are rendered per request, debugging view data.

**Detail view (with `id`):** Shows view name, file path, and data variables passed to the view.
