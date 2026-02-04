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
 * Tool for interacting with Redis operations recorded by Telescope
 */
class RedisTool extends Tool
{
    protected string $name = 'redis';
    protected string $title = 'Telescope Redis';
    protected string $description = 'Lists and analyzes Redis operations recorded by Telescope.';

    public function handle(Request $request, EntriesRepository $repository): Response
    {
        try {
            if ($id = $request->get('id')) {
                return $this->getRedisDetails($id, $repository);
            }
            return $this->listRedisOperations($request, $repository);
        } catch (\Exception $e) {
            return Response::error('Error: ' . $e->getMessage());
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('ID of specific Redis operation'),
            'limit' => $schema->integer()->default(50)->description('Max operations'),
            'command' => $schema->string()->description('Filter by Redis command (e.g., GET, SET, DEL)'),
        ];
    }

    protected function listRedisOperations(Request $request, EntriesRepository $repository): Response
    {
        $limit = min($request->integer('limit', 50), 100);
        $options = new EntryQueryOptions();
        $options->limit($limit);

        if ($command = $request->get('command')) {
            $options->tag('command:' . strtoupper($command));
        }

        $entries = $repository->get(EntryType::REDIS, $options);
        if (empty($entries)) return Response::text("No Redis operations found.");

        $operations = [];
        foreach ($entries as $entry) {
            $content = is_array($entry->content) ? $entry->content : [];
            $operations[] = [
                'id' => $entry->id,
                'command' => $content['command'] ?? 'Unknown',
                'parameters' => $content['parameters'] ?? [],
                'duration' => $content['duration'] ?? 0,
                'created_at' => isset($content['created_at']) ? DateFormatter::format($content['created_at']) : 'Unknown'
            ];
        }

        $table = "Redis Operations:\n\n";
        $table .= sprintf("%-5s %-15s %-50s %-10s %-20s\n", "ID", "Command", "Parameters", "Time (ms)", "Created At");
        $table .= str_repeat("-", 120) . "\n";

        foreach ($operations as $op) {
            $params = implode(' ', array_map(function($param) {
                $paramStr = is_string($param) ? $param : json_encode($param);
                return strlen($paramStr) > 20 ? substr($paramStr, 0, 17) . "..." : $paramStr;
            }, $op['parameters']));

            if (strlen($params) > 50) {
                $params = substr($params, 0, 47) . "...";
            }

            $table .= sprintf("%-5s %-15s %-50s %-10s %-20s\n",
                $op['id'], $op['command'], $params,
                number_format($op['duration'], 2), $op['created_at']);
        }

        $table .= "\n\n--- JSON Data ---\n" . json_encode(['total' => count($operations), 'operations' => $operations], JSON_PRETTY_PRINT);
        return Response::text($table);
    }

    protected function getRedisDetails(string $id, EntriesRepository $repository): Response
    {
        $entry = $repository->find($id);
        if (!$entry) return Response::error("Redis operation not found: {$id}");

        $content = is_array($entry->content) ? $entry->content : [];
        $createdAt = isset($content['created_at']) ? DateFormatter::format($content['created_at']) : 'Unknown';

        $output = "Redis Operation Details:\n\n";
        $output .= "ID: {$entry->id}\n";
        $output .= "Command: " . ($content['command'] ?? 'Unknown') . "\n";
        $output .= "Duration: " . number_format(($content['duration'] ?? 0), 2) . " ms\n";
        $output .= "Created At: {$createdAt}\n\n";

        if (!empty($content['parameters'])) {
            $output .= "Parameters:\n";
            foreach ($content['parameters'] as $index => $param) {
                $output .= sprintf("%d: %s\n", $index + 1, is_string($param) ? $param : json_encode($param));
            }
            $output .= "\n";
        }

        if (!empty($content['connection'])) {
            $output .= "Connection: " . $content['connection'] . "\n\n";
        }

        if (isset($content['result'])) {
            $output .= "Result:\n" . json_encode($content['result'], JSON_PRETTY_PRINT) . "\n";
        }

        $jsonData = [
            'id' => $entry->id,
            'command' => $content['command'] ?? 'Unknown',
            'duration' => $content['duration'] ?? 0,
            'created_at' => $createdAt,
            'parameters' => $content['parameters'] ?? [],
            'connection' => $content['connection'] ?? null,
            'result' => $content['result'] ?? null,
        ];

        $output .= "\n\n--- JSON Data ---\n" . json_encode($jsonData, JSON_PRETTY_PRINT);
        return Response::text($output);
    }
}
