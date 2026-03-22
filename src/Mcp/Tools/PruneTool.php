<?php

namespace LucianoTonet\TelescopeMcp\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Telescope\Contracts\PrunableRepository;

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
     * Optional callback for testing: callable(int $hours, bool $keepExceptions): array{deleted?: int, output?: string, exitCode?: int, message?: string}
     *
     * @var callable|null
     */
    public static $testRunner = null;

    public function handle(Request $request, PrunableRepository $repository): Response
    {
        try {
            $hours = $request->integer('hours', 24);
            $keepExceptions = $request->boolean('keep_exceptions', false);

            if (static::$testRunner !== null) {
                $result = (static::$testRunner)($hours, $keepExceptions);
                $output = $result['output'] ?? '';
                $exitCode = $result['exitCode'] ?? 0;
                if ($exitCode !== 0) {
                    throw new \Exception($result['message'] ?? 'Command failed');
                }

                if ($output !== '') {
                    return Response::text($output);
                }

                return Response::text($this->successMessage(
                    $hours,
                    (int) ($result['deleted'] ?? 0),
                    $keepExceptions
                ));
            }

            $deleted = $repository->prune(now()->subHours($hours), $keepExceptions);

            return Response::text($this->successMessage($hours, $deleted, $keepExceptions));
        } catch (\Exception $e) {
            return Response::error('Error: ' . $e->getMessage());
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'hours' => $schema->integer()->default(24)->description('Number of hours to keep (entries older than this will be deleted)'),
            'keep_exceptions' => $schema->boolean()->default(false)->description('Keep exception entries while pruning older Telescope data'),
        ];
    }

    protected function successMessage(int $hours, int $deleted, bool $keepExceptions): string
    {
        $exceptionNote = $keepExceptions ? ' Exception entries were preserved.' : '';

        return "Telescope entries pruned successfully. {$deleted} entries older than {$hours} hours were deleted.{$exceptionNote}";
    }
}
