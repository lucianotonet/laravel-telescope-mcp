<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;



it('generates boost tools files correctly', function () {
    // The command uses base_path('vendor/...')
    // In testbench, base_path() is the temp app root.
    $targetPath = base_path('vendor/lucianotonet/laravel-telescope-mcp/src/BoostExtension/Tools');
    
    // Ensure clean state
    if (File::exists($targetPath)) {
        File::deleteDirectory($targetPath);
    }
    
    $this->artisan('telescope-mcp:generate-boost-tools')
        ->assertExitCode(0);
        
    // Assert directory created
    expect(File::exists($targetPath))->toBeTrue();
    
    // Assert specific files created
    expect(File::exists($targetPath . '/TelescopeRequestsTool.php'))->toBeTrue();
    expect(File::exists($targetPath . '/TelescopeHttpClientTool.php'))->toBeTrue();
    
    // Check content of one file
    $content = File::get($targetPath . '/TelescopeRequestsTool.php');
    expect($content)->toContain("class TelescopeRequestsTool extends TelescopeBoostTool")
        ->toContain("protected string \$name = 'telescope_requests';");
        
    // Check http_client naming
    $httpContent = File::get($targetPath . '/TelescopeHttpClientTool.php');
    // Str::snake('HttpClient') -> http_client
    expect($httpContent)->toContain("protected string \$name = 'telescope_http_client';");
    
    // Cleanup
    if (File::exists(base_path('vendor'))) {
        File::deleteDirectory(base_path('vendor'));
    }
});
