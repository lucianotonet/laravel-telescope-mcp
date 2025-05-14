<?php

return [
    'enabled' => env('TELESCOPE_MCP_ENABLED', true),
    
    'middleware' => [
        // 'web',
        // Você pode personalizar os middlewares aqui
        // Ou usar auth:api para proteção
    ],
    
    'path' => env('TELESCOPE_MCP_PATH', 'mcp'),

    'logging' => [
        // 'enabled' => env('TELESCOPE_MCP_LOGGING', true),
        // 'channel' => env('TELESCOPE_MCP_LOG_CHANNEL', 'daily'),
        // 'path' => storage_path('logs/telescope-mcp.log'),
        // 'level' => env('TELESCOPE_MCP_LOG_LEVEL', 'debug'),
        // 'days' => env('TELESCOPE_MCP_LOG_DAYS', 7),
    ],
]; 