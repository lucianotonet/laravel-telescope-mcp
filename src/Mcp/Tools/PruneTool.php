<?php

namespace LucianoTonet\TelescopeMcp\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Illuminate\Support\Facades\Artisan;

/**
 * Tool for pruning old Telescope entries
 *
 * IMPORTANT: This tool does NOT implement IsReadOnly as it performs destructive operations
 */
class PruneTool extends Tool
{
    protected string $name = 'prune';
    protected string $title = 'Telescope Prune';
    protected string $description = 'Prunes old Telescope entries from the database.';

    /**
     * Optional callback for testing: callable(int $hours): array{output: string, exitCode: int}
     * When set, used instead of Artisan::call().
     *
     * @var callable|null
     */
    public static $testRunner = null;

    public function handle(Request $request): Response
    {
        try {
            $hours = $request->integer('hours', 24);

            if (static::$testRunner !== null) {
                $result = (static::$testRunner)($hours);
                $output = $result['output'] ?? '';
                $exitCode = $result['exitCode'] ?? 0;
                if ($exitCode !== 0) {
                    throw new \Exception($result['message'] ?? 'Command failed');
                }
                return Response::text($output ?: "Telescope entries pruned successfully. Entries older than {$hours} hours have been deleted.");
            }

            Artisan::call('telescope:prune', [
                '--hours' => $hours,
            ]);

            $output = Artisan::output();

            return Response::text($output ?: "Telescope entries pruned successfully. Entries older than {$hours} hours have been deleted.");
        } catch (\Exception $e) {
            return Response::error('Error: ' . $e->getMessage());
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'hours' => $schema->integer()->default(24)->description('Number of hours to keep (entries older than this will be deleted)'),
        ];
    }
}
