<?php

namespace LucianoTonet\TelescopeMcp\MCP\Tools;

use Laravel\Telescope\Contracts\EntriesRepository;
use LucianoTonet\TelescopeMcp\Support\Logger;
use LucianoTonet\TelescopeMcp\Support\DateFormatter;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\Storage\EntryQueryOptions;

/**
 * Tool for interacting with Redis operations recorded by Telescope
 */
class RedisTool extends AbstractTool
{
    /**
     * @var EntriesRepository
     */
    protected $entriesRepository;

    /**
     * RedisTool constructor
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
        return 'redis';
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
            'description' => 'Lists and analyzes Redis operations recorded by Telescope.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'id' => [
                        'type' => 'string',
                        'description' => 'ID of the specific Redis operation to view details'
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Maximum number of Redis operations to return',
                        'default' => 50
                    ],
                    'command' => [
                        'type' => 'string',
                        'description' => 'Filter by Redis command (e.g., GET, SET, DEL)'
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
                    'description' => 'List last 10 Redis operations',
                    'params' => ['limit' => 10]
                ],
                [
                    'description' => 'Get details of a specific Redis operation',
                    'params' => ['id' => '12345']
                ],
                [
                    'description' => 'List all SET operations',
                    'params' => ['command' => 'SET']
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
            // Check if details of a specific Redis operation were requested
            if ($this->hasId($params)) {
                return $this->getRedisDetails($params['id']);
            }

            return $this->listRedisOperations($params);
        } catch (\Exception $e) {
            Logger::error($this->getName() . ' execution error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->formatError('Error: ' . $e->getMessage());
        }
    }

    /**
     * Lists Redis operations recorded by Telescope
     * 
     * @param array $params Query parameters
     * @return array Response in MCP format
     */
    protected function listRedisOperations(array $params): array
    {
        // Set query limit
        $limit = isset($params['limit']) ? min((int)$params['limit'], 100) : 50;

        // Configure options
        $options = new EntryQueryOptions();
        $options->limit($limit);

        // Add filters if specified
        if (!empty($params['command'])) {
            $options->tag('command:' . strtoupper($params['command']));
        }

        // Fetch entries using the repository
        $entries = $this->entriesRepository->get(EntryType::REDIS, $options);

        if (empty($entries)) {
            return $this->formatResponse("No Redis operations found.");
        }

        $operations = [];

        foreach ($entries as $entry) {
            $content = is_array($entry->content) ? $entry->content : [];
            $createdAt = DateFormatter::format($entry->createdAt);

            // Extract relevant information from the Redis operation
            $command = $content['command'] ?? 'Unknown';
            $parameters = $content['parameters'] ?? [];
            $duration = $content['duration'] ?? 0;

            $operations[] = [
                'id' => $entry->id,
                'command' => $command,
                'parameters' => $parameters,
                'duration' => $duration,
                'created_at' => $createdAt
            ];
        }

        // Tabular formatting for better readability
        $table = "Redis Operations:\n\n";
        $table .= sprintf("%-5s %-15s %-50s %-10s %-20s\n", 
            "ID", "Command", "Parameters", "Time (ms)", "Created At");
        $table .= str_repeat("-", 120) . "\n";

        foreach ($operations as $op) {
            // Format parameters for display
            $params = implode(' ', array_map(function($param) {
                return strlen($param) > 20 ? substr($param, 0, 17) . "..." : $param;
            }, $op['parameters']));

            if (strlen($params) > 50) {
                $params = substr($params, 0, 47) . "...";
            }

            $table .= sprintf(
                "%-5s %-15s %-50s %-10s %-20s\n",
                $op['id'],
                $op['command'],
                $params,
                number_format($op['duration'], 2),
                $op['created_at']
            );
        }

        return $this->formatResponse($table);
    }

    /**
     * Gets details of a specific Redis operation
     * 
     * @param string $id The Redis operation ID
     * @return array Response in MCP format
     */
    protected function getRedisDetails(string $id): array
    {
        Logger::info($this->getName() . ' getting details', ['id' => $id]);

        // Fetch the specific entry
        $entry = $this->getEntryDetails(EntryType::REDIS, $id);

        if (!$entry) {
            return $this->formatError("Redis operation not found: {$id}");
        }

        $content = is_array($entry->content) ? $entry->content : [];

        // Detailed formatting of the Redis operation
        $output = "Redis Operation Details:\n\n";
        $output .= "ID: {$entry->id}\n";
        $output .= "Command: " . ($content['command'] ?? 'Unknown') . "\n";
        $output .= "Duration: " . number_format(($content['duration'] ?? 0), 2) . " ms\n";

        $createdAt = DateFormatter::format($entry->createdAt);
        $output .= "Created At: {$createdAt}\n\n";

        // Parameters
        if (!empty($content['parameters'])) {
            $output .= "Parameters:\n";
            foreach ($content['parameters'] as $index => $param) {
                $output .= sprintf("%d: %s\n", $index + 1, $param);
            }
            $output .= "\n";
        }

        // Connection
        if (!empty($content['connection'])) {
            $output .= "Connection: " . $content['connection'] . "\n\n";
        }

        // Result (if available)
        if (isset($content['result'])) {
            $output .= "Result:\n" . json_encode($content['result'], JSON_PRETTY_PRINT) . "\n";
        }

        return $this->formatResponse($output);
    }
} 