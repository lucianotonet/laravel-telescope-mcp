<?php

namespace LucianoTonet\TelescopeMcp\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Telescope\Contracts\EntriesRepository;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\Storage\EntryQueryOptions;
use LucianoTonet\TelescopeMcp\MCP\Tools\Traits\BatchQuerySupport;
use LucianoTonet\TelescopeMcp\Support\DateFormatter;

/**
 * Tool for interacting with database queries recorded by Telescope
 */
class QueriesTool extends Tool
{
    use BatchQuerySupport;

    protected string $name = 'queries';
    protected string $title = 'Telescope Database Queries';
    protected string $description = 'Lists and analyzes database queries recorded by Telescope.';

    public function handle(Request $request, EntriesRepository $repository): Response
    {
        try {
            // Check for specific query details
            if ($id = $request->get('id')) {
                return $this->getQueryDetails($id, $repository);
            }

            // Check if filtering by request_id
            if ($requestId = $request->get('request_id')) {
                return $this->listQueriesForRequest($requestId, $request, $repository);
            }

            return $this->listQueries($request, $repository);
        } catch (\Exception $e) {
            return Response::error('Error: ' . $e->getMessage());
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('ID of specific query to view details'),
            'request_id' => $schema->string()->description('Filter queries by the request ID they belong to (uses batch_id grouping)'),
            'limit' => $schema->integer()->default(50)->description('Maximum number of queries to return'),
            'slow' => $schema->boolean()->default(false)->description('Filter only slow queries (>100ms)'),
        ];
    }

    protected function listQueries(Request $request, EntriesRepository $repository): Response
    {
        $limit = min($request->integer('limit', 50), 100);
        $slow = $request->boolean('slow', false);

        $options = new EntryQueryOptions();
        $options->limit($limit);

        $entries = $repository->get(EntryType::QUERY, $options);
        if (empty($entries)) {
            return Response::text("No queries found.");
        }

        $queries = [];

        foreach ($entries as $entry) {
            $content = is_array($entry->content) ? $entry->content : [];
            $createdAt = isset($content['created_at']) ? DateFormatter::format($content['created_at']) : 'Unknown';

            $duration = $content['duration'] ?? 0;
            $isSlow = $duration > 100; // Queries taking more than 100ms are considered slow

            // Skip if we're only looking for slow queries and this one isn't slow
            if ($slow && !$isSlow) {
                continue;
            }

            $queries[] = [
                'id' => $entry->id,
                'sql' => $content['sql'] ?? 'Unknown',
                'duration' => $duration,
                'connection' => $content['connection'] ?? 'default',
                'created_at' => $createdAt,
            ];
        }

        // Tabular formatting for readability
        $table = "Database Queries:\n\n";
        $table .= sprintf("%-5s %-50s %-10s %-15s %-20s\n", "ID", "SQL", "Time (ms)", "Connection", "Created At");
        $table .= str_repeat("-", 105) . "\n";

        foreach ($queries as $query) {
            // Truncate long SQL
            $sql = $this->safeString($query['sql']);
            if (strlen($sql) > 50) {
                $sql = substr($sql, 0, 47) . "...";
            }

            $table .= sprintf(
                "%-5s %-50s %-10s %-15s %-20s\n",
                $query['id'],
                $sql,
                number_format($query['duration'], 2),
                $query['connection'],
                $query['created_at']
            );
        }

        $combinedText = $table . "\n\n--- JSON Data ---\n" . json_encode([
            'total' => count($queries),
            'queries' => $queries,
        ], JSON_PRETTY_PRINT);

        return Response::text($combinedText);
    }

    protected function listQueriesForRequest(string $requestId, Request $request, EntriesRepository $repository): Response
    {
        // Get the batch_id for this request
        $batchId = $this->getBatchIdForRequest($requestId);

        if (!$batchId) {
            return Response::error("Request not found or has no batch ID: {$requestId}");
        }

        $limit = min($request->integer('limit', 50), 100);
        $slow = $request->boolean('slow', false);

        // Get queries for this batch
        $entries = $this->getEntriesByBatchId($batchId, 'query', $limit);

        if (empty($entries)) {
            return Response::text("No queries found for request: {$requestId}");
        }

        $queries = [];

        foreach ($entries as $entry) {
            $content = is_array($entry->content) ? $entry->content : [];
            $createdAt = isset($entry->createdAt) ? DateFormatter::format($entry->createdAt) : 'Unknown';

            $duration = $content['duration'] ?? $content['time'] ?? 0;
            $isSlow = $duration > 100;

            // Skip if we're only looking for slow queries and this one isn't slow
            if ($slow && !$isSlow) {
                continue;
            }

            $queries[] = [
                'id' => $entry->id,
                'sql' => $content['sql'] ?? 'Unknown',
                'duration' => $duration,
                'connection' => $content['connection'] ?? 'default',
                'created_at' => $createdAt,
            ];
        }

        // Tabular formatting with request context
        $table = "Queries for Request: {$requestId}\n";
        $table .= "Batch ID: {$batchId}\n";
        $table .= "Total: " . count($queries) . " queries\n\n";
        $table .= sprintf("%-5s %-50s %-10s %-15s %-20s\n", "ID", "SQL", "Time (ms)", "Connection", "Created At");
        $table .= str_repeat("-", 105) . "\n";

        foreach ($queries as $query) {
            $sql = $this->safeString($query['sql']);
            if (strlen($sql) > 50) {
                $sql = substr($sql, 0, 47) . "...";
            }

            $table .= sprintf(
                "%-5s %-50s %-10s %-15s %-20s\n",
                $query['id'],
                $sql,
                number_format($query['duration'], 2),
                $query['connection'],
                $query['created_at']
            );
        }

        $combinedText = $table . "\n\n--- JSON Data ---\n" . json_encode([
            'request_id' => $requestId,
            'batch_id' => $batchId,
            'total' => count($queries),
            'queries' => $queries,
        ], JSON_PRETTY_PRINT);

        return Response::text($combinedText);
    }

    protected function getQueryDetails(string $id, EntriesRepository $repository): Response
    {
        $entry = $repository->find($id);

        if (!$entry) {
            return Response::error("Query not found: {$id}");
        }

        $content = is_array($entry->content) ? $entry->content : [];
        $createdAt = isset($content['created_at']) ? DateFormatter::format($content['created_at']) : 'Unknown';

        // Detailed formatting of the query
        $output = "Database Query Details:\n\n";
        $output .= "ID: {$entry->id}\n";
        $output .= "Connection: " . ($content['connection'] ?? 'default') . "\n";
        $output .= "Duration: " . number_format(($content['time'] ?? 0), 2) . "ms\n";
        $output .= "Created At: {$createdAt}\n\n";

        // Full SQL
        $output .= "SQL:\n" . ($content['sql'] ?? 'Unknown') . "\n\n";

        // Bindings if available
        if (isset($content['bindings']) && !empty($content['bindings'])) {
            $output .= "Bindings:\n" . json_encode($content['bindings'], JSON_PRETTY_PRINT) . "\n";
        }

        $combinedText = $output . "\n\n--- JSON Data ---\n" . json_encode([
            'id' => $entry->id,
            'connection' => $content['connection'] ?? 'default',
            'duration' => $content['time'] ?? 0,
            'created_at' => $createdAt,
            'sql' => $content['sql'] ?? 'Unknown',
            'bindings' => $content['bindings'] ?? [],
        ], JSON_PRETTY_PRINT);

        return Response::text($combinedText);
    }

    /**
     * Safely convert a value to string
     */
    protected function safeString($value): string
    {
        if (is_string($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (string) $value;
        }
        if (is_array($value) || is_object($value)) {
            return json_encode($value);
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_null($value)) {
            return 'null';
        }
        return (string) $value;
    }
}
