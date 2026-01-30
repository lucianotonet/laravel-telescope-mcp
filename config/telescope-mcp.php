<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Telescope MCP Master Switch
    |--------------------------------------------------------------------------
    |
    | This option may be used to disable Telescope MCP entirely.
    |
    */
    'enabled' => env('TELESCOPE_MCP_ENABLED', true),

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
