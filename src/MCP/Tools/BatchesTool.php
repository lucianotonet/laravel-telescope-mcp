<?php

namespace LucianoTonet\TelescopeMcp\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Telescope\Contracts\EntriesRepository;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\Storage\EntryQueryOptions;
use LucianoTonet\TelescopeMcp\Support\DateFormatter;

/**
 * Tool for interacting with batch operations recorded by Telescope
 */
class BatchesTool extends Tool
{
    protected string $name = 'batches';
    protected string $title = 'Telescope Batches';
    protected string $description = 'Lists and analyzes batch operations recorded by Telescope.';

    public function handle(Request $request, EntriesRepository $repository): Response
    {
        try {
            if ($id = $request->get('id')) {
                return $this->getBatchDetails($id, $repository);
            }
            return $this->listBatches($request, $repository);
        } catch (\Exception $e) {
            return Response::error('Error: ' . $e->getMessage());
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('ID of specific batch operation'),
            'limit' => $schema->integer()->default(50)->description('Maximum number of batch operations to return'),
            'status' => $schema->string()->enum(['pending', 'processing', 'finished', 'failed'])->description('Filter by batch status'),
            'name' => $schema->string()->description('Filter by batch name'),
        ];
    }

    protected function listBatches(Request $request, EntriesRepository $repository): Response
    {
        $limit = min($request->integer('limit', 50), 100);
        $options = new EntryQueryOptions();
        $options->limit($limit);

        if ($status = $request->get('status')) {
            $options->tag('status:' . $status);
        }
        if ($name = $request->get('name')) {
            $options->tag('name:' . $name);
        }

        $entries = $repository->get(EntryType::BATCH, $options);

        if (empty($entries)) {
            return Response::text("No batch operations found.");
        }

        $batches = [];
        foreach ($entries as $entry) {
            $content = is_array($entry->content) ? $entry->content : [];
            $createdAt = DateFormatter::format($entry->createdAt);

            $name = $content['name'] ?? 'Unknown';
            $status = $content['status'] ?? 'Unknown';
            $totalJobs = $content['totalJobs'] ?? 0;
            $pendingJobs = $content['pendingJobs'] ?? 0;
            $failedJobs = $content['failedJobs'] ?? 0;
            $finishedJobs = $totalJobs - $pendingJobs - $failedJobs;

            $batches[] = [
                'id' => $entry->id,
                'name' => $name,
                'status' => $status,
                'total' => $totalJobs,
                'finished' => $finishedJobs,
                'pending' => $pendingJobs,
                'failed' => $failedJobs,
                'created_at' => $createdAt,
            ];
        }

        $table = "Batch Operations:\n\n";
        $table .= sprintf(
            "%-5s %-30s %-10s %-8s %-8s %-8s %-8s %-20s\n",
            "ID",
            "Name",
            "Status",
            "Total",
            "Done",
            "Pending",
            "Failed",
            "Created At"
        );
        $table .= str_repeat("-", 105) . "\n";

        foreach ($batches as $batch) {
            $statusStr = strtoupper($batch['status']);
            switch (strtolower($batch['status'])) {
                case 'failed':
                    $statusStr .= ' [!]';
                    break;
                case 'finished':
                    $statusStr .= ' [✓]';
                    break;
                case 'processing':
                    $statusStr .= ' [→]';
                    break;
            }

            $name = $this->safeString($batch['name']);
            if (strlen($name) > 30) {
                $name = substr($name, 0, 27) . "...";
            }

            $table .= sprintf(
                "%-5s %-30s %-10s %-8d %-8d %-8d %-8d %-20s\n",
                $batch['id'],
                $name,
                $statusStr,
                $batch['total'],
                $batch['finished'],
                $batch['pending'],
                $batch['failed'],
                $batch['created_at']
            );
        }

        return Response::text($table);
    }

    protected function getBatchDetails(string $id, EntriesRepository $repository): Response
    {
        $entry = $repository->find($id);
        if (!$entry) {
            return Response::error("Batch operation not found: {$id}");
        }

        $content = is_array($entry->content) ? $entry->content : [];

        $output = "Batch Operation Details:\n\n";
        $output .= "ID: {$entry->id}\n";
        $output .= "Name: " . ($content['name'] ?? 'Unknown') . "\n";
        $output .= "Status: " . strtoupper($content['status'] ?? 'Unknown') . "\n";

        $totalJobs = $content['totalJobs'] ?? 0;
        $pendingJobs = $content['pendingJobs'] ?? 0;
        $failedJobs = $content['failedJobs'] ?? 0;
        $finishedJobs = $totalJobs - $pendingJobs - $failedJobs;

        $output .= "\nProgress:\n";
        $output .= "Total Jobs: {$totalJobs}\n";
        $output .= "Finished Jobs: {$finishedJobs}\n";
        $output .= "Pending Jobs: {$pendingJobs}\n";
        $output .= "Failed Jobs: {$failedJobs}\n";

        if ($totalJobs > 0) {
            $progress = ($finishedJobs / $totalJobs) * 100;
            $output .= "Completion: " . number_format($progress, 1) . "%\n";
        }

        $createdAt = DateFormatter::format($entry->createdAt);
        $output .= "\nCreated At: {$createdAt}\n";

        if (!empty($content['options'])) {
            $output .= "\nOptions:\n" . json_encode($content['options'], JSON_PRETTY_PRINT) . "\n";
        }

        if ($failedJobs > 0 && !empty($content['failedJobs'])) {
            $output .= "\nFailed Jobs:\n";
            foreach ($content['failedJobs'] as $job) {
                $output .= "- Job: " . ($job['name'] ?? 'Unknown') . "\n";
                $output .= "  Error: " . ($job['error'] ?? 'Unknown error') . "\n";
                if (!empty($job['stack'])) {
                    $output .= "  Stack Trace:\n    " . implode("\n    ", array_slice($job['stack'], 0, 5)) . "\n";
                    if (count($job['stack']) > 5) {
                        $output .= "    ... (truncated)\n";
                    }
                }
                $output .= "\n";
            }
        }

        return Response::text($output);
    }

    protected function safeString($value): string
    {
        if (!is_string($value)) {
            return (string) $value;
        }
        return $value;
    }
}
