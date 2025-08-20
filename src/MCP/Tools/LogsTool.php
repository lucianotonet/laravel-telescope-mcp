<?php

namespace LucianoTonet\TelescopeMcp\MCP\Tools;

use Laravel\Telescope\Contracts\EntriesRepository;
use LucianoTonet\TelescopeMcp\Support\Logger;
use LucianoTonet\TelescopeMcp\Support\DateFormatter;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\Storage\EntryQueryOptions;

/**
 * Tool for interacting with log entries recorded by Telescope
 */
class LogsTool extends AbstractTool
{
    /**
     * @var EntriesRepository
     */
    protected $entriesRepository;

    /**
     * LogsTool constructor
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
        return 'logs';
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
            'description' => 'Lists and analyzes log entries recorded by Telescope.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'id' => [
                        'type' => 'string',
                        'description' => 'ID of the specific log entry to view details'
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Maximum number of log entries to return',
                        'default' => 50
                    ],
                    'level' => [
                        'type' => 'string',
                        'description' => 'Filter by log level (debug, info, notice, warning, error, critical, alert, emergency)',
                        'enum' => ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency']
                    ],
                    'message' => [
                        'type' => 'string',
                        'description' => 'Filter by log message content'
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
                                'type' => [
                                    'type' => 'string',
                                    'enum' => ['text', 'json', 'markdown', 'html']
                                ],
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
                    'description' => 'List last 10 log entries',
                    'params' => ['limit' => 10]
                ],
                [
                    'description' => 'Get details of a specific log entry',
                    'params' => ['id' => '12345']
                ],
                [
                    'description' => 'List error logs',
                    'params' => ['level' => 'error']
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
            // Check if details of a specific log entry were requested
            if ($this->hasId($params)) {
                return $this->getLogDetails($params['id']);
            }

            return $this->listLogs($params);
        } catch (\Exception $e) {
            Logger::error($this->getName() . ' execution error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->formatError('Error: ' . $e->getMessage());
        }
    }

    /**
     * Lists log entries
     * 
     * @param array $params Tool parameters
     * @return array Response in MCP format
     */
    protected function listLogs(array $params): array
    {
        Logger::info($this->getName() . ' listing entries', $params);

        // Create query options
        $options = new EntryQueryOptions($params['limit'] ?? 50);

        // Add filters if specified
        if (!empty($params['level'])) {
            $options->tag('level:' . strtolower($params['level']));
        }
        if (!empty($params['message'])) {
            $options->tag('message:' . $params['message']);
        }

        // Fetch entries using the repository
        $entries = $this->entriesRepository->get(EntryType::LOG, $options);

        if (empty($entries)) {
            return $this->formatResponse("No log entries found.");
        }

        $logs = [];

        foreach ($entries as $entry) {
            $content = is_array($entry->content) ? $entry->content : [];
            $createdAt = DateFormatter::format($entry->createdAt);

            // Extract relevant information from the log entry
            $level = $content['level'] ?? 'Unknown';
            $message = $content['message'] ?? 'No message';

            $logs[] = [
                'id' => $entry->id,
                'level' => $level,
                'message' => $message,
                'created_at' => $createdAt
            ];
        }

        // Tabular formatting for better readability
        $table = "Log Entries:\n\n";
        $table .= sprintf("%-5s %-10s %-60s %-20s\n", 
            "ID", "Level", "Message", "Created At");
        $table .= str_repeat("-", 100) . "\n";

        foreach ($logs as $log) {
            // Truncate message if too long
            $message = $log['message'];
            $message = $this->safeString($message);
            if (strlen($message) > 60) {
                $message = substr($message, 0, 57) . "...";
            }

            $table .= sprintf("%-5s %-10s %-60s %-20s\n",
                $log['id'],
                strtoupper($log['level']),
                $message,
                $log['created_at']
            );
        }

        $combinedText = $table . "\n\n--- JSON Data ---\n" . json_encode([
            'total' => count($logs),
            'logs' => $logs
        ], JSON_PRETTY_PRINT);
        
        return $this->formatResponse($combinedText);
    }

    /**
     * Gets details of a specific log entry
     * 
     * @param string $id The log entry ID
     * @return array Response in MCP format
     */
    protected function getLogDetails(string $id): array
    {
        Logger::info($this->getName() . ' getting details', ['id' => $id]);

        // Fetch the specific entry
        $entry = $this->getEntryDetails(EntryType::LOG, $id);

        if (!$entry) {
            return $this->formatError("Log entry not found: {$id}");
        }

        $content = is_array($entry->content) ? $entry->content : [];

        // Detailed formatting of the log entry
        $output = "Log Entry Details:\n\n";
        $output .= "ID: {$entry->id}\n";
        $output .= "Level: " . strtoupper($content['level'] ?? 'Unknown') . "\n";
        $output .= "Message: " . ($content['message'] ?? 'No message') . "\n";
        
        $createdAt = DateFormatter::format($entry->createdAt);
        $output .= "Created At: {$createdAt}\n\n";

        // Context information
        if (!empty($content['context'])) {
            $output .= "Context:\n" . json_encode($content['context'], JSON_PRETTY_PRINT) . "\n";
        }

        $combinedText = $output . "\n\n--- JSON Data ---\n" . json_encode([
            'id' => $entry->id,
            'level' => $content['level'] ?? 'Unknown',
            'message' => $content['message'] ?? 'No message',
            'created_at' => $createdAt,
            'context' => $content['context'] ?? []
        ], JSON_PRETTY_PRINT);
        
        return $this->formatResponse($combinedText);
    }
} 