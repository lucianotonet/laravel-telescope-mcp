<?php

namespace LucianoTonet\TelescopeMcp\MCP;

use Illuminate\Support\Collection;
use Laravel\Telescope\Contracts\EntriesRepository;
use LucianoTonet\TelescopeMcp\MCP\Tools\RequestsTool;
use LucianoTonet\TelescopeMcp\MCP\Tools\LogsTool;
use LucianoTonet\TelescopeMcp\MCP\Tools\BatchesTool;
use LucianoTonet\TelescopeMcp\MCP\Tools\CacheTool;
use LucianoTonet\TelescopeMcp\MCP\Tools\CommandsTool;
use LucianoTonet\TelescopeMcp\MCP\Tools\DumpsTool;
use LucianoTonet\TelescopeMcp\MCP\Tools\EventsTool;
use LucianoTonet\TelescopeMcp\MCP\Tools\ExceptionsTool;
use LucianoTonet\TelescopeMcp\MCP\Tools\GatesTool;
use LucianoTonet\TelescopeMcp\MCP\Tools\HttpClientTool;
use LucianoTonet\TelescopeMcp\MCP\Tools\JobsTool;
use LucianoTonet\TelescopeMcp\MCP\Tools\MailTool;
use LucianoTonet\TelescopeMcp\MCP\Tools\ModelsTool;
use LucianoTonet\TelescopeMcp\MCP\Tools\NotificationsTool;
use LucianoTonet\TelescopeMcp\MCP\Tools\PruneTool;
use LucianoTonet\TelescopeMcp\MCP\Tools\QueriesTool;
use LucianoTonet\TelescopeMcp\MCP\Tools\RedisTool;
use LucianoTonet\TelescopeMcp\MCP\Tools\ScheduleTool;
use LucianoTonet\TelescopeMcp\MCP\Tools\ViewsTool;

class TelescopeMcpServer
{
    protected $entriesRepository;
    protected $tools;

    public function __construct(EntriesRepository $entriesRepository)
    {
        $this->entriesRepository = $entriesRepository;
        $this->tools = new Collection();
        
        // Registrar ferramentas existentes
        $this->registerTool(new RequestsTool($entriesRepository));
        $this->registerTool(new LogsTool($entriesRepository));

        // Registrar novas ferramentas
        $this->registerTool(new BatchesTool($entriesRepository));
        $this->registerTool(new CacheTool($entriesRepository));
        $this->registerTool(new CommandsTool($entriesRepository));
        $this->registerTool(new DumpsTool($entriesRepository));
        $this->registerTool(new EventsTool($entriesRepository));
        $this->registerTool(new ExceptionsTool($entriesRepository));
        $this->registerTool(new GatesTool($entriesRepository));
        $this->registerTool(new HttpClientTool($entriesRepository));
        $this->registerTool(new JobsTool($entriesRepository));
        $this->registerTool(new MailTool($entriesRepository));
        $this->registerTool(new ModelsTool($entriesRepository));
        $this->registerTool(new NotificationsTool($entriesRepository));
        $this->registerTool(new QueriesTool($entriesRepository));
        $this->registerTool(new RedisTool($entriesRepository));
        $this->registerTool(new ScheduleTool($entriesRepository));
        $this->registerTool(new ViewsTool($entriesRepository));

        // Registrar PruneTool (não precisa de $entriesRepository no construtor)
        $this->registerTool(new PruneTool());
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