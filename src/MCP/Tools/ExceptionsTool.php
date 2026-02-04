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
 * Tool for interacting with exceptions recorded by Telescope
 */
class ExceptionsTool extends Tool
{
    use BatchQuerySupport;

    protected string $name = 'exceptions';
    protected string $title = 'Telescope Exceptions';
    protected string $description = 'Displays exceptions recorded by Telescope, allowing you to view complete details of application errors.';

    public function handle(Request $request, EntriesRepository $repository): Response
    {
        try {
            if ($id = $request->get('id')) {
                return $this->getExceptionDetails($id, $repository);
            }

            if ($requestId = $request->get('request_id')) {
                return $this->listExceptionsForRequest($requestId, $request, $repository);
            }

            return $this->listExceptions($request, $repository);
        } catch (\Exception $e) {
            return Response::error('Error: ' . $e->getMessage());
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('ID of specific exception to view details'),
            'request_id' => $schema->string()->description('Filter exceptions by the request ID they belong to (uses batch_id grouping)'),
            'limit' => $schema->integer()->default(50)->description('Maximum number of exceptions to return'),
        ];
    }

    protected function listExceptions(Request $request, EntriesRepository $repository): Response
    {
        $limit = min($request->integer('limit', 50), 100);

        $options = new EntryQueryOptions();
        $options->limit($limit);

        $entries = $repository->get(EntryType::EXCEPTION, $options);

        if (empty($entries)) {
            return Response::text("No exceptions found.");
        }

        $exceptions = [];

        foreach ($entries as $entry) {
            $content = is_array($entry->content) ? $entry->content : [];

            $createdAt = DateFormatter::format($entry->createdAt);

            $className = $content['class'] ?? 'Unknown';
            $message = $content['message'] ?? 'No message';
            $file = $content['file'] ?? 'Unknown';
            $line = $content['line'] ?? 0;

            $exceptions[] = [
                'id' => $entry->id,
                'class' => $className,
                'message' => $message,
                'file' => $file,
                'line' => $line,
                'occurred_at' => $createdAt,
            ];
        }

        $table = "Application Exceptions:\n\n";
        $table .= sprintf("%-5s %-30s %-40s %-20s\n", "ID", "Exception", "Message", "Occurred At");
        $table .= str_repeat("-", 100) . "\n";

        foreach ($exceptions as $exception) {
            $message = $exception['message'];
            $message = $this->safeString($message);
            if (strlen($message) > 40) {
                $message = substr($message, 0, 37) . "...";
            }

            $className = $exception['class'];
            if (strpos($className, '\\') !== false) {
                $parts = explode('\\', $className);
                $className = end($parts);
            }

            $table .= sprintf(
                "%-5s %-30s %-40s %-20s\n",
                $exception['id'],
                substr($className, 0, 30),
                $message,
                $exception['occurred_at']
            );
        }

        $combinedText = $table . "\n\n--- JSON Data ---\n" . json_encode([
            'total' => count($exceptions),
            'exceptions' => $exceptions,
        ], JSON_PRETTY_PRINT);

        return Response::text($combinedText);
    }

    protected function listExceptionsForRequest(string $requestId, Request $request, EntriesRepository $repository): Response
    {
        $batchId = $this->getBatchIdForRequest($requestId);

        if (!$batchId) {
            return Response::error("Request not found or has no batch ID: {$requestId}");
        }

        $limit = min($request->integer('limit', 50), 100);

        $entries = $this->getEntriesByBatchId($batchId, 'exception', $limit);

        if (empty($entries)) {
            return Response::text("No exceptions found for request: {$requestId}");
        }

        $exceptions = [];

        foreach ($entries as $entry) {
            $content = is_array($entry->content) ? $entry->content : [];
            $createdAt = isset($entry->createdAt) ? DateFormatter::format($entry->createdAt) : 'Unknown';

            $className = $content['class'] ?? 'Unknown';
            $message = $content['message'] ?? 'No message';
            $file = $content['file'] ?? 'Unknown';
            $line = $content['line'] ?? 0;

            $exceptions[] = [
                'id' => $entry->id,
                'class' => $className,
                'message' => $message,
                'file' => $file,
                'line' => $line,
                'occurred_at' => $createdAt,
            ];
        }

        $table = "Exceptions for Request: {$requestId}\n";
        $table .= "Batch ID: {$batchId}\n";
        $table .= "Total: " . count($exceptions) . " exceptions\n\n";
        $table .= sprintf("%-5s %-30s %-40s %-20s\n", "ID", "Exception", "Message", "Occurred At");
        $table .= str_repeat("-", 100) . "\n";

        foreach ($exceptions as $exception) {
            $message = $exception['message'];
            $message = $this->safeString($message);
            if (strlen($message) > 40) {
                $message = substr($message, 0, 37) . "...";
            }

            $className = $exception['class'];
            if (strpos($className, '\\') !== false) {
                $parts = explode('\\', $className);
                $className = end($parts);
            }

            $table .= sprintf(
                "%-5s %-30s %-40s %-20s\n",
                $exception['id'],
                substr($className, 0, 30),
                $message,
                $exception['occurred_at']
            );
        }

        $combinedText = $table . "\n\n--- JSON Data ---\n" . json_encode([
            'request_id' => $requestId,
            'batch_id' => $batchId,
            'total' => count($exceptions),
            'exceptions' => $exceptions,
        ], JSON_PRETTY_PRINT);

        return Response::text($combinedText);
    }

    protected function getExceptionDetails(string $id, EntriesRepository $repository): Response
    {
        $entry = $repository->find($id);

        if (!$entry) {
            return Response::error("Exception not found: {$id}");
        }

        $content = is_array($entry->content) ? $entry->content : [];

        $createdAt = DateFormatter::format($entry->createdAt);

        $output = "Exception Details:\n\n";
        $output .= "ID: {$entry->id}\n";
        $output .= "Type: " . ($content['class'] ?? 'Unknown') . "\n";
        $output .= "Message: " . ($content['message'] ?? 'No message') . "\n";
        $output .= "File: " . ($content['file'] ?? 'Unknown') . "\n";
        $output .= "Line: " . ($content['line'] ?? 'Unknown') . "\n";

        $output .= "Occurred At: {$createdAt}\n\n";

        if (isset($content['trace']) && is_array($content['trace'])) {
            $output .= "Stack Trace:\n";

            foreach ($content['trace'] as $index => $frame) {
                $file = $frame['file'] ?? 'Unknown';
                $line = $frame['line'] ?? 'Unknown';
                $function = $frame['function'] ?? 'Unknown';
                $class = $frame['class'] ?? '';
                $type = $frame['type'] ?? '';

                $output .= sprintf(
                    "#%d %s%s%s() at %s:%s\n",
                    $index,
                    $class,
                    $type,
                    $function,
                    $file,
                    $line
                );
            }
        }

        if (isset($content['context']) && is_array($content['context'])) {
            $output .= "\nContext:\n";
            $output .= json_encode($content['context'], JSON_PRETTY_PRINT);
        }

        $combinedText = $output . "\n\n--- JSON Data ---\n" . json_encode([
            'id' => $entry->id,
            'class' => $content['class'] ?? 'Unknown',
            'message' => $content['message'] ?? 'No message',
            'file' => $content['file'] ?? 'Unknown',
            'line' => $content['line'] ?? 'Unknown',
            'occurred_at' => $createdAt,
            'trace' => $content['trace'] ?? [],
            'context' => $content['context'] ?? [],
        ], JSON_PRETTY_PRINT);

        return Response::text($combinedText);
    }

    /**
     * Safely converts a value to a string
     */
    protected function safeString($value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_null($value)) {
            return '';
        }

        if (is_array($value) || is_object($value)) {
            return json_encode($value);
        }

        return (string) $value;
    }
}
