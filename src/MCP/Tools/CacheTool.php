<?php

namespace LucianoTonet\TelescopeMcp\MCP\Tools;

use Laravel\Telescope\Contracts\EntriesRepository;
use LucianoTonet\TelescopeMcp\Support\Logger;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\Storage\EntryQueryOptions;

class CacheTool extends AbstractTool
{
    protected $entriesRepository;

    public function __construct(EntriesRepository $entriesRepository)
    {
        $this->entriesRepository = $entriesRepository;
    }

    public function getName()
    {
        return $this->getShortName();
    }

    /**
     * Retorna o nome curto da ferramenta
     */
    public function getShortName()
    {
        return 'cache';
    }

    /**
     * Retorna o esquema da ferramenta
     */
    public function getSchema()
    {
        return [
            'name' => $this->getName(),
            'description' => 'MCP Telescope Cache Tool - Em desenvolvimento. Esta ferramenta irá interagir com as operações de cache registradas pelo Telescope.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'id' => [
                        'type' => 'string',
                        'description' => 'ID da operação de cache específica para ver detalhes'
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Número máximo de operações de cache a retornar',
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
                // Usage examples will be added in the future
            ]
        ];
    }

    public function execute($params)
    {
        Logger::info($this->getName() . ' execute method called', ['params' => $params]);

        try {
            // Verificar se foi solicitado detalhes de uma operação específica
            if ($this->hasId($params)) {
                return $this->getCacheDetails($params['id']);
            }

            return $this->listCacheOperations($params);
        } catch (\Exception $e) {
            Logger::error($this->getName() . ' execution error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->formatError('Error: ' . $e->getMessage());
        }
    }

    /**
     * Lista as operações de cache registradas pelo Telescope
     */
    protected function listCacheOperations($params)
    {
        // Definir limite para a consulta
        $limit = isset($params['limit']) ? min((int)$params['limit'], 100) : 50;

        // Configurar opções
        $options = new EntryQueryOptions();
        $options->limit($limit);

        // TODO: Adicionar filtros específicos para cache se necessário (ex: key, action)

        // Buscar entradas usando o repositório
        $entries = $this->entriesRepository->get(EntryType::CACHE, $options);

        if (empty($entries)) {
            return $this->formatResponse("Nenhuma operação de cache encontrada.");
        }

        $cacheOperations = [];

        foreach ($entries as $entry) {
            $content = is_array($entry->content) ? $entry->content : [];

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
                        \LucianoTonet\TelescopeMcp\Support\Logger::warning('Failed to parse date in CacheTool::listCacheOperations', [
                            'date_string' => $entry->created_at,
                            'entry_id' => $entry->id ?? 'N/A',
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }

            // TODO: Extrair informações relevantes da operação de cache
            $action = $content['action'] ?? 'Unknown'; // put, get, increment, decrement, forever, forget, missing
            $key = $content['key'] ?? 'Unknown';
            $value = $content['value'] ?? null; // Pode ser grande, talvez mostrar apenas tipo ou truncar

            $cacheOperations[] = [
                'id' => $entry->id,
                'action' => $action,
                'key' => $key,
                // 'value' => $value, // Decidir como exibir o valor
                'created_at' => $createdAt
            ];
        }

        // Formatação tabular para facilitar a leitura
        $table = "Cache Operations:\n\n";
        $table .= sprintf("%-5s %-10s %-60s %-20s\n", "ID", "Action", "Key", "Created At");
        $table .= str_repeat("-", 100) . "\n";

        foreach ($cacheOperations as $op) {
            // Truncar a chave se muito longa
            $key = $op['key'];
            if (strlen($key) > 60) {
                $key = substr($key, 0, 57) . "...";
            }

            $table .= sprintf(
                "%-5s %-10s %-60s %-20s\n",
                $op['id'],
                $op['action'],
                $key,
                $op['created_at']
            );
        }

        return $this->formatResponse($table);
    }

    /**
     * Obtém detalhes de uma operação de cache específica
     */
    protected function getCacheDetails($id)
    {
        Logger::info($this->getName() . ' getting details', ['id' => $id]);

        // Buscar a entrada específica
        $entry = $this->getEntryDetails(EntryType::CACHE, $id);

        if (!$entry) {
            return $this->formatError("Operação de cache não encontrada: {$id}");
        }

        $content = is_array($entry->content) ? $entry->content : [];

        // Formatação detalhada da operação de cache
        $output = "Cache Operation Details:\n\n";
        $output .= "ID: {$entry->id}\n";
        $output .= "Action: " . ($content['action'] ?? 'Unknown') . "\n";
        $output .= "Key: " . ($content['key'] ?? 'Unknown') . "\n";

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
                    \LucianoTonet\TelescopeMcp\Support\Logger::warning('Failed to parse date in CacheTool::getCacheDetails', [
                        'date_string' => $entry->created_at,
                        'entry_id' => $entry->id ?? 'N/A',
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }
        $output .= "Created At: {$createdAt}\n\n";

        // Valor (decidir como exibir, pode ser grande)
        if (isset($content['value'])) {
            $output .= "Value:\n" . json_encode($content['value'], JSON_PRETTY_PRINT) . "\n";
        }

        // TODO: Adicionar outros detalhes relevantes se existirem

        return $this->formatResponse($output);
    }

    /**
     * Verifica se um ID foi fornecido nos parâmetros
     *
     * @param array $params
     * @return bool
     */
    protected function hasId($params)
    {
        return isset($params['id']) && !empty($params['id']);
    }

    /**
     * Obtém os detalhes de uma entrada específica do Telescope
     *
     * @param string $entryType
     * @param string $id
     * @return mixed
     */
    protected function getEntryDetails($entryType, $id)
    {
        Logger::debug("Getting details for {$entryType} entry", ['id' => $id]);

        try {
            return $this->entriesRepository->find($id);
        } catch (\Exception $e) {
            Logger::error("Failed to get entry details", [
                'id' => $id,
                'entryType' => $entryType,
                'error' => $e->getMessage()
            ]);

            throw new \Exception("Entry not found: {$id}");
        }
    }

    /**
     * Formata uma resposta para o MCP
     *
     * @param mixed $data
     * @param string $type
     * @return array
     */
    protected function formatResponse($data, $type = 'text')
    {
        // Se já for um array formatado com 'content', retorne-o diretamente
        if (is_array($data) && isset($data['content'])) {
            return $data;
        }

        // Converte para string se necessário
        $text = is_string($data) ? $data : json_encode($data, JSON_PRETTY_PRINT);

        // Retorna no formato esperado pelo MCP
        return [
            'content' => [
                [
                    'type' => $type,
                    'text' => $text
                ]
            ]
        ];
    }

    /**
     * Formata uma resposta de erro para o MCP
     *
     * @param string $message
     * @return array
     */
    protected function formatError($message)
    {
        return $this->formatResponse($message, 'error');
    }
} 