<?php

namespace LucianoTonet\TelescopeMcp\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use LucianoTonet\TelescopeMcp\MCP\TelescopeMcpServer;
use LucianoTonet\TelescopeMcp\Support\Logger;

class McpController extends Controller
{
    protected $server;
    
    public function __construct(TelescopeMcpServer $server)
    {
        $this->server = $server;
    }
    
    public function manifest(Request $request)
    {
        Logger::info('MCP request received', [
            'method' => $request->method(),
            'input' => $request->all()
        ]);
        
        // Se for uma requisição GET, retornar o manifesto diretamente
        if ($request->method() === 'GET') {
            $manifest = $this->server->getManifest();
            
            return response()->json([
                'jsonrpc' => '2.0',
                'result' => [
                    'protocolVersion' => '2024-11-05',
                    'serverInfo' => [
                        'name' => $manifest['name'],
                        'version' => $manifest['version'],
                        'description' => $manifest['description']
                    ],
                    'capabilities' => [
                        'tools' => $manifest['tools']
                    ]
                ]
            ]);
        }
        
        // Verificar se é uma requisição JSON-RPC 2.0
        if ($request->isJson() && $request->input('jsonrpc') === '2.0') {
            $method = $request->input('method');
            $id = $request->input('id', null);
            $params = $request->input('params', []);
            
            Logger::info('JSON-RPC request', [
                'method' => $method,
                'id' => $id,
                'params' => $params
            ]);
            
            switch ($method) {
                case 'mcp.manifest':
                case 'mcp.getManifest':
                case 'initialize':
                    // Retornar informações sobre o servidor MCP
                    $manifest = $this->server->getManifest();
                    
                    return response()->json([
                        'jsonrpc' => '2.0',
                        'id' => $id,
                        'result' => [
                            'protocolVersion' => '2024-11-05',
                            'serverInfo' => [
                                'name' => $manifest['name'],
                                'version' => $manifest['version'],
                                'description' => $manifest['description']
                            ],
                            'capabilities' => [
                                'tools' => $manifest['tools']
                            ]
                        ]
                    ]);
                
                case 'tools/list':
                    // Retornar lista de ferramentas disponíveis
                    $manifest = $this->server->getManifest();
                    
                    return response()->json([
                        'jsonrpc' => '2.0',
                        'id' => $id,
                        'result' => [
                            'tools' => array_values((array) $manifest['tools']) // Convert to array only for tools/list
                        ]
                    ]);
                
                case 'tools/call':
                    // Executar uma ferramenta
                    $toolName = $params['name'] ?? null;
                    $arguments = $params['arguments'] ?? [];
                    
                    if (!$toolName) {
                        return $this->jsonRpcError($id, -32602, 'Invalid params: tool name is required');
                    }
                    
                    Logger::info('Executing tool via JSON-RPC', [
                        'tool' => $toolName,
                        'arguments' => $arguments
                    ]);
                    
                    try {
                        $result = $this->server->executeTool($toolName, $arguments);
                        
                        // Garantir que a resposta esteja no formato correto
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
                        
                        return response()->json([
                            'jsonrpc' => '2.0',
                            'id' => $id,
                            'result' => $result
                        ]);
                    } catch (\Exception $e) {
                        return $this->jsonRpcError($id, -32603, $e->getMessage());
                    }
                
                default:
                    // Método não suportado
                    return $this->jsonRpcError($id, -32601, "Method not found: {$method}");
            }
        }
        
        // Se não for uma requisição JSON-RPC, retornar o manifesto
        $manifest = $this->server->getManifest();
        
        return response()->json([
            'jsonrpc' => '2.0',
            'result' => [
                'protocolVersion' => '2024-11-05',
                'serverInfo' => [
                    'name' => $manifest['name'],
                    'version' => $manifest['version'],
                    'description' => $manifest['description']
                ],
                'capabilities' => [
                    'tools' => $manifest['tools']
                ]
            ]
        ]);
    }
    
    public function executeTool(Request $request, $tool)
    {
        try {
            Logger::info('MCP tool execution request', [
                'tool' => $tool,
                'method' => $request->method(),
                'headers' => $request->headers->all(),
                'input' => $request->all()
            ]);
            
            // Validar requisição JSON-RPC
            if (!$request->isJson()) {
                Logger::warning('Invalid request format - not JSON', [
                    'tool' => $tool,
                    'content_type' => $request->header('Content-Type')
                ]);
                
                return response()->json([
                    'jsonrpc' => '2.0',
                    'id' => null,
                    'error' => [
                        'code' => -32700,
                        'message' => 'Parse error: Invalid JSON'
                    ]
                ], 400);
            }
            
            // Executar a ferramenta
            $params = $request->input('params', []);
            Logger::debug('Executing tool', [
                'tool' => $tool,
                'params' => $params
            ]);
            
            $result = $this->server->executeTool($tool, $params);
            
            // Formatar resposta no padrão esperado pelo MCP
            $response = [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => json_encode($result)
                    ]
                ]
            ];
            
            Logger::info('Tool execution successful', [
                'tool' => $tool,
                'response' => $response
            ]);
            
            // Retornar resposta
            return response()->json($response);
            
        } catch (\Exception $e) {
            Logger::error('Tool execution failed', [
                'tool' => $tool,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => [
                    'method' => $request->method(),
                    'headers' => $request->headers->all(),
                    'input' => $request->all()
                ]
            ]);
            
            return response()->json([
                'content' => [
                    [
                        'type' => 'error',
                        'text' => $e->getMessage()
                    ]
                ]
            ], 500);
        }
    }
    
    /**
     * Formata uma resposta de sucesso no padrão JSON-RPC 2.0
     * NOTE: This is used for standard JSON-RPC calls like mcp.execute, not necessarily tools/call from mcp-remote.
     */
    protected function jsonRpcResponse($id, $result)
    {
        // Obter o método da requisição atual
        $method = request()->input('method', 'unknown');
        
        return response()->json([
            'jsonrpc' => '2.0',
            'id' => $id,
            'method' => $method, // Incluir o método na resposta para compatibilidade com o cliente
            'result' => $result // Standard JSON-RPC result
        ]);
    }
    
    /**
     * Formata uma resposta de erro no padrão JSON-RPC 2.0
     */
    protected function jsonRpcError($id, $code, $message, $data = null)
    {
        $response = [
            'jsonrpc' => '2.0',
            'id' => $id,
            'method' => 'tools/call', // Required by MCP client
            'error' => [
                'code' => $code,
                'message' => $message
            ]
        ];
        
        if ($data !== null) {
            $response['error']['data'] = $data;
        }
        
        return response()->json($response);
    }
    
    /**
     * Executa uma ferramenta via chamada tools/call do MCP
     */
    public function executeToolCall(Request $request)
    {
        Logger::info('MCP tools/call request received', [
            'method' => $request->method(),
            'input' => $request->all()
        ]);
        
        try {
            // Validar requisição
            if (!$request->isJson()) {
                Logger::warning('Invalid request format - not JSON');
                return response()->json([
                    'jsonrpc' => '2.0',
                    'id' => $request->input('id', null),
                    'error' => [
                        'code' => -32700,
                        'message' => 'Parse error: Invalid JSON'
                    ]
                ], 400);
            }
            
            // Extrair parâmetros da requisição
            $jsonRpc = $request->input('jsonrpc');
            $id = $request->input('id');
            $method = $request->input('method');
            $params = $request->input('params', []);
            
            Logger::debug('JSON-RPC request', [
                'jsonrpc' => $jsonRpc,
                'id' => $id,
                'method' => $method,
                'params' => $params
            ]);
            
            // Validar versão JSON-RPC
            if ($jsonRpc !== '2.0') {
                return response()->json([
                    'jsonrpc' => '2.0',
                    'id' => $id,
                    'error' => [
                        'code' => -32600,
                        'message' => 'Invalid JSON-RPC version'
                    ]
                ], 400);
            }
            
            // Validar método
            if ($method !== 'tools/call') {
                return response()->json([
                    'jsonrpc' => '2.0',
                    'id' => $id,
                    'error' => [
                        'code' => -32601,
                        'message' => "Method not found: {$method}"
                    ]
                ], 400);
            }
            
            // Extrair nome da ferramenta e argumentos
            $toolName = $params['name'] ?? null;
            $arguments = $params['arguments'] ?? [];
            
            if (!$toolName) {
                Logger::warning('Tool name not provided');
                return response()->json([
                    'jsonrpc' => '2.0',
                    'id' => $id,
                    'error' => [
                        'code' => -32602,
                        'message' => 'Tool name not provided'
                    ]
                ], 400);
            }
            
            Logger::debug('Executing tool', [
                'tool' => $toolName,
                'arguments' => $arguments
            ]);
            
            // Executar a ferramenta
            $result = $this->server->executeTool($toolName, $arguments);
            
            Logger::info('Tool execution successful', [
                'tool' => $toolName,
                'result_type' => gettype($result)
            ]);
            
            // NOVA ABORDAGEM: Formato específico para tools/call
            // Para chamadas tools/call, o cliente espera a resposta no formato:
            // { jsonrpc: "2.0", id: <id>, method: "tools/call", content: [...] }
            
            // Primeiro, vamos verificar se o resultado já tem um formato adequado
            if (is_array($result) && isset($result['content'])) {
                $content = $result['content'];
            } else {
                // Caso contrário, converter para o formato content esperado
                $content = [
                    [
                        'type' => 'text',
                        'text' => is_string($result) ? $result : json_encode($result, JSON_PRETTY_PRINT)
                    ]
                ];
            }
            
            // Formatar a resposta no formato que o cliente espera
            $formattedResponse = [
                'jsonrpc' => '2.0',
                'id' => $id,
                'method' => 'tools/call',
                'content' => $content
            ];
            
            // Log da resposta formatada
            Logger::debug('Formatted response for tools/call', [
                'response' => $formattedResponse
            ]);
            
            return response()->json($formattedResponse);
            
        } catch (\Exception $e) {
            Logger::error('Tool execution failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'jsonrpc' => '2.0',
                'id' => $request->input('id'),
                'method' => 'tools/call',
                'error' => [
                    'code' => -32603,
                    'message' => 'Internal error',
                    'data' => $e->getMessage()
                ]
            ], 500);
        }
    }
} 