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
 * Tool for interacting with gate checks recorded by Telescope
 */
class GatesTool extends Tool
{
    protected string $name = 'gates';
    protected string $title = 'Telescope Gates';
    protected string $description = 'Lists and analyzes gate authorization checks recorded by Telescope.';

    public function handle(Request $request, EntriesRepository $repository): Response
    {
        try {
            if ($id = $request->get('id')) {
                return $this->getGateDetails($id, $repository);
            }
            return $this->listGateChecks($request, $repository);
        } catch (\Exception $e) {
            return Response::error('Error: ' . $e->getMessage());
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('ID of specific gate check'),
            'limit' => $schema->integer()->default(50)->description('Max gate checks'),
            'ability' => $schema->string()->description('Filter by gate ability name'),
            'result' => $schema->string()->description('Filter by check result (allowed, denied)'),
        ];
    }

    protected function listGateChecks(Request $request, EntriesRepository $repository): Response
    {
        $limit = min($request->integer('limit', 50), 100);
        $options = new EntryQueryOptions();
        $options->limit($limit);

        if ($ability = $request->get('ability')) {
            $options->tag('ability:' . $ability);
        }
        if ($result = $request->get('result')) {
            $options->tag('result:' . $result);
        }

        $entries = $repository->get(EntryType::GATE, $options);
        if (empty($entries)) {
            return Response::text("No gate checks found.");
        }

        $checks = [];
        foreach ($entries as $entry) {
            $content = is_array($entry->content) ? $entry->content : [];
            $checks[] = [
                'id' => $entry->id,
                'ability' => $content['ability'] ?? 'Unknown',
                'result' => isset($content['result']) && $content['result'] ? 'Allowed' : 'Denied',
                'user' => $content['user'] ?? 'Unknown',
                'created_at' => DateFormatter::format($entry->createdAt),
            ];
        }

        $table = "Gate Checks:\n\n";
        $table .= sprintf("%-5s %-30s %-10s %-30s %-20s\n", "ID", "Ability", "Result", "User", "Created At");
        $table .= str_repeat("-", 100) . "\n";

        foreach ($checks as $check) {
            $ability = strlen($check['ability']) > 30 ? substr($check['ability'], 0, 27) . "..." : $check['ability'];
            $user = strlen($check['user']) > 30 ? substr($check['user'], 0, 27) . "..." : $check['user'];
            $resultStr = $check['result'];
            if ($resultStr === 'Denied') {
                $resultStr .= ' [!]';
            }

            $table .= sprintf(
                "%-5s %-30s %-10s %-30s %-20s\n",
                $check['id'],
                $ability,
                $resultStr,
                $user,
                $check['created_at']
            );
        }

        $table .= "\n\n--- JSON Data ---\n" . json_encode(['total' => count($checks), 'checks' => $checks], JSON_PRETTY_PRINT);
        return Response::text($table);
    }

    protected function getGateDetails(string $id, EntriesRepository $repository): Response
    {
        $entry = $repository->find($id);
        if (!$entry) {
            return Response::error("Gate check not found: {$id}");
        }

        $content = is_array($entry->content) ? $entry->content : [];
        $createdAt = DateFormatter::format($entry->createdAt);

        $output = "Gate Check Details:\n\n";
        $output .= "ID: {$entry->id}\n";
        $output .= "Ability: " . ($content['ability'] ?? 'Unknown') . "\n";
        $output .= "Result: " . (isset($content['result']) && $content['result'] ? 'Allowed' : 'Denied') . "\n";
        $output .= "User: " . ($content['user'] ?? 'Unknown') . "\n";
        $output .= "Created At: {$createdAt}\n\n";

        if (!empty($content['arguments'])) {
            $output .= "Arguments:\n" . json_encode($content['arguments'], JSON_PRETTY_PRINT) . "\n\n";
        }

        if (!empty($content['context'])) {
            $output .= "Context:\n" . json_encode($content['context'], JSON_PRETTY_PRINT) . "\n";
        }

        $jsonData = [
            'id' => $entry->id,
            'ability' => $content['ability'] ?? 'Unknown',
            'result' => isset($content['result']) && $content['result'] ? 'Allowed' : 'Denied',
            'user' => $content['user'] ?? 'Unknown',
            'created_at' => $createdAt,
        ];

        $output .= "\n\n--- JSON Data ---\n" . json_encode($jsonData, JSON_PRETTY_PRINT);
        return Response::text($output);
    }
}
