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
 * Tool for interacting with command executions recorded by Telescope
 */
class CommandsTool extends Tool
{
    protected string $name = 'commands';
    protected string $title = 'Telescope Commands';
    protected string $description = 'Lists and analyzes command executions recorded by Telescope.';

    public function handle(Request $request, EntriesRepository $repository): Response
    {
        try {
            if ($id = $request->get('id')) {
                return $this->getCommandDetails($id, $repository);
            }
            return $this->listCommands($request, $repository);
        } catch (\Exception $e) {
            return Response::error('Error: ' . $e->getMessage());
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('ID of specific command execution'),
            'limit' => $schema->integer()->default(50)->description('Maximum number of command executions to return'),
            'command' => $schema->string()->description('Filter by command name'),
            'status' => $schema->string()->enum(['success', 'error'])->description('Filter by execution status'),
        ];
    }

    protected function listCommands(Request $request, EntriesRepository $repository): Response
    {
        $limit = min($request->integer('limit', 50), 100);
        $options = new EntryQueryOptions();
        $options->limit($limit);

        if ($command = $request->get('command')) {
            $options->tag('command:' . $command);
        }
        if ($status = $request->get('status')) {
            $options->tag('status:' . $status);
        }

        $entries = $repository->get(EntryType::COMMAND, $options);
        if (empty($entries)) {
            return Response::text("No command executions found.");
        }

        $commands = [];
        foreach ($entries as $entry) {
            $content = is_array($entry->content) ? $entry->content : [];
            $createdAt = isset($content['created_at']) ? DateFormatter::format($content['created_at']) : 'Unknown';

            $commands[] = [
                'id' => $entry->id,
                'command' => $content['command'] ?? 'Unknown',
                'exit_code' => $content['exit_code'] ?? 0,
                'arguments' => isset($content['arguments']) ? implode(' ', $content['arguments']) : '',
                'created_at' => $createdAt,
            ];
        }

        $table = "Command Executions:\n\n";
        $table .= sprintf(
            "%-5s %-20s %-40s %-10s %-20s\n",
            "ID",
            "Command",
            "Arguments/Options",
            "Status",
            "Created At"
        );
        $table .= str_repeat("-", 100) . "\n";

        foreach ($commands as $cmd) {
            $params = $this->safeString(trim($cmd['arguments']));
            if (strlen($params) > 40) {
                $params = substr($params, 0, 37) . "...";
            }

            $statusStr = $cmd['exit_code'] === 0 ? 'Success' : ($cmd['exit_code'] === null ? 'Unknown' : 'Error');
            if ($statusStr === 'Error') {
                $statusStr .= " [{$cmd['exit_code']}]";
            }

            $table .= sprintf(
                "%-5s %-20s %-40s %-10s %-20s\n",
                $cmd['id'],
                $cmd['command'],
                $params,
                $statusStr,
                $cmd['created_at']
            );
        }

        $table .= "\n\n--- JSON Data ---\n" . json_encode([
            'total' => count($commands),
            'commands' => $commands,
        ], JSON_PRETTY_PRINT);

        return Response::text($table);
    }

    protected function getCommandDetails(string $id, EntriesRepository $repository): Response
    {
        $entry = $repository->find($id);
        if (!$entry) {
            return Response::error("Command execution not found: {$id}");
        }

        $content = is_array($entry->content) ? $entry->content : [];
        $createdAt = isset($content['created_at']) ? DateFormatter::format($content['created_at']) : 'Unknown';

        $output = "Command Details:\n\n";
        $output .= "ID: {$entry->id}\n";
        $output .= "Command: " . ($content['command'] ?? 'Unknown') . "\n";
        $output .= "Exit Code: " . ($content['exit_code'] ?? 0) . "\n";
        $output .= "Created At: {$createdAt}\n\n";

        if (!empty($content['arguments'])) {
            $output .= "Arguments:\n";
            foreach ($content['arguments'] as $arg) {
                $output .= "  - {$arg}\n";
            }
            $output .= "\n";
        }

        if (!empty($content['options'])) {
            $output .= "Options:\n";
            foreach ($content['options'] as $key => $value) {
                if (is_bool($value)) {
                    $output .= $value ? "  --{$key}\n" : '';
                } else {
                    $output .= "  --{$key}=" . (is_array($value) ? implode(',', $value) : $value) . "\n";
                }
            }
            $output .= "\n";
        }

        if (!empty($content['output'])) {
            $output .= "Output:\n" . $content['output'] . "\n";
        }

        $output .= "\n\n--- JSON Data ---\n" . json_encode([
            'id' => $entry->id,
            'command' => $content['command'] ?? 'Unknown',
            'exit_code' => $content['exit_code'] ?? 0,
            'arguments' => $content['arguments'] ?? [],
            'options' => $content['options'] ?? [],
            'output' => $content['output'] ?? null,
            'created_at' => $createdAt,
        ], JSON_PRETTY_PRINT);

        return Response::text($output);
    }

    protected function safeString($value): string
    {
        if (is_array($value)) {
            return json_encode($value, JSON_PRETTY_PRINT);
        } elseif (is_object($value)) {
            return json_encode($value, JSON_PRETTY_PRINT);
        } elseif (is_null($value)) {
            return '';
        } elseif (is_bool($value)) {
            return $value ? 'true' : 'false';
        } elseif (is_numeric($value)) {
            return (string) $value;
        } else {
            return (string) $value;
        }
    }
}
