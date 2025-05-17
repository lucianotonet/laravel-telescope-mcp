<?php

namespace LucianoTonet\TelescopeMcp\MCP\Tools;

use Laravel\Telescope\Contracts\EntriesRepository;
use LucianoTonet\TelescopeMcp\Support\Logger;
use LucianoTonet\TelescopeMcp\Support\DateFormatter;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\Storage\EntryQueryOptions;

/**
 * Tool for interacting with gate checks recorded by Telescope
 */
class GatesTool extends AbstractTool
{
    /**
     * @var EntriesRepository
     */
    protected $entriesRepository;

    /**
     * GatesTool constructor
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
        return 'gates';
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
            'description' => 'Lists and analyzes gate authorization checks recorded by Telescope.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'id' => [
                        'type' => 'string',
                        'description' => 'ID of the specific gate check to view details'
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Maximum number of gate checks to return',
                        'default' => 50
                    ],
                    'ability' => [
                        'type' => 'string',
                        'description' => 'Filter by gate ability name'
                    ],
                    'result' => [
                        'type' => 'string',
                        'description' => 'Filter by check result (allowed, denied)',
                        'enum' => ['allowed', 'denied']
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
                    'description' => 'List last 10 gate checks',
                    'params' => ['limit' => 10]
                ],
                [
                    'description' => 'Get details of a specific gate check',
                    'params' => ['id' => '12345']
                ],
                [
                    'description' => 'List denied gate checks',
                    'params' => ['result' => 'denied']
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
            // Check if details of a specific gate check were requested
            if ($this->hasId($params)) {
                return $this->getGateDetails($params['id']);
            }

            return $this->listGateChecks($params);
        } catch (\Exception $e) {
            Logger::error($this->getName() . ' execution error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->formatError('Error: ' . $e->getMessage());
        }
    }

    /**
     * Lists gate checks recorded by Telescope
     * 
     * @param array $params Query parameters
     * @return array Response in MCP format
     */
    protected function listGateChecks(array $params): array
    {
        // Set query limit
        $limit = isset($params['limit']) ? min((int)$params['limit'], 100) : 50;

        // Configure options
        $options = new EntryQueryOptions();
        $options->limit($limit);

        // Add filters if specified
        if (!empty($params['ability'])) {
            $options->tag('ability:' . $params['ability']);
        }
        if (!empty($params['result'])) {
            $options->tag('result:' . $params['result']);
        }

        // Fetch entries using the repository
        $entries = $this->entriesRepository->get(EntryType::GATE, $options);

        if (empty($entries)) {
            return $this->formatResponse("No gate checks found.");
        }

        $checks = [];

        foreach ($entries as $entry) {
            $content = is_array($entry->content) ? $entry->content : [];
            $createdAt = DateFormatter::format($entry->createdAt);

            // Extract relevant information from the gate check
            $ability = $content['ability'] ?? 'Unknown';
            $result = isset($content['result']) && $content['result'] ? 'Allowed' : 'Denied';
            $user = $content['user'] ?? 'Unknown';

            $checks[] = [
                'id' => $entry->id,
                'ability' => $ability,
                'result' => $result,
                'user' => $user,
                'created_at' => $createdAt
            ];
        }

        // Tabular formatting for better readability
        $table = "Gate Checks:\n\n";
        $table .= sprintf("%-5s %-30s %-10s %-30s %-20s\n", 
            "ID", "Ability", "Result", "User", "Created At");
        $table .= str_repeat("-", 100) . "\n";

        foreach ($checks as $check) {
            // Truncate fields if too long
            $ability = $check['ability'];
            if (strlen($ability) > 30) {
                $ability = substr($ability, 0, 27) . "...";
            }

            $user = $check['user'];
            if (strlen($user) > 30) {
                $user = substr($user, 0, 27) . "...";
            }

            // Format result with color indicator
            $resultStr = $check['result'];
            if ($resultStr === 'Denied') {
                $resultStr .= ' [!]';
            }

            $table .= sprintf(
                "%-5s %-30s %-10s %-30s %-20s\n",
                $check['id'],
                $ability,
                $resultStr,
                $user,
                $check['created_at']
            );
        }

        return $this->formatResponse($table);
    }

    /**
     * Gets details of a specific gate check
     * 
     * @param string $id The gate check ID
     * @return array Response in MCP format
     */
    protected function getGateDetails(string $id): array
    {
        Logger::info($this->getName() . ' getting details', ['id' => $id]);

        // Fetch the specific entry
        $entry = $this->getEntryDetails(EntryType::GATE, $id);

        if (!$entry) {
            return $this->formatError("Gate check not found: {$id}");
        }

        $content = is_array($entry->content) ? $entry->content : [];

        // Detailed formatting of the gate check
        $output = "Gate Check Details:\n\n";
        $output .= "ID: {$entry->id}\n";
        $output .= "Ability: " . ($content['ability'] ?? 'Unknown') . "\n";
        $output .= "Result: " . (isset($content['result']) && $content['result'] ? 'Allowed' : 'Denied') . "\n";
        $output .= "User: " . ($content['user'] ?? 'Unknown') . "\n";

        $createdAt = DateFormatter::format($entry->createdAt);
        $output .= "Created At: {$createdAt}\n\n";

        // Arguments
        if (!empty($content['arguments'])) {
            $output .= "Arguments:\n" . json_encode($content['arguments'], JSON_PRETTY_PRINT) . "\n\n";
        }

        // Additional context
        if (!empty($content['context'])) {
            $output .= "Context:\n" . json_encode($content['context'], JSON_PRETTY_PRINT) . "\n";
        }

        return $this->formatResponse($output);
    }
} 