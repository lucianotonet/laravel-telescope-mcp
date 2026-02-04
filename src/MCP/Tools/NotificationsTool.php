<?php

namespace LucianoTonet\TelescopeMcp\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

use Laravel\Telescope\Contracts\EntriesRepository;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\Storage\EntryQueryOptions;
use LucianoTonet\TelescopeMcp\MCP\Tools\Traits\BatchQuerySupport;
use LucianoTonet\TelescopeMcp\Support\DateFormatter;

/**
 * Tool for interacting with notifications recorded by Telescope
 */
class NotificationsTool extends Tool
{
    use BatchQuerySupport;

    protected string $name = 'notifications';
    protected string $title = 'Telescope Notifications';
    protected string $description = 'Lists and analyzes notifications recorded by Telescope.';

    public function handle(Request $request, EntriesRepository $repository): Response
    {
        try {
            if ($id = $request->get('id')) {
                $includeRelated = $request->boolean('include_related', true);
                return $this->getNotificationDetails($id, $includeRelated, $repository);
            }
            return $this->listNotifications($request, $repository);
        } catch (\Exception $e) {
            return Response::error('Error: ' . $e->getMessage());
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('ID of specific notification'),
            'limit' => $schema->integer()->default(50)->description('Max notifications'),
            'channel' => $schema->string()->description('Filter by channel (mail, database, etc.)'),
            'status' => $schema->string()->description('Filter by status (sent, failed)'),
            'include_related' => $schema->boolean()->default(true),
        ];
    }

    protected function listNotifications(Request $request, EntriesRepository $repository): Response
    {
        $limit = min($request->integer('limit', 50), 100);
        $options = new EntryQueryOptions();
        $options->limit($limit);

        if ($channel = $request->get('channel')) $options->tag('channel:' . $channel);
        if ($status = $request->get('status')) $options->tag('status:' . $status);

        $entries = $repository->get(EntryType::NOTIFICATION, $options);
        if (empty($entries)) return Response::text("No notifications found.");

        $notifications = [];
        foreach ($entries as $entry) {
            $content = is_array($entry->content) ? $entry->content : [];
            $notifications[] = [
                'id' => $entry->id,
                'channel' => $content['channel'] ?? 'Unknown',
                'notification' => $content['notification'] ?? 'Unknown',
                'notifiable' => $content['notifiable'] ?? 'Unknown',
                'created_at' => isset($content['created_at']) ? DateFormatter::format($content['created_at']) : 'Unknown'
            ];
        }

        $table = "Notifications:\n\n";
        $table .= sprintf("%-5s %-15s %-30s %-30s %-20s\n",
            "ID", "Channel", "Notifiable", "Notification", "Created At");
        $table .= str_repeat("-", 120) . "\n";

        foreach ($notifications as $notif) {
            $notifiable = strlen($notif['notifiable']) > 30 ? substr($notif['notifiable'], 0, 27) . "..." : $notif['notifiable'];
            $notification = strlen($notif['notification']) > 30 ? substr($notif['notification'], 0, 27) . "..." : $notif['notification'];

            $table .= sprintf("%-5s %-15s %-30s %-30s %-20s\n",
                $notif['id'], $notif['channel'], $notifiable, $notification, $notif['created_at']);
        }

        $table .= "\n\n--- JSON Data ---\n" . json_encode(['total' => count($notifications), 'notifications' => $notifications], JSON_PRETTY_PRINT);
        return Response::text($table);
    }

    protected function getNotificationDetails(string $id, bool $includeRelated, EntriesRepository $repository): Response
    {
        $entry = $repository->find($id);
        if (!$entry) return Response::error("Notification not found: {$id}");

        $content = is_array($entry->content) ? $entry->content : [];
        $createdAt = isset($content['created_at']) ? DateFormatter::format($content['created_at']) : 'Unknown';

        $output = "Notification Details:\n\n";
        $output .= "ID: {$entry->id}\n";
        $output .= "Channel: " . ($content['channel'] ?? 'Unknown') . "\n";
        $output .= "Notification: " . ($content['notification'] ?? 'Unknown') . "\n";
        $output .= "Notifiable: " . ($content['notifiable'] ?? 'Unknown') . "\n";
        $output .= "Created At: {$createdAt}\n";

        $relatedSummary = [];
        if ($includeRelated && isset($entry->batchId) && $entry->batchId) {
            $summary = $this->getBatchSummary($entry->batchId);
            $typeLabels = ['query' => 'Queries', 'log' => 'Logs', 'cache' => 'Cache Operations',
                'model' => 'Model Events', 'view' => 'Views', 'exception' => 'Exceptions',
                'event' => 'Events', 'job' => 'Jobs', 'mail' => 'Mails',
                'request' => 'Requests', 'redis' => 'Redis Operations'];

            $output .= "\n--- Related Entries ---\n";
            $hasRelated = false;
            foreach ($summary as $type => $count) {
                if ($type !== 'notification') {
                    $label = $typeLabels[$type] ?? ucfirst($type);
                    $output .= "- {$label}: {$count}\n";
                    $relatedSummary[$type] = $count;
                    $hasRelated = true;
                }
            }
            if (!$hasRelated) {
                $output .= "(No related entries found)\n";
            }
        }

        $output .= "\n";

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

        $jsonData = [
            'id' => $entry->id,
            'batch_id' => $entry->batchId ?? null,
            'channel' => $content['channel'] ?? 'Unknown',
            'notification' => $content['notification'] ?? 'Unknown',
            'notifiable' => $content['notifiable'] ?? 'Unknown',
            'created_at' => $createdAt,
        ];
        if (!empty($relatedSummary)) $jsonData['related_entries'] = $relatedSummary;

        $output .= "\n\n--- JSON Data ---\n" . json_encode($jsonData, JSON_PRETTY_PRINT);
        return Response::text($output);
    }
} 