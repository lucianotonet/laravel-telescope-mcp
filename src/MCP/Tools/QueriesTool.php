<?php

namespace LucianoTonet\TelescopeMcp\MCP\Tools;

use Laravel\Telescope\Contracts\EntriesRepository;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\Storage\EntryQueryOptions;
use LucianoTonet\TelescopeMcp\Support\Logger;
use LucianoTonet\TelescopeMcp\Support\DateFormatter;
use LucianoTonet\TelescopeMcp\MCP\Tools\Traits\BatchQuerySupport;

class QueriesTool extends AbstractTool
{
    use BatchQuerySupport;

    /**
     * Retorna o nome curto da ferramenta
     */
    public function getShortName(): string
    {
        return 'queries';
    }

    /**
     * Retorna o esquema da ferramenta
     */
    public function getSchema(): array
    {
        return [
            'name' => $this->getName(),
            'description' => 'Lista e analisa queries de banco de dados registradas pelo Telescope',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'id' => [
                        'type' => 'string',
                        'description' => 'ID da query específica para ver detalhes'
                    ],
                    'request_id' => [
                        'type' => 'string',
                        'description' => 'Filter queries by the request ID they belong to (uses batch_id grouping)'
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Número máximo de queries a retornar',
                        'default' => 50
                    ],
                    'slow' => [
                        'type' => 'boolean',
                        'description' => 'Filtrar apenas queries lentas (>100ms)',
                        'default' => false
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
                                'type' => [
                                    'type' => 'string',
                                    'enum' => ['text', 'json', 'markdown', 'html']
                                ],
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
                    'description' => 'List all queries',
                    'params' => ['limit' => 10]
                ],
                [
                    'description' => 'List queries for a specific request',
                    'params' => ['request_id' => 'abc123']
                ],
                [
                    'description' => 'List slow queries for a request',
                    'params' => ['request_id' => 'abc123', 'slow' => true]
                ]
            ]
        ];
    }

    /**
     * Executa a ferramenta com os parâmetros fornecidos
     */
    public function execute(array $params): array
    {
        try {
            Logger::info($this->getName() . ' execute method called', ['params' => $params]);

            // Verificar se foi solicitado detalhes de uma query específica
            if ($this->hasId($params)) {
                return $this->getQueryDetails($params['id']);
            }

            // Check if filtering by request_id
            if ($this->hasRequestId($params)) {
                return $this->listQueriesForRequest($params['request_id'], $params);
            }

            return $this->listQueries($params);
        } catch (\Exception $e) {
            Logger::error($this->getName() . ' execution error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->formatError('Error: ' . $e->getMessage());
        }
    }

    /**
     * Lista as queries registradas pelo Telescope
     */
    protected function listQueries(array $params): array
    {
        // Definir limite para a consulta
        $limit = isset($params['limit']) ? min((int)$params['limit'], 100) : 50;

        // Configurar opções
        $options = new EntryQueryOptions();
        $options->limit($limit);

        // Buscar entradas usando o repositório
        $entries = $this->entriesRepository->get(EntryType::QUERY, $options);

        if (empty($entries)) {
            return $this->formatResponse("Nenhuma query encontrada.");
        }

        $queries = [];

        foreach ($entries as $entry) {
            $content = is_array($entry->content) ? $entry->content : [];

            // Get timestamp from content
            $createdAt = isset($content['created_at']) ? DateFormatter::format($content['created_at']) : 'Unknown';

            $duration = $content['duration'] ?? 0;
            $slow = $duration > 100; // Queries taking more than 100ms are considered slow

            // Skip if we're only looking for slow queries and this one isn't slow
            if ($params['slow'] ?? false && !$slow) {
                continue;
            }

            $queries[] = [
                'id' => $entry->id,
                'sql' => $content['sql'] ?? 'Unknown',
                'duration' => $duration,
                'connection' => $content['connection'] ?? 'default',
                'created_at' => $createdAt
            ];
        }

        // Formatação tabular para facilitar a leitura
        $table = "Database Queries:\n\n";
        $table .= sprintf("%-5s %-50s %-10s %-15s %-20s\n", "ID", "SQL", "Time (ms)", "Connection", "Created At");
        $table .= str_repeat("-", 105) . "\n";

        foreach ($queries as $query) {
            // Truncar SQL longa
            $sql = $query['sql'];
            $sql = $this->safeString($sql);
            if (strlen($sql) > 50) {
                $sql = substr($sql, 0, 47) . "...";
            }

            $table .= sprintf(
                "%-5s %-50s %-10s %-15s %-20s\n",
                $query['id'],
                $sql,
                number_format($query['duration'], 2),
                $query['connection'],
                $query['created_at']
            );
        }

        $combinedText = $table . "\n\n--- JSON Data ---\n" . json_encode([
            'total' => count($queries),
            'queries' => $queries
        ], JSON_PRETTY_PRINT);

        return $this->formatResponse($combinedText);
    }

    /**
     * Lista queries para um request específico usando batch_id
     */
    protected function listQueriesForRequest(string $requestId, array $params): array
    {
        Logger::info($this->getName() . ' listing queries for request', ['request_id' => $requestId]);

        // Get the batch_id for this request
        $batchId = $this->getBatchIdForRequest($requestId);

        if (!$batchId) {
            return $this->formatError("Request not found or has no batch ID: {$requestId}");
        }

        $limit = isset($params['limit']) ? min((int)$params['limit'], 100) : 50;

        // Get queries for this batch
        $entries = $this->getEntriesByBatchId($batchId, 'query', $limit);

        if (empty($entries)) {
            return $this->formatResponse("No queries found for request: {$requestId}");
        }

        $queries = [];

        foreach ($entries as $entry) {
            $content = is_array($entry->content) ? $entry->content : [];

            $createdAt = isset($entry->createdAt) ? DateFormatter::format($entry->createdAt) : 'Unknown';

            $duration = $content['duration'] ?? $content['time'] ?? 0;
            $slow = $duration > 100;

            // Skip if we're only looking for slow queries and this one isn't slow
            if (($params['slow'] ?? false) && !$slow) {
                continue;
            }

            $queries[] = [
                'id' => $entry->id,
                'sql' => $content['sql'] ?? 'Unknown',
                'duration' => $duration,
                'connection' => $content['connection'] ?? 'default',
                'created_at' => $createdAt
            ];
        }

        // Formatação tabular com contexto do request
        $table = "Queries for Request: {$requestId}\n";
        $table .= "Batch ID: {$batchId}\n";
        $table .= "Total: " . count($queries) . " queries\n\n";
        $table .= sprintf("%-5s %-50s %-10s %-15s %-20s\n", "ID", "SQL", "Time (ms)", "Connection", "Created At");
        $table .= str_repeat("-", 105) . "\n";

        foreach ($queries as $query) {
            $sql = $query['sql'];
            $sql = $this->safeString($sql);
            if (strlen($sql) > 50) {
                $sql = substr($sql, 0, 47) . "...";
            }

            $table .= sprintf(
                "%-5s %-50s %-10s %-15s %-20s\n",
                $query['id'],
                $sql,
                number_format($query['duration'], 2),
                $query['connection'],
                $query['created_at']
            );
        }

        $combinedText = $table . "\n\n--- JSON Data ---\n" . json_encode([
            'request_id' => $requestId,
            'batch_id' => $batchId,
            'total' => count($queries),
            'queries' => $queries
        ], JSON_PRETTY_PRINT);

        return $this->formatResponse($combinedText);
    }

    /**
     * Obtém detalhes de uma query específica
     */
    protected function getQueryDetails(string $id): array
    {
        Logger::info($this->getName() . ' getting details', ['id' => $id]);

        // Buscar a entrada específica
        $entry = $this->getEntryDetails(EntryType::QUERY, $id);

        if (!$entry) {
            return $this->formatError("Query não encontrada: {$id}");
        }

        $content = is_array($entry->content) ? $entry->content : [];

        // Get timestamp from content
        $createdAt = isset($content['created_at']) ? DateFormatter::format($content['created_at']) : 'Unknown';

        // Detailed formatting of the query
        $output = "Database Query Details:\n\n";
        $output .= "ID: {$entry->id}\n";
        $output .= "Connection: " . ($content['connection'] ?? 'default') . "\n";
        $output .= "Duration: " . number_format(($content['time'] ?? 0), 2) . "ms\n";
        $output .= "Created At: {$createdAt}\n\n";

        // SQL completo
        $output .= "SQL:\n" . ($content['sql'] ?? 'Unknown') . "\n\n";

        // Bindings se disponíveis
        if (isset($content['bindings']) && !empty($content['bindings'])) {
            $output .= "Bindings:\n" . json_encode($content['bindings'], JSON_PRETTY_PRINT) . "\n";
        }

        $combinedText = $output . "\n\n--- JSON Data ---\n" . json_encode([
            'id' => $entry->id,
            'connection' => $content['connection'] ?? 'default',
            'duration' => $content['time'] ?? 0,
            'created_at' => $createdAt,
            'sql' => $content['sql'] ?? 'Unknown',
            'bindings' => $content['bindings'] ?? []
        ], JSON_PRETTY_PRINT);

        return $this->formatResponse($combinedText);
    }
}
