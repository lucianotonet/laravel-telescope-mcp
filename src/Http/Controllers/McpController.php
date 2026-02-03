<?php

namespace LucianoTonet\TelescopeMcp\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use LucianoTonet\TelescopeMcp\MCP\TelescopeMcpServer;
use LucianoTonet\TelescopeMcp\Support\JsonRpcResponse;
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
            'uri' => $request->fullUrl(),
            'input_all' => $request->all(), // Be careful with large inputs
            'content_type' => $request->header('Content-Type'),
            'raw_content_preview' => substr($request->getContent(), 0, 200)
        ]);
        
        // Handle GET requests for manifest
        if ($request->method() === 'GET') {
            $manifest = $this->server->getManifest();
            return response()->json(JsonRpcResponse::success([
                'protocolVersion' => '2024-11-05',
                'serverInfo' => [
                    'name' => $manifest['name'],
                    'version' => $manifest['version'],
                    'description' => $manifest['description']
                ],
                'capabilities' => [
                    'tools' => $manifest['tools']
                ]
            ]));
        }
        
        // For POST requests, expect JSON-RPC
        if (!$request->isJson()) {
            Logger::warning('MCP request: Content-Type is not JSON.', ['content_type' => $request->header('Content-Type')]);
            return response()->json(
                JsonRpcResponse::error(JsonRpcResponse::PARSE_ERROR, 'Parse error: Content-Type must be application/json.', null, null),
                400
            );
        }

        $rawContent = $request->getContent();
        if (empty($rawContent)) {
            Logger::warning('MCP request: Empty JSON body.');
            return response()->json(
                JsonRpcResponse::error(JsonRpcResponse::INVALID_REQUEST, 'Invalid Request: Empty JSON body.', null, null),
                400
            );
        }

        $decoded = json_decode($rawContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Logger::warning('MCP request: JSON parse error.', ['json_error' => json_last_error_msg(), 'raw_content' => $rawContent]);
            return response()->json(
                JsonRpcResponse::error(JsonRpcResponse::PARSE_ERROR, 'Parse error: Invalid JSON in request body. Error: ' . json_last_error_msg(), null, null),
                400
            );
        }

        if (!is_array($decoded)) {
            Logger::warning('MCP request: Decoded JSON is not an array/object.', ['decoded_type' => gettype($decoded)]);
             return response()->json(
                JsonRpcResponse::error(JsonRpcResponse::INVALID_REQUEST, 'Invalid Request: JSON body must be an object.', null, null),
                400
            );
        }

        $jsonrpc = $decoded['jsonrpc'] ?? null;
        $id = $decoded['id'] ?? null; // ID can be string, number, or null. It's fine if it's not present for notifications.
        $method = $decoded['method'] ?? null;
        $params = $decoded['params'] ?? []; // Params should be an array or object, default to array.

        if ($jsonrpc !== '2.0') {
            Logger::warning('MCP request: Invalid JSON-RPC version.', ['version_received' => $jsonrpc]);
            return response()->json(
                JsonRpcResponse::error(JsonRpcResponse::INVALID_REQUEST, 'Invalid JSON-RPC version. Must be \'2.0\'.', null, $id),
                400
            );
        }

        if (empty($method) || !is_string($method)) {
            Logger::warning('MCP request: Missing or invalid method.', ['method_received' => $method]);
            return response()->json(
                JsonRpcResponse::error(JsonRpcResponse::INVALID_REQUEST, 'Invalid Request: Method is missing or not a string.', null, $id),
                400
            );
        }
        
        Logger::info('JSON-RPC request processing', [
            'jsonrpc_version' => $jsonrpc,
            'id' => $id,
            'method' => $method,
            'params_type' => gettype($params)
            // 'params_preview' => is_array($params) ? array_slice($params, 0, 5) : $params // Avoid logging too much
        ]);
        
        switch ($method) {
            case 'mcp.manifest':
            case 'mcp.getManifest':
            case 'initialize':
                $manifest = $this->server->getManifest();
                return response()->json(JsonRpcResponse::success([
                    'protocolVersion' => '2024-11-05',
                    'serverInfo' => [
                        'name' => $manifest['name'],
                        'version' => $manifest['version'],
                        'description' => $manifest['description']
                    ],
                    'capabilities' => [
                        'tools' => $manifest['tools']
                    ]
                ], $id));
            
            case 'tools/list':
                $manifest = $this->server->getManifest();
                return response()->json(JsonRpcResponse::success([
                    'tools' => array_values((array) $manifest['tools'])
                ], $id));
            
            case 'tools/call':
                $shortToolName = $params['name'] ?? null;
                $arguments = $params['arguments'] ?? [];

                if (!is_array($params)) {
                     Logger::warning('MCP tools/call: params is not an object/array.', ['params_received' => $params]);
                     return response()->json(
                        JsonRpcResponse::error(JsonRpcResponse::INVALID_PARAMS, 'Invalid params: Must be an object.', null, $id),
                        400
                    );
                }
                
                if (!$shortToolName || !is_string($shortToolName)) {
                    Logger::warning('MCP tools/call: Missing or invalid tool name.', ['tool_name_received' => $shortToolName]);
                    return response()->json(
                        JsonRpcResponse::error(JsonRpcResponse::INVALID_PARAMS, 'Invalid params: tool name is required and must be a string.', null, $id),
                        400
                    );
                }

                if (!is_array($arguments) && !is_object($arguments)) {
                     Logger::warning('MCP tools/call: Invalid arguments type.', ['arguments_type' => gettype($arguments)]);
                     return response()->json(
                        JsonRpcResponse::error(JsonRpcResponse::INVALID_PARAMS, 'Invalid params: arguments must be an array or object.', null, $id),
                        400
                    );
                }

                $toolName = $shortToolName;
                Logger::info('Executing tool via JSON-RPC', [
                    'tool_name' => $toolName,
                ]);
                
                try {
                    $result = $this->server->executeTool($toolName, (array) $arguments);
                    return response()->json(JsonRpcResponse::mcpToolResponse($result, $id));
                } catch (\Exception $e) {
                    Logger::error('MCP tools/call: Tool execution error.', ['tool_name' => $toolName, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
                    
                    $errorMessage = $e->getMessage();
                    // A mensagem de erro já virá com o nome curto da ferramenta de TelescopeMcpServer
                    // if (strpos($errorMessage, 'Tool not found:') === 0) {
                    //     $errorMessage = "Tool not found: {$toolName}";
                    // }

                    return response()->json(
                        JsonRpcResponse::error(JsonRpcResponse::INTERNAL_ERROR, $errorMessage, null, $id),
                        500
                    );
                }
            
            case 'notifications/initialized':
                // If it's a notification (no ID), do not return anything (HTTP 204 or 200 empty implicitly)
                // If it has an ID (which it shouldn't for this method), treat as unknown.
                if (is_null($id)) {
                    Logger::info('MCP notification: Client initialized.', ['method_received' => $method]);
                    // For notifications, the server should not return a JSON-RPC response.
                    // Returning an empty HTTP response with status 204 is appropriate.
                    return response(null, 204); 
                }
                // If it reached here with an ID, it will fall through to the default (method not found)

            default:
                // If it's 'notifications/initialized' with an ID, or any other unknown method
                if ($method === 'notifications/initialized' && !is_null($id)) {
                     Logger::warning('MCP request: notifications/initialized received with an ID, treating as method not found.', ['method_received' => $method, 'id' => $id]);
                } else {
                    Logger::warning('MCP request: Method not found.', ['method_received' => $method, 'id' => $id]);
                }
                return response()->json(
                    JsonRpcResponse::error(JsonRpcResponse::METHOD_NOT_FOUND, "Method not found: {$method}", null, $id),
                    400
                );
        }
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
            
            // $tool já é o nome curto da URL.
            $toolName = $tool;
            Logger::info('Direct tool execution via /tools/{tool} route', [
                'tool_name' => $toolName,
                'params' => $params
            ]);

            $result = $this->server->executeTool($toolName, $params);
            
            // Formatar resposta no padrão esperado pelo MCP
            $response = [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => json_encode($result)
                    ]
                ]
            ];
            
            Logger::info('Tool execution successful (direct route)', [
                'tool' => $toolName,
                'response' => $response
            ]);
            
            // Retornar resposta
            return response()->json($response);
            
        } catch (\Exception $e) {
            Logger::error('Tool execution failed (direct route)', [
                'tool' => $tool, // Log o nome curto aqui, pois é o da URL
                'full_tool_name_attempted' => $tool, // Agora $tool já é o nome correto
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
        $error = [
            'code' => $code,
            'message' => $message
        ];
        
        if ($data !== null) {
            $error['data'] = $data;
        }
        
        return response()->json([
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => $error
        ], JsonRpcResponse::httpStatusCode($code)); // Usar helper para status code HTTP
    }
    
    public function executeToolCall(Request $request)
    {
        Logger::info('MCP executeToolCall received', [
            'method' => $request->method(),
            'uri' => $request->fullUrl(),
            'input_all' => $request->all(),
            'content_type' => $request->header('Content-Type'),
            'raw_content_preview' => substr($request->getContent(), 0, 200)
        ]);

        if (!$request->isJson()) {
            Logger::warning('executeToolCall: Content-Type is not JSON.', ['content_type' => $request->header('Content-Type')]);
            return response()->json(
                JsonRpcResponse::error(JsonRpcResponse::PARSE_ERROR, 'Parse error: Content-Type must be application/json.', null, null),
                400
            );
        }

        $rawContent = $request->getContent();
        if (empty($rawContent)) {
            Logger::warning('executeToolCall: Empty JSON body.');
             return response()->json(
                JsonRpcResponse::error(JsonRpcResponse::INVALID_REQUEST, 'Invalid Request: Empty JSON body.', null, null),
                400
            );
        }

        $decoded = json_decode($rawContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Logger::warning('executeToolCall: JSON parse error.', ['json_error' => json_last_error_msg(), 'raw_content' => $rawContent]);
            return response()->json(
                JsonRpcResponse::error(JsonRpcResponse::PARSE_ERROR, 'Parse error: Invalid JSON in request body. Error: ' . json_last_error_msg(), null, null),
                400
            );
        }
        
        if (!is_array($decoded)) {
            Logger::warning('executeToolCall: Decoded JSON is not an array/object.', ['decoded_type' => gettype($decoded)]);
             return response()->json(
                JsonRpcResponse::error(JsonRpcResponse::INVALID_REQUEST, 'Invalid Request: JSON body must be an object.', null, null),
                400
            );
        }

        $jsonrpc = $decoded['jsonrpc'] ?? null;
        $id = $decoded['id'] ?? null;
        $params = $decoded['params'] ?? [];

        if ($jsonrpc !== '2.0') {
            Logger::warning('executeToolCall: Invalid JSON-RPC version.', ['version_received' => $jsonrpc]);
            return response()->json(
                JsonRpcResponse::error(JsonRpcResponse::INVALID_REQUEST, 'Invalid JSON-RPC version. Must be \'2.0\'.', null, $id),
                400
            );
        }

        $shortToolName = $params['name'] ?? null;
        $arguments = $params['arguments'] ?? [];

        if (!is_array($params)) {
             Logger::warning('MCP executeToolCall: params is not an object/array.', ['params_received' => $params]);
             return response()->json(
                JsonRpcResponse::error(JsonRpcResponse::INVALID_PARAMS, 'Invalid params: Must be an object.', null, $id),
                400
            );
        }

        if (!$shortToolName || !is_string($shortToolName)) {
            Logger::warning('executeToolCall: Missing or invalid tool name.', ['tool_name_received' => $shortToolName]);
            return response()->json(
                JsonRpcResponse::error(JsonRpcResponse::INVALID_PARAMS, 'Invalid params: tool name is required and must be a string.', null, $id),
                400
            );
        }

        if (!is_array($arguments) && !is_object($arguments)) {
             Logger::warning('MCP executeToolCall: Invalid arguments type.', ['arguments_type' => gettype($arguments)]);
             return response()->json(
                JsonRpcResponse::error(JsonRpcResponse::INVALID_PARAMS, 'Invalid params: arguments must be an array or object.', null, $id),
                400
            );
        }

        $toolName = $shortToolName;
        Logger::info('Executing tool via executeToolCall', [
            'tool_name' => $toolName,
        ]);

        try {
            $result = $this->server->executeTool($toolName, (array) $arguments);
            return response()->json(JsonRpcResponse::mcpToolResponse($result, $id));
        } catch (\Exception $e) {
            Logger::error('executeToolCall: Tool execution error.', ['tool' => $toolName, 'error' => $e->getMessage()]);
            return response()->json(
                JsonRpcResponse::error(JsonRpcResponse::INTERNAL_ERROR, $e->getMessage(), null, $id),
                500
            );
        }
    }
}
