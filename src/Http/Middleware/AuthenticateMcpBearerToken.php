<?php

namespace LucianoTonet\TelescopeMcp\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateMcpBearerToken
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!config('telescope-mcp.auth.enabled', false)) {
            return $next($request);
        }

        $expectedToken = (string) config('telescope-mcp.auth.bearer_token', '');
        if ($expectedToken === '') {
            return response()->json([
                'message' => 'MCP authentication is enabled, but no bearer token is configured.',
            ], 500);
        }

        $header = (string) config('telescope-mcp.auth.header', 'Authorization');
        $providedToken = $this->extractToken($request, $header);

        if ($providedToken !== null && hash_equals($expectedToken, $providedToken)) {
            return $next($request);
        }

        return response()->json(['message' => 'Unauthorized'], 401);
    }

    protected function extractToken(Request $request, string $header): ?string
    {
        if (strcasecmp($header, 'Authorization') === 0) {
            return $request->bearerToken();
        }

        $value = $request->header($header);
        if (!is_string($value) || $value === '') {
            return null;
        }

        if (str_starts_with(strtolower($value), 'bearer ')) {
            return trim(substr($value, 7));
        }

        return $value;
    }
}
