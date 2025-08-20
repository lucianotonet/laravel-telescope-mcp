<?php

namespace LucianoTonet\TelescopeMcp\MCP\Tools;

use Laravel\Telescope\Contracts\EntriesRepository;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\Storage\EntryQueryOptions;
use LucianoTonet\TelescopeMcp\Support\Logger;
use LucianoTonet\TelescopeMcp\Support\DateFormatter;

class EventsTool extends AbstractTool
{
    /**
     * Retorna o nome curto da ferramenta
     */
    public function getShortName(): string
    {
        return 'events';
    }

    /**
     * Retorna o esquema da ferramenta
     */
    public function getSchema(): array
    {
        return [
            'name' => $this->getName(),
            'description' => 'Lista e analisa eventos registrados pelo Telescope',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'id' => [
                        'type' => 'string',
                        'description' => 'ID do evento específico para ver detalhes'
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Número máximo de eventos a retornar',
                        'default' => 50
                    ],
                    'name' => [
                        'type' => 'string',
                        'description' => 'Filtrar por nome do evento'
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

            // Verificar se foi solicitado detalhes de um evento específico
            if ($this->hasId($params)) {
                return $this->getEventDetails($params['id']);
            }
            
            return $this->listEvents($params);
        } catch (\Exception $e) {
            Logger::error($this->getName() . ' execution error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->formatError('Error: ' . $e->getMessage());
        }
    }

    /**
     * Lista os eventos registrados pelo Telescope
     */
    protected function listEvents(array $params): array
    {
        // Definir limite para a consulta
        $limit = isset($params['limit']) ? min((int)$params['limit'], 100) : 50;
        
        // Configurar opções
        $options = new EntryQueryOptions();
        $options->limit($limit);
        
        // Adicionar filtro por nome se especificado
        if (!empty($params['name'])) {
            $options->tag($params['name']);
        }
        
        // Buscar entradas usando o repositório
        $entries = $this->entriesRepository->get(EntryType::EVENT, $options);
        
        if (empty($entries)) {
            return $this->formatResponse("Nenhum evento encontrado.");
        }
        
        $events = [];
        
        foreach ($entries as $entry) {
            $content = is_array($entry->content) ? $entry->content : [];
            
            // Format the date using DateFormatter
            $createdAt = DateFormatter::format($entry->createdAt);
            
            $events[] = [
                'id' => $entry->id,
                'name' => $content['name'] ?? 'Unknown',
                'listeners' => isset($content['listeners']) ? count($content['listeners']) : 0,
                'created_at' => $createdAt
            ];
        }
        
        // Formatação tabular para facilitar a leitura
        $table = "Events:\n\n";
        $table .= sprintf("%-5s %-60s %-10s %-20s\n", "ID", "Name", "Listeners", "Created At");
        $table .= str_repeat("-", 100) . "\n";
        
        foreach ($events as $event) {
            // Truncar nome longo
            $name = $event['name'];
            $name = $this->safeString($name);
            if (strlen($name) > 60) {
                $name = substr($name, 0, 57) . "...";
            }
            
            $table .= sprintf(
                "%-5s %-60s %-10s %-20s\n",
                $event['id'],
                $name,
                $event['listeners'],
                $event['created_at']
            );
        }
        
        return $this->formatResponse($table);
    }

    /**
     * Obtém detalhes de um evento específico
     */
    protected function getEventDetails(string $id): array
    {
        Logger::info($this->getName() . ' getting details', ['id' => $id]);
        
        // Buscar a entrada específica
        $entry = $this->getEntryDetails(EntryType::EVENT, $id);
        
        if (!$entry) {
            return $this->formatError("Evento não encontrado: {$id}");
        }
        
        $content = is_array($entry->content) ? $entry->content : [];
        
        // Format the date using DateFormatter
        $createdAt = DateFormatter::format($entry->createdAt);
        
        // Detailed formatting of the event
        $output = "Event Details:\n\n";
        $output .= "ID: {$entry->id}\n";
        $output .= "Name: " . ($content['name'] ?? 'Unknown') . "\n";
        $output .= "Created At: {$createdAt}\n\n";
        
        // Payload do evento
        if (isset($content['payload']) && !empty($content['payload'])) {
            $output .= "Payload:\n" . json_encode($content['payload'], JSON_PRETTY_PRINT) . "\n\n";
        }
        
        // Listeners
        if (isset($content['listeners']) && !empty($content['listeners'])) {
            $output .= "Listeners:\n";
            foreach ($content['listeners'] as $listener) {
                $output .= "- " . $listener . "\n";
            }
        }
        
        return $this->formatResponse($output);
    }
} 