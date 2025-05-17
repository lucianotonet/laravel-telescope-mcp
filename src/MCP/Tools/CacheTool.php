<?php

namespace LucianoTonet\TelescopeMcp\MCP\Tools;

use Laravel\Telescope\Contracts\EntriesRepository;
use LucianoTonet\TelescopeMcp\Support\Logger;
use LucianoTonet\TelescopeMcp\Support\DateFormatter;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\Storage\EntryQueryOptions;

/**
 * Tool for interacting with cache operations recorded by Telescope
 */
class CacheTool extends AbstractTool
{
    /**
     * @var EntriesRepository
     */
    protected $entriesRepository;

    /**
     * CacheTool constructor
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
        return 'cache';
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
            'description' => 'Lists and analyzes cache operations recorded by Telescope.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'id' => [
                        'type' => 'string',
                        'description' => 'ID of the specific cache operation to view details'
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Maximum number of cache operations to return',
                        'default' => 50
                    ],
                    'operation' => [
                        'type' => 'string',
                        'description' => 'Filter by operation type (hit, miss, set, forget)',
                        'enum' => ['hit', 'miss', 'set', 'forget']
                    ],
                    'key' => [
                        'type' => 'string',
                        'description' => 'Filter by cache key (partial match)'
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
                    'description' => 'List last 10 cache operations',
                    'params' => ['limit' => 10]
                ],
                [
                    'description' => 'Get details of a specific cache operation',
                    'params' => ['id' => '12345']
                ],
                [
                    'description' => 'List cache misses',
                    'params' => ['operation' => 'miss']
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
            // Check if details of a specific cache operation were requested
            if ($this->hasId($params)) {
                return $this->getCacheDetails($params['id']);
            }

            return $this->listCache($params);
        } catch (\Exception $e) {
            Logger::error($this->getName() . ' execution error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->formatError('Error: ' . $e->getMessage());
        }
    }

    /**
     * Lists cache operations
     * 
     * @param array $params Tool parameters
     * @return array Response in MCP format
     */
    protected function listCache(array $params): array
    {
        Logger::info($this->getName() . ' listing entries', $params);

        // Create query options
        $options = new EntryQueryOptions($params['limit'] ?? 50);

        // Add filters if specified
        if (!empty($params['operation'])) {
            $options->tag('operation:' . strtolower($params['operation']));
        }
        if (!empty($params['key'])) {
            $options->tag('key:' . $params['key']);
        }

        // Fetch entries using the repository
        $entries = $this->entriesRepository->get(EntryType::CACHE, $options);

        if (empty($entries)) {
            return $this->formatResponse("No cache operations found.");
        }

        $operations = [];

        foreach ($entries as $entry) {
            $content = is_array($entry->content) ? $entry->content : [];
            $createdAt = DateFormatter::format($entry->createdAt);

            // Extract relevant information from the cache operation
            $operation = $content['type'] ?? 'Unknown';
            $key = $content['key'] ?? 'Unknown';
            $duration = $content['duration'] ?? 0;

            $operations[] = [
                'id' => $entry->id,
                'operation' => $operation,
                'key' => $key,
                'duration' => $duration,
                'created_at' => $createdAt
            ];
        }

        // Tabular formatting for better readability
        $table = "Cache Operations:\n\n";
        $table .= sprintf("%-5s %-8s %-50s %-10s %-20s\n", 
            "ID", "Type", "Key", "Time (ms)", "Created At");
        $table .= str_repeat("-", 100) . "\n";

        foreach ($operations as $op) {
            // Truncate key if too long
            $key = $op['key'];
            if (strlen($key) > 50) {
                $key = substr($key, 0, 47) . "...";
            }

            $table .= sprintf("%-5s %-8s %-50s %-10.2f %-20s\n",
                $op['id'],
                $op['operation'],
                $key,
                $op['duration'],
                $op['created_at']
            );
        }

        return $this->formatResponse($table);
    }

    /**
     * Gets details of a specific cache operation
     * 
     * @param string $id The cache operation ID
     * @return array Response in MCP format
     */
    protected function getCacheDetails(string $id): array
    {
        Logger::info($this->getName() . ' getting details', ['id' => $id]);

        // Fetch the specific entry
        $entry = $this->getEntryDetails(EntryType::CACHE, $id);

        if (!$entry) {
            return $this->formatError("Cache operation not found: {$id}");
        }

        $content = is_array($entry->content) ? $entry->content : [];

        // Detailed formatting of the cache operation
        $output = "Cache Operation Details:\n\n";
        $output .= "ID: {$entry->id}\n";
        $output .= "Operation: " . ($content['type'] ?? 'Unknown') . "\n";
        $output .= "Key: " . ($content['key'] ?? 'Unknown') . "\n";
        $output .= "Duration: " . number_format(($content['duration'] ?? 0), 2) . " ms\n";
        
        $createdAt = DateFormatter::format($entry->createdAt);
        $output .= "Created At: {$createdAt}\n\n";

        // Value (if available)
        if (isset($content['value'])) {
            $output .= "Value:\n";
            if (is_array($content['value']) || is_object($content['value'])) {
                $output .= json_encode($content['value'], JSON_PRETTY_PRINT) . "\n";
            } else {
                $output .= $content['value'] . "\n";
            }
        }

        return $this->formatResponse($output);
    }

    /**
     * Obtém os detalhes de uma entrada específica do Telescope
     *
     * @param string $entryType
     * @param string $id
     * @return mixed
     */
    protected function getEntryDetails($entryType, $id)
    {
        Logger::debug("Getting details for {$entryType} entry", ['id' => $id]);

        try {
            return $this->entriesRepository->find($id);
        } catch (\Exception $e) {
            Logger::error("Failed to get entry details", [
                'id' => $id,
                'entryType' => $entryType,
                'error' => $e->getMessage()
            ]);

            throw new \Exception("Entry not found: {$id}");
        }
    }
} 