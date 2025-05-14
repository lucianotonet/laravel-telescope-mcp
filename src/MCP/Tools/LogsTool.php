<?php

namespace LucianoTonet\TelescopeMcp\MCP\Tools;

use Laravel\Telescope\Contracts\EntriesRepository;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\Storage\EntryQueryOptions;
use LucianoTonet\TelescopeMcp\Support\Logger;
use Laravel\Telescope\Models\TelescopeEntry;

class LogsTool
{
    protected $entriesRepository;
    
    public function __construct(EntriesRepository $entriesRepository)
    {
        $this->entriesRepository = $entriesRepository;
    }
    
    public function getName()
    {
        return 'mcp_telescope_logs';
    }
    
    public function getSchema()
    {
        return [
            'name' => $this->getName(),
            'description' => 'Retrieve application logs from Telescope. The tool returns logs with their messages, levels, timestamps and context. It can filter logs by level and limit the number of results.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Maximum number of logs to return. Default is 100.',
                        'default' => 100
                    ],
                    'level' => [
                        'type' => 'string',
                        'description' => 'Filter logs by level. Case insensitive.',
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
                    'description' => 'Get last 10 error logs',
                    'params' => [
                        'level' => 'error',
                        'limit' => 10
                    ]
                ],
                [
                    'description' => 'Get all debug logs (up to 100)',
                    'params' => [
                        'level' => 'debug'
                    ]
                ]
            ]
        ];
    }
    
    public function execute($params)
    {
        try {
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
                            'id' => $entry->sequence,
                            'timestamp' => property_exists($entry, 'createdAt') && $entry->createdAt ? $entry->createdAt->toIso8601String() : null,
                            'level' => $content['level'] ?? 'info',
                            'message' => $content['message'],
                            'context' => $content['context'] ?? []
                        ];
                    }
                    
                    // Se o conteúdo tiver uma estrutura com type e text (como visto nos logs)
                    if (isset($content['content']) && is_array($content['content'])) {
                        foreach ($content['content'] as $item) {
                            if (isset($item['type']) && $item['type'] === 'text' && isset($item['text'])) {
                                return [
                                    'id' => $entry->sequence,
                                    'timestamp' => property_exists($entry, 'createdAt') && $entry->createdAt ? $entry->createdAt->toIso8601String() : null,
                                    'level' => $content['level'] ?? 'info',
                                    'message' => $item['text'],
                                    'context' => $content['context'] ?? []
                                ];
                            }
                        }
                    }
                    
                    // Fallback para outros casos
                    return [
                        'id' => $entry->sequence,
                        'timestamp' => property_exists($entry, 'createdAt') && $entry->createdAt ? $entry->createdAt->toIso8601String() : null,
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

            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => json_encode([
                            'logs' => $logs,
                            'total' => $logs->count()
                        ], JSON_PRETTY_PRINT)
                    ]
                ]
            ];
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error executing LogsTool', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }
} 