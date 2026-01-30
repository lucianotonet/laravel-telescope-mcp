<?php

namespace LucianoTonet\TelescopeMcp\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GenerateBoostToolsCommand extends Command
{
    protected $signature = 'telescope-mcp:generate-boost-tools';
    protected $description = 'Generate Boost tool wrappers for all Telescope MCP tools';

    protected array $tools = [
        'Requests',
        'Logs',
        'Exceptions',
        'Batches',
        'Cache',
        'Commands',
        'Dumps',
        'Events',
        'Gates',
        'HttpClient',
        'Jobs',
        'Mail',
        'Models',
        'Notifications',
        'Queries',
        'Redis',
        'Schedule',
        'Views',
        'Prune',
    ];

    public function handle(): int
    {
        $this->info('Generating Boost tool wrappers...');

        $basePath = base_path('vendor/lucianotonet/laravel-telescope-mcp/src/BoostExtension/Tools');
        
        if (!is_dir($basePath)) {
            mkdir($basePath, 0755, true);
        }

        foreach ($this->tools as $tool) {
            $className = "Telescope{$tool}Tool";
            $fileName = "{$className}.php";
            $filePath = "{$basePath}/{$fileName}";

            if (file_exists($filePath)) {
                $this->warn("Skipping {$className} (already exists)");
                continue;
            }

            $content = $this->generateToolClass($tool);
            file_put_contents($filePath, $content);
            
            $this->info("✓ Generated {$className}");
        }

        $this->info("\nAll Boost tool wrappers generated successfully!");
        $this->warn("\nDon't forget to register them in TelescopeBoostServiceProvider::registerBoostTools()");

        return self::SUCCESS;
    }

    protected function generateToolClass(string $toolName): string
    {
        $className = "Telescope{$toolName}Tool";
        $toolNameSnake = Str::snake($toolName);
        
        return <<<PHP
<?php

namespace LucianoTonet\\TelescopeMcp\\BoostExtension\\Tools;

use Illuminate\\Contracts\\JsonSchema\\JsonSchema;
use LucianoTonet\\TelescopeMcp\\BoostExtension\\TelescopeBoostTool;

class {$className} extends TelescopeBoostTool
{
    protected string \$name = 'telescope_{$toolNameSnake}';

    public function description(): string
    {
        return 'Access {$toolName} data from Laravel Telescope';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema \$schema): array
    {
        return [
            'id' => \$schema->string()->description('Get details of a specific entry by ID'),
            'limit' => \$schema->integer()->default(50)->description('Maximum number of entries to return'),
        ];
    }
}

PHP;
    }
}
