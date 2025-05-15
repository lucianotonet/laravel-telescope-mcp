<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Telescope MCP Path
    |--------------------------------------------------------------------------
    |
    | This is the URI path where Telescope MCP will be accessible from. Feel free
    | to change this path to anything you like.
    |
    */
    'path' => env('TELESCOPE_MCP_PATH', 'telescope/mcp'),

    /*
    |--------------------------------------------------------------------------
    | Telescope MCP Route Middleware
    |--------------------------------------------------------------------------
    |
    | These middleware will be assigned to every Telescope MCP route, giving you
    | the chance to add your own middleware to this list or change any of
    | the existing middleware. Or, you can simply stick with this list.
    |
    */
    'middleware' => [
        // 'web',
    ],

    'enabled' => env('TELESCOPE_MCP_ENABLED', true),
    
    /*
    |--------------------------------------------------------------------------
    | Telescope MCP Logging
    |--------------------------------------------------------------------------
    |
    | Here you may configure the logging settings for Telescope MCP.
    |
    */
    'logging' => [
        'enabled' => env('TELESCOPE_MCP_LOGGING_ENABLED', true),
        'level' => env('TELESCOPE_MCP_LOGGING_LEVEL', 'debug'),
        'path' => storage_path('logs/telescope-mcp.log'),
        'channel' => env('TELESCOPE_MCP_LOGGING_CHANNEL', 'stack'),
    ],
]; 