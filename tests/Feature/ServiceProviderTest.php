<?php

use LucianoTonet\TelescopeMcp\TelescopeMcpServiceProvider;
use LucianoTonet\TelescopeMcp\TelescopeBoostServiceProvider;
use LucianoTonet\TelescopeMcp\MCP\TelescopeMcpServer;

test('telescope mcp service provider is registered', function () {
    expect(app()->getProvider(TelescopeMcpServiceProvider::class))
        ->toBeInstanceOf(TelescopeMcpServiceProvider::class);
});

test('telescope boost service provider is registered', function () {
    expect(app()->getProvider(TelescopeBoostServiceProvider::class))
        ->toBeInstanceOf(TelescopeBoostServiceProvider::class);
});

test('telescope mcp server can be resolved from container', function () {
    $server = app(TelescopeMcpServer::class);

    expect($server)->toBeInstanceOf(TelescopeMcpServer::class);
});

test('config is loaded correctly', function () {
    expect(config('telescope-mcp.enabled'))->toBeTrue()
        ->and(config('telescope-mcp.logging.enabled'))->toBeTrue()
        ->and(config('telescope-mcp.logging.channel'))->toBe('stack');
});

test('boost resources directory exists', function () {
    $boostPath = __DIR__.'/../../resources/boost';

    expect(is_dir($boostPath))->toBeTrue()
        ->and(is_dir($boostPath.'/guidelines'))->toBeTrue()
        ->and(is_dir($boostPath.'/skills'))->toBeTrue();
});

test('boost guidelines file exists', function () {
    $guidelinesPath = __DIR__.'/../../resources/boost/guidelines/core.blade.php';

    expect(file_exists($guidelinesPath))->toBeTrue();
});

test('boost skill file exists', function () {
    $skillPath = __DIR__.'/../../resources/boost/skills/telescope-debug/SKILL.md';

    expect(file_exists($skillPath))->toBeTrue();
});

test('boost skill has valid frontmatter', function () {
    $skillPath = __DIR__.'/../../resources/boost/skills/telescope-debug/SKILL.md';
    $content = file_get_contents($skillPath);

    expect($content)
        ->toContain('---')
        ->toContain('name: telescope-debug')
        ->toContain('description:');
});

test('telescope tools are registered in boost config', function () {
    $tools = config('boost.mcp.tools.include');

    expect($tools)->toBeArray();
    
    // Check for some key tools
    expect($tools)->toContain(LucianoTonet\TelescopeMcp\BoostExtension\Tools\TelescopeRequestsTool::class);
    expect($tools)->toContain(LucianoTonet\TelescopeMcp\BoostExtension\Tools\TelescopeExceptionsTool::class);
    expect($tools)->toContain(LucianoTonet\TelescopeMcp\BoostExtension\Tools\TelescopeHttpClientTool::class);
});
