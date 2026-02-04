<?php

namespace LucianoTonet\TelescopeMcp\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

use Laravel\Telescope\Contracts\EntriesRepository;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\Storage\EntryQueryOptions;
use LucianoTonet\TelescopeMcp\Support\DateFormatter;

/**
 * Tool for interacting with dump entries recorded by Telescope
 */
class DumpsTool extends Tool
{
    protected string $name = 'dumps';
    protected string $title = 'Telescope Dumps';
    protected string $description = 'Lists and analyzes dump entries recorded by Telescope.';

    public function handle(Request $request, EntriesRepository $repository): Response
    {
        try {
            if ($id = $request->get('id')) {
                return $this->getDumpDetails($id, $repository);
            }
            return $this->listDumps($request, $repository);
        } catch (\Exception $e) {
            return Response::error('Error: ' . $e->getMessage());
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('ID of specific dump entry'),
            'limit' => $schema->integer()->default(50)->description('Maximum number of dump entries to return'),
            'file' => $schema->string()->description('Filter by file path'),
            'line' => $schema->integer()->description('Filter by line number'),
        ];
    }

    protected function listDumps(Request $request, EntriesRepository $repository): Response
    {
        $limit = min($request->integer('limit', 50), 100);
        $options = new EntryQueryOptions();
        $options->limit($limit);

        if ($file = $request->get('file')) {
            $options->tag('file:' . $file);
        }
        if ($line = $request->get('line')) {
            $options->tag('line:' . $line);
        }

        $entries = $repository->get(EntryType::DUMP, $options);
        if (empty($entries)) {
            return Response::text("No dump entries found.");
        }

        $dumps = [];
        foreach ($entries as $entry) {
            $content = is_array($entry->content) ? $entry->content : [];
            $createdAt = DateFormatter::format($entry->createdAt);

            $file = $content['file'] ?? 'Unknown';
            $line = $content['line'] ?? 0;
            $dump = $content['dump'] ?? 'Empty dump';

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

        $table = "Dump Entries:\n\n";
        $table .= sprintf("%-5s %-40s %-6s %-50s %-20s\n",
            "ID", "File", "Line", "Content", "Created At");
        $table .= str_repeat("-", 125) . "\n";

        foreach ($dumps as $dump) {
            $file = $this->safeString($dump['file']);
            if (strlen($file) > 40) {
                $file = "..." . substr($file, -37);
            }

            $table .= sprintf("%-5s %-40s %-6s %-50s %-20s\n",
                $dump['id'], $file, $dump['line'], $dump['dump'], $dump['created_at']);
        }

        $table .= "\n\n--- JSON Data ---\n" . json_encode([
            'total' => count($dumps),
            'dumps' => $dumps
        ], JSON_PRETTY_PRINT);

        return Response::text($table);
    }

    protected function getDumpDetails(string $id, EntriesRepository $repository): Response
    {
        $entry = $repository->find($id);
        if (!$entry) {
            return Response::error("Dump entry not found: {$id}");
        }

        $content = is_array($entry->content) ? $entry->content : [];
        $createdAt = DateFormatter::format($entry->createdAt);

        $output = "Dump Entry Details:\n\n";
        $output .= "ID: {$entry->id}\n";
        $output .= "File: " . ($content['file'] ?? 'Unknown') . "\n";
        $output .= "Line: " . ($content['line'] ?? 'Unknown') . "\n";
        $output .= "Created At: {$createdAt}\n\n";

        $dump = $content['dump'] ?? null;
        if ($dump !== null) {
            $output .= "Content:\n";
            if (is_array($dump) || is_object($dump)) {
                $output .= json_encode($dump, JSON_PRETTY_PRINT) . "\n";
            } else {
                $output .= $dump . "\n";
            }
        }

        $output .= "\n\n--- JSON Data ---\n" . json_encode([
            'id' => $entry->id,
            'file' => $content['file'] ?? 'Unknown',
            'line' => $content['line'] ?? 'Unknown',
            'dump' => $content['dump'] ?? null,
            'created_at' => $createdAt
        ], JSON_PRETTY_PRINT);

        return Response::text($output);
    }

    protected function safeString($value): string
    {
        if (is_array($value)) {
            return json_encode($value, JSON_PRETTY_PRINT);
        } elseif (is_object($value)) {
            return json_encode($value, JSON_PRETTY_PRINT);
        } elseif (is_null($value)) {
            return '';
        } elseif (is_bool($value)) {
            return $value ? 'true' : 'false';
        } elseif (is_numeric($value)) {
            return (string) $value;
        } else {
            return (string) $value;
        }
    }
} 