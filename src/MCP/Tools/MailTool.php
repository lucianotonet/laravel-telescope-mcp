<?php

namespace LucianoTonet\TelescopeMcp\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Contracts\IsReadOnly;
use Laravel\Telescope\Contracts\EntriesRepository;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\Storage\EntryQueryOptions;
use LucianoTonet\TelescopeMcp\MCP\Tools\Traits\BatchQuerySupport;
use LucianoTonet\TelescopeMcp\Support\DateFormatter;

/**
 * Tool for interacting with emails recorded by Telescope
 */
class MailTool extends Tool implements IsReadOnly
{
    use BatchQuerySupport;

    protected string $name = 'mail';
    protected string $title = 'Telescope Mail';
    protected string $description = 'Lists and analyzes emails sent recorded by Telescope.';

    public function handle(Request $request, EntriesRepository $repository): Response
    {
        try {
            if ($id = $request->get('id')) {
                $includeRelated = $request->boolean('include_related', true);
                return $this->getMailDetails($id, $includeRelated, $repository);
            }
            return $this->listMails($request, $repository);
        } catch (\Exception $e) {
            return Response::error('Error: ' . $e->getMessage());
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('ID of specific email'),
            'limit' => $schema->integer()->default(50)->description('Max emails'),
            'to' => $schema->string()->description('Filter by recipient'),
            'subject' => $schema->string()->description('Filter by subject'),
            'include_related' => $schema->boolean()->default(true),
        ];
    }

    protected function listMails(Request $request, EntriesRepository $repository): Response
    {
        $limit = min($request->integer('limit', 50), 100);
        $options = new EntryQueryOptions();
        $options->limit($limit);

        if ($to = $request->get('to')) $options->tag($to);
        if ($subject = $request->get('subject')) $options->tag($subject);

        $entries = $repository->get(EntryType::MAIL, $options);
        if (empty($entries)) return Response::text("No emails found.");

        $mails = [];
        foreach ($entries as $entry) {
            $content = is_array($entry->content) ? $entry->content : [];

            // Format recipients
            $to = [];
            if (isset($content['to']) && is_array($content['to'])) {
                foreach ($content['to'] as $recipient) {
                    if (is_array($recipient)) {
                        $to[] = $recipient['address'] ?? '';
                    } else {
                        $to[] = $recipient;
                    }
                }
            }

            $mails[] = [
                'id' => $entry->id,
                'subject' => $content['subject'] ?? 'No Subject',
                'to' => implode(', ', $to),
                'created_at' => isset($content['created_at']) ? DateFormatter::format($content['created_at']) : 'Unknown'
            ];
        }

        $table = "Emails:\n\n";
        $table .= sprintf("%-5s %-40s %-40s %-20s\n", "ID", "Subject", "To", "Created At");
        $table .= str_repeat("-", 110) . "\n";

        foreach ($mails as $mail) {
            $subject = strlen($mail['subject']) > 40 ? substr($mail['subject'], 0, 37) . "..." : $mail['subject'];
            $to = strlen($mail['to']) > 40 ? substr($mail['to'], 0, 37) . "..." : $mail['to'];

            $table .= sprintf("%-5s %-40s %-40s %-20s\n",
                $mail['id'], $subject, $to, $mail['created_at']);
        }

        $table .= "\n\n--- JSON Data ---\n" . json_encode(['total' => count($mails), 'emails' => $mails], JSON_PRETTY_PRINT);
        return Response::text($table);
    }

    protected function getMailDetails(string $id, bool $includeRelated, EntriesRepository $repository): Response
    {
        $entry = $repository->find($id);
        if (!$entry) return Response::error("Email not found: {$id}");

        $content = is_array($entry->content) ? $entry->content : [];
        $createdAt = isset($content['created_at']) ? DateFormatter::format($content['created_at']) : 'Unknown';

        $output = "Email Details:\n\n";
        $output .= "ID: {$entry->id}\nSubject: " . ($content['subject'] ?? 'No Subject') . "\n";

        // Recipients
        if (isset($content['to']) && is_array($content['to'])) {
            $output .= "To:\n";
            foreach ($content['to'] as $recipient) {
                if (is_array($recipient)) {
                    $output .= "- " . ($recipient['address'] ?? '') .
                             (isset($recipient['name']) ? " ({$recipient['name']})" : '') . "\n";
                } else {
                    $output .= "- " . $recipient . "\n";
                }
            }
        }

        // CC
        if (isset($content['cc']) && !empty($content['cc'])) {
            $output .= "\nCC:\n";
            foreach ($content['cc'] as $recipient) {
                if (is_array($recipient)) {
                    $output .= "- " . ($recipient['address'] ?? '') .
                             (isset($recipient['name']) ? " ({$recipient['name']})" : '') . "\n";
                } else {
                    $output .= "- " . $recipient . "\n";
                }
            }
        }

        // BCC
        if (isset($content['bcc']) && !empty($content['bcc'])) {
            $output .= "\nBCC:\n";
            foreach ($content['bcc'] as $recipient) {
                if (is_array($recipient)) {
                    $output .= "- " . ($recipient['address'] ?? '') .
                             (isset($recipient['name']) ? " ({$recipient['name']})" : '') . "\n";
                } else {
                    $output .= "- " . $recipient . "\n";
                }
            }
        }

        $output .= "Created At: {$createdAt}\n";

        $relatedSummary = [];
        if ($includeRelated && isset($entry->batchId) && $entry->batchId) {
            $summary = $this->getBatchSummary($entry->batchId);
            $typeLabels = ['query' => 'Queries', 'log' => 'Logs', 'cache' => 'Cache Operations',
                'model' => 'Model Events', 'view' => 'Views', 'exception' => 'Exceptions',
                'event' => 'Events', 'job' => 'Jobs', 'request' => 'Requests',
                'notification' => 'Notifications', 'redis' => 'Redis Operations'];

            $output .= "\n--- Related Entries ---\n";
            $hasRelated = false;
            foreach ($summary as $type => $count) {
                if ($type !== 'mail') {
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

        // Email content
        if (isset($content['html'])) {
            $output .= "HTML Content:\n" . $content['html'] . "\n\n";
        }
        if (isset($content['text'])) {
            $output .= "Text Content:\n" . $content['text'] . "\n";
        }

        // Attachments
        if (isset($content['attachments']) && !empty($content['attachments'])) {
            $output .= "\nAttachments:\n";
            foreach ($content['attachments'] as $attachment) {
                $output .= "- " . ($attachment['file'] ?? 'Unknown file') . "\n";
            }
        }

        $jsonData = [
            'id' => $entry->id,
            'batch_id' => $entry->batchId ?? null,
            'subject' => $content['subject'] ?? 'No Subject',
            'created_at' => $createdAt,
        ];
        if (!empty($relatedSummary)) $jsonData['related_entries'] = $relatedSummary;

        $output .= "\n\n--- JSON Data ---\n" . json_encode($jsonData, JSON_PRETTY_PRINT);
        return Response::text($output);
    }
} 