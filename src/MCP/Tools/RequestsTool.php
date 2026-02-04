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
 * Tool for interacting with HTTP requests recorded by Telescope
 */
class RequestsTool extends Tool
{
    use BatchQuerySupport;

    protected string $name = 'requests';
    protected string $title = 'Telescope Requests';
    protected string $description = 'Lists and analyzes HTTP requests recorded by Telescope.';

    public function handle(Request $request, EntriesRepository $repository): Response
    {
        try {
            if ($id = $request->get('id')) {
                $includeRelated = $request->boolean('include_related', true);
                return $this->getRequestDetails($id, $includeRelated, $repository);
            }
            return $this->listRequests($request, $repository);
        } catch (\Exception $e) {
            return Response::error('Error: ' . $e->getMessage());
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('ID of specific request'),
            'limit' => $schema->integer()->default(50)->description('Max requests'),
            'method' => $schema->string()->description('Filter by HTTP method'),
            'status' => $schema->integer()->description('Filter by status code'),
            'path' => $schema->string()->description('Filter by path'),
            'include_related' => $schema->boolean()->default(true),
        ];
    }

    protected function listRequests(Request $request, EntriesRepository $repository): Response
    {
        $limit = min($request->integer('limit', 50), 100);
        $options = new EntryQueryOptions();
        $options->limit($limit);

        if ($method = $request->get('method')) $options->tag('method:' . strtoupper($method));
        if ($status = $request->get('status')) $options->tag('status:' . $status);
        if ($path = $request->get('path')) $options->tag('path:' . $path);

        $entries = $repository->get(EntryType::REQUEST, $options);
        if (empty($entries)) return Response::text("No requests found.");

        $requests = [];
        foreach ($entries as $entry) {
            $content = is_array($entry->content) ? $entry->content : [];
            $requests[] = [
                'id' => $entry->id,
                'method' => $content['method'] ?? 'Unknown',
                'uri' => $content['uri'] ?? 'Unknown',
                'status' => $content['response_status'] ?? 0,
                'duration' => $content['duration'] ?? 0,
                'created_at' => isset($content['created_at']) ? DateFormatter::format($content['created_at']) : 'Unknown'
            ];
        }

        $table = "HTTP Requests:\n\n";
        $table .= sprintf("%-5s %-7s %-50s %-7s %-10s %-20s\n", "ID", "Method", "URI", "Status", "Time (ms)", "Created At");
        $table .= str_repeat("-", 120) . "\n";

        foreach ($requests as $req) {
            $uri = strlen($req['uri']) > 50 ? substr($req['uri'], 0, 47) . "..." : $req['uri'];
            $statusStr = sprintf("%d", $req['status']);
            if ($req['status'] >= 500) $statusStr .= " [E]";
            elseif ($req['status'] >= 400) $statusStr .= " [W]";

            $table .= sprintf("%-5s %-7s %-50s %-7s %-10s %-20s\n",
                $req['id'], $req['method'], $uri, $statusStr,
                number_format($req['duration'], 2), $req['created_at']);
        }

        $table .= "\n\n--- JSON Data ---\n" . json_encode(['total' => count($requests), 'requests' => $requests], JSON_PRETTY_PRINT);
        return Response::text($table);
    }

    protected function getRequestDetails(string $id, bool $includeRelated, EntriesRepository $repository): Response
    {
        $entry = $repository->find($id);
        if (!$entry) return Response::error("Request not found: {$id}");

        $content = is_array($entry->content) ? $entry->content : [];
        $createdAt = isset($content['created_at']) ? DateFormatter::format($content['created_at']) : 'Unknown';

        $output = "HTTP Request Details:\n\n";
        $output .= "ID: {$entry->id}\nMethod: " . ($content['method'] ?? 'Unknown') . "\n";
        $output .= "URI: " . ($content['uri'] ?? 'Unknown') . "\n";
        $output .= "Status: " . ($content['response_status'] ?? 'Unknown') . "\n";
        $output .= "Duration: " . number_format(($content['duration'] ?? 0), 2) . " ms\n";
        $output .= "Created At: {$createdAt}\n";

        $relatedSummary = [];
        if ($includeRelated && isset($entry->batchId) && $entry->batchId) {
            $summary = $this->getBatchSummary($entry->batchId);
            $typeLabels = ['query' => 'Queries', 'log' => 'Logs', 'cache' => 'Cache Operations',
                'model' => 'Model Events', 'view' => 'Views', 'exception' => 'Exceptions',
                'event' => 'Events', 'job' => 'Jobs', 'mail' => 'Mails',
                'notification' => 'Notifications', 'redis' => 'Redis Operations'];

            $output .= "\n--- Related Entries ---\n";
            $hasRelated = false;
            foreach ($summary as $type => $count) {
                if ($type !== 'request') {
                    $label = $typeLabels[$type] ?? ucfirst($type);
                    $output .= "- {$label}: {$count}\n";
                    $relatedSummary[$type] = $count;
                    $hasRelated = true;
                }
            }
            if ($hasRelated) {
                $output .= "\nTip: Use 'queries --request_id={$id}' to see queries for this request.\n";
            } else {
                $output .= "(No related entries found)\n";
            }
        }

        $output .= "\n";
        if (!empty($content['headers'])) {
            $output .= "Request Headers:\n";
            foreach ($content['headers'] as $name => $values) {
                $output .= "- {$name}: " . implode(", ", (array)$values) . "\n";
            }
            $output .= "\n";
        }

        if (!empty($content['payload'])) {
            $output .= "Request Payload:\n" . json_encode($content['payload'], JSON_PRETTY_PRINT) . "\n\n";
        }

        $jsonData = [
            'id' => $entry->id,
            'batch_id' => $entry->batchId ?? null,
            'method' => $content['method'] ?? 'Unknown',
            'uri' => $content['uri'] ?? 'Unknown',
            'status' => $content['response_status'] ?? 'Unknown',
            'duration' => $content['duration'] ?? 0,
            'created_at' => $createdAt,
        ];
        if (!empty($relatedSummary)) $jsonData['related_entries'] = $relatedSummary;

        $output .= "\n\n--- JSON Data ---\n" . json_encode($jsonData, JSON_PRETTY_PRINT);
        return Response::text($output);
    }
}
