<?php

namespace LucianoTonet\TelescopeMcp\MCP\Tools;

use Laravel\Telescope\Contracts\EntriesRepository;
use LucianoTonet\TelescopeMcp\Support\Logger;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\Storage\EntryQueryOptions;

class DumpsTool extends AbstractTool
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
        return 'dumps';
    }

    /**
     * Retorna o esquema da ferramenta
     */
    public function getSchema()
    {
        return [
            'name' => $this->getName(),
            'description' => 'Lista e analisa dumps (saídas dd()) registrados pelo Telescope.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'id' => [
                        'type' => 'string',
                        'description' => 'ID do dump específico para ver detalhes'
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Número máximo de dumps a retornar',
                        'default' => 50
                    ],
                    // TODO: Adicionar filtros específicos para dumps se necessário (ex: por conteúdo)
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
            // Verificar se foi solicitado detalhes de um dump específico
            if ($this->hasId($params)) {
                return $this->getDumpDetails($params['id']);
            }

            return $this->listDumps($params);
        } catch (\Exception $e) {
            Logger::error($this->getName() . ' execution error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->formatError('Error: ' . $e->getMessage());
        }
    }

    /**
     * Lista os dumps registrados pelo Telescope
     */
    protected function listDumps($params)
    {
        // Definir limite para a consulta
        $limit = isset($params['limit']) ? min((int)$params['limit'], 100) : 50;

        // Configurar opções
        $options = new EntryQueryOptions();
        $options->limit($limit);

        // TODO: Adicionar filtros específicos para dumps se necessário

        // Buscar entradas usando o repositório
        $entries = $this->entriesRepository->get(EntryType::DUMP, $options);

        if (empty($entries)) {
            return $this->formatResponse("Nenhum dump encontrado.");
        }

        $dumps = [];

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
                        \LucianoTonet\TelescopeMcp\Support\Logger::warning('Failed to parse date in DumpsTool::listDumps', [
                            'date_string' => $entry->created_at,
                            'entry_id' => $entry->id ?? 'N/A',
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }

            // TODO: Extrair informações relevantes do dump (ex: conteúdo, arquivo, linha)
            $file = $content['file'] ?? 'Unknown';
            $line = $content['line'] ?? 'Unknown';
            $htmlDump = $content['html_dump'] ?? 'No content'; // O dump formatado em HTML
            $abstract = substr(strip_tags($htmlDump), 0, 100) . (strlen(strip_tags($htmlDump)) > 100 ? '...' : ''); // Exibir um resumo

            $dumps[] = [
                'id' => $entry->id,
                'file' => $file,
                'line' => $line,
                'abstract' => $abstract,
                'created_at' => $createdAt
            ];
        }

        // Formatação tabular para facilitar a leitura
        $table = "Dumps:\n\n";
        $table .= sprintf("%-5s %-40s %-8s %-50s %-20s\n", "ID", "File", "Line", "Abstract", "Created At");
        $table .= str_repeat("-", 125) . "\n"; // Ajustar largura da linha separadora

        foreach ($dumps as $dump) {
             // Truncar file se muito longo
            $file = $dump['file'];
            if (strlen($file) > 40) {
                $file = "..." . substr($file, -37);
            }

            $table .= sprintf(
                "%-5s %-40s %-8s %-50s %-20s\n",
                $dump['id'],
                $file,
                $dump['line'],
                $dump['abstract'],
                $dump['created_at']
            );
        }

        return $this->formatResponse($table);
    }

    /**
     * Obtém detalhes de um dump específico
     */
    protected function getDumpDetails($id)
    {
        // Implemente a lógica para obter detalhes de um dump específico com base no ID
        // Isso pode envolver buscar o dump no repositório ou em outra fonte de dados
        // Retorne um array com os detalhes do dump

        // Exemplo de como obter a entrada e tratar created_at (a ser adaptado):
        /*
        Logger::info($this->getName() . ' getting details', ['id' => $id]);
        $entry = $this->getEntryDetails(EntryType::DUMP, $id);

        if (!$entry) {
            return $this->formatError("Dump não encontrado: {$id}");
        }

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
                    \LucianoTonet\TelescopeMcp\Support\Logger::warning('Failed to parse date in DumpsTool::getDumpDetails', [
                        'date_string' => $entry->created_at,
                        'entry_id' => $entry->id ?? 'N/A',
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        $output = "Dump Details:\n\n";
        $output .= "ID: {$entry->id}\n";
        $output .= "Created At: {$createdAt}\n";
        // Adicionar outros detalhes do dump
        $output .= "Content: " . json_encode($content, JSON_PRETTY_PRINT) . "\n";

        return $this->formatResponse($output);
        */
        return $this->formatError("getDumpDetails not fully implemented yet.");
    }
} 