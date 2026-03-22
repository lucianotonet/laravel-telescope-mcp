<?php

namespace LucianoTonet\TelescopeMcp\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Telescope\Contracts\EntriesRepository;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\Storage\EntryQueryOptions;
use LucianoTonet\TelescopeMcp\Mcp\Tools\Traits\BatchQuerySupport;
use LucianoTonet\TelescopeMcp\Support\DateFormatter;

/**
 * Tool for interacting with gate checks recorded by Telescope
 */
class GatesTool extends Tool
{
    use BatchQuerySupport;

    protected string $name = 'gates';
    protected string $title = 'Telescope Gates';
    protected string $description = 'Lists and analyzes gate authorization checks recorded by Telescope.';

    public function handle(Request $request, EntriesRepository $repository): Response
    {
        try {
            if ($id = $request->get('id')) {
                return $this->getGateDetails($id, $repository);
            }

            if ($requestId = $request->get('request_id')) {
                return $this->listGateChecksForRequest($requestId, $request);
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
            'request_id' => $schema->string()->description('Filter gates by the request ID they belong to (uses batch_id grouping)'),
        ];
    }

    protected function listGateChecks(Request $request, EntriesRepository $repository): Response
    {
        $limit = min($request->integer('limit', 50), 100);
        $options = new EntryQueryOptions();
        $options->limit($limit);

        $entries = $repository->get(EntryType::GATE, $options);
        if (empty($entries)) {
            return Response::text('No gate checks found.');
        }

        $checks = [];
        foreach ($entries as $entry) {
            $check = $this->mapGateEntry($entry);

            if (!$this->matchesFilters($check, $request)) {
                continue;
            }

            $checks[] = $check;
        }

        return Response::text($this->buildGateChecksResponse("Gate Checks:\n\n", $checks));
    }

    protected function listGateChecksForRequest(string $requestId, Request $request): Response
    {
        $batchId = $this->getBatchIdForRequest($requestId);
        if (!$batchId) {
            return Response::error("Request not found or has no batch ID: {$requestId}");
        }

        $limit = min($request->integer('limit', 50), 100);
        $entries = $this->getEntriesByBatchId($batchId, EntryType::GATE, $limit);

        if (empty($entries)) {
            return Response::text("No gate checks found for request: {$requestId}");
        }

        $checks = [];
        foreach ($entries as $entry) {
            $check = $this->mapGateEntry($entry);

            if (!$this->matchesFilters($check, $request)) {
                continue;
            }

            $checks[] = $check;
        }

        $header = "Gate Checks for Request: {$requestId}\n";
        $header .= "Batch ID: {$batchId}\n\n";

        return Response::text($this->buildGateChecksResponse($header, $checks, [
            'request_id' => $requestId,
            'batch_id' => $batchId,
        ]));
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
        $output .= "Ability: " . $this->stringifyValue($content['ability'] ?? 'Unknown') . "\n";
        $output .= "Result: " . (isset($content['result']) && $content['result'] ? 'Allowed' : 'Denied') . "\n";
        $output .= "User: " . $this->stringifyValue($content['user'] ?? 'Unknown') . "\n";
        $output .= "Created At: {$createdAt}\n\n";

        if (!empty($content['arguments'])) {
            $output .= "Arguments:\n" . $this->encodeJson($content['arguments']) . "\n\n";
        }

        if (!empty($content['context'])) {
            $output .= "Context:\n" . $this->encodeJson($content['context']) . "\n";
        }

        $jsonData = [
            'id' => $entry->id,
            'ability' => $content['ability'] ?? 'Unknown',
            'result' => isset($content['result']) && $content['result'] ? 'Allowed' : 'Denied',
            'user' => $content['user'] ?? 'Unknown',
            'created_at' => $createdAt,
        ];

        if (array_key_exists('arguments', $content)) {
            $jsonData['arguments'] = $content['arguments'];
        }

        if (array_key_exists('context', $content)) {
            $jsonData['context'] = $content['context'];
        }

        $output .= "\n\n--- JSON Data ---\n" . $this->encodeJson($jsonData);

        return Response::text($output);
    }

    protected function matchesFilters(array $check, Request $request): bool
    {
        if ($ability = $request->get('ability')) {
            if (strcasecmp($check['ability'], (string) $ability) !== 0) {
                return false;
            }
        }

        if ($result = $request->get('result')) {
            if (strcasecmp($check['result'], ucfirst(strtolower((string) $result))) !== 0) {
                return false;
            }
        }

        return true;
    }

    protected function mapGateEntry(object $entry): array
    {
        $content = is_array($entry->content) ? $entry->content : [];

        return [
            'id' => $entry->id,
            'ability' => $this->stringifyValue($content['ability'] ?? 'Unknown'),
            'result' => isset($content['result']) && $content['result'] ? 'Allowed' : 'Denied',
            'user' => $this->stringifyValue($content['user'] ?? 'Unknown'),
            'created_at' => DateFormatter::format($entry->createdAt),
        ];
    }

    protected function buildGateChecksResponse(string $header, array $checks, array $jsonMeta = []): string
    {
        $table = $header;
        $table .= sprintf("%-5s %-30s %-10s %-30s %-20s\n", 'ID', 'Ability', 'Result', 'User', 'Created At');
        $table .= str_repeat('-', 100) . "\n";

        foreach ($checks as $check) {
            $ability = strlen($check['ability']) > 30 ? substr($check['ability'], 0, 27) . '...' : $check['ability'];
            $user = strlen($check['user']) > 30 ? substr($check['user'], 0, 27) . '...' : $check['user'];
            $resultStr = $check['result'] === 'Denied' ? 'Denied [!]' : $check['result'];

            $table .= sprintf(
                "%-5s %-30s %-10s %-30s %-20s\n",
                $check['id'],
                $ability,
                $resultStr,
                $user,
                $check['created_at']
            );
        }

        $table .= "\n\n--- JSON Data ---\n" . $this->encodeJson(array_merge($jsonMeta, [
            'total' => count($checks),
            'checks' => $checks,
        ]));

        return $table;
    }

    protected function stringifyValue(mixed $value): string
    {
        if (is_array($value) || is_object($value)) {
            return $this->encodeJson($value);
        }

        return (string) $value;
    }

    protected function encodeJson(mixed $value): string
    {
        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '{}';
    }
}
