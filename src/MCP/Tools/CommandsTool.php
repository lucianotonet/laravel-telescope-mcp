<?php

namespace LucianoTonet\TelescopeMcp\MCP\Tools;

use Laravel\Telescope\Contracts\EntriesRepository;
use LucianoTonet\TelescopeMcp\Support\Logger;
use LucianoTonet\TelescopeMcp\Support\DateFormatter;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\Storage\EntryQueryOptions;

/**
 * Tool for interacting with command executions recorded by Telescope
 */
class CommandsTool extends AbstractTool
{
    /**
     * @var EntriesRepository
     */
    protected $entriesRepository;

    /**
     * CommandsTool constructor
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
        return 'commands';
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
            'description' => 'Lists and analyzes command executions recorded by Telescope.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'id' => [
                        'type' => 'string',
                        'description' => 'ID of the specific command execution to view details'
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Maximum number of command executions to return',
                        'default' => 50
                    ],
                    'command' => [
                        'type' => 'string',
                        'description' => 'Filter by command name'
                    ],
                    'status' => [
                        'type' => 'string',
                        'description' => 'Filter by execution status (success, error)',
                        'enum' => ['success', 'error']
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
                    'description' => 'List last 10 command executions',
                    'params' => ['limit' => 10]
                ],
                [
                    'description' => 'Get details of a specific command execution',
                    'params' => ['id' => '12345']
                ],
                [
                    'description' => 'List failed command executions',
                    'params' => ['status' => 'error']
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
            // Check if details of a specific command execution were requested
            if ($this->hasId($params)) {
                return $this->getCommandDetails($params['id']);
            }

            return $this->listCommands($params);
        } catch (\Exception $e) {
            Logger::error($this->getName() . ' execution error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->formatError('Error: ' . $e->getMessage());
        }
    }

    /**
     * Lists command executions recorded by Telescope
     * 
     * @param array $params Query parameters
     * @return array Response in MCP format
     */
    protected function listCommands(array $params): array
    {
        // Set query limit
        $limit = isset($params['limit']) ? min((int)$params['limit'], 100) : 50;

        // Configure options
        $options = new EntryQueryOptions();
        $options->limit($limit);

        // Add filters if specified
        if (!empty($params['command'])) {
            $options->tag('command:' . $params['command']);
        }
        if (!empty($params['status'])) {
            $options->tag('status:' . $params['status']);
        }

        // Fetch entries using the repository
        $entries = $this->entriesRepository->get(EntryType::COMMAND, $options);

        if (empty($entries)) {
            return $this->formatResponse("No command executions found.");
        }

        $commands = [];

        foreach ($entries as $entry) {
            $content = is_array($entry->content) ? $entry->content : [];
            
            // Get timestamp from content
            $createdAt = isset($content['created_at']) ? DateFormatter::format($content['created_at']) : 'Unknown';
            
            $commands[] = [
                'id' => $entry->id,
                'command' => $content['command'] ?? 'Unknown',
                'exit_code' => $content['exit_code'] ?? 0,
                'arguments' => isset($content['arguments']) ? implode(' ', $content['arguments']) : '',
                'created_at' => $createdAt
            ];
        }

        // Tabular formatting for better readability
        $table = "Command Executions:\n\n";
        $table .= sprintf("%-5s %-20s %-40s %-10s %-20s\n", 
            "ID", "Command", "Arguments/Options", "Status", "Created At");
        $table .= str_repeat("-", 100) . "\n";

        foreach ($commands as $cmd) {
            // Combine args and opts, truncate if too long
            $params = trim($cmd['arguments']);
            $params = $this->safeString($params);
            if (strlen($params) > 40) {
                $params = substr($params, 0, 37) . "...";
            }

            // Format status with indicator
            $statusStr = $cmd['exit_code'] === 0 ? 'Success' : ($cmd['exit_code'] === null ? 'Unknown' : 'Error');
            if ($statusStr === 'Error') {
                $statusStr .= " [{$cmd['exit_code']}]";
            }

            $table .= sprintf(
                "%-5s %-20s %-40s %-10s %-20s\n",
                $cmd['id'],
                $cmd['command'],
                $params,
                $statusStr,
                $cmd['created_at']
            );
        }

        return $this->formatResponse($table);
    }

    /**
     * Gets details of a specific command execution
     * 
     * @param string $id The command execution ID
     * @return array Response in MCP format
     */
    protected function getCommandDetails(string $id): array
    {
        Logger::info($this->getName() . ' getting details', ['id' => $id]);

        // Fetch the specific entry
        $entry = $this->getEntryDetails(EntryType::COMMAND, $id);

        if (!$entry) {
            return $this->formatError("Command execution not found: {$id}");
        }

        $content = is_array($entry->content) ? $entry->content : [];
        
        // Get timestamp from content
        $createdAt = isset($content['created_at']) ? DateFormatter::format($content['created_at']) : 'Unknown';
        
        // Detailed formatting of the command
        $output = "Command Details:\n\n";
        $output .= "ID: {$entry->id}\n";
        $output .= "Command: " . ($content['command'] ?? 'Unknown') . "\n";
        $output .= "Exit Code: " . ($content['exit_code'] ?? 0) . "\n";
        $output .= "Created At: {$createdAt}\n\n";

        // Arguments
        if (!empty($content['arguments'])) {
            $output .= "Arguments:\n";
            foreach ($content['arguments'] as $arg) {
                $output .= "  - {$arg}\n";
            }
            $output .= "\n";
        }

        // Options
        if (!empty($content['options'])) {
            $output .= "Options:\n";
            foreach ($content['options'] as $key => $value) {
                if (is_bool($value)) {
                    $output .= $value ? "  --{$key}\n" : '';
                } else {
                    $output .= "  --{$key}=" . (is_array($value) ? implode(',', $value) : $value) . "\n";
                }
            }
            $output .= "\n";
        }

        // Output
        if (!empty($content['output'])) {
            $output .= "Output:\n" . $content['output'] . "\n";
        }

        return $this->formatResponse($output);
    }
} 