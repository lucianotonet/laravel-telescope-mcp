<?php

namespace LucianoTonet\TelescopeMcp\Support;

/**
 * Class to standardize JSON-RPC 2.0 responses
 * @see https://www.jsonrpc.org/specification
 */
class JsonRpcResponse
{
    // Standard JSON-RPC error codes
    public const PARSE_ERROR = -32700;
    public const INVALID_REQUEST = -32600;
    public const METHOD_NOT_FOUND = -32601;
    public const INVALID_PARAMS = -32602;
    public const INTERNAL_ERROR = -32603;
    
    /**
     * Creates a successful JSON-RPC 2.0 response
     *
     * @param mixed $result The result value
     * @param string|int|null $id The request id
     * @return array
     */
    public static function success($result, $id = null): array
    {
        return [
            'jsonrpc' => '2.0',
            'result' => $result,
            'id' => $id
        ];
    }
    
    /**
     * Creates an error JSON-RPC 2.0 response
     *
     * @param int $code The error code
     * @param string $message The error message
     * @param mixed|null $data Additional error data
     * @param string|int|null $id The request id
     * @return array
     */
    public static function error(int $code, string $message, $data = null, $id = null): array
    {
        $response = [
            'jsonrpc' => '2.0',
            'error' => [
                'code' => $code,
                'message' => $message
            ],
            'id' => $id
        ];
        
        if ($data !== null) {
            $response['error']['data'] = $data;
        }
        
        return $response;
    }
    
    /**
     * Formats content for MCP tools response
     *
     * @param mixed $content The content to format
     * @param string|int|null $id The request id
     * @return array
     */
    public static function mcpToolResponse($content, $id = null): array
    {
        // If content is already in the correct format, use it directly
        if (is_array($content) && isset($content['content'])) {
            return self::success($content, $id);
        }
        
        // Convert to the expected MCP format
        $formattedContent = [
            'content' => [
                [
                    'type' => 'text',
                    'text' => is_string($content) ? $content : json_encode($content, JSON_PRETTY_PRINT)
                ]
            ]
        ];
        
        return self::success($formattedContent, $id);
    }
} 