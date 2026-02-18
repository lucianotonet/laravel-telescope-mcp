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
    | Telescope MCP Web Routes
    |--------------------------------------------------------------------------
    |
    | This option may be used to disable the web-based MCP server routes.
    | If disabled, the MCP server will only be available via CLI (stdio).
    |
    */
    'routes' => env('TELESCOPE_MCP_ROUTES_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Telescope MCP Route Middleware
    |--------------------------------------------------------------------------
    |
    | These middleware will be assigned to every Telescope MCP route.
    |
    */
    'middleware' => array_values(array_filter(array_map(
        trim(...),
        explode(',', (string) env('TELESCOPE_MCP_MIDDLEWARE', 'api'))
    ))),

    /*
    |--------------------------------------------------------------------------
    | Telescope MCP Bearer Authentication
    |--------------------------------------------------------------------------
    |
    | Optional built-in bearer token authentication for HTTP MCP route.
    | Useful for Streamable HTTP clients and MCP Inspector.
    |
    */
    'auth' => [
        'enabled' => env(
            'TELESCOPE_MCP_AUTH_ENABLED',
            env('TELESCOPE_MCP_BEARER_TOKEN') !== null || env('MCP_BEARER_TOKEN') !== null
        ),
        'bearer_token' => env('TELESCOPE_MCP_BEARER_TOKEN', env('MCP_BEARER_TOKEN')),
        'header' => env('TELESCOPE_MCP_AUTH_HEADER', 'Authorization'),
    ],

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
