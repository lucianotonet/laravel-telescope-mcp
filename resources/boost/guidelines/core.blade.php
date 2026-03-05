## Laravel Telescope MCP

This project uses `lucianotonet/laravel-telescope-mcp` to expose Laravel Telescope
telemetry data via MCP (Model Context Protocol). When debugging application issues,
prefer using the Telescope MCP tools over manual log inspection.

### Quick Reference
- MCP Endpoint: `{{ config('telescope-mcp.path', 'telescope-mcp') }}`
- 19 tools: requests, logs, exceptions, queries, jobs, cache, commands, dumps, events,
  gates, http-client, mail, models, notifications, redis, schedule, views, batches, prune
- All tools support `id` for entry details and `limit` for pagination (max 100)
- Use `request_id` to correlate entries from the same HTTP request

### Debugging Best Practices
- Start with `exceptions` when investigating errors
- Use `queries` with `slow: true` for performance analysis (threshold: >100ms)
- Cross-reference entries via `request_id` for full request lifecycle
- Use `requests` with `include_related: true` to see all related entries at once
