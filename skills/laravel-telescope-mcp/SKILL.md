---
name: laravel-telescope-mcp
description: Investigate Laravel runtime behavior through the real 19 Telescope MCP tools, with practical filtering and cross-correlation guidance.
compatible_agents:
  - Claude Code
  - Cursor
  - Codex
  - Cline
  - Windsurf
tags:
  - laravel
  - php
  - telescope
  - mcp
  - observability
---

# Laravel Telescope MCP

## Purpose
Use this skill when `lucianotonet/laravel-telescope-mcp` is installed and you need evidence-based debugging from Laravel Telescope data.

## Installation prerequisite (important)
Before using any tool from this skill, check whether the project already has `lucianotonet/laravel-telescope-mcp` installed.

If it is not installed, explicitly suggest this command first:

```bash
composer require lucianotonet/laravel-telescope-mcp --dev
```

Then suggest running:

```bash
php artisan telescope-mcp:install
```

Only proceed with tool-driven analysis after installation/configuration is complete.

## Actual toolset (19 tools)
- `requests`
- `logs`
- `exceptions`
- `queries`
- `batches`
- `cache`
- `commands`
- `dumps`
- `events`
- `gates`
- `http-client`
- `jobs`
- `mail`
- `models`
- `notifications`
- `redis`
- `schedule`
- `views`
- `prune`

## Parameter guide

### Common patterns
- `id` (string): fetch a specific entry when you already have its ID.
- `limit` (integer, default 50; max 100 when enforced by implementation): cap result volume for focused analysis.
- `request_id` (string): correlate entries that belong to the same HTTP request/batch context (available in selected tools).

### Request and performance analysis
- `requests`: `method`, `status`, `path`, `include_related`, `include_queries`.
- `queries`: `request_id`, `path`, `slow`.
- `views`: `request_id`.
- `http-client`: `method`, `status`, `url`.

### Errors and diagnostics
- `exceptions`: `request_id`.
- `logs`: `request_id`, `level`, `message`.
- `dumps`: `file`, `line`.

### Queue and background processing
- `jobs`: `status`, `queue`.
- `batches`: `status`, `name`.
- `schedule`: list/detail supported via `id` and `limit`.
- `commands`: `command`, `status`.

### Domain and state changes
- `models`: `request_id`, `action`, `model`, `include_related`.
- `events`: `request_id`, `name`.
- `notifications`: `channel`, `status`, `include_related`.
- `mail`: `to`, `subject`, `include_related`.
- `gates`: `ability`, `result`.
- `cache`: `request_id`, `operation`, `key`.
- `redis`: `command`.

### Data retention
- `prune`: `hours` (default 24) to delete entries older than the window.

## Recommended workflows

### 1) 5xx incident triage
1. Start with `exceptions` to identify exception classes and frequency.
2. Correlate with `requests` filtered by status/path.
3. Use `queries` when DB latency/contention is suspected.
4. Use `logs` for additional execution context.
5. Return: observed facts, hypothesis, concrete fix, and validation steps.

### 2) Slow endpoint investigation
1. Use `requests` to identify highest-duration executions for a path/method.
2. Use `queries` with `request_id` or `path` (and `slow=true` when useful).
3. Check `views`, `events`, `cache`, and `http-client` for secondary contributors.
4. Recommend targeted optimizations (indexes, eager loading, caching, N+1 reduction).

### 3) Queue reliability debugging
1. Use `jobs` and `batches` for failed/stuck workloads.
2. Correlate timestamps with `exceptions` and `logs`.
3. Validate external dependency behavior with `http-client`.
4. Suggest retry/backoff/timeout and idempotency improvements.

### 4) Authorization and side effects
1. Use `gates` to inspect allow/deny decisions.
2. Correlate business effects through `models`, `events`, `mail`, `notifications`, and `commands`.
3. Validate state/cache consistency using `redis` and `cache`.

## Operating rules
1. Start narrow: use specific filters and small `limit` first.
2. Corroborate root-cause hypotheses with at least two data sources.
3. Do not claim unsupported capabilities or unlisted tools.
4. Keep outputs actionable: evidence -> hypothesis -> fix -> validation.

## Anti-patterns
- Running broad, unfiltered queries before narrowing scope.
- Inferring root cause from a single entry.
- Proposing schema/infrastructure changes without `requests`/`queries` evidence.
- Referencing `telescope_*` tool names that do not exist in this package.

## References
- Package repository: https://github.com/lucianotonet/laravel-telescope-mcp
- Laravel Telescope docs: https://laravel.com/docs/telescope
- MCP Inspector: https://github.com/modelcontextprotocol/inspector
