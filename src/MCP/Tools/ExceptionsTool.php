<?php

namespace LucianoTonet\TelescopeMcp\MCP\Tools;

use Laravel\Telescope\Contracts\EntriesRepository;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\Storage\EntryQueryOptions;
use LucianoTonet\TelescopeMcp\Support\Logger;

class ExceptionsTool extends AbstractTool
{
    /**
     * Retorna o nome curto da ferramenta
     */
    public function getShortName(): string
    {
        return 'exceptions';
    }

    /**
     * Retorna o esquema da ferramenta
     */
    public function getSchema(): array
    {
        return [
            'name' => $this->getName(),
            'description' => 'Exibe exceções registradas pelo Telescope, permitindo visualizar detalhes completos de erros da aplicação.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'id' => [
                        'type' => 'string',
                        'description' => 'ID da exceção específica para ver detalhes'
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Número máximo de exceções a retornar',
                        'default' => 50
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
                    'description' => 'Listar as últimas 10 exceções',
                    'params' => [
                        'limit' => 10
                    ]
                ],
                [
                    'description' => 'Ver detalhes de uma exceção específica',
                    'params' => [
                        'id' => '123456'
                    ]
                ]
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

            // Verificar se foi solicitado detalhes de uma exceção específica
            if ($this->hasId($params)) {
                return $this->getExceptionDetails($params['id']);
            }
            
            return $this->listExceptions($params);
        } catch (\Exception $e) {
            Logger::error($this->getName() . ' execution error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->formatError('Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Lista as exceções registradas pelo Telescope
     */
    protected function listExceptions($params)
    {
        // Definir limite para a consulta
        $limit = isset($params['limit']) ? min((int)$params['limit'], 100) : 50;
        
        // Configurar opções
        $options = new EntryQueryOptions();
        $options->limit($limit);
        
        // Buscar entradas usando o repositório
        $entries = $this->entriesRepository->get(EntryType::EXCEPTION, $options);
        
        if (empty($entries)) {
            return $this->formatResponse("Nenhuma exceção encontrada.");
        }
        
        $exceptions = [];
        
        foreach ($entries as $entry) {
            $content = is_array($entry->content) ? $entry->content : [];
            
            $className = isset($content['class']) ? $content['class'] : 'Unknown';
            $message = isset($content['message']) ? $content['message'] : 'No message';
            $file = isset($content['file']) ? $content['file'] : 'Unknown';
            $line = isset($content['line']) ? $content['line'] : 'Unknown';
            
            $createdAt = 'Unknown';
            if (property_exists($entry, 'created_at') && !empty($entry->created_at)) {
                if (is_object($entry->created_at) && method_exists($entry->created_at, 'format')) {
                    $createdAt = $entry->created_at->format('Y-m-d H:i:s');
                } elseif (is_string($entry->created_at)) {
                    try {
                        if (trim($entry->created_at) !== '') {
                            $dateTime = new \DateTime($entry->created_at);
                            $createdAt = $dateTime->format('Y-m-d H:i:s');
                        }
                    } catch (\Exception $e) {
                        \LucianoTonet\TelescopeMcp\Support\Logger::warning('Failed to parse date in ExceptionsTool::listExceptions', [
                            'date_string' => $entry->created_at,
                            'entry_id' => $entry->id ?? 'N/A',
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }
            
            $exceptions[] = [
                'id' => $entry->id,
                'class' => $className,
                'message' => $message,
                'file' => $file,
                'line' => $line,
                'occurred_at' => $createdAt
            ];
        }
        
        // Formatação tabular para facilitar a leitura
        $table = "Application Exceptions:\n\n";
        $table .= sprintf("%-5s %-30s %-40s %-20s\n", "ID", "Exception", "Message", "Occurred At");
        $table .= str_repeat("-", 100) . "\n";
        
        foreach ($exceptions as $exception) {
            // Truncar mensagem longa
            $message = $exception['message'];
            if (strlen($message) > 40) {
                $message = substr($message, 0, 37) . "...";
            }
            
            // Obter apenas o nome da classe sem namespace
            $className = $exception['class'];
            if (strpos($className, '\\') !== false) {
                $parts = explode('\\', $className);
                $className = end($parts);
            }
            
            $table .= sprintf(
                "%-5s %-30s %-40s %-20s\n",
                $exception['id'],
                substr($className, 0, 30),
                $message,
                $exception['occurred_at']
            );
        }
        
        return $this->formatResponse($table);
    }
    
    /**
     * Obtém detalhes de uma exceção específica
     */
    protected function getExceptionDetails($id)
    {
        Logger::info($this->getName() . ' getting details', ['id' => $id]);
        
        // Buscar a entrada específica
        $entry = $this->getEntryDetails(EntryType::EXCEPTION, $id);
        
        if (!$entry) {
            return $this->formatError("Exceção não encontrada: {$id}");
        }
        
        $content = is_array($entry->content) ? $entry->content : [];
        
        // Formatação detalhada da exceção
        $output = "Exception Details:\n\n";
        $output .= "ID: {$entry->id}\n";
        $output .= "Type: " . (isset($content['class']) ? $content['class'] : 'Unknown') . "\n";
        $output .= "Message: " . (isset($content['message']) ? $content['message'] : 'No message') . "\n";
        $output .= "File: " . (isset($content['file']) ? $content['file'] : 'Unknown') . "\n";
        $output .= "Line: " . (isset($content['line']) ? $content['line'] : 'Unknown') . "\n";
        
        $createdAt = 'Unknown';
        if (property_exists($entry, 'created_at') && !empty($entry->created_at)) {
            if (is_object($entry->created_at) && method_exists($entry->created_at, 'format')) {
                $createdAt = $entry->created_at->format('Y-m-d H:i:s');
            } elseif (is_string($entry->created_at)) {
                try {
                    if (trim($entry->created_at) !== '') {
                        $dateTime = new \DateTime($entry->created_at);
                        $createdAt = $dateTime->format('Y-m-d H:i:s');
                    }
                } catch (\Exception $e) {
                    \LucianoTonet\TelescopeMcp\Support\Logger::warning('Failed to parse date in ExceptionsTool::getExceptionDetails', [
                        'date_string' => $entry->created_at,
                        'entry_id' => $entry->id ?? 'N/A',
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }
        $output .= "Occurred At: {$createdAt}\n\n";
        
        // Stack Trace
        if (isset($content['trace']) && is_array($content['trace'])) {
            $output .= "Stack Trace:\n";
            
            foreach ($content['trace'] as $index => $frame) {
                $file = isset($frame['file']) ? $frame['file'] : 'Unknown';
                $line = isset($frame['line']) ? $frame['line'] : 'Unknown';
                $function = isset($frame['function']) ? $frame['function'] : 'Unknown';
                $class = isset($frame['class']) ? $frame['class'] : '';
                $type = isset($frame['type']) ? $frame['type'] : '';
                
                $output .= sprintf(
                    "#%d %s%s%s() at %s:%s\n",
                    $index,
                    $class,
                    $type,
                    $function,
                    $file,
                    $line
                );
            }
        }
        
        // Context se disponível
        if (isset($content['context']) && is_array($content['context'])) {
            $output .= "\nContext:\n";
            $output .= json_encode($content['context'], JSON_PRETTY_PRINT);
        }
        
        return $this->formatResponse($output);
    }
} 