<?php

use Illuminate\Support\Facades\Log;
use LucianoTonet\TelescopeMcp\Support\Logger;

test('getInstance returns singleton instance', function () {
    $first = Logger::getInstance();
    $second = Logger::getInstance();

    expect($first)->toBe($second);
});

test('info calls Log when logging is enabled', function () {
    config(['telescope-mcp.logging.enabled' => true]);
    Log::shouldReceive('log')->once()->with('info', 'Test message', ['key' => 'value']);

    Logger::info('Test message', ['key' => 'value']);
});

test('debug calls Log when logging is enabled', function () {
    config(['telescope-mcp.logging.enabled' => true]);
    Log::shouldReceive('log')->once()->with('debug', 'Debug message', []);

    Logger::debug('Debug message');
});

test('warning calls Log when logging is enabled', function () {
    config(['telescope-mcp.logging.enabled' => true]);
    Log::shouldReceive('log')->once()->with('warning', 'Warning message', ['context' => 'data']);

    Logger::warning('Warning message', ['context' => 'data']);
});

test('error calls Log when logging is enabled', function () {
    config(['telescope-mcp.logging.enabled' => true]);
    Log::shouldReceive('log')->once()->with('error', 'Error message', []);

    Logger::error('Error message');
});

test('does not call Log when logging is disabled', function () {
    config(['telescope-mcp.logging.enabled' => false]);
    Log::shouldReceive('log')->never();

    Logger::info('Should not log');
    Logger::error('Should not log either');
});
