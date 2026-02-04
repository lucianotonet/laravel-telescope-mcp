<?php

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Telescope\Contracts\EntriesRepository;
use Laravel\Telescope\EntryResult;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\Storage\EntryQueryOptions;
use LucianoTonet\TelescopeMcp\Mcp\Tools\ModelsTool;

test('models tool lists no model operations when repository returns empty', function () {
    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('get')
        ->with(EntryType::MODEL, Mockery::type(EntryQueryOptions::class))
        ->once()
        ->andReturn([]);

    $tool = new ModelsTool();
    $response = $tool->handle(new Request([]), $repository);

    expect($response)->toBeInstanceOf(Response::class);
    expect($response->content()->toArray()['text'] ?? '')->toContain('No model operations found');
});

test('models tool lists model operations when repository returns entries', function () {
    $entry = new EntryResult(
        'model-1',
        null,
        'batch-1',
        'model',
        null,
        [
            'action' => 'created',
            'model' => 'App\Models\User',
            'model_id' => '1',
            'created_at' => now()->toIso8601String(),
        ],
        now(),
        []
    );

    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('get')
        ->with(EntryType::MODEL, Mockery::type(EntryQueryOptions::class))
        ->once()
        ->andReturn(collect([$entry]));

    $tool = new ModelsTool();
    $response = $tool->handle(new Request([]), $repository);

    expect($response->content()->toArray()['text'] ?? '')->toContain('Model Operations');
    expect($response->content()->toArray()['text'] ?? '')->toContain('App\Models\User');
});

test('models tool returns details when id is provided', function () {
    $entry = new EntryResult(
        'model-123',
        null,
        'batch-123',
        'model',
        null,
        [
            'action' => 'updated',
            'model' => 'App\Models\Post',
            'model_id' => '42',
            'attributes' => ['title' => 'New Title'],
            'changes' => ['title' => ['old' => 'Old Title', 'new' => 'New Title']],
            'created_at' => now()->toIso8601String(),
        ],
        now(),
        []
    );

    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('find')->with('model-123')->once()->andReturn($entry);

    // Mock do DB para getBatchSummary
    \Illuminate\Support\Facades\DB::shouldReceive('connection')
        ->andReturnSelf();
    \Illuminate\Support\Facades\DB::shouldReceive('table')
        ->with('telescope_entries')
        ->andReturnSelf();
    \Illuminate\Support\Facades\DB::shouldReceive('where')
        ->andReturnSelf();
    \Illuminate\Support\Facades\DB::shouldReceive('select')
        ->andReturnSelf();
    \Illuminate\Support\Facades\DB::shouldReceive('groupBy')
        ->andReturnSelf();
    \Illuminate\Support\Facades\DB::shouldReceive('get')
        ->andReturn(collect([]));
    \Illuminate\Support\Facades\DB::shouldReceive('raw')
        ->andReturn('count(*) as count');

    $tool = new ModelsTool();
    $response = $tool->handle(new Request(['id' => 'model-123', 'include_related' => false]), $repository);

    expect($response->content()->toArray()['text'] ?? '')->toContain('Model Operation Details');
    expect($response->content()->toArray()['text'] ?? '')->toContain('App\Models\Post');
});

test('models tool returns error when id not found', function () {
    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('find')
        ->with('non-existent')
        ->once()
        ->andThrow(new \Exception('Not found'));

    $tool = new ModelsTool();
    $response = $tool->handle(new Request(['id' => 'non-existent']), $repository);

    expect($response->isError())->toBeTrue();
});

test('models tool filters by action', function () {
    $entry = new EntryResult(
        'model-1',
        null,
        'batch-1',
        'model',
        null,
        [
            'action' => 'deleted',
            'model' => 'App\Models\Comment',
            'model_id' => '5',
            'created_at' => now()->toIso8601String(),
        ],
        now(),
        []
    );

    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('get')
        ->with(EntryType::MODEL, Mockery::type(EntryQueryOptions::class))
        ->once()
        ->andReturn(collect([$entry]));

    $tool = new ModelsTool();
    $response = $tool->handle(new Request(['action' => 'deleted']), $repository);

    expect($response->content()->toArray()['text'] ?? '')->toContain('deleted');
});

test('models tool lists models for request when request_id is provided', function () {
    $repository = Mockery::mock(EntriesRepository::class);

    // Mock DB for getBatchIdForEntry
    \Illuminate\Support\Facades\DB::shouldReceive('connection')
        ->andReturnSelf();
    \Illuminate\Support\Facades\DB::shouldReceive('table')
        ->with('telescope_entries')
        ->andReturnSelf();

    // First call: getBatchIdForEntry
    \Illuminate\Support\Facades\DB::shouldReceive('where')
        ->with('uuid', 'req-123')
        ->once()
        ->andReturnSelf();
    \Illuminate\Support\Facades\DB::shouldReceive('first')
        ->once()
        ->andReturn((object) ['batch_id' => 'batch-123']);

    // Second call: getEntriesByBatchId
    \Illuminate\Support\Facades\DB::shouldReceive('where')
        ->with('batch_id', 'batch-123')
        ->once()
        ->andReturnSelf();
    \Illuminate\Support\Facades\DB::shouldReceive('where')
        ->with('type', 'model')
        ->once()
        ->andReturnSelf();
    \Illuminate\Support\Facades\DB::shouldReceive('orderBy')
        ->with('sequence', 'asc')
        ->once()
        ->andReturnSelf();
    \Illuminate\Support\Facades\DB::shouldReceive('limit')
        ->with(50)
        ->once()
        ->andReturnSelf();
    \Illuminate\Support\Facades\DB::shouldReceive('get')
        ->once()
        ->andReturn(collect([]));

    $tool = new ModelsTool();
    $response = $tool->handle(new Request(['request_id' => 'req-123']), $repository);

    expect($response)->toBeInstanceOf(Response::class);
    expect($response->content()->toArray()['text'] ?? '')->toContain('No model operations found for request');
});
