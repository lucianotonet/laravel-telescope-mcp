<?php

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use LucianoTonet\TelescopeMcp\MCP\TelescopeMcpServer;

beforeEach(function () {
    $this->server = app(TelescopeMcpServer::class);
});

test('logs tool can be executed', function () {
    Log::info('Test log message for Telescope MCP');

    $result = $this->server->executeTool('logs', ['limit' => 10]);

    expect($result)
        ->toBeArray()
        ->toHaveKey('content')
        ->and($result['content'])->toBeArray()
        ->and($result['content'][0])->toHaveKeys(['type', 'text']);
});

test('logs tool respects limit parameter', function () {
    for ($i = 0; $i < 5; $i++) {
        Log::info("Test log message {$i}");
    }

    $result = $this->server->executeTool('logs', ['limit' => 3]);

    expect($result)->toBeArray()->toHaveKey('content');
});

test('requests tool can be executed', function () {
    $result = $this->server->executeTool('requests', ['limit' => 10]);

    expect($result)
        ->toBeArray()
        ->toHaveKey('content')
        ->and($result['content'])->toBeArray()
        ->and($result['content'][0])->toHaveKeys(['type', 'text']);
});

test('exceptions tool can be executed', function () {
    $result = $this->server->executeTool('exceptions', ['limit' => 10]);

    expect($result)
        ->toBeArray()
        ->toHaveKey('content')
        ->and($result['content'])->toBeArray();
});

test('queries tool can be executed', function () {
    DB::select('SELECT 1');

    $result = $this->server->executeTool('queries', ['limit' => 10]);

    expect($result)
        ->toBeArray()
        ->toHaveKey('content')
        ->and($result['content'])->toBeArray();
});

test('queries tool can filter slow queries', function () {
    $result = $this->server->executeTool('queries', [
        'limit' => 10,
        'slow' => true,
    ]);

    expect($result)->toBeArray()->toHaveKey('content');
});

test('cache tool can be executed', function () {
    Cache::put('test_key', 'test_value', 60);
    Cache::get('test_key');

    $result = $this->server->executeTool('cache', ['limit' => 10]);

    expect($result)
        ->toBeArray()
        ->toHaveKey('content')
        ->and($result['content'])->toBeArray();
});

test('events tool can be executed', function () {
    $result = $this->server->executeTool('events', ['limit' => 10]);

    expect($result)
        ->toBeArray()
        ->toHaveKey('content');
});

test('jobs tool can be executed', function () {
    $result = $this->server->executeTool('jobs', ['limit' => 10]);

    expect($result)
        ->toBeArray()
        ->toHaveKey('content');
});

test('jobs tool can filter failed jobs', function () {
    $result = $this->server->executeTool('jobs', [
        'limit' => 10,
        'failed' => true,
    ]);

    expect($result)->toBeArray()->toHaveKey('content');
});

test('commands tool can be executed', function () {
    $result = $this->server->executeTool('commands', ['limit' => 10]);

    expect($result)
        ->toBeArray()
        ->toHaveKey('content');
});

test('schedule tool can be executed', function () {
    $result = $this->server->executeTool('schedule', ['limit' => 10]);

    expect($result)
        ->toBeArray()
        ->toHaveKey('content');
});

test('mail tool can be executed', function () {
    $result = $this->server->executeTool('mail', ['limit' => 10]);

    expect($result)
        ->toBeArray()
        ->toHaveKey('content');
});

test('notifications tool can be executed', function () {
    $result = $this->server->executeTool('notifications', ['limit' => 10]);

    expect($result)
        ->toBeArray()
        ->toHaveKey('content');
});

test('gates tool can be executed', function () {
    $result = $this->server->executeTool('gates', ['limit' => 10]);

    expect($result)
        ->toBeArray()
        ->toHaveKey('content');
});

test('models tool can be executed', function () {
    $result = $this->server->executeTool('models', ['limit' => 10]);

    expect($result)
        ->toBeArray()
        ->toHaveKey('content');
});

test('views tool can be executed', function () {
    $result = $this->server->executeTool('views', ['limit' => 10]);

    expect($result)
        ->toBeArray()
        ->toHaveKey('content');
});

test('dumps tool can be executed', function () {
    $result = $this->server->executeTool('dumps', ['limit' => 10]);

    expect($result)
        ->toBeArray()
        ->toHaveKey('content');
});

test('batches tool can be executed', function () {
    $result = $this->server->executeTool('batches', ['limit' => 10]);

    expect($result)
        ->toBeArray()
        ->toHaveKey('content');
});

test('redis tool can be executed', function () {
    $result = $this->server->executeTool('redis', ['limit' => 10]);

    expect($result)
        ->toBeArray()
        ->toHaveKey('content');
});

test('http-client tool can be executed', function () {
    $result = $this->server->executeTool('http-client', ['limit' => 10]);

    expect($result)
        ->toBeArray()
        ->toHaveKey('content');
});

test('prune tool can be executed', function () {
    $result = $this->server->executeTool('prune', ['hours' => 24]);

    expect($result)
        ->toBeArray()
        ->toHaveKey('content');
});

test('tool responses have valid MCP format', function () {
    $result = $this->server->executeTool('logs', ['limit' => 5]);

    expect($result)
        ->toHaveKey('content')
        ->and($result['content'])->toBeArray();

    foreach ($result['content'] as $item) {
        expect($item)
            ->toHaveKeys(['type', 'text'])
            ->and($item['type'])->toBeString()
            ->and($item['text'])->toBeString();
    }
});
