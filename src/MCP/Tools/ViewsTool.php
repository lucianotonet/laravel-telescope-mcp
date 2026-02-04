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
 * Tool for interacting with view renderings recorded by Telescope
 */
class ViewsTool extends Tool
{
    use BatchQuerySupport;

    protected string $name = 'views';
    protected string $title = 'Telescope Views';
    protected string $description = 'Lists and analyzes view renderings recorded by Telescope.';

    public function handle(Request $request, EntriesRepository $repository): Response
    {
        try {
            if ($id = $request->get('id')) {
                return $this->getViewDetails($id, $repository);
            }

            if ($requestId = $request->get('request_id')) {
                return $this->listViewsForRequest($requestId, $request, $repository);
            }

            return $this->listViews($request, $repository);
        } catch (\Exception $e) {
            return Response::error('Error: ' . $e->getMessage());
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('ID of specific view rendering'),
            'request_id' => $schema->string()->description('Filter views by the request ID they belong to (uses batch_id grouping)'),
            'limit' => $schema->integer()->default(50)->description('Max views'),
        ];
    }

    protected function listViews(Request $request, EntriesRepository $repository): Response
    {
        $limit = min($request->integer('limit', 50), 100);
        $options = new EntryQueryOptions();
        $options->limit($limit);

        $entries = $repository->get(EntryType::VIEW, $options);
        if (empty($entries)) {
            return Response::text("No view renderings found.");
        }

        $views = [];
        foreach ($entries as $entry) {
            $content = is_array($entry->content) ? $entry->content : [];
            $views[] = [
                'id' => $entry->id,
                'name' => $content['name'] ?? 'Unknown',
                'path' => $content['path'] ?? 'Unknown',
                'created_at' => isset($content['created_at']) ? DateFormatter::format($content['created_at']) : 'Unknown',
            ];
        }

        $table = "View Renderings:\n\n";
        $table .= sprintf("%-5s %-30s %-50s %-20s\n", "ID", "Name", "Path", "Created At");
        $table .= str_repeat("-", 120) . "\n";

        foreach ($views as $view) {
            $name = strlen($view['name']) > 30 ? substr($view['name'], 0, 27) . "..." : $view['name'];
            $path = strlen($view['path']) > 50 ? substr($view['path'], 0, 47) . "..." : $view['path'];

            $table .= sprintf(
                "%-5s %-30s %-50s %-20s\n",
                $view['id'],
                $name,
                $path,
                $view['created_at']
            );
        }

        $table .= "\n\n--- JSON Data ---\n" . json_encode(['total' => count($views), 'views' => $views], JSON_PRETTY_PRINT);
        return Response::text($table);
    }

    protected function listViewsForRequest(string $requestId, Request $request, EntriesRepository $repository): Response
    {
        $batchId = $this->getBatchIdForRequest($requestId);

        if (!$batchId) {
            return Response::error("Request not found or has no batch ID: {$requestId}");
        }

        $limit = min($request->integer('limit', 50), 100);
        $entries = $this->getEntriesByBatchId($batchId, 'view', $limit);

        if (empty($entries)) {
            return Response::text("No views found for request: {$requestId}");
        }

        $views = [];
        foreach ($entries as $entry) {
            $content = is_array($entry->content) ? $entry->content : [];
            $views[] = [
                'id' => $entry->id,
                'name' => $content['name'] ?? 'Unknown',
                'path' => $content['path'] ?? 'Unknown',
                'created_at' => isset($entry->createdAt) ? DateFormatter::format($entry->createdAt) : 'Unknown',
            ];
        }

        $table = "Views for Request: {$requestId}\n";
        $table .= "Batch ID: {$batchId}\n";
        $table .= "Total: " . count($views) . " views\n\n";
        $table .= sprintf("%-5s %-30s %-50s %-20s\n", "ID", "Name", "Path", "Created At");
        $table .= str_repeat("-", 120) . "\n";

        foreach ($views as $view) {
            $name = strlen($view['name']) > 30 ? substr($view['name'], 0, 27) . "..." : $view['name'];
            $path = strlen($view['path']) > 50 ? substr($view['path'], 0, 47) . "..." : $view['path'];

            $table .= sprintf(
                "%-5s %-30s %-50s %-20s\n",
                $view['id'],
                $name,
                $path,
                $view['created_at']
            );
        }

        $table .= "\n\n--- JSON Data ---\n" . json_encode([
            'request_id' => $requestId,
            'batch_id' => $batchId,
            'total' => count($views),
            'views' => $views,
        ], JSON_PRETTY_PRINT);

        return Response::text($table);
    }

    protected function getViewDetails(string $id, EntriesRepository $repository): Response
    {
        $entry = $repository->find($id);
        if (!$entry) {
            return Response::error("View rendering not found: {$id}");
        }

        $content = is_array($entry->content) ? $entry->content : [];
        $createdAt = isset($content['created_at']) ? DateFormatter::format($content['created_at']) : 'Unknown';

        $output = "View Details:\n\n";
        $output .= "ID: {$entry->id}\n";
        $output .= "Name: " . ($content['name'] ?? 'Unknown') . "\n";
        $output .= "Path: " . ($content['path'] ?? 'Unknown') . "\n";
        $output .= "Created At: {$createdAt}\n\n";

        if (isset($content['data']) && is_array($content['data'])) {
            $output .= "View Data:\n";
            foreach ($content['data'] as $key => $value) {
                $output .= "- {$key}: " . json_encode($value, JSON_PRETTY_PRINT) . "\n";
            }
            $output .= "\n";
        }

        $jsonData = [
            'id' => $entry->id,
            'name' => $content['name'] ?? 'Unknown',
            'path' => $content['path'] ?? 'Unknown',
            'created_at' => $createdAt,
            'data' => $content['data'] ?? [],
        ];

        $output .= "\n\n--- JSON Data ---\n" . json_encode($jsonData, JSON_PRETTY_PRINT);
        return Response::text($output);
    }
}
