<?php

namespace LucianoTonet\TelescopeMcp\MCP\Tools;

use Laravel\Telescope\Contracts\EntriesRepository;
use LucianoTonet\TelescopeMcp\Support\Logger;
use LucianoTonet\TelescopeMcp\Support\DateFormatter;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\Storage\EntryQueryOptions;

/**
 * Tool for interacting with HTTP requests recorded by Telescope
 */
class RequestsTool extends AbstractTool
{
    /**
     * Returns the tool's short name
     * 
     * @return string
     */
    public function getShortName(): string
    {
        return 'requests';
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
            'description' => 'Lists and analyzes HTTP requests recorded by Telescope.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'id' => [
                        'type' => 'string',
                        'description' => 'ID of the specific request to view details'
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Maximum number of requests to return',
                        'default' => 50
                    ],
                    'method' => [
                        'type' => 'string',
                        'description' => 'Filter by HTTP method (GET, POST, etc.)'
                    ],
                    'status' => [
                        'type' => 'integer',
                        'description' => 'Filter by HTTP status code'
                    ],
                    'path' => [
                        'type' => 'string',
                        'description' => 'Filter by request path'
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
                    'description' => 'List last 10 requests',
                    'params' => ['limit' => 10]
                ],
                [
                    'description' => 'Get details of a specific request',
                    'params' => ['id' => '12345']
                ],
                [
                    'description' => 'List failed requests',
                    'params' => ['status' => 500]
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
            // Check if details of a specific request were requested
            if ($this->hasId($params)) {
                return $this->getRequestDetails($params['id']);
            }

            return $this->listRequests($params);
        } catch (\Exception $e) {
            Logger::error($this->getName() . ' execution error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->formatError('Error: ' . $e->getMessage());
        }
    }

    /**
     * Lists HTTP requests recorded by Telescope
     * 
     * @param array $params Query parameters
     * @return array Response in MCP format
     */
    protected function listRequests(array $params): array
    {
        // Set query limit
        $limit = isset($params['limit']) ? min((int)$params['limit'], 100) : 50;

        // Configure options
        $options = new EntryQueryOptions();
        $options->limit($limit);

        // Add filters if specified
        if (!empty($params['method'])) {
            $options->tag('method:' . strtoupper($params['method']));
        }
        if (!empty($params['status'])) {
            $options->tag('status:' . $params['status']);
        }
        if (!empty($params['path'])) {
            $options->tag('path:' . $params['path']);
        }

        // Fetch entries using the repository
        $entries = $this->entriesRepository->get(EntryType::REQUEST, $options);

        if (empty($entries)) {
            return $this->formatResponse("No requests found.");
        }

        $requests = [];

        foreach ($entries as $entry) {
            $content = is_array($entry->content) ? $entry->content : [];
            
            // Get timestamp from content
            $createdAt = isset($content['created_at']) ? DateFormatter::format($content['created_at']) : 'Unknown';

            // Extract relevant information from the request
            $method = $content['method'] ?? 'Unknown';
            $uri = $content['uri'] ?? 'Unknown';
            $status = $content['response_status'] ?? 0;
            $duration = $content['duration'] ?? 0;

            $requests[] = [
                'id' => $entry->id,
                'method' => $method,
                'uri' => $uri,
                'status' => $status,
                'duration' => $duration,
                'created_at' => $createdAt
            ];
        }

        // Tabular formatting for better readability
        $table = "HTTP Requests:\n\n";
        $table .= sprintf("%-5s %-7s %-50s %-7s %-10s %-20s\n", 
            "ID", "Method", "URI", "Status", "Time (ms)", "Created At");
        $table .= str_repeat("-", 120) . "\n";

        foreach ($requests as $request) {
            // Truncate URI if too long
            $uri = $request['uri'];
            if (strlen($uri) > 50) {
                $uri = substr($uri, 0, 47) . "...";
            }

            // Format status code with color indicators
            $status = $request['status'];
            $statusStr = sprintf("%d", $status);
            if ($status >= 500) {
                $statusStr .= " [E]"; // Error
            } elseif ($status >= 400) {
                $statusStr .= " [W]"; // Warning
            }

            $table .= sprintf(
                "%-5s %-7s %-50s %-7s %-10s %-20s\n",
                $request['id'],
                $request['method'],
                $uri,
                $statusStr,
                number_format($request['duration'], 2),
                $request['created_at']
            );
        }

        return $this->formatResponse($table);
    }

    /**
     * Gets details of a specific HTTP request
     * 
     * @param string $id The request ID
     * @return array Response in MCP format
     */
    protected function getRequestDetails(string $id): array
    {
        Logger::info($this->getName() . ' getting details', ['id' => $id]);

        // Fetch the specific entry
        $entry = $this->getEntryDetails(EntryType::REQUEST, $id);

        if (!$entry) {
            return $this->formatError("Request not found: {$id}");
        }

        $content = is_array($entry->content) ? $entry->content : [];

        // Get timestamp from content
        $createdAt = isset($content['created_at']) ? DateFormatter::format($content['created_at']) : 'Unknown';

        // Detailed formatting of the request
        $output = "HTTP Request Details:\n\n";
        $output .= "ID: {$entry->id}\n";
        $output .= "Method: " . ($content['method'] ?? 'Unknown') . "\n";
        $output .= "URI: " . ($content['uri'] ?? 'Unknown') . "\n";
        $output .= "Status: " . ($content['response_status'] ?? 'Unknown') . "\n";
        $output .= "Duration: " . number_format(($content['duration'] ?? 0), 2) . " ms\n";
        $output .= "Created At: {$createdAt}\n\n";

        // Request headers
        if (!empty($content['headers'])) {
            $output .= "Request Headers:\n";
            foreach ($content['headers'] as $name => $values) {
                $output .= "- {$name}: " . implode(", ", (array)$values) . "\n";
            }
            $output .= "\n";
        }

        // Request payload
        if (!empty($content['payload'])) {
            $output .= "Request Payload:\n" . json_encode($content['payload'], JSON_PRETTY_PRINT) . "\n\n";
        }

        // Response headers
        if (!empty($content['response_headers'])) {
            $output .= "Response Headers:\n";
            foreach ($content['response_headers'] as $name => $values) {
                $output .= "- {$name}: " . implode(", ", (array)$values) . "\n";
            }
            $output .= "\n";
        }

        // Response content (if available and not too large)
        if (!empty($content['response'])) {
            $response = $content['response'];
            if (strlen($response) > 1000) {
                $response = substr($response, 0, 1000) . "\n... (response truncated)";
            }
            $output .= "Response Content:\n" . $response . "\n";
        }

        return $this->formatResponse($output);
    }
} 