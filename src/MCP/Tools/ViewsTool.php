<?php

namespace LucianoTonet\TelescopeMcp\MCP\Tools;

use Laravel\Telescope\Contracts\EntriesRepository;
use LucianoTonet\TelescopeMcp\Support\Logger;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\Storage\EntryQueryOptions;

class ViewsTool extends AbstractTool
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
        return 'views';
    }

    /**
     * Retorna o esquema da ferramenta
     */
    public function getSchema()
    {
        return [
            'name' => $this->getName(),
            'description' => 'Lista e analisa renderizações de views registradas pelo Telescope.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'id' => [
                        'type' => 'string',
                        'description' => 'ID da renderização de view específica para ver detalhes'
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Número máximo de renderizações de view a retornar',
                        'default' => 50
                    ],
                    'name' => [
                        'type' => 'string',
                        'description' => 'Filtrar por nome da view'
                    ],
                    // TODO: Adicionar outros filtros específicos para views se necessário (ex: dados)
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
            // Verificar se foi solicitado detalhes de uma renderização de view específica
            if ($this->hasId($params)) {
                return $this->getViewDetails($params['id']);
            }

            return $this->listViews($params);
        } catch (\Exception $e) {
            Logger::error($this->getName() . ' execution error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->formatError('Error: ' . $e->getMessage());
        }
    }

    /**
     * Lista as renderizações de view registradas pelo Telescope
     */
    protected function listViews($params)
    {
        // Definir limite para a consulta
        $limit = isset($params['limit']) ? min((int)$params['limit'], 100) : 50;

        // Configurar opções
        $options = new EntryQueryOptions();
        $options->limit($limit);

        // Adicionar filtros se especificados
        if (!empty($params['name'])) {
            $options->tag($params['name']);
        }

        // Buscar entradas usando o repositório
        $entries = $this->entriesRepository->get(EntryType::VIEW, $options);

        if (empty($entries)) {
            return $this->formatResponse("Nenhuma renderização de view encontrada.");
        }

        $viewRenderings = [];

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
                        \LucianoTonet\TelescopeMcp\Support\Logger::warning('Failed to parse date in ViewsTool::listViews', [
                            'date_string' => $entry->created_at,
                            'entry_id' => $entry->id ?? 'N/A',
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }

            $viewRenderings[] = [
                'id' => $entry->id,
                'name' => $content['name'] ?? 'Unknown',
                'path' => $content['path'] ?? 'Unknown',
                'duration' => $content['render_time'] ?? 0, // Tempo de renderização
                'created_at' => $createdAt
            ];
        }

        // Formatação tabular para facilitar a leitura
        $table = "View Renderings:\n\n";
        $table .= sprintf("%-5s %-40s %-40s %-15s %-20s\n", "ID", "Name", "Path", "Render Time (ms)", "Created At");
        $table .= str_repeat("-", 120) . "\n";

        foreach ($viewRenderings as $view) {
             // Truncar campos longos
            $name = $view['name'];
            if (strlen($name) > 40) {
                $name = substr($name, 0, 37) . "...";
            }

            $path = $view['path'];
            if (strlen($path) > 40) {
                $path = "..." . substr($path, -37);
            }

            $table .= sprintf(
                "%-5s %-40s %-40s %-15s %-20s\n",
                $view['id'],
                $name,
                $path,
                number_format($view['duration'], 2),
                $view['created_at']
            );
        }

        return $this->formatResponse($table);
    }

    /**
     * Obtém detalhes de uma renderização de view específica
     */
    protected function getViewDetails($id)
    {
        Logger::info($this->getName() . ' getting details', ['id' => $id]);

        // Buscar a entrada específica
        $entry = $this->getEntryDetails(EntryType::VIEW, $id);

        if (!$entry) {
            return $this->formatError("Renderização de view não encontrada: {$id}");
        }

        $content = is_array($entry->content) ? $entry->content : [];

        // Formatação detalhada da renderização de view
        $output = "View Rendering Details:\n\n";
        $output .= "ID: {$entry->id}\n";
        $output .= "Name: " . ($content['name'] ?? 'Unknown') . "\n";
        $output .= "Path: " . ($content['path'] ?? 'Unknown') . "\n";
        $output .= "Render Time: " . (isset($content['render_time']) ? number_format($content['render_time'], 2) . "ms" : 'Unknown') . "\n";

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
                    \LucianoTonet\TelescopeMcp\Support\Logger::warning('Failed to parse date in ViewsTool::getViewDetails', [
                        'date_string' => $entry->created_at,
                        'entry_id' => $entry->id ?? 'N/A',
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }
        $output .= "Created At: {$createdAt}\n\n";

        // Dados passados para a view
        if (isset($content['data']) && !empty($content['data'])) {
            $output .= "Data:\n" . json_encode($content['data'], JSON_PRETTY_PRINT) . "\n";
        }

        return $this->formatResponse($output);
    }
} 