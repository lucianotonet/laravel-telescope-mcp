<?php

use Illuminate\Support\Facades\File;
use LucianoTonet\TelescopeMcp\Console\InstallMcpCommand;

beforeEach(function () {
    $this->command = new InstallMcpCommand();
});

test('install command expandPath expands tilde to home directory', function () {
    $home = $_SERVER['HOME'] ?? $_SERVER['USERPROFILE'] ?? getenv('HOME') ?: sys_get_temp_dir();
    $method = (new \ReflectionClass(InstallMcpCommand::class))->getMethod('expandPath');
    $method->setAccessible(true);

    $result = $method->invoke($this->command, '~/.cursor/mcp.json');

    expect($result)->toContain('.cursor');
    expect($result)->toContain('mcp.json');
});

test('install command loadMcpConfig returns empty structure when file does not exist', function () {
    $method = (new \ReflectionClass(InstallMcpCommand::class))->getMethod('loadMcpConfig');
    $method->setAccessible(true);

    $path = sys_get_temp_dir() . '/telescope-mcp-test-' . uniqid() . '-nonexistent.json';
    $result = $method->invoke($this->command, $path);

    expect($result)->toBe(['mcpServers' => []]);
});

test('install command loadMcpConfig returns parsed config when valid json exists', function () {
    $method = (new \ReflectionClass(InstallMcpCommand::class))->getMethod('loadMcpConfig');
    $method->setAccessible(true);

    $path = sys_get_temp_dir() . '/telescope-mcp-test-' . uniqid() . '.json';
    File::put($path, '{"mcpServers":{"other":{}}}');

    try {
        $result = $method->invoke($this->command, $path);
        expect($result)->toHaveKey('mcpServers');
        expect($result['mcpServers'])->toHaveKey('other');
    } finally {
        if (File::exists($path)) {
            File::delete($path);
        }
    }
});

test('install command loadMcpConfig returns empty structure and creates backup when json is invalid', function () {
    $command = $this->app->make(InstallMcpCommand::class);
    $components = Mockery::mock();
    $components->shouldReceive('warn')->once()->with('Existing config is invalid JSON. Creating backup...');
    $reflection = new \ReflectionClass(InstallMcpCommand::class);
    $prop = $reflection->getProperty('components');
    $prop->setAccessible(true);
    $prop->setValue($command, $components);

    $method = $reflection->getMethod('loadMcpConfig');
    $method->setAccessible(true);

    $path = sys_get_temp_dir() . '/telescope-mcp-test-' . uniqid() . '.json';
    File::put($path, 'invalid { json');

    try {
        $result = $method->invoke($command, $path);
        expect($result)->toBe(['mcpServers' => []]);
        expect(File::exists($path . '.backup'))->toBeTrue();
    } finally {
        if (File::exists($path)) {
            File::delete($path);
        }
        if (File::exists($path . '.backup')) {
            File::delete($path . '.backup');
        }
    }
});

test('install command getMcpServerConfig returns expected structure', function () {
    $method = (new \ReflectionClass(InstallMcpCommand::class))->getMethod('getMcpServerConfig');
    $method->setAccessible(true);

    $config = $method->invoke($this->command);

    expect($config)->toHaveKeys(['command', 'args', 'cwd', 'env']);
    expect($config['command'])->toBe('php');
    expect($config['args'])->toContain('artisan');
    expect($config['args'])->toContain('telescope-mcp:server');
    expect($config['env'])->toHaveKey('APP_ENV');
});

test('install command getMcpServerConfigToml returns valid toml block', function () {
    $getConfig = (new \ReflectionClass(InstallMcpCommand::class))->getMethod('getMcpServerConfig');
    $getConfig->setAccessible(true);
    $getToml = (new \ReflectionClass(InstallMcpCommand::class))->getMethod('getMcpServerConfigToml');
    $getToml->setAccessible(true);

    $config = $getConfig->invoke($this->command);
    $toml = $getToml->invoke($this->command, $config);

    expect($toml)->toContain('[mcpServers.laravel-telescope]');
    expect($toml)->toContain('command = ');
    expect($toml)->toContain('args = ');
    expect($toml)->toContain('cwd = ');
});

test('install command is registered in application', function () {
    $commands = $this->app->make(\Illuminate\Contracts\Console\Kernel::class)->all();
    expect(array_key_exists('telescope-mcp:install', $commands))->toBeTrue();
});
