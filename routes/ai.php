<?php

use Laravel\Mcp\Facades\Mcp;
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

Mcp::web(
    config('telescope-mcp.path', 'telescope-mcp'),
    TelescopeServer::class
)->middleware(config('telescope-mcp.middleware', ['api']));
