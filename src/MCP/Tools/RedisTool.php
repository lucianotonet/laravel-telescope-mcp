<?php

namespace LucianoTonet\TelescopeMcp\MCP\Tools;

use Laravel\Telescope\Contracts\EntriesRepository;
use LucianoTonet\TelescopeMcp\Support\Logger;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\Storage\EntryQueryOptions;

class RedisTool extends AbstractTool
{
    protected $entriesRepository;

    public function __construct(EntriesRepository $entriesRepository)
    {
        $this->entriesRepository = $entriesRepository;
    }

    /**
     * Retorna o nome da ferramenta
     *
     * @return string
     */
    public function getName()
    {
        return $this->getShortName();
    }

    /**
     * Retorna o nome curto da ferramenta
     */
    public function getShortName()
    {
        return 'redis';
    }

    /**
     * Retorna o esquema da ferramenta
     */
    public function getSchema()
    {
        return [
            'name' => $this->getName(),
            'description' => 'Lista e analisa comandos Redis registrados pelo Telescope.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'id' => [
                        'type' => 'string',
                        'description' => 'ID do comando Redis específico para ver detalhes'
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Número máximo de comandos Redis a retornar',
                        'default' => 50
                    ],
                    'command' => [
                        'type' => 'string',
                        'description' => 'Filtrar por nome do comando Redis (ex: GET, SET)'
                    ],
                    'key' => [
                        'type' => 'string',
                        'description' => 'Filtrar por chave Redis (busca parcial)'
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
            // Verificar se foi solicitado detalhes de um comando Redis específico
            if ($this->hasId($params)) {
                return $this->getRedisCommandDetails($params['id']);
            }

            return $this->listRedisCommands($params);
        } catch (\Exception $e) {
            Logger::error($this->getName() . ' execution error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->formatError('Error: ' . $e->getMessage());
        }
    }

    /**
     * Lista os comandos Redis registrados pelo Telescope
     */
    protected function listRedisCommands($params)
    {
        // Definir limite para a consulta
        $limit = isset($params['limit']) ? min((int)$params['limit'], 100) : 50;

        // Configurar opções
        $options = new EntryQueryOptions();
        $options->limit($limit);

        // Adicionar filtros se especificados
        if (!empty($params['command'])) {
            // Telescope armazena o comando como tag no formato 'redis:COMANDO'
            $options->tag('redis:' . strtoupper($params['command']));
        }
        if (!empty($params['key'])) {
            // Telescope armazena a chave como tag no formato 'redis-key:chave'
            $options->tag('redis-key:' . $params['key']);
        }

        // Buscar entradas usando o repositório
        $entries = $this->entriesRepository->get(EntryType::REDIS, $options);

        if (empty($entries)) {
            return $this->formatResponse("Nenhum comando Redis encontrado.");
        }

        $redisCommands = [];

        foreach ($entries as $entry) {
            $content = is_array($entry->content) ? $entry->content : [];

            $createdAt = 'Unknown';
            if (property_exists($entry, 'created_at') && !empty($entry->created_at)) {
                if (is_object($entry->created_at) && method_exists($entry->created_at, 'format')) {
                    $createdAt = $entry->created_at->format('Y-m-d H:i:s');
                } elseif (is_string($entry->created_at)) {
                    $createdAt = $entry->created_at;
                }
            }

            // TODO: Extrair informações relevantes do comando Redis (ex: comando, chave, duração, connection)
            $command = $content['command'] ?? 'Unknown';
            // A chave pode estar em diferentes formatos dependendo do comando
            $key = 'N/A';
            if (isset($content['key'])) {
                 $key = is_array($content['key']) ? json_encode($content['key']) : $content['key'];
            } elseif (isset($content['keys']) && is_array($content['keys'])) {
                 $key = implode(', ', $content['keys']);
            }

            $redisCommands[] = [
                'id' => $entry->id,
                'command' => $command,
                'key' => $key,
                'duration' => $content['time'] ?? 0,
                'connection' => $content['connection'] ?? 'default',
                'created_at' => $createdAt
            ];
        }

        // Formatação tabular para facilitar a leitura
        $table = "Redis Commands:\n\n";
        $table .= sprintf("%-5s %-15s %-40s %-10s %-15s %-20s\n", "ID", "Command", "Key", "Duration", "Connection", "Created At");
        $table .= str_repeat("-", 110) . "\n";

        foreach ($redisCommands as $cmd) {
             // Truncar key se muito longa
            $key = $cmd['key'];
            if (strlen($key) > 40) {
                $key = substr($key, 0, 37) . "...";
            }

            $table .= sprintf(
                "%-5s %-15s %-40s %-10s %-15s %-20s\n",
                $cmd['id'],
                $cmd['command'],
                $key,
                number_format($cmd['duration'], 2) . "ms",
                $cmd['connection'],
                $cmd['created_at']
            );
        }

        return $this->formatResponse($table);
    }

    /**
     * Obtém detalhes de um comando Redis específico
     */
    protected function getRedisCommandDetails($id)
    {
        Logger::info($this->getName() . ' getting details', ['id' => $id]);

        // Buscar a entrada específica
        $entry = $this->getEntryDetails(EntryType::REDIS, $id);

        if (!$entry) {
            return $this->formatError("Comando Redis não encontrado: {$id}");
        }

        $content = is_array($entry->content) ? $entry->content : [];

        // Formatação detalhada do comando Redis
        $output = "Redis Command Details:\n\n";
        $output .= "ID: {$entry->id}\n";
        $output .= "Command: " . ($content['command'] ?? 'Unknown') . "\n";

        // Chave(s) - pode ser string ou array
        $key = 'N/A';
        if (isset($content['key'])) {
             $key = is_array($content['key']) ? json_encode($content['key'], JSON_UNESCAPED_UNICODE) : $content['key'];
        } elseif (isset($content['keys']) && is_array($content['keys'])) {
             $key = json_encode($content['keys'], JSON_UNESCAPED_UNICODE);
        }
        $output .= "Key(s): {$key}\n";

        // Valores (para comandos como SET, MSET, etc.)
        if (isset($content['value'])) {
            $output .= "Value: " . (is_array($content['value']) ? json_encode($content['value'], JSON_UNESCAPED_UNICODE) : $content['value']) . "\n";
        }

        $output .= "Duration: " . (isset($content['time']) ? number_format($content['time'], 2) . "ms" : 'Unknown') . "\n";
        $output .= "Connection: " . ($content['connection'] ?? 'default') . "\n";

        $createdAt = 'Unknown';
        if (property_exists($entry, 'created_at') && !empty($entry->created_at)) {
            if (is_object($entry->created_at) && method_exists($entry->created_at, 'format')) {
                $createdAt = $entry->created_at->format('Y-m-d H:i:s');
            } elseif (is_string($entry->created_at)) {
                $createdAt = $entry->created_at;
            }
        }
        $output .= "Created At: {$createdAt}\n\n";

        // TODO: Adicionar outros detalhes relevantes se existirem (ex: argumentos completos, opções)

        return $this->formatResponse($output);
    }
} 