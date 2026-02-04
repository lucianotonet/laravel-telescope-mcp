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
    'path' => env('TELESCOPE_MCP_PATH', 'telescope-mcp'),

    /*
    |--------------------------------------------------------------------------
    | Telescope MCP Master Switch
    |--------------------------------------------------------------------------
    |
    | This option may be used to disable Telescope MCP.
    |
    */
    'enabled' => env('TELESCOPE_MCP_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Telescope MCP Route Middleware
    |--------------------------------------------------------------------------
    |
    | These middleware will be assigned to every Telescope MCP route.
    |
    */
    'middleware' => ['api'],

    /*
    |--------------------------------------------------------------------------
    | Telescope MCP Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log settings for Telescope MCP.
    |
    */
    'logging' => [
        'enabled' => env('TELESCOPE_MCP_LOGGING_ENABLED', true),
        'channel' => env('TELESCOPE_MCP_LOG_CHANNEL', 'stack'),
    ],
];
