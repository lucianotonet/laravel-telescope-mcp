<?php

namespace LucianoTonet\TelescopeMcp\MCP\Tools;

use Laravel\Telescope\Contracts\EntriesRepository;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\Storage\EntryQueryOptions;
use LucianoTonet\TelescopeMcp\Support\Logger;

class RequestsTool extends AbstractTool
{
    /**
     * Retorna o nome curto da ferramenta
     */
    public function getShortName()
    {
        return 'requests';
    }
    
    /**
     * Retorna o esquema da ferramenta
     */
    public function getSchema()
    {
        return [
            'name' => $this->getName(),
            'description' => 'Lista requisições HTTP registradas pelo Telescope',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'id' => [
                        'type' => 'string',
                        'description' => 'ID da requisição específica para ver detalhes'
                    ],
                    'limit' => [
                        'type' => 'number',
                        'description' => 'Número máximo de requisições a retornar',
                        'default' => 50
                    ],
                    'tag' => [
                        'type' => 'string',
                        'description' => 'Filtrar por tag',
                        'default' => ''
                    ]
                ],
                'required' => []
            ],
            'outputSchema' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string'],
                        'url' => ['type' => 'string'],
                        'method' => ['type' => 'string'],
                        'status' => ['type' => 'number'],
                        'duration' => ['type' => 'number'],
                        'created_at' => ['type' => 'string', 'format' => 'date-time']
                    ],
                    'required' => ['id', 'url', 'method']
                ]
            ]
        ];
    }
    
    /**
     * Executa a ferramenta com os parâmetros fornecidos
     */
    public function execute($params)
    {
        Logger::info($this->getName() . ' execute method entered', ['params' => $params]);

        try {
            // Verificar se foi solicitado detalhes de uma requisição específica
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
     * Lista as requisições registradas pelo Telescope
     */
    protected function listRequests($params)
    {
        // Definir limite rígido para evitar problemas
        set_time_limit(5);
        
        // Limite padrão e tag
        $limit = min((int)($params['limit'] ?? 50), 100);
        $tag = $params['tag'] ?? null;
        
        $results = [];
        
        // Verificar repositório
        if (empty($this->entriesRepository)) {
            Logger::warning($this->getName() . ' repository not available');
            throw new \Exception('Telescope entries repository not available');
        }
        
        // Configurar opções
        $options = new EntryQueryOptions();
        $options->limit($limit);
        
        if (!empty($tag)) {
            $options->tag($tag);
        }
        
        Logger::debug($this->getName() . ' querying repository', [
            'options' => [
                'limit' => $limit,
                'tag' => $tag
            ]
        ]);
        
        // Buscar entradas com timeout curto
        $startTime = microtime(true);
        
        $entries = $this->entriesRepository->get(EntryType::REQUEST, $options);
        
        $duration = microtime(true) - $startTime;
        
        Logger::info($this->getName() . ' query completed', [
            'count' => count($entries),
            'duration' => $duration
        ]);
        
        // Se demorou mais de 2 segundos, logue como aviso
        if ($duration > 2) {
            Logger::warning($this->getName() . ' query slow', ['duration' => $duration]);
        }
        
        // Processar entradas rapidamente
        if (count($entries) > 0) {
            foreach ($entries as $entry) {
                // Tratar a data com segurança
                $created_at = 'Unknown';
                if ($entry && isset($entry->created_at)) {
                    if (is_object($entry->created_at) && method_exists($entry->created_at, 'format')) {
                        $created_at = $entry->created_at->format('Y-m-d H:i:s');
                    } elseif (is_string($entry->created_at)) {
                        try {
                            $created_at = (new \DateTime($entry->created_at))->format('Y-m-d H:i:s');
                        } catch (\Exception $e) {
                            Logger::warning('Failed to parse date', ['date' => $entry->created_at]);
                        }
                    }
                }

                $results[] = [
                    'id' => (string)$entry->id,
                    'url' => $entry->content['uri'] ?? 'Unknown',
                    'method' => $entry->content['method'] ?? 'Unknown',
                    'status' => (int)($entry->content['response_status'] ?? 0),
                    'duration' => (float)($entry->content['duration'] ?? 0),
                    'created_at' => $created_at
                ];
                
                // Limitar o número de resultados
                if (count($results) >= $limit) {
                    break;
                }
            }
        }
        
        Logger::info($this->getName() . ' execution completed', [
            'results_count' => count($results)
        ]);
        
        // Formatar os resultados em uma tabela
        $table = "HTTP Requests:\n\n";
        $table .= sprintf("%-5s %-30s %-8s %-8s %-10s %-25s\n", "ID", "URL", "Method", "Status", "Duration", "Created At");
        $table .= str_repeat("-", 90) . "\n";
        
        foreach ($results as $result) {
            $table .= sprintf(
                "%-5s %-30s %-8s %-8s %-10s %-25s\n",
                $result['id'],
                substr($result['url'], 0, 30),
                $result['method'],
                $result['status'],
                number_format($result['duration'], 2) . "ms",
                $result['created_at']
            );
        }
        
        // Retornar no formato esperado pelo MCP
        return $this->formatResponse($table);
    }
    
    /**
     * Obtém detalhes de uma requisição específica
     */
    protected function getRequestDetails($id)
    {
        Logger::info($this->getName() . ' getting details', ['id' => $id]);
        
        // Buscar a entrada específica
        $entry = $this->getEntryDetails(EntryType::REQUEST, $id);
        
        if (!$entry) {
            return $this->formatError("Requisição não encontrada: {$id}");
        }
        
        // Formatação detalhada da requisição
        $details = [
            'id' => $entry->id,
            'ip_address' => $entry->content['ip_address'] ?? 'Unknown',
            'uri' => $entry->content['uri'] ?? 'Unknown',
            'method' => $entry->content['method'] ?? 'Unknown',
            'controller_action' => $entry->content['controller_action'] ?? 'Unknown',
            'middleware' => $entry->content['middleware'] ?? [],
            'headers' => $entry->content['headers'] ?? [],
            'payload' => $entry->content['payload'] ?? [],
            'session' => $entry->content['session'] ?? [],
            'response_status' => $entry->content['response_status'] ?? 0,
            'response' => $entry->content['response'] ?? null,
            'duration' => $entry->content['duration'] ?? 0,
            'memory' => $entry->content['memory'] ?? 0,
            'created_at' => $entry->created_at ? (is_object($entry->created_at) ? $entry->created_at->format('Y-m-d H:i:s') : $entry->created_at) : 'Unknown'
        ];
        
        // Formatar como um texto estruturado para exibição
        $output = "HTTP Request Details:\n\n";
        $output .= "ID: {$details['id']}\n";
        $output .= "URI: {$details['uri']}\n";
        $output .= "Method: {$details['method']}\n";
        $output .= "Controller Action: {$details['controller_action']}\n";
        $output .= "Status: {$details['response_status']}\n";
        $output .= "Duration: " . number_format($details['duration'], 2) . "ms\n";
        $output .= "Memory: " . number_format($details['memory'] / 1024 / 1024, 2) . "MB\n";
        $output .= "IP Address: {$details['ip_address']}\n";
        $output .= "Created At: {$details['created_at']}\n\n";
        
        // Middleware
        $output .= "Middleware:\n" . json_encode($details['middleware'], JSON_PRETTY_PRINT) . "\n\n";
        
        // Headers (limitados)
        $output .= "Headers:\n" . json_encode(array_slice($details['headers'], 0, 10), JSON_PRETTY_PRINT) . "\n\n";
        
        // Payload (limitado)
        $output .= "Payload:\n" . json_encode(array_slice($details['payload'], 0, 10), JSON_PRETTY_PRINT) . "\n\n";
        
        // Response (truncado se for muito grande)
        $response = is_string($details['response']) ? $details['response'] : json_encode($details['response']);
        if (strlen($response) > 1000) {
            $response = substr($response, 0, 1000) . "... (truncated)";
        }
        $output .= "Response:\n{$response}\n";
        
        return $this->formatResponse($output);
    }
} 