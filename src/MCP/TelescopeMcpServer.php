<?php

namespace LucianoTonet\TelescopeMcp\MCP;

use Illuminate\Support\Collection;
use Laravel\Telescope\Contracts\EntriesRepository;
use LucianoTonet\TelescopeMcp\MCP\Tools\RequestsTool;
use LucianoTonet\TelescopeMcp\MCP\Tools\LogsTool;

class TelescopeMcpServer
{
    protected $entriesRepository;
    protected $tools;

    public function __construct(EntriesRepository $entriesRepository)
    {
        $this->entriesRepository = $entriesRepository;
        $this->tools = new Collection();
        
        // Registrar a ferramenta de requests
        $this->registerTool(new RequestsTool($entriesRepository));
        
        // Registrar a ferramenta de logs
        $this->registerTool(new LogsTool($entriesRepository));
    }
    
    public function registerTool($tool)
    {
        $this->tools->put($tool->getName(), $tool);
    }
    
    public function hasTool($toolName)
    {
        return $this->tools->has($toolName);
    }
    
    public function getManifest()
    {
        // Format tools to match MCP client expectations
        $toolsFormatted = (object)[];
        foreach ($this->tools as $name => $tool) {
            $schema = $tool->getSchema();
            $toolsFormatted->{$schema['name']} = [
                'name' => $schema['name'],
                'description' => $schema['description'],
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => $schema['parameters']['properties'] ?? [],
                    'required' => $schema['parameters']['required'] ?? []
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
        
        return [
            'name' => 'Laravel Telescope MCP',
            'version' => '1.0.0',
            'description' => 'Laravel Telescope Model Context Provider',
            'tools' => $toolsFormatted
        ];
    }
    
    public function executeTool($toolName, $params)
    {
        if (!$this->tools->has($toolName)) {
            throw new \Exception("Tool not found: {$toolName}");
        }
        
        try {
            $tool = $this->tools->get($toolName);
            $result = $tool->execute($params);
            
            // Log the execution result
            \Illuminate\Support\Facades\Log::info('Tool execution result', [
                'tool' => $toolName,
                'result' => $result
            ]);
            
            // Garantir que o resultado esteja no formato esperado pelo MCP
            if (!isset($result['content'])) {
                $result = [
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => is_string($result) ? $result : json_encode($result, JSON_PRETTY_PRINT)
                        ]
                    ]
                ];
            }
            
            return $result;
            
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Tool execution error', [
                'tool' => $toolName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }
} 