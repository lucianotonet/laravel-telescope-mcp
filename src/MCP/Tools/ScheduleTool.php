<?php

namespace LucianoTonet\TelescopeMcp\MCP\Tools;

use Laravel\Telescope\Contracts\EntriesRepository;
use LucianoTonet\TelescopeMcp\Support\Logger;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\Storage\EntryQueryOptions;

class ScheduleTool extends AbstractTool
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
        return 'schedule';
    }

    /**
     * Retorna o esquema da ferramenta
     */
    public function getSchema()
    {
        return [
            'name' => $this->getName(),
            'description' => 'Lista e analisa tarefas agendadas (scheduled tasks) registradas pelo Telescope.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'id' => [
                        'type' => 'string',
                        'description' => 'ID da tarefa agendada específica para ver detalhes'
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Número máximo de tarefas agendadas a retornar',
                        'default' => 50
                    ],
                    'command' => [
                        'type' => 'string',
                        'description' => 'Filtrar por comando da tarefa agendada'
                    ],
                    'expression' => [
                        'type' => 'string',
                        'description' => 'Filtrar por expressão Cron da tarefa agendada'
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
            // Verificar se foi solicitado detalhes de uma tarefa agendada específica
            if ($this->hasId($params)) {
                return $this->getScheduledTaskDetails($params['id']);
            }

            return $this->listScheduledTasks($params);
        } catch (\Exception $e) {
            Logger::error($this->getName() . ' execution error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->formatError('Error: ' . $e->getMessage());
        }
    }

    /**
     * Lista as tarefas agendadas registradas pelo Telescope
     */
    protected function listScheduledTasks($params)
    {
        // Definir limite para a consulta
        $limit = isset($params['limit']) ? min((int)$params['limit'], 100) : 50;

        // Configurar opções
        $options = new EntryQueryOptions();
        $options->limit($limit);

        // Adicionar filtros se especificados
        if (!empty($params['command'])) {
            $options->tag('command:' . $params['command']);
        }
        if (!empty($params['expression'])) {
            $options->tag('expression:' . $params['expression']);
        }

        // Buscar entradas usando o repositório
        $entries = $this->entriesRepository->get(EntryType::SCHEDULED_TASK, $options);

        if (empty($entries)) {
            return $this->formatResponse("Nenhuma tarefa agendada encontrada.");
        }

        $scheduledTasks = [];

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
                        \LucianoTonet\TelescopeMcp\Support\Logger::warning('Failed to parse date in ScheduleTool::listScheduledTasks', [
                            'date_string' => $entry->created_at,
                            'entry_id' => $entry->id ?? 'N/A',
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }

            $scheduledTasks[] = [
                'id' => $entry->id,
                'command' => $content['command'] ?? 'Unknown',
                'expression' => $content['expression'] ?? 'Unknown',
                'description' => $content['description'] ?? 'N/A',
                'created_at' => $createdAt
            ];
        }

        // Formatação tabular para facilitar a leitura
        $table = "Scheduled Tasks:\n\n";
        $table .= sprintf("%-5s %-40s %-20s %-30s %-20s\n", "ID", "Command", "Expression", "Description", "Created At");
        $table .= str_repeat("-", 120) . "\n";

        foreach ($scheduledTasks as $task) {
             // Truncar campos longos
            $command = $task['command'];
            if (strlen($command) > 40) {
                $command = substr($command, 0, 37) . "...";
            }

            $description = $task['description'];
            if (strlen($description) > 30) {
                $description = substr($description, 0, 27) . "...";
            }

            $table .= sprintf(
                "%-5s %-40s %-20s %-30s %-20s\n",
                $task['id'],
                $command,
                $task['expression'],
                $description,
                $task['created_at']
            );
        }

        return $this->formatResponse($table);
    }

    /**
     * Obtém detalhes de uma tarefa agendada específica
     */
    protected function getScheduledTaskDetails($id)
    {
        Logger::info($this->getName() . ' getting details', ['id' => $id]);

        // Buscar a entrada específica
        $entry = $this->getEntryDetails(EntryType::SCHEDULED_TASK, $id);

        if (!$entry) {
            return $this->formatError("Tarefa agendada não encontrada: {$id}");
        }

        $content = is_array($entry->content) ? $entry->content : [];

        // Formatação detalhada da tarefa agendada
        $output = "Scheduled Task Details:\n\n";
        $output .= "ID: {$entry->id}\n";
        $output .= "Command: " . ($content['command'] ?? 'Unknown') . "\n";
        $output .= "Expression: " . ($content['expression'] ?? 'Unknown') . "\n";
        $output .= "Description: " . ($content['description'] ?? 'N/A') . "\n";

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
                    \LucianoTonet\TelescopeMcp\Support\Logger::warning('Failed to parse date in ScheduleTool::getScheduledTaskDetails', [
                        'date_string' => $entry->created_at,
                        'entry_id' => $entry->id ?? 'N/A',
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }
        $output .= "Created At: {$createdAt}\n\n";

        // Outros detalhes relevantes
        if (isset($content['user'])) {
            $output .= "User: " . ($content['user'] ?? 'N/A') . "\n";
        }
        if (isset($content['output'])) {
            $output .= "Output: " . ($content['output'] ?? 'N/A') . "\n";
        }
         if (isset($content['exit_code'])) {
            $output .= "Exit Code: " . ($content['exit_code'] ?? 'N/A') . "\n";
        }

        return $this->formatResponse($output);
    }
} 