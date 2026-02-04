<?php

namespace LucianoTonet\TelescopeMcp\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Contracts\IsReadOnly;
use Laravel\Telescope\Contracts\EntriesRepository;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\Storage\EntryQueryOptions;
use LucianoTonet\TelescopeMcp\MCP\Tools\Traits\BatchQuerySupport;
use LucianoTonet\TelescopeMcp\Support\DateFormatter;

/**
 * Tool for interacting with cache operations recorded by Telescope
 */
class CacheTool extends Tool implements IsReadOnly
{
    use BatchQuerySupport;

    protected string $name = 'cache';
    protected string $title = 'Telescope Cache';
    protected string $description = 'Lists and analyzes cache operations recorded by Telescope.';

    public function handle(Request $request, EntriesRepository $repository): Response
    {
        try {
            if ($id = $request->get('id')) {
                return $this->getCacheDetails($id, $repository);
            }
            if ($requestId = $request->get('request_id')) {
                return $this->listCacheForRequest($requestId, $request, $repository);
            }
            return $this->listCache($request, $repository);
        } catch (\Exception $e) {
            return Response::error('Error: ' . $e->getMessage());
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('ID of specific cache operation'),
            'request_id' => $schema->string()->description('Filter cache operations by request ID (uses batch_id grouping)'),
            'limit' => $schema->integer()->default(50)->description('Maximum number of cache operations to return'),
            'operation' => $schema->enum(['hit', 'miss', 'set', 'forget'])->description('Filter by operation type'),
            'key' => $schema->string()->description('Filter by cache key (partial match)'),
        ];
    }

    protected function listCache(Request $request, EntriesRepository $repository): Response
    {
        $limit = min($request->integer('limit', 50), 100);
        $options = new EntryQueryOptions($limit);

        if ($operation = $request->get('operation')) $options->tag('operation:' . strtolower($operation));
        if ($key = $request->get('key')) $options->tag('key:' . $key);

        $entries = $repository->get(EntryType::CACHE, $options);
        if (empty($entries)) return Response::text("No cache operations found.");

        $operations = [];
        foreach ($entries as $entry) {
            $content = is_array($entry->content) ? $entry->content : [];
            $createdAt = DateFormatter::format($entry->createdAt);

            $operations[] = [
                'id' => $entry->id,
                'operation' => $content['type'] ?? 'Unknown',
                'key' => $content['key'] ?? 'Unknown',
                'duration' => $content['duration'] ?? 0,
                'created_at' => $createdAt
            ];
        }

        $table = "Cache Operations:\n\n";
        $table .= sprintf("%-5s %-8s %-50s %-10s %-20s\n",
            "ID", "Type", "Key", "Time (ms)", "Created At");
        $table .= str_repeat("-", 100) . "\n";

        foreach ($operations as $op) {
            $key = $this->safeString($op['key']);
            if (strlen($key) > 50) {
                $key = substr($key, 0, 47) . "...";
            }

            $table .= sprintf("%-5s %-8s %-50s %-10.2f %-20s\n",
                $op['id'],
                $op['operation'],
                $key,
                $op['duration'],
                $op['created_at']
            );
        }

        $combinedText = $table . "\n\n--- JSON Data ---\n" . json_encode([
            'total' => count($operations),
            'operations' => $operations
        ], JSON_PRETTY_PRINT);

        return Response::text($combinedText);
    }

    protected function listCacheForRequest(string $requestId, Request $request, EntriesRepository $repository): Response
    {
        $batchId = $this->getBatchIdForRequest($requestId);
        if (!$batchId) {
            return Response::error("Request not found or has no batch ID: {$requestId}");
        }

        $limit = min($request->integer('limit', 50), 100);
        $entries = $this->getEntriesByBatchId($batchId, 'cache', $limit);

        if (empty($entries)) {
            return Response::text("No cache operations found for request: {$requestId}");
        }

        $operations = [];
        foreach ($entries as $entry) {
            $content = is_array($entry->content) ? $entry->content : [];
            $createdAt = isset($entry->createdAt) ? DateFormatter::format($entry->createdAt) : 'Unknown';

            $operation = $content['type'] ?? 'Unknown';
            if ($filterOp = $request->get('operation')) {
                if (strtolower($operation) !== strtolower($filterOp)) {
                    continue;
                }
            }

            $operations[] = [
                'id' => $entry->id,
                'operation' => $operation,
                'key' => $content['key'] ?? 'Unknown',
                'duration' => $content['duration'] ?? 0,
                'created_at' => $createdAt
            ];
        }

        $table = "Cache Operations for Request: {$requestId}\n";
        $table .= "Batch ID: {$batchId}\n";
        $table .= "Total: " . count($operations) . " operations\n\n";
        $table .= sprintf("%-5s %-8s %-50s %-10s %-20s\n", "ID", "Type", "Key", "Time (ms)", "Created At");
        $table .= str_repeat("-", 100) . "\n";

        foreach ($operations as $op) {
            $key = $this->safeString($op['key']);
            if (strlen($key) > 50) {
                $key = substr($key, 0, 47) . "...";
            }

            $table .= sprintf("%-5s %-8s %-50s %-10.2f %-20s\n",
                $op['id'],
                $op['operation'],
                $key,
                $op['duration'],
                $op['created_at']
            );
        }

        $combinedText = $table . "\n\n--- JSON Data ---\n" . json_encode([
            'request_id' => $requestId,
            'batch_id' => $batchId,
            'total' => count($operations),
            'operations' => $operations
        ], JSON_PRETTY_PRINT);

        return Response::text($combinedText);
    }

    protected function getCacheDetails(string $id, EntriesRepository $repository): Response
    {
        $entry = $repository->find($id);
        if (!$entry) return Response::error("Cache operation not found: {$id}");

        $content = is_array($entry->content) ? $entry->content : [];

        $output = "Cache Operation Details:\n\n";
        $output .= "ID: {$entry->id}\n";
        $output .= "Operation: " . ($content['type'] ?? 'Unknown') . "\n";
        $output .= "Key: " . ($content['key'] ?? 'Unknown') . "\n";
        $output .= "Duration: " . number_format(($content['duration'] ?? 0), 2) . " ms\n";

        $createdAt = DateFormatter::format($entry->createdAt);
        $output .= "Created At: {$createdAt}\n\n";

        if (isset($content['value'])) {
            $output .= "Value:\n";
            if (is_array($content['value']) || is_object($content['value'])) {
                $output .= json_encode($content['value'], JSON_PRETTY_PRINT) . "\n";
            } else {
                $output .= $content['value'] . "\n";
            }
        }

        $combinedText = $output . "\n\n--- JSON Data ---\n" . json_encode([
            'id' => $entry->id,
            'operation' => $content['type'] ?? 'Unknown',
            'key' => $content['key'] ?? 'Unknown',
            'duration' => $content['duration'] ?? 0,
            'created_at' => $createdAt,
            'value' => $content['value'] ?? null
        ], JSON_PRETTY_PRINT);

        return Response::text($combinedText);
    }

    protected function safeString($value): string
    {
        if (!is_string($value)) {
            return (string)$value;
        }
        return $value;
    }
}
