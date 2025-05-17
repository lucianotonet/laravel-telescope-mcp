<?php

namespace LucianoTonet\TelescopeMcp\MCP\Tools;

use Laravel\Telescope\Contracts\EntriesRepository;
use LucianoTonet\TelescopeMcp\Support\Logger;
use LucianoTonet\TelescopeMcp\Support\DateFormatter;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\Storage\EntryQueryOptions;

/**
 * Tool for interacting with scheduled tasks recorded by Telescope
 */
class ScheduleTool extends AbstractTool
{
    /**
     * @var EntriesRepository
     */
    protected $entriesRepository;

    /**
     * ScheduleTool constructor
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
        return 'schedule';
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
            'description' => 'Lists and analyzes scheduled tasks recorded by Telescope.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'id' => [
                        'type' => 'string',
                        'description' => 'ID of the specific scheduled task to view details'
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Maximum number of scheduled tasks to return',
                        'default' => 50
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
                    'description' => 'List last 10 scheduled tasks',
                    'params' => ['limit' => 10]
                ],
                [
                    'description' => 'Get details of a specific scheduled task',
                    'params' => ['id' => '12345']
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
            // Check if details of a specific scheduled task were requested
            if ($this->hasId($params)) {
                return $this->getScheduleDetails($params['id']);
            }

            return $this->listScheduledTasks($params);
        } catch (\Exception $e) {
            Logger::error($this->getName() . ' execution error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->formatError('Error: ' . $e->getMessage());
        }
    }

    /**
     * Lists scheduled tasks recorded by Telescope
     * 
     * @param array $params Query parameters
     * @return array Response in MCP format
     */
    protected function listScheduledTasks(array $params): array
    {
        // Set query limit
        $limit = isset($params['limit']) ? min((int)$params['limit'], 100) : 50;

        // Configure options
        $options = new EntryQueryOptions();
        $options->limit($limit);

        // Fetch entries using the repository
        $entries = $this->entriesRepository->get(EntryType::SCHEDULED_TASK, $options);

        if (empty($entries)) {
            return $this->formatResponse("No scheduled tasks found.");
        }

        $tasks = [];

        foreach ($entries as $entry) {
            $content = is_array($entry->content) ? $entry->content : [];
            $createdAt = DateFormatter::format($entry->createdAt);

            // Extract relevant information from the scheduled task
            $command = $content['command'] ?? 'Unknown';
            $expression = $content['expression'] ?? 'Unknown';
            $description = $content['description'] ?? '';
            $output = $content['output'] ?? '';
            $exitCode = $content['exit_code'] ?? null;
            $status = $exitCode === 0 ? 'Success' : ($exitCode === null ? 'Running' : 'Failed');

            $tasks[] = [
                'id' => $entry->id,
                'command' => $command,
                'expression' => $expression,
                'description' => $description,
                'status' => $status,
                'created_at' => $createdAt
            ];
        }

        // Tabular formatting for better readability
        $table = "Scheduled Tasks:\n\n";
        $table .= sprintf("%-5s %-30s %-15s %-30s %-10s %-20s\n", 
            "ID", "Command", "Expression", "Description", "Status", "Created At");
        $table .= str_repeat("-", 120) . "\n";

        foreach ($tasks as $task) {
            // Truncate fields if too long
            $command = $task['command'];
            if (strlen($command) > 30) {
                $command = substr($command, 0, 27) . "...";
            }

            $description = $task['description'];
            if (strlen($description) > 30) {
                $description = substr($description, 0, 27) . "...";
            }

            $table .= sprintf(
                "%-5s %-30s %-15s %-30s %-10s %-20s\n",
                $task['id'],
                $command,
                $task['expression'],
                $description,
                $task['status'],
                $task['created_at']
            );
        }

        return $this->formatResponse($table);
    }

    /**
     * Gets details of a specific scheduled task
     * 
     * @param string $id The scheduled task ID
     * @return array Response in MCP format
     */
    protected function getScheduleDetails(string $id): array
    {
        Logger::info($this->getName() . ' getting details', ['id' => $id]);

        // Fetch the specific entry
        $entry = $this->getEntryDetails(EntryType::SCHEDULED_TASK, $id);

        if (!$entry) {
            return $this->formatError("Scheduled task not found: {$id}");
        }

        $content = is_array($entry->content) ? $entry->content : [];

        // Detailed formatting of the scheduled task
        $output = "Scheduled Task Details:\n\n";
        $output .= "ID: {$entry->id}\n";
        $output .= "Command: " . ($content['command'] ?? 'Unknown') . "\n";
        $output .= "Expression: " . ($content['expression'] ?? 'Unknown') . "\n";
        $output .= "Description: " . ($content['description'] ?? 'None') . "\n";
        
        $exitCode = $content['exit_code'] ?? null;
        $status = $exitCode === 0 ? 'Success' : ($exitCode === null ? 'Running' : 'Failed');
        $output .= "Status: {$status}\n";
        
        if ($exitCode !== null) {
            $output .= "Exit Code: {$exitCode}\n";
        }

        $createdAt = DateFormatter::format($entry->createdAt);
        $output .= "Created At: {$createdAt}\n\n";

        // Command output
        if (!empty($content['output'])) {
            $output .= "Command Output:\n" . $content['output'] . "\n\n";
        }

        return $this->formatResponse($output);
    }
} 