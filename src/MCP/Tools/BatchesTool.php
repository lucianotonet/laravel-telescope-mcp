<?php

namespace LucianoTonet\TelescopeMcp\MCP\Tools;

use Laravel\Telescope\Contracts\EntriesRepository;
use LucianoTonet\TelescopeMcp\Support\Logger;
use LucianoTonet\TelescopeMcp\Support\DateFormatter;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\Storage\EntryQueryOptions;

/**
 * Tool for interacting with batch operations recorded by Telescope
 */
class BatchesTool extends AbstractTool
{
    /**
     * @var EntriesRepository
     */
    protected $entriesRepository;

    /**
     * BatchesTool constructor
     * 
     * @param EntriesRepository $entriesRepository The Telescope entries repository
     */
    public function __construct(EntriesRepository $entriesRepository)
    {
        $this->entriesRepository = $entriesRepository;
    }

    /**
     * Returns the tool's short name
     * 
     * @return string
     */
    public function getShortName(): string
    {
        return 'batches';
    }

    /**
     * Returns the tool's schema
     * 
     * @return array
     */
    public function getSchema(): array
    {
        return [
            'name' => $this->getName(),
            'description' => 'Lists and analyzes batch operations recorded by Telescope.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'id' => [
                        'type' => 'string',
                        'description' => 'ID of the specific batch operation to view details'
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Maximum number of batch operations to return',
                        'default' => 50
                    ],
                    'status' => [
                        'type' => 'string',
                        'description' => 'Filter by batch status (pending, processing, finished, failed)',
                        'enum' => ['pending', 'processing', 'finished', 'failed']
                    ],
                    'name' => [
                        'type' => 'string',
                        'description' => 'Filter by batch name'
                    ]
                ],
                'required' => []
            ],
            'outputSchema' => [
                'type' => 'object',
                'properties' => [
                    'content' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'type' => ['type' => 'string'],
                                'text' => ['type' => 'string']
                            ],
                            'required' => ['type', 'text']
                        ]
                    ]
                ],
                'required' => ['content']
            ],
            'examples' => [
                [
                    'description' => 'List last 10 batch operations',
                    'params' => ['limit' => 10]
                ],
                [
                    'description' => 'Get details of a specific batch operation',
                    'params' => ['id' => '12345']
                ],
                [
                    'description' => 'List failed batch operations',
                    'params' => ['status' => 'failed']
                ]
            ]
        ];
    }

    /**
     * Executes the tool with the given parameters
     * 
     * @param array $params Tool parameters
     * @return array Response in MCP format
     */
    public function execute(array $params): array
    {
        Logger::info($this->getName() . ' execute method called', ['params' => $params]);

        try {
            // Check if details of a specific batch operation were requested
            if ($this->hasId($params)) {
                return $this->getBatchDetails($params['id']);
            }

            return $this->listBatches($params);
        } catch (\Exception $e) {
            Logger::error($this->getName() . ' execution error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->formatError('Error: ' . $e->getMessage());
        }
    }

    /**
     * Lists batch operations recorded by Telescope
     * 
     * @param array $params Query parameters
     * @return array Response in MCP format
     */
    protected function listBatches(array $params): array
    {
        // Set query limit
        $limit = isset($params['limit']) ? min((int)$params['limit'], 100) : 50;

        // Configure options
        $options = new EntryQueryOptions();
        $options->limit($limit);

        // Add filters if specified
        if (!empty($params['status'])) {
            $options->tag('status:' . $params['status']);
        }
        if (!empty($params['name'])) {
            $options->tag('name:' . $params['name']);
        }

        // Fetch entries using the repository
        $entries = $this->entriesRepository->get(EntryType::BATCH, $options);

        if (empty($entries)) {
            return $this->formatResponse("No batch operations found.");
        }

        $batches = [];

        foreach ($entries as $entry) {
            $content = is_array($entry->content) ? $entry->content : [];
            $createdAt = DateFormatter::format($entry->createdAt);

            // Extract relevant information from the batch operation
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
                'created_at' => $createdAt
            ];
        }

        // Tabular formatting for better readability
        $table = "Batch Operations:\n\n";
        $table .= sprintf("%-5s %-30s %-10s %-8s %-8s %-8s %-8s %-20s\n", 
            "ID", "Name", "Status", "Total", "Done", "Pending", "Failed", "Created At");
        $table .= str_repeat("-", 105) . "\n";

        foreach ($batches as $batch) {
            // Format status with indicator
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

            // Truncate name if too long
            $name = $batch['name'];
            $name = $this->safeString($name);
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

        return $this->formatResponse($table);
    }

    /**
     * Gets details of a specific batch operation
     * 
     * @param string $id The batch operation ID
     * @return array Response in MCP format
     */
    protected function getBatchDetails(string $id): array
    {
        Logger::info($this->getName() . ' getting details', ['id' => $id]);

        // Fetch the specific entry
        $entry = $this->getEntryDetails(EntryType::BATCH, $id);

        if (!$entry) {
            return $this->formatError("Batch operation not found: {$id}");
        }

        $content = is_array($entry->content) ? $entry->content : [];

        // Detailed formatting of the batch operation
        $output = "Batch Operation Details:\n\n";
        $output .= "ID: {$entry->id}\n";
        $output .= "Name: " . ($content['name'] ?? 'Unknown') . "\n";
        $output .= "Status: " . strtoupper($content['status'] ?? 'Unknown') . "\n";

        // Progress information
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

        // Options and configuration
        if (!empty($content['options'])) {
            $output .= "\nOptions:\n" . json_encode($content['options'], JSON_PRETTY_PRINT) . "\n";
        }

        // Failed jobs details
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

        return $this->formatResponse($output);
    }
} 