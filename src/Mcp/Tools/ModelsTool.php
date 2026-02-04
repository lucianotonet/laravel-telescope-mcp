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
 * Tool for interacting with Eloquent model operations recorded by Telescope
 */
class ModelsTool extends Tool
{
    use BatchQuerySupport;

    protected string $name = 'models';
    protected string $title = 'Telescope Models';
    protected string $description = 'Lists and analyzes Eloquent model operations recorded by Telescope.';

    public function handle(Request $request, EntriesRepository $repository): Response
    {
        try {
            if ($id = $request->get('id')) {
                $includeRelated = $request->boolean('include_related', true);
                return $this->getModelDetails($id, $includeRelated, $repository);
            }

            if ($requestId = $request->get('request_id')) {
                return $this->listModelsForRequest($requestId, $request, $repository);
            }

            return $this->listModelOperations($request, $repository);
        } catch (\Exception $e) {
            return Response::error('Error: ' . $e->getMessage());
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('ID of specific model operation'),
            'request_id' => $schema->string()->description('Filter by request ID (uses batch_id grouping)'),
            'limit' => $schema->integer()->default(50)->description('Max operations'),
            'action' => $schema->string()->description('Filter by action type (created, updated, deleted)'),
            'model' => $schema->string()->description('Filter by model name'),
            'include_related' => $schema->boolean()->default(true),
        ];
    }

    protected function listModelOperations(Request $request, EntriesRepository $repository): Response
    {
        $limit = min($request->integer('limit', 50), 100);
        $options = new EntryQueryOptions();
        $options->limit($limit);

        if ($action = $request->get('action')) {
            $options->tag($action);
        }
        if ($model = $request->get('model')) {
            $options->tag($model);
        }

        $entries = $repository->get(EntryType::MODEL, $options);
        if (empty($entries)) {
            return Response::text("No model operations found.");
        }

        $operations = [];
        foreach ($entries as $entry) {
            $content = is_array($entry->content) ? $entry->content : [];
            $operations[] = [
                'id' => $entry->id,
                'action' => $content['action'] ?? 'Unknown',
                'model' => $content['model'] ?? 'Unknown',
                'model_id' => $content['model_id'] ?? 'N/A',
                'created_at' => isset($content['created_at']) ? DateFormatter::format($content['created_at']) : 'Unknown',
            ];
        }

        $table = "Model Operations:\n\n";
        $table .= sprintf("%-5s %-8s %-40s %-10s %-20s\n", "ID", "Action", "Model", "Model ID", "Created At");
        $table .= str_repeat("-", 90) . "\n";

        foreach ($operations as $op) {
            $model = strlen($op['model']) > 40 ? substr($op['model'], 0, 37) . "..." : $op['model'];
            $table .= sprintf(
                "%-5s %-8s %-40s %-10s %-20s\n",
                $op['id'],
                $op['action'],
                $model,
                $op['model_id'],
                $op['created_at']
            );
        }

        $table .= "\n\n--- JSON Data ---\n" . json_encode(['total' => count($operations), 'operations' => $operations], JSON_PRETTY_PRINT);
        return Response::text($table);
    }

    protected function listModelsForRequest(string $requestId, Request $request, EntriesRepository $repository): Response
    {
        $batchId = $this->getBatchIdForRequest($requestId);
        if (!$batchId) {
            return Response::error("Request not found or has no batch ID: {$requestId}");
        }

        $limit = min($request->integer('limit', 50), 100);
        $entries = $this->getEntriesByBatchId($batchId, 'model', $limit);

        if (empty($entries)) {
            return Response::text("No model operations found for request: {$requestId}");
        }

        $filterAction = $request->get('action');
        $operations = [];

        foreach ($entries as $entry) {
            $content = is_array($entry->content) ? $entry->content : [];
            $action = $content['action'] ?? 'Unknown';

            if ($filterAction && strtolower($action) !== strtolower($filterAction)) {
                continue;
            }

            $operations[] = [
                'id' => $entry->id,
                'action' => $action,
                'model' => $content['model'] ?? 'Unknown',
                'model_id' => $content['model_id'] ?? 'N/A',
                'created_at' => isset($entry->createdAt) ? DateFormatter::format($entry->createdAt) : 'Unknown',
            ];
        }

        $table = "Model Operations for Request: {$requestId}\n";
        $table .= "Batch ID: {$batchId}\n";
        $table .= "Total: " . count($operations) . " operations\n\n";
        $table .= sprintf("%-5s %-8s %-40s %-10s %-20s\n", "ID", "Action", "Model", "Model ID", "Created At");
        $table .= str_repeat("-", 90) . "\n";

        foreach ($operations as $op) {
            $model = strlen($op['model']) > 40 ? substr($op['model'], 0, 37) . "..." : $op['model'];
            $table .= sprintf(
                "%-5s %-8s %-40s %-10s %-20s\n",
                $op['id'],
                $op['action'],
                $model,
                $op['model_id'],
                $op['created_at']
            );
        }

        $table .= "\n\n--- JSON Data ---\n" . json_encode([
            'request_id' => $requestId,
            'batch_id' => $batchId,
            'total' => count($operations),
            'operations' => $operations,
        ], JSON_PRETTY_PRINT);
        return Response::text($table);
    }

    protected function getModelDetails(string $id, bool $includeRelated, EntriesRepository $repository): Response
    {
        $entry = $repository->find($id);
        if (!$entry) {
            return Response::error("Model operation not found: {$id}");
        }

        $content = is_array($entry->content) ? $entry->content : [];
        $createdAt = isset($content['created_at']) ? DateFormatter::format($content['created_at']) : 'Unknown';

        $output = "Model Operation Details:\n\n";
        $output .= "ID: {$entry->id}\n";
        $output .= "Action: " . ($content['action'] ?? 'Unknown') . "\n";
        $output .= "Model: " . ($content['model'] ?? 'Unknown') . "\n";
        $output .= "Model ID: " . ($content['model_id'] ?? 'N/A') . "\n";
        $output .= "Created At: {$createdAt}\n";

        $relatedSummary = [];
        if ($includeRelated && isset($entry->batchId) && $entry->batchId) {
            $summary = $this->getBatchSummary($entry->batchId);
            $typeLabels = ['query' => 'Queries', 'log' => 'Logs', 'cache' => 'Cache Operations',
                'request' => 'Requests', 'view' => 'Views', 'exception' => 'Exceptions',
                'event' => 'Events', 'job' => 'Jobs', 'mail' => 'Mails',
                'notification' => 'Notifications', 'redis' => 'Redis Operations'];

            $output .= "\n--- Related Entries ---\n";
            $hasRelated = false;
            foreach ($summary as $type => $count) {
                if ($type !== 'model') {
                    $label = $typeLabels[$type] ?? ucfirst($type);
                    $output .= "- {$label}: {$count}\n";
                    $relatedSummary[$type] = $count;
                    $hasRelated = true;
                }
            }
            if (!$hasRelated) {
                $output .= "(No related entries found)\n";
            }
        }

        $output .= "\n";

        // Old attributes (for update/delete)
        if (isset($content['old']) && !empty($content['old'])) {
            $output .= "Old Attributes:\n" . json_encode($content['old'], JSON_PRETTY_PRINT) . "\n\n";
        }

        // New attributes (for create/update)
        if (isset($content['attributes']) && !empty($content['attributes'])) {
            $output .= "New Attributes:\n" . json_encode($content['attributes'], JSON_PRETTY_PRINT) . "\n";
        }

        // Changes (differences for update)
        if (isset($content['changes']) && !empty($content['changes'])) {
            $output .= "\nChanges:\n" . json_encode($content['changes'], JSON_PRETTY_PRINT) . "\n";
        }

        $jsonData = [
            'id' => $entry->id,
            'batch_id' => $entry->batchId ?? null,
            'action' => $content['action'] ?? 'Unknown',
            'model' => $content['model'] ?? 'Unknown',
            'model_id' => $content['model_id'] ?? 'N/A',
            'created_at' => $createdAt,
            'old' => $content['old'] ?? [],
            'attributes' => $content['attributes'] ?? [],
            'changes' => $content['changes'] ?? [],
        ];
        if (!empty($relatedSummary)) {
            $jsonData['related_entries'] = $relatedSummary;
        }

        $output .= "\n\n--- JSON Data ---\n" . json_encode($jsonData, JSON_PRETTY_PRINT);
        return Response::text($output);
    }
}
