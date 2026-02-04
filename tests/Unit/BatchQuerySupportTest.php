<?php

use Laravel\Telescope\Contracts\EntriesRepository;
use Laravel\Telescope\EntryResult;
use LucianoTonet\TelescopeMcp\MCP\Tools\LogsTool;

test('hasRequestId returns true when request_id is provided', function () {
    $repository = Mockery::mock(EntriesRepository::class);
    $tool = new LogsTool($repository);

    $reflection = new \ReflectionClass($tool);
    $method = $reflection->getMethod('hasRequestId');
    $method->setAccessible(true);

    expect($method->invoke($tool, ['request_id' => 'abc-123']))->toBeTrue();
    expect($method->invoke($tool, ['request_id' => 'xyz']))->toBeTrue();
});

test('hasRequestId returns false when request_id is missing or empty', function () {
    $repository = Mockery::mock(EntriesRepository::class);
    $tool = new LogsTool($repository);

    $reflection = new \ReflectionClass($tool);
    $method = $reflection->getMethod('hasRequestId');
    $method->setAccessible(true);

    expect($method->invoke($tool, []))->toBeFalse();
    expect($method->invoke($tool, ['request_id' => '']))->toBeFalse();
    expect($method->invoke($tool, ['other' => 'value']))->toBeFalse();
});

test('getBatchIdForEntry returns batchId when entry exists', function () {
    $repository = Mockery::mock(EntriesRepository::class);

    // Mock DB query
    \Illuminate\Support\Facades\DB::shouldReceive('connection')
        ->andReturnSelf();
    \Illuminate\Support\Facades\DB::shouldReceive('table')
        ->with('telescope_entries')
        ->andReturnSelf();
    \Illuminate\Support\Facades\DB::shouldReceive('where')
        ->with('uuid', 'entry-1')
        ->once()
        ->andReturnSelf();
    \Illuminate\Support\Facades\DB::shouldReceive('first')
        ->once()
        ->andReturn((object) ['batch_id' => 'batch-123']);

    $tool = new LogsTool();

    $reflection = new \ReflectionClass($tool);
    $method = $reflection->getMethod('getBatchIdForEntry');
    $method->setAccessible(true);

    expect($method->invoke($tool, 'entry-1'))->toBe('batch-123');
});

test('getBatchIdForEntry returns null when entry not found', function () {
    $repository = Mockery::mock(EntriesRepository::class);

    // Mock DB query returning null
    \Illuminate\Support\Facades\DB::shouldReceive('connection')
        ->andReturnSelf();
    \Illuminate\Support\Facades\DB::shouldReceive('table')
        ->with('telescope_entries')
        ->andReturnSelf();
    \Illuminate\Support\Facades\DB::shouldReceive('where')
        ->with('uuid', 'non-existent')
        ->once()
        ->andReturnSelf();
    \Illuminate\Support\Facades\DB::shouldReceive('first')
        ->once()
        ->andReturn(null);

    $tool = new LogsTool();

    $reflection = new \ReflectionClass($tool);
    $method = $reflection->getMethod('getBatchIdForEntry');
    $method->setAccessible(true);

    expect($method->invoke($tool, 'non-existent'))->toBeNull();
});

test('getBatchIdForRequest returns batch id when request entry exists', function () {
    $repository = Mockery::mock(EntriesRepository::class);

    // Mock DB query
    \Illuminate\Support\Facades\DB::shouldReceive('connection')
        ->andReturnSelf();
    \Illuminate\Support\Facades\DB::shouldReceive('table')
        ->with('telescope_entries')
        ->andReturnSelf();
    \Illuminate\Support\Facades\DB::shouldReceive('where')
        ->with('uuid', 'req-1')
        ->once()
        ->andReturnSelf();
    \Illuminate\Support\Facades\DB::shouldReceive('first')
        ->once()
        ->andReturn((object) ['batch_id' => 'batch-456']);

    $tool = new LogsTool();

    $reflection = new \ReflectionClass($tool);
    $method = $reflection->getMethod('getBatchIdForRequest');
    $method->setAccessible(true);

    expect($method->invoke($tool, 'req-1'))->toBe('batch-456');
});

test('getBatchIdForRequest returns null when request not found', function () {
    $repository = Mockery::mock(EntriesRepository::class);

    // Mock DB query returning null
    \Illuminate\Support\Facades\DB::shouldReceive('connection')
        ->andReturnSelf();
    \Illuminate\Support\Facades\DB::shouldReceive('table')
        ->with('telescope_entries')
        ->andReturnSelf();
    \Illuminate\Support\Facades\DB::shouldReceive('where')
        ->with('uuid', 'missing')
        ->once()
        ->andReturnSelf();
    \Illuminate\Support\Facades\DB::shouldReceive('first')
        ->once()
        ->andReturn(null);

    $tool = new LogsTool();

    $reflection = new \ReflectionClass($tool);
    $method = $reflection->getMethod('getBatchIdForRequest');
    $method->setAccessible(true);

    expect($method->invoke($tool, 'missing'))->toBeNull();
});
