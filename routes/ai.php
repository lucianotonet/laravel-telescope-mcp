<?php

use Laravel\Mcp\Facades\Mcp;
use LucianoTonet\TelescopeMcp\Http\Middleware\AuthenticateMcpBearerToken;
use LucianoTonet\TelescopeMcp\Mcp\Servers\TelescopeServer;

/*
|--------------------------------------------------------------------------
| AI Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of the AI routes for your
| application. These routes are loaded by the TelescopeMcpServiceProvider.
|
*/

$middleware = config('telescope-mcp.middleware', ['api']);

if (config('telescope-mcp.auth.enabled', false)) {
    $middleware[] = AuthenticateMcpBearerToken::class;
}

Mcp::web(
    config('telescope-mcp.path', 'telescope-mcp'),
    TelescopeServer::class
)->middleware($middleware);
