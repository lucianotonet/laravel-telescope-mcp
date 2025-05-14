<?php

namespace LucianoTonet\TelescopeMcp\MCP\Tools;

use Laravel\Telescope\Contracts\EntriesRepository;
use LucianoTonet\TelescopeMcp\Support\Logger;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\Storage\EntryQueryOptions;

class BatchesTool extends AbstractTool
{
    /**
     * Retorna o nome curto da ferramenta
     */
    public function getShortName()
    {
        return 'batches';
    }

    /**
     * Retorna o esquema da ferramenta
     */
    public function getSchema()
    {
        return [
            'name' => $this->getName(),
            'description' => 'Lista e analisa batches (lotes de jobs) registrados pelo Telescope.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'id' => [
                        'type' => 'string',
                        'description' => 'ID do batch específico para ver detalhes'
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Número máximo de batches a retornar',
                        'default' => 50
                    ],
                    // TODO: Adicionar filtros específicos para batches se necessário (ex: status, total jobs)
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
                // Usage examples will be added in the future
            ]
        ];
    }

    public function execute($params)
    {
        Logger::info($this->getName() . ' execute method called', ['params' => $params]);

        try {
            // Verificar se foi solicitado detalhes de um batch específico
            if ($this->hasId($params)) {
                return $this->getBatchDetails($params['id']);
            }

            return $this->listBatches($params);
        } catch (\Exception $e) {
            Logger::error($this->getName() . ' execution error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->formatError('Error: ' . $e->getMessage());
        }
    }

    /**
     * Lista os batches registrados pelo Telescope
     */
    protected function listBatches($params)
    {
        // Definir limite para a consulta
        $limit = isset($params['limit']) ? min((int)$params['limit'], 100) : 50;

        // Configurar opções
        $options = new EntryQueryOptions();
        $options->limit($limit);

        // TODO: Adicionar filtros específicos para batches se necessário

        // Buscar entradas usando o repositório
        $entries = $this->entriesRepository->get(EntryType::BATCH, $options);

        if (empty($entries)) {
            return $this->formatResponse("Nenhum batch encontrado.");
        }

        $batches = [];

        foreach ($entries as $entry) {
            $content = is_array($entry->content) ? $entry->content : [];

            $createdAt = 'Unknown';
            if (property_exists($entry, 'created_at') && !empty($entry->created_at)) {
                if (is_object($entry->created_at) && method_exists($entry->created_at, 'format')) {
                    $createdAt = $entry->created_at->format('Y-m-d H:i:s');
                } elseif (is_string($entry->created_at)) {
                    $createdAt = $entry->created_at;
                }
            }

            // TODO: Extrair informações relevantes do batch (ex: ID do batch, nome, status, jobs)
            // A estrutura exata pode variar dependendo de como o Telescope registra batches.
            $batchId = $content['id'] ?? 'N/A'; // O Telescope armazena o UUID do batch aqui
            $name = $content['name'] ?? 'N/A';
            $totalJobs = $content['total_jobs'] ?? 0;
            $pendingJobs = $content['pending_jobs'] ?? 0;
            $failedJobs = $content['failed_jobs'] ?? 0;
            $status = 'Processing'; // Determinar status baseado em pending/failed/total jobs
            if ($pendingJobs === 0 && $failedJobs === 0 && $totalJobs > 0) {
                $status = 'Completed';
            } elseif ($failedJobs > 0) {
                $status = 'Failed';
            } elseif ($pendingJobs > 0 && $totalJobs > 0) {
                 $status = 'Pending'; // ou 'Processing'
            }


            $batches[] = [
                'id' => $entry->id, // ID da entrada do Telescope
                'batch_id' => $batchId, // UUID do Batch
                'name' => $name,
                'total_jobs' => $totalJobs,
                'pending_jobs' => $pendingJobs,
                'failed_jobs' => $failedJobs,
                'status' => $status,
                'created_at' => $createdAt,
            ];
        }

        // Formatação tabular para facilitar a leitura
        $table = "Batches:\n\n";
        $table .= sprintf("%-5s %-36s %-20s %-10s %-10s %-10s %-10s %-20s\n", "ID", "Batch ID", "Name", "Total", "Pending", "Failed", "Status", "Created At");
        $table .= str_repeat("-", 150) . "\n"; // Ajustar largura da linha separadora

        foreach ($batches as $batch) {
             // Truncar nome se muito longo
            $name = $batch['name'];
            if (strlen($name) > 20) {
                $name = substr($name, 0, 17) . "...";
            }

            $table .= sprintf(
                "%-5s %-36s %-20s %-10s %-10s %-10s %-10s %-20s\n",
                $batch['id'],
                $batch['batch_id'],
                $name,
                $batch['total_jobs'],
                $batch['pending_jobs'],
                $batch['failed_jobs'],
                $batch['status'],
                $batch['created_at']
            );
        }

        return $this->formatResponse($table);
    }

    /**
     * Obtém detalhes de um batch específico
     */
    protected function getBatchDetails($id)
    {
        Logger::info($this->getName() . ' getting details', ['id' => $id]);

        // Buscar a entrada específica
        $entry = $this->getEntryDetails(EntryType::BATCH, $id);

        if (!$entry) {
            return $this->formatError("Batch não encontrado: {$id}");
        }

        $content = is_array($entry->content) ? $entry->content : [];

        // Formatação detalhada do batch
        $output = "Batch Details:\n\n";
        $output .= "Telescope Entry ID: {$entry->id}\n";
        $output .= "Batch UUID: " . ($content['id'] ?? 'N/A') . "\n"; // UUID do batch
        $output .= "Name: " . ($content['name'] ?? 'N/A') . "\n";
        $output .= "Total Jobs: " . ($content['total_jobs'] ?? 0) . "\n";
        $output .= "Pending Jobs: " . ($content['pending_jobs'] ?? 0) . "\n";
        $output .= "Failed Jobs: " . ($content['failed_jobs'] ?? 0) . "\n";

        $status = 'Processing'; // Determinar status baseado em pending/failed/total jobs
        if (($content['pending_jobs'] ?? 0) === 0 && ($content['failed_jobs'] ?? 0) === 0 && ($content['total_jobs'] ?? 0) > 0) {
            $status = 'Completed';
        } elseif (($content['failed_jobs'] ?? 0) > 0) {
            $status = 'Failed';
        } elseif (($content['pending_jobs'] ?? 0) > 0 && ($content['total_jobs'] ?? 0) > 0) {
             $status = 'Pending'; // ou 'Processing'
        }
        $output .= "Status: {$status}\n";


        $createdAt = 'Unknown';
        if (property_exists($entry, 'created_at') && !empty($entry->created_at)) {
            if (is_object($entry->created_at) && method_exists($entry->created_at, 'format')) {
                $createdAt = $entry->created_at->format('Y-m-d H:i:s');
            } elseif (is_string($entry->created_at)) {
                $createdAt = $entry->created_at;
            }
        }
        $output .= "Created At: {$createdAt}\n\n";

        // Jobs dentro do batch (pode ser uma lista de IDs ou detalhes limitados)
        if (isset($content['jobs']) && is_array($content['jobs']) && !empty($content['jobs'])) {
             $output .= "Jobs in Batch:\n";
             // Decidir como exibir os jobs. Apenas IDs? Nome e status?
             // Por enquanto, lista básica:
             foreach($content['jobs'] as $job) {
                 $output .= "- " . ($job['name'] ?? 'Unknown Job') . " (ID: " . ($job['id'] ?? 'N/A') . ", Status: " . ($job['status'] ?? 'Unknown') . ")\n";
             }
             $output .= "\n";
        }


        // TODO: Adicionar outros detalhes relevantes se existirem no conteúdo do batch

        return $this->formatResponse($output);
    }
} 