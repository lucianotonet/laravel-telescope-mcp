<?php

namespace LucianoTonet\TelescopeMcp\MCP\Tools;

use Laravel\Telescope\Contracts\EntriesRepository;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\Storage\EntryQueryOptions;
use LucianoTonet\TelescopeMcp\Support\Logger;
use LucianoTonet\TelescopeMcp\Support\DateFormatter;

class HttpClientTool extends AbstractTool
{
    /**
     * Retorna o nome curto da ferramenta
     */
    public function getShortName(): string
    {
        return 'http-client';
    }

    /**
     * Retorna o esquema da ferramenta
     */
    public function getSchema(): array
    {
        return [
            'name' => $this->getName(),
            'description' => 'Lista e analisa requisições HTTP feitas pelo cliente HTTP do Laravel',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'id' => [
                        'type' => 'string',
                        'description' => 'ID da requisição específica para ver detalhes'
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Número máximo de requisições a retornar',
                        'default' => 50
                    ],
                    'method' => [
                        'type' => 'string',
                        'description' => 'Filtrar por método HTTP (GET, POST, etc)',
                        'enum' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS']
                    ],
                    'status' => [
                        'type' => 'integer',
                        'description' => 'Filtrar por código de status HTTP'
                    ],
                    'url' => [
                        'type' => 'string',
                        'description' => 'Filtrar por URL (busca parcial)'
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

    /**
     * Executa a ferramenta com os parâmetros fornecidos
     */
    public function execute(array $params): array
    {
        try {
            Logger::info($this->getName() . ' execute method called', ['params' => $params]);

            // Verificar se foi solicitado detalhes de uma requisição específica
            if ($this->hasId($params)) {
                return $this->getRequestDetails($params['id']);
            }
            
            return $this->listRequests($params);
        } catch (\Exception $e) {
            Logger::error($this->getName() . ' execution error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->formatError('Error: ' . $e->getMessage());
        }
    }

    /**
     * Lista as requisições HTTP registradas pelo Telescope
     */
    protected function listRequests(array $params): array
    {
        // Definir limite para a consulta
        $limit = isset($params['limit']) ? min((int)$params['limit'], 100) : 50;
        
        // Configurar opções
        $options = new EntryQueryOptions();
        $options->limit($limit);
        
        // Adicionar filtros se especificados
        if (!empty($params['method'])) {
            $options->tag($params['method']);
        }
        if (!empty($params['status'])) {
            $options->tag((string)$params['status']);
        }
        if (!empty($params['url'])) {
            $options->tag($params['url']);
        }
        
        // Buscar entradas usando o repositório
        $entries = $this->entriesRepository->get(EntryType::HTTP_CLIENT_REQUEST, $options);
        
        if (empty($entries)) {
            return $this->formatResponse("Nenhuma requisição HTTP encontrada.");
        }
        
        $requests = [];
        
        foreach ($entries as $entry) {
            $content = is_array($entry->content) ? $entry->content : [];
            
            // Format the date using DateFormatter
            $createdAt = DateFormatter::format($entry->createdAt);
            
            $requests[] = [
                'id' => $entry->id,
                'method' => $content['method'] ?? 'Unknown',
                'url' => $content['uri'] ?? 'Unknown',
                'status' => $content['response_status'] ?? 0,
                'duration' => isset($content['duration']) ? round($content['duration'] / 1000, 2) : 0,
                'created_at' => $createdAt
            ];
        }
        
        // Formatação tabular para facilitar a leitura
        $table = "HTTP Client Requests:\n\n";
        $table .= sprintf("%-5s %-6s %-50s %-7s %-8s %-20s\n", "ID", "Method", "URL", "Status", "Time(s)", "Created At");
        $table .= str_repeat("-", 100) . "\n";
        
        foreach ($requests as $request) {
            // Truncar URL longa
            $url = $request['url'];
            if (strlen($url) > 50) {
                $url = substr($url, 0, 47) . "...";
            }
            
            $table .= sprintf(
                "%-5s %-6s %-50s %-7s %-8s %-20s\n",
                $request['id'],
                $request['method'],
                $url,
                $request['status'],
                $request['duration'],
                $request['created_at']
            );
        }
        
        return $this->formatResponse($table);
    }

    /**
     * Obtém detalhes de uma requisição HTTP específica
     */
    protected function getRequestDetails(string $id): array
    {
        Logger::info($this->getName() . ' getting details', ['id' => $id]);
        
        // Buscar a entrada específica
        $entry = $this->getEntryDetails(EntryType::HTTP_CLIENT_REQUEST, $id);
        
        if (!$entry) {
            return $this->formatError("Requisição não encontrada: {$id}");
        }
        
        $content = is_array($entry->content) ? $entry->content : [];
        
        // Format the date using DateFormatter
        $createdAt = DateFormatter::format($entry->createdAt);
        
        // Detailed formatting of the request
        $output = "HTTP Client Request Details:\n\n";
        $output .= "ID: {$entry->id}\n";
        $output .= "Method: " . ($content['method'] ?? 'Unknown') . "\n";
        $output .= "URL: " . ($content['uri'] ?? 'Unknown') . "\n";
        $output .= "Status: " . ($content['response_status'] ?? 0) . "\n";
        $output .= "Duration: " . (isset($content['duration']) ? round($content['duration'] / 1000, 2) . "s" : 'Unknown') . "\n";
        $output .= "Created At: {$createdAt}\n\n";
        
        // Headers da requisição
        if (isset($content['headers']) && !empty($content['headers'])) {
            $output .= "Request Headers:\n";
            foreach ($content['headers'] as $name => $values) {
                $output .= "{$name}: " . implode(', ', (array)$values) . "\n";
            }
            $output .= "\n";
        }
        
        // Body da requisição
        if (isset($content['payload']) && !empty($content['payload'])) {
            $output .= "Request Body:\n" . json_encode($content['payload'], JSON_PRETTY_PRINT) . "\n\n";
        }
        
        // Headers da resposta
        if (isset($content['response_headers']) && !empty($content['response_headers'])) {
            $output .= "Response Headers:\n";
            foreach ($content['response_headers'] as $name => $values) {
                $output .= "{$name}: " . implode(', ', (array)$values) . "\n";
            }
            $output .= "\n";
        }
        
        // Body da resposta
        if (isset($content['response']) && !empty($content['response'])) {
            $output .= "Response Body:\n" . json_encode($content['response'], JSON_PRETTY_PRINT) . "\n";
        }
        
        return $this->formatResponse($output);
    }
} 