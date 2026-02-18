# TODO - Harden MCP Authentication

## Goal
Ensure `telescope-mcp` web endpoint is explicitly protected in production and documented clearly.

## Why
- Current default route middleware is `['api']` only (`config/telescope-mcp.php`).
- Telescope dashboard access rules do not automatically protect MCP endpoint.
- Users may assume Telescope auth gate covers MCP route, causing accidental public exposure.

## Tasks
1. Documentation hardening
- [x] Added warning in `README.md` that MCP HTTP route is public unless protected.
- [x] Added "Secure Streamable HTTP in IDEs" with token/header setup.
- [x] Added practical local/online URL examples and Inspector auth usage.

2. Config ergonomics
- [x] Added `TELESCOPE_MCP_MIDDLEWARE` parsing (`api,auth:sanctum` style).
- [x] Added built-in bearer auth settings:
  - `TELESCOPE_MCP_AUTH_ENABLED`
  - `TELESCOPE_MCP_BEARER_TOKEN` (fallback `MCP_BEARER_TOKEN`)
  - `TELESCOPE_MCP_AUTH_HEADER`

3. Runtime safety signal
- [x] Added production warning when endpoint appears unprotected.

4. Tests
- [x] Added feature tests for missing token (401), valid token (200), env fallback token, and middleware env parsing.

5. Optional OAuth support clarification
- [ ] Remaining: dedicated OAuth section and helper flow for Passport discovery/registration.

## Suggested Acceptance Criteria
- A new user cannot mistake MCP endpoint as protected by Telescope UI auth.
- Production deployments have clear warnings when endpoint is not protected.
- README includes working auth examples for Inspector (`--header "Authorization: Bearer ..."`) and clients.
- Automated tests cover protected and unprotected route behavior.
