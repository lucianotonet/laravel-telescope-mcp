<?php

namespace LucianoTonet\TelescopeMcp\MCP\Tools;

use Laravel\Telescope\Contracts\EntriesRepository;
use LucianoTonet\TelescopeMcp\Support\Logger;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\Storage\EntryQueryOptions;

class CommandsTool extends AbstractTool
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
        return 'commands';
    }

    public function getSchema()
    {
        return [
            'name' => $this->getName(),
            'description' => 'Lista e analisa comandos de console registrados pelo Telescope.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'id' => [
                        'type' => 'string',
                        'description' => 'ID do comando específico para ver detalhes'
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Número máximo de comandos a retornar',
                        'default' => 50
                    ],
                    'command' => [
                        'type' => 'string',
                        'description' => 'Filtrar por nome do comando'
                    ],
                    'exit_code' => [
                        'type' => 'integer',
                        'description' => 'Filtrar por código de saída do comando'
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
            // Verificar se foi solicitado detalhes de um comando específico
            if ($this->hasId($params)) {
                return $this->getCommandDetails($params['id']);
            }

            return $this->listCommands($params);
        } catch (\Exception $e) {
            Logger::error($this->getName() . ' execution error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->formatError('Error: ' . $e->getMessage());
        }
    }

    /**
     * Lista os comandos de console registrados pelo Telescope
     */
    protected function listCommands($params)
    {
        // Definir limite para a consulta
        $limit = isset($params['limit']) ? min((int)$params['limit'], 100) : 50;

        // Configurar opções
        $options = new EntryQueryOptions();
        $options->limit($limit);

        // Adicionar filtros se especificados
        if (!empty($params['command'])) {
            $options->tag($params['command']);
        }
        // TODO: Adicionar filtro por exit_code se necessário. Requer explorar como o Telescope armazena isso.
        // if (isset($params['exit_code'])) {
        //     $options->tag('exit:' . $params['exit_code']);
        // }

        // Buscar entradas usando o repositório
        $entries = $this->entriesRepository->get(EntryType::COMMAND, $options);

        if (empty($entries)) {
            return $this->formatResponse("Nenhum comando encontrado.");
        }

        $commands = [];

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

            $commands[] = [
                'id' => $entry->id,
                'command' => $content['command'] ?? 'Unknown',
                'exit_code' => $content['exit_code'] ?? 'N/A',
                'duration' => $content['duration'] ?? 0,
                'created_at' => $createdAt
            ];
        }

        // Formatação tabular para facilitar a leitura
        $table = "Commands:\n\n";
        $table .= sprintf("%-5s %-40s %-10s %-10s %-20s\n", "ID", "Command", "Exit Code", "Duration", "Created At");
        $table .= str_repeat("-", 90) . "\n";

        foreach ($commands as $cmd) {
            // Truncar comando longo
            $command = $cmd['command'];
            if (strlen($command) > 40) {
                $command = substr($command, 0, 37) . "...";
            }

            $table .= sprintf(
                "%-5s %-40s %-10s %-10s %-20s\n",
                $cmd['id'],
                $command,
                $cmd['exit_code'],
                number_format($cmd['duration'], 2) . "ms",
                $cmd['created_at']
            );
        }

        return $this->formatResponse($table);
    }

    /**
     * Obtém detalhes de um comando específico
     */
    protected function getCommandDetails($id)
    {
        Logger::info($this->getName() . ' getting details', ['id' => $id]);

        // Buscar a entrada específica
        $entry = $this->getEntryDetails(EntryType::COMMAND, $id);

        if (!$entry) {
            return $this->formatError("Comando não encontrado: {$id}");
        }

        $content = is_array($entry->content) ? $entry->content : [];

        // Formatação detalhada do comando
        $output = "Command Details:\n\n";
        $output .= "ID: {$entry->id}\n";
        $output .= "Command: " . ($content['command'] ?? 'Unknown') . "\n";
        $output .= "Exit Code: " . ($content['exit_code'] ?? 'N/A') . "\n";
        $output .= "Duration: " . (isset($content['duration']) ? number_format($content['duration'], 2) . "ms" : 'Unknown') . "\n";
        $output .= "Memory: " . (isset($content['memory']) ? number_format($content['memory'] / 1024 / 1024, 2) . "MB" : 'Unknown') . "\n";

        $createdAt = 'Unknown';
        if (property_exists($entry, 'created_at') && !empty($entry->created_at)) {
            if (is_object($entry->created_at) && method_exists($entry->created_at, 'format')) {
                $createdAt = $entry->created_at->format('Y-m-d H:i:s');
            } elseif (is_string($entry->created_at)) {
                $createdAt = $entry->created_at;
            }
        }
        $output .= "Created At: {$createdAt}\n\n";

        // Argumentos
        if (isset($content['arguments']) && !empty($content['arguments'])) {
            $output .= "Arguments:\n";
            foreach ($content['arguments'] as $key => $value) {
                $output .= "- {$key}: " . (is_array($value) ? json_encode($value) : $value) . "\n";
            }
            $output .= "\n";
        }

        // Opções
        if (isset($content['options']) && !empty($content['options'])) {
            $output .= "Options:\n";
            foreach ($content['options'] as $key => $value) {
                $output .= "- {$key}: " . (is_array($value) ? json_encode($value) : $value) . "\n";
            }
            $output .= "\n";
        }

        return $this->formatResponse($output);
    }
} 