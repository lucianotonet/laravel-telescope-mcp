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
use Illuminate\Support\Facades\Log;
use LucianoTonet\TelescopeMcp\Support\JsonRpcResponse;
use LucianoTonet\TelescopeMcp\Support\Logger;

class TelescopeMcpServer
{
    protected $entriesRepository;
    protected $tools;
    protected $manifest;
    
    /**
     * Prefixo para nomes de ferramenta
     * 
     * @var string
     */
    protected $toolPrefix = '';

    public function __construct(EntriesRepository $entriesRepository)
    {
        $this->entriesRepository = $entriesRepository;
        $this->tools = new Collection();
        
        // Registrar ferramentas existentes
        $this->registerTool(new RequestsTool($entriesRepository));
        $this->registerTool(new LogsTool($entriesRepository));
        $this->registerTool(new ExceptionsTool($entriesRepository));

        // Registrar novas ferramentas
        $this->registerTool(new BatchesTool($entriesRepository));
        $this->registerTool(new CacheTool($entriesRepository));
        $this->registerTool(new CommandsTool($entriesRepository));
        $this->registerTool(new DumpsTool($entriesRepository));
        $this->registerTool(new EventsTool($entriesRepository));
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

        $this->buildManifest();
    }
    
    /**
     * Registra uma ferramenta no servidor MCP
     * 
     * @param object $tool
     * @return void
     */
    public function registerTool($tool)
    {
        // Usar o nome completo fornecido pela ferramenta
        $toolName = $tool->getName();
        
        // Adicionar à coleção
        $this->tools->put($toolName, $tool);
    }
    
    /**
     * Verifica se uma ferramenta está registrada
     * 
     * @param string $toolName
     * @return bool
     */
    public function hasTool($toolName)
    {
        // Tentar buscar pelo nome exato
        if ($this->tools->has($toolName)) {
            return true;
        }
        
        // Se não encontrou pelo nome exato, tentar buscar pela ferramenta
        foreach ($this->tools as $tool) {
            if ($tool->getName() === $toolName) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Obtém o nome curto de uma ferramenta
     * 
     * @param string $toolName
     * @return string
     */
    protected function getShortToolName($toolName)
    {
        // Remover o prefixo "mcp_telescope_" se existir
        if (strpos($toolName, 'mcp_telescope_') === 0) {
            return substr($toolName, strlen('mcp_telescope_'));
        }
        
        return $toolName;
    }
    
    /**
     * Obtém uma ferramenta pelo nome
     * 
     * @param string $toolName
     * @return object|null
     */
    protected function getTool($toolName)
    {
        // Tentar buscar pelo nome exato
        if ($this->tools->has($toolName)) {
            return $this->tools->get($toolName);
        }
        
        // Se não encontrou pelo nome exato, tentar buscar pela ferramenta
        foreach ($this->tools as $tool) {
            if ($tool->getName() === $toolName) {
                return $tool;
            }
        }
        
        return null;
    }
    
    /**
     * Constrói o manifesto do servidor MCP
     */
    protected function buildManifest()
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
        
        $this->manifest = [
            'name' => 'Laravel Telescope MCP',
            'version' => '1.0.0',
            'description' => 'Laravel Telescope Model Context Provider',
            'tools' => $toolsFormatted
        ];
    }
    
    /**
     * Retorna o manifesto do servidor MCP
     */
    public function getManifest(): array
    {
        return $this->manifest;
    }
    
    /**
     * Executa uma ferramenta com os argumentos fornecidos
     *
     * @param string $toolName Nome da ferramenta
     * @param array $arguments Argumentos para a ferramenta
     * @return array Resultado da execução
     * @throws \Exception Se a ferramenta não for encontrada
     */
    public function executeTool(string $toolName, array $arguments = []): array
    {
        Logger::info('Executing tool', [
            'tool' => $toolName,
            'arguments' => $arguments
        ]);
        
        try {
            // Verificar se a ferramenta existe
            if (!isset($this->tools[$toolName])) {
                throw new \Exception("Tool not found: {$toolName}");
            }
            
            // Executar a ferramenta
            $tool = $this->tools[$toolName];
            $result = $tool->execute($arguments);
            
            Logger::info('Tool execution successful', [
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
            Log::error('Tool execution error', [
                'tool' => $toolName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }
} 