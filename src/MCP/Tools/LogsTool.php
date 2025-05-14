<?php

namespace LucianoTonet\TelescopeMcp\MCP\Tools;

use Laravel\Telescope\Contracts\EntriesRepository;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\Storage\EntryQueryOptions;
use LucianoTonet\TelescopeMcp\Support\Logger;
use Laravel\Telescope\Models\TelescopeEntry;

class LogsTool extends AbstractTool
{
    protected $entriesRepository;
    
    public function __construct(EntriesRepository $entriesRepository)
    {
        $this->entriesRepository = $entriesRepository;
    }
    
    /**
     * Retorna o nome curto da ferramenta
     */
    public function getShortName()
    {
        return 'logs';
    }
    
    /**
     * Retorna o esquema da ferramenta
     */
    public function getSchema()
    {
        return [
            'name' => $this->getName(),
            'description' => 'Recupera logs da aplicação registrados pelo Telescope. Permite filtrar por nível e limitar resultados.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'id' => [
                        'type' => 'string',
                        'description' => 'ID do log específico para ver detalhes'
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Número máximo de logs a retornar. Padrão é 100.',
                        'default' => 100
                    ],
                    'level' => [
                        'type' => 'string',
                        'description' => 'Filtrar logs por nível. Insensível a maiúsculas/minúsculas.',
                        'enum' => ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency']
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
                    'description' => 'Obter os últimos 10 logs de erro',
                    'params' => [
                        'level' => 'error',
                        'limit' => 10
                    ]
                ],
                [
                    'description' => 'Obter todos os logs de debug (até 100)',
                    'params' => [
                        'level' => 'debug'
                    ]
                ],
                [
                    'description' => 'Ver detalhes de um log específico',
                    'params' => [
                        'id' => '123456'
                    ]
                ]
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

            // Verificar se foi solicitado detalhes de um log específico
            if ($this->hasId($params)) {
                return $this->getLogDetails($params['id']);
            }
            
            return $this->listLogs($params);
        } catch (\Exception $e) {
            Logger::error($this->getName() . ' execution error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->formatError('Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Lista os logs registrados pelo Telescope
     */
    protected function listLogs($params)
    {
        // Configurar opções de consulta
        $options = new EntryQueryOptions();
        $options->limit($params['limit'] ?? 100);
        
        // Buscar entradas usando o repositório
        $entries = $this->entriesRepository->get(EntryType::LOG, $options);

        $logs = collect($entries)
            ->map(function ($entry) {
                $content = is_array($entry->content) ? $entry->content : [];
                
                // Se o conteúdo tiver uma estrutura específica com message e context
                if (isset($content['message'])) {
                    return [
                        'id' => $entry->id,
                        'timestamp' => property_exists($entry, 'created_at') && $entry->created_at ? 
                            (is_object($entry->created_at) ? $entry->created_at->format('Y-m-d H:i:s') : $entry->created_at) : null,
                        'level' => $content['level'] ?? 'info',
                        'message' => $content['message'],
                        'context' => $content['context'] ?? []
                    ];
                }
                
                // Fallback para outros casos
                return [
                    'id' => $entry->id,
                    'timestamp' => property_exists($entry, 'created_at') && $entry->created_at ? 
                        (is_object($entry->created_at) ? $entry->created_at->format('Y-m-d H:i:s') : $entry->created_at) : null,
                    'level' => $content['level'] ?? 'info',
                    'message' => json_encode($content, JSON_PRETTY_PRINT),
                    'context' => []
                ];
            });

        // Aplicar filtro por nível se especificado
        if (!empty($params['level'])) {
            $logs = $logs->filter(function ($log) use ($params) {
                return strtolower($log['level']) === strtolower($params['level']);
            });
        }

        // Formatação tabular para facilitar a leitura
        $table = "Application Logs:\n\n";
        $table .= sprintf("%-5s %-20s %-10s %-50s\n", "ID", "Timestamp", "Level", "Message");
        $table .= str_repeat("-", 90) . "\n";
        
        foreach ($logs as $log) {
            // Truncar mensagem longa
            $message = $log['message'];
            if (strlen($message) > 50) {
                $message = substr($message, 0, 47) . "...";
            }
            
            $table .= sprintf(
                "%-5s %-20s %-10s %-50s\n",
                $log['id'],
                $log['timestamp'] ?? 'Unknown',
                strtoupper($log['level']),
                $message
            );
        }
        
        return $this->formatResponse($table);
    }
    
    /**
     * Obtém detalhes de um log específico
     */
    protected function getLogDetails($id)
    {
        Logger::info($this->getName() . ' getting details', ['id' => $id]);
        
        // Buscar a entrada específica
        $entry = $this->getEntryDetails(EntryType::LOG, $id);
        
        if (!$entry) {
            return $this->formatError("Log não encontrado: {$id}");
        }
        
        $content = is_array($entry->content) ? $entry->content : [];
        
        // Formatação detalhada do log
        $details = [
            'id' => $entry->id,
            'timestamp' => property_exists($entry, 'created_at') && $entry->created_at ? 
                (is_object($entry->created_at) ? $entry->created_at->format('Y-m-d H:i:s') : $entry->created_at) : 'Unknown',
            'level' => $content['level'] ?? 'info',
            'message' => $content['message'] ?? json_encode($content),
            'context' => $content['context'] ?? []
        ];
        
        // Formatar como um texto estruturado para exibição
        $output = "Log Details:\n\n";
        $output .= "ID: {$details['id']}\n";
        $output .= "Timestamp: {$details['timestamp']}\n";
        $output .= "Level: " . strtoupper($details['level']) . "\n\n";
        $output .= "Message:\n{$details['message']}\n\n";
        
        // Context (se disponível)
        if (!empty($details['context'])) {
            $output .= "Context:\n" . json_encode($details['context'], JSON_PRETTY_PRINT) . "\n";
        }
        
        return $this->formatResponse($output);
    }
} 