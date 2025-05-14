# Plano de Implementação: Laravel Telescope MCP

## Status Atual

Implementamos com sucesso uma versão funcional do servidor MCP para Laravel Telescope, com a seguinte estrutura:

```
laravel-telescope-mcp/
  ├── src/
  │   ├── Http/
  │   │   └── Controllers/
  │   │       └── McpController.php
  │   ├── MCP/
  │   │   ├── TelescopeMcpServer.php
  │   │   └── Tools/
  │   │       └── RequestsTool.php
  ├── routes/
  │   └── api.php
  ├── DEVELOPMENT.md
  └── PLAN.md
```

A primeira ferramenta (`mcp_telescope_requests`) foi implementada e está funcionando corretamente, permitindo consultar requisições HTTP registradas pelo Telescope via protocolo MCP.

## Próximos Passos

### 1. Implementação de Ferramentas Adicionais

#### 1.1 LogsTool

Criar uma ferramenta para acessar logs registrados pelo Telescope:

```php
<?php

namespace LucianoTonet\TelescopeMcp\MCP\Tools;

use Laravel\Telescope\Contracts\EntriesRepository;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\Storage\EntryQueryOptions;

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
            'description' => 'Lista logs registrados pelo Telescope',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'limit' => [
                        'type' => 'number',
                        'description' => 'Número máximo de logs a retornar',
                        'default' => 50
                    ],
                    'tag' => [
                        'type' => 'string',
                        'description' => 'Filtrar por tag',
                        'default' => ''
                    ],
                    'level' => [
                        'type' => 'string',
                        'description' => 'Filtrar por nível de log (info, error, warning, etc)',
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
                        'level' => ['type' => 'string'],
                        'message' => ['type' => 'string'],
                        'context' => ['type' => 'object'],
                        'created_at' => ['type' => 'string', 'format' => 'date-time']
                    ]
                ]
            ]
        ];
    }
    
    public function execute($params)
    {
        $limit = $params['limit'] ?? 50;
        $tag = $params['tag'] ?? null;
        $level = $params['level'] ?? null;
        
        try {
            $options = new EntryQueryOptions();
            $options->limit($limit);
            
            if (!empty($tag)) {
                $options->tag($tag);
            }
            
            $entries = $this->entriesRepository->get(EntryType::LOG, $options);
            
            $results = [];
            
            foreach ($entries as $entry) {
                try {
                    // Filtrar por nível se especificado
                    if ($level && $entry->content['level'] !== $level) {
                        continue;
                    }
                    
                    $resultItem = [
                        'id' => $entry->id,
                        'level' => $entry->content['level'] ?? 'Unknown',
                        'message' => $entry->content['message'] ?? 'Unknown',
                        'context' => $entry->content['context'] ?? [],
                        'created_at' => $entry->created_at ?? null,
                    ];
                    
                    $results[] = $resultItem;
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('Error processing log entry', [
                        'entry_id' => $entry->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            return $results;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('LogsTool error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }
}
```

#### 1.2 QueriesTool

Para monitorar queries de banco de dados:

```php
<?php

namespace LucianoTonet\TelescopeMcp\MCP\Tools;

use Laravel\Telescope\Contracts\EntriesRepository;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\Storage\EntryQueryOptions;

class QueriesTool
{
    protected $entriesRepository;
    
    public function __construct(EntriesRepository $entriesRepository)
    {
        $this->entriesRepository = $entriesRepository;
    }
    
    public function getName()
    {
        return 'mcp_telescope_queries';
    }
    
    public function getSchema()
    {
        return [
            'name' => $this->getName(),
            'description' => 'Lista queries de banco de dados registradas pelo Telescope',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'limit' => [
                        'type' => 'number',
                        'description' => 'Número máximo de queries a retornar',
                        'default' => 50
                    ],
                    'tag' => [
                        'type' => 'string',
                        'description' => 'Filtrar por tag',
                        'default' => ''
                    ],
                    'connection' => [
                        'type' => 'string',
                        'description' => 'Filtrar por conexão de banco de dados',
                        'default' => ''
                    ],
                    'slow' => [
                        'type' => 'boolean',
                        'description' => 'Retornar apenas queries lentas',
                        'default' => false
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
                        'connection' => ['type' => 'string'],
                        'query' => ['type' => 'string'],
                        'duration' => ['type' => 'number'],
                        'created_at' => ['type' => 'string', 'format' => 'date-time']
                    ]
                ]
            ]
        ];
    }
    
    public function execute($params)
    {
        $limit = $params['limit'] ?? 50;
        $tag = $params['tag'] ?? null;
        $connection = $params['connection'] ?? null;
        $slow = $params['slow'] ?? false;
        
        try {
            $options = new EntryQueryOptions();
            $options->limit($limit);
            
            if (!empty($tag)) {
                $options->tag($tag);
            }
            
            $entries = $this->entriesRepository->get(EntryType::QUERY, $options);
            
            $results = [];
            
            foreach ($entries as $entry) {
                try {
                    // Filtrar por conexão se especificado
                    if ($connection && $entry->content['connection'] !== $connection) {
                        continue;
                    }
                    
                    // Filtrar por queries lentas (acima de 100ms) se solicitado
                    if ($slow && ($entry->content['time'] < 100)) {
                        continue;
                    }
                    
                    $resultItem = [
                        'id' => $entry->id,
                        'connection' => $entry->content['connection'] ?? 'Unknown',
                        'query' => $entry->content['query'] ?? 'Unknown',
                        'bindings' => $entry->content['bindings'] ?? [],
                        'duration' => $entry->content['time'] ?? null,
                        'created_at' => $entry->created_at ?? null,
                    ];
                    
                    $results[] = $resultItem;
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('Error processing query entry', [
                        'entry_id' => $entry->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            return $results;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('QueriesTool error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }
}
```

### 2. Autenticação e Segurança

Implementar um mecanismo de autenticação simples para o servidor MCP:

1. Criar um middleware de autenticação específico para o servidor MCP
2. Configurar rotas para usar este middleware
3. Documentar como configurar e proteger o servidor MCP em produção

### 3. Documentação

1. Criar um README.md detalhado para o projeto
2. Documentar cada ferramenta e seus parâmetros
3. Fornecer exemplos de uso com mcp-remote e Cursor

### 4. Testes Automatizados

Criar testes unitários e de integração para o servidor MCP, cobrindo:

1. Funcionalidade de cada ferramenta individualmente
2. Conformidade com o protocolo MCP
3. Tratamento de erros e casos de borda

## Considerações Finais

Este plano representa os próximos passos para a evolução do Laravel Telescope MCP após a implementação inicial bem-sucedida. O foco deve ser expandir o conjunto de ferramentas disponíveis, melhorar a segurança e documentar adequadamente o projeto para facilitar sua adoção.
