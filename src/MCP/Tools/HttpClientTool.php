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
use LucianoTonet\TelescopeMcp\Support\DateFormatter;

/**
 * Tool for interacting with HTTP client requests recorded by Telescope
 */
class HttpClientTool extends Tool implements IsReadOnly
{
    protected string $name = 'http-client';
    protected string $title = 'Telescope HTTP Client';
    protected string $description = 'Lists and analyzes HTTP requests made by Laravel HTTP client.';

    public function handle(Request $request, EntriesRepository $repository): Response
    {
        try {
            if ($id = $request->get('id')) {
                return $this->getRequestDetails($id, $repository);
            }
            return $this->listRequests($request, $repository);
        } catch (\Exception $e) {
            return Response::error('Error: ' . $e->getMessage());
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('ID of specific HTTP client request'),
            'limit' => $schema->integer()->default(50)->description('Max requests'),
            'method' => $schema->string()->description('Filter by HTTP method (GET, POST, etc)'),
            'status' => $schema->integer()->description('Filter by HTTP status code'),
            'url' => $schema->string()->description('Filter by URL (partial match)'),
        ];
    }

    protected function listRequests(Request $request, EntriesRepository $repository): Response
    {
        $limit = min($request->integer('limit', 50), 100);
        $options = new EntryQueryOptions();
        $options->limit($limit);

        if ($method = $request->get('method')) $options->tag($method);
        if ($status = $request->get('status')) $options->tag((string)$status);
        if ($url = $request->get('url')) $options->tag($url);

        $entries = $repository->get(EntryType::CLIENT_REQUEST, $options);
        if (empty($entries)) return Response::text("No HTTP client requests found.");

        $requests = [];
        foreach ($entries as $entry) {
            $content = is_array($entry->content) ? $entry->content : [];
            $requests[] = [
                'id' => $entry->id,
                'method' => $content['method'] ?? 'Unknown',
                'url' => $content['uri'] ?? 'Unknown',
                'status' => $content['response_status'] ?? 0,
                'duration' => isset($content['duration']) ? round($content['duration'] / 1000, 2) : 0,
                'created_at' => DateFormatter::format($entry->createdAt)
            ];
        }

        $table = "HTTP Client Requests:\n\n";
        $table .= sprintf("%-5s %-6s %-50s %-7s %-8s %-20s\n", "ID", "Method", "URL", "Status", "Time(s)", "Created At");
        $table .= str_repeat("-", 100) . "\n";

        foreach ($requests as $req) {
            $url = strlen($req['url']) > 50 ? substr($req['url'], 0, 47) . "..." : $req['url'];
            $table .= sprintf("%-5s %-6s %-50s %-7s %-8s %-20s\n",
                $req['id'], $req['method'], $url, $req['status'], $req['duration'], $req['created_at']);
        }

        $table .= "\n\n--- JSON Data ---\n" . json_encode(['total' => count($requests), 'requests' => $requests], JSON_PRETTY_PRINT);
        return Response::text($table);
    }

    protected function getRequestDetails(string $id, EntriesRepository $repository): Response
    {
        $entry = $repository->find($id);
        if (!$entry) return Response::error("HTTP client request not found: {$id}");

        $content = is_array($entry->content) ? $entry->content : [];
        $createdAt = DateFormatter::format($entry->createdAt);

        $output = "HTTP Client Request Details:\n\n";
        $output .= "ID: {$entry->id}\n";
        $output .= "Method: " . ($content['method'] ?? 'Unknown') . "\n";
        $output .= "URL: " . ($content['uri'] ?? 'Unknown') . "\n";
        $output .= "Status: " . ($content['response_status'] ?? 0) . "\n";
        $output .= "Duration: " . (isset($content['duration']) ? round($content['duration'] / 1000, 2) . "s" : 'Unknown') . "\n";
        $output .= "Created At: {$createdAt}\n\n";

        if (isset($content['headers']) && !empty($content['headers'])) {
            $output .= "Request Headers:\n";
            foreach ($content['headers'] as $name => $values) {
                $output .= "- {$name}: " . implode(', ', (array)$values) . "\n";
            }
            $output .= "\n";
        }

        if (isset($content['payload']) && !empty($content['payload'])) {
            $output .= "Request Body:\n" . json_encode($content['payload'], JSON_PRETTY_PRINT) . "\n\n";
        }

        if (isset($content['response_headers']) && !empty($content['response_headers'])) {
            $output .= "Response Headers:\n";
            foreach ($content['response_headers'] as $name => $values) {
                $output .= "- {$name}: " . implode(', ', (array)$values) . "\n";
            }
            $output .= "\n";
        }

        if (isset($content['response']) && !empty($content['response'])) {
            $output .= "Response Body:\n" . json_encode($content['response'], JSON_PRETTY_PRINT) . "\n";
        }

        $jsonData = [
            'id' => $entry->id,
            'method' => $content['method'] ?? 'Unknown',
            'url' => $content['uri'] ?? 'Unknown',
            'status' => $content['response_status'] ?? 0,
            'duration' => isset($content['duration']) ? round($content['duration'] / 1000, 2) : 0,
            'created_at' => $createdAt,
        ];

        $output .= "\n\n--- JSON Data ---\n" . json_encode($jsonData, JSON_PRETTY_PRINT);
        return Response::text($output);
    }
} 