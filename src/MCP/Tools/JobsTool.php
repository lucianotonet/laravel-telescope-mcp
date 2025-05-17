<?php

namespace LucianoTonet\TelescopeMcp\MCP\Tools;

use Laravel\Telescope\Contracts\EntriesRepository;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\Storage\EntryQueryOptions;
use LucianoTonet\TelescopeMcp\Support\Logger;
use LucianoTonet\TelescopeMcp\Support\DateFormatter;

class JobsTool extends AbstractTool
{
    /**
     * Retorna o nome curto da ferramenta
     */
    public function getShortName(): string
    {
        return 'jobs';
    }

    /**
     * Retorna o esquema da ferramenta
     */
    public function getSchema(): array
    {
        return [
            'name' => $this->getName(),
            'description' => 'Lista e analisa jobs (tarefas em fila) registrados pelo Telescope',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'id' => [
                        'type' => 'string',
                        'description' => 'ID do job específico para ver detalhes'
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Número máximo de jobs a retornar',
                        'default' => 50
                    ],
                    'status' => [
                        'type' => 'string',
                        'description' => 'Filtrar por status (pending, processed, failed)',
                        'enum' => ['pending', 'processed', 'failed']
                    ],
                    'queue' => [
                        'type' => 'string',
                        'description' => 'Filtrar por fila específica'
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

            // Verificar se foi solicitado detalhes de um job específico
            if ($this->hasId($params)) {
                return $this->getJobDetails($params['id']);
            }
            
            return $this->listJobs($params);
        } catch (\Exception $e) {
            Logger::error($this->getName() . ' execution error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->formatError('Error: ' . $e->getMessage());
        }
    }

    /**
     * Lista os jobs registrados pelo Telescope
     */
    protected function listJobs(array $params): array
    {
        // Definir limite para a consulta
        $limit = isset($params['limit']) ? min((int)$params['limit'], 100) : 50;
        
        // Configurar opções
        $options = new EntryQueryOptions();
        $options->limit($limit);
        
        // Adicionar filtros se especificados
        if (!empty($params['status'])) {
            $options->tag($params['status']);
        }
        if (!empty($params['queue'])) {
            $options->tag($params['queue']);
        }
        
        // Buscar entradas usando o repositório
        $entries = $this->entriesRepository->get(EntryType::JOB, $options);
        
        if (empty($entries)) {
            return $this->formatResponse("Nenhum job encontrado.");
        }
        
        $jobs = [];
        
        foreach ($entries as $entry) {
            $content = is_array($entry->content) ? $entry->content : [];
            
            // Format the date using DateFormatter
            $createdAt = DateFormatter::format($entry->createdAt);
            
            $jobs[] = [
                'id' => $entry->id,
                'name' => $content['name'] ?? 'Unknown',
                'status' => $content['status'] ?? 'Unknown',
                'queue' => $content['queue'] ?? 'default',
                'created_at' => $createdAt,
                'attempts' => $content['attempts'] ?? 0
            ];
        }
        
        // Formatação tabular para facilitar a leitura
        $table = "Jobs:\n\n";
        $table .= sprintf("%-5s %-40s %-10s %-15s %-8s %-20s\n", "ID", "Name", "Status", "Queue", "Attempts", "Created At");
        $table .= str_repeat("-", 105) . "\n";
        
        foreach ($jobs as $job) {
            // Truncar nome longo
            $name = $job['name'];
            if (strlen($name) > 40) {
                $name = substr($name, 0, 37) . "...";
            }
            
            $table .= sprintf(
                "%-5s %-40s %-10s %-15s %-8s %-20s\n",
                $job['id'],
                $name,
                $job['status'],
                $job['queue'],
                $job['attempts'],
                $job['created_at']
            );
        }
        
        return $this->formatResponse($table);
    }

    /**
     * Obtém detalhes de um job específico
     */
    protected function getJobDetails(string $id): array
    {
        Logger::info($this->getName() . ' getting details', ['id' => $id]);
        
        // Buscar a entrada específica
        $entry = $this->getEntryDetails(EntryType::JOB, $id);
        
        if (!$entry) {
            return $this->formatError("Job não encontrado: {$id}");
        }
        
        $content = is_array($entry->content) ? $entry->content : [];
        
        // Format the date using DateFormatter
        $createdAt = DateFormatter::format($entry->createdAt);
        
        // Detailed formatting of the job
        $output = "Job Details:\n\n";
        $output .= "ID: {$entry->id}\n";
        $output .= "Name: " . ($content['name'] ?? 'Unknown') . "\n";
        $output .= "Status: " . ($content['status'] ?? 'Unknown') . "\n";
        $output .= "Queue: " . ($content['queue'] ?? 'default') . "\n";
        $output .= "Attempts: " . ($content['attempts'] ?? 0) . "\n";
        $output .= "Created At: {$createdAt}\n\n";
        
        // Dados do job
        if (isset($content['data']) && !empty($content['data'])) {
            $output .= "Data:\n" . json_encode($content['data'], JSON_PRETTY_PRINT) . "\n\n";
        }
        
        // Exception se falhou
        if (isset($content['exception']) && !empty($content['exception'])) {
            $output .= "Exception:\n" . $content['exception'] . "\n";
        }
        
        return $this->formatResponse($output);
    }
} 