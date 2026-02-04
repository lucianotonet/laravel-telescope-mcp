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
 * Tool for interacting with events recorded by Telescope
 */
class EventsTool extends Tool
{
    use BatchQuerySupport;

    protected string $name = 'events';
    protected string $title = 'Telescope Events';
    protected string $description = 'Lists and analyzes events recorded by Telescope.';

    public function handle(Request $request, EntriesRepository $repository): Response
    {
        try {
            if ($id = $request->get('id')) {
                return $this->getEventDetails($id, $repository);
            }

            if ($requestId = $request->get('request_id')) {
                return $this->listEventsForRequest($requestId, $request, $repository);
            }

            return $this->listEvents($request, $repository);
        } catch (\Exception $e) {
            return Response::error('Error: ' . $e->getMessage());
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('ID of specific event'),
            'request_id' => $schema->string()->description('Filter events by request ID (uses batch_id grouping)'),
            'limit' => $schema->integer()->default(50)->description('Maximum number of events to return'),
            'name' => $schema->string()->description('Filter by event name'),
        ];
    }

    protected function listEvents(Request $request, EntriesRepository $repository): Response
    {
        $limit = min($request->integer('limit', 50), 100);
        $options = new EntryQueryOptions();
        $options->limit($limit);

        if ($name = $request->get('name')) {
            $options->tag($name);
        }

        $entries = $repository->get(EntryType::EVENT, $options);
        if (empty($entries)) {
            return Response::text("No events found.");
        }

        $events = [];
        foreach ($entries as $entry) {
            $content = is_array($entry->content) ? $entry->content : [];
            $createdAt = DateFormatter::format($entry->createdAt);

            $events[] = [
                'id' => $entry->id,
                'name' => $content['name'] ?? 'Unknown',
                'listeners' => isset($content['listeners']) ? count($content['listeners']) : 0,
                'created_at' => $createdAt,
            ];
        }

        $table = "Events:\n\n";
        $table .= sprintf("%-5s %-60s %-10s %-20s\n", "ID", "Name", "Listeners", "Created At");
        $table .= str_repeat("-", 100) . "\n";

        foreach ($events as $event) {
            $name = $this->safeString($event['name']);
            if (strlen($name) > 60) {
                $name = substr($name, 0, 57) . "...";
            }

            $table .= sprintf(
                "%-5s %-60s %-10s %-20s\n",
                $event['id'],
                $name,
                $event['listeners'],
                $event['created_at']
            );
        }

        $table .= "\n\n--- JSON Data ---\n" . json_encode([
            'total' => count($events),
            'events' => $events,
        ], JSON_PRETTY_PRINT);

        return Response::text($table);
    }

    protected function listEventsForRequest(string $requestId, Request $request, EntriesRepository $repository): Response
    {
        $batchId = $this->getBatchIdForRequest($requestId);

        if (!$batchId) {
            return Response::error("Request not found or has no batch ID: {$requestId}");
        }

        $limit = min($request->integer('limit', 50), 100);
        $entries = $this->getEntriesByBatchId($batchId, 'event', $limit);

        if (empty($entries)) {
            return Response::text("No events found for request: {$requestId}");
        }

        $events = [];
        $nameFilter = $request->get('name');

        foreach ($entries as $entry) {
            $content = is_array($entry->content) ? $entry->content : [];
            $createdAt = isset($entry->createdAt) ? DateFormatter::format($entry->createdAt) : 'Unknown';

            $name = $content['name'] ?? 'Unknown';

            if ($nameFilter && strpos($name, $nameFilter) === false) {
                continue;
            }

            $events[] = [
                'id' => $entry->id,
                'name' => $name,
                'listeners' => isset($content['listeners']) ? count($content['listeners']) : 0,
                'created_at' => $createdAt,
            ];
        }

        $table = "Events for Request: {$requestId}\n";
        $table .= "Batch ID: {$batchId}\n";
        $table .= "Total: " . count($events) . " events\n\n";
        $table .= sprintf("%-5s %-60s %-10s %-20s\n", "ID", "Name", "Listeners", "Created At");
        $table .= str_repeat("-", 100) . "\n";

        foreach ($events as $event) {
            $name = $this->safeString($event['name']);
            if (strlen($name) > 60) {
                $name = substr($name, 0, 57) . "...";
            }

            $table .= sprintf(
                "%-5s %-60s %-10s %-20s\n",
                $event['id'],
                $name,
                $event['listeners'],
                $event['created_at']
            );
        }

        $table .= "\n\n--- JSON Data ---\n" . json_encode([
            'request_id' => $requestId,
            'batch_id' => $batchId,
            'total' => count($events),
            'events' => $events,
        ], JSON_PRETTY_PRINT);

        return Response::text($table);
    }

    protected function getEventDetails(string $id, EntriesRepository $repository): Response
    {
        $entry = $repository->find($id);
        if (!$entry) {
            return Response::error("Event not found: {$id}");
        }

        $content = is_array($entry->content) ? $entry->content : [];
        $createdAt = DateFormatter::format($entry->createdAt);

        $output = "Event Details:\n\n";
        $output .= "ID: {$entry->id}\n";
        $output .= "Name: " . ($content['name'] ?? 'Unknown') . "\n";
        $output .= "Created At: {$createdAt}\n\n";

        if (isset($content['payload']) && !empty($content['payload'])) {
            $output .= "Payload:\n" . json_encode($content['payload'], JSON_PRETTY_PRINT) . "\n\n";
        }

        if (isset($content['listeners']) && !empty($content['listeners'])) {
            $output .= "Listeners:\n";
            foreach ($content['listeners'] as $listener) {
                $output .= "- " . $listener . "\n";
            }
        }

        $output .= "\n\n--- JSON Data ---\n" . json_encode([
            'id' => $entry->id,
            'name' => $content['name'] ?? 'Unknown',
            'created_at' => $createdAt,
            'payload' => $content['payload'] ?? [],
            'listeners' => $content['listeners'] ?? [],
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
