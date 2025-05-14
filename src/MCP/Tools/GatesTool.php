<?php

namespace LucianoTonet\TelescopeMcp\MCP\Tools;

use Laravel\Telescope\Contracts\EntriesRepository;
use LucianoTonet\TelescopeMcp\Support\Logger;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\Storage\EntryQueryOptions;

class GatesTool extends AbstractTool
{
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
        return 'gates';
    }

    /**
     * Retorna o esquema da ferramenta
     */
    public function getSchema()
    {
        return [
            'name' => $this->getName(),
            'description' => 'Lista e analisa avaliações de gates de autorização registradas pelo Telescope.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'id' => [
                        'type' => 'string',
                        'description' => 'ID da avaliação de gate específica para ver detalhes'
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Número máximo de avaliações de gate a retornar',
                        'default' => 50
                    ],
                    'ability' => [
                        'type' => 'string',
                        'description' => 'Filtrar por nome da habilidade'
                    ],
                    'result' => [
                        'type' => 'boolean',
                        'description' => 'Filtrar por resultado (true para permitido, false para negado)'
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
            // Verificar se foi solicitado detalhes de uma avaliação de gate específica
            if ($this->hasId($params)) {
                return $this->getGateDetails($params['id']);
            }

            return $this->listGateEvaluations($params);
        } catch (\Exception $e) {
            Logger::error($this->getName() . ' execution error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->formatError('Error: ' . $e->getMessage());
        }
    }

    /**
     * Lista as avaliações de gates registradas pelo Telescope
     */
    protected function listGateEvaluations($params)
    {
        // Definir limite para a consulta
        $limit = isset($params['limit']) ? min((int)$params['limit'], 100) : 50;

        // Configurar opções
        $options = new EntryQueryOptions();
        $options->limit($limit);

        // Adicionar filtros se especificados
        if (!empty($params['ability'])) {
            $options->tag($params['ability']);
        }
        if (isset($params['result'])) {
            $options->tag('result:' . ($params['result'] ? 'allowed' : 'denied'));
        }

        // Buscar entradas usando o repositório
        $entries = $this->entriesRepository->get(EntryType::GATE, $options);

        if (empty($entries)) {
            return $this->formatResponse("Nenhuma avaliação de gate encontrada.");
        }

        $gateEvaluations = [];

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

            $gateEvaluations[] = [
                'id' => $entry->id,
                'ability' => $content['ability'] ?? 'Unknown',
                'result' => $content['result'] ?? 'Unknown', // true/false/null
                'arguments' => $content['arguments'] ?? [],
                'created_at' => $createdAt
            ];
        }

        // Formatação tabular para facilitar a leitura
        $table = "Gate Evaluations:\n\n";
        $table .= sprintf("%-5s %-40s %-8s %-20s\n", "ID", "Ability", "Result", "Created At");
        $table .= str_repeat("-", 80) . "\n";

        foreach ($gateEvaluations as $evaluation) {
            // Truncar ability se muito longa
            $ability = $evaluation['ability'];
            if (strlen($ability) > 40) {
                $ability = substr($ability, 0, 37) . "...";
            }

            $result = 'Unknown';
            if ($evaluation['result'] === true) {
                $result = 'Allowed';
            } elseif ($evaluation['result'] === false) {
                $result = 'Denied';
            }

            $table .= sprintf(
                "%-5s %-40s %-8s %-20s\n",
                $evaluation['id'],
                $ability,
                $result,
                $evaluation['created_at']
            );
        }

        return $this->formatResponse($table);
    }

    /**
     * Obtém detalhes de uma avaliação de gate específica
     */
    protected function getGateDetails($id)
    {
        Logger::info($this->getName() . ' getting details', ['id' => $id]);

        // Buscar a entrada específica
        $entry = $this->getEntryDetails(EntryType::GATE, $id);

        if (!$entry) {
            return $this->formatError("Avaliação de gate não encontrada: {$id}");
        }

        $content = is_array($entry->content) ? $entry->content : [];

        // Formatação detalhada da avaliação de gate
        $output = "Gate Evaluation Details:\n\n";
        $output .= "ID: {$entry->id}\n";
        $output .= "Ability: " . ($content['ability'] ?? 'Unknown') . "\n";

        $result = 'Unknown';
        if (($content['result'] ?? null) === true) {
            $result = 'Allowed';
        } elseif (($content['result'] ?? null) === false) {
            $result = 'Denied';
        }
        $output .= "Result: {$result}\n";

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
            $output .= "Arguments:\n" . json_encode($content['arguments'], JSON_PRETTY_PRINT) . "\n";
        }

        // User
        if (isset($content['user']) && !empty($content['user'])) {
            $output .= "User:\n" . json_encode($content['user'], JSON_PRETTY_PRINT) . "\n";
        }

        return $this->formatResponse($output);
    }
} 