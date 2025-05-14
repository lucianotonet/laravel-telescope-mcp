<?php

use Illuminate\Support\Facades\Route;
use LucianoTonet\TelescopeMcp\Http\Controllers\McpController;

// Rota específica para chamadas tools/call do MCP (deve vir antes da rota genérica)
Route::post('tools/call', [McpController::class, 'executeToolCall']);

// Rota base para o protocolo MCP
Route::post('/', [McpController::class, 'manifest']);

// Rotas alternativas para acesso direto
Route::get('/manifest.json', [McpController::class, 'manifest']);

// Rota para execução de ferramentas específicas
Route::post('/tools/{tool}', [McpController::class, 'executeTool'])
    ->where('tool', '[a-zA-Z0-9_]+'); // Evita conflito com tools/call

// Log todas as requisições para diagnóstico
Route::any('{any}', function () {
    \LucianoTonet\TelescopeMcp\Support\Logger::info('Route not found', [
        'method' => request()->method(),
        'path' => request()->path(),
        'input' => request()->all()
    ]);
    return response()->json(['error' => 'Route not found'], 404);
})->where('any', '.*'); 