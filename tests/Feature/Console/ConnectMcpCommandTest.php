<?php

use Illuminate\Support\Facades\Log;
use LucianoTonet\TelescopeMcp\Console\ConnectMcpCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

test('connect command handle returns success with default url', function () {
    Log::shouldReceive('info')
        ->once()
        ->with('MCP Connect command executed for URL: http://127.0.0.1:8000/telescope-mcp');

    $command = new ConnectMcpCommand();
    $command->setLaravel($this->app);
    $exitCode = $command->run(new ArrayInput([]), new BufferedOutput());

    expect($exitCode)->toBe(0);
});

test('connect command handle returns success with custom url', function () {
    $url = 'https://app.example.com/mcp';

    Log::shouldReceive('info')
        ->once()
        ->with("MCP Connect command executed for URL: {$url}");

    $command = new ConnectMcpCommand();
    $command->setLaravel($this->app);
    $output = new BufferedOutput();
    $exitCode = $command->run(new ArrayInput(['url' => $url]), $output);

    expect($exitCode)->toBe(0);
    expect($output->fetch())->toContain($url);
});

test('connect command output contains instructions', function () {
    Log::shouldReceive('info')->once();

    $command = new ConnectMcpCommand();
    $command->setLaravel($this->app);
    $output = new BufferedOutput();
    $command->run(new ArrayInput([]), $output);

    $text = $output->fetch();
    expect($text)->toContain('Telescope MCP Server is ready.');
    expect($text)->toContain('npx -y mcp-remote');
    expect($text)->toContain('Happy debugging!');
});
