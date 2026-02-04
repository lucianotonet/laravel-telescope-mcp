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
 * Tool for interacting with jobs recorded by Telescope
 */
class JobsTool extends Tool implements IsReadOnly
{
    protected string $name = 'jobs';
    protected string $title = 'Telescope Jobs';
    protected string $description = 'Lists and analyzes jobs (queued tasks) recorded by Telescope.';

    public function handle(Request $request, EntriesRepository $repository): Response
    {
        try {
            if ($id = $request->get('id')) {
                return $this->getJobDetails($id, $repository);
            }
            return $this->listJobs($request, $repository);
        } catch (\Exception $e) {
            return Response::error('Error: ' . $e->getMessage());
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('ID of specific job'),
            'limit' => $schema->integer()->default(50)->description('Max jobs'),
            'status' => $schema->string()->description('Filter by status (pending, processed, failed)'),
            'queue' => $schema->string()->description('Filter by specific queue'),
        ];
    }

    protected function listJobs(Request $request, EntriesRepository $repository): Response
    {
        $limit = min($request->integer('limit', 50), 100);
        $options = new EntryQueryOptions();
        $options->limit($limit);

        if ($status = $request->get('status')) $options->tag($status);
        if ($queue = $request->get('queue')) $options->tag($queue);

        $entries = $repository->get(EntryType::JOB, $options);
        if (empty($entries)) return Response::text("No jobs found.");

        $jobs = [];
        foreach ($entries as $entry) {
            $content = is_array($entry->content) ? $entry->content : [];
            $jobs[] = [
                'id' => $entry->id,
                'name' => $content['name'] ?? 'Unknown',
                'status' => $content['status'] ?? 'Unknown',
                'queue' => $content['queue'] ?? 'default',
                'attempts' => $content['attempts'] ?? 0,
                'created_at' => DateFormatter::format($entry->createdAt)
            ];
        }

        $table = "Jobs:\n\n";
        $table .= sprintf("%-5s %-40s %-10s %-15s %-8s %-20s\n", "ID", "Name", "Status", "Queue", "Attempts", "Created At");
        $table .= str_repeat("-", 105) . "\n";

        foreach ($jobs as $job) {
            $name = strlen($job['name']) > 40 ? substr($job['name'], 0, 37) . "..." : $job['name'];
            $table .= sprintf("%-5s %-40s %-10s %-15s %-8s %-20s\n",
                $job['id'], $name, $job['status'], $job['queue'], $job['attempts'], $job['created_at']);
        }

        $table .= "\n\n--- JSON Data ---\n" . json_encode(['total' => count($jobs), 'jobs' => $jobs], JSON_PRETTY_PRINT);
        return Response::text($table);
    }

    protected function getJobDetails(string $id, EntriesRepository $repository): Response
    {
        $entry = $repository->find($id);
        if (!$entry) return Response::error("Job not found: {$id}");

        $content = is_array($entry->content) ? $entry->content : [];
        $createdAt = DateFormatter::format($entry->createdAt);

        $output = "Job Details:\n\n";
        $output .= "ID: {$entry->id}\n";
        $output .= "Name: " . ($content['name'] ?? 'Unknown') . "\n";
        $output .= "Status: " . ($content['status'] ?? 'Unknown') . "\n";
        $output .= "Queue: " . ($content['queue'] ?? 'default') . "\n";
        $output .= "Attempts: " . ($content['attempts'] ?? 0) . "\n";
        $output .= "Created At: {$createdAt}\n\n";

        if (isset($content['data']) && !empty($content['data'])) {
            $output .= "Data:\n" . json_encode($content['data'], JSON_PRETTY_PRINT) . "\n\n";
        }

        if (isset($content['exception']) && !empty($content['exception'])) {
            $output .= "Exception:\n" . $content['exception'] . "\n";
        }

        $jsonData = [
            'id' => $entry->id,
            'name' => $content['name'] ?? 'Unknown',
            'status' => $content['status'] ?? 'Unknown',
            'queue' => $content['queue'] ?? 'default',
            'attempts' => $content['attempts'] ?? 0,
            'created_at' => $createdAt,
        ];

        $output .= "\n\n--- JSON Data ---\n" . json_encode($jsonData, JSON_PRETTY_PRINT);
        return Response::text($output);
    }
} 