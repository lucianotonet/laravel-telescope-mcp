<?php

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Telescope\Contracts\PrunableRepository;
use LucianoTonet\TelescopeMcp\Mcp\Tools\PruneTool;

beforeEach(function () {
    PruneTool::$testRunner = null;
});

afterEach(function () {
    PruneTool::$testRunner = null;
});

test('prune tool calls telescope prune command with default hours', function () {
    PruneTool::$testRunner = function (int $hours, bool $keepExceptions) {
        expect($hours)->toBe(24);
        expect($keepExceptions)->toBeFalse();
        return ['output' => 'Pruned entries successfully', 'exitCode' => 0];
    };

    $tool = new PruneTool();
    $repository = Mockery::mock(PrunableRepository::class);
    $response = $tool->handle(new Request([]), $repository);

    expect($response)->toBeInstanceOf(Response::class);
    expect($response->content()->toArray()['text'] ?? '')->toContain('Pruned entries successfully');
});

test('prune tool calls telescope prune command with custom hours', function () {
    PruneTool::$testRunner = function (int $hours, bool $keepExceptions) {
        expect($hours)->toBe(48);
        expect($keepExceptions)->toBeTrue();
        return ['output' => 'Pruned entries successfully', 'exitCode' => 0];
    };

    $tool = new PruneTool();
    $repository = Mockery::mock(PrunableRepository::class);
    $response = $tool->handle(new Request(['hours' => 48, 'keep_exceptions' => true]), $repository);

    expect($response)->toBeInstanceOf(Response::class);
    expect($response->content()->toArray()['text'] ?? '')->toContain('Pruned entries successfully');
});

test('prune tool returns success message when output is empty', function () {
    PruneTool::$testRunner = fn (int $hours, bool $keepExceptions) => ['deleted' => 12, 'output' => '', 'exitCode' => 0];

    $tool = new PruneTool();
    $repository = Mockery::mock(PrunableRepository::class);
    $response = $tool->handle(new Request([]), $repository);

    expect($response)->toBeInstanceOf(Response::class);
    expect($response->content()->toArray()['text'] ?? '')->toContain('Telescope entries pruned successfully');
    expect($response->content()->toArray()['text'] ?? '')->toContain('24 hours');
    expect($response->content()->toArray()['text'] ?? '')->toContain('12 entries');
});

test('prune tool returns error when command fails', function () {
    PruneTool::$testRunner = fn (int $hours, bool $keepExceptions) => ['output' => '', 'exitCode' => 1, 'message' => 'Command failed'];

    $tool = new PruneTool();
    $repository = Mockery::mock(PrunableRepository::class);
    $response = $tool->handle(new Request([]), $repository);

    expect($response->isError())->toBeTrue();
    expect($response->content()->toArray()['text'] ?? '')->toContain('Error: Command failed');
});

test('prune tool uses prunable repository in runtime mode', function () {
    $repository = Mockery::mock(PrunableRepository::class);
    $repository->shouldReceive('prune')
        ->once()
        ->withArgs(function ($before, $keepExceptions) {
            expect($before->diffInMinutes(now()->subHours(24)))->toBeLessThan(1);
            expect($keepExceptions)->toBeFalse();

            return true;
        })
        ->andReturn(7);

    $tool = new PruneTool();
    $response = $tool->handle(new Request([]), $repository);

    expect($response->content()->toArray()['text'] ?? '')->toContain('7 entries older than 24 hours were deleted');
});
