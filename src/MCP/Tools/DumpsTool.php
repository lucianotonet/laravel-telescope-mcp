<?php

namespace LucianoTonet\TelescopeMcp\MCP\Tools;

use Laravel\Telescope\Contracts\EntriesRepository;
use LucianoTonet\TelescopeMcp\Support\Logger;
use LucianoTonet\TelescopeMcp\Support\DateFormatter;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\Storage\EntryQueryOptions;

/**
 * Tool for interacting with dump entries recorded by Telescope
 */
class DumpsTool extends AbstractTool
{
    /**
     * @var EntriesRepository
     */
    protected $entriesRepository;

    /**
     * DumpsTool constructor
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
        return 'dumps';
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
            'description' => 'Lists and analyzes dump entries recorded by Telescope.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'id' => [
                        'type' => 'string',
                        'description' => 'ID of the specific dump entry to view details'
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Maximum number of dump entries to return',
                        'default' => 50
                    ],
                    'file' => [
                        'type' => 'string',
                        'description' => 'Filter by file path'
                    ],
                    'line' => [
                        'type' => 'integer',
                        'description' => 'Filter by line number'
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
                    'description' => 'List last 10 dump entries',
                    'params' => ['limit' => 10]
                ],
                [
                    'description' => 'Get details of a specific dump entry',
                    'params' => ['id' => '12345']
                ],
                [
                    'description' => 'List dumps from a specific file',
                    'params' => ['file' => 'app/Http/Controllers/HomeController.php']
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
            // Check if details of a specific dump entry were requested
            if ($this->hasId($params)) {
                return $this->getDumpDetails($params['id']);
            }

            return $this->listDumps($params);
        } catch (\Exception $e) {
            Logger::error($this->getName() . ' execution error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->formatError('Error: ' . $e->getMessage());
        }
    }

    /**
     * Lists dump entries recorded by Telescope
     * 
     * @param array $params Query parameters
     * @return array Response in MCP format
     */
    protected function listDumps(array $params): array
    {
        // Set query limit
        $limit = isset($params['limit']) ? min((int)$params['limit'], 100) : 50;

        // Configure options
        $options = new EntryQueryOptions();
        $options->limit($limit);

        // Add filters if specified
        if (!empty($params['file'])) {
            $options->tag('file:' . $params['file']);
        }
        if (!empty($params['line'])) {
            $options->tag('line:' . $params['line']);
        }

        // Fetch entries using the repository
        $entries = $this->entriesRepository->get(EntryType::DUMP, $options);

        if (empty($entries)) {
            return $this->formatResponse("No dump entries found.");
        }

        $dumps = [];

        foreach ($entries as $entry) {
            $content = is_array($entry->content) ? $entry->content : [];
            $createdAt = DateFormatter::format($entry->createdAt);

            // Extract relevant information from the dump entry
            $file = $content['file'] ?? 'Unknown';
            $line = $content['line'] ?? 0;
            $dump = $content['dump'] ?? 'Empty dump';

            // Format dump content for display
            if (is_array($dump) || is_object($dump)) {
                $dump = json_encode($dump);
            }
            $dump = $this->safeString($dump);
            if (strlen($dump) > 50) {
                $dump = substr($dump, 0, 47) . "...";
            }

            $dumps[] = [
                'id' => $entry->id,
                'file' => $file,
                'line' => $line,
                'dump' => $dump,
                'created_at' => $createdAt
            ];
        }

        // Tabular formatting for better readability
        $table = "Dump Entries:\n\n";
        $table .= sprintf("%-5s %-40s %-6s %-50s %-20s\n", 
            "ID", "File", "Line", "Content", "Created At");
        $table .= str_repeat("-", 125) . "\n";

        foreach ($dumps as $dump) {
            // Truncate file path if too long
            $file = $dump['file'];
            $file = $this->safeString($file);
            if (strlen($file) > 40) {
                $file = "..." . substr($file, -37);
            }

            $table .= sprintf(
                "%-5s %-40s %-6s %-50s %-20s\n",
                $dump['id'],
                $file,
                $dump['line'],
                $dump['dump'],
                $dump['created_at']
            );
        }

        return $this->formatResponse($table);
    }

    /**
     * Gets details of a specific dump entry
     * 
     * @param string $id The dump entry ID
     * @return array Response in MCP format
     */
    protected function getDumpDetails(string $id): array
    {
        Logger::info($this->getName() . ' getting details', ['id' => $id]);

        // Fetch the specific entry
        $entry = $this->getEntryDetails(EntryType::DUMP, $id);

        if (!$entry) {
            return $this->formatError("Dump entry not found: {$id}");
        }

        $content = is_array($entry->content) ? $entry->content : [];

        // Detailed formatting of the dump entry
        $output = "Dump Entry Details:\n\n";
        $output .= "ID: {$entry->id}\n";
        $output .= "File: " . ($content['file'] ?? 'Unknown') . "\n";
        $output .= "Line: " . ($content['line'] ?? 'Unknown') . "\n";

        $createdAt = DateFormatter::format($entry->createdAt);
        $output .= "Created At: {$createdAt}\n\n";

        // Dump content
        $dump = $content['dump'] ?? null;
        if ($dump !== null) {
            $output .= "Content:\n";
            if (is_array($dump) || is_object($dump)) {
                $output .= json_encode($dump, JSON_PRETTY_PRINT) . "\n";
            } else {
                $output .= $dump . "\n";
            }
        }

        return $this->formatResponse($output);
    }
} 