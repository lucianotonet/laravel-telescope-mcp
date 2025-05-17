<?php

use Illuminate\Support\Facades\Route;
use LucianoTonet\TelescopeMcp\Http\Controllers\McpController;

// Specific route for MCP tools/call (must come before generic route)
Route::post('tools/call', [McpController::class, 'executeToolCall']);

// Base route for MCP protocol
Route::post('/', [McpController::class, 'manifest']);

// Alternative routes for direct access
Route::get('/manifest.json', [McpController::class, 'manifest']);

// Route for executing specific tools
Route::post('/tools/{tool}', [McpController::class, 'executeTool'])
    ->where('tool', '[a-zA-Z0-9_]+'); // Prevents conflict with tools/call

// Log all requests for diagnostics
Route::any('{any}', function () {
    \LucianoTonet\TelescopeMcp\Support\Logger::info('Route not found', [
        'method' => request()->method(),
        'path' => request()->path(),
        'input' => request()->all()
    ]);
    return response()->json(['error' => 'Route not found'], 404);
})->where('any', '.*');
