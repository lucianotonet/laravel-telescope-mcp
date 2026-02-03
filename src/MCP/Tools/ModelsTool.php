<?php

namespace LucianoTonet\TelescopeMcp\MCP\Tools;

use Laravel\Telescope\Contracts\EntriesRepository;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\Storage\EntryQueryOptions;
use LucianoTonet\TelescopeMcp\Support\Logger;
use LucianoTonet\TelescopeMcp\Support\DateFormatter;
use LucianoTonet\TelescopeMcp\MCP\Tools\Traits\BatchQuerySupport;

class ModelsTool extends AbstractTool
{
    use BatchQuerySupport;

    /**
     * Retorna o nome curto da ferramenta
     */
    public function getShortName(): string
    {
        return 'models';
    }

    /**
     * Retorna o esquema da ferramenta
     */
    public function getSchema(): array
    {
        return [
            'name' => $this->getName(),
            'description' => 'Lista e analisa operações de modelos Eloquent registradas pelo Telescope',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'id' => [
                        'type' => 'string',
                        'description' => 'ID da operação específica para ver detalhes'
                    ],
                    'request_id' => [
                        'type' => 'string',
                        'description' => 'Filter model operations by the request ID they belong to (uses batch_id grouping)'
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Número máximo de operações a retornar',
                        'default' => 50
                    ],
                    'action' => [
                        'type' => 'string',
                        'description' => 'Filtrar por tipo de ação (created, updated, deleted)',
                        'enum' => ['created', 'updated', 'deleted']
                    ],
                    'model' => [
                        'type' => 'string',
                        'description' => 'Filtrar por nome do modelo'
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
                    'description' => 'List last 10 model operations',
                    'params' => ['limit' => 10]
                ],
                [
                    'description' => 'List model operations for a specific request',
                    'params' => ['request_id' => 'abc123']
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

            // Verificar se foi solicitado detalhes de uma operação específica
            if ($this->hasId($params)) {
                return $this->getModelDetails($params['id']);
            }

            // Check if filtering by request_id
            if ($this->hasRequestId($params)) {
                return $this->listModelsForRequest($params['request_id'], $params);
            }

            return $this->listModelOperations($params);
        } catch (\Exception $e) {
            Logger::error($this->getName() . ' execution error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->formatError('Error: ' . $e->getMessage());
        }
    }

    /**
     * Lista as operações de modelos registradas pelo Telescope
     */
    protected function listModelOperations(array $params): array
    {
        // Definir limite para a consulta
        $limit = isset($params['limit']) ? min((int)$params['limit'], 100) : 50;

        // Configurar opções
        $options = new EntryQueryOptions();
        $options->limit($limit);

        // Adicionar filtros se especificados
        if (!empty($params['action'])) {
            $options->tag($params['action']);
        }
        if (!empty($params['model'])) {
            $options->tag($params['model']);
        }

        // Buscar entradas usando o repositório
        $entries = $this->entriesRepository->get(EntryType::MODEL, $options);

        if (empty($entries)) {
            return $this->formatResponse("Nenhuma operação de modelo encontrada.");
        }

        $operations = [];

        foreach ($entries as $entry) {
            $content = is_array($entry->content) ? $entry->content : [];

            // Get timestamp from content
            $createdAt = isset($content['created_at']) ? DateFormatter::format($content['created_at']) : 'Unknown';

            $operations[] = [
                'id' => $entry->id,
                'action' => $content['action'] ?? 'Unknown',
                'model' => $content['model'] ?? 'Unknown',
                'model_id' => $content['model_id'] ?? 'N/A',
                'created_at' => $createdAt
            ];
        }

        // Formatação tabular para facilitar a leitura
        $table = "Model Operations:\n\n";
        $table .= sprintf("%-5s %-8s %-40s %-10s %-20s\n", "ID", "Action", "Model", "Model ID", "Created At");
        $table .= str_repeat("-", 90) . "\n";

        foreach ($operations as $op) {
            // Truncar nome do modelo se muito longo
            $model = $op['model'];
            $model = $this->safeString($model);
            if (strlen($model) > 40) {
                $model = substr($model, 0, 37) . "...";
            }

            $table .= sprintf(
                "%-5s %-8s %-40s %-10s %-20s\n",
                $op['id'],
                $op['action'],
                $model,
                $op['model_id'],
                $op['created_at']
            );
        }

        $combinedText = $table . "\n\n--- JSON Data ---\n" . json_encode([
            'total' => count($operations),
            'operations' => $operations
        ], JSON_PRETTY_PRINT);

        return $this->formatResponse($combinedText);
    }

    /**
     * Lists model operations for a specific request using batch_id
     *
     * @param string $requestId The request ID
     * @param array $params Tool parameters
     * @return array Response in MCP format
     */
    protected function listModelsForRequest(string $requestId, array $params): array
    {
        Logger::info($this->getName() . ' listing models for request', ['request_id' => $requestId]);

        // Get the batch_id for this request
        $batchId = $this->getBatchIdForRequest($requestId);

        if (!$batchId) {
            return $this->formatError("Request not found or has no batch ID: {$requestId}");
        }

        $limit = isset($params['limit']) ? min((int)$params['limit'], 100) : 50;

        // Get model operations for this batch
        $entries = $this->getEntriesByBatchId($batchId, 'model', $limit);

        if (empty($entries)) {
            return $this->formatResponse("No model operations found for request: {$requestId}");
        }

        $operations = [];

        foreach ($entries as $entry) {
            $content = is_array($entry->content) ? $entry->content : [];
            $createdAt = isset($entry->createdAt) ? DateFormatter::format($entry->createdAt) : 'Unknown';

            $action = $content['action'] ?? 'Unknown';
            $model = $content['model'] ?? 'Unknown';

            // Filter by action if specified
            if (!empty($params['action']) && strtolower($action) !== strtolower($params['action'])) {
                continue;
            }

            $operations[] = [
                'id' => $entry->id,
                'action' => $action,
                'model' => $model,
                'model_id' => $content['model_id'] ?? 'N/A',
                'created_at' => $createdAt
            ];
        }

        // Formatação tabular com contexto do request
        $table = "Model Operations for Request: {$requestId}\n";
        $table .= "Batch ID: {$batchId}\n";
        $table .= "Total: " . count($operations) . " operations\n\n";
        $table .= sprintf("%-5s %-8s %-40s %-10s %-20s\n", "ID", "Action", "Model", "Model ID", "Created At");
        $table .= str_repeat("-", 90) . "\n";

        foreach ($operations as $op) {
            $model = $op['model'];
            $model = $this->safeString($model);
            if (strlen($model) > 40) {
                $model = substr($model, 0, 37) . "...";
            }

            $table .= sprintf(
                "%-5s %-8s %-40s %-10s %-20s\n",
                $op['id'],
                $op['action'],
                $model,
                $op['model_id'],
                $op['created_at']
            );
        }

        $combinedText = $table . "\n\n--- JSON Data ---\n" . json_encode([
            'request_id' => $requestId,
            'batch_id' => $batchId,
            'total' => count($operations),
            'operations' => $operations
        ], JSON_PRETTY_PRINT);

        return $this->formatResponse($combinedText);
    }

    /**
     * Obtém detalhes de uma operação de modelo específica
     */
    protected function getModelDetails(string $id): array
    {
        Logger::info($this->getName() . ' getting details', ['id' => $id]);

        // Buscar a entrada específica
        $entry = $this->getEntryDetails(EntryType::MODEL, $id);

        if (!$entry) {
            return $this->formatError("Operação não encontrada: {$id}");
        }

        $content = is_array($entry->content) ? $entry->content : [];

        // Get timestamp from content
        $createdAt = isset($content['created_at']) ? DateFormatter::format($content['created_at']) : 'Unknown';

        // Detailed formatting of the model operation
        $output = "Model Operation Details:\n\n";
        $output .= "ID: {$entry->id}\n";
        $output .= "Action: " . ($content['action'] ?? 'Unknown') . "\n";
        $output .= "Model: " . ($content['model'] ?? 'Unknown') . "\n";
        $output .= "Model ID: " . ($content['model_id'] ?? 'N/A') . "\n";
        $output .= "Created At: {$createdAt}\n\n";

        // Atributos antigos (para update/delete)
        if (isset($content['old']) && !empty($content['old'])) {
            $output .= "Old Attributes:\n" . json_encode($content['old'], JSON_PRETTY_PRINT) . "\n\n";
        }

        // Novos atributos (para create/update)
        if (isset($content['attributes']) && !empty($content['attributes'])) {
            $output .= "New Attributes:\n" . json_encode($content['attributes'], JSON_PRETTY_PRINT) . "\n";
        }

        // Changes (diferenças para update)
        if (isset($content['changes']) && !empty($content['changes'])) {
            $output .= "\nChanges:\n" . json_encode($content['changes'], JSON_PRETTY_PRINT) . "\n";
        }

        $combinedText = $output . "\n\n--- JSON Data ---\n" . json_encode([
            'id' => $entry->id,
            'action' => $content['action'] ?? 'Unknown',
            'model' => $content['model'] ?? 'Unknown',
            'model_id' => $content['model_id'] ?? 'N/A',
            'created_at' => $createdAt,
            'old' => $content['old'] ?? [],
            'attributes' => $content['attributes'] ?? [],
            'changes' => $content['changes'] ?? []
        ], JSON_PRETTY_PRINT);

        return $this->formatResponse($combinedText);
    }
}
