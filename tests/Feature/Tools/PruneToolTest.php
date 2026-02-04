<?php

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use LucianoTonet\TelescopeMcp\Mcp\Tools\PruneTool;

beforeEach(function () {
    PruneTool::$testRunner = null;
});

afterEach(function () {
    PruneTool::$testRunner = null;
});

test('prune tool calls telescope prune command with default hours', function () {
    PruneTool::$testRunner = function (int $hours) {
        expect($hours)->toBe(24);
        return ['output' => 'Pruned entries successfully', 'exitCode' => 0];
    };

    $tool = new PruneTool();
    $response = $tool->handle(new Request([]));

    expect($response)->toBeInstanceOf(Response::class);
    expect($response->content()->toArray()['text'] ?? '')->toContain('Pruned entries successfully');
});

test('prune tool calls telescope prune command with custom hours', function () {
    PruneTool::$testRunner = function (int $hours) {
        expect($hours)->toBe(48);
        return ['output' => 'Pruned entries successfully', 'exitCode' => 0];
    };

    $tool = new PruneTool();
    $response = $tool->handle(new Request(['hours' => 48]));

    expect($response)->toBeInstanceOf(Response::class);
    expect($response->content()->toArray()['text'] ?? '')->toContain('Pruned entries successfully');
});

test('prune tool returns success message when output is empty', function () {
    PruneTool::$testRunner = fn (int $hours) => ['output' => '', 'exitCode' => 0];

    $tool = new PruneTool();
    $response = $tool->handle(new Request([]));

    expect($response)->toBeInstanceOf(Response::class);
    expect($response->content()->toArray()['text'] ?? '')->toContain('Telescope entries pruned successfully');
    expect($response->content()->toArray()['text'] ?? '')->toContain('24 hours');
});

test('prune tool returns error when command fails', function () {
    PruneTool::$testRunner = fn (int $hours) => ['output' => '', 'exitCode' => 1, 'message' => 'Command failed'];

    $tool = new PruneTool();
    $response = $tool->handle(new Request([]));

    expect($response->isError())->toBeTrue();
    expect($response->content()->toArray()['text'] ?? '')->toContain('Error: Command failed');
});
