<?php

namespace LucianoTonet\TelescopeMcp\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

use Laravel\Telescope\Contracts\EntriesRepository;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\Storage\EntryQueryOptions;
use LucianoTonet\TelescopeMcp\Support\DateFormatter;

/**
 * Tool for interacting with scheduled tasks recorded by Telescope
 */
class ScheduleTool extends Tool
{
    protected string $name = 'schedule';
    protected string $title = 'Telescope Schedule';
    protected string $description = 'Lists and analyzes scheduled tasks recorded by Telescope.';

    public function handle(Request $request, EntriesRepository $repository): Response
    {
        try {
            if ($id = $request->get('id')) {
                return $this->getScheduleDetails($id, $repository);
            }
            return $this->listScheduledTasks($request, $repository);
        } catch (\Exception $e) {
            return Response::error('Error: ' . $e->getMessage());
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('ID of specific scheduled task'),
            'limit' => $schema->integer()->default(50)->description('Max tasks'),
        ];
    }

    protected function listScheduledTasks(Request $request, EntriesRepository $repository): Response
    {
        $limit = min($request->integer('limit', 50), 100);
        $options = new EntryQueryOptions();
        $options->limit($limit);

        $entries = $repository->get(EntryType::SCHEDULED_TASK, $options);
        if (empty($entries)) return Response::text("No scheduled tasks found.");

        $tasks = [];
        foreach ($entries as $entry) {
            $content = is_array($entry->content) ? $entry->content : [];
            $exitCode = $content['exit_code'] ?? null;
            $status = $exitCode === 0 ? 'Success' : ($exitCode === null ? 'Running' : 'Failed');

            $tasks[] = [
                'id' => $entry->id,
                'command' => $content['command'] ?? 'Unknown',
                'expression' => $content['expression'] ?? 'Unknown',
                'description' => $content['description'] ?? '',
                'status' => $status,
                'created_at' => isset($content['created_at']) ? DateFormatter::format($content['created_at']) : 'Unknown'
            ];
        }

        $table = "Scheduled Tasks:\n\n";
        $table .= sprintf("%-5s %-30s %-15s %-30s %-10s %-20s\n",
            "ID", "Command", "Expression", "Description", "Status", "Created At");
        $table .= str_repeat("-", 120) . "\n";

        foreach ($tasks as $task) {
            $command = strlen($task['command']) > 30 ? substr($task['command'], 0, 27) . "..." : $task['command'];
            $description = strlen($task['description']) > 30 ? substr($task['description'], 0, 27) . "..." : $task['description'];

            $table .= sprintf("%-5s %-30s %-15s %-30s %-10s %-20s\n",
                $task['id'], $command, $task['expression'], $description,
                $task['status'], $task['created_at']);
        }

        $table .= "\n\n--- JSON Data ---\n" . json_encode(['total' => count($tasks), 'tasks' => $tasks], JSON_PRETTY_PRINT);
        return Response::text($table);
    }

    protected function getScheduleDetails(string $id, EntriesRepository $repository): Response
    {
        $entry = $repository->find($id);
        if (!$entry) return Response::error("Scheduled task not found: {$id}");

        $content = is_array($entry->content) ? $entry->content : [];
        $createdAt = isset($content['created_at']) ? DateFormatter::format($content['created_at']) : 'Unknown';

        $exitCode = $content['exit_code'] ?? null;
        $status = $exitCode === 0 ? 'Success' : ($exitCode === null ? 'Running' : 'Failed');

        $output = "Scheduled Task Details:\n\n";
        $output .= "ID: {$entry->id}\n";
        $output .= "Command: " . ($content['command'] ?? 'Unknown') . "\n";
        $output .= "Expression: " . ($content['expression'] ?? 'Unknown') . "\n";
        $output .= "Description: " . ($content['description'] ?? 'None') . "\n";
        $output .= "Status: {$status}\n";

        if ($exitCode !== null) {
            $output .= "Exit Code: {$exitCode}\n";
        }

        $output .= "Created At: {$createdAt}\n\n";

        if (!empty($content['output'])) {
            $output .= "Command Output:\n" . $content['output'] . "\n\n";
        }

        $jsonData = [
            'id' => $entry->id,
            'command' => $content['command'] ?? 'Unknown',
            'expression' => $content['expression'] ?? 'Unknown',
            'description' => $content['description'] ?? '',
            'status' => $status,
            'exit_code' => $exitCode,
            'created_at' => $createdAt,
            'output' => $content['output'] ?? null,
        ];

        $output .= "\n\n--- JSON Data ---\n" . json_encode($jsonData, JSON_PRETTY_PRINT);
        return Response::text($output);
    }
}
