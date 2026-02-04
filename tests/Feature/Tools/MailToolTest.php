<?php

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Telescope\Contracts\EntriesRepository;
use Laravel\Telescope\EntryResult;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\Storage\EntryQueryOptions;
use LucianoTonet\TelescopeMcp\Mcp\Tools\MailTool;

test('mail tool lists no emails when repository returns empty', function () {
    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('get')
        ->with(EntryType::MAIL, Mockery::type(EntryQueryOptions::class))
        ->once()
        ->andReturn([]);

    $tool = new MailTool();
    $response = $tool->handle(new Request([]), $repository);

    expect($response)->toBeInstanceOf(Response::class);
    expect($response->content()->toArray()['text'] ?? '')->toContain('No emails found');
});

test('mail tool lists emails when repository returns entries', function () {
    $entry = new EntryResult(
        'mail-1',
        null,
        'batch-1',
        'mail',
        null,
        [
            'subject' => 'Test Email',
            'to' => ['user@example.com'],
            'created_at' => now()->toIso8601String(),
        ],
        now(),
        []
    );

    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('get')
        ->with(EntryType::MAIL, Mockery::type(EntryQueryOptions::class))
        ->once()
        ->andReturn(collect([$entry]));

    $tool = new MailTool();
    $response = $tool->handle(new Request([]), $repository);

    expect($response->content()->toArray()['text'] ?? '')->toContain('Emails');
    expect($response->content()->toArray()['text'] ?? '')->toContain('Test Email');
});

test('mail tool returns details when id is provided', function () {
    $entry = new EntryResult(
        'mail-123',
        null,
        'batch-123',
        'mail',
        null,
        [
            'subject' => 'Detail Email',
            'to' => [['address' => 'user@example.com', 'name' => 'User']],
            'html' => '<p>HTML content</p>',
            'text' => 'Text content',
            'created_at' => now()->toIso8601String(),
        ],
        now(),
        []
    );

    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('find')->with('mail-123')->once()->andReturn($entry);

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

    $tool = new MailTool();
    $response = $tool->handle(new Request(['id' => 'mail-123', 'include_related' => false]), $repository);

    expect($response->content()->toArray()['text'] ?? '')->toContain('Email Details');
    expect($response->content()->toArray()['text'] ?? '')->toContain('Detail Email');
});

test('mail tool returns error when id not found', function () {
    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('find')
        ->with('non-existent')
        ->once()
        ->andThrow(new \Exception('Not found'));

    $tool = new MailTool();
    $response = $tool->handle(new Request(['id' => 'non-existent']), $repository);

    expect($response->isError())->toBeTrue();
});

test('mail tool filters by recipient', function () {
    $entry = new EntryResult(
        'mail-1',
        null,
        'batch-1',
        'mail',
        null,
        [
            'subject' => 'Filtered Email',
            'to' => ['filter@example.com'],
            'created_at' => now()->toIso8601String(),
        ],
        now(),
        []
    );

    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('get')
        ->with(EntryType::MAIL, Mockery::type(EntryQueryOptions::class))
        ->once()
        ->andReturn(collect([$entry]));

    $tool = new MailTool();
    $response = $tool->handle(new Request(['to' => 'filter@example.com']), $repository);

    expect($response->content()->toArray()['text'] ?? '')->toContain('Filtered Email');
});

test('mail tool handles include_related parameter', function () {
    $entry = new EntryResult(
        'mail-123',
        null,
        'batch-123',
        'mail',
        null,
        [
            'subject' => 'Related Email',
            'to' => ['user@example.com'],
            'created_at' => now()->toIso8601String(),
        ],
        now(),
        []
    );

    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('find')->with('mail-123')->once()->andReturn($entry);

    $tool = new MailTool();
    $response = $tool->handle(new Request(['id' => 'mail-123', 'include_related' => false]), $repository);

    expect($response->content()->toArray()['text'] ?? '')->toContain('Email Details');
});
