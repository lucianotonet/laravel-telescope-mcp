<?php

namespace LucianoTonet\TelescopeMcp\MCP\Tools;

use Laravel\Telescope\Contracts\EntriesRepository;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\Storage\EntryQueryOptions;
use LucianoTonet\TelescopeMcp\Support\Logger;

class RequestsTool
{
    protected $entriesRepository;
    
    public function __construct(EntriesRepository $entriesRepository)
    {
        $this->entriesRepository = $entriesRepository;
    }
    
    public function getName()
    {
        return 'mcp_telescope_requests';
    }
    
    public function getSchema()
    {
        return [
            'name' => $this->getName(),
            'description' => 'Lista requisições HTTP registradas pelo Telescope',
            'parameters' => [
                'type' => 'object',
                'properties' => [
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
    
    public function execute($params)
    {
        Logger::info('RequestsTool execute method entered'); // Simplified log

        try {
            // Definir limite rígido para evitar problemas
            set_time_limit(5);
            
            // Limite padrão e tag
            $limit = min((int)($params['limit'] ?? 50), 100);
            $tag = $params['tag'] ?? null;
            
            $results = [];
            
            // Verificar repositório
            if (empty($this->entriesRepository)) {
                Logger::warning('RequestsTool repository not available');
                throw new \Exception('Telescope entries repository not available');
            }
            
            // Configurar opções
            $options = new EntryQueryOptions();
            $options->limit($limit);
            
            if (!empty($tag)) {
                $options->tag($tag);
            }
            
            Logger::debug('RequestsTool querying repository', [
                'options' => [
                    'limit' => $limit,
                    'tag' => $tag
                ]
            ]);
            
            // Buscar entradas com timeout curto
            try {
                $startTime = microtime(true);
                
                Logger::debug('RequestsTool before repository get', ['repository_class' => get_class($this->entriesRepository)]);
                
                $entries = $this->entriesRepository->get(EntryType::REQUEST, $options);
                
                // Adicionar log para inspecionar a estrutura de EntryResult
                if (!empty($entries)) {
                    Logger::debug('RequestsTool EntryResult structure', ['entry' => $entries[0]]);
                }
                
                Logger::debug('RequestsTool after repository get', ['entries_count' => count($entries)]);
                
                $duration = microtime(true) - $startTime;
                
                Logger::info('RequestsTool query completed', [
                    'count' => count($entries),
                    'duration' => $duration
                ]);
                
                // Se demorou mais de 2 segundos, logue como aviso
                if ($duration > 2) {
                    Logger::warning('RequestsTool query slow', ['duration' => $duration]);
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
            } catch (\Exception $e) {
                Logger::error('RequestsTool query failed', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }
            
            Logger::info('RequestsTool execution completed', [
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
            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => $table
                    ]
                ]
            ];
            
        } catch (\Exception $e) {
            Logger::error('RequestsTool execution error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'content' => [
                    [
                        'type' => 'error',
                        'text' => 'Error: ' . $e->getMessage()
                    ]
                ]
            ];
        }
    }
    
    /**
     * Define um timeout absoluto para a execução
     */
    protected function setupTimeout($seconds)
    {
        // Set PHP execution time limit
        set_time_limit($seconds);
        
        // For CLI only - setup alarm (not supported on Windows)
        if (function_exists('pcntl_alarm') && php_sapi_name() == 'cli') {
            pcntl_alarm($seconds);
        }
    }
    
    /**
     * Report progress to keep the connection alive
     */
    protected function reportProgress($percentage, $message)
    {
        \Illuminate\Support\Facades\Log::info('Tool progress', [
            'percentage' => $percentage,
            'message' => $message
        ]);
        
        // Flush output buffers to ensure progress is sent
        if (ob_get_level() > 0) {
            ob_flush();
            flush();
        }
        
        if (function_exists('mcp_report_progress')) {
            mcp_report_progress($percentage, $message);
        }
    }
} 