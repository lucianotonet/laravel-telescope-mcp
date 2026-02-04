<?php

use Laravel\Mcp\Request;
use Laravel\Telescope\Contracts\EntriesRepository;
use Laravel\Telescope\EntryResult;
use Laravel\Telescope\EntryType;
use LucianoTonet\TelescopeMcp\Mcp\Tools\QueriesTool;
use LucianoTonet\TelescopeMcp\Mcp\Tools\RequestsTool;
use Illuminate\Support\Facades\DB;

test('query tool includes source location and backtrace in details', function () {
    $repository = Mockery::mock(EntriesRepository::class);

    $queryEntry = new EntryResult(
        'q-1',
        null,
        'batch-1',
        EntryType::QUERY,
        null,
        [
            'sql' => 'select * from users',
            'time' => 10.5,
            'connection' => 'mysql',
            'file' => 'app/Http/Controllers/UserController.php',
            'line' => 42,
            'backtrace' => [
                ['file' => 'app/Http/Controllers/UserController.php', 'line' => 42],
                ['file' => 'vendor/laravel/framework/src/Illuminate/Routing/Controller.php', 'line' => 54],
            ]
        ],
        now(),
        []
    );

    $repository->shouldReceive('find')->with('q-1')->once()->andReturn($queryEntry);

    $tool = new QueriesTool();
    $response = $tool->handle(new Request(['id' => 'q-1']), $repository);

    $text = $response->content()->toArray()['text'] ?? '';

    expect($text)->toContain('Source: app/Http/Controllers/UserController.php at line 42');
    expect($text)->toContain('Backtrace:');
    expect($text)->toContain('- app/Http/Controllers/UserController.php:42');
});

test('requests tool includes query location in associated queries list', function () {
    $repository = Mockery::mock(EntriesRepository::class);

    $requestEntry = new EntryResult(
        'req-1',
        null,
        'batch-1',
        EntryType::REQUEST,
        null,
        ['method' => 'GET', 'uri' => '/test'],
        now(),
        []
    );

    $repository->shouldReceive('find')->with('req-1')->once()->andReturn($requestEntry);

    // Mock DB for BatchQuerySupport
    DB::shouldReceive('connection')->andReturnSelf();
    DB::shouldReceive('table')->with('telescope_entries')->andReturnSelf();

    // getBatchSummary (called inside getRequestDetails)
    DB::shouldReceive('raw')->andReturn('count(*) as count');
    DB::shouldReceive('where')->with('batch_id', 'batch-1')->once()->andReturnSelf();
    DB::shouldReceive('select')->andReturnSelf();
    DB::shouldReceive('groupBy')->andReturnSelf();
    DB::shouldReceive('get')->once()->andReturn(collect([(object) ['type' => 'query', 'count' => 1]]));
    DB::shouldReceive('pluck')->andReturn(collect(['query' => 1]));

    // getEntriesByBatchId (for includeQueries)
    $queryRow = new stdClass();
    $queryRow->uuid = 'q-1';
    $queryRow->batch_id = 'batch-1';
    $queryRow->type = 'query';
    $queryRow->content = json_encode([
        'sql' => 'select * from users',
        'time' => 5.0,
        'file' => 'app/Models/User.php',
        'line' => 15
    ]);
    $queryRow->created_at = now();
    $queryRow->sequence = 1;

    DB::shouldReceive('where')->with('batch_id', 'batch-1')->once()->andReturnSelf();
    DB::shouldReceive('where')->with('type', 'query')->once()->andReturnSelf();
    DB::shouldReceive('orderBy')->andReturnSelf();
    DB::shouldReceive('limit')->andReturnSelf();
    DB::shouldReceive('get')->once()->andReturn(collect([$queryRow]));

    $tool = new RequestsTool();
    $response = $tool->handle(new Request([
        'id' => 'req-1',
        'include_queries' => true
    ]), $repository);

    $text = $response->content()->toArray()['text'] ?? '';

    expect($text)->toContain('app/Models/User.php:15');
});
