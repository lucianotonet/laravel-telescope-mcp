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
 * Tool for interacting with log entries recorded by Telescope
 */
class LogsTool extends Tool
{
    use BatchQuerySupport;

    protected string $name = 'logs';
    protected string $title = 'Telescope Logs';
    protected string $description = 'Lists and analyzes log entries recorded by Telescope.';

    public function handle(Request $request, EntriesRepository $repository): Response
    {
        try {
            if ($id = $request->get('id')) {
                return $this->getLogDetails($id, $repository);
            }

            if ($requestId = $request->get('request_id')) {
                return $this->listLogsForRequest($requestId, $request, $repository);
            }

            return $this->listLogs($request, $repository);
        } catch (\Exception $e) {
            return Response::error('Error: ' . $e->getMessage());
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('ID of the specific log entry to view details'),
            'request_id' => $schema->string()->description('Filter logs by the request ID they belong to (uses batch_id grouping)'),
            'limit' => $schema->integer()->default(50)->description('Maximum number of log entries to return'),
            'level' => $schema->string()->enum(['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'])
                ->description('Filter by log level'),
            'message' => $schema->string()->description('Filter by log message content'),
        ];
    }

    protected function listLogs(Request $request, EntriesRepository $repository): Response
    {
        $limit = min($request->integer('limit', 50), 100);
        $options = new EntryQueryOptions();
        $options->limit($limit);

        // Note: Telescope doesn't tag logs by default, so we fetch all and filter manually
        $entries = $repository->get(EntryType::LOG, $options);
        if (empty($entries)) {
            return Response::text("No log entries found.");
        }

        $levelFilter = $request->get('level');
        $messageFilter = $request->get('message');

        $logs = [];
        foreach ($entries as $entry) {
            $content = is_array($entry->content) ? $entry->content : [];
            $level = $content['level'] ?? 'Unknown';
            $message = $content['message'] ?? 'No message';

            // Filter by level if specified
            if ($levelFilter && strtolower($level) !== strtolower($levelFilter)) {
                continue;
            }

            // Filter by message content if specified (case-insensitive partial match)
            if ($messageFilter && stripos($message, $messageFilter) === false) {
                continue;
            }

            $createdAt = DateFormatter::format($entry->createdAt);

            $logs[] = [
                'id' => $entry->id,
                'level' => $level,
                'message' => $message,
                'created_at' => $createdAt,
            ];
        }

        $table = "Log Entries:\n\n";
        $table .= sprintf("%-5s %-10s %-60s %-20s\n", "ID", "Level", "Message", "Created At");
        $table .= str_repeat("-", 100) . "\n";

        foreach ($logs as $log) {
            $message = $this->safeString($log['message']);
            if (strlen($message) > 60) {
                $message = substr($message, 0, 57) . "...";
            }

            $table .= sprintf(
                "%-5s %-10s %-60s %-20s\n",
                $log['id'],
                strtoupper($log['level']),
                $message,
                $log['created_at']
            );
        }

        $table .= "\n\n--- JSON Data ---\n" . json_encode([
            'total' => count($logs),
            'logs' => $logs,
        ], JSON_PRETTY_PRINT);

        return Response::text($table);
    }

    protected function listLogsForRequest(string $requestId, Request $request, EntriesRepository $repository): Response
    {
        $batchId = $this->getBatchIdForRequest($requestId);

        if (!$batchId) {
            return Response::error("Request not found or has no batch ID: {$requestId}");
        }

        $limit = min($request->integer('limit', 50), 100);
        $entries = $this->getEntriesByBatchId($batchId, 'log', $limit);

        if (empty($entries)) {
            return Response::text("No logs found for request: {$requestId}");
        }

        $logs = [];
        $levelFilter = $request->get('level');
        $messageFilter = $request->get('message');

        foreach ($entries as $entry) {
            $content = is_array($entry->content) ? $entry->content : [];
            $createdAt = isset($entry->createdAt) ? DateFormatter::format($entry->createdAt) : 'Unknown';

            $level = $content['level'] ?? 'Unknown';
            $message = $content['message'] ?? 'No message';

            // Filter by level if specified
            if ($levelFilter && strtolower($level) !== strtolower($levelFilter)) {
                continue;
            }

            // Filter by message content if specified (case-insensitive partial match)
            if ($messageFilter && stripos($message, $messageFilter) === false) {
                continue;
            }

            $logs[] = [
                'id' => $entry->id,
                'level' => $level,
                'message' => $message,
                'created_at' => $createdAt,
            ];
        }

        $table = "Logs for Request: {$requestId}\n";
        $table .= "Batch ID: {$batchId}\n";
        $table .= "Total: " . count($logs) . " logs\n\n";
        $table .= sprintf("%-5s %-10s %-60s %-20s\n", "ID", "Level", "Message", "Created At");
        $table .= str_repeat("-", 100) . "\n";

        foreach ($logs as $log) {
            $message = $this->safeString($log['message']);
            if (strlen($message) > 60) {
                $message = substr($message, 0, 57) . "...";
            }

            $table .= sprintf(
                "%-5s %-10s %-60s %-20s\n",
                $log['id'],
                strtoupper($log['level']),
                $message,
                $log['created_at']
            );
        }

        $table .= "\n\n--- JSON Data ---\n" . json_encode([
            'request_id' => $requestId,
            'batch_id' => $batchId,
            'total' => count($logs),
            'logs' => $logs,
        ], JSON_PRETTY_PRINT);

        return Response::text($table);
    }

    protected function getLogDetails(string $id, EntriesRepository $repository): Response
    {
        $entry = $repository->find($id);
        if (!$entry) {
            return Response::error("Log entry not found: {$id}");
        }

        $content = is_array($entry->content) ? $entry->content : [];
        $createdAt = DateFormatter::format($entry->createdAt);

        $output = "Log Entry Details:\n\n";
        $output .= "ID: {$entry->id}\n";
        $output .= "Level: " . strtoupper($content['level'] ?? 'Unknown') . "\n";
        $output .= "Message: " . ($content['message'] ?? 'No message') . "\n";
        $output .= "Created At: {$createdAt}\n\n";

        if (!empty($content['context'])) {
            $output .= "Context:\n" . json_encode($content['context'], JSON_PRETTY_PRINT) . "\n";
        }

        $output .= "\n\n--- JSON Data ---\n" . json_encode([
            'id' => $entry->id,
            'level' => $content['level'] ?? 'Unknown',
            'message' => $content['message'] ?? 'No message',
            'created_at' => $createdAt,
            'context' => $content['context'] ?? [],
        ], JSON_PRETTY_PRINT);

        return Response::text($output);
    }

    /**
     * Safely converts a value to string for strlen() operations
     */
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
