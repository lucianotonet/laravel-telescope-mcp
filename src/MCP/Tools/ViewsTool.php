<?php

namespace LucianoTonet\TelescopeMcp\MCP\Tools;

use Laravel\Telescope\Contracts\EntriesRepository;
use LucianoTonet\TelescopeMcp\Support\Logger;
use LucianoTonet\TelescopeMcp\Support\DateFormatter;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\Storage\EntryQueryOptions;

/**
 * Tool for interacting with view renderings recorded by Telescope
 */
class ViewsTool extends AbstractTool
{
    /**
     * @var EntriesRepository
     */
    protected $entriesRepository;

    /**
     * ViewsTool constructor
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
        return 'views';
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
            'description' => 'Lists and analyzes view renderings recorded by Telescope.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'id' => [
                        'type' => 'string',
                        'description' => 'ID of the specific view rendering to view details'
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Maximum number of view renderings to return',
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
                    'description' => 'List last 10 view renderings',
                    'params' => ['limit' => 10]
                ],
                [
                    'description' => 'Get details of a specific view rendering',
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
            // Check if details of a specific view rendering were requested
            if ($this->hasId($params)) {
                return $this->getViewDetails($params['id']);
            }

            return $this->listViews($params);
        } catch (\Exception $e) {
            Logger::error($this->getName() . ' execution error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->formatError('Error: ' . $e->getMessage());
        }
    }

    /**
     * Lists view renderings recorded by Telescope
     * 
     * @param array $params Query parameters
     * @return array Response in MCP format
     */
    protected function listViews(array $params): array
    {
        // Set query limit
        $limit = isset($params['limit']) ? min((int)$params['limit'], 100) : 50;

        // Configure options
        $options = new EntryQueryOptions();
        $options->limit($limit);

        // Fetch entries using the repository
        $entries = $this->entriesRepository->get(EntryType::VIEW, $options);

        if (empty($entries)) {
            return $this->formatResponse("No view renderings found.");
        }

        $views = [];

        foreach ($entries as $entry) {
            $content = is_array($entry->content) ? $entry->content : [];
            
            // Get timestamp from content
            $createdAt = isset($content['created_at']) ? DateFormatter::format($content['created_at']) : 'Unknown';
            
            $views[] = [
                'id' => $entry->id,
                'name' => $content['name'] ?? 'Unknown',
                'path' => $content['path'] ?? 'Unknown',
                'created_at' => $createdAt
            ];
        }

        // Tabular formatting for better readability
        $table = "View Renderings:\n\n";
        $table .= sprintf("%-5s %-30s %-50s %-20s\n", 
            "ID", "Name", "Path", "Created At");
        $table .= str_repeat("-", 120) . "\n";

        foreach ($views as $view) {
            // Truncate name and path if too long
            $name = $view['name'];
            if (strlen($name) > 30) {
                $name = substr($name, 0, 27) . "...";
            }

            $path = $view['path'];
            if (strlen($path) > 50) {
                $path = substr($path, 0, 47) . "...";
            }

            $table .= sprintf(
                "%-5s %-30s %-50s %-20s\n",
                $view['id'],
                $name,
                $path,
                $view['created_at']
            );
        }

        return $this->formatResponse($table);
    }

    /**
     * Gets details of a specific view rendering
     * 
     * @param string $id The view rendering ID
     * @return array Response in MCP format
     */
    protected function getViewDetails(string $id): array
    {
        Logger::info($this->getName() . ' getting details', ['id' => $id]);

        // Fetch the specific entry
        $entry = $this->getEntryDetails(EntryType::VIEW, $id);

        if (!$entry) {
            return $this->formatError("View rendering not found: {$id}");
        }

        $content = is_array($entry->content) ? $entry->content : [];
        
        // Get timestamp from content
        $createdAt = isset($content['created_at']) ? DateFormatter::format($content['created_at']) : 'Unknown';
        
        // Detailed formatting of the view
        $output = "View Details:\n\n";
        $output .= "ID: {$entry->id}\n";
        $output .= "Name: " . ($content['name'] ?? 'Unknown') . "\n";
        $output .= "Path: " . ($content['path'] ?? 'Unknown') . "\n";
        $output .= "Created At: {$createdAt}\n\n";

        // View data
        if (isset($content['data']) && is_array($content['data'])) {
            $output .= "View Data:\n";
            foreach ($content['data'] as $key => $value) {
                $output .= "- {$key}: " . json_encode($value, JSON_PRETTY_PRINT) . "\n";
            }
            $output .= "\n";
        }

        return $this->formatResponse($output);
    }
} 