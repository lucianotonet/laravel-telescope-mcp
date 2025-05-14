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
            'description' => 'Retrieve application logs from Telescope',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Maximum number of logs to return',
                        'default' => 100
                    ],
                    'level' => [
                        'type' => 'string',
                        'description' => 'Filter logs by level',
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
            ]
        ];
    }
    
    public function execute($params)
    {
        try {
            $query = TelescopeEntry::where('type', 'log')
                ->orderBy('sequence', 'desc')
                ->take(100)
                ->get();

            $logs = $query->map(function ($entry) {
                $content = json_decode($entry->content, true);
                return [
                    'id' => $entry->sequence,
                    'timestamp' => $entry->created_at->toIso8601String(),
                    'level' => $content['level'] ?? 'info',
                    'message' => $content['message'] ?? '',
                    'context' => $content['context'] ?? []
                ];
            });

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