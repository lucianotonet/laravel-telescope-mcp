<?php

namespace LucianoTonet\TelescopeMcp\MCP\Tools;

use Laravel\Telescope\Contracts\EntriesRepository;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\Storage\EntryQueryOptions;
use LucianoTonet\TelescopeMcp\Support\Logger;

class QueriesTool extends AbstractTool
{
    /**
     * Retorna o nome curto da ferramenta
     */
    public function getShortName()
    {
        return 'queries';
    }

    /**
     * Retorna o esquema da ferramenta
     */
    public function getSchema()
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
    public function execute($params)
    {
        try {
            Logger::info($this->getName() . ' execute method called', ['params' => $params]);

            // Verificar se foi solicitado detalhes de uma query específica
            if ($this->hasId($params)) {
                return $this->getQueryDetails($params['id']);
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
    protected function listQueries($params)
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
            
            $duration = isset($content['time']) ? $content['time'] : 0;
            
            // Se o filtro de queries lentas estiver ativo, pular queries rápidas
            if (!empty($params['slow']) && $duration < 100) {
                continue;
            }
            
            $createdAt = 'Unknown';
            if (property_exists($entry, 'created_at') && !empty($entry->created_at)) {
                if (is_object($entry->created_at) && method_exists($entry->created_at, 'format')) {
                    $createdAt = $entry->created_at->format('Y-m-d H:i:s');
                } elseif (is_string($entry->created_at)) {
                    try {
                        if (trim($entry->created_at) !== '') {
                            $dateTime = new \DateTime($entry->created_at);
                            $createdAt = $dateTime->format('Y-m-d H:i:s');
                        }
                    } catch (\Exception $e) {
                        \LucianoTonet\TelescopeMcp\Support\Logger::warning('Failed to parse date in QueriesTool::listQueries', [
                            'date_string' => $entry->created_at,
                            'entry_id' => $entry->id ?? 'N/A',
                            'error' => $e->getMessage()
                        ]);
                    }
                }
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
        
        return $this->formatResponse($table);
    }

    /**
     * Obtém detalhes de uma query específica
     */
    protected function getQueryDetails($id)
    {
        Logger::info($this->getName() . ' getting details', ['id' => $id]);
        
        // Buscar a entrada específica
        $entry = $this->getEntryDetails(EntryType::QUERY, $id);
        
        if (!$entry) {
            return $this->formatError("Query não encontrada: {$id}");
        }
        
        $content = is_array($entry->content) ? $entry->content : [];
        
        // Formatação detalhada da query
        $output = "Query Details:\n\n";
        $output .= "ID: {$entry->id}\n";
        $output .= "Connection: " . ($content['connection'] ?? 'default') . "\n";
        $output .= "Duration: " . number_format(($content['time'] ?? 0), 2) . "ms\n";
        
        $createdAt = 'Unknown';
        if (property_exists($entry, 'created_at') && !empty($entry->created_at)) {
            if (is_object($entry->created_at) && method_exists($entry->created_at, 'format')) {
                $createdAt = $entry->created_at->format('Y-m-d H:i:s');
            } elseif (is_string($entry->created_at)) {
                try {
                    if (trim($entry->created_at) !== '') {
                        $dateTime = new \DateTime($entry->created_at);
                        $createdAt = $dateTime->format('Y-m-d H:i:s');
                    }
                } catch (\Exception $e) {
                    \LucianoTonet\TelescopeMcp\Support\Logger::warning('Failed to parse date in QueriesTool::getQueryDetails', [
                        'date_string' => $entry->created_at,
                        'entry_id' => $entry->id ?? 'N/A',
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }
        $output .= "Created At: {$createdAt}\n\n";
        
        // SQL completo
        $output .= "SQL:\n" . ($content['sql'] ?? 'Unknown') . "\n\n";
        
        // Bindings se disponíveis
        if (isset($content['bindings']) && !empty($content['bindings'])) {
            $output .= "Bindings:\n" . json_encode($content['bindings'], JSON_PRETTY_PRINT) . "\n";
        }
        
        return $this->formatResponse($output);
    }
} 