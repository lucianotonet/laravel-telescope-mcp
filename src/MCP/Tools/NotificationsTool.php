<?php

namespace LucianoTonet\TelescopeMcp\MCP\Tools;

use Laravel\Telescope\Contracts\EntriesRepository;
use LucianoTonet\TelescopeMcp\Support\Logger;
use LucianoTonet\TelescopeMcp\Support\DateFormatter;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\Storage\EntryQueryOptions;

/**
 * Tool for interacting with notifications recorded by Telescope
 */
class NotificationsTool extends AbstractTool
{
    /**
     * @var EntriesRepository
     */
    protected $entriesRepository;

    /**
     * NotificationsTool constructor
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
        return 'notifications';
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
            'description' => 'Lists and analyzes notifications recorded by Telescope.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'id' => [
                        'type' => 'string',
                        'description' => 'ID of the specific notification to view details'
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Maximum number of notifications to return',
                        'default' => 50
                    ],
                    'channel' => [
                        'type' => 'string',
                        'description' => 'Filter by notification channel (mail, database, etc.)'
                    ],
                    'status' => [
                        'type' => 'string',
                        'description' => 'Filter by notification status (sent, failed)',
                        'enum' => ['sent', 'failed']
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
                    'description' => 'List last 10 notifications',
                    'params' => ['limit' => 10]
                ],
                [
                    'description' => 'Get details of a specific notification',
                    'params' => ['id' => '12345']
                ],
                [
                    'description' => 'List failed notifications',
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
            // Check if details of a specific notification were requested
            if ($this->hasId($params)) {
                return $this->getNotificationDetails($params['id']);
            }

            return $this->listNotifications($params);
        } catch (\Exception $e) {
            Logger::error($this->getName() . ' execution error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->formatError('Error: ' . $e->getMessage());
        }
    }

    /**
     * Lists notifications recorded by Telescope
     * 
     * @param array $params Query parameters
     * @return array Response in MCP format
     */
    protected function listNotifications(array $params): array
    {
        // Set query limit
        $limit = isset($params['limit']) ? min((int)$params['limit'], 100) : 50;

        // Configure options
        $options = new EntryQueryOptions();
        $options->limit($limit);

        // Add filters if specified
        if (!empty($params['channel'])) {
            $options->tag('channel:' . $params['channel']);
        }
        if (!empty($params['status'])) {
            $options->tag('status:' . $params['status']);
        }

        // Fetch entries using the repository
        $entries = $this->entriesRepository->get(EntryType::NOTIFICATION, $options);

        if (empty($entries)) {
            return $this->formatResponse("No notifications found.");
        }

        $notifications = [];

        foreach ($entries as $entry) {
            $content = is_array($entry->content) ? $entry->content : [];
            
            // Get timestamp from content
            $createdAt = isset($content['created_at']) ? DateFormatter::format($content['created_at']) : 'Unknown';
            
            $notifications[] = [
                'id' => $entry->id,
                'channel' => $content['channel'] ?? 'Unknown',
                'notification' => $content['notification'] ?? 'Unknown',
                'notifiable' => $content['notifiable'] ?? 'Unknown',
                'created_at' => $createdAt
            ];
        }

        // Tabular formatting for better readability
        $table = "Notifications:\n\n";
        $table .= sprintf("%-5s %-15s %-30s %-30s %-20s\n", 
            "ID", "Channel", "Notifiable", "Notification", "Created At");
        $table .= str_repeat("-", 120) . "\n";

        foreach ($notifications as $notif) {
            // Truncate fields if too long
            $notifiable = $notif['notifiable'];
            $notifiable = $this->safeString($notifiable);
            if (strlen($notifiable) > 30) {
                $notifiable = substr($notifiable, 0, 27) . "...";
            }

            $notification = $notif['notification'];
            $notification = $this->safeString($notification);
            if (strlen($notification) > 30) {
                $notification = substr($notification, 0, 27) . "...";
            }

            $table .= sprintf(
                "%-5s %-15s %-30s %-30s %-20s\n",
                $notif['id'],
                $notif['channel'],
                $notifiable,
                $notification,
                $notif['created_at']
            );
        }

        return $this->formatResponse($table);
    }

    /**
     * Gets details of a specific notification
     * 
     * @param string $id The notification ID
     * @return array Response in MCP format
     */
    protected function getNotificationDetails(string $id): array
    {
        Logger::info($this->getName() . ' getting details', ['id' => $id]);

        // Fetch the specific entry
        $entry = $this->getEntryDetails(EntryType::NOTIFICATION, $id);

        if (!$entry) {
            return $this->formatError("Notification not found: {$id}");
        }

        $content = is_array($entry->content) ? $entry->content : [];
        
        // Get timestamp from content
        $createdAt = isset($content['created_at']) ? DateFormatter::format($content['created_at']) : 'Unknown';
        
        // Detailed formatting of the notification
        $output = "Notification Details:\n\n";
        $output .= "ID: {$entry->id}\n";
        $output .= "Channel: " . ($content['channel'] ?? 'Unknown') . "\n";
        $output .= "Notification: " . ($content['notification'] ?? 'Unknown') . "\n";
        $output .= "Notifiable: " . ($content['notifiable'] ?? 'Unknown') . "\n";
        $output .= "Created At: {$createdAt}\n\n";

        // Response (if available)
        if (!empty($content['response'])) {
            $output .= "Response:\n" . json_encode($content['response'], JSON_PRETTY_PRINT) . "\n\n";
        }

        // Exception (if failed)
        if (!empty($content['exception'])) {
            $output .= "Exception:\n";
            $output .= "Message: " . ($content['exception']['message'] ?? 'Unknown') . "\n";
            if (!empty($content['exception']['trace'])) {
                $output .= "Stack Trace:\n" . implode("\n", array_slice($content['exception']['trace'], 0, 5)) . "\n";
                if (count($content['exception']['trace']) > 5) {
                    $output .= "... (truncated)\n";
                }
            }
            $output .= "\n";
        }

        // Data
        if (!empty($content['data'])) {
            $output .= "Data:\n" . json_encode($content['data'], JSON_PRETTY_PRINT) . "\n";
        }

        return $this->formatResponse($output);
    }
} 