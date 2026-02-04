<?php

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Telescope\Contracts\EntriesRepository;
use Laravel\Telescope\EntryResult;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\Storage\EntryQueryOptions;
use LucianoTonet\TelescopeMcp\Mcp\Tools\NotificationsTool;

test('notifications tool lists no notifications when repository returns empty', function () {
    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('get')
        ->with(EntryType::NOTIFICATION, Mockery::type(EntryQueryOptions::class))
        ->once()
        ->andReturn([]);

    $tool = new NotificationsTool();
    $response = $tool->handle(new Request([]), $repository);

    expect($response)->toBeInstanceOf(Response::class);
    expect($response->content()->toArray()['text'] ?? '')->toContain('No notifications found');
});

test('notifications tool lists notifications when repository returns entries', function () {
    $entry = new EntryResult(
        'notif-1',
        null,
        'batch-1',
        'notification',
        null,
        [
            'channel' => 'mail',
            'notification' => 'App\Notifications\TestNotification',
            'notifiable' => 'user@example.com',
            'created_at' => now()->toIso8601String(),
        ],
        now(),
        []
    );

    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('get')
        ->with(EntryType::NOTIFICATION, Mockery::type(EntryQueryOptions::class))
        ->once()
        ->andReturn(collect([$entry]));

    $tool = new NotificationsTool();
    $response = $tool->handle(new Request([]), $repository);

    expect($response->content()->toArray()['text'] ?? '')->toContain('Notifications');
    expect($response->content()->toArray()['text'] ?? '')->toContain('mail');
});

test('notifications tool returns details when id is provided', function () {
    $entry = new EntryResult(
        'notif-123',
        null,
        'batch-123',
        'notification',
        null,
        [
            'channel' => 'database',
            'notification' => 'App\Notifications\DetailNotification',
            'notifiable' => 'user@example.com',
            'data' => ['key' => 'value'],
            'created_at' => now()->toIso8601String(),
        ],
        now(),
        []
    );

    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('find')->with('notif-123')->once()->andReturn($entry);

    // Mock DB for getBatchSummary (since include_related defaults to true)
    \Illuminate\Support\Facades\DB::shouldReceive('connection')
        ->andReturnSelf();
    \Illuminate\Support\Facades\DB::shouldReceive('table')
        ->with('telescope_entries')
        ->andReturnSelf();
    \Illuminate\Support\Facades\DB::shouldReceive('where')
        ->with('batch_id', 'batch-123')
        ->once()
        ->andReturnSelf();
    \Illuminate\Support\Facades\DB::shouldReceive('select')
        ->once()
        ->andReturnSelf();
    \Illuminate\Support\Facades\DB::shouldReceive('groupBy')
        ->with('type')
        ->once()
        ->andReturnSelf();
    \Illuminate\Support\Facades\DB::shouldReceive('get')
        ->once()
        ->andReturn(collect([]));
    \Illuminate\Support\Facades\DB::shouldReceive('raw')
        ->with('count(*) as count')
        ->once()
        ->andReturn('count(*) as count');

    $tool = new NotificationsTool();
    $response = $tool->handle(new Request(['id' => 'notif-123']), $repository);

    expect($response->content()->toArray()['text'] ?? '')->toContain('Notification Details');
    expect($response->content()->toArray()['text'] ?? '')->toContain('database');
});

test('notifications tool returns error when id not found', function () {
    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('find')
        ->with('non-existent')
        ->once()
        ->andThrow(new \Exception('Not found'));

    $tool = new NotificationsTool();
    $response = $tool->handle(new Request(['id' => 'non-existent']), $repository);

    expect($response->isError())->toBeTrue();
});

test('notifications tool filters by channel', function () {
    $entry = new EntryResult(
        'notif-1',
        null,
        'batch-1',
        'notification',
        null,
        [
            'channel' => 'slack',
            'notification' => 'App\Notifications\SlackNotification',
            'notifiable' => 'team@example.com',
            'created_at' => now()->toIso8601String(),
        ],
        now(),
        []
    );

    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('get')
        ->with(EntryType::NOTIFICATION, Mockery::type(EntryQueryOptions::class))
        ->once()
        ->andReturn(collect([$entry]));

    $tool = new NotificationsTool();
    $response = $tool->handle(new Request(['channel' => 'slack']), $repository);

    expect($response->content()->toArray()['text'] ?? '')->toContain('slack');
});

test('notifications tool handles include_related parameter', function () {
    $entry = new EntryResult(
        'notif-123',
        null,
        'batch-123',
        'notification',
        null,
        [
            'channel' => 'mail',
            'notification' => 'App\Notifications\TestNotification',
            'notifiable' => 'user@example.com',
            'created_at' => now()->toIso8601String(),
        ],
        now(),
        []
    );

    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('find')->with('notif-123')->once()->andReturn($entry);

    // Test with include_related=false (no DB calls should be made)
    $tool = new NotificationsTool();
    $response = $tool->handle(new Request(['id' => 'notif-123', 'include_related' => false]), $repository);

    expect($response->content()->toArray()['text'] ?? '')->toContain('Notification Details');
    expect($response->content()->toArray()['text'] ?? '')->not->toContain('Related Entries');
});

test('notifications tool includes related entries when include_related is true', function () {
    $entry = new EntryResult(
        'notif-456',
        null,
        'batch-456',
        'notification',
        null,
        [
            'channel' => 'database',
            'notification' => 'App\Notifications\AnotherNotification',
            'notifiable' => 'admin@example.com',
            'created_at' => now()->toIso8601String(),
        ],
        now(),
        []
    );

    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('find')->with('notif-456')->once()->andReturn($entry);

    // Mock DB for getBatchSummary when include_related=true (default)
    \Illuminate\Support\Facades\DB::shouldReceive('connection')
        ->andReturnSelf();
    \Illuminate\Support\Facades\DB::shouldReceive('table')
        ->with('telescope_entries')
        ->andReturnSelf();
    \Illuminate\Support\Facades\DB::shouldReceive('where')
        ->with('batch_id', 'batch-456')
        ->once()
        ->andReturnSelf();
    \Illuminate\Support\Facades\DB::shouldReceive('select')
        ->once()
        ->andReturnSelf();
    \Illuminate\Support\Facades\DB::shouldReceive('groupBy')
        ->with('type')
        ->once()
        ->andReturnSelf();
    \Illuminate\Support\Facades\DB::shouldReceive('get')
        ->once()
        ->andReturn(collect([]));
    \Illuminate\Support\Facades\DB::shouldReceive('raw')
        ->with('count(*) as count')
        ->once()
        ->andReturn('count(*) as count');

    // Test with include_related=true (default behavior)
    $tool = new NotificationsTool();
    $response = $tool->handle(new Request(['id' => 'notif-456']), $repository);

    expect($response->content()->toArray()['text'] ?? '')->toContain('Notification Details');
    expect($response->content()->toArray()['text'] ?? '')->toContain('Related Entries');
});
